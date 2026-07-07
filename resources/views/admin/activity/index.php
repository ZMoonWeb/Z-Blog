<?php
$admin = $admin ?? null;
$activities = is_array($activities ?? null) ? $activities : [];

$formatDate = static function (?string $date): string {
    $timestamp = strtotime((string) $date);
    return $timestamp ? date('Y.m.d H:i', $timestamp) : '-';
};

$actionText = static function (string $action): string {
    return match ($action) {
        'login_success' => '登录成功',
        'login_failed' => '登录失败',
        'login_ip_locked' => 'IP 锁定',
        'login_blocked' => '锁定拒绝',
        'logout' => '退出登录',
        'profile_update' => '更新个人资料',
        'profile_update_failed' => '更新个人资料失败',
        'settings_update' => '更新前台设置',
        'settings_update_failed' => '更新前台设置失败',
        'update_check' => '检查更新',
        'update_check_failed' => '检查更新失败',
        'post_create' => '创建文章',
        'post_create_failed' => '创建文章失败',
        'post_update' => '编辑文章',
        'post_update_failed' => '编辑文章失败',
        'post_delete' => '删除文章',
        'post_delete_failed' => '删除文章失败',
        'category_create' => '创建分类',
        'category_create_failed' => '创建分类失败',
        'category_update' => '编辑分类',
        'category_update_failed' => '编辑分类失败',
        'category_delete' => '删除分类',
        'category_delete_failed' => '删除分类失败',
        'announcement_create' => '创建公告',
        'announcement_create_failed' => '创建公告失败',
        'announcement_update' => '编辑公告',
        'announcement_update_failed' => '编辑公告失败',
        'announcement_delete' => '删除公告',
        'announcement_delete_failed' => '删除公告失败',
        'guestbook_approve' => '通过留言',
        'guestbook_hide' => '隐藏留言',
        'guestbook_delete' => '删除留言',
        'guestbook_delete_failed' => '删除留言失败',
        'guestbook_restore' => '恢复留言',
        'guestbook_reply_update' => '更新留言回复',
        'guestbook_reply_update_failed' => '更新留言回复失败',
        'guestbook_restore_failed' => '恢复留言失败',
        'guestbook_action_failed' => '留言操作失败',
        'guestbook_action_unknown' => '未知留言操作',
        default => '后台活动',
    };
};

$statusClass = static function (string $status): string {
    return match ($status) {
        'success' => 'success',
        'warning' => 'warning',
        'danger', 'error' => 'danger',
        default => 'muted',
    };
};

$statusText = static function (string $status): string {
    return match ($status) {
        'success' => '成功',
        'warning' => '警告',
        'danger', 'error' => '危险',
        default => '记录',
    };
};

$decodeMetadata = static function (mixed $metadata): array {
    $metadata = trim((string) $metadata);
    if ($metadata === '') {
        return [];
    }

    $decoded = json_decode($metadata, true);
    return is_array($decoded) ? $decoded : [];
};
$renderChanges = static function (array $changes): void {
    if (empty($changes)) {
        return;
    }
    ?>
    <div class="admin-audit-change-list">
        <?php foreach ($changes as $field => $change): ?>
            <?php
            $label = (string) ($change['label'] ?? $field);
            $old = (string) ($change['old'] ?? '');
            $new = (string) ($change['new'] ?? '');
            ?>
            <div class="admin-audit-change-item">
                <div class="admin-audit-change-label"><?= htmlspecialchars($label) ?></div>
                <div class="admin-audit-change-values">
                    <div>
                        <span>修改前</span>
                        <code><?= htmlspecialchars($old !== '' ? $old : '空') ?></code>
                    </div>
                    <div>
                        <span>修改后</span>
                        <code><?= htmlspecialchars($new !== '' ? $new : '空') ?></code>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
};

$renderSnapshot = static function (array $snapshot): void {
    if (empty($snapshot)) {
        return;
    }
    ?>
    <div class="admin-audit-snapshot-list">
        <?php foreach ($snapshot as $field => $item): ?>
            <?php
            $label = (string) ($item['label'] ?? $field);
            $value = (string) ($item['value'] ?? '');
            ?>
            <div class="admin-audit-snapshot-item">
                <span><?= htmlspecialchars($label) ?></span>
                <code><?= htmlspecialchars($value !== '' ? $value : '空') ?></code>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
};

