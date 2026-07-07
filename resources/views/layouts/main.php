<?php
$title = $title ?? '首页';
$content = $content ?? '';
$siteSettings = $siteSettings ?? [];
$currentQuery = (string) ($currentQuery ?? '');

$setting = static function (string $key, string $default = '') use ($siteSettings): string {
    $value = trim((string) ($siteSettings[$key] ?? ''));
    return $value !== '' ? $value : $default;
};

$siteTitle = $setting('site_title', 'Z-Blog');
$siteLogo = $setting('site_logo', '/assets/img/ZMoon.png');
$siteAvatar = $setting('profile_avatar', $setting('site_avatar', '/assets/img/ZMoon.png'));
$footerLogo = $setting('footer_logo', '/assets/img/ZMoon.png');
$footerBrand = $setting('footer_brand', $siteTitle);
$footerText = $setting('footer_text', '© 2026 筑梦科技 · 记录想法，沉淀内容');
$footerLinkText = $setting('footer_link_text', 'QQ交流群');
$footerLinkUrl = $setting('footer_link_url', 'https://qm.qq.com/q/PE4qEHoF8W');
$footerPowered = $setting('footer_powered', 'Powered by PHP · Theme inspired by clean card design');

$publicPath = dirname(__DIR__, 3) . '/public';
$frontCssVersion = @filemtime($publicPath . '/assets/css/front/index.css') ?: time();
$frontTailwindCssVersion = @filemtime($publicPath . '/assets/css/front/tailwind.css') ?: time();
$frontJsVersion = @filemtime($publicPath . '/assets/js/front/index.js') ?: time();
$frontThemeJsVersion = @filemtime($publicPath . '/assets/js/front/modules/theme.js') ?: time();
$frontToastJsVersion = @filemtime($publicPath . '/assets/js/front/modules/toast.js') ?: time();
$frontMobileMenuJsVersion = @filemtime($publicPath . '/assets/js/front/modules/mobile-menu.js') ?: time();
$frontCarouselJsVersion = @filemtime($publicPath . '/assets/js/front/modules/carousel.js') ?: time();
$frontSearchJsVersion = @filemtime($publicPath . '/assets/js/front/modules/search.js') ?: time();
$frontLikeJsVersion = @filemtime($publicPath . '/assets/js/front/modules/like.js') ?: time();
$frontCommentJsVersion = @filemtime($publicPath . '/assets/js/front/modules/comment.js') ?: time();
$frontGuestbookJsVersion = @filemtime($publicPath . '/assets/js/front/modules/guestbook.js') ?: time();
$loadingChars = preg_split('//u', $siteTitle, -1, PREG_SPLIT_NO_EMPTY) ?: [];
?>

