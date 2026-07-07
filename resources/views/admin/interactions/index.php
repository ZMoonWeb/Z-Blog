<?php
$admin = $admin ?? null;
$events = is_array($events ?? null) ? $events : [];

$formatDate = static function (?string $date): string {
    $timestamp = strtotime((string) $date);
    return $timestamp ? date('Y.m.d H:i', $timestamp) : '-';
};

$actionText = static function (string $action): string {
    return match ($action) {
        'liked' => '点赞文章',
        'unliked' => '取消点赞',
        'commented' => '评论文章',
        'page_view' => '访问页面',
        'guestbook_view' => '访问留言板',
        'guestbook_open' => '打开写留言',
        'guestbook_post' => '发布留言',
        'guestbook_detail' => '查看留言',
        default => '访问文章',
    };
};

$actionClass = static function (string $action): string {
    return match ($action) {
        'liked', 'guestbook_post' => 'success',
        'unliked' => 'muted',
        'commented', 'guestbook_detail' => 'info',
        default => 'neutral',
    };
};

$pageMeta = static function (string $page): array {
    $page = trim($page);
    $page = $page !== '' && str_starts_with($page, 'page:') ? substr($page, 5) : $page;
    $page = trim($page);
    $normalized = trim($page, '/');

    return match ($normalized) {
        '', 'posts', 'home', 'index' => ['path' => '/', 'label' => '首页'],
        'hot' => ['path' => '/hot', 'label' => '热榜页面'],
        'notice' => ['path' => '/notice', 'label' => '公告页面'],
        'about' => ['path' => '/about', 'label' => '关于页面'],
        'me' => ['path' => '/me', 'label' => '个人主页'],
        default => [
            'path' => str_starts_with($page, '/') ? $page : '/' . $normalized,
            'label' => '页面',
        ],
    };
};

$guestbookTargetMeta = static function (string $action, string $sourceId): array {
    if ($action === 'guestbook_open') {
        return ['path' => '/guestbook/new', 'label' => '写留言页面'];
    }

    if ($action === 'guestbook_detail' && $sourceId !== '') {
        return ['path' => '/guestbook/' . rawurlencode($sourceId), 'label' => '留言详情页面'];
    }

    return ['path' => '/guestbook', 'label' => '留言板页面'];
};

$targetText = static function (array $row) use ($pageMeta, $guestbookTargetMeta): string {
    $title = trim((string) ($row['post_title'] ?? ''));
    if ($title !== '') {
        return $title;
    }

    $sourceType = trim((string) ($row['source_type'] ?? ''));
    $sourceId = trim((string) ($row['source_id'] ?? ''));
    $excerpt = trim((string) ($row['content_excerpt'] ?? ''));

    if ($sourceType === 'guestbook') {
        $action = (string) ($row['action'] ?? '');
        if ($sourceId !== '' && $action === 'guestbook_post') {
            return '留言 #' . $sourceId;
        }

        $meta = $guestbookTargetMeta($action, $sourceId);
        return trim($meta['path'] . ' ' . $meta['label']);
    }

    if ($sourceType === 'page') {
        $meta = $pageMeta($excerpt);
        return trim($meta['path'] . ' ' . $meta['label']);
    }

    return trim((string) ($row['post_id'] ?? '')) !== '' ? '文章已删除' : '-';
};

$targetUrl = static function (array $row) use ($pageMeta, $guestbookTargetMeta): string {
    $slug = trim((string) ($row['post_slug'] ?? ''));
    if ($slug !== '') {
        return '/post/' . rawurlencode($slug);
    }

    $sourceType = trim((string) ($row['source_type'] ?? ''));
    $sourceId = trim((string) ($row['source_id'] ?? ''));
    $excerpt = trim((string) ($row['content_excerpt'] ?? ''));

    if ($sourceType === 'guestbook') {
        if ((string) ($row['action'] ?? '') === 'guestbook_post' && $sourceId !== '') {
            return '/guestbook/' . rawurlencode($sourceId);
        }

        $meta = $guestbookTargetMeta((string) ($row['action'] ?? ''), $sourceId);
        return $meta['path'];
    }

    if ($sourceType === 'page') {
        $meta = $pageMeta($excerpt);
        return $meta['path'];
    }

    return '';
};

$excerptLabel = static function (string $action): string {
    return match ($action) {
        'commented' => '评论内容',
        'guestbook_post' => '留言内容',
        default => '动作摘要',
    };
};

