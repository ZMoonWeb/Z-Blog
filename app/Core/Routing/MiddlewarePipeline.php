<?php

declare(strict_types=1);

namespace App\Core\Routing;

class MiddlewarePipeline
{
    private array $middleware;

    public function __construct(array $middleware = [])
    {
        $this->middleware = $middleware;
    }

    public function handle(callable $destination): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            static function (callable $next, mixed $middleware): callable {
                return static function () use ($middleware, $next): mixed {
                    if (is_callable($middleware)) {
                        return $middleware($next);
                    }

                    if (is_string($middleware) && class_exists($middleware)) {
                        $instance = new $middleware();
                        if (method_exists($instance, 'handle')) {
                            return $instance->handle($next);
                        }
                    }

                    return $next();
                };
            },
            $destination
        );

        return $pipeline();
    }
}
