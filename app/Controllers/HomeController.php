<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\VisitorIdentifier;
use App\Models\Category;
use App\Models\GuestbookMessage;
use App\Models\Like;
use App\Models\Post;
use App\Models\PostInteractionLog;
use App\Models\SiteContent;

class HomeController
{
    private const SEARCH_QUERY_MAX_LENGTH = 80;

    public function index(?string $panel = null, string $guestbookView = 'list', ?int $guestbookMessageId = null): void
    {
        SiteContent::seedDefaults();

        $pageParam = $_GET['page'] ?? 1;
        $page = max(1, (int) (is_scalar($pageParam) ? $pageParam : 1));
        $category = $this->queryString('category');
        $searchQuery = $this->normalizeSearchQuery($this->queryString('q'));
        $activePanel = $panel ?? $this->queryString('panel', 'posts');
        $activePanel = in_array($activePanel, ['posts', 'hot', 'notice', 'guestbook', 'about'], true) ? $activePanel : 'posts';
        if ($searchQuery !== '') {
            $activePanel = 'posts';
        }
        $guestbookView = in_array($guestbookView, ['list', 'compose', 'detail'], true) ? $guestbookView : 'list';
        if ($activePanel !== 'guestbook') {
            $guestbookView = 'list';
        }

        $panelTitles = [
            'posts' => '首页',
            'hot' => '热榜',
            'notice' => '公告',
            'guestbook' => '留言板',
            'about' => '关于',
        ];
        $pageTitle = $panelTitles[$activePanel] ?? '首页';

        if ($activePanel === 'guestbook' && $guestbookView === 'compose') {
            $pageTitle = '我要留言';
        }

        if ($activePanel === 'guestbook' && $guestbookView === 'detail') {
            $pageTitle = '留言详情';
        }

        if ($searchQuery !== '') {
            $pageTitle = '搜索：' . $searchQuery;
        }

        $guestbookError = '';
        $guestbookOld = [];
        $guestbookSuccess = '';

        if ($activePanel === 'guestbook') {
            $this->startSession();
            $guestbookError = (string) ($_SESSION['guestbook_error'] ?? '');
            $guestbookOld = is_array($_SESSION['guestbook_old'] ?? null) ? $_SESSION['guestbook_old'] : [];
            $guestbookSuccess = (string) ($_SESSION['guestbook_success'] ?? '');
        }

        $result = Post::publishedPaginated($page, 8, $category !== '' ? $category : null, $searchQuery !== '' ? $searchQuery : null);
        $hotData = $this->hotData();
        $guestbookData = $this->guestbookData();
        $guestbookDetail = null;

        if ($activePanel === 'guestbook' && $guestbookView === 'detail' && $guestbookMessageId !== null) {
            $message = GuestbookMessage::find($guestbookMessageId);
            if (
                $message !== null
                && (int) ($message['status'] ?? GuestbookMessage::STATUS_HIDDEN) === GuestbookMessage::STATUS_APPROVED
                && (int) ($message['is_deleted'] ?? 0) === 0
            ) {
                $guestbookDetail = $message;
            }
        }

        $this->recordVisitorPageView($activePanel, $guestbookView, $guestbookMessageId);

        $announcements = SiteContent::allActiveAnnouncements();
        $settings = SiteContent::settings();
        $sidebarAnnouncement = SiteContent::sidebarAnnouncement($settings);
        $aboutData = $this->aboutData($settings);
        $visitorHashes = VisitorIdentifier::hashes();
        $likedPostIds = Like::likedPostIdsForHashes($visitorHashes, array_merge(
            array_column($result['data'], 'id'),
            array_column($hotData['hotPosts'], 'id'),
            array_column($hotData['weekTop'], 'id'),
            array_column($hotData['monthTop'], 'id')
        ));

        $this->render('home/index', [
            'title' => $pageTitle,
            'posts' => $result['data'],
            'pagination' => $result['pagination'],
            'siteSettings' => $settings,
            'copyButtons' => SiteContent::copyButtons(),
            'announcement' => $sidebarAnnouncement,
            'announcements' => $announcements,
            'heroSlides' => SiteContent::heroSlides(),
            'categories' => Category::allWithPostCount(),
            'currentCategory' => $category,
            'currentQuery' => $searchQuery,
            'activePanel' => $activePanel,
            'hotPosts' => $hotData['hotPosts'],
            'weekTop' => $hotData['weekTop'],
            'monthTop' => $hotData['monthTop'],
            'hotStats' => $hotData['hotStats'],
            'guestbookMessages' => $guestbookData['messages'],
            'guestbookPagination' => $guestbookData['pagination'],
            'guestbookStats' => $guestbookData['stats'],
            'guestbookTrends' => $guestbookData['trends'],
            'guestbookView' => $guestbookView,
            'guestbookDetail' => $guestbookDetail,
            'guestbookError' => $guestbookError,
            'guestbookOld' => $guestbookOld,
            'guestbookSuccess' => $guestbookSuccess,
            'aboutHtml' => $aboutData['aboutHtml'],
            'aboutSkills' => $aboutData['skills'],
            'aboutLinks' => $aboutData['links'],
            'aboutStats' => $aboutData['stats'],
            'aboutStatCards' => $aboutData['statCards'],
            'aboutFeatureCards' => $aboutData['featureCards'],
            'likedPostIds' => $likedPostIds,
        ]);

        if ($activePanel === 'guestbook' && session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['guestbook_error'], $_SESSION['guestbook_old'], $_SESSION['guestbook_success']);
        }
    }

    public function profile(): void
    {
        SiteContent::seedDefaults();

        $settings = SiteContent::settings();

        $setting = static function (string $key, string $default = '') use ($settings): string {
            $value = trim((string) ($settings[$key] ?? ''));
            return $value !== '' ? $value : $default;
        };

        $profileCover = $setting('profile_home_cover', $setting('profile_cover', '/assets/img/backgrounds/sidebar-profile-cover.png'));
        $profileAvatar = $setting('profile_avatar', '/assets/img/ZMoon.png');
        $profileName = $setting('profile_name', 'Z-Blog');
        $profileText = $setting('profile_motto', $setting('profile_text', '把日常里的灵感，慢慢写成光。'));

        $statCards = SiteContent::aboutStatCardsWithValues(SiteContent::aboutMetricValues());

        $result = Post::publishedPaginated(1, 9);
        $posts = $result['data'];

        $copyButtons = SiteContent::copyButtons();
        $announcement = SiteContent::sidebarAnnouncement($settings);

        $visitorHashes = VisitorIdentifier::hashes();
        $likedPostIds = Like::likedPostIdsForHashes($visitorHashes, array_column($posts, 'id'));

        $this->recordVisitorPageView('me', 'list', null);

        $this->render('me/index', [
            'title' => '作者主页',
            'siteSettings' => $settings,
            'profileCover' => $profileCover,
            'profileAvatar' => $profileAvatar,
            'profileName' => $profileName,
            'profileText' => $profileText,
            'statCards' => $statCards,
            'posts' => $posts,
            'pagination' => $result['pagination'],
            'copyButtons' => $copyButtons,
            'announcement' => $announcement,
            'likedPostIds' => $likedPostIds,
        ]);
    }

    private function normalizeSearchQuery(string $query): string
    {
        $query = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $query) ?? '';
        $query = preg_replace('/\s+/u', ' ', trim($query)) ?? '';

        if ($query === '') {
            return '';
        }

        if (mb_strlen($query, 'UTF-8') > self::SEARCH_QUERY_MAX_LENGTH) {
            $query = mb_substr($query, 0, self::SEARCH_QUERY_MAX_LENGTH, 'UTF-8');
        }

        return $query;
    }

    private function queryString(string $key, string $default = ''): string
    {
        $value = $_GET[$key] ?? $default;
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    private function recordVisitorPageView(string $panel, string $view = 'list', ?int $sourceId = null): void
    {
        $visitorIdentity = VisitorIdentifier::likeIdentity();
        $sourceType = $panel === 'guestbook' ? 'guestbook' : 'page';
        $action = 'page_view';

        if ($panel === 'guestbook') {
            $action = match ($view) {
                'compose' => 'guestbook_open',
                'detail' => 'guestbook_detail',
                default => 'guestbook_view',
            };
        }

        PostInteractionLog::record($action, [
            'visitor_hash' => $visitorIdentity['primary_hash'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'content_excerpt' => $panel === 'guestbook' ? 'guestbook:' . $view : 'page:' . $panel,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);
    }

    private function aboutData(array $settings): array
    {
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

        return [
            'aboutHtml' => SiteContent::renderRichContent($aboutContent, $aboutMode),
            'skills' => $skills,
            'links' => $links,
            'stats' => $stats,
            'statCards' => SiteContent::aboutStatCardsWithValues($stats),
            'featureCards' => SiteContent::aboutFeatureCards(),
        ];
    }

    private function guestbookData(): array
    {
        GuestbookMessage::createTable();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = GuestbookMessage::approvedPaginated($page, 15);

        $trendDays = 30;
        $trendStart = (new \DateTimeImmutable('today', new \DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai')))
            ->modify('-' . ($trendDays - 1) . ' days')
            ->setTime(0, 0);

        $createdStmt = Database::query(
            "SELECT DATE(created_at) AS day,
                COUNT(*) AS created_count,
                COALESCE(SUM(CASE WHEN admin_reply IS NULL OR TRIM(admin_reply) = '' THEN 1 ELSE 0 END), 0) AS pending_count
             FROM guestbook_messages
             WHERE status = ? AND is_deleted = 0 AND created_at >= ?
             GROUP BY DATE(created_at)
             ORDER BY day ASC",
            [GuestbookMessage::STATUS_APPROVED, $trendStart->format('Y-m-d H:i:s')]
        );

        $createdByDay = [];
        foreach ($createdStmt->fetchAll() as $row) {
            $day = substr((string) ($row['day'] ?? ''), 0, 10);
            if ($day === '') {
                continue;
            }

            $createdByDay[$day] = [
                'created' => (int) ($row['created_count'] ?? 0),
                'pending' => (int) ($row['pending_count'] ?? 0),
            ];
        }

        $repliedStmt = Database::query(
            "SELECT DATE(replied_at) AS day, COUNT(*) AS replied_count
             FROM guestbook_messages
             WHERE status = ?
                AND is_deleted = 0
                AND admin_reply IS NOT NULL
                AND TRIM(admin_reply) <> ''
                AND replied_at >= ?
             GROUP BY DATE(replied_at)
             ORDER BY day ASC",
            [GuestbookMessage::STATUS_APPROVED, $trendStart->format('Y-m-d H:i:s')]
        );

        $repliedByDay = [];
        foreach ($repliedStmt->fetchAll() as $row) {
            $day = substr((string) ($row['day'] ?? ''), 0, 10);
            if ($day !== '') {
                $repliedByDay[$day] = (int) ($row['replied_count'] ?? 0);
            }
        }

        $runningTotal = (int) Database::query(
            "SELECT COUNT(*) FROM guestbook_messages WHERE status = ? AND is_deleted = 0 AND created_at < ?",
            [GuestbookMessage::STATUS_APPROVED, $trendStart->format('Y-m-d H:i:s')]
        )->fetchColumn();

        $trends = [
            'total' => [],
            'recent' => [],
            'replied' => [],
            'pending' => [],
        ];

        for ($i = 0; $i < $trendDays; $i++) {
            $day = $trendStart->modify('+' . $i . ' days')->format('Y-m-d');
            $created = (int) ($createdByDay[$day]['created'] ?? 0);
            $pending = (int) ($createdByDay[$day]['pending'] ?? 0);
            $replied = (int) ($repliedByDay[$day] ?? 0);

            $runningTotal += $created;

            $trends['total'][] = $runningTotal;
            $trends['recent'][] = $created;
            $trends['replied'][] = $replied;
            $trends['pending'][] = $pending;
        }

        $stats = [
            'total' => GuestbookMessage::countByStatus(GuestbookMessage::STATUS_APPROVED),
            'admin' => GuestbookMessage::countRepliedApproved(),
            'recent' => (int) Database::query(
                "SELECT COUNT(*) FROM guestbook_messages WHERE status = ? AND is_deleted = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                [GuestbookMessage::STATUS_APPROVED]
            )->fetchColumn(),
        ];

        return [
            'messages' => $result['data'],
            'pagination' => $result['pagination'],
            'stats' => $stats,
            'trends' => $trends,
        ];
    }

    private function hotData(): array
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

    private function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
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
