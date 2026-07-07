<?php

declare(strict_types=1);

namespace App\Modules\Guestbook\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\VisitorIdentifier;
use App\Models\Category;
use App\Models\Like;
use App\Models\Post;
use App\Models\PostInteractionLog;
use App\Models\SiteContent;
use App\Modules\Guestbook\Requests\GuestbookRequest;
use App\Modules\Guestbook\Services\GuestbookService;

class GuestbookController extends Controller
{
    private const SEARCH_QUERY_MAX_LENGTH = 80;

    public function __construct(
        private ?GuestbookService $guestbook = null,
        private ?GuestbookRequest $guestbookRequest = null
    ) {
        parent::__construct();
        $this->guestbook ??= new GuestbookService();
        $this->guestbookRequest ??= new GuestbookRequest();
    }

    public function index(): void
    {
        $this->renderHomePage('guestbook');
    }

    public function compose(): void
    {
        $this->renderHomePage('guestbook', 'compose');
    }

    public function detail(int $id): void
    {
        $this->renderHomePage('guestbook', 'detail', $id);
    }

    public function store(): void
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

        $this->guestbook->createTable();

        $content = trim((string) ($_POST['content'] ?? ''));
        $_SESSION['guestbook_old'] = compact('content');

        $errors = $this->guestbookRequest->validate(['content' => $content]);
        if (!empty($errors)) {
            if ($this->wantsJson()) {
                $this->json(['success' => false, 'message' => (string) reset($errors)], 422);
                return;
            }

            $_SESSION['guestbook_error'] = (string) reset($errors);
            $this->redirect('/guestbook/new');
            return;
        }

        $this->guestbook->createPublicMessage($content);

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

    private function renderHomePage(?string $panel = null, string $guestbookView = 'list', ?int $guestbookMessageId = null): void
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
        $guestbookData = $this->guestbook->publicData();
        $guestbookDetail = null;

        if ($activePanel === 'guestbook' && $guestbookView === 'detail' && $guestbookMessageId !== null) {
            $guestbookDetail = $this->guestbook->visibleDetail($guestbookMessageId);
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

    protected function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