$renderErrors = static function (array $errors): void {
    if (empty($errors)) {
        return;
    }
    ?>
    <div class="admin-audit-error-list">
        <?php foreach ($errors as $field => $error): ?>
            <div class="admin-audit-error-item">
                <span><?= htmlspecialchars((string) $field) ?></span>
                <code><?= htmlspecialchars((string) $error) ?></code>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
};
?>
<!DOCTYPE html>
<html lang="zh-CN" data-admin-theme="light" data-admin-theme-source="system">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>后台活动记录 - Z-Blog</title>
    <link rel="icon" href="/assets/img/ZMoon.png" type="image/png">
    <link rel="stylesheet" href="/assets/css/admin/index.css?v=<?= time() ?>">
</head>
<body class="admin-dashboard-page admin-activity-index-page">
    <div class="admin-layout">
        <?php
        $active = 'activity';
        require dirname(__DIR__) . '/partials/sidebar.php';
        ?>

        <main class="admin-main admin-list-main">
            <div class="admin-section-actions">
                <div>
                    <h2 class="admin-section-title">后台活动记录</h2>
                    <p class="admin-section-desc">记录管理员登录、文章、分类、公告、前台设置、留言和个人资料等后台操作。</p>
                </div>
            </div>

            <section class="admin-table-card admin-activity-card">
                <div class="admin-interaction-card-head">
                    <div>
                        <h3 class="admin-section-title">操作审计</h3>
                        <p class="admin-section-desc">共 <?= (int) ($pagination['total'] ?? count($activities)) ?> 条记录</p>
                    </div>
                </div>

                <?php if (empty($activities)): ?>
                    <div class="admin-empty">
                        <div class="admin-empty-title">暂无后台活动记录</div>
                        <p class="admin-empty-desc">管理员登录与后台数据修改会在这里显示。</p>
                    </div>
                <?php else: ?>
                    <div class="admin-table-wrap admin-activity-table-wrap">
                        <table class="admin-table admin-activity-table">
                            <thead>
                                <tr>
                                    <th>时间</th>
                                    <th>账号</th>
                                    <th>动作</th>
                                    <th>状态</th>
                                    <th>IP</th>
                                    <th>说明</th>
                                    <th>详情</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activities as $activity): ?>
                                    <?php
                                    $modalId = 'admin-activity-detail-' . (int) ($activity['id'] ?? 0);
                                    $status = (string) ($activity['status'] ?? 'info');
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($formatDate($activity['created_at'] ?? null)) ?></td>
                                        <td>
                                            <div class="admin-table-title"><?= htmlspecialchars((string) ($activity['username'] ?? '未知账号')) ?></div>
                                            <code class="admin-data-code">ID <?= htmlspecialchars((string) ($activity['admin_id'] ?? '-')) ?></code>
                                        </td>
                                        <td><?= htmlspecialchars($actionText((string) ($activity['action'] ?? ''))) ?></td>
                                        <td><span class="admin-status-pill <?= htmlspecialchars($statusClass($status)) ?>"><?= htmlspecialchars($statusText($status)) ?></span></td>
                                        <td><code class="admin-data-code"><?= htmlspecialchars((string) ($activity['ip_address'] ?? '-')) ?></code></td>
                                        <td><?= htmlspecialchars((string) ($activity['message'] ?? '-')) ?></td>
                                        <td><button type="button" class="admin-interaction-detail-button" data-admin-modal-open="<?= htmlspecialchars($modalId) ?>">详情</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="admin-interaction-mobile-list" aria-label="手机端后台活动记录">
                        <?php foreach ($activities as $activity): ?>
                            <?php
                            $modalId = 'admin-activity-detail-mobile-' . (int) ($activity['id'] ?? 0);
                            $status = (string) ($activity['status'] ?? 'info');
                            ?>
                            <article class="admin-interaction-mobile-card">
                                <div class="admin-interaction-mobile-main">
                                    <div class="admin-interaction-mobile-title-row">
                                        <div class="admin-interaction-mobile-title"><?= htmlspecialchars($actionText((string) ($activity['action'] ?? ''))) ?></div>
                                        <span class="admin-status-pill <?= htmlspecialchars($statusClass($status)) ?>"><?= htmlspecialchars($statusText($status)) ?></span>
                                    </div>
                                    <div class="admin-interaction-mobile-target"><?= htmlspecialchars((string) ($activity['message'] ?? '-')) ?></div>
                                </div>
                                <div class="admin-interaction-mobile-meta">
                                    <span><?= htmlspecialchars($formatDate($activity['created_at'] ?? null)) ?></span>
                                    <span>IP <?= htmlspecialchars((string) ($activity['ip_address'] ?? '-')) ?></span>
                                </div>
                                <div class="admin-interaction-mobile-actions">
                                    <button type="button" class="admin-interaction-detail-button" data-admin-modal-open="<?= htmlspecialchars($modalId) ?>">详情</button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php require dirname(__DIR__) . '/partials/pagination.php'; ?>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <?php foreach ($activities as $activity): ?>
        <?php
        $metadata = $decodeMetadata($activity['metadata'] ?? '');
        foreach ([
            'admin-activity-detail-' . (int) ($activity['id'] ?? 0),
            'admin-activity-detail-mobile-' . (int) ($activity['id'] ?? 0),
        ] as $modalId):
        ?>
        <div class="admin-modal" id="<?= htmlspecialchars($modalId) ?>" data-admin-modal aria-hidden="true">
            <div class="admin-modal-backdrop" data-admin-modal-close></div>
            <div class="admin-modal-panel admin-detail-modal admin-interaction-detail-modal" role="dialog" aria-modal="true" aria-labelledby="<?= htmlspecialchars($modalId) ?>-title">
                <div class="admin-modal-head">
                    <div>
                        <h3 class="admin-section-title" id="<?= htmlspecialchars($modalId) ?>-title">活动详情</h3>
                        <p class="admin-section-desc"><?= htmlspecialchars($formatDate($activity['created_at'] ?? null)) ?></p>
                    </div>
                    <button type="button" class="admin-modal-close" data-admin-modal-close aria-label="关闭">×</button>
                </div>

                <div class="admin-interaction-detail-panel">
                    <div class="admin-interaction-detail-row">
                        <span class="admin-detail-label">动作</span>
                        <strong><?= htmlspecialchars($actionText((string) ($activity['action'] ?? ''))) ?></strong>
                    </div>
                    <div class="admin-interaction-detail-row">
                        <span class="admin-detail-label">账号</span>
                        <strong><?= htmlspecialchars((string) ($activity['username'] ?? '未知账号')) ?></strong>
                    </div>
                    <div class="admin-interaction-detail-row">
                        <span class="admin-detail-label">IP</span>
                        <code class="admin-data-code"><?= htmlspecialchars((string) ($activity['ip_address'] ?? '-')) ?></code>
                    </div>
                    <div class="admin-interaction-detail-row admin-interaction-detail-row-stack">
                        <span class="admin-detail-label">说明</span>
                        <p class="admin-interaction-detail-text"><?= nl2br(htmlspecialchars((string) ($activity['message'] ?? '-'))) ?></p>
                    </div>
                    <?php if (!empty($metadata['changes']) && is_array($metadata['changes'])): ?>
                        <div class="admin-interaction-detail-row admin-interaction-detail-row-stack">
                            <span class="admin-detail-label">变更详情</span>
                            <?php $renderChanges($metadata['changes']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($metadata['snapshot']) && is_array($metadata['snapshot'])): ?>
                        <div class="admin-interaction-detail-row admin-interaction-detail-row-stack">
                            <span class="admin-detail-label">记录快照</span>
                            <?php $renderSnapshot($metadata['snapshot']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($metadata['errors']) && is_array($metadata['errors'])): ?>
                        <div class="admin-interaction-detail-row admin-interaction-detail-row-stack">
                            <span class="admin-detail-label">错误详情</span>
                            <?php $renderErrors($metadata['errors']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($metadata)): ?>
                        <div class="admin-interaction-detail-row admin-interaction-detail-row-stack">
                            <span class="admin-detail-label">原始数据</span>
                            <code class="admin-data-code"><?= htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></code>
                        </div>
                    <?php endif; ?>
                    <div class="admin-interaction-detail-row admin-interaction-detail-row-stack">
                        <span class="admin-detail-label">浏览器</span>
                        <code class="admin-data-code"><?= htmlspecialchars((string) ($activity['user_agent'] ?? '-')) ?></code>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <script src="/assets/js/admin/modules/theme.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/modules/sidebar.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/modules/modal.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/modules/editor.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/modules/forms.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/modules/upload-preview.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/index.js?v=<?= time() ?>"></script>
</body>
</html>
