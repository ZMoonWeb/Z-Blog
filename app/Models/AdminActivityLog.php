<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class AdminActivityLog
{
    public static function createTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS admin_activity_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            admin_id INT UNSIGNED DEFAULT NULL,
            username VARCHAR(50) DEFAULT NULL,
            action VARCHAR(40) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'info',
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            message VARCHAR(255) DEFAULT NULL,
            metadata TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_admin_activity_created_at (created_at),
            INDEX idx_admin_activity_action (action),
            INDEX idx_admin_activity_ip (ip_address),
            INDEX idx_admin_activity_admin (admin_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
    }

    public static function record(string $action, array $data = []): void
    {
        self::createTable();

        $metadata = $data['metadata'] ?? null;
        if (is_array($metadata)) {
            $metadata = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        Database::query(
            "INSERT INTO admin_activity_logs
            (admin_id, username, action, status, ip_address, user_agent, message, metadata, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                isset($data['admin_id']) && (int) $data['admin_id'] > 0 ? (int) $data['admin_id'] : null,
                self::nullableText((string) ($data['username'] ?? ''), 50),
                self::normalizeAction($action),
                self::normalizeStatus((string) ($data['status'] ?? 'info')),
                self::nullableText((string) ($data['ip_address'] ?? ''), 45),
                self::nullableText((string) ($data['user_agent'] ?? ''), 255),
                self::nullableText((string) ($data['message'] ?? ''), 255),
                self::nullableText((string) ($metadata ?? ''), 65535),
                self::now(),
            ]
        );
    }

    public static function all(int $limit = 500): array
    {
        self::createTable();

        $limit = max(1, min(1000, $limit));
        $stmt = Database::query(
            "SELECT * FROM admin_activity_logs ORDER BY created_at DESC, id DESC LIMIT {$limit}"
        );

        return $stmt->fetchAll();
    }

    public static function prune(int $olderThanDays = 90): void
    {
        self::createTable();

        $threshold = (new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai')))
            ->modify('-' . max(1, $olderThanDays) . ' days')
            ->format('Y-m-d H:i:s');

        Database::query("DELETE FROM admin_activity_logs WHERE created_at < ?", [$threshold]);
    }

    private static function normalizeAction(string $action): string
    {
        $action = preg_replace('/[^a-z0-9_:-]+/i', '_', trim($action)) ?: 'unknown';
        return substr(strtolower($action), 0, 40);
    }

    private static function normalizeStatus(string $status): string
    {
        return match ($status) {
            'success', 'warning', 'danger', 'error', 'info' => $status,
            default => 'info',
        };
    }

    private static function nullableText(string $value, int $maxLength): ?string
    {
        $value = trim($value);
        return $value !== '' ? mb_substr($value, 0, $maxLength, 'UTF-8') : null;
    }

    private static function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai')))->format('Y-m-d H:i:s');
    }
}