$shortHash = static function (?string $hash): string {
    $hash = trim((string) $hash);
    return $hash !== '' ? substr($hash, 0, 12) : '';
};

$actorName = static function (array $row) use ($shortHash): string {
    $name = trim((string) ($row['actor_name'] ?? ''));
    if ($name !== '') {
        return $name;
    }

    $hash = $shortHash($row['visitor_hash'] ?? '');
    if ($hash !== '') {
        return '访客 ' . $hash;
    }

    $ip = trim((string) ($row['ip_address'] ?? ''));
    return $ip !== '' ? '访客 ' . $ip : '未知访客';
};

$sourceText = static function (array $row): string {
    $sourceType = trim((string) ($row['source_type'] ?? ''));
    $sourceId = trim((string) ($row['source_id'] ?? ''));

    if ($sourceType === '' && $sourceId === '') {
        return '-';
    }

    return $sourceId !== '' ? $sourceType . ' #' . $sourceId : $sourceType;
};
?>
<!DOCTYPE html>
<html lang="zh-CN" data-admin-theme="light" data-admin-theme-source="system">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>互动记录 - Z-Blog</title>
    <link rel="icon" href="/assets/img/ZMoon.png" type="image/png">
    <link rel="stylesheet" href="/assets/css/admin/index.css?v=<?= time() ?>">
