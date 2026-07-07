<?php
$title = $title ?? '首页';
$posts = $posts ?? [];
$pagination = $pagination ?? [
    'current_page' => 1,
    'last_page' => 1,
    'has_previous' => false,
    'has_next' => false,
];
$siteSettings = $siteSettings ?? [];
$copyButtons = $copyButtons ?? [];
$announcement = $announcement ?? null;
$heroSlides = $heroSlides ?? [];
$categories = $categories ?? [];
$currentCategory = (string) ($currentCategory ?? '');
$currentQuery = trim((string) ($currentQuery ?? ''));
$hasSearchQuery = $currentQuery !== '';
$activePanel = (string) ($activePanel ?? 'posts');
$activePanel = in_array($activePanel, ['posts', 'hot', 'notice', 'guestbook', 'about'], true) ? $activePanel : 'posts';
$hotPosts = $hotPosts ?? [];
$weekTop = $weekTop ?? [];
$monthTop = $monthTop ?? [];
$hotStats = $hotStats ?? [
    'post_count' => count($hotPosts),
    'total_views' => 0,
    'total_likes' => 0,
    'total_comments' => 0,
];
$announcements = $announcements ?? [];
$guestbookMessages = $guestbookMessages ?? [];
$guestbookPagination = $guestbookPagination ?? [
    'current_page' => 1,
    'last_page' => 1,
    'has_previous' => false,
    'has_next' => false,
];
$guestbookStats = $guestbookStats ?? [
    'total' => $guestbookPagination['total'] ?? count($guestbookMessages),
    'admin' => 0,
    'recent' => 0,
];
$guestbookView = (string) ($guestbookView ?? 'list');
$guestbookView = in_array($guestbookView, ['list', 'compose', 'detail'], true) ? $guestbookView : 'list';
$guestbookDetail = isset($guestbookDetail) && is_array($guestbookDetail) ? $guestbookDetail : null;
$guestbookError = (string) ($guestbookError ?? '');
$guestbookSuccess = (string) ($guestbookSuccess ?? '');
$guestbookOld = isset($guestbookOld) && is_array($guestbookOld) ? $guestbookOld : [];
$aboutHtml = $aboutHtml ?? '';
$aboutSkills = $aboutSkills ?? [];
$aboutLinks = $aboutLinks ?? [];
$aboutStats = $aboutStats ?? ['posts' => 0, 'comments' => 0, 'likes' => 0, 'views' => 0];
$aboutStatCards = $aboutStatCards ?? [];
$aboutFeatureCards = $aboutFeatureCards ?? [];
$likedPostIds = isset($likedPostIds) && is_array($likedPostIds) ? array_map('intval', $likedPostIds) : [];

$rankClass = static function (int $index): string {
    return match ($index) {
        0 => 'rank-gold',
        1 => 'rank-silver',
        2 => 'rank-bronze',
        default => '',
    };
};

$formatNumber = static function (int $number): string {
    if ($number >= 10000) {
        return rtrim(rtrim(number_format($number / 10000, 1), '0'), '.') . 'w';
    }

    return (string) $number;
};

$formatDate = static function (?string $date): string {
    $date = trim((string) $date);
    if ($date === '') {
        return '未记录时间';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }

    return date('Y.m.d', $timestamp);
};

$formatTime = static function (?string $date): string {
    $date = trim((string) $date);
    if ($date === '') {
        return '';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '';
    }

    return date('H:i', $timestamp);
};

