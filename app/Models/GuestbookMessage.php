<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class GuestbookMessage
{
    public const STATUS_PENDING = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_HIDDEN = 2;

    public static function createTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS guestbook_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nickname VARCHAR(80) NOT NULL,
            website VARCHAR(255) DEFAULT NULL,
            content TEXT NOT NULL,
            mood VARCHAR(20) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            admin_reply TEXT DEFAULT NULL,
            replied_at DATETIME DEFAULT NULL,
            status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=pending,1=approved,2=hidden',
            is_admin TINYINT UNSIGNED NOT NULL DEFAULT 0,
            is_deleted TINYINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_status_created (status, created_at),
            INDEX idx_deleted_status_created (is_deleted, status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);

        self::ensureReplyColumns();
        self::removeEmailColumn();
    }

    private static function ensureReplyColumns(): void
    {
        $columns = [
            'admin_reply' => "ALTER TABLE guestbook_messages ADD COLUMN admin_reply TEXT DEFAULT NULL AFTER user_agent",
            'replied_at' => "ALTER TABLE guestbook_messages ADD COLUMN replied_at DATETIME DEFAULT NULL AFTER admin_reply",
            'is_deleted' => "ALTER TABLE guestbook_messages ADD COLUMN is_deleted TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER is_admin",
        ];

        foreach ($columns as $column => $sql) {
            $stmt = Database::query(
                "SELECT 1
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?
                    AND COLUMN_NAME = ?
                LIMIT 1",
                ['guestbook_messages', $column]
            );
            if ($stmt->fetch() === false) {
                Database::query($sql);
            }
        }
    }

    private static function removeEmailColumn(): void
    {
        $stmt = Database::query(
            "SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?
            LIMIT 1",
            ['guestbook_messages', 'email']
        );

        if ($stmt->fetch() !== false) {
            Database::query("ALTER TABLE guestbook_messages DROP COLUMN email");
        }
    }

    public static function create(array $data): int
    {
        $now = self::now();

        Database::query(
            "INSERT INTO guestbook_messages
                (nickname, website, content, mood, ip_address, user_agent, status, is_admin, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                (string) $data['nickname'],
                ($data['website'] ?? '') !== '' ? (string) $data['website'] : null,
                (string) $data['content'],
                ($data['mood'] ?? '') !== '' ? (string) $data['mood'] : null,
                $data['ip_address'] ?? null,
                isset($data['user_agent']) ? mb_substr((string) $data['user_agent'], 0, 255) : null,
                (int) ($data['status'] ?? self::STATUS_APPROVED),
                (int) ($data['is_admin'] ?? 0),
                $now,
                $now,
            ]
        );

        return (int) Database::getInstance()->lastInsertId();
    }

    public static function approvedPaginated(int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        $totalStmt = Database::query(
            "SELECT COUNT(*) FROM guestbook_messages WHERE status = ? AND is_deleted = 0",
            [self::STATUS_APPROVED]
        );
        $total = (int) $totalStmt->fetchColumn();

        $stmt = Database::query(
            "SELECT * FROM guestbook_messages
             WHERE status = ?
                AND is_deleted = 0
             ORDER BY is_admin DESC, created_at DESC, id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            [self::STATUS_APPROVED]
        );

        $lastPage = max(1, (int) ceil($total / $perPage));

        return [
            'data' => $stmt->fetchAll(),
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
                'has_previous' => $page > 1,
                'has_next' => $page < $lastPage,
            ],
        ];
    }

    public static function all(): array
    {
        $stmt = Database::query(
            "SELECT * FROM guestbook_messages
             ORDER BY is_deleted ASC, status ASC, created_at DESC, id DESC"
        );

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::query("SELECT * FROM guestbook_messages WHERE id = ? LIMIT 1", [$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function nicknameExists(string $nickname): bool
    {
        $stmt = Database::query(
            "SELECT 1 FROM guestbook_messages WHERE nickname = ? AND is_deleted = 0 LIMIT 1",
            [$nickname]
        );

        return $stmt->fetch() !== false;
    }

    public static function updateStatus(int $id, int $status): void
    {
        Database::query(
            "UPDATE guestbook_messages SET status = ?, updated_at = ? WHERE id = ? AND is_deleted = 0",
            [$status, self::now(), $id]
        );
    }

    public static function updateReply(int $id, string $reply): void
    {
        $reply = trim($reply);

        Database::query(
            "UPDATE guestbook_messages SET admin_reply = ?, replied_at = ?, updated_at = ? WHERE id = ? AND is_deleted = 0",
            [$reply !== '' ? $reply : null, $reply !== '' ? self::now() : null, self::now(), $id]
        );
    }

    public static function delete(int $id): bool
    {
        $stmt = Database::query(
            "UPDATE guestbook_messages SET is_deleted = 1, updated_at = ? WHERE id = ? AND is_deleted = 0",
            [self::now(), $id]
        );
        return $stmt->rowCount() > 0;
    }

    public static function restore(int $id): bool
    {
        $stmt = Database::query(
            "UPDATE guestbook_messages SET is_deleted = 0, status = ?, updated_at = ? WHERE id = ? AND is_deleted = 1",
            [self::STATUS_APPROVED, self::now(), $id]
        );

        return $stmt->rowCount() > 0;
    }

    public static function countAll(): int
    {
        $stmt = Database::query("SELECT COUNT(*) FROM guestbook_messages");
        return (int) $stmt->fetchColumn();
    }

    public static function countByStatus(int $status): int
    {
        $stmt = Database::query("SELECT COUNT(*) FROM guestbook_messages WHERE status = ? AND is_deleted = 0", [$status]);
        return (int) $stmt->fetchColumn();
    }

    public static function countVisibleByStatus(int $status): int
    {
        return self::countByStatus($status);
    }

    public static function countDeleted(): int
    {
        $stmt = Database::query("SELECT COUNT(*) FROM guestbook_messages WHERE is_deleted = 1");
        return (int) $stmt->fetchColumn();
    }

    public static function countRepliedApproved(): int
    {
        $stmt = Database::query(
            "SELECT COUNT(*) FROM guestbook_messages WHERE status = ? AND is_deleted = 0 AND admin_reply IS NOT NULL AND TRIM(admin_reply) <> ''",
            [self::STATUS_APPROVED]
        );

        return (int) $stmt->fetchColumn();
    }

    public static function countReplied(): int
    {
        $stmt = Database::query(
            "SELECT COUNT(*) FROM guestbook_messages WHERE is_deleted = 0 AND admin_reply IS NOT NULL AND TRIM(admin_reply) <> ''"
        );

        return (int) $stmt->fetchColumn();
    }

    public static function countUnreplied(): int
    {
        $stmt = Database::query(
            "SELECT COUNT(*) FROM guestbook_messages WHERE is_deleted = 0 AND (admin_reply IS NULL OR TRIM(admin_reply) = '')"
        );

        return (int) $stmt->fetchColumn();
    }

    private static function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai')))->format('Y-m-d H:i:s');
    }
}
