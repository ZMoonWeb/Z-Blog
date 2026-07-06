<?php
$announcements = $announcements ?? [];

$formatDate = $formatDate ?? static function (?string $date): string {
    $date = trim((string) $date);
    if ($date === '') {
        return '未记录时间';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }

    return date('Y.m.d', $timestamp);
};

$formatTime = $formatTime ?? static function (?string $date): string {
    $date = trim((string) $date);
    if ($date === '') {
        return '';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '';
    }

    return date('H:i', $timestamp);
};

$formatFullDateTime = static function (?string $date): string {
    $date = trim((string) $date);
    if ($date === '') {
        return '未记录时间';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }

    return date('Y-m-d-H:i:s', $timestamp);
};

$noticeStatus = static function (array $announcement): array {
    return match ((string) ($announcement['level'] ?? 'normal')) {
        'urgent' => ['class' => 'is-urgent', 'label' => '紧急'],
        'important' => ['class' => 'is-important', 'label' => '重要'],
        'archived' => ['class' => 'is-archived', 'label' => '归档'],
        default => ['class' => 'is-normal', 'label' => '普通'],
    };
};
?>

<section class="system-notice-panel" aria-labelledby="notice-title">
    <h1 id="notice-title">系统公告</h1>
    <?php if (empty($announcements)): ?>
        <div class="empty-state page-empty system-notice-empty">
            <h2>暂无公告</h2>
            <p>管理员发布公告后，会在这里自动展示。</p>
        </div>
    <?php else: ?>
        <section class="system-notice-list" aria-label="公告列表">
            <?php foreach ($announcements as $index => $announcement): ?>
                <?php
                $createdAt = (string) ($announcement['created_at'] ?? '');
                $status = $noticeStatus($announcement);
                ?>
                <article class="system-notice-item <?= htmlspecialchars($status['class']) ?>">
                    <div class="system-notice-rail" aria-hidden="true">
                        <span class="system-notice-dot"></span>
                    </div>
                    <div class="system-notice-body">
                        <div class="notice-rich-content system-notice-content">
                            <?= $announcement['html'] ?? '' ?>
                        </div>
                        <time class="system-notice-time" datetime="<?= htmlspecialchars($createdAt) ?>">
                            <?= htmlspecialchars($formatFullDateTime($createdAt)) ?>
                        </time>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</section>