$renderStatIcon = static function (string $type): void {
    $paths = [
        'view' => '<path d="M12 5c5.1 0 8.5 4.3 9.7 6.1a1.7 1.7 0 0 1 0 1.8C20.5 14.7 17.1 19 12 19s-8.5-4.3-9.7-6.1a1.7 1.7 0 0 1 0-1.8C3.5 9.3 6.9 5 12 5Zm0 2c-3.9 0-6.7 3.1-7.9 5 1.2 1.9 4 5 7.9 5s6.7-3.1 7.9-5c-1.2-1.9-4-5-7.9-5Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/>',
        'like' => '<path d="M12 20.6 10.7 19.4C5.9 15.1 3 12.5 3 9.2 3 6.6 5.1 4.5 7.7 4.5c1.5 0 2.9.7 3.8 1.8.9-1.1 2.3-1.8 3.8-1.8 2.6 0 4.7 2.1 4.7 4.7 0 3.3-2.9 5.9-7.7 10.2L12 20.6Z"/>',
        'comment' => '<path d="M4.5 5.5A2.5 2.5 0 0 1 7 3h10a2.5 2.5 0 0 1 2.5 2.5v7A2.5 2.5 0 0 1 17 15h-6.8L5 19.2V15H7a2.5 2.5 0 0 1-2.5-2.5v-7Zm4 2V9h7V7.5h-7Zm0 3.2v1.5h5.2v-1.5H8.5Z"/>',
        'score' => '<path d="M13 2.8c.3 2.3 1.3 3.7 2.6 5.1 1.7 1.8 3.4 3.7 3.4 6.7 0 3.8-3 6.9-7 6.9s-7-3.1-7-6.9c0-2.8 1.5-5.1 4.2-7.2-.1 1.8.4 3 1.3 4 1.2-2.5 1.2-5.4 2.5-8.6Z"/>',
    ];

    echo '<svg viewBox="0 0 24 24" aria-hidden="true">' . ($paths[$type] ?? $paths['score']) . '</svg>';
};

$renderMiniList = static function (array $items, string $emptyText) use ($formatNumber): void {
    ?>
    <?php if (empty($items)): ?>
        <div class="mini-empty"><?= htmlspecialchars($emptyText) ?></div>
    <?php else: ?>
        <div class="mini-rank-list">
            <?php foreach ($items as $index => $item): ?>
                <a class="mini-rank-item" href="/post/<?= rawurlencode((string) $item['slug']) ?>">
                    <span><?= $index + 1 ?></span>
                    <strong><?= htmlspecialchars((string) $item['title']) ?></strong>
                    <small><?= htmlspecialchars($formatNumber((int) ($item['view_count'] ?? 0))) ?> 浏览</small>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php
};

$setting = static function (string $key, string $default = '') use ($siteSettings): string {
    $value = trim((string) ($siteSettings[$key] ?? ''));
    return $value !== '' ? $value : $default;
};

$profileCover = $setting('profile_cover', '/assets/img/backgrounds/sidebar-profile-cover.png');
$profileAvatar = $setting('profile_avatar', '/assets/img/ZMoon.png');
$profileName = $setting('profile_name', 'Z-Blog');
$profileText = $setting('profile_motto', $setting('profile_text', '把日常里的灵感，慢慢写成光。分享技术、生活与正在成长的想法。'));

