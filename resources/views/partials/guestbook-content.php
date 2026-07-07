<?php
$siteSettings = $siteSettings ?? [];
$messages = $messages ?? [];
$pagination = $pagination ?? [
    'current_page' => 1,
    'last_page' => 1,
    'has_previous' => false,
    'has_next' => false,
];
$guestbookStats = $guestbookStats ?? [
    'total' => $pagination['total'] ?? count($messages),
    'admin' => 0,
    'recent' => 0,
];
$guestbookError = $guestbookError ?? '';
$guestbookSuccess = $guestbookSuccess ?? '';
$guestbookOld = $guestbookOld ?? [];
$guestbookBaseUrl = $guestbookBaseUrl ?? '/guestbook';
$guestbookFormAction = $guestbookFormAction ?? '/guestbook';

$setting = static function (string $key, string $default = '') use ($siteSettings): string {
    $value = trim((string) ($siteSettings[$key] ?? ''));
    return $value !== '' ? $value : $default;
};

$formatDate = static function (?string $date): string {
    $timestamp = strtotime((string) $date);
    return $timestamp ? date('Y.m.d H:i', $timestamp) : (string) $date;
};

$guestbookPageUrl = static function (int $page) use ($guestbookBaseUrl): string {
    $separator = str_contains($guestbookBaseUrl, '?') ? '&' : '?';
    return $guestbookBaseUrl . $separator . 'page=' . $page;
};

