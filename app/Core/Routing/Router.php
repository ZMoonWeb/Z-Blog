<?php

declare(strict_types=1);

namespace App\Core\Routing;

class Router
{
    /** @var array<int, Route> */
    private array $routes = [];
    private array $groupStack = [];

    public function get(string $pattern, mixed $action): Route
    {
        return $this->add('GET', $pattern, $action);
    }

    public function post(string $pattern, mixed $action): Route
    {
        return $this->add('POST', $pattern, $action);
    }

    public function match(array|string $methods, string $pattern, mixed $action): Route
    {
        return $this->add($methods, $pattern, $action);
    }

    public function any(string $pattern, mixed $action): Route
    {
        return $this->add('ANY', $pattern, $action);
    }

    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $this->groupStack[] = [
            'prefix' => '/' . trim($prefix, '/'),
            'middleware' => $middleware,
        ];

        $callback($this);

        array_pop($this->groupStack);
    }

    public function dispatch(string $method, string $path): bool
    {
        foreach ($this->routes as $route) {
            $parameters = [];
            if (!$route->matches($method, $path, $parameters)) {
                continue;
            }

            $this->runRoute($route, $parameters);

            return true;
        }

        return false;
    }

    private function add(array|string $methods, string $pattern, mixed $action): Route
    {
        $route = new Route($methods, $this->prefixPattern($pattern), $action);
        $middleware = $this->groupMiddleware();
        if ($middleware !== []) {
            $route->middleware($middleware);
        }

        $this->routes[] = $route;

        return $route;
    }

    private function runRoute(Route $route, array $parameters): void
    {
        $pipeline = new MiddlewarePipeline($route->middlewareList());
        $pipeline->handle(static function () use ($route, $parameters): void {
            $action = $route->action();

            if (is_array($action) && isset($action[0], $action[1])) {
                $controller = new $action[0]();
                $controller->{$action[1]}(...array_values($parameters));
                return;
            }

            if (is_callable($action)) {
                $action(...array_values($parameters));
                return;
            }

            throw new \RuntimeException('Invalid route action for ' . $route->pattern());
        });
    }

    private function prefixPattern(string $pattern): string
    {
        $prefix = '';
        foreach ($this->groupStack as $group) {
            $prefix .= '/' . trim((string) $group['prefix'], '/');
        }

        $pattern = '/' . trim($pattern, '/');
        $fullPattern = '/' . trim($prefix . $pattern, '/');

        return $fullPattern === '/' ? '/' : $fullPattern;
    }

    private function groupMiddleware(): array
    {
        $middleware = [];
        foreach ($this->groupStack as $group) {
            $middleware = array_merge($middleware, (array) $group['middleware']);
        }

        return $middleware;
    }
}