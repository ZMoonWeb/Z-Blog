<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Post
{
    private const SEARCH_KEYWORD_MAX_LENGTH = 80;

    public static function createTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS posts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            summary TEXT DEFAULT NULL,
            content LONGTEXT NOT NULL,
            content_mode VARCHAR(20) NOT NULL DEFAULT 'markdown',
            cover_image VARCHAR(255) DEFAULT NULL,
            category_id INT UNSIGNED DEFAULT NULL,
            tags VARCHAR(500) DEFAULT NULL,
            status TINYINT UNSIGNED DEFAULT 1 COMMENT '1=published, 0=draft',
            view_count INT UNSIGNED DEFAULT 0,
            published_at DATETIME NULL DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_status (status),
            INDEX idx_published_at (published_at),
            INDEX idx_category_id (category_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
    }

    public static function create(array $data): int
    {
        $now = self::now();
        $status = (int) ($data['status'] ?? 1);

        Database::query(
            "INSERT INTO posts (title, slug, summary, content, content_mode, cover_image, category_id, tags, status, published_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['title'],
                $data['slug'],
                $data['summary'] ?? null,
                $data['content'],
                $data['content_mode'] ?? 'markdown',
                $data['cover_image'] ?? null,
                !empty($data['category_id']) ? (int) $data['category_id'] : null,
                $data['tags'] ?? null,
                $status,
                $status === 1 ? ($data['published_at'] ?? $now) : null,
                $now,
                $now,
            ]
        );

        return (int) Database::getInstance()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $post = self::find($id);
        if ($post === null) {
            return;
        }

        $status = (int) ($data['status'] ?? 1);
        $publishedAt = $post['published_at'];

        if ($status === 1 && empty($publishedAt)) {
            $publishedAt = self::now();
        }

        if ($status === 0) {
            $publishedAt = null;
        }

        Database::query(
            "UPDATE posts
            SET title = ?, slug = ?, summary = ?, content = ?, content_mode = ?, cover_image = ?, category_id = ?, tags = ?, status = ?, published_at = ?, updated_at = ?
            WHERE id = ?",
            [
                $data['title'],
                $data['slug'],
                $data['summary'] ?? null,
                $data['content'],
                $data['content_mode'] ?? 'markdown',
                $data['cover_image'] ?? null,
                !empty($data['category_id']) ? (int) $data['category_id'] : null,
                $data['tags'] ?? null,
                $status,
                $publishedAt,
                self::now(),
                $id,
            ]
        );
    }

    public static function delete(int $id): bool
    {
        $stmt = Database::query("DELETE FROM posts WHERE id = ?", [$id]);
        return $stmt->rowCount() > 0;
    }

    public static function all(): array
    {
        $stmt = Database::query(
            "SELECT posts.*, categories.name AS category_name,
                (SELECT COUNT(*) FROM post_comments WHERE post_comments.post_id = posts.id AND post_comments.status = 1) AS comment_count,
                (SELECT COUNT(*) FROM post_likes WHERE post_likes.post_id = posts.id) AS like_count
            FROM posts
            LEFT JOIN categories ON categories.id = posts.category_id
            ORDER BY posts.created_at DESC, posts.id DESC"
        );

        return $stmt->fetchAll();
    }

    public static function publishedPaginated(int $page = 1, int $perPage = 10, ?string $categorySlug = null, ?string $keyword = null): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = "posts.status = 1";
        $params = [];

        if ($categorySlug !== null && $categorySlug !== '') {
            $where .= " AND categories.slug = ?";
            $params[] = $categorySlug;
        }

        $keyword = self::normalizeSearchKeyword($keyword);
        if ($keyword !== null) {
            $likeKeyword = '%' . self::escapeLikeKeyword($keyword) . '%';
            $where .= " AND (
                posts.title LIKE ? ESCAPE '!'
                OR posts.summary LIKE ? ESCAPE '!'
                OR posts.content LIKE ? ESCAPE '!'
                OR posts.tags LIKE ? ESCAPE '!'
                OR categories.name LIKE ? ESCAPE '!'
            )";
            array_push($params, $likeKeyword, $likeKeyword, $likeKeyword, $likeKeyword, $likeKeyword);
        }

        $totalStmt = Database::query(
            "SELECT COUNT(*)
            FROM posts
            LEFT JOIN categories ON categories.id = posts.category_id
            WHERE {$where}",
            $params
        );
        $total = (int) $totalStmt->fetchColumn();

        $stmt = Database::query(
            "SELECT posts.*, categories.name AS category_name, categories.slug AS category_slug,
                (SELECT COUNT(*) FROM post_comments WHERE post_comments.post_id = posts.id AND post_comments.status = 1) AS comment_count,
                (SELECT COUNT(*) FROM post_likes WHERE post_likes.post_id = posts.id) AS like_count
            FROM posts
            LEFT JOIN categories ON categories.id = posts.category_id
            WHERE {$where}
            ORDER BY COALESCE(posts.published_at, posts.created_at) DESC, posts.id DESC
            LIMIT {$perPage} OFFSET {$offset}",
            $params
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

    public static function find(int $id): ?array
    {
        $stmt = Database::query("SELECT * FROM posts WHERE id = ? LIMIT 1", [$id]);
        $post = $stmt->fetch();
        return $post ?: null;
    }

    public static function findPublishedBySlug(string $slug): ?array
    {
        $stmt = Database::query(
            "SELECT posts.*, categories.name AS category_name, categories.slug AS category_slug,
                (SELECT COUNT(*) FROM post_comments WHERE post_comments.post_id = posts.id AND post_comments.status = 1) AS comment_count,
                (SELECT COUNT(*) FROM post_likes WHERE post_likes.post_id = posts.id) AS like_count
            FROM posts
            LEFT JOIN categories ON categories.id = posts.category_id
            WHERE posts.slug = ? AND posts.status = 1
            LIMIT 1",
            [$slug]
        );

        $post = $stmt->fetch();
        return $post ?: null;
    }

    public static function incrementViewCount(int $id): void
    {
        Database::query("UPDATE posts SET view_count = view_count + 1 WHERE id = ?", [$id]);
    }

    public static function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        if ($ignoreId !== null) {
            $stmt = Database::query(
                "SELECT COUNT(*) FROM posts WHERE slug = ? AND id <> ?",
                [$slug, $ignoreId]
            );
        } else {
            $stmt = Database::query("SELECT COUNT(*) FROM posts WHERE slug = ?", [$slug]);
        }

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function generateSlug(string $title, ?int $ignoreId = null): string
    {
        $slug = trim(strtolower($title));
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $slug) ?: '';
        $slug = trim($slug, '-');

        if ($slug === '') {
            $slug = 'post-' . date('YmdHis');
        }

        $base = $slug;
        $index = 2;

        while (self::slugExists($slug, $ignoreId)) {
            $slug = $base . '-' . $index;
            $index++;
        }

        return $slug;
    }

    private static function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai')))->format('Y-m-d H:i:s');
    }

    private static function normalizeSearchKeyword(?string $keyword): ?string
    {
        $keyword = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $keyword) ?? '';
        $keyword = preg_replace('/\s+/u', ' ', trim($keyword)) ?? '';

        if ($keyword === '') {
            return null;
        }

        if (mb_strlen($keyword, 'UTF-8') > self::SEARCH_KEYWORD_MAX_LENGTH) {
            $keyword = mb_substr($keyword, 0, self::SEARCH_KEYWORD_MAX_LENGTH, 'UTF-8');
        }

        return $keyword;
    }

    private static function escapeLikeKeyword(string $keyword): string
    {
        return strtr($keyword, [
            '!' => '!!',
            '%' => '!%',
            '_' => '!_',
        ]);
    }

    public static function calculateHeat(int $viewCount, int $likeCount, int $commentCount, string $publishedAt): float
    {
        $viewWeight = 0.1;
        $likeWeight = 2.0;
        $commentWeight = 5.0;

        $baseHeat = ($viewCount * $viewWeight) + ($likeCount * $likeWeight) + ($commentCount * $commentWeight);

        try {
            $now = new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai'));
            $published = new \DateTimeImmutable($publishedAt, new \DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai'));
            $daysSincePublish = max(0, ($now->getTimestamp() - $published->getTimestamp()) / 86400);

            $halfLifeDays = 7.0;
            $decayFactor = pow(0.5, $daysSincePublish / $halfLifeDays);

            return round($baseHeat * $decayFactor, 2);
        } catch (\Exception $e) {
            return round($baseHeat, 2);
        }
    }
}
