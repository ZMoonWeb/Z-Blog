<?php
$admin = $admin ?? null;
$announcements = $announcements ?? [];
$flash = $flash ?? null;

$levelLabels = [
    'normal' => '普通',
    'important' => '重要',
    'urgent' => '紧急',
    'archived' => '归档',
];

$modeLabels = [
    'text' => '文本',
    'markdown' => 'Markdown',
    'html' => 'HTML',
];

$announcementSummary = static function (array $announcement): string {
    $content = trim(strip_tags((string) ($announcement['content'] ?? '')));
    return $content !== '' ? mb_substr($content, 0, 82) : '暂无内容';
};
?>
<!DOCTYPE html>
<html lang="zh-CN" data-admin-theme="light" data-admin-theme-source="system">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>公告管理 - Z-Blog</title>
    <link rel="icon" href="/assets/img/ZMoon.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/admin/index.css?v=<?= time() ?>">
</head>
<body class="admin-dashboard-page admin-announcements-index-page">
    <div class="admin-layout">
        <?php
        $active = 'announcements';
        require dirname(__DIR__) . '/partials/sidebar.php';
        ?>

        <main class="admin-main admin-list-main">

            <?php if (is_array($flash)): ?>
                <div class="admin-flash admin-flash-<?= htmlspecialchars((string) ($flash['type'] ?? 'success')) ?>">
                    <?= htmlspecialchars((string) ($flash['message'] ?? '')) ?>
                </div>
            <?php endif; ?>

            <div class="admin-section-actions admin-announcements-head">
                <div class="admin-announcements-head-copy">
                    <h2 class="admin-section-title">全部公告</h2>
                    <p class="admin-section-desc">发布、编辑和维护站点公告内容。</p>
                </div>
                <a class="admin-btn admin-announcements-create-btn" href="/admin/announcements/create">新建公告</a>
            </div>

            <section class="admin-table-card admin-announcements-list-card">
                <?php if (empty($announcements)): ?>
                    <div class="admin-empty">
                        <div class="admin-empty-title">暂无公告</div>
                        <p class="admin-empty-desc">创建第一条公告后会展示在前台公告页。</p>
                        <a class="admin-btn" href="/admin/announcements/create">新建公告</a>
                    </div>
                <?php else: ?>
                    <div class="admin-table-wrap admin-announcements-table-wrap">
                        <table class="admin-table admin-announcements-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>级别</th>
                                    <th>格式</th>
                                    <th>状态</th>
                                    <th>更新时间</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($announcements as $announcement): ?>
                                    <?php
                                    $level = (string) ($announcement['level'] ?? 'normal');
                                    $mode = (string) ($announcement['content_mode'] ?? 'text');
                                    if (!array_key_exists($mode, $modeLabels)) {
                                        $mode = 'text';
                                    }
                                    ?>
                                    <tr>
                                        <td>#<?= (int) $announcement['id'] ?></td>
                                        <td><span class="admin-badge admin-badge-muted"><?= htmlspecialchars($levelLabels[$level] ?? $levelLabels['normal']) ?></span></td>
                                        <td><?= htmlspecialchars($modeLabels[$mode]) ?></td>
                                        <td>
                                            <?php if ((int) $announcement['is_active'] === 1): ?>
                                                <span class="admin-badge admin-badge-success">显示中</span>
                                            <?php else: ?>
                                                <span class="admin-badge admin-badge-muted">已隐藏</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars((string) $announcement['updated_at']) ?></td>
                                        <td>
                                            <div class="admin-row-actions">
                                                <a class="admin-btn admin-btn-secondary admin-btn-sm" href="/admin/announcements/<?= (int) $announcement['id'] ?>/edit">编辑</a>
                                                <form class="admin-inline-form" method="post" action="/admin/announcements/<?= (int) $announcement['id'] ?>/delete" data-announcement-delete-form data-announcement-delete-name="公告 #<?= (int) $announcement['id'] ?>">
                                                    <?= \App\Core\Security\Csrf::field() ?>
                                                    <button class="admin-btn admin-btn-danger admin-btn-sm" type="submit">删除</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="admin-announcement-mobile-list" aria-label="手机端公告列表">
                        <?php foreach ($announcements as $announcement): ?>
                            <?php
                            $announcementId = (int) ($announcement['id'] ?? 0);
                            $level = (string) ($announcement['level'] ?? 'normal');
                            $mode = (string) ($announcement['content_mode'] ?? 'text');
                            if (!array_key_exists($mode, $modeLabels)) {
                                $mode = 'text';
                            }
                            $updatedAt = trim((string) ($announcement['updated_at'] ?? ''));
                            ?>
                            <article class="admin-announcement-mobile-card">
                                <div class="admin-announcement-mobile-main">
                                    <div class="admin-announcement-mobile-title-row">
                                        <div class="admin-announcement-mobile-title">公告 #<?= $announcementId ?></div>
                                        <?php if ((int) ($announcement['is_active'] ?? 0) === 1): ?>
                                            <span class="admin-badge admin-badge-success">显示中</span>
                                        <?php else: ?>
                                            <span class="admin-badge admin-badge-muted">已隐藏</span>
                                        <?php endif; ?>
                                    </div>
                                    <p><?= htmlspecialchars($announcementSummary($announcement)) ?></p>
                                </div>
                                <div class="admin-announcement-mobile-meta">
                                    <span class="admin-badge admin-badge-muted"><?= htmlspecialchars($levelLabels[$level] ?? $levelLabels['normal']) ?></span>
                                    <span><?= htmlspecialchars($modeLabels[$mode] ?? $mode) ?></span>
                                    <span><?= htmlspecialchars($updatedAt !== '' ? $updatedAt : '-') ?></span>
                                </div>
                                <div class="admin-announcement-mobile-actions">
                                    <a class="admin-btn admin-btn-secondary admin-btn-sm" href="/admin/announcements/<?= $announcementId ?>/edit">编辑</a>
                                    <form class="admin-inline-form" method="post" action="/admin/announcements/<?= $announcementId ?>/delete" data-announcement-delete-form data-announcement-delete-name="公告 #<?= $announcementId ?>">
                                        <?= \App\Core\Security\Csrf::field() ?>
                                        <button class="admin-btn admin-btn-danger admin-btn-sm" type="submit">删除</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php require dirname(__DIR__) . '/partials/pagination.php'; ?>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <div class="admin-modal admin-announcement-delete-modal" id="announcement-delete-modal" data-admin-modal data-announcement-delete-modal aria-hidden="true">
        <div class="admin-modal-backdrop" data-admin-modal-close></div>
        <section class="admin-modal-panel admin-announcement-delete-panel" role="dialog" aria-modal="true" aria-labelledby="announcement-delete-title">
            <div class="admin-modal-head">
                <div>
                    <h2 class="admin-section-title" id="announcement-delete-title">删除公告</h2>
                    <p class="admin-section-desc" data-announcement-delete-desc>删除后，该公告将从前台公告页移除，无法恢复。</p>
                </div>
            </div>
            <div class="admin-announcement-delete-body">
                <p>确定要删除这条公告吗？</p>
            </div>
            <div class="admin-form-actions admin-announcement-delete-actions">
                <button class="admin-btn admin-btn-secondary" type="button" data-admin-modal-close>取消</button>
                <button class="admin-btn admin-btn-danger" type="button" data-announcement-delete-confirm>确认删除</button>
            </div>
        </section>
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
