<?php
$siteSettings = $siteSettings ?? [];

$setting = static function (string $key, string $default = '') use ($siteSettings): string {
    $value = trim((string) ($siteSettings[$key] ?? ''));
    return $value !== '' ? $value : $default;
};

$siteTitle = $setting('site_title', 'Z-Blog');
$siteLogo = $setting('site_logo', '/assets/img/ZMoon.png');
$siteAvatar = $setting('site_avatar', '/assets/img/ZMoon.png');
$loginNotice = trim((string) ($loginNotice ?? ''));
$loginNoticeTitle = trim((string) ($loginNoticeTitle ?? '登录状态异常'));

$publicPath = dirname(__DIR__, 3) . '/public';
$frontCssVersion = @filemtime($publicPath . '/assets/css/front/index.css') ?: time();
$adminCssVersion = @filemtime($publicPath . '/assets/css/admin/index.css') ?: time();
$frontJsVersion = @filemtime($publicPath . '/assets/js/front/index.js') ?: time();
$frontThemeJsVersion = @filemtime($publicPath . '/assets/js/front/modules/theme.js') ?: time();
$adminJsVersion = @filemtime($publicPath . '/assets/js/admin/index.js') ?: time();
$adminThemeJsVersion = @filemtime($publicPath . '/assets/js/admin/modules/theme.js') ?: time();
$adminSidebarJsVersion = @filemtime($publicPath . '/assets/js/admin/modules/sidebar.js') ?: time();
$adminModalJsVersion = @filemtime($publicPath . '/assets/js/admin/modules/modal.js') ?: time();
$adminEditorJsVersion = @filemtime($publicPath . '/assets/js/admin/modules/editor.js') ?: time();
$adminFormsJsVersion = @filemtime($publicPath . '/assets/js/admin/modules/forms.js') ?: time();
$adminUploadPreviewJsVersion = @filemtime($publicPath . '/assets/js/admin/modules/upload-preview.js') ?: time();
?>

<!DOCTYPE html>
<html lang="zh-CN" data-theme="light" data-theme-source="system" data-admin-theme="light" data-admin-theme-source="system">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            const source = hasManualTheme ? 'manual' : 'system';

            root.setAttribute('data-theme', theme);
            root.setAttribute('data-theme-source', source);
            root.setAttribute('data-admin-theme', theme);
            root.setAttribute('data-admin-theme-source', source);
            root.style.colorScheme = theme === 'light' ? 'only light' : 'dark';
        })();
    </script>
    <title>后台登录 - <?= htmlspecialchars($siteTitle) ?></title>
    <link rel="icon" href="/assets/img/ZMoon.png" type="image/png">
    <link rel="preload" href="/assets/font/MiSans/woff2/MiSans-Regular.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/assets/css/admin/index.css?v=<?= $adminCssVersion ?>">
    <link rel="stylesheet" href="/assets/css/front/index.css?v=<?= $frontCssVersion ?>">
