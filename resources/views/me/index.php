<?php
ob_start();

$siteSettings = $siteSettings ?? [];
$profileCover = $profileCover ?? '/assets/img/backgrounds/sidebar-profile-cover.png';
$profileAvatar = $profileAvatar ?? '/assets/img/ZMoon.png';
$profileName = $profileName ?? 'Z-Blog';
$profileText = $profileText ?? '';
$statCards = $statCards ?? [];
$posts = $posts ?? [];
$likedPostIds = $likedPostIds ?? [];

$formatNumber = static function (int $number): string {
    if ($number >= 10000) {
        return rtrim(rtrim(number_format($number / 10000, 1), '0'), '.') . 'w';
    }

    return (string) $number;
};

$formatDate = static function (string $raw): string {
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }

    try {
        $date = new DateTimeImmutable($raw, new DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai'));
        return $date->format('Y-m-d');
    } catch (Exception $e) {
        return htmlspecialchars($raw);
    }
};

$coverImage = trim($profileCover) !== '' ? $profileCover : '/assets/img/backgrounds/sidebar-profile-cover.png';
$avatarImage = trim($profileAvatar) !== '' ? $profileAvatar : '/assets/img/ZMoon.png';
?>

<div class="me-page">
    <section class="me-cover" aria-hidden="true">
        <img class="me-cover-img" src="<?= htmlspecialchars($coverImage) ?>" alt="">
    </section>

    <header class="me-head">
        <img class="me-avatar" src="<?= htmlspecialchars($avatarImage) ?>" alt="<?= htmlspecialchars($profileName) ?> 头像">
        <div class="me-head-meta">
            <h1 class="me-name"><?= htmlspecialchars($profileName) ?></h1>
            <?php if ($profileText !== ''): ?>
                <p class="me-text"><?= htmlspecialchars($profileText) ?></p>
            <?php endif; ?>
        </div>
    </header>

    <section class="me-stats" aria-label="作者数据">
        <?php foreach ($statCards as $card): ?>
            <div class="me-stat-item">
                <span class="me-stat-value"><?= htmlspecialchars($formatNumber((int) ($card['value'] ?? 0))) ?></span>
                <span class="me-stat-label"><?= htmlspecialchars((string) ($card['label'] ?? '')) ?></span>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="me-works" aria-label="作品列表">
        <div class="me-works-head">
            <h2>作品</h2>
        </div>

        <?php if (!empty($posts)): ?>
        <div class="me-works-grid">
            <?php foreach ($posts as $post):
                $postSlug = (string) ($post['slug'] ?? '');
                $postUrl = '/post/' . rawurlencode($postSlug);
                $postTitle = (string) ($post['title'] ?? '');
                $summary = trim((string) ($post['summary'] ?? ''));
                if ($summary === '') {
                    $summary = mb_substr(strip_tags((string) ($post['content'] ?? '')), 0, 80);
                }
                $workCover = trim((string) ($post['cover_image'] ?? ''));
                if ($workCover === '') {
                    $workCover = '/assets/img/ZMoon.png';
                }
                $publishedAt = $formatDate((string) ($post['published_at'] ?? $post['created_at'] ?? ''));
                $viewCount = (int) ($post['view_count'] ?? 0);
                $likeCount = (int) ($post['like_count'] ?? 0);
                $commentCount = (int) ($post['comment_count'] ?? 0);
            ?>
            <a class="me-work-card" href="<?= htmlspecialchars($postUrl) ?>" aria-label="阅读<?= htmlspecialchars($postTitle) ?>">
                <img class="me-work-cover" src="<?= htmlspecialchars($workCover) ?>" alt="<?= htmlspecialchars($postTitle) ?>封面" loading="lazy">
                <div class="me-work-body">
                    <h3 class="me-work-title"><?= htmlspecialchars($postTitle) ?></h3>
                    <?php if ($summary !== ''): ?>
                        <p class="me-work-summary"><?= htmlspecialchars($summary) ?></p>
                    <?php endif; ?>
                    <div class="me-work-meta">
                        <?php if ($publishedAt !== ''): ?>
                            <span><?= htmlspecialchars($publishedAt) ?></span>
                        <?php endif; ?>
                        <span>浏览 <?= htmlspecialchars($formatNumber($viewCount)) ?></span>
                        <span>喜欢 <?= htmlspecialchars($formatNumber($likeCount)) ?></span>
                        <span>评论 <?= htmlspecialchars($formatNumber($commentCount)) ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="me-works-empty">
            还没有发布作品，敬请期待。
        </div>
        <?php endif; ?>
    </section>
</div>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/main.php';
