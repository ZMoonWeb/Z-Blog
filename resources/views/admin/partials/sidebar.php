<?php
$active = $active ?? '';
$admin = $admin ?? null;
$adminName = is_array($admin) ? (string) ($admin['username'] ?? '管理员') : '管理员';
$adminInitial = mb_substr($adminName !== '' ? $adminName : '管', 0, 1);
$siteSettings = $siteSettings ?? (isset($settings) && is_array($settings) ? $settings : \App\Models\SiteContent::settings());
$adminAvatar = trim((string) ($siteSettings['profile_avatar'] ?? $siteSettings['site_avatar'] ?? ''));
if ($adminAvatar === '') {
    $adminAvatar = \App\Models\SiteContent::DEFAULT_AVATAR;
}

$adminBootChars = ['Z', '-', 'B', 'l', 'o', 'g'];
$adminBootIndex = 0;
?>
<script>
(function () {
    try {
        var root = document.documentElement;
        // 提前应用主题（在顶栏渲染前），防止切换页面时顶栏闪白
        var stored = null;
        try { stored = localStorage.getItem('zblog-admin-theme'); } catch (e) {}
        var dark;
        var source;
        if (stored === 'dark' || stored === 'light') {
            dark = stored === 'dark';
            source = 'manual';
        } else {
            dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            source = 'system';
        }
        var theme = dark ? 'dark' : 'light';
        root.setAttribute('data-admin-theme', theme);
        root.setAttribute('data-theme', theme);
        root.setAttribute('data-admin-theme-source', source);
        root.setAttribute('data-theme-source', source);
        root.style.colorScheme = theme;

        // 只有刷新（reload/back_forward）才显示全屏加载动画；
        // 点击侧栏导航跳转的新页面属于 navigate，直接跳过全屏动画
        var navType = 'navigate';
        try {
            var entries = performance.getEntriesByType('navigation');
            if (entries.length > 0 && entries[0].type) {
                navType = entries[0].type;
            } else if (performance.navigation) {
                navType = performance.navigation.type === 1 ? 'reload' : 'navigate';
            }
        } catch (e) {}

        if (navType === 'navigate') {
            root.classList.add('boot-skip');
        }
    } catch (e) {}
})();
</script>
<div class="admin-boot" data-admin-boot aria-hidden="true">
    <div class="admin-boot-inner">
        <h1 class="admin-boot-title">
            <?php foreach ($adminBootChars as $char):
                $charClass = 'admin-boot-letter';
                if ($char === '-') {
                    $charClass .= ' admin-boot-letter-dash';
                }
            ?>
                <span class="<?= htmlspecialchars($charClass) ?>" style="--boot-i:<?= $adminBootIndex++ ?>" data-char="<?= htmlspecialchars($char) ?>"><?= htmlspecialchars($char) ?></span>
            <?php endforeach; ?>
        </h1>
    </div>
</div>

