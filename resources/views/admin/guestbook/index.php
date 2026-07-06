<?php
$admin = $admin ?? null;
$messages = $messages ?? [];
$stats = $stats ?? ['total' => 0, 'replied' => 0, 'unreplied' => 0, 'hidden' => 0, 'deleted' => 0];
$flash = $flash ?? null;

$statusLabel = static function (int $status): string {
    return match ($status) {
        0 => '待审核',
        1 => '已显示',
        2 => '已隐藏',
        default => '未知状态',
    };
};

$statusClass = static function (int $status): string {
    return match ($status) {
        0 => 'warning',
        1 => 'success',
        2 => 'muted',
        default => 'muted',
    };
};

$formatDate = static function (?string $date): string {
    $timestamp = strtotime((string) $date);
    return $timestamp ? date('Y.m.d H:i', $timestamp) : '-';
};

$messageSummary = static function (array $message): string {
    $content = trim(strip_tags((string) ($message['content'] ?? '')));
    return $content !== '' ? mb_substr($content, 0, 72) : '暂无内容';
};
?>
<!DOCTYPE html>
<html lang="zh-CN" data-admin-theme="light" data-admin-theme-source="system">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>留言板管理 - Z-Blog</title>
    <link rel="icon" href="/assets/img/ZMoon.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/admin/index.css?v=<?= time() ?>">
