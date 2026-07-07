<?php
$hotPosts = $hotPosts ?? [];
$likedPostIds = isset($likedPostIds) && is_array($likedPostIds) ? array_map('intval', $likedPostIds) : [];

$formatHotNumber = static function (int $number): string {
    if ($number > 10000) {
        return number_format($number / 10000, 1) . 'W';
    }

    return (string) round($number);
};

$rankClass = static function (int $index): string {
    return match ($index) {
        0 => 'rank-gold',
        1 => 'rank-silver',
        2 => 'rank-bronze',
        default => '',
    };
};
?>

<section class="hot-board-panel" aria-labelledby="hot-board-title">
    <header class="hot-board-head">
        <div>
            <span class="hot-board-kicker">Ranking</span>
            <h2 id="hot-board-title">热榜</h2>
        </div>
        <p>按热度从高到低排序</p>
    </header>

    <div class="hot-board-body">
        <section class="post-section hot-post-section" aria-label="热榜文章排行">
    <?php if (empty($hotPosts)): ?>
        <div class="empty-state page-empty">
            <h2>暂无热榜数据</h2>
            <p>发布文章并产生阅读、点赞或评论后，这里会自动生成热榜。</p>
        </div>
    <?php else: ?>
        <div class="post-list">
            <?php foreach ($hotPosts as $index => $post): ?>
                <?php
                $summary = trim((string) ($post['summary'] ?? ''));
                if ($summary === '') {
                    $summary = mb_substr(strip_tags((string) ($post['content'] ?? '')), 0, 120) . '...';
                }

                $coverImage = trim((string) ($post['cover_image'] ?? ''));
                if ($coverImage === '') {
                    $coverImage = '/assets/img/ZMoon.png';
                }

                $publishedAt = trim((string) ($post['published_at'] ?? $post['created_at'] ?? ''));
                $tagSource = $post['tags'] ?? '';
                $postTags = preg_split('/[,，\s]+/u', trim((string) $tagSource), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                if (empty($postTags) && !empty($post['category_name'])) {
                    $postTags = [(string) $post['category_name']];
                }

                $commentCount = (int) ($post['comment_count'] ?? 0);
                $likeCount = (int) ($post['like_count'] ?? 0);
                $viewCount = (int) ($post['view_count'] ?? 0);
                $hotScore = (int) round($viewCount * 1 + $likeCount * 1.2 + $commentCount * 1.5);
                $postId = (int) ($post['id'] ?? 0);
                $postTitle = (string) ($post['title'] ?? '未命名文章');
                $postSlug = (string) ($post['slug'] ?? '');
                $postUrl = $postSlug !== '' ? '/post/' . rawurlencode($postSlug) : '#';
                $isLiked = in_array($postId, $likedPostIds, true);
                ?>
                <article class="post-card hot-post-card">
                    <a class="post-card-cover" href="<?= htmlspecialchars($postUrl) ?>" aria-label="阅读<?= htmlspecialchars($postTitle) ?>">
                        <img src="<?= htmlspecialchars($coverImage) ?>" alt="<?= htmlspecialchars($postTitle) ?>封面" loading="lazy">
                    </a>

                    <div class="post-card-body">
                        <div class="post-card-labels">
                            <span class="hot-rank-badge <?= htmlspecialchars($rankClass($index)) ?>">#<?= $index + 1 ?></span>
                            <span class="hot-score-badge">热度 <?= htmlspecialchars($formatHotNumber($hotScore)) ?></span>
                        </div>

                        <h3><a class="post-card-title-link" href="<?= htmlspecialchars($postUrl) ?>"><?= htmlspecialchars($postTitle) ?></a></h3>
                        <p><a class="post-card-summary-link" href="<?= htmlspecialchars($postUrl) ?>"><?= htmlspecialchars($summary) ?></a></p>

                        <div class="post-card-footer">
                            <div class="post-card-meta">
                                <span class="post-time"><?= htmlspecialchars($publishedAt) ?></span>
                                <?php foreach (array_slice($postTags, 0, 4) as $tag): ?>
                                    <span class="post-tag"><?= htmlspecialchars((string) $tag) ?></span>
                                <?php endforeach; ?>
                            </div>

                            <div class="post-stats" aria-label="文章互动数据">
                                <span class="post-stat">
                                    <svg viewBox="0 0 1024 1024" fill="currentColor" width="14" height="14" aria-hidden="true">
                                        <path d="M512 853.312c-176 0-323.84-158.08-396.352-251.712a149.312 149.312 0 0 1 0-180.736C188.16 327.04 336 170.688 512 170.688c176 0 323.84 157.248 396.352 251.136a149.312 149.312 0 0 1 0 180.672c-72.512 92.8-220.352 250.88-396.352 250.88zM512 256C371.2 256 244.672 394.88 181.76 477.632a59.328 59.328 0 0 0 0 68.928C244.672 628.48 371.2 768 512 768s267.328-138.688 330.24-221.44a59.328 59.328 0 0 0 0-68.928C779.328 394.048 652.8 256 512 256z m0 426.624a170.688 170.688 0 1 1 0-341.312 170.688 170.688 0 0 1 0 341.312z m0-256a85.312 85.312 0 1 0 0 170.688 85.312 85.312 0 0 0 0-170.688z"/>
                                    </svg>
                                    <?= $viewCount ?>
                                </span>
                                <form class="inline-like-form" method="post" action="<?= htmlspecialchars($postUrl) ?>/like">
                                    <?= \App\Core\Security\Csrf::field() ?>
                                    <input type="hidden" name="redirect_to" value="<?= htmlspecialchars((string) ($_SERVER['REQUEST_URI'] ?? '/hot')) ?>">
                                    <button class="post-stat post-like inline-like-button <?= $isLiked ? 'is-liked' : '' ?>" type="submit" data-like-toggle data-like-post-id="<?= $postId ?>" aria-label="<?= $isLiked ? '取消点赞' : '点赞' ?>：<?= htmlspecialchars($postTitle) ?>" aria-pressed="<?= $isLiked ? 'true' : 'false' ?>">
                                        <span class="post-like-heart" aria-hidden="true">♥</span>
                                        <span data-like-count><?= $likeCount ?></span>
                                    </button>
                                </form>
                                <span class="post-stat">
                                    <svg viewBox="0 0 1024 1024" fill="currentColor" width="14" height="14" aria-hidden="true">
                                        <path d="M878.3 98.2H145.7c-44.7 0-81 36.3-81 81V714c0 44.7 36.3 81 81 81h192.8l149.2 121.8c7.4 6 16.3 9 25.3 9 8.9 0 17.9-3 25.2-9l150-121.8h190c44.7 0 81-36.3 81-81V179.2c0.1-44.7-36.3-81-80.9-81z m1 615.8c0 0.5-0.5 1-1 1H674.1c-9.2 0-18.1 3.2-25.2 9L513.1 834.2 378.1 724c-7.1-5.8-16.1-9-25.3-9H145.7c-0.5 0-1-0.5-1-1V179.2c0-0.5 0.5-1 1-1h732.5c0.5 0 1 0.5 1 1V714z"/>
                                        <path d="M322.1 447.6m-50 0a50 50 0 1 0 100 0 50 50 0 1 0-100 0Z"/>
                                        <path d="M513.1 447.6m-50 0a50 50 0 1 0 100 0 50 50 0 1 0-100 0Z"/>
                                        <path d="M704.3 447.6m-50 0a50 50 0 1 0 100 0 50 50 0 1 0-100 0Z"/>
                                    </svg>
                                    <?= $commentCount ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
        </section>
    </div>
</section>