<header class="site-header admin-site-header admin-shell-header" data-admin-header aria-label="后台顶部导航">
    <div class="site-header-inner admin-site-header-inner admin-shell-header-inner">
        <div class="admin-shell-left">
            <button class="admin-sidebar-toggle" type="button" onclick="toggleAdminSidebar()" aria-label="展开侧边栏" aria-expanded="false">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <a class="site-brand admin-site-brand admin-shell-brand" href="/admin" aria-label="返回后台概览">
                <span class="admin-brand-logo" aria-hidden="true">
                    <img src="/assets/img/ZMoon.png" alt="">
                </span>
                <span>
                    <span class="admin-sidebar-title">Z-Blog Admin</span>
                    <span class="admin-sidebar-subtitle">内容工作台</span>
                </span>
            </a>
        </div>

        <nav class="site-nav admin-site-nav admin-shell-nav" aria-label="后台快捷导航" hidden>
            <a class="<?= $active === 'dashboard' ? 'active' : '' ?>" href="/admin">控制台</a>
            <a class="<?= $active === 'posts' ? 'active' : '' ?>" href="/admin/posts">文章</a>
            <a class="<?= $active === 'categories' ? 'active' : '' ?>" href="/admin/categories">分类</a>
            <a class="<?= $active === 'announcements' ? 'active' : '' ?>" href="/admin/announcements">公告</a>
            <a class="<?= $active === 'guestbook' ? 'active' : '' ?>" href="/admin/guestbook">留言板</a>
            <a class="<?= $active === 'activity' ? 'active' : '' ?>" href="/admin/activity">活动</a>
            <a class="<?= $active === 'profile' ? 'active' : '' ?>" href="/admin/profile">个人资料</a>
            <a class="<?= $active === 'settings' ? 'active' : '' ?>" href="/admin/settings">前台设置</a>
            <a class="<?= $active === 'backend_settings' ? 'active' : '' ?>" href="/admin/backend-settings">后台设置</a>
        </nav>

        <div class="site-actions admin-site-actions admin-shell-actions" aria-label="后台快捷操作">
            <button class="site-icon-button admin-site-icon-button admin-theme-toggle" type="button" aria-label="切换颜色模式" aria-pressed="false" data-admin-theme-toggle>
                <svg t="1782521702947" class="admin-theme-icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="8058" width="200" height="200" aria-hidden="true"><path d="M512 204.8a307.2 307.2 0 1 1-0.1024 614.4 307.2 307.2 0 0 1 0-614.4z m-21.0944 95.744c-16.5888-9.216-29.3888-4.4032-55.0912 5.2224a215.04 215.04 0 0 0 0 402.2272c25.6 9.728 38.5024 14.5408 55.0912 5.3248a50.4832 50.4832 0 0 0 6.5536-4.5056C512 696.5248 512 679.1168 512 644.096V369.664c0-34.816 0-52.4288-14.5408-64.6144a50.5856 50.5856 0 0 0-6.5536-4.5056z" p-id="8059"></path></svg>
            </button>
            <a class="admin-site-user admin-shell-user" href="/admin/profile" aria-label="个人资料" title="<?= htmlspecialchars($adminName) ?> · 个人资料">
                <span class="admin-user-avatar"><img src="<?= htmlspecialchars($adminAvatar) ?>" alt="<?= htmlspecialchars($adminName) ?> 头像"></span>
            </a>
        </div>
    </div>
</header>

<div class="admin-sidebar-mask" onclick="closeAdminSidebar()" aria-hidden="true"></div>

