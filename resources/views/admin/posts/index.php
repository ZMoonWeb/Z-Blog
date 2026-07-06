<!DOCTYPE html>
<html lang="zh-CN" data-admin-theme="light" data-admin-theme-source="system">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>文章管理 - Z-Blog</title>
    <link rel="icon" href="/assets/img/ZMoon.png" type="image/png">
    <link rel="stylesheet" href="/assets/css/admin/index.css?v=<?= time() ?>">
</head>
<body class="admin-dashboard-page admin-posts-index-page">
    <?php
    $admin = $admin ?? null;
    $posts = $posts ?? [];
    $flash = $flash ?? null;
    $formatDate = static function (?string $date): string {
        $timestamp = strtotime((string) $date);
        return $timestamp ? date('Y-m-d', $timestamp) : '-';
    };
    $postSummary = static function (array $post): string {
        $summary = trim((string) ($post['summary'] ?? ''));
        if ($summary !== '') {
            return $summary;
        }

        $content = trim(strip_tags((string) ($post['content'] ?? '')));
        return $content !== '' ? mb_substr($content, 0, 80) : '暂无摘要';
    };
    ?>

    <div class="admin-layout">
        <?php
        $active = 'posts';
        require __DIR__ . '/../partials/sidebar.php';
        ?>

        <main class="admin-main admin-list-main">
            <?php if (!empty($flash['message'])): ?>
                <div class="admin-flash admin-flash-<?= ($flash['type'] ?? 'success') === 'error' ? 'error' : 'success' ?>">
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>

            <div class="admin-section-actions admin-posts-head">
                <div class="admin-posts-head-copy">
                    <h2 class="admin-section-title">全部文章</h2>
                    <p class="admin-section-desc">发布、编辑和删除博客文章。</p>
                </div>
                <a class="admin-btn admin-posts-create-btn" href="/admin/posts/create">写文章</a>
            </div>

            <section class="admin-table-card admin-posts-list-card">
                <?php if (empty($posts)): ?>
                    <div class="admin-empty">
                        <div class="admin-empty-title">还没有文章</div>
                        <p class="admin-empty-desc">点击下方按钮创建第一篇博客文章。</p>
                        <a class="admin-btn" href="/admin/posts/create">开始写作</a>
                    </div>
                <?php else: ?>
                    <table class="admin-table admin-posts-table">
                        <thead>
                            <tr>
                                <th>标题</th>
                                <th>分类</th>
                                <th>状态</th>
                                <th>发布时间</th>
                                <th style="text-align: right;">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($posts as $post): ?>
                                <tr>
                                    <td>
                                        <a class="admin-table-title" href="/admin/posts/<?= (int) $post['id'] ?>/edit">
                                            <?= htmlspecialchars($post['title']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($post['category_name'] ?: '未分类') ?></td>
                                    <td>
                                        <?php if ((int) $post['status'] === 1): ?>
                                            <span class="admin-badge admin-badge-success">已发布</span>
                                        <?php else: ?>
                                            <span class="admin-badge admin-badge-muted">草稿</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($post['published_at'] ?? '-') ?></td>
                                    <td>
                                        <div class="admin-row-actions">
                                            <a class="admin-btn admin-btn-secondary admin-btn-sm" href="/admin/posts/<?= (int) $post['id'] ?>/edit">编辑</a>
                                            <form class="admin-inline-form" method="post" action="/admin/posts/<?= (int) $post['id'] ?>/delete" onsubmit="return confirm('确定要删除这篇文章吗？')">
                                                <button class="admin-btn admin-btn-danger admin-btn-sm" type="submit">删除</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="admin-post-mobile-list" aria-label="手机端文章列表">
                        <?php foreach ($posts as $post): ?>
                            <?php
                            $postId = (int) ($post['id'] ?? 0);
                            $coverImage = trim((string) ($post['cover_image'] ?? ''));
                            if ($coverImage === '') {
                                $coverImage = '/assets/img/ZMoon.png';
                            }
                            $summary = $postSummary($post);
                            $publishedAt = $formatDate($post['published_at'] ?? $post['created_at'] ?? null);
                            ?>
                            <article class="admin-post-mobile-card">
                                <a class="admin-post-mobile-cover" href="/admin/posts/<?= $postId ?>/edit" aria-label="编辑 <?= htmlspecialchars((string) ($post['title'] ?? '文章')) ?>">
                                    <img src="<?= htmlspecialchars($coverImage) ?>" alt="">
                                </a>
                                <div class="admin-post-mobile-body">
                                    <div class="admin-post-mobile-main">
                                        <a class="admin-post-mobile-title" href="/admin/posts/<?= $postId ?>/edit"><?= htmlspecialchars((string) ($post['title'] ?? '未命名文章')) ?></a>
                                        <p><?= htmlspecialchars($summary) ?></p>
                                    </div>
                                    <div class="admin-post-mobile-meta">
                                        <span><?= htmlspecialchars($publishedAt) ?></span>
                                        <span>浏览 <?= (int) ($post['view_count'] ?? 0) ?></span>
                                        <span>喜欢 <?= (int) ($post['like_count'] ?? 0) ?></span>
                                        <span>评论 <?= (int) ($post['comment_count'] ?? 0) ?></span>
                                    </div>
                                    <div class="admin-post-mobile-actions">
                                        <?php if ((int) ($post['status'] ?? 0) === 1): ?>
                                            <span class="admin-badge admin-badge-success">已发布</span>
                                        <?php else: ?>
                                            <span class="admin-badge admin-badge-muted">草稿</span>
                                        <?php endif; ?>
                                        <div class="admin-post-mobile-action-buttons">
                                            <a class="admin-btn admin-btn-secondary admin-btn-sm" href="/admin/posts/<?= $postId ?>/edit">编辑</a>
                                            <form class="admin-inline-form" method="post" action="/admin/posts/<?= $postId ?>/delete" onsubmit="return confirm('确定要删除这篇文章吗？')">
                                                <button class="admin-btn admin-btn-danger admin-btn-sm" type="submit">删除</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php require __DIR__ . '/../partials/pagination.php'; ?>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <script src="/assets/js/admin/index.js?v=<?= time() ?>"></script>
</body>
</html>