</head>
<body class="admin-dashboard-page admin-interactions-index-page">
    <div class="admin-layout">
        <?php
        $active = 'interactions';
        require dirname(__DIR__) . '/partials/sidebar.php';
        ?>

        <main class="admin-main admin-list-main">
            <div class="admin-section-actions">
                <div>
                    <h2 class="admin-section-title">互动记录</h2>
                    <p class="admin-section-desc">按时间记录访客在前台的访问、留言、评论、点赞等行为。</p>
                </div>
            </div>

            <section class="admin-table-card admin-interaction-card">
                <div class="admin-interaction-card-head">
                    <div>
                        <h3 class="admin-section-title">动作日志</h3>
                        <p class="admin-section-desc">共 <?= (int) ($pagination['total'] ?? count($events)) ?> 条记录</p>
                    </div>
                </div>

                <?php if (empty($events)): ?>
                    <div class="admin-empty admin-interaction-empty">
                        <div class="admin-empty-title">暂无互动记录</div>
                        <p class="admin-empty-desc">有访客访问页面、发布留言、点赞或评论后，这里会显示动作明细。</p>
                    </div>
                <?php else: ?>
                    <div class="admin-interaction-table-wrap">
                        <table class="admin-table admin-interaction-table">
                            <thead>
                                <tr>
                                    <th>时间</th>
                                    <th>访客</th>
                                    <th>动作</th>
                                    <th>对象</th>
                                    <th>来源</th>
                                    <th>详情</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($events as $event): ?>
                                    <?php
                                    $action = (string) ($event['action'] ?? 'viewed');
                                    $excerpt = trim((string) ($event['content_excerpt'] ?? ''));
                                    $modalId = 'interaction-detail-' . (int) ($event['id'] ?? 0);
                                    ?>
                                    <tr>
                                        <td class="admin-interaction-time"><?= htmlspecialchars($formatDate($event['created_at'] ?? null)) ?></td>
                                        <td>
                                            <div class="admin-table-title"><?= htmlspecialchars($actorName($event)) ?></div>
                                            <code class="admin-data-code"><?= htmlspecialchars((string) ($event['visitor_hash'] ?? '-')) ?></code>
                                        </td>
                                        <td>
                                            <span class="admin-status-pill <?= htmlspecialchars($actionClass($action)) ?>"><?= htmlspecialchars($actionText($action)) ?></span>
                                        </td>
                                        <td>
                                            <a class="admin-table-title" href="<?= htmlspecialchars($targetUrl($event) ?: '#') ?>" target="_blank" rel="noopener noreferrer">
                                                <?= htmlspecialchars($targetText($event)) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="admin-interaction-source">
                                                <span>IP</span>
                                                <code class="admin-data-code"><?= htmlspecialchars((string) ($event['ip_address'] ?? '-')) ?></code>
                                            </div>
                                        </td>
                                        <td>
                                            <button type="button" class="admin-interaction-detail-button" data-admin-modal-open="<?= htmlspecialchars($modalId) ?>">详情</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="admin-interaction-mobile-list" aria-label="手机端互动记录">
                        <?php foreach ($events as $event): ?>
                            <?php
                            $action = (string) ($event['action'] ?? 'viewed');
                            $modalId = 'interaction-detail-' . (int) ($event['id'] ?? 0);
                            $target = $targetUrl($event);
                            ?>
                            <article class="admin-interaction-mobile-card">
                                <div class="admin-interaction-mobile-main">
                                    <div class="admin-interaction-mobile-title-row">
                                        <div class="admin-interaction-mobile-title"><?= htmlspecialchars($actorName($event)) ?></div>
                                        <span class="admin-status-pill <?= htmlspecialchars($actionClass($action)) ?>"><?= htmlspecialchars($actionText($action)) ?></span>
                                    </div>
                                    <a class="admin-interaction-mobile-target" href="<?= htmlspecialchars($target ?: '#') ?>" target="_blank" rel="noopener noreferrer">
                                        <?= htmlspecialchars($targetText($event)) ?>
                                    </a>
                                </div>
                                <div class="admin-interaction-mobile-meta">
                                    <span><?= htmlspecialchars($formatDate($event['created_at'] ?? null)) ?></span>
                                    <span>IP <?= htmlspecialchars((string) ($event['ip_address'] ?? '-')) ?></span>
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

    <?php foreach ($events as $event): ?>
        <?php
        $action = (string) ($event['action'] ?? 'viewed');
        $excerpt = trim((string) ($event['content_excerpt'] ?? ''));
        $modalId = 'interaction-detail-' . (int) ($event['id'] ?? 0);
        ?>
        <div class="admin-modal" id="<?= htmlspecialchars($modalId) ?>" data-admin-modal aria-hidden="true">
            <div class="admin-modal-backdrop" data-admin-modal-close></div>
            <div class="admin-modal-panel admin-detail-modal admin-interaction-detail-modal" role="dialog" aria-modal="true" aria-labelledby="<?= htmlspecialchars($modalId) ?>-title">
                <div class="admin-modal-head">
                    <div>
                        <h3 class="admin-section-title" id="<?= htmlspecialchars($modalId) ?>-title">互动详情</h3>
                        <p class="admin-section-desc"><?= htmlspecialchars($formatDate($event['created_at'] ?? null)) ?></p>
                    </div>
                    <button type="button" class="admin-modal-close" data-admin-modal-close aria-label="关闭">×</button>
                </div>

                <div class="admin-interaction-detail-panel">
                    <div class="admin-interaction-detail-row">
                        <span class="admin-detail-label">访客</span>
                        <strong><?= htmlspecialchars($actorName($event)) ?></strong>
                    </div>
                    <div class="admin-interaction-detail-row">
                        <span class="admin-detail-label">动作</span>
                        <strong><?= htmlspecialchars($actionText($action)) ?></strong>
                    </div>
                    <div class="admin-interaction-detail-row">
                        <span class="admin-detail-label">对象</span>
                        <a class="admin-table-title" href="<?= htmlspecialchars($targetUrl($event) ?: '#') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($targetText($event)) ?></a>
                    </div>
                    <?php if ($excerpt !== ''): ?>
                    <div class="admin-interaction-detail-row admin-interaction-detail-row-stack">
                        <span class="admin-detail-label"><?= htmlspecialchars($excerptLabel($action)) ?></span>
                        <p class="admin-interaction-detail-text"><?= nl2br(htmlspecialchars($excerpt)) ?></p>
                    </div>
                    <?php endif; ?>
                    <div class="admin-interaction-detail-row admin-interaction-detail-row-stack">
                        <span class="admin-detail-label">访客指纹</span>
                        <code class="admin-data-code"><?= htmlspecialchars((string) ($event['visitor_hash'] ?? '-')) ?></code>
                    </div>
                    <div class="admin-interaction-detail-row">
                        <span class="admin-detail-label">IP</span>
                        <code class="admin-data-code"><?= htmlspecialchars((string) ($event['ip_address'] ?? '-')) ?></code>
                    </div>
                    <div class="admin-interaction-detail-row">
                        <span class="admin-detail-label">来源</span>
                        <code class="admin-data-code"><?= htmlspecialchars($sourceText($event)) ?></code>
                    </div>
                    <div class="admin-interaction-detail-row admin-interaction-detail-row-stack">
                        <span class="admin-detail-label">浏览器</span>
                        <code class="admin-data-code"><?= htmlspecialchars((string) ($event['user_agent'] ?? '-')) ?></code>
                    </div>
                </div>
            </div>
        </div>
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
