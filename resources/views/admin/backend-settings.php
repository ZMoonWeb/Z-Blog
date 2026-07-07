<?php
$admin = is_array($admin ?? null) ? $admin : [];
$sessionInfo = is_array($sessionInfo ?? null) ? $sessionInfo : [];
$flash = $flash ?? null;
$blogVersion = trim((string) ($blogVersion ?? '1.0.2'));
if ($blogVersion === '') {
    $blogVersion = '1.0.2';
}
$updateCheckUrlConfigured = (bool) ($updateCheckUrlConfigured ?? false);

$adminName = trim((string) ($admin['username'] ?? '管理员'));
$loginAt = (int) ($sessionInfo['login_at'] ?? 0);
$expiresAt = (int) ($sessionInfo['expires_at'] ?? 0);
$ipAddress = trim((string) ($sessionInfo['ip_address'] ?? ''));
$userAgent = trim((string) ($sessionInfo['user_agent'] ?? ''));
$timezone = new DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai');

$formatTime = static function (int $timestamp) use ($timezone): string {
    if ($timestamp <= 0) {
        return '未记录';
    }

    return (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone)->format('Y.m.d H:i');
};

$sessionRows = [
    ['当前账号', $adminName !== '' ? $adminName : '管理员'],
    ['登录时间', $formatTime($loginAt)],
    ['有效至', $formatTime($expiresAt)],
    ['登录 IP', $ipAddress !== '' ? $ipAddress : '未记录'],
];
?>
<!DOCTYPE html>
<html lang="zh-CN" data-admin-theme="light" data-admin-theme-source="system">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>后台设置 - Z-Blog</title>
    <link rel="icon" href="/assets/img/ZMoon.png" type="image/png">
    <link rel="stylesheet" href="/assets/css/admin/index.css?v=<?= time() ?>">
</head>
<body class="admin-dashboard-page admin-backend-settings-page">
    <div class="admin-layout">
        <?php
        $active = 'backend_settings';
        require __DIR__ . '/partials/sidebar.php';
        ?>

        <main class="admin-main admin-backend-settings-main">
            <div class="admin-toast-container" data-admin-toast-container aria-live="polite" aria-atomic="true">
                <?php if (is_array($flash)): ?>
                    <div
                        class="admin-toast-seed"
                        data-admin-toast
                        data-admin-toast-type="<?= htmlspecialchars(((string) ($flash['type'] ?? 'success')) === 'error' ? 'error' : 'success') ?>"
                        data-admin-toast-message="<?= htmlspecialchars((string) ($flash['message'] ?? '')) ?>"
                        hidden
                    ></div>
                <?php endif; ?>
            </div>

            <div class="admin-section-actions admin-backend-settings-head">
                <div>
                    <h2 class="admin-section-title">后台设置</h2>
                    <p class="admin-section-desc">管理后台会话与安全操作。</p>
                </div>
            </div>

            <div class="admin-backend-settings-grid">
                <section class="admin-backend-settings-card admin-backend-session-card" aria-labelledby="backend-session-title">
                    <div class="admin-backend-card-head">
                        <div>
                            <h3 id="backend-session-title">账户会话</h3>
                            <p>当前后台登录状态。</p>
                        </div>
                        <span class="admin-backend-session-dot" aria-hidden="true"></span>
                    </div>

                    <div class="admin-backend-session-list">
                        <?php foreach ($sessionRows as $row): ?>
                            <div class="admin-backend-session-row">
                                <span><?= htmlspecialchars($row[0]) ?></span>
                                <strong><?= htmlspecialchars($row[1]) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($userAgent !== ''): ?>
                        <div class="admin-backend-user-agent">
                            <span>浏览器</span>
                            <p><?= htmlspecialchars($userAgent) ?></p>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="admin-backend-settings-card admin-backend-logout-card" aria-labelledby="backend-logout-title">
                    <div class="admin-backend-card-head">
                        <div>
                            <h3 id="backend-logout-title">退出登录</h3>
                            <p>结束当前会话并返回登录页。</p>
                        </div>
                    </div>

                    <form class="admin-backend-logout-form" method="post" action="/admin/logout">
                        <?= \App\Core\Security\Csrf::field() ?>
                        <button class="admin-btn admin-btn-danger" type="submit">退出登录</button>
                    </form>
                </section>
            </div>
        </main>
    </div>
    <script src="/assets/js/admin/modules/theme.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/modules/sidebar.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/modules/modal.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/modules/editor.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/modules/forms.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/modules/upload-preview.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/index.js?v=<?= time() ?>"></script>
</body>
</html>
