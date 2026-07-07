<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Repositories\AdminRepository;

class AdminAuthService
{
    private const ADMIN_SESSION_TTL = 86400;
    private const ADMIN_SESSION_REGENERATE_INTERVAL = 900;

    public function __construct(private ?AdminRepository $admins = null)
    {
        $this->admins ??= new AdminRepository();
    }

    public function currentAdmin(): ?array
    {
        $admin = $_SESSION['admin'] ?? null;

        return is_array($admin) ? $admin : null;
    }

    public function hasAdminSession(): bool
    {
        return $this->currentAdmin() !== null;
    }

    public function isLoggedIn(?string &$invalidReason = null): bool
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

        $admin = $this->admins->findById($adminId);
        if ($admin === null) {
            $invalidReason = 'data';
            return false;
        }

        $databaseUsername = (string) ($admin['username'] ?? '');
        if ($sessionUsername === '' || $databaseUsername === '' || $sessionUsername !== $databaseUsername) {
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

    public function authenticate(string $username, string $password): ?array
    {
        $admin = $this->admins->findByUsername($username);

        if ($admin === null || !password_verify($password, (string) $admin['password'])) {
            return null;
        }

        if ($this->admins->passwordNeedsRehash((string) $admin['password'])) {
            $this->admins->updatePasswordHash((int) $admin['id'], $this->admins->hashPassword($password));
        }

        return $admin;
    }

    public function storeAdminSession(array $admin, string $ipAddress, string $userAgent): void
    {
        session_regenerate_id(true);

        $_SESSION['admin'] = [
            'id' => (int) $admin['id'],
            'username' => $admin['username'],
        ];
        $_SESSION['admin_login_at'] = time();
        $_SESSION['admin_expires_at'] = time() + self::ADMIN_SESSION_TTL;
        $_SESSION['admin_last_regenerated_at'] = time();
        $_SESSION['admin_ip_address'] = $ipAddress;
        $_SESSION['admin_user_agent'] = $userAgent;
    }

    public function clearAdminSession(): void
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

    public function pullAdminLoginNotice(): ?string
    {
        $notice = $_SESSION['admin_login_notice'] ?? null;
        unset($_SESSION['admin_login_notice']);

        if (!is_string($notice)) {
            return null;
        }

        $notice = trim($notice);
        return $notice !== '' ? $notice : null;
    }

    public function rememberAdminLoginNoticeCookie(string $notice): void
    {
        $this->setAdminLoginNoticeCookie($notice, time() + 300);
    }

    public function clearAdminLoginNoticeCookie(): void
    {
        $this->setAdminLoginNoticeCookie('', time() - 3600);
    }

    public function clientIp(): string
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($ip === '' || strlen($ip) > 45) {
            return 'unknown';
        }

        return $ip;
    }

    public function userAgent(): string
    {
        return mb_substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255, 'UTF-8');
    }

    public function formatLockRemaining(int $seconds): string
    {
        $seconds = max(1, $seconds);
        $minutes = (int) ceil($seconds / 60);

        if ($minutes >= 1) {
            return $minutes . ' 分钟';
        }

        return $seconds . ' 秒';
    }

    private function setAdminLoginNoticeCookie(string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie('admin_login_notice', $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
