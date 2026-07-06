<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class AdminLoginAttempt
{
    public static function createTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS admin_login_attempts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            locked_until DATETIME DEFAULT NULL,
            last_attempt_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_admin_login_ip (ip_address),
            INDEX idx_admin_login_locked_until (locked_until),
            INDEX idx_admin_login_last_attempt_at (last_attempt_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
    }

    /**
     * @return array{locked: bool, attempts: int, remaining_seconds: int}
     */
    public static function status(string $ipAddress): array
    {
        self::createTable();

        $row = self::find($ipAddress);
        if ($row === null) {
            return ['locked' => false, 'attempts' => 0, 'remaining_seconds' => 0];
        }

        return self::statusFromRow($row);
    }

    /**
     * @return array{locked: bool, attempts: int, remaining_seconds: int}
     */
    public static function recordFailure(string $ipAddress, int $maxAttempts, int $lockSeconds, int $windowSeconds): array
    {
        self::createTable();

        $now = new \DateTimeImmutable('now', self::timezone());
        $row = self::find($ipAddress);
        if ($row !== null) {
            $status = self::statusFromRow($row);
            if ($status['locked']) {
                return $status;
            }

            $lockedUntil = self::parseDate((string) ($row['locked_until'] ?? ''));
            if ($lockedUntil !== null && $lockedUntil->getTimestamp() <= $now->getTimestamp()) {
                $attempts = 0;
            } else {
                $lastAttemptAt = self::parseDate((string) ($row['last_attempt_at'] ?? ''));
                $attempts = $lastAttemptAt !== null && ($now->getTimestamp() - $lastAttemptAt->getTimestamp()) < $windowSeconds
                    ? (int) ($row['attempts'] ?? 0)
                    : 0;
            }
        } else {
            $attempts = 0;
        }

        $attempts++;
        $lockedUntil = $attempts >= $maxAttempts ? $now->modify('+' . $lockSeconds . ' seconds') : null;
        $nowText = self::formatDate($now);
        $lockedText = $lockedUntil !== null ? self::formatDate($lockedUntil) : null;
        $ipAddress = self::normalizeIp($ipAddress);

        if ($row !== null) {
            Database::query(
                "UPDATE admin_login_attempts
                SET attempts = ?, locked_until = ?, last_attempt_at = ?, updated_at = ?
                WHERE ip_address = ?",
                [$attempts, $lockedText, $nowText, $nowText, $ipAddress]
            );
        } else {
            Database::query(
                "INSERT INTO admin_login_attempts
                (ip_address, attempts, locked_until, last_attempt_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?)",
                [$ipAddress, $attempts, $lockedText, $nowText, $nowText, $nowText]
            );
        }

        return [
            'locked' => $lockedUntil !== null,
            'attempts' => $attempts,
            'remaining_seconds' => $lockedUntil !== null ? max(0, $lockedUntil->getTimestamp() - $now->getTimestamp()) : 0,
        ];
    }

    public static function clear(string $ipAddress): void
    {
        self::createTable();

        Database::query("DELETE FROM admin_login_attempts WHERE ip_address = ?", [self::normalizeIp($ipAddress)]);
    }

    public static function prune(int $olderThanSeconds = 86400): void
    {
        self::createTable();

        $now = new \DateTimeImmutable('now', self::timezone());
        $threshold = $now->modify('-' . $olderThanSeconds . ' seconds');
        Database::query(
            "DELETE FROM admin_login_attempts WHERE last_attempt_at < ? AND (locked_until IS NULL OR locked_until < ?)",
            [self::formatDate($threshold), self::formatDate($now)]
        );
    }

    private static function find(string $ipAddress): ?array
    {
        $stmt = Database::query(
            "SELECT * FROM admin_login_attempts WHERE ip_address = ? LIMIT 1",
            [self::normalizeIp($ipAddress)]
        );
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @return array{locked: bool, attempts: int, remaining_seconds: int}
     */
    private static function statusFromRow(array $row): array
    {
        $lockedUntil = self::parseDate((string) ($row['locked_until'] ?? ''));
        $remaining = $lockedUntil !== null ? max(0, $lockedUntil->getTimestamp() - time()) : 0;

        return [
            'locked' => $remaining > 0,
            'attempts' => (int) ($row['attempts'] ?? 0),
            'remaining_seconds' => $remaining,
        ];
    }

    private static function normalizeIp(string $ipAddress): string
    {
        $ipAddress = trim($ipAddress);
        return substr($ipAddress !== '' ? $ipAddress : 'unknown', 0, 45);
    }

    private static function parseDate(string $date): ?\DateTimeImmutable
    {
        $date = trim($date);
        if ($date === '') {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date, self::timezone());
        return $parsed instanceof \DateTimeImmutable ? $parsed : null;
    }

    private static function formatDate(\DateTimeImmutable $date): string
    {
        return $date->setTimezone(self::timezone())->format('Y-m-d H:i:s');
    }

    private static function timezone(): \DateTimeZone
    {
        return new \DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai');
    }
}