<!DOCTYPE html>
<html lang="zh-CN" data-theme="light" data-theme-source="system">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(\App\Core\Security\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
    <meta name="color-scheme" content="light dark">
    <script>
        (() => {
            const storageKey = 'zblog-front-theme';
            const root = document.documentElement;
            let storedTheme = null;

            try {
                storedTheme = window.localStorage.getItem(storageKey);
            } catch (error) {
                storedTheme = null;
            }

            const hasManualTheme = storedTheme === 'light' || storedTheme === 'dark';
            const systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = hasManualTheme ? storedTheme : (systemDark ? 'dark' : 'light');

            root.setAttribute('data-theme', theme);
            root.setAttribute('data-theme-source', hasManualTheme ? 'manual' : 'system');
            root.style.colorScheme = theme === 'light' ? 'only light' : 'dark';
        })();
    </script>
    <style>
        /* 关键内联样式：在外部 CSS 与脚本就绪前，强制隐藏页面骨架并让加载遮罩 / 欢迎页纯色占位，杜绝首页内容闪现 */
        html{background:#f5f5f7}
        html[data-theme="dark"]{background:#000000}
        .front-loading-screen{position:fixed;inset:0;z-index:10002;display:flex;align-items:center;justify-content:center;background:#f5f5f7;opacity:1;visibility:visible}
        html[data-theme="dark"] .front-loading-screen{background:#000000}
        body.is-front-loading{overflow:hidden}
        .welcome-screen{position:fixed;inset:0;z-index:10001;display:flex;align-items:center;justify-content:center;background:#f5f5f7}
        html[data-theme="dark"] .welcome-screen{background:#000000}
        .welcome-screen.welcome-boot{visibility:visible !important;opacity:1 !important;background:#f5f5f7 !important}
        html[data-theme="dark"] .welcome-screen.welcome-boot{background:#000000 !important}
        .welcome-screen.welcome-boot .welcome-inner{opacity:0 !important;visibility:hidden !important}
        /* JS 接管前隐藏页面骨架（顶栏 / 主体 / 底栏）；欢迎页因 welcome-boot 仍可见，作为纯色遮罩 */
        html:not(.js-ready) body > .site-header,
        html:not(.js-ready) body > .site-main,
        html:not(.js-ready) body > .site-footer{visibility:hidden}
    </style>
    <title><?= htmlspecialchars($title) ?> - <?= htmlspecialchars($siteTitle) ?></title>
    <link rel="icon" href="/assets/img/ZMoon.png" type="image/png">
    <!-- 项目字体 MiSans：先 preload，避免首屏闪现系统字体 -->
    <link rel="preload" href="/assets/font/MiSans/woff2/MiSans-Regular.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/font/MiSans/woff2/MiSans-Bold.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/assets/css/front/tailwind.css?v=<?= $frontTailwindCssVersion ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/front/index.css?v=<?= $frontCssVersion ?>">
</head>
<body>
    <!-- 加载遮罩：置于 body 最前，确保首次绘制即覆盖内容，避免闪现 -->
    <div class="front-loading-screen" data-front-loading aria-live="polite" aria-label="页面加载中">
        <div class="front-loading-inner" aria-hidden="true">
            <h1 class="front-loading-title">
                <?php foreach ($loadingChars as $index => $loadingChar):
                    $loadingCharClass = 'welcome-letter';
                    if ($loadingChar === '-') {
                        $loadingCharClass .= ' welcome-letter-dash';
                    }
                ?>
                    <span class="<?= htmlspecialchars($loadingCharClass) ?>" style="--welcome-i:<?= (int) $index ?>" data-char="<?= htmlspecialchars($loadingChar) ?>"><?= htmlspecialchars($loadingChar) ?></span>
                <?php endforeach; ?>
            </h1>
        </div>
    </div>

    <!-- 全局消息提示容器 -->
    <div class="toast-container" data-toast-container aria-live="polite" aria-atomic="true"></div>

    <!-- 顶部导航栏：全宽固定顶部 -->
    <header class="site-header">
        <div class="site-header-inner">
            <button class="mobile-sidebar-button" type="button" aria-label="打开侧边栏" data-mobile-menu-open>
                <svg t="1779003834073" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="6176" width="200" height="200" aria-hidden="true"><path d="M0 837.818182c0 25.716364 20.712727 46.545455 46.592 46.545454h651.543273A46.452364 46.452364 0 0 0 744.727273 837.818182c0-25.716364-20.712727-46.545455-46.592-46.545455H46.592A46.452364 46.452364 0 0 0 0 837.818182z m0-325.818182c0 25.716364 20.712727 46.545455 46.545455 46.545455h744.727272c25.716364 0 46.545455-20.666182 46.545455-46.545455 0-25.716364-20.712727-46.545455-46.545455-46.545455H46.545455c-25.716364 0-46.545455 20.666182-46.545455 46.545455zM0 186.181818c0 25.716364 20.922182 46.545455 46.661818 46.545455h930.676364A46.498909 46.498909 0 0 0 1024 186.181818c0-25.716364-20.922182-46.545455-46.661818-46.545454H46.661818A46.498909 46.498909 0 0 0 0 186.181818z" fill="#2c2c2c" p-id="6177"></path></svg>
            </button>

            <a class="site-brand" href="/" aria-label="返回 <?= htmlspecialchars($siteTitle) ?> 首页">
                <img src="<?= htmlspecialchars($siteLogo) ?>" alt="<?= htmlspecialchars($siteTitle) ?> Logo">
                <span><?= htmlspecialchars($siteTitle) ?></span>
            </a>

            <nav class="site-nav" aria-label="前台导航">
                <a href="/" data-home-panel-target="posts">首页</a>
                <a href="/hot" data-home-panel-target="hot">热榜</a>
                <a href="/notice" data-home-panel-target="notice">公告</a>
                <a href="/guestbook" data-home-panel-target="guestbook">留言板</a>
                <a href="/about" data-home-panel-target="about">关于</a>
            </nav>

            <div class="site-actions" aria-label="快捷操作">
                <div class="site-search" data-site-search>
                    <button class="site-icon-button site-search-toggle" type="button" aria-label="打开搜索" aria-expanded="false" aria-controls="site-search-panel" data-site-search-toggle>
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M10.8 18.1a7.3 7.3 0 1 1 0-14.6 7.3 7.3 0 0 1 0 14.6Zm0-2a5.3 5.3 0 1 0 0-10.6 5.3 5.3 0 0 0 0 10.6Z"/>
                            <path d="m16.2 16.1 4.1 4.1-1.4 1.4-4.1-4.1z"/>
                        </svg>
                    </button>

                    <div class="site-search-panel" id="site-search-panel" data-site-search-panel hidden>
                        <form class="site-search-form" method="get" action="/" role="search" data-site-search-form>
                            <label class="sr-only" for="site-search-input">搜索文章</label>
                            <svg class="site-search-icon" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M10.8 18.1a7.3 7.3 0 1 1 0-14.6 7.3 7.3 0 0 1 0 14.6Zm0-2a5.3 5.3 0 1 0 0-10.6 5.3 5.3 0 0 0 0 10.6Z"/>
                                <path d="m16.2 16.1 4.1 4.1-1.4 1.4-4.1-4.1z"/>
                            </svg>
                            <input class="site-search-input" id="site-search-input" name="q" type="search" value="<?= htmlspecialchars($currentQuery) ?>" placeholder="搜索文章..." autocomplete="off" maxlength="80" data-site-search-input>
                            <button class="site-search-close" type="button" aria-label="关闭搜索" data-site-search-close>
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="m6.4 5 12.6 12.6-1.4 1.4L5 6.4 6.4 5Z"/>
                                    <path d="M17.6 5 19 6.4 6.4 19 5 17.6 17.6 5Z"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
                <button class="site-icon-button theme-toggle-button" type="button" aria-label="切换颜色模式" data-theme-toggle>
                    <svg t="1782521702947" class="theme-icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="8058" width="200" height="200" aria-hidden="true">
                        <path d="M512 204.8a307.2 307.2 0 1 1-0.1024 614.4 307.2 307.2 0 0 1 0-614.4z m-21.0944 95.744c-16.5888-9.216-29.3888-4.4032-55.0912 5.2224a215.04 215.04 0 0 0 0 402.2272c25.6 9.728 38.5024 14.5408 55.0912 5.3248a50.4832 50.4832 0 0 0 6.5536-4.5056C512 696.5248 512 679.1168 512 644.096V369.664c0-34.816 0-52.4288-14.5408-64.6144a50.5856 50.5856 0 0 0-6.5536-4.5056z" p-id="8059"></path>
                    </svg>
                </button>
                <a class="site-avatar" href="/me" aria-label="作者主页">
                    <img src="<?= htmlspecialchars($siteAvatar) ?>" alt="作者头像">
                </a>
            </div>
        </div>
    </header>

    <!-- 手机端侧边抽屉导航 -->
    <div class="mobile-menu-mask" data-mobile-menu-mask hidden></div>
    <aside class="mobile-menu-drawer" data-mobile-menu-drawer aria-label="手机端导航" aria-hidden="true">
        <div class="mobile-menu-head">
            <strong>导航菜单</strong>
            <button type="button" aria-label="关闭侧边栏" data-mobile-menu-close>
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m6.4 5 12.6 12.6-1.4 1.4L5 6.4 6.4 5Z"/>
                    <path d="M17.6 5 19 6.4 6.4 19 5 17.6 17.6 5Z"/>
                </svg>
            </button>
        </div>
        <nav class="mobile-menu-nav">
            <a href="/" data-home-panel-target="posts">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10.8 12 3l9 7.8-1.3 1.5-1.2-1V20h-5.2v-5.4h-2.6V20H5.5v-8.7l-1.2 1L3 10.8Z"/></svg>
                <span>首页</span>
            </a>
            <a href="/hot" data-home-panel-target="hot">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.1 2.6c.4 2.6 1.6 4.2 3.1 5.8 1.8 1.9 3.8 4 3.8 7.4 0 3.7-3 6.7-8 6.7s-8-3-8-6.7c0-2.9 1.7-5.4 4.6-7.5-.1 1.9.4 3 1.3 4 1.2-2.5 1.2-5.4 2.5-8.6Z"/></svg>
                <span>热榜</span>
            </a>
            <a href="/notice" data-home-panel-target="notice">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H8.4L4 22v-3.3A2 2 0 0 1 3 17V6a2 2 0 0 1 2-2Zm2 4v2h10V8H7Zm0 4v2h7v-2H7Z"/></svg>
                <span>公告</span>
            </a>
            <a href="/guestbook" data-home-panel-target="guestbook">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v8A2.5 2.5 0 0 1 17.5 16H9l-5 4V5.5Zm4 2V9h8V7.5H8Zm0 3.5v1.5h5.8V11H8Z"/></svg>
                <span>留言板</span>
            </a>
            <a href="/about" data-home-panel-target="about">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 1 0 20 10 10 0 0 1 0-20Zm-1 8v7h2v-7h-2Zm0-3v2h2V7h-2Z"/></svg>
                <span>关于</span>
            </a>
        </nav>
    </aside>

    <main class="site-main">
        <?= $content ?>
    </main>

    <!-- 底部 Footer：极简专业版 -->
    <footer class="site-footer">
        <div class="site-footer-inner">
            <div class="site-footer-brand" aria-label="<?= htmlspecialchars($footerBrand) ?>">
                <img src="<?= htmlspecialchars($footerLogo) ?>" alt="<?= htmlspecialchars($footerBrand) ?> Logo">
                <span><?= htmlspecialchars($footerBrand) ?></span>
            </div>
            <div class="site-footer-copy">
                <span><?= htmlspecialchars($footerText) ?></span>
                <span class="site-footer-credit">设计和开发由 <a href="<?= htmlspecialchars($footerLinkUrl) ?>" target="_blank" rel="noopener noreferrer">ZMoon</a> 完成</span>
            </div>
        </div>
    </footer>

    <script src="/assets/js/front/modules/theme.js?v=<?= $frontThemeJsVersion ?>" defer></script>
    <script src="/assets/js/front/modules/toast.js?v=<?= $frontToastJsVersion ?>" defer></script>
    <script src="/assets/js/front/modules/mobile-menu.js?v=<?= $frontMobileMenuJsVersion ?>" defer></script>
    <script src="/assets/js/front/modules/carousel.js?v=<?= $frontCarouselJsVersion ?>" defer></script>
    <script src="/assets/js/front/modules/search.js?v=<?= $frontSearchJsVersion ?>" defer></script>
    <script src="/assets/js/front/index.js?v=<?= $frontJsVersion ?>" defer></script>
    <script src="/assets/js/front/modules/like.js?v=<?= $frontLikeJsVersion ?>" defer></script>
    <script src="/assets/js/front/modules/comment.js?v=<?= $frontCommentJsVersion ?>" defer></script>
    <script src="/assets/js/front/modules/guestbook.js?v=<?= $frontGuestbookJsVersion ?>" defer></script>
</body>
</html>