$escapeAttr = static fn (?string $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$messagePreview = static function (?string $content): string {
    $text = trim(preg_replace('/\s+/u', ' ', (string) $content) ?? '');
    if (mb_strlen($text) <= 42) {
        return $text;
    }

    return mb_substr($text, 0, 42) . '...';
};

$totalMessages = (int) ($guestbookStats['total'] ?? ($pagination['total'] ?? count($messages)));
$repliedMessages = (int) ($guestbookStats['admin'] ?? 0);
$pendingReplies = max(0, $totalMessages - $repliedMessages);
$recentMessages = (int) ($guestbookStats['recent'] ?? 0);
$guestbookView = (string) ($guestbookView ?? 'list');
$guestbookDetail = isset($guestbookDetail) && is_array($guestbookDetail) ? $guestbookDetail : null;
$guestbookTrends = isset($guestbookTrends) && is_array($guestbookTrends) ? $guestbookTrends : [];
$guestbookComposeUrl = '/guestbook/new';
$guestbookBackUrl = '/guestbook';

$guestbookMessageUrl = static function (array $message): string {
    return '/guestbook/' . (int) ($message['id'] ?? 0);
};

$guestbookInitial = static function (string $nickname): string {
    $initial = mb_substr(trim($nickname), 0, 1);
    return $initial !== '' ? $initial : '访';
};

$formatStatNumber = static fn (int $number): string => number_format($number);

$guestbookStatCards = [
    [
        'class' => 'total',
        'label' => '留言总数',
        'value' => $totalMessages,
        'points' => '4 38 16 29 28 32 40 18 52 24 64 12 76 20 88 14',
        'icon' => '<path d="M4 5.8A2.8 2.8 0 0 1 6.8 3h10.4A2.8 2.8 0 0 1 20 5.8v7.4a2.8 2.8 0 0 1-2.8 2.8H10l-4.8 4v-4A2.8 2.8 0 0 1 4 13.2V5.8Zm4 2.7h8v-1.6H8v1.6Zm0 3.2h6v-1.6H8v1.6Z"/>',
    ],
    [
        'class' => 'reply',
        'label' => '站长回复',
        'value' => $repliedMessages,
        'points' => '4 34 16 36 28 23 40 27 52 16 64 19 76 11 88 15',
        'icon' => '<path d="M9.2 16.6 4.8 12.2l1.7-1.7 2.7 2.7 8.3-8.3 1.7 1.7-10 10Z"/>',
    ],
    [
        'class' => 'pending',
        'label' => '等待回复',
        'value' => $pendingReplies,
        'points' => '4 16 16 29 28 19 40 35 52 22 64 30 76 20 88 34',
        'icon' => '<path d="M12 3a9 9 0 1 1 0 18 9 9 0 0 1 0-18Zm1 4.2h-2v5.5l4.4 2.6 1-1.7-3.4-2V7.2Z"/>',
    ],
    [
        'class' => 'recent',
        'label' => '近30天新增',
        'value' => $recentMessages,
        'points' => '4 36 16 18 28 31 40 14 52 25 64 17 76 28 88 10',
        'icon' => '<path d="M13 2.8 4 13h7l-1 8.2L20 10h-7l1-7.2Z"/>',
    ],
];

$guestbookStatCards = [
    [
        'title' => '留言概览',
        'rows' => [
            [
                'tone' => 'blue',
                'label' => '留言总数',
                'value' => $totalMessages,
                'series' => $guestbookTrends['total'] ?? [],
                'icon' => '<path d="M4 5.8A2.8 2.8 0 0 1 6.8 3h10.4A2.8 2.8 0 0 1 20 5.8v7.4a2.8 2.8 0 0 1-2.8 2.8H10l-4.8 4v-4A2.8 2.8 0 0 1 4 13.2V5.8Zm4 2.7h8v-1.6H8v1.6Zm0 3.2h6v-1.6H8v1.6Z"/>'
            ],
            [
                'tone' => 'amber',
                'label' => '近30天新增',
                'value' => $recentMessages,
                'series' => $guestbookTrends['recent'] ?? [],
                'icon' => '<path d="M13 2.8 4 13h7l-1 8.2L20 10h-7l1-7.2Z"/>'
            ],
        ],
    ],
    [
        'title' => '回复状态',
        'rows' => [
            [
                'tone' => 'green',
                'label' => '站长回复',
                'value' => $repliedMessages,
                'series' => $guestbookTrends['replied'] ?? [],
                'icon' => '<path d="M9.2 16.6 4.8 12.2l1.7-1.7 2.7 2.7 8.3-8.3 1.7 1.7-10 10Z"/>'
            ],
            [
                'tone' => 'pink',
                'label' => '等待回复',
                'value' => $pendingReplies,
                'series' => $guestbookTrends['pending'] ?? [],
                'icon' => '<path d="M12 3a9 9 0 1 1 0 18 9 9 0 0 1 0-18Zm1 4.2h-2v5.5l4.4 2.6 1-1.7-3.4-2V7.2Z"/>'
            ],
        ],
    ],
];

$guestbookSparkline = static function (array $values, int $width = 76, int $height = 24, int $padding = 2): array {
    $values = array_values(array_map(static fn ($value): float => max(0.0, (float) $value), $values));

    if ($values === []) {
        $values = [0.0];
    }

    $count = count($values);
    $innerWidth = max(1, $width - ($padding * 2));
    $innerHeight = max(1, $height - ($padding * 2));
    $max = max($values);
    $min = min($values);
    $range = $max - $min;
    $points = [];

    foreach ($values as $index => $value) {
        $x = $count > 1 ? $padding + ($innerWidth * $index / ($count - 1)) : ($width / 2);
        $y = $range <= 0.00001
            ? $height / 2
            : $height - $padding - (($value - $min) / $range) * $innerHeight;
        $points[] = [$x, $y];
    }

    return [
        'line' => implode(' ', array_map(static fn (array $point): string => sprintf('%.2f %.2f', $point[0], $point[1]), $points)),
        'last' => $points[$count - 1],
    ];
};

$renderGuestbookForm = static function () use ($guestbookFormAction, $guestbookOld): void {
    ?>
    <form class="guestbook-form" method="post" action="<?= htmlspecialchars($guestbookFormAction) ?>">
        <?= \App\Core\Security\Csrf::field() ?>
        <label class="guestbook-field guestbook-form-full">
            <span class="guestbook-field-head">
                <strong>留言内容</strong>
            </span>
            <textarea name="content" maxlength="1000" required placeholder="可以分享建议、想法，或者单纯打个招呼..."><?= htmlspecialchars((string) ($guestbookOld['content'] ?? '')) ?></textarea>
        </label>

        <div class="guestbook-form-actions">
            <span class="guestbook-form-note">
                <span>最多 1000 字，发布后公开展示。</span>
            </span>
            <button type="submit" aria-label="发布留言">
                <span>发布留言</span>
            </button>
        </div>
    </form>
    <?php
};
?>

<?php if ($guestbookView === 'compose'): ?>
<section class="guestbook-board guestbook-page-panel guestbook-compose-page" id="guestbook-compose" aria-labelledby="guestbook-compose-title">
    <article class="guestbook-compose-card">
        <div class="guestbook-page-nav">
            <a class="guestbook-page-back" href="<?= htmlspecialchars($guestbookBackUrl) ?>" aria-label="返回留言板">
                <span>返回留言板</span>
            </a>
        </div>

        <header class="guestbook-compose-head">
            <div class="guestbook-compose-title">
                <h1 id="guestbook-compose-title">我要留言</h1>
                <p><?= htmlspecialchars($setting('guestbook_notice', '请文明留言，垃圾信息会被自动隐藏。留言默认公开显示。')) ?></p>
            </div>
        </header>

        <?php if ($guestbookError !== ''): ?>
            <div class="comment-alert comment-alert-error" role="alert"><?= htmlspecialchars($guestbookError) ?></div>
        <?php endif; ?>

        <?php $renderGuestbookForm(); ?>
    </article>
</section>
<?php return; ?>
<?php endif; ?>

<?php if ($guestbookView === 'detail'): ?>
<?php
$detailMessage = $guestbookDetail;
$detailNickname = (string) ($detailMessage['nickname'] ?? '访客');
$detailContent = (string) ($detailMessage['content'] ?? '');
$detailReply = trim((string) ($detailMessage['admin_reply'] ?? ''));
$detailHasReply = $detailReply !== '';
$detailCreatedAt = $formatDate($detailMessage['created_at'] ?? '');
$detailRepliedAt = trim((string) ($detailMessage['replied_at'] ?? '')) !== '' ? $formatDate($detailMessage['replied_at'] ?? '') : '';
?>
<section class="guestbook-board guestbook-page-panel guestbook-detail-page" id="guestbook-detail">
    <?php if ($detailMessage === null): ?>
        <article class="guestbook-detail-page-card">
            <div class="guestbook-page-nav">
                <a class="guestbook-page-back" href="<?= htmlspecialchars($guestbookBackUrl) ?>" aria-label="返回留言板">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M15.6 5.4 9 12l6.6 6.6-1.4 1.4L6.2 12l8-8 1.4 1.4Z"/>
                    </svg>
                    <span>返回留言板</span>
                </a>
            </div>
            <div class="empty-state page-empty">
                <h1>留言不存在</h1>
                <p>这条留言可能不存在，或暂时没有公开展示。</p>
            </div>
        </article>
    <?php else: ?>
        <article class="guestbook-detail-page-card">
            <div class="guestbook-page-nav">
                <a class="guestbook-page-back" href="<?= htmlspecialchars($guestbookBackUrl) ?>" aria-label="返回留言板">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M15.6 5.4 9 12l6.6 6.6-1.4 1.4L6.2 12l8-8 1.4 1.4Z"/>
                    </svg>
                    <span>返回留言板</span>
                </a>
            </div>
            <header class="guestbook-detail-head">
                <div class="guestbook-detail-title-block">
                    <h1 id="guestbook-detail-title"><?= htmlspecialchars($detailNickname) ?> 的留言</h1>
                    <time datetime="<?= $escapeAttr((string) ($detailMessage['created_at'] ?? '')) ?>"><?= htmlspecialchars($detailCreatedAt) ?></time>
                </div>
            </header>

            <div class="guestbook-detail-content"><?= nl2br(htmlspecialchars($detailContent)) ?></div>

            <div class="guestbook-detail-reply<?= $detailHasReply ? '' : ' no-reply' ?>">
                <div class="guestbook-detail-reply-head">
                    <strong>站长回复</strong>
                    <?php if ($detailHasReply && $detailRepliedAt !== ''): ?>
                        <time><?= htmlspecialchars($detailRepliedAt) ?></time>
                    <?php endif; ?>
                </div>
                <p><?= $detailHasReply ? nl2br(htmlspecialchars($detailReply)) : '暂未回复' ?></p>
            </div>
        </article>
    <?php endif; ?>
</section>
<?php return; ?>
<?php endif; ?>

<section class="guestbook-board" id="message-list">
    <div class="guestbook-board-head">
        <span class="page-kicker">Guestbook</span>
        <h1><?= htmlspecialchars($setting('guestbook_title', '留言板')) ?></h1>
        <p><?= htmlspecialchars($setting('guestbook_subtitle', '在这里，留下你想说的任何一句话')) ?></p>
    </div>

    <details class="guestbook-stats-panel" open>
        <summary class="guestbook-stats-toggle">
            <span class="guestbook-stats-toggle-copy">
                <span class="section-kicker">Stats</span>
                <strong>留言统计</strong>
            </span>
            <span class="guestbook-stats-toggle-meta">
                <span><?= htmlspecialchars($formatStatNumber($totalMessages)) ?> 条留言</span>
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="m7.4 8.6 4.6 4.6 4.6-4.6L18 10l-6 6-6-6 1.4-1.4Z" />
                </svg>
            </span>
        </summary>

        <div class="guestbook-stats-grid" aria-label="留言统计">
            <?php foreach ($guestbookStatCards as $card): ?>
                <article class="guestbook-stat-card guestbook-stat-card-<?= htmlspecialchars((string) ($card['rows'][0]['tone'] ?? 'blue')) ?>">
                    <header class="guestbook-stat-card-head">
                        <h3><?= htmlspecialchars((string) $card['title']) ?></h3>
                    </header>

                    <div class="guestbook-stat-rows">
                        <?php foreach ($card['rows'] as $row): ?>
                            <?php $sparkline = $guestbookSparkline((array) $row['series']); ?>
                            <div class="guestbook-stat-row guestbook-stat-row-<?= htmlspecialchars((string) $row['tone']) ?>">
                                <div class="guestbook-stat-row-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" focusable="false">
                                        <?= $row['icon'] ?>
                                    </svg>
                                </div>

                                <div class="guestbook-stat-row-copy">
                                    <span class="guestbook-stat-row-label"><?= htmlspecialchars((string) $row['label']) ?></span>
                                    <strong class="guestbook-stat-row-value"><?= htmlspecialchars($formatStatNumber((int) $row['value'])) ?></strong>
                                </div>

                                <svg class="guestbook-stat-row-chart" viewBox="0 0 76 24" aria-hidden="true" focusable="false">
                                    <path class="guestbook-stat-row-fill" d="M 2 22 L <?= htmlspecialchars($sparkline['line']) ?> L 74 22 Z" />
                                    <polyline class="guestbook-stat-row-line" points="<?= htmlspecialchars($sparkline['line']) ?>" />
                                    <circle class="guestbook-stat-row-dot" cx="<?= htmlspecialchars(sprintf('%.2f', $sparkline['last'][0])) ?>" cy="<?= htmlspecialchars(sprintf('%.2f', $sparkline['last'][1])) ?>" r="1.8" />
                                </svg>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </details>

    <div class="guestbook-board-divider" aria-hidden="true"></div>

    <div class="guestbook-barrage-section">
        <div class="section-title-row">
            <div>
                <span class="section-kicker">Messages</span>
                <h2>留言弹幕墙</h2>
            </div>
            <a class="section-action guestbook-compose-open" href="<?= htmlspecialchars($guestbookComposeUrl) ?>">我要留言</a>
        </div>

        <?php if (empty($messages)): ?>
            <div class="empty-state page-empty">
                <h2>还没有留言</h2>
                <p>成为第一个在这里留下想法的人。</p>
            </div>
        <?php else: ?>
            <div class="guestbook-barrage-stage" data-guestbook-barrage-stage aria-label="从右向左滑动的留言弹幕">
                <?php foreach ($messages as $message): ?>
                    <?php
                    $nickname = (string) ($message['nickname'] ?? '访客');
                    $isAdmin = (int) ($message['is_admin'] ?? 0) === 1;
                    $adminReply = trim((string) ($message['admin_reply'] ?? ''));
                    $hasReply = $adminReply !== '';
                    $replyStatus = $hasReply ? '站长已回复' : '站长暂未回复';
                    $content = (string) ($message['content'] ?? '');
                    $createdAt = $formatDate($message['created_at'] ?? '');
                    $repliedAt = trim((string) ($message['replied_at'] ?? '')) !== '' ? $formatDate($message['replied_at'] ?? '') : '';
                    ?>
                    <a
                        class="guestbook-barrage-item <?= $isAdmin ? 'is-admin' : '' ?>"
                        href="<?= htmlspecialchars($guestbookMessageUrl($message)) ?>"
                        data-guestbook-barrage
                        aria-label="查看 <?= htmlspecialchars($nickname) ?> 的留言详情"
                    >
                        <span class="guestbook-barrage-copy">
                            <span class="guestbook-barrage-meta">
                                <strong><?= htmlspecialchars($nickname) ?></strong>
                                <small class="<?= $hasReply ? 'has-reply' : 'no-reply' ?>"><?= htmlspecialchars($replyStatus) ?></small>
                            </span>
                            <span class="guestbook-barrage-text"><?= htmlspecialchars($messagePreview($content)) ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (($pagination['last_page'] ?? 1) > 1): ?>
                <nav class="pagination page-pagination" aria-label="留言分页">
                    <?php if (!empty($pagination['has_previous'])): ?>
                        <a href="<?= htmlspecialchars($guestbookPageUrl((int) $pagination['current_page'] - 1)) ?>">上一页</a>
                    <?php else: ?>
                        <span>上一页</span>
                    <?php endif; ?>

                    <span><?= (int) $pagination['current_page'] ?> / <?= (int) $pagination['last_page'] ?></span>

                    <?php if (!empty($pagination['has_next'])): ?>
                        <a href="<?= htmlspecialchars($guestbookPageUrl((int) $pagination['current_page'] + 1)) ?>">下一页</a>
                    <?php else: ?>
                        <span>下一页</span>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($guestbookSuccess !== ''): ?>
        <div class="comment-alert comment-alert-success" role="status"><?= htmlspecialchars($guestbookSuccess) ?></div>
    <?php endif; ?>
</section>