$copyButtonIcons = [
    'GitHub' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C6.48 2 2 6.58 2 12.26c0 4.53 2.87 8.37 6.84 9.73.5.1.68-.22.68-.49v-1.73c-2.78.62-3.37-1.37-3.37-1.37-.45-1.19-1.11-1.5-1.11-1.5-.91-.64.07-.63.07-.63 1 .07 1.53 1.06 1.53 1.06.9 1.57 2.36 1.12 2.93.85.09-.67.35-1.12.63-1.38-2.22-.26-4.55-1.14-4.55-5.06 0-1.12.39-2.03 1.03-2.75-.1-.26-.45-1.31.1-2.71 0 0 .84-.28 2.75 1.05A9.29 9.29 0 0 1 12 7c.85 0 1.71.12 2.51.34 1.91-1.33 2.75-1.05 2.75-1.05.55 1.4.2 2.45.1 2.71.64.72 1.03 1.63 1.03 2.75 0 3.93-2.34 4.8-4.57 5.05.36.32.68.95.68 1.91v2.83c0 .27.18.59.69.49A10.16 10.16 0 0 0 22 12.26C22 6.58 17.52 2 12 2Z"></path></svg>',
    'Gitee' => '<svg t="1779002958566" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="2521" width="200" height="200" aria-hidden="true"><path d="M512 1024C229.2224 1024 0 794.7776 0 512S229.2224 0 512 0s512 229.2224 512 512-229.2224 512-512 512z m259.1488-568.8832H480.4096a25.2928 25.2928 0 0 0-25.2928 25.2928l-0.0256 63.2064c0 13.952 11.3152 25.2928 25.2672 25.2928h177.024c13.9776 0 25.2928 11.3152 25.2928 25.2672v12.6464a75.8528 75.8528 0 0 1-75.8528 75.8528H366.592a25.2928 25.2928 0 0 1-25.2672-25.2928v-240.1792a75.8528 75.8528 0 0 1 75.8272-75.8528h353.9456a25.2928 25.2928 0 0 0 25.2672-25.2928l0.0768-63.2064a25.2928 25.2928 0 0 0-25.2672-25.2928H417.152a189.6192 189.6192 0 0 0-189.6192 189.6448v353.9456c0 13.9776 11.3152 25.2928 25.2928 25.2928h372.9408a170.6496 170.6496 0 0 0 170.6496-170.6496v-145.408a25.2928 25.2928 0 0 0-25.2928-25.2672z" fill="#C71D23" p-id="2522"></path></svg>',
    'QQ' => '<svg t="1779002974473" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="3504" width="200" height="200" aria-hidden="true"><path d="M148.859845 404.057356c-5.11465 15.34395 0 20.4586 0 76.719751 0 15.34395-61.375801 76.719751-86.949052 143.210202-25.57325 66.490451-25.57325 138.095552 10.2293 163.668803 35.802551 30.6879 71.605101-92.063701 76.719752-71.605101 0 5.11465 5.11465 15.34395 5.11465 25.57325 15.34395 35.802551 35.802551 71.605101 61.375801 102.293002 5.11465 5.11465-35.802551 20.4586-61.375801 61.3758-25.57325 40.917201 10.2293 117.636952 132.980902 117.636952 158.554152 0 199.471353-56.261151 199.471353-56.261151h51.1465c10.2293 0 86.949051 66.490451 194.356703 56.261151 184.127403-20.4586 158.554152-81.834401 143.210202-122.751602-15.34395-40.917201-66.490451-61.375801-66.490451-61.375801 46.031851-51.146501 51.146501-76.719751 66.490451-122.751601 5.11465-20.4586 51.146501 102.293002 81.834402 71.605101 15.34395-10.2293 40.917201-61.375801 15.34395-163.668803s-81.834401-127.866252-81.834401-143.210202V404.057356c-10.2293-35.802551-30.6879-25.57325-30.687901-35.802551 0-204.586003-153.439502-368.254805-342.681555-368.254805S174.433095 163.668802 174.433095 368.254805c0 15.34395-15.34395 5.11465-25.57325 35.802551z m0 0" fill="#4A9AFD" p-id="3505"></path></svg>',
    '邮箱' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm0 3.2v.25l8 4.8 8-4.8V8.2l-8 4.8-8-4.8Z"></path></svg>',
    '微信' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.4 4.2c-4.1 0-7.4 2.76-7.4 6.16 0 1.96 1.1 3.63 2.87 4.8l-.72 2.16 2.51-1.26c.87.25 1.76.38 2.74.38.35 0 .69-.02 1.02-.06a5.43 5.43 0 0 1-.28-1.72c0-3.04 2.9-5.5 6.47-5.5.07 0 .14 0 .21.02-.62-2.82-3.67-4.98-7.42-4.98Zm-2.4 3.2a.9.9 0 1 1 0 1.8.9.9 0 0 1 0-1.8Zm4.8 0a.9.9 0 1 1 0 1.8.9.9 0 0 1 0-1.8Zm4.82 3.05c-2.96 0-5.36 1.9-5.36 4.25s2.4 4.25 5.36 4.25c.66 0 1.28-.1 1.88-.28l2.05 1.03-.58-1.76C21.19 17.13 22 15.98 22 14.7c0-2.35-2.4-4.25-5.38-4.25Zm-1.76 2.46a.76.76 0 1 1 0 1.52.76.76 0 0 1 0-1.52Zm3.52 0a.76.76 0 1 1 0 1.52.76.76 0 0 1 0-1.52Z"></path></svg>',
];

$slides = [];
foreach ($heroSlides as $slide) {
    $image = trim((string) ($slide['image_url'] ?? ''));
    $url = trim((string) ($slide['link_url'] ?? '/'));
    $slideTitle = trim((string) ($slide['title'] ?? ''));
    if ($image === '' || $slideTitle === '') {
        continue;
    }

    $slides[] = [
        'image' => $image,
        'url' => $url !== '' ? $url : '/',
        'title' => $slideTitle,
    ];
}