<aside class="admin-sidebar" id="adminSidebar">
    <div class="admin-sidebar-brand">
        <a class="admin-brand-link" href="/admin" aria-label="返回后台概览">
            <span class="admin-brand-logo" aria-hidden="true">
                <img src="/assets/img/ZMoon.png" alt="">
            </span>
            <span>
                <span class="admin-sidebar-title">Z-Blog</span>
                <span class="admin-sidebar-subtitle">内容管理系统</span>
            </span>
        </a>
        <button class="admin-sidebar-close" type="button" onclick="closeAdminSidebar()" aria-label="收起侧边栏">×</button>
    </div>

    <nav class="admin-nav" aria-label="后台导航">
        <a class="admin-nav-item <?= $active === 'dashboard' ? 'active' : '' ?>" href="/admin">
            <svg class="admin-nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.8A1.8 1.8 0 0 1 5.8 4h4.4A1.8 1.8 0 0 1 12 5.8v4.4a1.8 1.8 0 0 1-1.8 1.8H5.8A1.8 1.8 0 0 1 4 10.2V5.8Zm8 8A1.8 1.8 0 0 1 13.8 12h4.4a1.8 1.8 0 0 1 1.8 1.8v4.4a1.8 1.8 0 0 1-1.8 1.8h-4.4a1.8 1.8 0 0 1-1.8-1.8v-4.4ZM4 15a3 3 0 1 1 6 0v2a3 3 0 1 1-6 0v-2Zm10-8a3 3 0 1 1 6 0v2a3 3 0 1 1-6 0V7Z"/></svg>
            <span>概览</span>
        </a>
        <a class="admin-nav-item <?= $active === 'posts' ? 'active' : '' ?>" href="/admin/posts">
            <svg class="admin-nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm8 1.8V8h3.2L14 4.8ZM7 11v2h10v-2H7Zm0 4v2h7v-2H7Z"/></svg>
            <span>文章</span>
        </a>
        <a class="admin-nav-item <?= $active === 'categories' ? 'active' : '' ?>" href="/admin/categories">
            <svg class="admin-nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H10l2 2h5.5A2.5 2.5 0 0 1 20 7.5v9A2.5 2.5 0 0 1 17.5 19h-11A2.5 2.5 0 0 1 4 16.5v-11Zm4 5.5v2h8v-2H8Z"/></svg>
            <span>分类</span>
        </a>
        <a class="admin-nav-item <?= $active === 'announcements' ? 'active' : '' ?>" href="/admin/announcements">
            <svg class="admin-nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H8.4L4 22v-3.3A2 2 0 0 1 3 17V6a2 2 0 0 1 2-2Zm2 4v2h10V8H7Zm0 4v2h7v-2H7Z"/></svg>
            <span>公告</span>
        </a>
        <a class="admin-nav-item <?= $active === 'guestbook' ? 'active' : '' ?>" href="/admin/guestbook">
            <svg class="admin-nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v8A2.5 2.5 0 0 1 17.5 16H9l-5 4V5.5Zm4 2V9h8V7.5H8Zm0 3.5v1.5h5.8V11H8Z"/></svg>
            <span>留言板</span>
        </a>
        <a class="admin-nav-item <?= $active === 'interactions' ? 'active' : '' ?>" href="/admin/interactions">
            <svg class="admin-nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21.2 10.6 20C5.6 15.5 2.5 12.7 2.5 9.2 2.5 6.4 4.7 4.2 7.5 4.2c1.6 0 3.1.8 4 2 0.9-1.2 2.4-2 4-2 2.8 0 5 2.2 5 5 0 3.5-3.1 6.3-8.1 10.8L12 21.2Zm-6-10.7h3v-3h2v3h3v2h-3v3H9v-3H6v-2Z"/></svg>
            <span>互动记录</span>
        </a>
        <a class="admin-nav-item <?= $active === 'activity' ? 'active' : '' ?>" href="/admin/activity">
            <svg class="admin-nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 20 5.5v6c0 4.7-3.2 8.8-8 10.5-4.8-1.7-8-5.8-8-10.5v-6L12 2Zm0 3-5 2.2v4.3c0 3.3 2 6.1 5 7.5 3-1.4 5-4.2 5-7.5V7.2L12 5Zm-1 3h2v5h-2V8Zm0 7h2v2h-2v-2Z"/></svg>
            <span>活动记录</span>
        </a>
        <a class="admin-nav-item <?= $active === 'profile' ? 'active' : '' ?>" href="/admin/profile">
            <svg class="admin-nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            <span>个人资料</span>
        </a>
        <a class="admin-nav-item <?= $active === 'settings' ? 'active' : '' ?>" href="/admin/settings">
            <svg class="admin-nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M19.4 13.5c.06-.48.1-.98.1-1.5s-.04-1.02-.1-1.5l2-1.52-2-3.46-2.38.96a7.75 7.75 0 0 0-2.6-1.5L14.06 2h-4.12l-.36 2.98a7.75 7.75 0 0 0-2.6 1.5L4.6 5.52l-2 3.46 2 1.52c-.06.48-.1.98-.1 1.5s.04 1.02.1 1.5l-2 1.52 2 3.46 2.38-.96a7.75 7.75 0 0 0 2.6 1.5l.36 2.98h4.12l.36-2.98a7.75 7.75 0 0 0 2.6-1.5l2.38.96 2-3.46-2-1.52ZM12 15.5a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7Z"/></svg>
            <span>前台设置</span>
        </a>
        <a class="admin-nav-item <?= $active === 'backend_settings' ? 'active' : '' ?>" href="/admin/backend-settings">
            <svg class="admin-nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5 19 6v5.5c0 4.2-2.8 7.9-7 9.5-4.2-1.6-7-5.3-7-9.5V6l7-3.5Zm0 2.3L7 7.3v4.2c0 3 1.8 5.8 5 7.2 3.2-1.4 5-4.2 5-7.2V7.3l-5-2.5Zm-3 5.7h6v2H9v-2Zm0 3.5h6v2H9v-2Z"/></svg>
            <span>后台设置</span>
        </a>
    </nav>

    <div class="admin-sidebar-footer">
        <span>当前版本</span>
        <strong>Z-Blog v<?= \App\Core\Config::get('app.version', '1.0.0') ?></strong>
    </div>
</aside>
