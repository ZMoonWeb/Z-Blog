<?php

declare(strict_types=1);

namespace App\Core\Routing;

class Route
{
    private array $methods;
    private string $pattern;
    private mixed $action;
    private array $middleware = [];
    private array $parameterNames = [];
    private string $compiledPattern;

    public function __construct(array|string $methods, string $pattern, mixed $action)
    {
        $this->methods = array_map('strtoupper', (array) $methods);
        $this->pattern = $this->normalizePattern($pattern);
        $this->action = $action;
        $this->compiledPattern = $this->compilePattern($this->pattern);
    }

    public function middleware(array|string $middleware): self
    {
        $this->middleware = array_values(array_merge($this->middleware, (array) $middleware));

        return $this;
    }

    public function matches(string $method, string $path, array &$parameters = []): bool
    {
        $method = strtoupper($method);
        $path = $this->normalizePattern($path);

        if (!$this->allowsMethod($method)) {
            return false;
        }

        if (preg_match($this->compiledPattern, $path, $matches) !== 1) {
            return false;
        }

        $parameters = [];
        foreach ($this->parameterNames as $index => $name) {
            $parameters[$name] = rawurldecode((string) ($matches[$index + 1] ?? ''));
        }

        return true;
    }

    public function action(): mixed
    {
        return $this->action;
    }

    public function middlewareList(): array
    {
        return $this->middleware;
    }

    public function pattern(): string
    {
        return $this->pattern;
    }

    private function allowsMethod(string $method): bool
    {
        if (in_array('ANY', $this->methods, true)) {
            return true;
        }

        if ($method === 'HEAD' && in_array('GET', $this->methods, true)) {
            return true;
        }

        return in_array($method, $this->methods, true);
    }

    private function normalizePattern(string $pattern): string
    {
        $pattern = '/' . trim($pattern, '/');

        return $pattern === '/' ? '/' : rtrim($pattern, '/');
    }

    private function compilePattern(string $pattern): string
    {
        $this->parameterNames = [];
        $regex = '';
        $offset = 0;

        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)(?::([^}]+))?\}/', $pattern, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $index => $match) {
            [$placeholder, $position] = $match;
            $constraint = $matches[2][$index][0] ?? '';

            $regex .= preg_quote(substr($pattern, $offset, $position - $offset), '#');
            $this->parameterNames[] = $matches[1][$index][0];
            $regex .= '(' . ($constraint !== '' ? $constraint : '[^/]+') . ')';
            $offset = $position + strlen($placeholder);
        }

        $regex .= preg_quote(substr($pattern, $offset), '#');

        return '#^' . $regex . '$#u';
    }
}