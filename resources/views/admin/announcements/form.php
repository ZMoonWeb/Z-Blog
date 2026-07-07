<?php
$admin = $admin ?? null;
$announcement = $announcement ?? [
    'level' => 'normal',
    'content' => '',
    'content_mode' => 'text',
    'is_active' => 1,
];
$errors = $errors ?? [];
$mode = $mode ?? 'create';

$isEdit = $mode === 'edit';
$action = $isEdit ? '/admin/announcements/' . (int) ($announcement['id'] ?? 0) . '/edit' : '/admin/announcements/create';
$contentMode = (string) ($announcement['content_mode'] ?? 'text');
if (!in_array($contentMode, ['text', 'markdown', 'html'], true)) {
    $contentMode = 'text';
}

$announcementLevels = [
    'normal' => '普通',
    'important' => '重要',
    'urgent' => '紧急',
    'archived' => '归档',
];
$announcementLevel = (string) ($announcement['level'] ?? 'normal');
if (!array_key_exists($announcementLevel, $announcementLevels)) {
    $announcementLevel = 'normal';
}
?>
<!DOCTYPE html>
<html lang="zh-CN" data-admin-theme="light" data-admin-theme-source="system">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title><?= $isEdit ? '编辑公告' : '新建公告' ?> - Z-Blog</title>
    <link rel="icon" href="/assets/img/ZMoon.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/admin/index.css?v=<?= time() ?>">
</head>
<body class="admin-dashboard-page admin-announcement-form-page">
    <div class="admin-layout">
        <?php
        $active = 'announcements';
        require dirname(__DIR__) . '/partials/sidebar.php';
        ?>

        <main class="admin-main">
<?php if (!empty($errors)): ?>
                <div class="admin-flash admin-flash-error">
                    <?= htmlspecialchars((string) reset($errors)) ?>
                </div>
            <?php endif; ?>

            <form class="admin-form-card admin-editor-form admin-announcement-form" method="post" action="<?= htmlspecialchars($action) ?>">
                <?= \App\Core\Security\Csrf::field() ?>
                <div class="admin-form-grid admin-editor-grid admin-announcement-grid">
                    <div class="admin-form-panel admin-announcement-content">
                        <h2 class="admin-section-title">公告内容</h2>
                        <p class="admin-section-desc">公告会展示在前台公告页，最新启用公告也会显示在首页侧栏。</p>

                        <div class="admin-form-group">
                            <label class="admin-form-label" for="level">公告级别</label>
                            <select class="admin-select" id="level" name="level">
                                <?php foreach ($announcementLevels as $levelValue => $levelLabel): ?>
                                    <option value="<?= htmlspecialchars($levelValue) ?>" <?= $announcementLevel === $levelValue ? 'selected' : '' ?>><?= htmlspecialchars($levelLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="admin-form-group">
                            <label class="admin-form-label" for="content_mode">内容格式</label>
                            <select class="admin-select" id="content_mode" name="content_mode">
                                <option value="text" <?= $contentMode === 'text' ? 'selected' : '' ?>>文本</option>
                                <option value="markdown" <?= $contentMode === 'markdown' ? 'selected' : '' ?>>Markdown</option>
                                <option value="html" <?= $contentMode === 'html' ? 'selected' : '' ?>>HTML</option>
                            </select>
                        </div>

                        <div class="admin-form-group">
                            <label class="admin-form-label" for="content">公告正文</label>
                            <textarea class="admin-textarea admin-editor-textarea" id="content" name="content"><?= htmlspecialchars((string) ($announcement['content'] ?? '')) ?></textarea>
                        </div>
                    </div>

                    <aside class="admin-form-panel admin-editor-side admin-announcement-side">
                        <section class="admin-announcement-side-box">
                            <div class="admin-announcement-status">
                                <h2 class="admin-section-title">发布状态</h2>
                                <label class="admin-check-row">
                                    <input type="checkbox" name="is_active" value="1" <?= (int) ($announcement['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                                    <span>在前台显示这条公告</span>
                                </label>
                            </div>

                            <div class="admin-form-actions admin-editor-actions admin-announcement-actions">
                                <a class="admin-btn admin-btn-secondary" href="/admin/announcements">取消</a>
                                <button class="admin-btn" type="submit"><?= $isEdit ? '保存公告' : '创建公告' ?></button>
                            </div>
                        </section>
                    </aside>
                </div>
            </form>
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

