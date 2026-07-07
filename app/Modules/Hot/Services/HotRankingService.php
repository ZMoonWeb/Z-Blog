<?php

declare(strict_types=1);

namespace App\Modules\Hot\Services;

use App\Core\Database;

class HotRankingService
{
    public function defaultLimit(): int
    {
        return 5;
    }

    public function rankingData(): array
    {
        $stmt = Database::query(
            "SELECT posts.*, categories.name AS category_name, categories.slug AS category_slug,
                (SELECT COUNT(*) FROM post_comments WHERE post_comments.post_id = posts.id AND post_comments.status = 1) AS comment_count,
                (SELECT COUNT(*) FROM post_likes WHERE post_likes.post_id = posts.id) AS like_count,
                (
                    posts.view_count * 1
                    + (SELECT COUNT(*) FROM post_likes WHERE post_likes.post_id = posts.id) * 1.2
                    + (SELECT COUNT(*) FROM post_comments WHERE post_comments.post_id = posts.id AND post_comments.status = 1) * 1.5
                ) AS hot_score
            FROM posts
            LEFT JOIN categories ON categories.id = posts.category_id
            WHERE posts.status = 1
            ORDER BY hot_score DESC, posts.view_count DESC, posts.id DESC
            LIMIT 50"
        );

        $weekTopStmt = Database::query(
            "SELECT posts.id, posts.title, posts.slug, posts.view_count,
                (SELECT COUNT(*) FROM post_likes WHERE post_likes.post_id = posts.id) AS like_count
            FROM posts
            WHERE posts.status = 1
                AND COALESCE(posts.published_at, posts.created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY (
                posts.view_count
                + (SELECT COUNT(*) FROM post_likes WHERE post_likes.post_id = posts.id) * 5
            ) DESC
            LIMIT 5"
        );

        $monthTopStmt = Database::query(
            "SELECT posts.id, posts.title, posts.slug, posts.view_count,
                (SELECT COUNT(*) FROM post_likes WHERE post_likes.post_id = posts.id) AS like_count
            FROM posts
            WHERE posts.status = 1
                AND COALESCE(posts.published_at, posts.created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY (
                posts.view_count
                + (SELECT COUNT(*) FROM post_likes WHERE post_likes.post_id = posts.id) * 5
            ) DESC
            LIMIT 5"
        );

        return [
            'hotPosts' => $stmt->fetchAll(),
            'weekTop' => $weekTopStmt->fetchAll(),
            'monthTop' => $monthTopStmt->fetchAll(),
            'hotStats' => [
                'post_count' => (int) Database::query("SELECT COUNT(*) FROM posts WHERE status = 1")->fetchColumn(),
                'total_views' => (int) Database::query("SELECT COALESCE(SUM(view_count), 0) FROM posts WHERE status = 1")->fetchColumn(),
                'total_likes' => (int) Database::query("SELECT COUNT(*) FROM post_likes")->fetchColumn(),
                'total_comments' => (int) Database::query("SELECT COUNT(*) FROM post_comments WHERE status = 1")->fetchColumn(),
            ],
        ];
    }
}
