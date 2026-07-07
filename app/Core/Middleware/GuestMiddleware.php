<?php

declare(strict_types=1);

namespace App\Core\Middleware;

class GuestMiddleware
{
    public function handle(callable $next): mixed
    {
        $this->startSession();

        $admin = $_SESSION['admin'] ?? null;
        $expiresAt = (int) ($_SESSION['admin_expires_at'] ?? 0);
        if (is_array($admin) && $expiresAt > time()) {
            header('Location: /admin');
            return null;
        }

        return $next();
    }

    private function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}