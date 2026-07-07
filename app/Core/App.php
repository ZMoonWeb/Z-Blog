<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Security\SecurityHeaders;

class App
{
    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = rtrim($basePath ?? dirname(__DIR__, 2), '/\\');
    }

    public function run(): void
    {
        $this->handle(Request::capture())->send();
    }

    public function handle(Request $request): Response
    {
        SecurityHeaders::sendSecurityHeaders();

        $router = new Router();
        ob_start();

        try {
            $router->dispatch();
            $content = (string) ob_get_clean();
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            throw $e;
        }
        $statusCode = http_response_code();
        if (!is_int($statusCode) || $statusCode < 100) {
            $statusCode = 200;
        }

        return new Response($content, $statusCode);
    }

    public function basePath(): string
    {
        return $this->basePath;
    }
}
