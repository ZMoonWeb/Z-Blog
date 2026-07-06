<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\VisitorIdentifier;
use App\Models\GuestbookMessage;
use App\Models\Like;
use App\Models\Post;
use App\Models\PostInteractionLog;
use App\Models\SiteContent;

class PageController
{
    /**
     * 热榜：基于浏览量、点赞数、评论数综合排序
     */
    public function hot(): void
    {
        SiteContent::seedDefaults();
        $this->recordPageInteraction('hot');

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

        $hotPosts = $stmt->fetchAll();
        $likedPostIds = Like::likedPostIdsForHashes(
            VisitorIdentifier::hashes(),
            array_column($hotPosts, 'id')
        );

        $this->render('pages/hot', [
            'title' => '热榜',
            'siteSettings' => SiteContent::settings(),
            'hotPosts' => $hotPosts,
            'likedPostIds' => $likedPostIds,
        ]);
    }

    /**
     * 公告页：列出所有有效公告
     */
    public function notice(): void
    {
        SiteContent::seedDefaults();
        $this->recordPageInteraction('notice');

        $announcements = SiteContent::allActiveAnnouncements();

        $this->render('pages/notice', [
            'title' => '公告',
            'siteSettings' => SiteContent::settings(),
            'announcements' => $announcements,
        ]);
    }

    /**
     * 留言板
     */
    public function guestbook(): void
    {
        $this->startSession();

        SiteContent::seedDefaults();
        GuestbookMessage::createTable();
        $this->recordPageInteraction('guestbook', 'guestbook_view', 'guestbook:list', 'guestbook');

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = GuestbookMessage::approvedPaginated($page, 15);

        $settings = SiteContent::settings();

        $guestbookStats = [
            'total' => GuestbookMessage::countByStatus(GuestbookMessage::STATUS_APPROVED),
            'admin' => GuestbookMessage::countRepliedApproved(),
            'recent' => (int) Database::query(
                "SELECT COUNT(*) FROM guestbook_messages WHERE status = ? AND is_deleted = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                [GuestbookMessage::STATUS_APPROVED]
            )->fetchColumn(),
        ];

        $this->render('pages/guestbook', [
            'title' => (string) ($settings['guestbook_title'] ?? '留言板'),
            'siteSettings' => $settings,
            'messages' => $result['data'],
            'pagination' => $result['pagination'],
            'guestbookStats' => $guestbookStats,
            'guestbookError' => $_SESSION['guestbook_error'] ?? '',
            'guestbookOld' => $_SESSION['guestbook_old'] ?? [],
            'guestbookSuccess' => $_SESSION['guestbook_success'] ?? '',
        ]);

        unset($_SESSION['guestbook_error'], $_SESSION['guestbook_old'], $_SESSION['guestbook_success']);
    }

    public function postGuestbook(): void
    {
        $this->startSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($this->wantsJson()) {
                $this->json(['success' => false, 'message' => '请求方法不正确'], 405);
                return;
            }

            $this->redirect('/guestbook');
            return;
        }

        GuestbookMessage::createTable();

        $content = trim((string) ($_POST['content'] ?? ''));

        $_SESSION['guestbook_old'] = compact('content');

        if ($content === '' || mb_strlen($content) > 1000) {
            if ($this->wantsJson()) {
                $this->json(['success' => false, 'message' => '请输入 1-1000 个字符的留言内容'], 422);
                return;
            }

            $_SESSION['guestbook_error'] = '请输入 1-1000 个字符的留言内容';
            $this->redirect('/guestbook/new');
            return;
        }

        $visitorIdentity = VisitorIdentifier::likeIdentity();
        $visitorHash = (string) ($visitorIdentity['primary_hash'] ?? '');
        $shortHash = $visitorHash !== '' ? substr($visitorHash, 0, 12) : '';
        $remoteIp = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $nickname = $shortHash !== '' ? '访客 ' . $shortHash : ($remoteIp !== '' ? '访客 ' . $remoteIp : '未知访客');

        // 是否管理员留言
        $isAdmin = isset($_SESSION['admin']) && is_array($_SESSION['admin']) ? 1 : 0;

        $messageId = GuestbookMessage::create([
            'nickname' => $nickname,
            'content' => $content,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'status' => GuestbookMessage::STATUS_APPROVED,
            'is_admin' => $isAdmin,
        ]);

        PostInteractionLog::record('guestbook_post', [
            'actor_name' => $nickname,
            'visitor_hash' => $visitorHash,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'content_excerpt' => $content,
            'source_type' => 'guestbook',
            'source_id' => $messageId,
        ]);

        unset($_SESSION['guestbook_old']);
        $_SESSION['guestbook_success'] = '留言已发布，感谢分享';

        if ($this->wantsJson()) {
            $this->json([
                'success' => true,
                'message' => '留言已发布，3 秒后返回留言板。',
                'redirect' => '/guestbook',
            ]);
            return;
        }

        $this->redirect('/guestbook');
    }

    /**
     * 关于页
     */
    public function about(): void
    {
        SiteContent::seedDefaults();
        $this->recordPageInteraction('about');

        $settings = SiteContent::settings();

        $aboutContent = (string) ($settings['about_content'] ?? '');
        $aboutMode = (string) ($settings['about_mode'] ?? 'markdown');

        $skills = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($settings['about_skills'] ?? '')) ?: [])));

        $links = [];
        foreach (preg_split('/\R/u', (string) ($settings['about_links'] ?? '')) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 3));
            if (count($parts) < 3) {
                continue;
            }
            [$label, $icon, $url] = $parts;
            if ($label === '' || $url === '') {
                continue;
            }
            $links[] = [
                'label' => $label,
                'icon' => $icon !== '' ? $icon : 'fa-solid fa-link',
                'url' => $url,
            ];
        }

        $stats = SiteContent::aboutMetricValues();

        $this->render('pages/about', [
            'title' => (string) ($settings['about_title'] ?? '关于本站'),
            'siteSettings' => $settings,
            'aboutHtml' => SiteContent::renderRichContent($aboutContent, $aboutMode),
            'skills' => $skills,
            'links' => $links,
            'stats' => $stats,
            'statCards' => SiteContent::aboutStatCardsWithValues($stats),
            'featureCards' => SiteContent::aboutFeatureCards(),
        ]);
    }

    private function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    private function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    private function wantsJson(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

        return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function recordPageInteraction(
        string $page,
        string $action = 'page_view',
        ?string $excerpt = null,
        string $sourceType = 'page'
    ): void {
        $visitorIdentity = VisitorIdentifier::likeIdentity();

        PostInteractionLog::record($action, [
            'visitor_hash' => $visitorIdentity['primary_hash'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'content_excerpt' => $excerpt ?? 'page:' . $page,
            'source_type' => $sourceType,
        ]);
    }

    private function render(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);

        $viewFile = dirname(__DIR__, 2) . '/resources/views/' . $view . '.php';

        if (!file_exists($viewFile)) {
            http_response_code(500);
            echo 'View not found';
            return;
        }

        require $viewFile;
    }
}
