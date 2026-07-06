<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Admin
{
    private const PASSWORD_ALGO = PASSWORD_BCRYPT;
    private const PASSWORD_OPTIONS = ['cost' => 12];

    public static function createTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS admin (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
    }

    public static function create(string $username, string $password): void
    {
        $hash = self::hashPassword($password);
        $now = self::now();

        Database::query(
            "INSERT INTO admin (username, password, created_at, updated_at) VALUES (?, ?, ?, ?)",
            [$username, $hash, $now, $now]
        );
    }

    public static function findByUsername(string $username): ?array
    {
        $stmt = Database::query("SELECT * FROM admin WHERE username = ? LIMIT 1", [$username]);
        $admin = $stmt->fetch();
        return $admin ?: null;
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::query("SELECT * FROM admin WHERE id = ? LIMIT 1", [$id]);
        $admin = $stmt->fetch();
        return $admin ?: null;
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, self::PASSWORD_ALGO, self::PASSWORD_OPTIONS);
    }

    public static function passwordNeedsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::PASSWORD_ALGO, self::PASSWORD_OPTIONS);
    }

    public static function updatePasswordHash(int $id, string $hash): void
    {
        Database::query(
            "UPDATE admin SET password = ?, updated_at = ? WHERE id = ?",
            [$hash, self::now(), $id]
        );
    }

    private static function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai')))->format('Y-m-d H:i:s');
    }
}