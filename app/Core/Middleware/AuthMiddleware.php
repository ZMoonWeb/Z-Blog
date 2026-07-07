<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Models\Admin;

class AuthMiddleware
{
    private const ADMIN_SESSION_TTL = 86400;
    private const ADMIN_SESSION_REGENERATE_INTERVAL = 900;

    public function handle(callable $next): mixed
    {
        $this->startSession();

        $invalidReason = null;
        if ($this->isLoggedIn($invalidReason)) {
            return $next();
        }

        $this->clearAdminSession();
        if ($invalidReason === 'data') {
            $_SESSION['admin_login_notice'] = '数据异常，请重新登录';
        }

        if ($this->wantsJson()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'type' => 'error',
                'message' => $invalidReason === 'data' ? '数据异常，请重新登录' : '请重新登录',
                'login_url' => '/admin/login' . ($invalidReason === 'data' ? '?notice=data' : ''),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return null;
        }

        header('Location: /admin/login' . ($invalidReason === 'data' ? '?notice=data' : ''));

        return null;
    }

    private function isLoggedIn(?string &$invalidReason = null): bool
    {
        $invalidReason = null;

        if (!isset($_SESSION['admin']) || !is_array($_SESSION['admin'])) {
            return false;
        }

        $adminId = (int) ($_SESSION['admin']['id'] ?? 0);
        $sessionUsername = trim((string) ($_SESSION['admin']['username'] ?? ''));
        $expiresAt = (int) ($_SESSION['admin_expires_at'] ?? 0);

        if ($adminId <= 0) {
            $invalidReason = 'data';
            return false;
        }

        if ($expiresAt <= time()) {
            return false;
        }

        $admin = Admin::findById($adminId);
        if ($admin === null) {
            $invalidReason = 'data';
            return false;
        }

        $databaseUsername = trim((string) ($admin['username'] ?? ''));
        if ($databaseUsername === '' || ($sessionUsername !== '' && !hash_equals($databaseUsername, $sessionUsername))) {
            $invalidReason = 'data';
            return false;
        }

        $_SESSION['admin'] = [
            'id' => (int) $admin['id'],
            'username' => $databaseUsername,
        ];

        $lastRegeneratedAt = (int) ($_SESSION['admin_last_regenerated_at'] ?? 0);
        if ($lastRegeneratedAt <= 0 || time() - $lastRegeneratedAt >= self::ADMIN_SESSION_REGENERATE_INTERVAL) {
            session_regenerate_id(true);
            $_SESSION['admin_last_regenerated_at'] = time();
        }

        return true;
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.gc_maxlifetime', (string) self::ADMIN_SESSION_TTL);
        $params = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => self::ADMIN_SESSION_TTL,
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => true,
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
        session_start();
    }

    private function clearAdminSession(): void
    {
        unset(
            $_SESSION['admin'],
            $_SESSION['admin_login_at'],
            $_SESSION['admin_expires_at'],
            $_SESSION['admin_last_regenerated_at'],
            $_SESSION['admin_ip_address'],
            $_SESSION['admin_user_agent']
        );
    }

    private function wantsJson(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

        return str_contains($accept, 'application/json')
            || $requestedWith === 'xmlhttprequest'
            || str_starts_with($requestPath, '/admin/api/');
    }
}