</head>
<body class="admin-login-page admin-login-ready">
    <header class="site-header" aria-label="后台登录顶部导航">
        <div class="site-header-inner">
            <button class="mobile-sidebar-button" type="button" aria-label="打开侧边栏" data-mobile-menu-open>
                <svg t="1779003834073" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="6176" width="200" height="200" aria-hidden="true"><path d="M0 837.818182c0 25.716364 20.712727 46.545455 46.592 46.545454h651.543273A46.452364 46.452364 0 0 0 744.727273 837.818182c0-25.716364-20.712727-46.545455-46.592-46.545455H46.592A46.452364 46.452364 0 0 0 0 837.818182z m0-325.818182c0 25.716364 20.712727 46.545455 46.545455 46.545455h744.727272c25.716364 0 46.545455-20.666182 46.545455-46.545455 0-25.716364-20.712727-46.545455-46.545455-46.545455H46.545455c-25.716364 0-46.545455 20.666182-46.545455 46.545455zM0 186.181818c0 25.716364 20.922182 46.545455 46.661818 46.545455h930.676364A46.498909 46.498909 0 0 0 1024 186.181818c0-25.716364-20.922182-46.545455-46.661818-46.545454H46.661818A46.498909 46.498909 0 0 0 0 186.181818z" fill="#2c2c2c" p-id="6177"></path></svg>
            </button>

            <a class="site-brand" href="/" aria-label="返回首页">
                <img src="<?= htmlspecialchars($siteLogo) ?>" alt="<?= htmlspecialchars($siteTitle) ?> Logo">
                <span><?= htmlspecialchars($siteTitle) ?></span>
            </a>

            <nav class="site-nav" aria-label="后台登录导航">
                <a href="/">首页</a>
            </nav>

            <div class="site-actions" aria-label="快捷操作">
                <button class="site-icon-button theme-toggle-button" type="button" aria-label="切换颜色模式" aria-pressed="false" data-theme-toggle>
                    <svg t="1782521702947" class="theme-icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="8058" width="200" height="200" aria-hidden="true"><path d="M512 204.8a307.2 307.2 0 1 1-0.1024 614.4 307.2 307.2 0 0 1 0-614.4z m-21.0944 95.744c-16.5888-9.216-29.3888-4.4032-55.0912 5.2224a215.04 215.04 0 0 0 0 402.2272c25.6 9.728 38.5024 14.5408 55.0912 5.3248a50.4832 50.4832 0 0 0 6.5536-4.5056C512 696.5248 512 679.1168 512 644.096V369.664c0-34.816 0-52.4288-14.5408-64.6144a50.5856 50.5856 0 0 0-6.5536-4.5056z" p-id="8059"></path></svg>
                </button>
                <a class="site-avatar" href="/" aria-label="返回前台首页">
                    <img src="<?= htmlspecialchars($siteAvatar) ?>" alt="用户头像">
                </a>
            </div>
        </div>
    </header>

    <main class="admin-login-shell" aria-labelledby="admin-login-title">
        <div class="admin-login-identity">
            <span class="admin-login-logo" aria-hidden="true">
                <img src="<?= htmlspecialchars($siteLogo) ?>" alt="">
            </span>
            <strong class="admin-login-site-title"><?= htmlspecialchars($siteTitle) ?></strong>
        </div>

        <section class="admin-login-card">
            <div class="admin-login-panel">
                <div class="admin-login-card-body">
                    <h1 class="admin-login-card-title" id="admin-login-title">登录</h1>

                    <?php if (!empty($error)): ?>
                        <div class="admin-alert" role="alert">
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <form class="admin-login-form" method="post" action="/admin/login">
                        <?= \App\Core\Security\Csrf::field() ?>
                        <div class="admin-form-group admin-login-field">
                            <label class="admin-form-label" for="username">
                                <span>用户名</span>
                            </label>
                            <div class="admin-login-input-wrap">
                                <svg class="admin-login-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M20 21a8 8 0 0 0-16 0"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                <input class="admin-input" type="text" id="username" name="username" value="<?= htmlspecialchars($username ?? '') ?>" autocomplete="username" placeholder="管理员用户名" required>
                            </div>
                        </div>

                        <div class="admin-form-group admin-login-field">
                            <label class="admin-form-label" for="password">
                                <span>密码</span>
                                <span class="admin-form-hint">安全验证</span>
                            </label>
                            <div class="admin-password-field admin-login-input-wrap">
                                <svg class="admin-login-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="4" y="11" width="16" height="10" rx="3"></rect>
                                    <path d="M8 11V8a4 4 0 0 1 8 0v3"></path>
                                </svg>
                                <input class="admin-input" type="password" id="password" name="password" autocomplete="current-password" placeholder="管理员密码" required>
                                <button type="button" class="admin-password-toggle" onclick="togglePassword()" aria-label="显示密码" aria-controls="password" aria-pressed="false">
                                    <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                    <svg class="eye-closed" style="display:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                        <line x1="1" y1="1" x2="23" y2="23"></line>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="admin-btn admin-btn-block admin-login-submit" data-login-submit>
                            <span class="admin-login-submit-spinner" aria-hidden="true"></span>
                            <span class="admin-login-submit-text">登录</span>
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <?php if ($loginNotice !== ''): ?>
        <div class="admin-modal admin-login-session-modal is-open" id="admin-login-session-modal" data-admin-modal aria-hidden="false">
            <div class="admin-modal-backdrop" data-admin-modal-close></div>
            <section class="admin-modal-panel admin-login-session-panel" role="dialog" aria-modal="true" aria-labelledby="admin-login-session-title">
                <div class="admin-modal-head admin-login-session-head">
                    <div>
                        <h2 class="admin-section-title" id="admin-login-session-title"><?= htmlspecialchars($loginNoticeTitle !== '' ? $loginNoticeTitle : '登录状态异常') ?></h2>
                        <p class="admin-section-desc"><?= htmlspecialchars($loginNotice) ?></p>
                    </div>
                </div>
                <div class="admin-form-actions admin-login-session-actions">
                    <button class="admin-btn" type="button" data-admin-modal-close>确认</button>
                </div>
            </section>
        </div>
    <?php endif; ?>

    <script src="/assets/js/front/modules/theme.js?v=<?= $frontThemeJsVersion ?>"></script>
    <script src="/assets/js/front/index.js?v=<?= $frontJsVersion ?>"></script>
    <script src="/assets/js/admin/modules/theme.js?v=<?= $adminThemeJsVersion ?>"></script>
    <script src="/assets/js/admin/modules/sidebar.js?v=<?= $adminSidebarJsVersion ?>"></script>
    <script src="/assets/js/admin/modules/modal.js?v=<?= $adminModalJsVersion ?>"></script>
    <script src="/assets/js/admin/modules/editor.js?v=<?= $adminEditorJsVersion ?>"></script>
    <script src="/assets/js/admin/modules/forms.js?v=<?= $adminFormsJsVersion ?>"></script>
    <script src="/assets/js/admin/modules/upload-preview.js?v=<?= $adminUploadPreviewJsVersion ?>"></script>
    <script src="/assets/js/admin/index.js?v=<?= $adminJsVersion ?>"></script>
</body>
</html>
