<?php

declare(strict_types=1);

namespace App\Modules\Home\Services;

use App\Core\VisitorIdentifier;
use App\Models\Category;
use App\Models\Like;
use App\Models\Post;
use App\Models\PostInteractionLog;
use App\Models\SiteContent;
use App\Modules\About\Services\AboutPageService;
use App\Modules\Guestbook\Services\GuestbookService;
use App\Modules\Hot\Services\HotRankingService;

class HomePageService
{
    private const SEARCH_QUERY_MAX_LENGTH = 80;

    public function __construct(
        private ?AboutPageService $about = null,
        private ?GuestbookService $guestbook = null,
        private ?HotRankingService $hot = null
    ) {
        $this->about ??= new AboutPageService();
        $this->guestbook ??= new GuestbookService();
        $this->hot ??= new HotRankingService();
    }

    public function defaultPanel(): ?string
    {
        return null;
    }

    public function pageData(?string $panel = null, string $guestbookView = 'list', ?int $guestbookMessageId = null): array
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
        $hotData = $this->hot->rankingData();
        $guestbookData = $this->guestbook->publicData();
        $guestbookDetail = null;

        if ($activePanel === 'guestbook' && $guestbookView === 'detail' && $guestbookMessageId !== null) {
            $guestbookDetail = $this->guestbook->visibleDetail($guestbookMessageId);
        }

        $this->recordVisitorPageView($activePanel, $guestbookView, $guestbookMessageId);

        $announcements = SiteContent::allActiveAnnouncements();
        $settings = SiteContent::settings();
        $sidebarAnnouncement = SiteContent::sidebarAnnouncement($settings);
        $aboutData = $this->about->aboutData($settings);
        $visitorHashes = VisitorIdentifier::hashes();
        $likedPostIds = Like::likedPostIdsForHashes($visitorHashes, array_merge(
            array_column($result['data'], 'id'),
            array_column($hotData['hotPosts'], 'id'),
            array_column($hotData['weekTop'], 'id'),
            array_column($hotData['monthTop'], 'id')
        ));

        return [
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
        ];
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

    private function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
