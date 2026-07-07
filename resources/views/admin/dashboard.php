<!DOCTYPE html>
<html lang="zh-CN" data-admin-theme="light" data-admin-theme-source="system">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(\App\Core\Security\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
    <meta name="color-scheme" content="light dark">
    <title>后台管理 - Z-Blog</title>
    <link rel="icon" href="/assets/img/ZMoon.png" type="image/png">
    <link rel="stylesheet" href="/assets/css/admin/index.css?v=<?= time() ?>">
</head>
<body class="admin-dashboard-page">
    <?php
    $admin = $admin ?? null;
    $stats = $stats ?? [
        'posts' => 0,
        'categories' => 0,
        'published' => 0,
        'drafts' => 0,
        'comments' => 0,
        'likes' => 0,
    ];
    $trend = $trend ?? [];
    $server = $server ?? [];

    $adminName = (string) ($admin['username'] ?? '管理员');
    $blogVersion = (string) ($blogVersion ?? '1.0.2');
    $updateCheckUrlConfigured = (bool) ($updateCheckUrlConfigured ?? false);

    $hour = (int) (new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai')))->format('H');
    $greeting = $hour < 6 ? '夜深了' : ($hour < 12 ? '早上好' : ($hour < 18 ? '下午好' : '晚上好'));

    $statCards = [
        ['label' => '文章总量', 'value' => (int) ($stats['posts'] ?? 0), 'unit' => '篇', 'hint' => '已发布 ' . (int) ($stats['published'] ?? 0) . ' · 草稿 ' . (int) ($stats['drafts'] ?? 0), 'icon' => 'file'],
        ['label' => '评论总数', 'value' => (int) ($stats['comments'] ?? 0), 'unit' => '条', 'hint' => '读者参与讨论', 'icon' => 'comment'],
        ['label' => '获赞数量', 'value' => (int) ($stats['likes'] ?? 0), 'unit' => '次', 'hint' => '文章获得认可', 'icon' => 'heart'],
        ['label' => '分类数量', 'value' => (int) ($stats['categories'] ?? 0), 'unit' => '个', 'hint' => '内容归档分组', 'icon' => 'folder'],
    ];

    $maxTrend = 1;
    foreach ($trend as $day) {
        $maxTrend = max($maxTrend, (int) $day['posts'], (int) $day['comments'], (int) $day['likes']);
    }

    $chartW = 760;
    $chartH = 320;
    $padL = 44;
    $padR = 16;
    $padT = 20;
    $padB = 34;
    $plotW = $chartW - $padL - $padR;
    $plotH = $chartH - $padT - $padB;
    $stepX = count($trend) > 1 ? $plotW / (count($trend) - 1) : $plotW;

    // Y 轴最大值：至少为 4，向上取整到整数，避免全 1 时挤在底部
    $yMax = max(4, (int) ceil($maxTrend * 1.15));

    $yOf = static function (int $v) use ($padT, $plotH, $yMax): float {
        return $padT + $plotH - ($v / $yMax) * $plotH;
    };

    $buildPath = static function (string $key) use ($trend, $yOf, $padL, $stepX): string {
        $points = [];
        foreach ($trend as $i => $day) {
            $x = $padL + $i * $stepX;
            $y = $yOf((int) ($day[$key] ?? 0));
            $points[] = $i === 0 ? "M{$x},{$y}" : "L{$x},{$y}";
        }
        return implode(' ', $points);
    };

    // Y 轴刻度（4 等分）
    $yTicks = [];
    for ($g = 0; $g <= 4; $g++) {
        $yTicks[] = [
            'y' => $padT + ($plotH / 4) * $g,
            'val' => (int) round($yMax * (1 - $g / 4)),
        ];
    }

    $series = [
        ['key' => 'posts', 'color' => '#111827', 'label' => '文章'],
        ['key' => 'comments', 'color' => '#6366f1', 'label' => '评论'],
        ['key' => 'likes', 'color' => '#ec4899', 'label' => '点赞'],
    ];

    $serverRows = [
        ['PHP 版本', (string) ($server['php_version'] ?? '未知')],
        ['运行环境', (string) ($server['php_sapi'] ?? '未知')],
        ['服务器软件', (string) ($server['server_software'] ?? '未知')],
        ['操作系统', (string) ($server['os'] ?? '未知')],
        ['运行时长', (string) ($server['uptime'] ?? '未知')],
        ['内存限制', (string) ($server['memory_limit'] ?? '未知')],
    ];

    $cpu = $server['cpu'] ?? [];
    $memory = $server['memory'] ?? [];
    $disk = $server['disk'] ?? [];
    $load = $server['load'] ?? null;

    $cpuPercent = $cpu['percent'] !== null ? (float) $cpu['percent'] : null;
    $memPercent = $memory['percent'] !== null ? (float) $memory['percent'] : null;
    $diskPercent = $disk['percent'] !== null ? (float) $disk['percent'] : null;

    $barColor = static function (?float $p): string {
        if ($p === null) return 'var(--admin-text-muted)';
        if ($p >= 85) return '#dc2626';
        if ($p >= 60) return '#d97706';
        return '#16a34a';
    };
    ?>

    <div class="admin-layout">
        <?php
        $active = 'dashboard';
        require __DIR__ . '/partials/sidebar.php';
        ?>

        <?php if ($updateCheckUrlConfigured): ?>
        <script>
        window.__zblogAdminUpdateCheckEarly = window.__zblogAdminUpdateCheckEarly || (function () {
            try {
                var csrfMeta = document.querySelector('meta[name="csrf-token"]');
                var csrfToken = csrfMeta ? String(csrfMeta.getAttribute('content') || '') : '';
                return fetch('/admin/api/check-update', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: Object.assign({
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }, csrfToken !== '' ? { 'X-CSRF-Token': csrfToken } : {})
                }).then(function (response) {
                    return response.text().then(function (text) {
                        var payload = null;
                        try {
                            payload = text ? JSON.parse(text) : null;
                        } catch (error) {}

                        return {
                            ok: response.ok,
                            status: response.status,
                            payload: payload
                        };
                    });
                }).catch(function () {
                    return null;
                });
            } catch (error) {
                return null;
            }
        })();
        </script>
        <?php endif; ?>

        <main class="admin-main">
            <div class="dash">
                <header class="dash-greet">
                    <h1 class="dash-greet-title">👋 <?= htmlspecialchars($greeting) ?>，<?= htmlspecialchars($adminName) ?></h1>
                    <p class="dash-greet-sub">今天是 <?= (new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai')))->format('Y 年 n 月 j 日') ?>，欢迎回到 Z-Blog 管理后台。</p>
                </header>

                <section class="dash-overview-card">
                    <div class="dash-stat-grid">
                        <?php foreach ($statCards as $card): ?>
                        <article class="dash-stat-card">
                            <span class="dash-stat-label"><?= htmlspecialchars($card['label']) ?></span>
                            <div class="dash-stat-value">
                                <strong><?= (int) $card['value'] ?></strong>
                                <span class="dash-stat-unit"><?= htmlspecialchars($card['unit']) ?></span>
                            </div>
                            <span class="dash-stat-hint"><?= htmlspecialchars($card['hint']) ?></span>
                        </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="dash-overview-divider"></div>

                    <div class="dash-chart-head">
                        <h2>近 7 天互动趋势</h2>
                        <div class="dash-chart-legend">
                            <?php foreach ($series as $s): ?>
                            <span class="dash-legend-item"><i style="background:<?= htmlspecialchars($s['color']) ?>"></i><?= htmlspecialchars($s['label']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="dash-chart-body" data-trend-chart data-trend-json="<?= htmlspecialchars(json_encode(array_map(static fn($d) => ['label' => $d['label'], 'posts' => (int)$d['posts'], 'comments' => (int)$d['comments'], 'likes' => (int)$d['likes']], $trend), JSON_UNESCAPED_UNICODE)) ?>">
                        <svg class="dash-chart" viewBox="0 0 <?= $chartW ?> <?= $chartH ?>" role="img" aria-label="近 7 天互动趋势折线图">
                            <?php foreach ($yTicks as $tick): ?>
                            <line x1="<?= $padL ?>" y1="<?= $tick['y'] ?>" x2="<?= $chartW - $padR ?>" y2="<?= $tick['y'] ?>" stroke="currentColor" stroke-width="1" opacity="0.1"/>
                            <text x="<?= $padL - 8 ?>" y="<?= $tick['y'] + 4 ?>" text-anchor="end" font-size="11" fill="currentColor" opacity="0.45"><?= (int) $tick['val'] ?></text>
                            <?php endforeach; ?>

                            <?php foreach ($trend as $i => $day):
                                $x = $padL + $i * $stepX;
                            ?>
                            <line x1="<?= $x ?>" y1="<?= $padT ?>" x2="<?= $x ?>" y2="<?= $padT + $plotH ?>" stroke="currentColor" stroke-width="1" opacity="0.05"/>
                            <?php endforeach; ?>

                            <?php foreach ($series as $s):
                                $path = $buildPath($s['key']);
                                if ($path === '') { continue; }
                            ?>
                            <path d="<?= $path ?>" fill="none" stroke="<?= htmlspecialchars($s['color']) ?>" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <?php endforeach; ?>

                            <?php foreach ($trend as $i => $day): $x = $padL + $i * $stepX; ?>
                            <text x="<?= $x ?>" y="<?= $chartH - 10 ?>" text-anchor="middle" font-size="11" fill="currentColor" opacity="0.5"><?= htmlspecialchars($day['label']) ?></text>
                            <?php endforeach; ?>
                        </svg>
                        <div class="dash-chart-tooltip" data-trend-tooltip hidden></div>
                    </div>
                </section>

                <section class="dash-server-card" data-server-card>
                    <div class="dash-server-head">
                        <h2>服务器运行状况</h2>
                        <span class="dash-server-badge">运行中</span>
                    </div>

                    <div class="dash-ring-grid">
                        <?php
                        $rings = [
                            ['key' => 'cpu', 'label' => 'CPU', 'percent' => $cpuPercent, 'sub' => (int) ($cpu['cores'] ?? 0) . ' 核'],
                            ['key' => 'memory', 'label' => '内存', 'percent' => $memPercent, 'sub' => (string) ($memory['used'] ?? '0') . ' / ' . (string) ($memory['total'] ?? '0')],
                            ['key' => 'disk', 'label' => '磁盘', 'percent' => $diskPercent, 'sub' => (string) ($disk['used'] ?? '0') . ' / ' . (string) ($disk['total'] ?? '0')],
                        ];
                        foreach ($rings as $r):
                            $pct = $r['percent'] !== null ? max(0, min(100, $r['percent'])) : 0;
                            $color = $barColor($r['percent']);
                            $circumference = 2 * M_PI * 42;
                            $offset = $circumference * (1 - $pct / 100);
                        ?>
                        <div class="dash-ring" data-ring="<?= htmlspecialchars($r['key']) ?>">
                            <svg class="dash-ring-svg" viewBox="0 0 100 100" aria-hidden="true">
                                <circle class="dash-ring-track" cx="50" cy="50" r="42" fill="none" stroke-width="8"/>
                                <circle class="dash-ring-prog" cx="50" cy="50" r="42" fill="none" stroke-width="8"
                                    stroke="<?= htmlspecialchars($color) ?>"
                                    stroke-dasharray="<?= htmlspecialchars((string) $circumference) ?>"
                                    stroke-dashoffset="<?= htmlspecialchars((string) $offset) ?>"
                                    stroke-linecap="round"
                                    transform="rotate(-90 50 50)"
                                    data-circumference="<?= htmlspecialchars((string) $circumference) ?>"/>
                            </svg>
                            <div class="dash-ring-center">
                                <span class="dash-ring-pct" data-ring-pct><?= $r['percent'] !== null ? htmlspecialchars((string) $r['percent']) . '%' : '—' ?></span>
                            </div>
                            <span class="dash-ring-label"><?= htmlspecialchars($r['label']) ?></span>
                            <span class="dash-ring-sub" data-ring-sub><?= htmlspecialchars($r['sub']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="dash-collapse" data-collapse="load">
                        <button class="dash-collapse-toggle" type="button" data-collapse-toggle aria-expanded="true">
                            <span class="dash-collapse-title">系统负载</span>
                            <span class="dash-collapse-now" data-load-now>—</span>
                            <svg class="dash-collapse-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                        <div class="dash-collapse-body" data-collapse-body>
                            <svg class="dash-load-svg" viewBox="0 0 300 80" preserveAspectRatio="none" aria-hidden="true" data-load-svg>
                                <line x1="0" y1="20" x2="300" y2="20" stroke="currentColor" stroke-width="1" opacity="0.1"/>
                                <line x1="0" y1="40" x2="300" y2="40" stroke="currentColor" stroke-width="1" opacity="0.1"/>
                                <line x1="0" y1="60" x2="300" y2="60" stroke="currentColor" stroke-width="1" opacity="0.1"/>
                                <path class="dash-load-path" data-load-path d="" fill="none" stroke="#6366f1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <div class="dash-load-meta">
                                <span data-load-m1>1m: —</span>
                                <span data-load-m5>5m: —</span>
                                <span data-load-m15>15m: —</span>
                            </div>
                        </div>
                    </div>

                    <div class="dash-collapse" data-collapse="info">
                        <button class="dash-collapse-toggle" type="button" data-collapse-toggle aria-expanded="false">
                            <span class="dash-collapse-title">详细信息</span>
                            <svg class="dash-collapse-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                        <div class="dash-collapse-body" data-collapse-body>
                            <div class="dash-server-list">
                                <?php foreach ($serverRows as $row): ?>
                                <div class="dash-server-row">
                                    <span class="dash-server-key"><?= htmlspecialchars($row[0]) ?></span>
                                    <span class="dash-server-val"><?= htmlspecialchars($row[1]) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <div
        data-update-check-card
        data-update-auto-check="true"
        data-update-check-enabled="<?= $updateCheckUrlConfigured ? 'true' : 'false' ?>"
        data-update-check-url="/admin/api/check-update"
        data-update-notes-url="/admin/api/update-notes"
        data-current-version="<?= htmlspecialchars($blogVersion) ?>"
        hidden
    ></div>

    <div class="admin-modal admin-update-notes-modal" id="admin-update-notes-modal" data-admin-modal aria-hidden="true">
        <div class="admin-modal-backdrop" data-admin-modal-close></div>
        <section class="admin-modal-panel admin-detail-modal admin-update-notes-panel" role="dialog" aria-modal="true" aria-labelledby="admin-update-notes-title">
            <div class="admin-modal-head admin-update-notes-head">
                <div>
                    <h3 class="admin-section-title" id="admin-update-notes-title">发现新版本</h3>
                    <p class="admin-section-desc" data-update-notes-summary>检测到 <span data-update-notes-version>新版本</span>，可以前往 GitHub 下载。</p>
                </div>
            </div>
            <div class="admin-update-notes-content" data-update-notes-content>
                <p class="admin-update-notes-empty">正在读取更新说明。</p>
            </div>
            <label class="admin-update-remind-option">
                <input type="checkbox" data-update-no-remind-today>
                <span>今日不再提醒</span>
            </label>
            <div class="admin-update-notes-actions">
                <button class="admin-btn admin-btn-secondary" type="button" data-admin-modal-close data-update-defer>暂不下载</button>
                <a class="admin-btn admin-update-download-button" href="https://github.com" target="_blank" rel="noopener noreferrer" data-update-download>前往下载</a>
            </div>
        </section>
    </div>
    <script src="/assets/js/admin/modules/theme.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/modules/sidebar.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/modules/modal.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/modules/editor.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/modules/forms.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/modules/upload-preview.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/modules/update-check.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/index.js?v=<?= time() ?>"></script>
    <script src="/assets/js/admin/modules/dashboard-metrics.js?v=<?= time() ?>"></script>
</body>
</html>