$categoryItems = [
    [
        'name' => '全部',
        'slug' => '',
        'active' => $currentCategory === '',
    ],
];

foreach ($categories as $category) {
    $slug = (string) ($category['slug'] ?? '');
    $categoryItems[] = [
        'name' => (string) ($category['name'] ?? ''),
        'slug' => $slug,
        'active' => $currentCategory !== '' && $currentCategory === $slug,
    ];
}

$currentCategoryName = '全部';
foreach ($categoryItems as $categoryItem) {
    if (!empty($categoryItem['active'])) {
        $currentCategoryName = (string) ($categoryItem['name'] ?? '全部');
        break;
    }
}

$buildPostsUrl = static function (array $overrides = []) use ($currentCategory, $currentQuery): string {
    $params = [];

    if ($currentCategory !== '') {
        $params['category'] = $currentCategory;
    }

    if ($currentQuery !== '') {
        $params['q'] = $currentQuery;
    }

    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '' || ($key === 'page' && (int) $value <= 1)) {
            unset($params[$key]);
            continue;
        }

        $params[$key] = $value;
    }

    $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    return $queryString === '' ? '/' : '/?' . $queryString;
};

ob_start();
?>

<?php
// 欢迎页仅在纯首页（/ 路径、posts 面板、无搜索、无分类、第 1 页）渲染
$welcomeRequestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$isWelcomeHome = $welcomeRequestPath === '/'
    && $activePanel === 'posts'
    && $currentQuery === ''
    && $currentCategory === ''
    && (int) ($pagination['current_page'] ?? 1) <= 1;