</head>
<body class="admin-dashboard-page admin-guestbook-index-page">
    <div class="admin-layout">
        <?php
        $active = 'guestbook';
        require dirname(__DIR__) . '/partials/sidebar.php';
        ?>

        <main class="admin-main admin-list-main">
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

            <div class="admin-section-actions">
                <div>
                    <h2 class="admin-section-title">全部留言</h2>
                    <p class="admin-section-desc">先看整体情况，再通过详情弹窗处理回复、显示和隐藏。</p>
                </div>
            </div>

            <details class="admin-guestbook-stat-collapse" open>
                <summary>
                    <span>统计数据</span>
                    <span class="admin-guestbook-stat-toggle-text" aria-hidden="true"></span>
                </summary>
                <section class="admin-stat-grid admin-guestbook-stat-grid">
                    <article class="admin-stat-card">
                        <div class="admin-stat-header"><i class="fa-solid fa-comments"></i><span>全部留言</span></div>
                        <strong class="admin-big-number"><?= (int) ($stats['total'] ?? 0) ?></strong>
                    </article>
                    <article class="admin-stat-card">
                        <div class="admin-stat-header"><i class="fa-solid fa-reply"></i><span>已回复留言</span></div>
                        <strong class="admin-big-number"><?= (int) ($stats['replied'] ?? 0) ?></strong>
                    </article>
                    <article class="admin-stat-card">
                        <div class="admin-stat-header"><i class="fa-regular fa-clock"></i><span>未回复留言</span></div>
                        <strong class="admin-big-number"><?= (int) ($stats['unreplied'] ?? 0) ?></strong>
                    </article>
                    <article class="admin-stat-card">
                        <div class="admin-stat-header"><i class="fa-solid fa-eye-slash"></i><span>已隐藏留言</span></div>
                        <strong class="admin-big-number"><?= (int) ($stats['hidden'] ?? 0) ?></strong>
                    </article>
                    <article class="admin-stat-card">
                        <div class="admin-stat-header"><i class="fa-regular fa-trash-can"></i><span>已删除留言</span></div>
                        <strong class="admin-big-number"><?= (int) ($stats['deleted'] ?? 0) ?></strong>
                    </article>
                </section>
            </details>

            <section class="admin-table-card admin-guestbook-list-card">
                <?php if (empty($messages)): ?>
                    <div class="admin-empty">
                        <div class="admin-empty-title">暂无留言</div>
                        <p class="admin-empty-desc">前台有新留言后，这里会按时间显示出来。</p>
                    </div>
                <?php else: ?>
                    <div class="admin-table-wrap admin-guestbook-table-wrap">
                        <table class="admin-table admin-guestbook-table">
                            <thead>
                                <tr>
                                    <th>称呼</th>
                                    <th>留言状态</th>
                                    <th>回复状态</th>
                                    <th>提交时间</th>
                                    <th style="text-align: right;">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($messages as $message): ?>
                                    <?php
                                    $messageId = (int) ($message['id'] ?? 0);
                                    $status = (int) ($message['status'] ?? 0);
                                    $isDeleted = (int) ($message['is_deleted'] ?? 0) === 1;
                                    $adminReply = trim((string) ($message['admin_reply'] ?? ''));
                                    $modalId = 'guestbook-modal-' . $messageId;
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="admin-table-title"><?= htmlspecialchars((string) ($message['nickname'] ?? '访客')) ?></div>
                                        </td>
                                        <td>
                                            <?php if ($isDeleted): ?>
                                                <span class="admin-status-pill danger">已删除</span>
                                            <?php else: ?>
                                                <span class="admin-status-pill <?= htmlspecialchars($statusClass($status)) ?>"><?= htmlspecialchars($statusLabel($status)) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($adminReply !== ''): ?>
                                                <span class="admin-badge admin-badge-success">已回复</span>
                                            <?php else: ?>
                                                <span class="admin-badge admin-badge-muted">未回复</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($formatDate($message['created_at'] ?? null)) ?></td>
                                        <td>
                                            <div class="admin-row-actions">
                                                <button
                                                    class="admin-btn admin-btn-secondary admin-btn-sm"
                                                    type="button"
                                                    data-admin-modal-open="<?= htmlspecialchars($modalId) ?>"
                                                >详情</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="admin-guestbook-mobile-list" aria-label="手机端留言列表">
                        <?php foreach ($messages as $message): ?>
                            <?php
                            $messageId = (int) ($message['id'] ?? 0);
                            $status = (int) ($message['status'] ?? 0);
                            $isDeleted = (int) ($message['is_deleted'] ?? 0) === 1;
                            $adminReply = trim((string) ($message['admin_reply'] ?? ''));
                            $modalId = 'guestbook-modal-' . $messageId;
                            ?>
                            <article class="admin-guestbook-mobile-card">
                                <div class="admin-guestbook-mobile-main">
                                    <div class="admin-guestbook-mobile-title-row">
                                        <div class="admin-guestbook-mobile-title"><?= htmlspecialchars((string) ($message['nickname'] ?? '访客')) ?></div>
                                        <?php if ($isDeleted): ?>
                                            <span class="admin-status-pill danger">已删除</span>
                                        <?php else: ?>
                                            <span class="admin-status-pill <?= htmlspecialchars($statusClass($status)) ?>"><?= htmlspecialchars($statusLabel($status)) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <p><?= htmlspecialchars($messageSummary($message)) ?></p>
                                </div>
                                <div class="admin-guestbook-mobile-meta">
                                    <?php if ($adminReply !== ''): ?>
                                        <span class="admin-badge admin-badge-success">已回复</span>
                                    <?php else: ?>
                                        <span class="admin-badge admin-badge-muted">未回复</span>
                                    <?php endif; ?>
                                    <span><?= htmlspecialchars($formatDate($message['created_at'] ?? null)) ?></span>
                                </div>
                                <div class="admin-guestbook-mobile-actions">
                                    <button
                                        class="admin-btn admin-btn-secondary admin-btn-sm"
                                        type="button"
                                        data-admin-modal-open="<?= htmlspecialchars($modalId) ?>"
                                    >详情</button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php require dirname(__DIR__) . '/partials/pagination.php'; ?>
                <?php endif; ?>
            </section>

            <?php foreach ($messages as $message): ?>
                <?php
                $messageId = (int) ($message['id'] ?? 0);
                $status = (int) ($message['status'] ?? 0);
                $isDeleted = (int) ($message['is_deleted'] ?? 0) === 1;
                $adminReply = trim((string) ($message['admin_reply'] ?? ''));
                $replyTextareaId = 'guestbook-reply-' . $messageId;
                $modalId = 'guestbook-modal-' . $messageId;
                ?>
                <div class="admin-modal" id="<?= htmlspecialchars($modalId) ?>" data-admin-modal aria-hidden="true">
                    <div class="admin-modal-backdrop" data-admin-modal-close></div>
                    <section class="admin-modal-panel admin-detail-modal admin-guestbook-detail-modal" role="dialog" aria-modal="true" aria-labelledby="<?= htmlspecialchars($modalId) ?>-title">
                        <div class="admin-modal-head admin-guestbook-detail-head">
                            <div class="admin-guestbook-detail-title">
                                <h2 class="admin-section-title" id="<?= htmlspecialchars($modalId) ?>-title"><?= htmlspecialchars((string) ($message['nickname'] ?? '访客')) ?></h2>
                                <p class="admin-section-desc">提交于 <?= htmlspecialchars($formatDate($message['created_at'] ?? null)) ?></p>
                                <div class="admin-guestbook-status-row">
                                    <?php if ($isDeleted): ?>
                                        <span class="admin-status-pill danger">已删除</span>
                                    <?php else: ?>
                                        <span class="admin-status-pill <?= htmlspecialchars($statusClass($status)) ?>"><?= htmlspecialchars($statusLabel($status)) ?></span>
                                    <?php endif; ?>
                                    <?php if ($adminReply !== ''): ?>
                                        <span class="admin-status-pill success">已回复</span>
                                    <?php else: ?>
                                        <span class="admin-status-pill muted">未回复</span>
                                    <?php endif; ?>
                                </div>
                                <p class="admin-guestbook-scroll-hint">上下滑动可查看更多</p>
                            </div>
                        </div>

                        <div class="admin-guestbook-detail-body">
                            <section class="admin-guestbook-detail-section">
                                <h3 class="admin-guestbook-section-title">留言内容</h3>
                                <div class="admin-detail-content admin-guestbook-message-content"><?= nl2br(htmlspecialchars((string) ($message['content'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
                            </section>

                            <form class="admin-reply-form admin-guestbook-reply-form" method="post" action="/admin/guestbook/<?= $messageId ?>/reply">
                                <label class="admin-reply-label" for="<?= htmlspecialchars($replyTextareaId) ?>">管理员回复</label>
                                <textarea
                                    id="<?= htmlspecialchars($replyTextareaId) ?>"
                                    name="admin_reply"
                                    maxlength="1000"
                                    placeholder="输入回复内容，留空保存可清空当前回复"
                                    data-reply-textarea
                                ><?= htmlspecialchars($adminReply) ?></textarea>
                                <div class="admin-reply-meta">
                                    <span class="admin-reply-counter" data-reply-counter-for="<?= htmlspecialchars($replyTextareaId) ?>"><?= mb_strlen($adminReply) ?>/1000</span>
                                </div>
                                <div class="admin-reply-actions">
                                    <button class="admin-btn admin-btn-secondary" type="submit">保存回复</button>
                                </div>
                            </form>
                        </div>

                        <div class="admin-form-actions admin-detail-actions admin-guestbook-actions">
                            <?php if ($isDeleted): ?>
                                <form method="post" action="/admin/guestbook/<?= $messageId ?>/restore">
                                    <button class="admin-btn" type="submit">恢复留言</button>
                                </form>
                            <?php endif; ?>

                            <?php if (!$isDeleted && $status !== 1): ?>
                                <form method="post" action="/admin/guestbook/<?= $messageId ?>/approve">
                                    <button class="admin-btn" type="submit">显示留言</button>
                                </form>
                            <?php endif; ?>

                            <?php if (!$isDeleted && $status !== 2): ?>
                                <button class="admin-btn admin-btn-secondary" type="button" data-admin-modal-close>取消</button>
                                <form method="post" action="/admin/guestbook/<?= $messageId ?>/hide" data-guestbook-confirm-form data-guestbook-confirm-name="隐藏留言" data-guestbook-confirm-desc="隐藏后，该留言将不在前台显示，可随时恢复。" data-guestbook-confirm-variant="warning">
                                    <button class="admin-btn admin-btn-warning" type="submit">隐藏留言</button>
                                </form>
                            <?php endif; ?>

                            <?php if (!$isDeleted): ?>
                                <form method="post" action="/admin/guestbook/<?= $messageId ?>/delete" data-guestbook-confirm-form data-guestbook-confirm-name="删除留言" data-guestbook-confirm-desc="删除后该留言将被移除，无法恢复。" data-guestbook-confirm-variant="danger">
                                    <button class="admin-btn admin-btn-danger" type="submit">删除留言</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            <?php endforeach; ?>
        </main>
    </div>

    <div class="admin-modal admin-guestbook-confirm-modal" id="guestbook-confirm-modal" data-admin-modal data-guestbook-confirm-modal aria-hidden="true">
        <div class="admin-modal-backdrop" data-admin-modal-close></div>
        <section class="admin-modal-panel admin-guestbook-confirm-panel" role="dialog" aria-modal="true" aria-labelledby="guestbook-confirm-title">
            <div class="admin-modal-head">
                <div>
                    <h2 class="admin-section-title" id="guestbook-confirm-title" data-guestbook-confirm-title>确认操作</h2>
                    <p class="admin-section-desc" data-guestbook-confirm-desc>请确认是否继续。</p>
                </div>
            </div>
            <div class="admin-guestbook-confirm-body">
                <p data-guestbook-confirm-question>确定要执行此操作吗？</p>
            </div>
            <div class="admin-form-actions admin-guestbook-confirm-actions">
                <button class="admin-btn admin-btn-secondary" type="button" data-admin-modal-close>取消</button>
                <button class="admin-btn admin-btn-danger" type="button" data-guestbook-confirm-confirm>确认</button>
            </div>
        </section>
    </div>

    <script src="/assets/js/admin/index.js?v=<?= time() ?>"></script>
</body>
</html>

