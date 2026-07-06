<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Comment
{
    public static function createTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS post_comments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            post_id INT UNSIGNED NOT NULL,
            author_name VARCHAR(80) NOT NULL,
            content TEXT NOT NULL,
            status TINYINT UNSIGNED DEFAULT 1 COMMENT '1=approved, 0=pending',
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            visitor_hash CHAR(64) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_post_status_created (post_id, status, created_at),
            INDEX idx_visitor_hash (visitor_hash),
            CONSTRAINT fk_post_comments_post_id FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
        self::ensureMetadataColumns();
        self::removeEmailColumn();
    }

    private static function ensureMetadataColumns(): void
    {
        $stmt = Database::query(
            "SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?
            LIMIT 1",
            ['post_comments', 'visitor_hash']
        );

        if ($stmt->fetch() === false) {
            Database::query("ALTER TABLE post_comments ADD COLUMN visitor_hash CHAR(64) DEFAULT NULL AFTER user_agent");
        }

        $stmt = Database::query(
            "SELECT 1
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND INDEX_NAME = ?
            LIMIT 1",
            ['post_comments', 'idx_visitor_hash']
        );

        if ($stmt->fetch() === false) {
            Database::query("ALTER TABLE post_comments ADD INDEX idx_visitor_hash (visitor_hash)");
        }
    }

    public static function create(array $data): int
    {
        $now = self::now();
        $visitorHash = self::normalizeHash((string) ($data['visitor_hash'] ?? ''));

        Database::query(
            "INSERT INTO post_comments (post_id, author_name, content, status, ip_address, user_agent, visitor_hash, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                (int) $data['post_id'],
                $data['author_name'],
                $data['content'],
                (int) ($data['status'] ?? 1),
                $data['ip_address'] ?? null,
                isset($data['user_agent']) ? mb_substr((string) $data['user_agent'], 0, 255) : null,
                $visitorHash,
                $now,
                $now,
            ]
        );

        $commentId = (int) Database::getInstance()->lastInsertId();

        PostInteractionLog::record('commented', [
            'post_id' => (int) $data['post_id'],
            'actor_name' => (string) $data['author_name'],
            'visitor_hash' => $visitorHash ?? '',
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'content_excerpt' => (string) $data['content'],
            'source_type' => 'comment',
            'source_id' => $commentId,
            'created_at' => $now,
        ]);

        return $commentId;
    }

    public static function approvedByPostId(int $postId): array
    {
        $stmt = Database::query(
            "SELECT *
            FROM post_comments
            WHERE post_id = ? AND status = 1
            ORDER BY created_at DESC, id DESC",
            [$postId]
        );

        return $stmt->fetchAll();
    }

    public static function countApprovedByPostId(int $postId): int
    {
        $stmt = Database::query(
            "SELECT COUNT(*)
            FROM post_comments
            WHERE post_id = ? AND status = 1",
            [$postId]
        );

        return (int) $stmt->fetchColumn();
    }

    public static function countAll(): int
    {
        $stmt = Database::query("SELECT COUNT(*) FROM post_comments");
        return (int) $stmt->fetchColumn();
    }

    public static function allWithPosts(): array
    {
        $stmt = Database::query(
            "SELECT
                post_comments.*,
                posts.title AS post_title,
                posts.slug AS post_slug
            FROM post_comments
            LEFT JOIN posts ON posts.id = post_comments.post_id
            ORDER BY post_comments.created_at DESC, post_comments.id DESC"
        );

        return $stmt->fetchAll();
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
            ['post_comments', 'author_email']
        );

        if ($stmt->fetch() !== false) {
            Database::query("ALTER TABLE post_comments DROP COLUMN author_email");
        }
    }

    private static function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai')))->format('Y-m-d H:i:s');
    }

    private static function normalizeHash(string $hash): ?string
    {
        $hash = strtolower(trim($hash));

        return preg_match('/^[a-f0-9]{64}$/', $hash) === 1 ? $hash : null;
    }
}