if ($isWelcomeHome):
    $welcomeBrand = $setting('site_title', 'Z-Blog');
    $welcomeChars = preg_split('//u', $welcomeBrand, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $welcomeCharIndex = 0;
?>
<!-- 欢迎页：全屏覆盖，点击任意位置进入博客 -->
<div class="welcome-screen welcome-boot" data-welcome-screen role="button" tabindex="0" aria-label="点击进入博客">
    <div class="welcome-inner">
        <h1 class="welcome-title">
            <?php foreach ($welcomeChars as $welcomeChar):
                $welcomeCharClass = 'welcome-letter';
                if ($welcomeChar === '-') {
                    $welcomeCharClass .= ' welcome-letter-dash';
                }
            ?>
                <span class="<?= htmlspecialchars($welcomeCharClass) ?>" style="--welcome-i:<?= $welcomeCharIndex++ ?>" data-char="<?= htmlspecialchars($welcomeChar) ?>"><?= htmlspecialchars($welcomeChar) ?></span>
            <?php endforeach; ?>
        </h1>
        <div class="welcome-enter">
            <span class="welcome-enter-text">点击进入博客</span>
            <svg class="welcome-enter-arrow" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 10l5 5 5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="home-shell">
    <aside class="home-sidebar" aria-label="侧边栏">
        <section class="profile-card" aria-label="个人资料">
            <div class="profile-cover">
                <img src="<?= htmlspecialchars($profileCover) ?>" alt="<?= htmlspecialchars($profileName) ?> 个人横幅">
            </div>

            <div class="profile-main">
                <div class="profile-avatar-wrap">
                    <img class="profile-avatar" src="<?= htmlspecialchars($profileAvatar) ?>" alt="<?= htmlspecialchars($profileName) ?> 头像">
                    <span class="profile-status" aria-label="在线"></span>
                </div>

                <h1><?= htmlspecialchars($profileName) ?></h1>
                <p><?= htmlspecialchars($profileText) ?></p>

                <?php if (!empty($copyButtons)): ?>
                    <div class="profile-socials" aria-label="复制按钮">
                        <?php foreach ($copyButtons as $button): ?>
                            <?php
                            $label = trim((string) ($button['label'] ?? '复制'));
                            $copyValue = (string) ($button['copy_value'] ?? '');
                            $iconSvg = $copyButtonIcons[$label] ?? '';
                            $buttonClass = match ($label) {
                                'GitHub' => 'copy-button-github',
                                'Gitee' => 'copy-button-gitee',
                                'QQ' => 'copy-button-qq',
                                '邮箱' => 'copy-button-email',
                                '微信' => 'copy-button-wechat',
                                default => 'copy-button-default',
                            };
                            ?>
                            <?php if ($copyValue !== ''): ?>
                                <button class="<?= htmlspecialchars($buttonClass) ?>" type="button" data-copy-value="<?= htmlspecialchars($copyValue) ?>" aria-label="复制<?= htmlspecialchars($label) ?>">
                                    <?php if ($iconSvg !== ''): ?>
                                        <?= $iconSvg ?>
                                    <?php else: ?>
                                        <span class="copy-button-text"><?= htmlspecialchars(mb_substr($label, 0, 2)) ?></span>
                                    <?php endif; ?>
                                    <span class="copy-tooltip" role="status" aria-live="polite"></span>
                                </button>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($announcement !== null): ?>
            <section class="notice-card" aria-labelledby="sidebar-notice-title">
                <div class="notice-card-head">
                    <span class="notice-card-icon" aria-hidden="true">
                        <svg class="notice-card-icon-light" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg"><path d="M79.7696 689.8688h876.4928v107.2128a111.7696 111.7696 0 0 1-111.7696 111.7696H191.5392a111.7696 111.7696 0 0 1-111.7696-111.7696v-107.2128z" fill="#FDA338"></path><path d="M853.8624 268.4416h-87.04l-163.2768-145.1008A129.28 129.28 0 0 0 432.384 122.88L267.4176 268.4416H182.1696a133.12 133.12 0 0 0-133.12 133.12v404.8384a133.12 133.12 0 0 0 133.12 133.12h671.6928a133.12 133.12 0 0 0 133.12-133.12V401.5616a133.12 133.12 0 0 0-133.12-133.12zM472.9856 168.96a67.7376 67.7376 0 0 1 89.7024 0l111.5136 99.1744H360.2944z m452.5568 637.44a71.68 71.68 0 0 1-71.68 71.68H182.1696a71.68 71.68 0 0 1-71.68-71.68V401.5616a71.68 71.68 0 0 1 71.68-71.68h671.6928a71.68 71.68 0 0 1 71.68 71.68z" fill="#474A54"></path><path d="M756.1216 479.744H271.7184a30.72 30.72 0 0 0 0 61.44h484.4032a30.72 30.72 0 0 0 0-61.44zM611.4304 659.1488H271.7184a30.72 30.72 0 1 0 0 61.44h339.712a30.72 30.72 0 1 0 0-61.44z" fill="#474A54"></path></svg>
                        <svg t="1780672335963" class="icon notice-card-icon-dark" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="1707" width="200" height="200"><path d="M837.26 267.28H659l-73.59-123.56c-14.76-24.84-41.66-39.75-71.52-39.91h-0.51c-29.86 0-56.59 14.73-71.69 39.25L366 267.28H190.19c-68.56 0-124.28 53.17-124.28 118.59V802.6c0 65.42 55.72 118.59 124.28 118.59h646.89c68.56 0 124.27-53.17 124.27-118.59V385.87c0.18-65.42-55.54-118.59-124.09-118.59zM190.2 839.78c-23.65 0-42.89-16.69-42.89-37.2v-416.7c0-20.51 19.24-37.18 42.89-37.18h221.52l99.49-163.29 4.53 0.22 97 163.07h224.52c16.89 0 27.47 8.08 32.28 12.89S880 374.41 880 385.68v416.9c0 20.51-19.24 37.2-42.89 37.2z" fill="#949DA6" p-id="1708"></path><path d="M741.75 470.13H312.41c-23.6 0-42.72 18.22-42.72 40.7s19.12 40.7 42.72 40.7h429.34c23.6 0 42.72-18.22 42.72-40.7s-19.12-40.7-42.72-40.7zM577.84 673.64H312.41c-23.6 0-42.72 18.22-42.72 40.7S288.81 755 312.41 755h265.43c23.6 0 42.72-18.22 42.72-40.7s-19.12-40.7-42.72-40.7zM350.82 266.62h325.62v81.4H350.82z" fill="#949DA6" p-id="1709"></path></svg>
                    </span>
                    <h2 id="sidebar-notice-title">侧栏公告</h2>
                </div>
                <div class="notice-content">
                    <?= $announcement['html'] ?? '' ?>
                </div>
            </section>
        <?php endif; ?>
    </aside>

    <div class="home-content" data-home-panels data-active-panel="<?= htmlspecialchars($activePanel) ?>">
        <section class="home-panel" data-home-panel="posts" aria-label="首页文章内容" <?= $activePanel === 'posts' ? '' : 'hidden' ?>>
            <?php if (!empty($slides)): ?>
            <section class="hero-card" data-hero-carousel aria-label="首页轮播图">
                <div class="hero-track" data-hero-track>
                    <?php foreach ($slides as $slide): ?>
                        <a class="hero-slide" href="<?= htmlspecialchars($slide['url']) ?>" aria-label="<?= htmlspecialchars($slide['title']) ?>">
                            <img src="<?= htmlspecialchars($slide['image']) ?>" alt="<?= htmlspecialchars($slide['title']) ?>">
                            <div class="hero-slide-content">
                                <h2><?= htmlspecialchars($slide['title']) ?></h2>
                                <span>阅读更多 →</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if (count($slides) > 1): ?>
                    <button class="hero-nav hero-nav-prev" type="button" data-hero-prev aria-label="上一张">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.8 5.2 8 12l6.8 6.8-1.6 1.6L4.8 12l8.4-8.4z"></path></svg>
                    </button>
                    <button class="hero-nav hero-nav-next" type="button" data-hero-next aria-label="下一张">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9.2 18.8 6.8-6.8-6.8-6.8 1.6-1.6 8.4 8.4-8.4 8.4z"></path></svg>
                    </button>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="category-dropdown" data-category-dropdown data-current-category="<?= htmlspecialchars($currentCategory) ?>" aria-label="文章分类">
            <button class="category-trigger" type="button" data-category-trigger aria-expanded="false">
                <span data-category-trigger-label><?= htmlspecialchars($currentCategoryName === '全部' ? '标签分类' : $currentCategoryName) ?></span>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.4 8.6 12 13.2l4.6-4.6L18 10l-6 6-6-6z"></path></svg>
            </button>
            <div class="category-menu" data-category-menu>
                <?php foreach ($categoryItems as $category): ?>
                    <?php if ($category['name'] !== ''): ?>
                        <?php $categoryUrl = $buildPostsUrl(['category' => $category['slug'] !== '' ? $category['slug'] : null, 'page' => null]); ?>
                        <a class="<?= $category['active'] ? 'is-active' : '' ?>" href="<?= $categoryUrl ?>" data-category-link data-category-slug="<?= htmlspecialchars((string) $category['slug']) ?>" data-category-name="<?= htmlspecialchars((string) $category['name']) ?>">
                            <?= htmlspecialchars($category['name']) ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($hasSearchQuery): ?>
            <section class="notice-card search-result-card" aria-live="polite">
                <div>
                    <span class="search-result-eyebrow">搜索结果</span>
                    <h2>关键词“<?= htmlspecialchars($currentQuery) ?>”</h2>
                    <p>共找到 <?= (int) ($pagination['total'] ?? count($posts)) ?> 篇相关文章<?= $currentCategoryName !== '全部' ? '，当前分类：' . htmlspecialchars($currentCategoryName) : '' ?>。</p>
                </div>
                <a class="search-result-clear" href="/">清除搜索</a>
            </section>
        <?php endif; ?>

        <section class="post-section" id="posts" aria-label="文章列表">
            <?php if (empty($posts)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon" aria-hidden="true">
                        <svg t="1779004415428" class="icon" viewBox="0 0 1082 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="7754" width="200" height="200"><path d="M844.643404 51.020945l-7.631004-47.361709a4.349412 4.349412 0 0 0-7.995625-1.562663l-24.950518 41.058968c-60.553189 99.541629-190.709989 161.214726-328.510816 226.377771-57.089286 27.008024-116.131901 55.135957-171.892923 86.506416-123.997304 66.165753-227.341413 208.836879-161.032415 364.764596a4.466612 4.466612 0 0 1-2.161684 5.76883C75.500707 758.568678 27.566022 817.83267 1.130974 899.494831A22.042663 22.042663 0 1 0 43.062429 913.10302c23.439944-72.559649 64.004069-126.185032 120.611534-152.62008a4.33639 4.33639 0 0 1 5.208877 1.302219c42.243988 55.435468 117.121587 98.434743 215.530285 98.434743 70.736542 0 180.448503-22.476302 301.502792-143.517568l1.185019-0.2344c193.822293-227.406524 181.828855-513.855662 157.425269-665.433967z" fill="#919191" p-id="7755"></path></svg>
                    </div>
                    <h2><?= $hasSearchQuery ? '没有找到相关文章' : '还没有文章，去写一篇吧' ?></h2>
                    <p><?= $hasSearchQuery ? '试试更换关键词或分类。' : '内容正在准备中，稍后再来看看。灵感正在路上。' ?></p>
                </div>
            <?php else: ?>
                <div class="post-list">
                    <?php foreach ($posts as $index => $post): ?>
                        <?php
                        $summary = trim((string) ($post['summary'] ?? ''));
                        if ($summary === '') {
                            $summary = mb_substr(strip_tags((string) ($post['content'] ?? '')), 0, 120) . '...';
                        }

                        $coverImage = trim((string) ($post['cover_image'] ?? ''));
                        if ($coverImage === '') {
                            $coverImage = '/assets/img/ZMoon.png';
                        }

                        $publishedAt = trim((string) ($post['published_at'] ?? $post['created_at'] ?? ''));
                        $tagSource = $post['tags'] ?? '';
                        $postTags = preg_split('/[,，\s]+/u', trim((string) $tagSource), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                        if (empty($postTags) && !empty($post['category_name'])) {
                            $postTags = [(string) $post['category_name']];
                        }

                        $commentCount = (int) ($post['comment_count'] ?? 0);
                        $likeCount = (int) ($post['like_count'] ?? 0);
                        $viewCount = (int) ($post['view_count'] ?? 0);
                        $postId = (int) ($post['id'] ?? 0);
                        $postSlug = (string) ($post['slug'] ?? '');
                        $postUrl = '/post/' . rawurlencode($postSlug);
                        $isLiked = in_array($postId, $likedPostIds, true);
                        $isPinned = $index === 0;
                        ?>
                        <article class="post-card">
                            <a class="post-card-cover" href="<?= htmlspecialchars($postUrl) ?>" aria-label="阅读<?= htmlspecialchars((string) $post['title']) ?>">
                                <img src="<?= htmlspecialchars($coverImage) ?>" alt="<?= htmlspecialchars((string) $post['title']) ?>封面" loading="lazy">
                            </a>

                            <div class="post-card-body">
                                <div class="post-card-labels">
                                    <?php if ($isPinned): ?>
                                        <span class="post-pin">置顶</span>
                                    <?php endif; ?>
                                </div>

                                <h3><a class="post-card-title-link" href="<?= htmlspecialchars($postUrl) ?>"><?= htmlspecialchars((string) $post['title']) ?></a></h3>
                                <p><a class="post-card-summary-link" href="<?= htmlspecialchars($postUrl) ?>"><?= htmlspecialchars($summary) ?></a></p>

                                <div class="post-card-footer">
                                    <div class="post-card-meta">
                                        <span class="post-time"><?= htmlspecialchars($publishedAt) ?></span>
                                        <?php foreach (array_slice($postTags, 0, 4) as $tag): ?>
                                            <span class="post-tag"><?= htmlspecialchars((string) $tag) ?></span>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="post-stats" aria-label="文章互动数据">
                                        <span class="post-stat">👁 <?= $viewCount ?></span>
                                        <form class="inline-like-form" method="post" action="<?= htmlspecialchars($postUrl) ?>/like">
                                            <?= \App\Core\Security\Csrf::field() ?>
                                            <input type="hidden" name="redirect_to" value="<?= htmlspecialchars((string) ($_SERVER['REQUEST_URI'] ?? '/')) ?>">
                                            <button class="post-stat post-like inline-like-button <?= $isLiked ? 'is-liked' : '' ?>" type="submit" data-like-toggle data-like-post-id="<?= $postId ?>" aria-label="<?= $isLiked ? '取消点赞' : '点赞' ?>：<?= htmlspecialchars((string) $post['title']) ?>" aria-pressed="<?= $isLiked ? 'true' : 'false' ?>">
                                                <span class="post-like-heart" aria-hidden="true">♥</span>
                                                <span data-like-count><?= $likeCount ?></span>
                                            </button>
                                        </form>
                                        <span class="post-stat">
                                            <svg viewBox="0 0 1024 1024" fill="currentColor" width="14" height="14" style="vertical-align: -2px; margin-right: 2px;">
                                                <path d="M878.3 98.2H145.7c-44.7 0-81 36.3-81 81V714c0 44.7 36.3 81 81 81h192.8l149.2 121.8c7.4 6 16.3 9 25.3 9 8.9 0 17.9-3 25.2-9l150-121.8h190c44.7 0 81-36.3 81-81V179.2c0.1-44.7-36.3-81-80.9-81z m1 615.8c0 0.5-0.5 1-1 1H674.1c-9.2 0-18.1 3.2-25.2 9L513.1 834.2 378.1 724c-7.1-5.8-16.1-9-25.3-9H145.7c-0.5 0-1-0.5-1-1V179.2c0-0.5 0.5-1 1-1h732.5c0.5 0 1 0.5 1 1V714z"/>
                                                <path d="M322.1 447.6m-50 0a50 50 0 1 0 100 0 50 50 0 1 0-100 0Z"/>
                                                <path d="M513.1 447.6m-50 0a50 50 0 1 0 100 0 50 50 0 1 0-100 0Z"/>
                                                <path d="M704.3 447.6m-50 0a50 50 0 1 0 100 0 50 50 0 1 0-100 0Z"/>
                                            </svg>
                                            <?= $commentCount ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if (($pagination['last_page'] ?? 1) > 1): ?>
                    <nav class="pagination" aria-label="分页">
                        <?php $previousPageUrl = $buildPostsUrl(['page' => (int) $pagination['current_page'] - 1]); ?>
                        <?php $nextPageUrl = $buildPostsUrl(['page' => (int) $pagination['current_page'] + 1]); ?>
                        <?php if (!empty($pagination['has_previous'])): ?>
                            <a href="<?= htmlspecialchars($previousPageUrl) ?>" data-post-page-link>上一页</a>
                        <?php else: ?>
                            <span>上一页</span>
                        <?php endif; ?>

                        <span><?= (int) $pagination['current_page'] ?> / <?= (int) $pagination['last_page'] ?></span>

                        <?php if (!empty($pagination['has_next'])): ?>
                            <a href="<?= htmlspecialchars($nextPageUrl) ?>" data-post-page-link>下一页</a>
                        <?php else: ?>
                            <span>下一页</span>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
            </section>
        </section>

        <section class="home-panel" data-home-panel="hot" aria-label="热榜内容" <?= $activePanel === 'hot' ? '' : 'hidden' ?>>
            <?php require dirname(__DIR__) . '/partials/hot-ranking.php'; ?>
        </section>

        <section class="home-panel" data-home-panel="guestbook" aria-label="留言板内容" <?= $activePanel === 'guestbook' ? '' : 'hidden' ?>>
            <?php
            $messages = $guestbookMessages;
            $pagination = $guestbookPagination;
            $guestbookBaseUrl = '/guestbook';
            $guestbookFormAction = '/guestbook';
            require dirname(__DIR__) . '/partials/guestbook-content.php';
            ?>
        </section>

        <section class="home-panel" data-home-panel="about" aria-label="关于内容" <?= $activePanel === 'about' ? '' : 'hidden' ?>>
            <?php
            $skills = $aboutSkills;
            $links = $aboutLinks;
            $stats = $aboutStats;
            $statCards = $aboutStatCards;
            $featureCards = $aboutFeatureCards;
            require dirname(__DIR__) . '/partials/about-content.php';
            ?>
        </section>

        <section class="home-panel" data-home-panel="notice" aria-label="公告内容" <?= $activePanel === 'notice' ? '' : 'hidden' ?>>
            <?php require dirname(__DIR__) . '/partials/notice-list.php'; ?>
        </section>
    </div>
</div>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/main.php';
?>
