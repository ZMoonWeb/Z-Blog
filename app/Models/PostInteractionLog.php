<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class PostInteractionLog
{
    public static function createTable(bool $backfill = true): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS post_interaction_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            post_id INT UNSIGNED DEFAULT NULL,
            action VARCHAR(20) NOT NULL,
            actor_name VARCHAR(80) DEFAULT NULL,
            visitor_hash CHAR(64) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            content_excerpt VARCHAR(255) DEFAULT NULL,
            source_type VARCHAR(20) DEFAULT NULL,
            source_id INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uniq_source (source_type, source_id),
            INDEX idx_created_at (created_at),
            INDEX idx_post_created (post_id, created_at),
            INDEX idx_visitor_hash (visitor_hash),
            CONSTRAINT fk_post_interaction_logs_post_id FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
        self::ensureFlexiblePostId();

        if ($backfill) {
            self::backfillExistingLogs();
        }
    }

    public static function record(string $action, array $data): void
    {
        self::createTable(false);

        $sourceType = self::normalizeSourceType((string) ($data['source_type'] ?? ''));
        $sourceId = isset($data['source_id']) && (int) $data['source_id'] > 0 ? (int) $data['source_id'] : null;

        Database::query(
            "INSERT INTO post_interaction_logs
                (post_id, action, actor_name, visitor_hash, ip_address, user_agent, content_excerpt, source_type, source_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE id = id",
            [
                isset($data['post_id']) && (int) $data['post_id'] > 0 ? (int) $data['post_id'] : null,
                self::normalizeAction($action),
                self::nullableText((string) ($data['actor_name'] ?? ''), 80),
                self::normalizeHash((string) ($data['visitor_hash'] ?? '')),
                self::nullableText((string) ($data['ip_address'] ?? ''), 45),
                self::nullableText((string) ($data['user_agent'] ?? ''), 255),
                self::nullableText((string) ($data['content_excerpt'] ?? ''), 255),
                $sourceType,
                $sourceType !== null ? $sourceId : null,
                (string) ($data['created_at'] ?? self::now()),
            ]
        );
    }

    public static function allWithPosts(int $limit = 300): array
    {
        self::createTable();

        $limit = max(1, min(1000, $limit));
        $stmt = Database::query(
            "SELECT
                post_interaction_logs.*,
                posts.title AS post_title,
                posts.slug AS post_slug
            FROM post_interaction_logs
            LEFT JOIN posts ON posts.id = post_interaction_logs.post_id
            ORDER BY post_interaction_logs.created_at DESC, post_interaction_logs.id DESC
            LIMIT {$limit}"
        );

        return $stmt->fetchAll();
    }

    private static function backfillExistingLogs(): void
    {
        if (self::tableExists('post_comments')) {
            Database::query(
                "INSERT IGNORE INTO post_interaction_logs
                    (post_id, action, actor_name, visitor_hash, ip_address, user_agent, content_excerpt, source_type, source_id, created_at)
                SELECT
                    post_id,
                    'commented',
                    author_name,
                    visitor_hash,
                    ip_address,
                    user_agent,
                    LEFT(content, 255),
                    'comment',
                    id,
                    created_at
                FROM post_comments"
            );
        }

        if (self::tableExists('post_like_logs')) {
            Database::query(
                "INSERT IGNORE INTO post_interaction_logs
                    (post_id, action, actor_name, visitor_hash, ip_address, user_agent, content_excerpt, source_type, source_id, created_at)
                SELECT
                    post_id,
                    CASE WHEN action = 'unliked' THEN 'unliked' ELSE 'liked' END,
                    NULL,
                    visitor_hash,
                    ip_address,
                    user_agent,
                    NULL,
                    'like',
                    id,
                    created_at
                FROM post_like_logs"
            );
        }
    }

    private static function ensureFlexiblePostId(): void
    {
        if (!self::tableExists('post_interaction_logs')) {
            return;
        }

        $stmt = Database::query(
            "SELECT IS_NULLABLE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'post_interaction_logs'
                AND COLUMN_NAME = 'post_id'
            LIMIT 1"
        );
        $column = $stmt->fetch();

        if ($column !== false && strtoupper((string) ($column['IS_NULLABLE'] ?? '')) === 'YES') {
            return;
        }

        $constraints = Database::query(
            "SELECT CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'post_interaction_logs'
                AND COLUMN_NAME = 'post_id'
                AND REFERENCED_TABLE_NAME IS NOT NULL"
        )->fetchAll();

        foreach ($constraints as $constraint) {
            $name = trim((string) ($constraint['CONSTRAINT_NAME'] ?? ''));
            if ($name !== '') {
                Database::query("ALTER TABLE post_interaction_logs DROP FOREIGN KEY `{$name}`");
            }
        }

        Database::query("ALTER TABLE post_interaction_logs MODIFY post_id INT UNSIGNED DEFAULT NULL");

        $fkExists = Database::query(
            "SELECT 1
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'post_interaction_logs'
                AND COLUMN_NAME = 'post_id'
                AND REFERENCED_TABLE_NAME = 'posts'
            LIMIT 1"
        )->fetch() !== false;

        if (!$fkExists && self::tableExists('posts')) {
            Database::query(
                "ALTER TABLE post_interaction_logs
                ADD CONSTRAINT fk_post_interaction_logs_post_id
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE"
            );
        }
    }

    private static function tableExists(string $table): bool
    {
        $stmt = Database::query(
            "SELECT 1
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
            LIMIT 1",
            [$table]
        );

        return $stmt->fetch() !== false;
    }

    private static function normalizeAction(string $action): string
    {
        return match ($action) {
            'viewed',
            'liked',
            'unliked',
            'commented',
            'page_view',
            'guestbook_view',
            'guestbook_open',
            'guestbook_post',
            'guestbook_detail' => $action,
            default => 'viewed',
        };
    }

    private static function normalizeSourceType(string $sourceType): ?string
    {
        return match ($sourceType) {
            'like', 'comment', 'page', 'guestbook' => $sourceType,
            default => null,
        };
    }

    private static function normalizeHash(string $hash): ?string
    {
        $hash = strtolower(trim($hash));

        return preg_match('/^[a-f0-9]{64}$/', $hash) === 1 ? $hash : null;
    }

    private static function nullableText(string $value, int $maxLength): ?string
    {
        $value = trim($value);

        return $value !== '' ? mb_substr($value, 0, $maxLength) : null;
    }

    private static function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai')))->format('Y-m-d H:i:s');
    }
}
