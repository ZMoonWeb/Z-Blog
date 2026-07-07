<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Core\Config;
use App\Core\Database;
use App\Models\Category;
use App\Models\Comment;
use App\Models\GuestbookMessage;
use App\Models\Like;
use App\Models\Post;

class DashboardService
{
    public function __construct(private ?ServerMetricsService $serverMetrics = null)
    {
        $this->serverMetrics ??= new ServerMetricsService();
    }

    public function currentAdmin(): ?array
    {
        $admin = $_SESSION['admin'] ?? null;

        return is_array($admin) ? $admin : null;
    }

    public function dashboardData(): array
    {
        $posts = Post::all();

        return [
            'admin' => $this->currentAdmin(),
            'stats' => [
                'posts' => count($posts),
                'categories' => count(Category::all()),
                'published' => count(array_filter($posts, static fn (array $post): bool => (int) $post['status'] === 1)),
                'drafts' => count(array_filter($posts, static fn (array $post): bool => (int) $post['status'] === 0)),
                'comments' => Comment::countAll(),
                'likes' => Like::countAll(),
                'guestbook' => GuestbookMessage::countAll(),
                'guestbook_pending' => GuestbookMessage::countByStatus(GuestbookMessage::STATUS_PENDING),
            ],
            'trend' => $this->buildWeeklyTrend(),
            'server' => $this->serverMetrics->serverInfo(),
            'blogVersion' => $this->currentBlogVersion(),
            'updateCheckUrlConfigured' => trim((string) Config::get('app.update_check_url', '')) !== '',
        ];
    }

    public function currentBlogVersion(): string
    {
        $version = trim((string) Config::get('app.version', '1.0.1'));
        return $version !== '' ? $version : '1.0.1';
    }

    /**
     * @return array<int, array{date: string, label: string, start: string, end: string, posts: int, comments: int, likes: int}>
     */
    private function buildWeeklyTrend(): array
    {
        $tz = new \DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai');
        $today = new \DateTimeImmutable('today', $tz);
        $days = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = $today->modify("-{$i} days");
            $days[] = [
                'date' => $day->format('Y-m-d'),
                'label' => $day->format('n/j'),
                'start' => $day->format('Y-m-d 00:00:00'),
                'end' => $day->format('Y-m-d 23:59:59'),
                'posts' => 0,
                'comments' => 0,
                'likes' => 0,
            ];
        }

        $dayMap = [];
        foreach ($days as $index => $day) {
            $dayMap[$day['date']] = $index;
        }

        $stmt = Database::query(
            "SELECT DATE(created_at) AS d, COUNT(*) AS c
            FROM posts
            WHERE created_at >= ? AND created_at <= ?
            GROUP BY d",
            [$days[0]['start'], $days[6]['end']]
        );
        foreach ($stmt->fetchAll() as $row) {
            $d = (string) ($row['d'] ?? '');
            if (isset($dayMap[$d])) {
                $days[$dayMap[$d]]['posts'] = (int) $row['c'];
            }
        }

        $stmt = Database::query(
            "SELECT DATE(created_at) AS d, COUNT(*) AS c
            FROM post_comments
            WHERE created_at >= ? AND created_at <= ?
            GROUP BY d",
            [$days[0]['start'], $days[6]['end']]
        );
        foreach ($stmt->fetchAll() as $row) {
            $d = (string) ($row['d'] ?? '');
            if (isset($dayMap[$d])) {
                $days[$dayMap[$d]]['comments'] = (int) $row['c'];
            }
        }

        $stmt = Database::query(
            "SELECT DATE(created_at) AS d, COUNT(*) AS c
            FROM post_likes
            WHERE created_at >= ? AND created_at <= ?
            GROUP BY d",
            [$days[0]['start'], $days[6]['end']]
        );
        foreach ($stmt->fetchAll() as $row) {
            $d = (string) ($row['d'] ?? '');
            if (isset($dayMap[$d])) {
                $days[$dayMap[$d]]['likes'] = (int) $row['c'];
            }
        }

        return $days;
    }
}
