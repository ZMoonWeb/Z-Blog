<?php

declare(strict_types=1);

namespace App\Core\Security;

use App\Core\Config;

class SessionManager
{
    public static function start(?string $name = null, ?int $ttl = null, array $options = []): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::regenerateIfNeeded();
            return;
        }

        $ttl = $ttl ?? (int) Config::get('session.ttl', 7200);
        if ($ttl > 0) {
            ini_set('session.gc_maxlifetime', (string) $ttl);
        }

        if (is_string($name) && $name !== '') {
            session_name($name);
        }

        session_set_cookie_params(array_merge(self::cookieOptions($ttl), $options));
        session_start();
        self::regenerateIfNeeded();
    }

    public static function startAdmin(): void
    {
        self::start(
            (string) Config::get('session.admin_name', 'zblog_admin_session'),
            (int) Config::get('session.ttl', 7200)
        );
    }

    public static function regenerateIfNeeded(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $interval = (int) Config::get('session.regenerate_interval', 900);
        if ($interval <= 0) {
            return;
        }

        $lastRegeneratedAt = (int) ($_SESSION['_session_regenerated_at'] ?? 0);
        if ($lastRegeneratedAt > 0 && time() - $lastRegeneratedAt < $interval) {
            return;
        }

        session_regenerate_id(true);
        $_SESSION['_session_regenerated_at'] = time();
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => (bool) ($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }

    private static function cookieOptions(int $ttl): array
    {
        $secure = Config::get('session.secure', null);

        return [
            'lifetime' => $ttl,
            'path' => '/',
            'domain' => '',
            'secure' => $secure === null ? self::isSecureRequest() : (bool) $secure,
            'httponly' => (bool) Config::get('session.http_only', true),
            'samesite' => (string) Config::get('session.same_site', 'Lax'),
        ];
    }

    private static function isSecureRequest(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}
