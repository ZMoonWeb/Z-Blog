<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Like
{
    public static function createTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS post_likes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            post_id INT UNSIGNED NOT NULL,
            visitor_hash CHAR(64) NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uniq_post_visitor (post_id, visitor_hash),
            INDEX idx_post_id (post_id),
            CONSTRAINT fk_post_likes_post_id FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
        self::createLogTable();
    }

    public static function createLogTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS post_like_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            post_id INT UNSIGNED NOT NULL,
            visitor_hash CHAR(64) NOT NULL,
            action VARCHAR(20) NOT NULL DEFAULT 'liked',
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_post_created (post_id, created_at),
            INDEX idx_visitor_hash (visitor_hash),
            CONSTRAINT fk_post_like_logs_post_id FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
    }

    public static function toggle(int $postId, string $visitorHash, ?string $ipAddress = null, ?string $userAgent = null): bool
    {
        return self::toggleForVisitor($postId, $visitorHash, [], $ipAddress, $userAgent);
    }

    public static function toggleForVisitor(int $postId, string $visitorHash, array $aliasHashes = [], ?string $ipAddress = null, ?string $userAgent = null): bool
    {
        $visitorHashes = self::normalizeHashes(array_merge([$visitorHash], $aliasHashes));
        $visitorHash = $visitorHashes[0] ?? '';

        if ($visitorHash === '') {
            return false;
        }

        if (self::existsForHashes($postId, $visitorHashes)) {
            $placeholders = implode(',', array_fill(0, count($visitorHashes), '?'));
            Database::query(
                "DELETE FROM post_likes WHERE post_id = ? AND visitor_hash IN ({$placeholders})",
                array_merge([$postId], $visitorHashes)
            );
            self::logAction($postId, $visitorHash, 'unliked', $ipAddress, $userAgent);

            return false;
        }

        Database::query(
            "INSERT INTO post_likes (post_id, visitor_hash, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?)",
            [
                $postId,
                $visitorHash,
                $ipAddress,
                $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
                self::now(),
            ]
        );
        self::logAction($postId, $visitorHash, 'liked', $ipAddress, $userAgent);

        return true;
    }

    public static function exists(int $postId, string $visitorHash): bool
    {
        return self::existsForHashes($postId, [$visitorHash]);
    }

    public static function existsForHashes(int $postId, array $visitorHashes): bool
    {
        $visitorHashes = self::normalizeHashes($visitorHashes);

        if (empty($visitorHashes)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($visitorHashes), '?'));
        $stmt = Database::query(
            "SELECT COUNT(*) FROM post_likes WHERE post_id = ? AND visitor_hash IN ({$placeholders})",
            array_merge([$postId], $visitorHashes)
        );

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function likedPostIds(string $visitorHash, array $postIds): array
    {
        return self::likedPostIdsForHashes([$visitorHash], $postIds);
    }

    public static function likedPostIdsForHashes(array $visitorHashes, array $postIds): array
    {
        $visitorHashes = self::normalizeHashes($visitorHashes);
        $postIds = array_values(array_unique(array_filter(array_map('intval', $postIds), static fn (int $postId): bool => $postId > 0)));

        if (empty($visitorHashes) || empty($postIds)) {
            return [];
        }

        $hashPlaceholders = implode(',', array_fill(0, count($visitorHashes), '?'));
        $postPlaceholders = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = Database::query(
            "SELECT post_id FROM post_likes WHERE visitor_hash IN ({$hashPlaceholders}) AND post_id IN ({$postPlaceholders})",
            array_merge($visitorHashes, $postIds)
        );

        return array_values(array_unique(array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN))));
    }

    public static function countByPostId(int $postId): int
    {
        $stmt = Database::query("SELECT COUNT(*) FROM post_likes WHERE post_id = ?", [$postId]);
        return (int) $stmt->fetchColumn();
    }

    public static function countAll(): int
    {
        $stmt = Database::query("SELECT COUNT(*) FROM post_likes");
        return (int) $stmt->fetchColumn();
    }

    public static function allWithPosts(): array
    {
        self::createLogTable();

        $stmt = Database::query(
            "SELECT
                post_like_logs.*,
                posts.title AS post_title,
                posts.slug AS post_slug
            FROM post_like_logs
            LEFT JOIN posts ON posts.id = post_like_logs.post_id
            ORDER BY post_like_logs.created_at DESC, post_like_logs.id DESC"
        );

        return $stmt->fetchAll();
    }

    private static function logAction(int $postId, string $visitorHash, string $action, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        self::createLogTable();
        $normalizedAction = $action === 'unliked' ? 'unliked' : 'liked';
        $createdAt = self::now();

        Database::query(
            "INSERT INTO post_like_logs (post_id, visitor_hash, action, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?)",
            [
                $postId,
                $visitorHash,
                $normalizedAction,
                $ipAddress,
                $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
                $createdAt,
            ]
        );

        PostInteractionLog::record($normalizedAction, [
            'post_id' => $postId,
            'visitor_hash' => $visitorHash,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'source_type' => 'like',
            'source_id' => (int) Database::getInstance()->lastInsertId(),
            'created_at' => $createdAt,
        ]);
    }

    private static function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai')))->format('Y-m-d H:i:s');
    }

    private static function normalizeHashes(array $visitorHashes): array
    {
        $hashes = [];

        foreach ($visitorHashes as $visitorHash) {
            $visitorHash = strtolower(trim((string) $visitorHash));
            if (preg_match('/^[a-f0-9]{64}$/', $visitorHash) !== 1) {
                continue;
            }

            $hashes[] = $visitorHash;
        }

        return array_values(array_unique($hashes));
    }
}
