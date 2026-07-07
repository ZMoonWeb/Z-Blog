<?php

declare(strict_types=1);

namespace App\Core\Security;

class RateLimiter
{
    private const SESSION_KEY = '_rate_limits';

    public static function hit(string $key, int $decaySeconds = 60): int
    {
        SessionManager::start();
        self::prune();

        $key = self::normalizeKey($key);
        $now = time();

        if (!isset($_SESSION[self::SESSION_KEY][$key])) {
            $_SESSION[self::SESSION_KEY][$key] = [
                'count' => 0,
                'expires_at' => $now + $decaySeconds,
            ];
        }

        if ((int) $_SESSION[self::SESSION_KEY][$key]['expires_at'] <= $now) {
            $_SESSION[self::SESSION_KEY][$key] = [
                'count' => 0,
                'expires_at' => $now + $decaySeconds,
            ];
        }

        $_SESSION[self::SESSION_KEY][$key]['count']++;

        return (int) $_SESSION[self::SESSION_KEY][$key]['count'];
    }

    public static function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        SessionManager::start();
        self::prune();

        $record = $_SESSION[self::SESSION_KEY][self::normalizeKey($key)] ?? null;

        return is_array($record) && (int) ($record['count'] ?? 0) >= $maxAttempts;
    }

    public static function clear(string $key): void
    {
        SessionManager::start();
        unset($_SESSION[self::SESSION_KEY][self::normalizeKey($key)]);
    }

    private static function prune(): void
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
            return;
        }

        $now = time();
        foreach ($_SESSION[self::SESSION_KEY] as $key => $record) {
            if (!is_array($record) || (int) ($record['expires_at'] ?? 0) <= $now) {
                unset($_SESSION[self::SESSION_KEY][$key]);
            }
        }
    }

    private static function normalizeKey(string $key): string
    {
        return hash('sha256', $key);
    }
}
