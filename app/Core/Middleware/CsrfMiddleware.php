<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Config;
use App\Core\Security\Csrf;

class CsrfMiddleware
{
    public function handle(callable $next): mixed
    {
        if (!(bool) Config::get('security.csrf', true)) {
            return $next();
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next();
        }

        if (Csrf::validate()) {
            return $next();
        }

        http_response_code(419);

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        if (str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'type' => 'error',
                'message' => '页面已过期，请刷新后重试',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return null;
        }

        echo '页面已过期，请刷新后重试';

        return null;
    }
}