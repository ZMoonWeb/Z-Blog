<?php
$siteSettings = $siteSettings ?? [];
$aboutHtml = $aboutHtml ?? '';
$skills = $skills ?? [];
$links = $links ?? [];
$stats = $stats ?? ['posts' => 0, 'comments' => 0, 'likes' => 0, 'views' => 0];
$statCards = $statCards ?? [];
$featureCards = $featureCards ?? [];

$setting = static function (string $key, string $default = '') use ($siteSettings): string {
    $value = trim((string) ($siteSettings[$key] ?? ''));
    return $value !== '' ? $value : $default;
};

$formatAboutNumber = static function (int $number): string {
    if ($number >= 10000) {
        return rtrim(rtrim(number_format($number / 10000, 1), '0'), '.') . 'w';
    }

    return (string) $number;
};

if (empty($statCards)) {
    $statCards = [
        ['metric_key' => 'posts', 'label' => '文章沉淀', 'description' => '已发布的内容数量', 'icon_class' => 'fa-regular fa-file-lines', 'value' => (int) ($stats['posts'] ?? 0)],
        ['metric_key' => 'views', 'label' => '阅读轨迹', 'description' => '全站累计浏览量', 'icon_class' => 'fa-regular fa-eye', 'value' => (int) ($stats['views'] ?? 0)],
        ['metric_key' => 'likes', 'label' => '喜欢反馈', 'description' => '收到的点赞数量', 'icon_class' => 'fa-regular fa-heart', 'value' => (int) ($stats['likes'] ?? 0)],
        ['metric_key' => 'comments', 'label' => '交流回声', 'description' => '已通过的评论数量', 'icon_class' => 'fa-regular fa-comments', 'value' => (int) ($stats['comments'] ?? 0)],
    ];
}

$aboutTitle = $setting('about_title', '关于本站');
$aboutSubtitle = $setting('about_subtitle', '关于这里的故事，关于写作的小角落');
$aboutAvatar = $setting('about_avatar', '/assets/img/ZMoon.png');
$aboutNote = $setting('about_note', '把文章、灵感和一路踩过的坑都收在这里，慢慢写，也慢慢整理。');
$hasAside = !empty($featureCards) || !empty($skills) || !empty($links);
$hasAboutHtml = trim($aboutHtml) !== '';
?>

<section class="about-ios" aria-labelledby="about-title">
    <header class="about-ios-hero">
        <div class="about-ios-profile-card">
            <span class="about-ios-avatar">
                <img src="<?= htmlspecialchars($aboutAvatar) ?>" alt="<?= htmlspecialchars($aboutTitle) ?>头像">
            </span>

            <div class="about-ios-intro">
                <span class="about-ios-kicker">About</span>
                <h1 id="about-title"><?= htmlspecialchars($aboutTitle) ?></h1>
                <p class="about-ios-lead"><?= htmlspecialchars($aboutSubtitle) ?></p>
                <?php if ($aboutNote !== ''): ?>
                    <p class="about-ios-note"><?= htmlspecialchars($aboutNote) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <ul class="about-ios-stats" aria-label="站点数据">
        <?php foreach ($statCards as $card): ?>
            <?php
            $metricKey = preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($card['metric_key'] ?? 'metric')) ?: 'metric';
            ?>
            <li class="about-ios-stat is-<?= htmlspecialchars($metricKey) ?>">
                <span class="about-ios-stat-icon" aria-hidden="true"><i class="<?= htmlspecialchars((string) ($card['icon_class'] ?? 'fa-solid fa-chart-simple')) ?>"></i></span>
                <div class="about-ios-stat-copy">
                    <strong><?= htmlspecialchars($formatAboutNumber((int) ($card['value'] ?? 0))) ?></strong>
                    <span><?= htmlspecialchars((string) ($card['label'] ?? '数据')) ?></span>
                </div>
                <?php if (trim((string) ($card['description'] ?? '')) !== ''): ?>
                    <small><?= htmlspecialchars((string) $card['description']) ?></small>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="about-ios-main<?= $hasAside ? '' : ' is-single' ?>">
        <article class="about-ios-article article-content">
            <?php if ($hasAboutHtml): ?>
                <?= $aboutHtml ?>
            <?php else: ?>
                <p>这里暂时还没有填写详细介绍。你可以在后台补充关于本站、关于作者或关于内容方向的说明。</p>
            <?php endif; ?>
        </article>

        <?php if ($hasAside): ?>
            <aside class="about-ios-side" aria-label="关于页补充信息">
                <?php if (!empty($featureCards)): ?>
                    <section class="about-ios-card" aria-labelledby="about-feature-title">
                        <div class="about-ios-card-head">
                            <span class="about-ios-kicker">Highlights</span>
                            <h2 id="about-feature-title">亮点</h2>
                        </div>

                        <div class="about-ios-feature-list">
                            <?php foreach ($featureCards as $card): ?>
                                <section class="about-ios-feature">
                                    <span class="about-ios-feature-icon" aria-hidden="true"><i class="<?= htmlspecialchars((string) ($card['icon_class'] ?? 'fa-solid fa-sparkles')) ?>"></i></span>
                                    <div>
                                        <h3><?= htmlspecialchars((string) ($card['title'] ?? '亮点')) ?></h3>
                                        <?php if (trim((string) ($card['description'] ?? '')) !== ''): ?>
                                            <p><?= htmlspecialchars((string) $card['description']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!empty($skills)): ?>
                    <section class="about-ios-card" aria-labelledby="about-keywords-title">
                        <div class="about-ios-card-head">
                            <span class="about-ios-kicker">Keywords</span>
                            <h2 id="about-keywords-title">关键词</h2>
                        </div>

                        <div class="about-ios-tags">
                            <?php foreach ($skills as $skill): ?>
                                <span><?= htmlspecialchars((string) $skill) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!empty($links)): ?>
                    <section class="about-ios-card" aria-labelledby="about-links-title">
                        <div class="about-ios-card-head">
                            <span class="about-ios-kicker">Links</span>
                            <h2 id="about-links-title">找到我</h2>
                        </div>

                        <div class="about-ios-links">
                            <?php foreach ($links as $link): ?>
                                <?php
                                $linkUrl = trim((string) ($link['url'] ?? ''));
                                $linkLabel = trim((string) ($link['label'] ?? ''));
                                if ($linkUrl === '' || $linkLabel === '') {
                                    continue;
                                }
                                ?>
                                <a href="<?= htmlspecialchars($linkUrl) ?>" target="_blank" rel="noopener noreferrer">
                                    <i class="<?= htmlspecialchars((string) ($link['icon'] ?? 'fa-solid fa-link')) ?>" aria-hidden="true"></i>
                                    <span><?= htmlspecialchars($linkLabel) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </aside>
        <?php endif; ?>
    </div>
</section>
