<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Validator;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\AdminLoginAttempt;
use App\Models\Category;
use App\Models\Comment;
use App\Models\GuestbookMessage;
use App\Models\Like;
use App\Models\Post;
use App\Models\PostInteractionLog;
use App\Models\SiteContent;

class AdminController
{
    private const ADMIN_LIST_PER_PAGE = 10;
    private const ADMIN_SESSION_TTL = 86400;
    private const ADMIN_SESSION_REGENERATE_INTERVAL = 900;
    private const ADMIN_LOGIN_MAX_ATTEMPTS = 3;
    private const ADMIN_LOGIN_LOCK_SECONDS = 600;
    private const ADMIN_LOGIN_WINDOW_SECONDS = 600;
    private const UPDATE_PACKAGE_MAX_BYTES = 104857600;
    private const UPDATE_PROTECTED_FILES = ['.env'];
    private const UPDATE_PROTECTED_PATH_PREFIXES = [
        '.git/',
        '.agents/',
        '.codex/',
        'public/uploads/',
        'storage/cache/',
        'storage/logs/',
        'storage/update-tmp/',
        'storage/update-backups/',
    ];

    public function index(): void
    {
        $this->requireLogin();

        $admin = $_SESSION['admin'] ?? null;
        $posts = Post::all();

        $trend = $this->buildWeeklyTrend();

        $this->render('admin/dashboard', [
            'admin' => $admin,
            'stats' => [
                'posts' => count($posts),
                'categories' => count(Category::all()),
                'published' => count(array_filter($posts, static fn (array $post): bool => (int) $post['status'] === 1)),
                'drafts' => count(array_filter($posts, static fn (array $post): bool => (int) $post['status'] === 0)),
                'comments' => Comment::countAll(),
                'likes' => Like::countAll(),
                'guestbook' => GuestbookMessage::countAll(),
                'guestbook_pending' => GuestbookMessage::countByStatus(GuestbookMessage::STATUS_PENDING),
            ],
            'trend' => $trend,
            'server' => $this->serverInfo(),
            'blogVersion' => $this->currentBlogVersion(),
            'updateCheckUrlConfigured' => trim((string) Config::get('app.update_check_url', '')) !== '',
        ]);
    }

    /**
     * 近 7 天每日互动趋势：文章发布数、评论数、点赞数
     * @return array<int, array{date: string, label: string, posts: int, comments: int, likes: int}>
     */
    private function buildWeeklyTrend(): array
    {
        $tz = new \DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai');
        $today = new \DateTimeImmutable('today', $tz);
        $days = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = $today->modify("-{$i} days");
            $days[] = [
                'date' => $day->format('Y-m-d'),
                'label' => $day->format('n/j'),
                'start' => $day->format('Y-m-d 00:00:00'),
                'end' => $day->format('Y-m-d 23:59:59'),
                'posts' => 0,
                'comments' => 0,
                'likes' => 0,
            ];
        }

        $dayMap = [];
        foreach ($days as $index => $day) {
            $dayMap[$day['date']] = $index;
        }

        $stmt = \App\Core\Database::query(
            "SELECT DATE(created_at) AS d, COUNT(*) AS c
            FROM posts
            WHERE created_at >= ? AND created_at <= ?
            GROUP BY d",
            [$days[0]['start'], $days[6]['end']]
        );
        foreach ($stmt->fetchAll() as $row) {
            $d = (string) ($row['d'] ?? '');
            if (isset($dayMap[$d])) {
                $days[$dayMap[$d]]['posts'] = (int) $row['c'];
            }
        }

        $stmt = \App\Core\Database::query(
            "SELECT DATE(created_at) AS d, COUNT(*) AS c
            FROM post_comments
            WHERE created_at >= ? AND created_at <= ?
            GROUP BY d",
            [$days[0]['start'], $days[6]['end']]
        );
        foreach ($stmt->fetchAll() as $row) {
            $d = (string) ($row['d'] ?? '');
            if (isset($dayMap[$d])) {
                $days[$dayMap[$d]]['comments'] = (int) $row['c'];
            }
        }

        $stmt = \App\Core\Database::query(
            "SELECT DATE(created_at) AS d, COUNT(*) AS c
            FROM post_likes
            WHERE created_at >= ? AND created_at <= ?
            GROUP BY d",
            [$days[0]['start'], $days[6]['end']]
        );
        foreach ($stmt->fetchAll() as $row) {
            $d = (string) ($row['d'] ?? '');
            if (isset($dayMap[$d])) {
                $days[$dayMap[$d]]['likes'] = (int) $row['c'];
            }
        }

        return $days;
    }

    /**
     * 服务器运行状况（CPU/内存/磁盘/负载）。
     *
     * 核心原理：
     * - CPU：读取 /proc/stat，两次采样（间隔 200ms）计算差值得到使用率
     * - 内存：读取 /proc/meminfo，MemTotal - MemAvailable 得到已用，MemAvailable 为真实可用
     * - 磁盘：disk_total_space() / disk_free_space()
     * - 负载：sys_getloadavg() 取 1/5/15 分钟平均负载
     *
     * @return array<string, mixed>
     */
    private function serverInfo(): array
    {
        $formatBytes = static function (float $bytes): string {
            if ($bytes <= 0) {
                return '0 B';
            }
            $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
            $power = (int) floor(log($bytes, 1024));
            $power = min($power, count($units) - 1);
            return round($bytes / (1024 ** $power), 1) . ' ' . $units[$power];
        };

        $isLinux = stripos(PHP_OS_FAMILY, 'Linux') === 0 || stripos(PHP_OS, 'Linux') === 0;

        // —— 运行时长 ——
        $uptime = '未知';
        if ($isLinux) {
            $raw = $this->readSystemFile('/proc/uptime');
            if ($raw !== null && preg_match('/^(\d+(?:\.\d+)?)/', $raw, $m)) {
                $secs = (int) floor((float) $m[1]);
                $d = (int) floor($secs / 86400);
                $h = (int) floor(($secs % 86400) / 3600);
                $mi = (int) floor(($secs % 3600) / 60);
                $uptime = ($d > 0 ? $d . ' 天 ' : '') . $h . ' 小时 ' . $mi . ' 分钟';
            }
        }
        // —— CPU 使用率（两次采样 /proc/stat）——
        $cpuPercent = null;
        $cpuCores = null;
        $cpuModel = '未知';
        if ($isLinux) {
            // 核心数
            if (($cpuinfo = $this->readSystemFile('/proc/cpuinfo')) !== null) {
                $cpuCores = substr_count($cpuinfo, 'processor');
                if (preg_match('/model name\s*:\s*(.+)/', $cpuinfo, $cm)) {
                    $cpuModel = trim($cm[1]);
                }
            }
            $cpuPercent = $this->sampleCpuUsage();
        }

        // —— 内存（/proc/meminfo，系统原始视角，与宝塔一致）——
        // 已用 = MemTotal - (MemFree + Buffers + Cached)，buffers/cache 视为可回收
        $mem = ['total' => 0, 'available' => 0, 'used' => 0, 'percent' => null];
        $raw = $isLinux ? $this->readSystemFile('/proc/meminfo') : null;
        if ($raw !== null) {
            $get = static function (string $key) use ($raw): int {
                return preg_match('/^' . $key . ':\s+(\d+)/m', $raw, $m) ? (int) $m[1] : 0;
            };
            // 单位 kB
            $totalKb = $get('MemTotal');
            $freeKb = $get('MemFree');
            $buffersKb = $get('Buffers');
            // Cached 在 /proc/meminfo 里可能是 "Cached:"，含 SReclaimable
            $cachedKb = $get('Cached') + $get('SReclaimable');
            $availKb = $freeKb + $buffersKb + $cachedKb;
            $mem['total'] = $totalKb * 1024;
            $mem['available'] = $availKb * 1024;
            $mem['used'] = max(0, $mem['total'] - $mem['available']);
            $mem['percent'] = $mem['total'] > 0 ? round($mem['used'] / $mem['total'] * 100, 1) : null;
        }

        // —— 磁盘（根分区，与宝塔一致）——
        $diskFree = function_exists('disk_free_space') ? (float) @disk_free_space('/') : 0.0;
        $diskTotal = function_exists('disk_total_space') ? (float) @disk_total_space('/') : 0.0;
        $diskUsed = max(0, $diskTotal - $diskFree);
        $diskPercent = $diskTotal > 0 ? round($diskUsed / $diskTotal * 100, 1) : null;

        // —— 系统负载 ——
        $load = null;
        if (function_exists('sys_getloadavg')) {
            $lv = @sys_getloadavg();
            if (is_array($lv) && count($lv) >= 3) {
                $load = [
                    'm1' => round((float) $lv[0], 2),
                    'm5' => round((float) $lv[1], 2),
                    'm15' => round((float) $lv[2], 2),
                ];
            }
        }

        return [
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'server_software' => (string) ($_SERVER['SERVER_SOFTWARE'] ?? '未知'),
            'os' => php_uname('s') . ' ' . php_uname('r'),
            'uptime' => $uptime,
            'memory_limit' => ini_get('memory_limit') ?: '未知',
            'cpu' => [
                'model' => $cpuModel,
                'cores' => $cpuCores,
                'percent' => $cpuPercent,
            ],
            'memory' => [
                'total' => $formatBytes($mem['total']),
                'available' => $formatBytes($mem['available']),
                'used' => $formatBytes($mem['used']),
                'percent' => $mem['percent'],
            ],
            'disk' => [
                'total' => $formatBytes($diskTotal),
                'free' => $formatBytes($diskFree),
                'used' => $formatBytes($diskUsed),
                'percent' => $diskPercent,
            ],
            'load' => $load,
        ];
    }

    /**
     * 读取 /proc/stat 两次（间隔 200ms），计算 CPU 使用率百分比。
     * 只统计 user+nice+system+irq+softirq+steal 为"忙"，idle+iowait 视为"闲"。
     */
    private function sampleCpuUsage(): ?float
    {
        $read = function (): ?array {
            $raw = $this->readSystemFile('/proc/stat');
            if ($raw === null || !preg_match('/^cpu\s+(.+)/m', $raw, $m)) {
                return null;
            }
            $vals = array_map('floatval', preg_split('/\s+/', trim($m[1])));
            // Linux jiffies: user nice system idle iowait irq softirq steal.
            // Guest fields are excluded because they are already counted in user/nice.
            // Common panel metric: idle + iowait is idle time.
            $idle = ($vals[3] ?? 0) + ($vals[4] ?? 0);
            $busy = ($vals[0] ?? 0) + ($vals[1] ?? 0) + ($vals[2] ?? 0)
                + ($vals[5] ?? 0) + ($vals[6] ?? 0) + ($vals[7] ?? 0);
            return ['idle' => $idle, 'busy' => $busy, 'total' => $idle + $busy];
        };

        $a = $read();
        if ($a === null) {
            return null;
        }
        usleep(1000000);
        $b = $read();
        if ($b === null) {
            return null;
        }

        $totalDelta = $b['total'] - $a['total'];
        $idleDelta = $b['idle'] - $a['idle'];
        if ($totalDelta <= 0) {
            return null;
        }
        return round(max(0, min(100, ($totalDelta - $idleDelta) / $totalDelta * 100)), 1);
    }

    /**
     * 读取系统文件前先尊重 open_basedir，避免 is_file/readable 直接触发 warning。
     */
    private function readSystemFile(string $path): ?string
    {
        if (!$this->isPathAllowedByOpenBasedir($path)) {
            return null;
        }

        if (!@is_file($path) || !@is_readable($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        return is_string($raw) ? $raw : null;
    }

    private function isPathAllowedByOpenBasedir(string $path): bool
    {
        $openBasedir = trim((string) ini_get('open_basedir'));
        if ($openBasedir === '') {
            return true;
        }

        $target = $this->normalizePathForCompare($path);
        foreach (explode(PATH_SEPARATOR, $openBasedir) as $base) {
            $base = trim($base);
            if ($base === '') {
                continue;
            }

            $allowed = $this->normalizePathForCompare($base);
            if ($allowed === '') {
                continue;
            }

            $allowedPrefix = rtrim($allowed, '/') . '/';
            if ($allowed === '/' || $target === $allowed || strncmp($target, $allowedPrefix, strlen($allowedPrefix)) === 0) {
                return true;
            }
        }

        return false;
    }

    private function normalizePathForCompare(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if ($path === '.') {
            $path = getcwd() ?: '.';
        }

        $path = preg_replace('#/+#', '/', $path) ?: $path;
        if (DIRECTORY_SEPARATOR === '\\') {
            $path = strtolower($path);
        }

        return rtrim($path, '/') ?: '/';
    }

    /**
     * AJAX 接口：返回服务器实时指标 JSON（供概览页每秒轮询）。
     */
    public function serverMetrics(): void
    {
        $this->requireLogin();
        $this->startSession();

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');

        $info = $this->serverInfo();

        $cpu = $info['cpu'] ?? [];
        $memory = $info['memory'] ?? [];
        $disk = $info['disk'] ?? [];

        echo json_encode([
            'cpu' => [
                'model' => $cpu['model'] ?? '未知',
                'cores' => $cpu['cores'] ?? 0,
                'percent' => $cpu['percent'],
            ],
            'memory' => [
                'total' => $memory['total'] ?? '0',
                'available' => $memory['available'] ?? '0',
                'used' => $memory['used'] ?? '0',
                'percent' => $memory['percent'],
            ],
            'disk' => [
                'total' => $disk['total'] ?? '0',
                'free' => $disk['free'] ?? '0',
                'used' => $disk['used'] ?? '0',
                'percent' => $disk['percent'],
            ],
            'load' => $info['load'] ?? null,
            'ts' => time(),
        ], JSON_UNESCAPED_UNICODE);
    }

    public function checkUpdate(): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse([
                'success' => false,
                'type' => 'error',
                'message' => '请使用 POST 检查更新',
                'beijing_time' => $this->beijingTime(),
            ], 405);
            return;
        }

        $currentVersion = $this->currentBlogVersion();
        $updateUrl = trim((string) Config::get('app.update_check_url', ''));

        if ($updateUrl === '') {
            $this->recordAdminActivity('update_check_failed', $this->currentAdmin(), '', 'warning', '检查更新失败：未配置更新检查地址。', [
                'resource_type' => 'system_update',
                'current_version' => $currentVersion,
            ]);
            $this->jsonResponse([
                'success' => false,
                'type' => 'error',
                'message' => '未配置更新检查地址，请设置 APP_UPDATE_CHECK_URL',
                'current_version' => $currentVersion,
                'beijing_time' => $this->beijingTime(),
            ], 422);
            return;
        }

        if (!$this->isValidUpdateCheckUrl($updateUrl)) {
            $this->recordAdminActivity('update_check_failed', $this->currentAdmin(), '', 'warning', '检查更新失败：更新检查地址不合法。', [
                'resource_type' => 'system_update',
                'current_version' => $currentVersion,
                'update_url' => $updateUrl,
            ]);
            $this->jsonResponse([
                'success' => false,
                'type' => 'error',
                'message' => '更新检查地址不合法，请使用 http 或 https 地址',
                'current_version' => $currentVersion,
                'beijing_time' => $this->beijingTime(),
            ], 422);
            return;
        }

        try {
            $remote = $this->requestUpdateInfo($updateUrl, [
                'action' => 'check_update',
                'version' => $currentVersion,
                'request_time' => $this->updateRequestTime(),
            ]);
            $latestVersion = trim((string) ($remote['latest_version'] ?? $remote['version'] ?? ''));
            if ($latestVersion === '') {
                throw new \RuntimeException('更新服务器返回缺少 latest_version');
            }

            $updateAvailable = $this->resolveUpdateAvailable($remote, $currentVersion, $latestVersion);
            $releaseUrl = $this->updateReleaseUrl($remote);
            $remoteMessage = trim((string) ($remote['message'] ?? ''));
            $message = $remoteMessage !== ''
                ? $remoteMessage
                : ($updateAvailable ? ('发现新版本 ' . $latestVersion) : '当前已是最新版本');

            $this->recordAdminActivity('update_check', $this->currentAdmin(), '', 'success', '检查系统更新：' . $message . '。', [
                'resource_type' => 'system_update',
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'update_available' => $updateAvailable,
            ]);

            $this->jsonResponse([
                'success' => true,
                'type' => $updateAvailable ? 'warning' : 'success',
                'message' => $message,
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'update_available' => $updateAvailable,
                'release_url' => $releaseUrl,
                'beijing_time' => $this->beijingTime(),
            ]);
        } catch (\Throwable $exception) {
            $this->recordAdminActivity('update_check_failed', $this->currentAdmin(), '', 'warning', '检查更新失败：' . $exception->getMessage(), [
                'resource_type' => 'system_update',
                'current_version' => $currentVersion,
                'error' => $exception->getMessage(),
            ]);

            $this->jsonResponse([
                'success' => false,
                'type' => 'error',
                'message' => '检查更新失败：' . $exception->getMessage(),
                'current_version' => $currentVersion,
                'beijing_time' => $this->beijingTime(),
            ], 502);
        }
    }

    public function updateNotes(): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse([
                'success' => false,
                'type' => 'error',
                'message' => '请使用 POST 获取更新说明',
                'beijing_time' => $this->beijingTime(),
            ], 405);
            return;
        }

        $targetVersion = trim((string) ($_POST['version'] ?? $_POST['latest_version'] ?? ''));
        $updateUrl = trim((string) Config::get('app.update_check_url', ''));

        if (!$this->isSafeUpdateVersion($targetVersion)) {
            $this->jsonResponse([
                'success' => false,
                'type' => 'error',
                'message' => '更新版本号不合法',
                'beijing_time' => $this->beijingTime(),
            ], 422);
            return;
        }

        if ($updateUrl === '' || !$this->isValidUpdateCheckUrl($updateUrl)) {
            $this->jsonResponse([
                'success' => false,
                'type' => 'error',
                'message' => $updateUrl === '' ? '未配置更新检查地址' : '更新检查地址不合法',
                'latest_version' => $targetVersion,
                'beijing_time' => $this->beijingTime(),
            ], 422);
            return;
        }

        if ($this->versionForCompare($targetVersion) === '1.0.0') {
            $this->jsonResponse([
                'success' => true,
                'type' => 'success',
                'message' => '暂无更新说明',
                'latest_version' => $targetVersion,
                'update_notes' => [],
                'beijing_time' => $this->beijingTime(),
            ]);
            return;
        }

        try {
            $remote = $this->requestUpdateInfo($updateUrl, [
                'action' => 'get_update_notes',
                'version' => $targetVersion,
                'request_time' => $this->updateRequestTime(),
            ]);
            $notes = $this->normalizeUpdateNotes($remote);
            $message = trim((string) ($remote['message'] ?? ''));
            if ($message === '') {
                $message = '更新说明获取成功';
            }

            $this->recordAdminActivity('update_notes', $this->currentAdmin(), '', 'success', '获取更新说明：' . $targetVersion . '。', [
                'resource_type' => 'system_update',
                'latest_version' => $targetVersion,
            ]);

            $this->jsonResponse([
                'success' => true,
                'type' => 'success',
                'message' => $message,
                'latest_version' => $targetVersion,
                'update_notes' => $notes,
                'release_url' => $this->updateReleaseUrl($remote),
                'beijing_time' => $this->beijingTime(),
            ]);
        } catch (\Throwable $exception) {
            $this->recordAdminActivity('update_notes_failed', $this->currentAdmin(), '', 'warning', '获取更新说明失败：' . $exception->getMessage(), [
                'resource_type' => 'system_update',
                'latest_version' => $targetVersion,
                'error' => $exception->getMessage(),
            ]);

            $this->jsonResponse([
                'success' => false,
                'type' => 'error',
                'message' => '获取更新说明失败：' . $exception->getMessage(),
                'latest_version' => $targetVersion,
                'beijing_time' => $this->beijingTime(),
            ], 502);
        }
    }

    public function applyUpdate(): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse([
                'success' => false,
                'type' => 'error',
                'message' => '请使用 POST 执行更新',
                'beijing_time' => $this->beijingTime(),
            ], 405);
            return;
        }

        $this->jsonResponse([
            'success' => false,
            'type' => 'warning',
            'message' => '后台自动覆盖更新已关闭，请前往 GitHub 下载新版后手动更新。',
            'release_url' => $this->updateReleaseUrl(),
            'beijing_time' => $this->beijingTime(),
        ], 410);
    }

    private function currentBlogVersion(): string

    {
        $version = trim((string) Config::get('app.version', '1.0.0'));
        return $version !== '' ? $version : '1.0.0';
    }

    private function isValidUpdateCheckUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }

    private function updateReleaseUrl(array $remote = []): string
    {
        $url = trim((string) ($remote['release_url'] ?? $remote['github_url'] ?? $remote['repo_url'] ?? ''));
        if ($url !== '' && $this->isValidUpdateCheckUrl($url)) {
            return $url;
        }

        $fallback = trim((string) Config::get('app.update_release_url', 'https://github.com'));
        return $this->isValidUpdateCheckUrl($fallback) ? $fallback : 'https://github.com';
    }

    private function updateRequestTime(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai')))->format(DATE_ATOM);
    }

    private function beijingTime(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Shanghai')))->format('Y-m-d H:i:s');
    }

    private function normalizeUpdateNotes(array $remote): array
    {
        $source = $remote['notes'] ?? $remote['update_notes'] ?? null;
        if ($source === null && array_key_exists('changelog', $remote)) {
            $source = $remote['changelog'];
        }

        $items = [];
        if (is_array($source)) {
            foreach ($source as $item) {
                if (is_array($item)) {
                    $item = $item['text'] ?? $item['content'] ?? $item['note'] ?? '';
                }
                if (is_scalar($item)) {
                    $items[] = (string) $item;
                }
            }
        } elseif (is_scalar($source)) {
            $items = preg_split('/\r\n|\r|\n/', (string) $source) ?: [];
        }

        $notes = [];
        foreach ($items as $item) {
            $note = trim((string) $item);
            if ($note === '') {
                continue;
            }
            if (strlen($note) > 800) {
                $note = substr($note, 0, 800) . '...';
            }
            $notes[] = $note;
            if (count($notes) >= 30) {
                break;
            }
        }

        return $notes;
    }

    private function isSafeUpdateVersion(string $version): bool
    {
        return $version !== ''
            && strlen($version) <= 32
            && preg_match('/^[0-9A-Za-z][0-9A-Za-z._+\-]*$/', $version) === 1;
    }

    private function installUpdatePackage(string $downloadUrl, string $targetVersion): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('服务器缺少 ZipArchive 扩展，无法自动解压更新包');
        }

        @set_time_limit(180);

        $projectRoot = realpath(dirname(__DIR__, 2));
        if ($projectRoot === false || !is_dir($projectRoot)) {
            throw new \RuntimeException('无法定位项目根目录');
        }
        $tmpBase = $this->resolveUpdateTempBase($projectRoot);

        $workDir = $tmpBase . DIRECTORY_SEPARATOR . 'update-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $extractDir = $workDir . DIRECTORY_SEPARATOR . 'extract';
        $zipPath = $workDir . DIRECTORY_SEPARATOR . 'package.zip';
        $this->ensureDirectory($extractDir);

        try {
            $bytes = $this->downloadUpdatePackage($downloadUrl, $zipPath);

            $zip = new \ZipArchive();
            $openResult = $zip->open($zipPath);
            if ($openResult !== true) {
                throw new \RuntimeException('更新包不是有效 zip 文件');
            }

            try {
                $this->assertSafeZipArchive($zip);
                if (!$zip->extractTo($extractDir)) {
                    throw new \RuntimeException('更新包解压失败');
                }
            } finally {
                $zip->close();
            }

            $sourceDir = $this->resolveUpdateSourceDirectory($extractDir);
            $this->assertSafeExtractedTree($sourceDir);
            $this->assertCompleteUpdatePackage($sourceDir);
            $this->assertUpdateTargetWritable($projectRoot, $sourceDir);

            $stats = [
                'files' => 0,
                'directories' => 0,
                'removed' => 0,
                'skipped' => 0,
            ];
            $this->prepareUpdateTargetTree($projectRoot);
            $this->copyUpdateTree($sourceDir, $projectRoot, $sourceDir, $stats);

            return [
                'bytes' => $bytes,
                'files' => $stats['files'],
                'directories' => $stats['directories'],
                'removed' => $stats['removed'],
                'skipped' => $stats['skipped'],
                'version' => $targetVersion,
            ];
        } finally {
            $this->deleteUpdateWorkDirectory($workDir, $tmpBase);
        }
    }

    private function downloadUpdatePackage(string $url, string $targetPath): int
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", [
                    'Accept: application/zip, application/octet-stream, */*',
                    'User-Agent: Z-Blog Update Installer/' . $this->currentBlogVersion(),
                ]) . "\r\n",
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);

        $input = @fopen($url, 'rb', false, $context);
        if (!is_resource($input)) {
            throw new \RuntimeException('无法下载更新包');
        }

        $statusCode = $this->httpStatusCode($http_response_header ?? []);
        if ($statusCode >= 400) {
            fclose($input);
            throw new \RuntimeException('更新包下载返回 HTTP ' . $statusCode);
        }

        $output = @fopen($targetPath, 'wb');
        if (!is_resource($output)) {
            fclose($input);
            throw new \RuntimeException('无法写入更新包临时文件');
        }

        $bytes = 0;
        try {
            while (!feof($input)) {
                $chunk = fread($input, 1024 * 1024);
                if ($chunk === false) {
                    throw new \RuntimeException('读取更新包失败');
                }
                if ($chunk === '') {
                    continue;
                }

                $bytes += strlen($chunk);
                if ($bytes > self::UPDATE_PACKAGE_MAX_BYTES) {
                    throw new \RuntimeException('更新包超过允许大小');
                }

                if (fwrite($output, $chunk) === false) {
                    throw new \RuntimeException('写入更新包失败');
                }
            }
        } finally {
            fclose($input);
            fclose($output);
        }

        if ($bytes <= 0) {
            throw new \RuntimeException('更新包为空');
        }

        return $bytes;
    }

    private function assertSafeZipArchive(\ZipArchive $zip): void
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            $normalized = str_replace('\\', '/', $name);

            if (
                $normalized === ''
                || str_contains($normalized, "\0")
                || str_starts_with($normalized, '/')
                || preg_match('/^[A-Za-z]:/', $normalized)
                || preg_match('#(^|/)\.\.(/|$)#', $normalized)
            ) {
                throw new \RuntimeException('更新包包含不安全路径：' . $name);
            }
        }
    }

    private function resolveUpdateSourceDirectory(string $extractDir): string
    {
        $entries = array_values(array_filter(scandir($extractDir) ?: [], static function (string $entry): bool {
            return $entry !== '.' && $entry !== '..' && $entry !== '__MACOSX';
        }));

        if (count($entries) === 1) {
            $candidate = $extractDir . DIRECTORY_SEPARATOR . $entries[0];
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return $extractDir;
    }

    private function assertSafeExtractedTree(string $sourceDir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new \RuntimeException('更新包包含不允许的符号链接');
            }
        }
    }

    private function assertCompleteUpdatePackage(string $sourceDir): void
    {
        $required = ['app', 'config', 'public', 'resources'];
        foreach ($required as $name) {
            if (!is_dir($sourceDir . DIRECTORY_SEPARATOR . $name)) {
                throw new \RuntimeException('更新包不是完整项目包，缺少 ' . $name . ' 目录');
            }
        }
    }

    private function assertUpdateTargetWritable(string $projectRoot, string $sourceDir): void
    {
        $this->assertProjectRootWritable($projectRoot);
        $this->assertUpdateTargetsWritable($projectRoot, $sourceDir, $sourceDir);
    }

    private function assertProjectRootWritable(string $projectRoot): void
    {
        $this->makeUpdatePathWritable($projectRoot, 0777);
        $marker = $projectRoot . DIRECTORY_SEPARATOR . '.update-write-test-' . bin2hex(random_bytes(6)) . '.tmp';

        $handle = @fopen($marker, 'wb');
        if (!is_resource($handle)) {
            throw new \RuntimeException($this->updatePermissionMessage(
                'PHP 运行用户无权写入项目根目录，无法创建更新替换文件',
                $projectRoot
            ));
        }

        fclose($handle);
        @unlink($marker);
    }

    private function assertUpdateTargetsWritable(string $projectRoot, string $sourceDir, string $sourceRoot): void
    {
        $entries = @scandir($sourceDir);
        if ($entries === false) {
            throw new \RuntimeException('无法读取更新包目录');
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $sourcePath = $sourceDir . DIRECTORY_SEPARATOR . $entry;
            $relativePath = $this->relativeUpdatePath($sourcePath, $sourceRoot);
            if ($relativePath === '' || $this->isProtectedUpdatePath($relativePath)) {
                continue;
            }

            $targetPath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $parent = dirname($targetPath);
            $this->makeUpdatePathWritable($parent, 0777);

            if (is_dir($sourcePath)) {
                if (file_exists($targetPath) && !is_dir($targetPath)) {
                    $this->assertReplacePathWritable($targetPath, $relativePath);
                    continue;
                }

                if (is_dir($targetPath)) {
                    $this->makeUpdatePathWritable($targetPath, 0777);
                }
                $this->assertUpdateTargetsWritable($projectRoot, $sourcePath, $sourceRoot);
                continue;
            }

            if (!is_file($sourcePath)) {
                continue;
            }

            if (file_exists($targetPath) || is_link($targetPath)) {
                $this->assertReplacePathWritable($targetPath, $relativePath);
            } elseif (!is_dir($parent) || !is_writable($parent)) {
                throw new \RuntimeException($this->updatePermissionMessage(
                    '目标目录不可写，无法创建新文件：' . $relativePath,
                    $parent
                ));
            }
        }
    }

    private function assertReplacePathWritable(string $targetPath, string $relativePath): void
    {
        $parent = dirname($targetPath);
        $this->makeUpdatePathWritable($parent, 0777);
        $this->makeUpdatePathWritable($targetPath, is_dir($targetPath) ? 0777 : 0666);

        if ((is_file($targetPath) || is_link($targetPath)) && is_writable($targetPath)) {
            return;
        }

        if (is_writable($parent)) {
            return;
        }

        throw new \RuntimeException($this->updatePermissionMessage(
            '目标文件不可写且所在目录不可写，无法覆盖：' . $relativePath,
            $targetPath
        ));
    }

    private function updatePermissionMessage(string $message, string $path): string
    {
        return $message
            . '；路径：' . $path
            . '；PHP 运行用户：' . $this->phpRuntimeUser()
            . '。请将项目目录属主改为 PHP 运行用户，或给项目根目录及文件写权限后重试。';
    }

    private function phpRuntimeUser(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $user = @posix_getpwuid((int) posix_geteuid());
            if (is_array($user) && !empty($user['name'])) {
                return (string) $user['name'];
            }
        }

        $candidates = [get_current_user(), getenv('USER'), getenv('USERNAME')];
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '未知';
    }

    private function copyUpdateTree(string $sourceDir, string $targetRoot, string $sourceRoot, array &$stats): void
    {
        $entries = scandir($sourceDir);
        if ($entries === false) {
            throw new \RuntimeException('无法读取更新包目录');
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $sourcePath = $sourceDir . DIRECTORY_SEPARATOR . $entry;
            $relativePath = $this->relativeUpdatePath($sourcePath, $sourceRoot);
            if ($relativePath === '' || $this->isProtectedUpdatePath($relativePath)) {
                $stats['skipped']++;
                continue;
            }

            $targetPath = $targetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            if (is_dir($sourcePath)) {
                if (is_file($targetPath) || is_link($targetPath)) {
                    $this->removeUpdateTargetPath($targetPath, $targetRoot, $relativePath, $stats);
                }
                $this->ensureDirectory($targetPath);
                $this->makeUpdatePathWritable($targetPath, 0777);
                $stats['directories']++;
                $this->copyUpdateTree($sourcePath, $targetRoot, $sourceRoot, $stats);
                continue;
            }

            if (!is_file($sourcePath)) {
                $stats['skipped']++;
                continue;
            }


            $parent = dirname($targetPath);
            $this->ensureDirectory($parent);
            $this->makeUpdatePathWritable($parent, 0777);
            if (is_dir($targetPath) || is_link($targetPath)) {
                $this->removeUpdateTargetPath($targetPath, $targetRoot, $relativePath, $stats);
            }
            $this->copyUpdateFile($sourcePath, $targetPath, $relativePath);
            $stats['files']++;
        }
    }

    private function copyUpdateFile(string $sourcePath, string $targetPath, string $relativePath): void
    {
        $parent = dirname($targetPath);
        $this->ensureDirectory($parent);
        $this->makeUpdatePathWritable($parent, 0777);

        if (file_exists($targetPath) || is_link($targetPath)) {
            $this->makeUpdatePathWritable($targetPath, 0666);
        }

        if (@copy($sourcePath, $targetPath)) {
            $this->applyUpdateFileMode($sourcePath, $targetPath);
            return;
        }

        if ($this->writeUpdateFileDirectly($sourcePath, $targetPath)) {
            $this->applyUpdateFileMode($sourcePath, $targetPath);
            return;
        }

        $tempPath = $this->temporaryUpdatePath($parent, basename($targetPath));
        if (!@copy($sourcePath, $tempPath)) {
            throw new \RuntimeException('目标目录不可写，无法创建替换文件：' . $relativePath);
        }
        $this->applyUpdateFileMode($sourcePath, $tempPath);

        if (file_exists($targetPath) || is_link($targetPath)) {
            $this->makeUpdatePathWritable($targetPath, 0666);
            if (!@rename($tempPath, $targetPath)) {
                clearstatcache(true, $targetPath);
                if (!@unlink($targetPath)) {
                    @unlink($tempPath);
                    throw new \RuntimeException('目标文件不可删除，无法覆盖：' . $relativePath);
                }

                clearstatcache(true, $targetPath);
                if (!@rename($tempPath, $targetPath)) {
                    if (!@copy($tempPath, $targetPath)) {
                        @unlink($tempPath);
                        throw new \RuntimeException('替换文件失败，无法覆盖：' . $relativePath);
                    }
                    @unlink($tempPath);
                }
            }
        } elseif (!@rename($tempPath, $targetPath)) {
            if (!@copy($tempPath, $targetPath)) {
                @unlink($tempPath);
                throw new \RuntimeException('写入新文件失败：' . $relativePath);
            }
            @unlink($tempPath);
        }

        $this->applyUpdateFileMode($sourcePath, $targetPath);
    }

    private function writeUpdateFileDirectly(string $sourcePath, string $targetPath): bool
    {
        $input = @fopen($sourcePath, 'rb');
        if (!is_resource($input)) {
            return false;
        }

        $output = @fopen($targetPath, 'wb');
        if (!is_resource($output)) {
            fclose($input);
            return false;
        }

        try {
            while (!feof($input)) {
                $chunk = fread($input, 1024 * 1024);
                if ($chunk === false) {
                    return false;
                }
                if ($chunk !== '' && fwrite($output, $chunk) === false) {
                    return false;
                }
            }

            return true;
        } finally {
            fclose($input);
            fclose($output);
        }
    }

    private function temporaryUpdatePath(string $parent, string $basename): string
    {
        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $basename) ?: 'file';
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $path = $parent . DIRECTORY_SEPARATOR . '.update-' . $safeName . '-' . bin2hex(random_bytes(6)) . '.tmp';
            if (!file_exists($path)) {
                return $path;
            }
        }

        throw new \RuntimeException('无法生成更新临时文件名');
    }

    private function applyUpdateFileMode(string $sourcePath, string $targetPath): void
    {
        $sourceMode = fileperms($sourcePath);
        $mode = is_int($sourceMode) && ($sourceMode & 0111) !== 0 ? 0775 : 0664;
        @chmod($targetPath, $mode);
        clearstatcache(true, $targetPath);
    }

    private function prepareUpdateTargetTree(string $targetRoot): void
    {
        $this->makeUpdatePathWritable($targetRoot, 0777);
        $this->prepareUpdateTargetDirectory($targetRoot, $targetRoot);
    }

    private function prepareUpdateTargetDirectory(string $directory, string $targetRoot): void
    {
        $entries = @scandir($directory);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            $relativePath = $this->relativeUpdatePath($path, $targetRoot);
            if ($this->isProtectedUpdatePath($relativePath)) {
                continue;
            }

            if (is_dir($path) && !is_link($path)) {
                $this->makeUpdatePathWritable($path, 0777);
                $this->prepareUpdateTargetDirectory($path, $targetRoot);
                continue;
            }

            $this->makeUpdatePathWritable($path, 0666);
        }
    }

    private function makeUpdatePathWritable(string $path, int $mode): void
    {
        if (!file_exists($path) && !is_link($path)) {
            return;
        }

        @chmod($path, $mode);
        clearstatcache(true, $path);

        if (!$this->isUpdatePathWritableEnough($path)) {
            $this->chmodUpdatePathWithShell($path, $mode);
            clearstatcache(true, $path);
        }
    }

    private function isUpdatePathWritableEnough(string $path): bool
    {
        if (is_dir($path)) {
            return is_readable($path) && is_writable($path) && is_executable($path);
        }

        if (is_file($path) || is_link($path)) {
            return is_writable($path);
        }

        return true;
    }

    private function chmodUpdatePathWithShell(string $path, int $mode): void
    {
        if (DIRECTORY_SEPARATOR === '\\' || !function_exists('exec')) {
            return;
        }

        $modeString = sprintf('%04o', $mode & 0777);
        $command = 'chmod ' . $modeString . ' ' . escapeshellarg($path) . ' 2>/dev/null';
        @exec($command);
    }

    private function removeUpdateTargetPath(string $targetPath, string $targetRoot, string $relativePath, array &$stats): void
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '' || $this->isProtectedUpdatePath($relativePath)) {
            $stats['skipped']++;
            return;
        }

        $normalizedRoot = rtrim(str_replace('\\', '/', $targetRoot), '/') . '/';
        $normalizedTarget = str_replace('\\', '/', $targetPath);
        if (strncmp($normalizedTarget, $normalizedRoot, strlen($normalizedRoot)) !== 0) {
            throw new \RuntimeException('拒绝替换项目目录外的路径');
        }

        if (is_link($targetPath) || is_file($targetPath)) {
            $this->makeUpdatePathWritable(dirname($targetPath), 0777);
            $this->makeUpdatePathWritable($targetPath, 0666);
            if (!@unlink($targetPath)) {
                throw new \RuntimeException('替换旧文件失败：' . $relativePath);
            }
            $stats['removed']++;
            return;
        }

        if (!is_dir($targetPath)) {
            return;
        }

        $entries = scandir($targetPath);
        if ($entries === false) {
            throw new \RuntimeException('无法读取待替换目录：' . $relativePath);
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $childRelative = $relativePath . '/' . $entry;
            if ($this->isProtectedUpdatePath($childRelative)) {
                $stats['skipped']++;
                continue;
            }

            $this->removeUpdateTargetPath(
                $targetPath . DIRECTORY_SEPARATOR . $entry,
                $targetRoot,
                $childRelative,
                $stats
            );
        }

        $this->makeUpdatePathWritable($targetPath, 0777);
        $this->makeUpdatePathWritable(dirname($targetPath), 0777);
        if (!@rmdir($targetPath) && is_dir($targetPath)) {
            throw new \RuntimeException('无法替换包含受保护内容的目录：' . $relativePath);
        }
        $stats['removed']++;
    }

    private function relativeUpdatePath(string $path, string $root): string
    {
        $path = str_replace('\\', '/', $path);
        $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
        if (strncmp($path, $root, strlen($root)) !== 0) {
            throw new \RuntimeException('更新包路径解析失败');
        }

        $relative = ltrim(substr($path, strlen($root)), '/');
        if ($relative === '' || str_contains($relative, "\0") || preg_match('#(^|/)\.\.(/|$)#', $relative)) {
            throw new \RuntimeException('更新包路径不合法');
        }

        return $relative;
    }

    private function isProtectedUpdatePath(string $relativePath): bool
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        foreach (self::UPDATE_PROTECTED_FILES as $file) {
            if ($relativePath === $file) {
                return true;
            }
        }

        $prefixTarget = rtrim($relativePath, '/') . '/';
        foreach (self::UPDATE_PROTECTED_PATH_PREFIXES as $prefix) {
            if ($prefixTarget === $prefix || str_starts_with($relativePath, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function resolveUpdateTempBase(string $projectRoot): string
    {
        $preferred = $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'update-tmp';
        $hash = substr(hash('sha256', $projectRoot), 0, 12);
        $systemTmp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        $candidates = [$preferred];

        if ($systemTmp !== '') {
            $candidates[] = $systemTmp . DIRECTORY_SEPARATOR . 'zblog-update-tmp-' . $hash;
        }

        $lastError = '';
        foreach ($candidates as $candidate) {
            try {
                $this->ensureDirectory($candidate);
            } catch (\RuntimeException $exception) {
                $lastError = $exception->getMessage();
                continue;
            }

            if (is_dir($candidate) && is_writable($candidate)) {
                return $candidate;
            }

            $lastError = '更新临时目录不可写：' . $candidate;
        }

        throw new \RuntimeException($lastError !== '' ? $lastError : '无法创建可写更新临时目录');
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        $parent = dirname($directory);
        if ($parent !== $directory && !is_dir($parent)) {
            $this->ensureDirectory($parent);
        }

        if (is_dir($parent) && !is_writable($parent)) {
            $this->makeUpdatePathWritable($parent, 0777);
        }

        if (is_dir($parent) && !is_writable($parent)) {
            throw new \RuntimeException('父目录不可写，无法创建目录：' . $directory);
        }

        if (!@mkdir($directory, 0777) && !is_dir($directory)) {
            throw new \RuntimeException('目录创建失败：' . $directory);
        }
    }

    private function deleteUpdateWorkDirectory(string $directory, string $allowedBase): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $base = realpath($allowedBase);
        $target = realpath($directory);
        if ($base === false || $target === false) {
            return;
        }

        $base = rtrim(str_replace('\\', '/', $base), '/') . '/';
        $targetNormalized = rtrim(str_replace('\\', '/', $target), '/') . '/';
        if ($targetNormalized === $base || strncmp($targetNormalized, $base, strlen($base)) !== 0) {
            throw new \RuntimeException('拒绝清理非更新临时目录');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($target, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($target);
    }

    private function requestUpdateInfo(string $url, array $fields): array

    {
        $content = http_build_query($fields, '', '&', PHP_QUERY_RFC3986);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
                    'Accept: application/json',
                    'User-Agent: Z-Blog Update Checker/' . $this->currentBlogVersion(),
                ]) . "\r\n",
                'content' => $content,
                'timeout' => 8,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new \RuntimeException('无法连接更新服务器');
        }

        $statusCode = $this->httpStatusCode($http_response_header ?? []);
        if ($statusCode >= 400) {
            throw new \RuntimeException('更新服务器返回 HTTP ' . $statusCode);
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new \RuntimeException('更新服务器返回不是有效 JSON');
        }

        $successValue = $data['success'] ?? $data['ok'] ?? null;
        if ($successValue !== null && $this->booleanFromMixed($successValue) === false) {
            $message = trim((string) ($data['message'] ?? '更新服务器拒绝请求'));
            throw new \RuntimeException($message !== '' ? $message : '更新服务器拒绝请求');
        }

        return $data;
    }

    private function httpStatusCode(array $headers): int
    {
        $statusLine = (string) ($headers[0] ?? '');
        if (preg_match('/\s(\d{3})\s?/', $statusLine, $matches)) {
            return (int) $matches[1];
        }

        return 200;
    }

    private function resolveUpdateAvailable(array $remote, string $currentVersion, string $latestVersion): bool
    {
        if (array_key_exists('update_available', $remote)) {
            return $this->booleanFromMixed($remote['update_available']) === true;
        }

        if (array_key_exists('is_latest', $remote)) {
            return $this->booleanFromMixed($remote['is_latest']) !== true;
        }

        return version_compare($this->versionForCompare($latestVersion), $this->versionForCompare($currentVersion), '>');
    }

    private function versionForCompare(string $version): string
    {
        $version = trim($version);
        $version = preg_replace('/^[vV]\s*/', '', $version) ?: $version;
        return $version !== '' ? $version : '0.0.0';
    }

    private function booleanFromMixed(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            return is_bool($parsed) ? $parsed : null;
        }

        return null;
    }

    public function login(): void
    {
        $this->startSession();
        AdminLoginAttempt::createTable();
        AdminActivityLog::createTable();

        $loginNotice = $this->pullAdminLoginNotice();
        $loginNoticeTitle = '登录状态异常';
        $noticeCode = (string) ($_GET['notice'] ?? '');
        $noticeCookie = (string) ($_COOKIE['admin_login_notice'] ?? '');
        if ($loginNotice === null && ($noticeCode === 'data' || $noticeCookie === 'data')) {
            $loginNotice = '数据异常，请重新登录';
        }
        if ($noticeCookie !== '') {
            $this->clearAdminLoginNoticeCookie();
        }

        $invalidReason = null;
        if ($this->isLoggedIn($invalidReason)) {
            $this->redirect('/admin');
            return;
        }

        if ($invalidReason === 'client_changed') {
            $loginNotice = '登录环境已变化，请重新登录';
            $this->clearAdminSession();
        } elseif ($invalidReason === 'data') {
            $loginNotice = '数据异常，请重新登录';
            $this->clearAdminSession();
        }

        $error = '';
        $username = trim((string) ($_POST['username'] ?? ''));
        $ipAddress = $this->clientIp();
        $userAgent = $this->userAgent();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = (string) ($_POST['password'] ?? '');
            $lockStatus = AdminLoginAttempt::status($ipAddress);

            if ($lockStatus['locked']) {
                http_response_code(429);
                $error = '当前 IP 登录失败次数过多，请等待 ' . $this->formatLockRemaining($lockStatus['remaining_seconds']) . ' 后再试';
                $loginNoticeTitle = 'IP 已被锁定';
                $loginNotice = $error;
                $this->recordAdminActivity('login_blocked', null, $username, 'danger', '锁定期间继续尝试登录，已拒绝。', [
                    'remaining_seconds' => $lockStatus['remaining_seconds'],
                    'attempts' => $lockStatus['attempts'],
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ]);
            } elseif ($username === '' || $password === '') {
                $error = '请输入用户名和密码';
            } else {
                $admin = Admin::findByUsername($username);

                if ($admin !== null && password_verify($password, (string) $admin['password'])) {
                    AdminLoginAttempt::clear($ipAddress);

                    if (Admin::passwordNeedsRehash((string) $admin['password'])) {
                        Admin::updatePasswordHash((int) $admin['id'], Admin::hashPassword($password));
                    }

                    session_regenerate_id(true);

                    $_SESSION['admin'] = [
                        'id' => (int) $admin['id'],
                        'username' => $admin['username'],
                    ];
                    $_SESSION['admin_login_at'] = time();
                    $_SESSION['admin_expires_at'] = time() + self::ADMIN_SESSION_TTL;
                    $_SESSION['admin_last_regenerated_at'] = time();
                    $_SESSION['admin_ip_address'] = $ipAddress;
                    $_SESSION['admin_user_agent'] = $userAgent;

                    $this->recordAdminActivity('login_success', $admin, $username, 'success', '管理员登录成功。', [
                        'ip_address' => $ipAddress,
                        'user_agent' => $userAgent,
                    ]);

                    $this->redirect('/admin');
                    return;
                }

                $failureStatus = AdminLoginAttempt::recordFailure(
                    $ipAddress,
                    self::ADMIN_LOGIN_MAX_ATTEMPTS,
                    self::ADMIN_LOGIN_LOCK_SECONDS,
                    self::ADMIN_LOGIN_WINDOW_SECONDS
                );

                if ($failureStatus['locked']) {
                    http_response_code(429);
                    $error = '用户名或密码错误，当前 IP 已锁定 10 分钟';
                    $loginNoticeTitle = 'IP 已被锁定';
                    $loginNotice = '连续 3 次登录失败，当前 IP 已被锁定 10 分钟。';
                    $this->recordAdminActivity('login_ip_locked', null, $username, 'danger', '连续 3 次登录失败，IP 已锁定 10 分钟。', [
                        'attempts' => $failureStatus['attempts'],
                        'remaining_seconds' => $failureStatus['remaining_seconds'],
                        'ip_address' => $ipAddress,
                        'user_agent' => $userAgent,
                    ]);
                } else {
                    $remainingAttempts = max(0, self::ADMIN_LOGIN_MAX_ATTEMPTS - $failureStatus['attempts']);
                    $error = '用户名或密码错误，还可尝试 ' . $remainingAttempts . ' 次';
                    $this->recordAdminActivity('login_failed', null, $username, 'warning', '管理员登录失败。', [
                        'attempts' => $failureStatus['attempts'],
                        'remaining_attempts' => $remainingAttempts,
                        'ip_address' => $ipAddress,
                        'user_agent' => $userAgent,
                    ]);
                }
            }
        }

        $this->render('admin/login', [
            'error' => $error,
            'username' => $username,
            'loginNotice' => $loginNotice,
            'loginNoticeTitle' => $loginNoticeTitle,
            'siteSettings' => SiteContent::settings(),
        ]);
    }

    public function logout(): void
    {
        $this->startSession();

        $admin = isset($_SESSION['admin']) && is_array($_SESSION['admin']) ? $_SESSION['admin'] : null;
        $this->recordAdminActivity('logout', $admin, (string) ($admin['username'] ?? ''), 'info', '管理员退出登录。');
        $this->clearAdminSession();

        if ($this->wantsJson()) {
            $this->jsonResponse([
                'ok' => true,
                'type' => 'success',
                'message' => '已退出登录',
                'redirect' => '/admin/login',
            ]);
            return;
        }

        $this->redirect('/admin/login');
    }

    public function posts(): void
    {
        $this->requireLogin();

        $page = $this->paginateAdminList(Post::all(), '/admin/posts');

        $this->render('admin/posts/index', [
            'admin' => $_SESSION['admin'] ?? null,
            'posts' => $page['items'],
            'pagination' => $page['pagination'],
            'flash' => $this->pullFlash(),
        ]);
    }

    public function interactions(): void
    {
        $this->requireLogin();
        Comment::createTable();
        Like::createTable();
        PostInteractionLog::createTable();

        $page = $this->paginateAdminList(PostInteractionLog::allWithPosts(), '/admin/interactions');

        $this->render('admin/interactions/index', [
            'admin' => $_SESSION['admin'] ?? null,
            'events' => $page['items'],
            'pagination' => $page['pagination'],
        ]);
    }

    public function activity(): void
    {
        $this->requireLogin();
        AdminActivityLog::createTable();

        $page = $this->paginateAdminList(AdminActivityLog::all(), '/admin/activity');

        $this->render('admin/activity/index', [
            'admin' => $_SESSION['admin'] ?? null,
            'activities' => $page['items'],
            'pagination' => $page['pagination'],
        ]);
    }

    public function createPost(): void
    {
        $this->requireLogin();

        $errors = [];
        $post = $this->defaultPostData();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $post = $this->postDataFromRequest();
            $errors = $this->validatePostData($post);

            if (empty($errors)) {
                $post['slug'] = Post::generateSlug($post['title']);
                $postId = Post::create($post);

                $this->recordAdminActivity('post_create', $this->currentAdmin(), '', 'success', '创建文章《' . (string) $post['title'] . '》。', [
                    'resource_type' => 'post',
                    'resource_id' => $postId,
                    'title' => (string) $post['title'],
                    'snapshot' => $this->auditSnapshot($post, $this->postAuditFields()),
                ]);

                $this->flash('文章已创建');
                $this->redirect('/admin/posts');
                return;
            }

            $this->recordValidationFailedActivity('post_create_failed', '创建文章失败：表单校验未通过。', $errors, [
                'resource_type' => 'post',
                'title' => (string) ($post['title'] ?? ''),
            ]);

            if ($this->wantsJson()) {
                $this->jsonValidationFailure('文章创建失败，请检查表单内容', $errors);
                return;
            }
        }

        $this->render('admin/posts/create', [
            'admin' => $_SESSION['admin'] ?? null,
            'post' => $post,
            'categories' => Category::all(),
            'errors' => $errors,
        ]);
    }

    public function editPost(int $id): void
    {
        $this->requireLogin();

        $existingPost = Post::find($id);
        if ($existingPost === null) {
            $this->flash('文章不存在或已被删除', 'error');
            $this->redirect('/admin/posts');
            return;
        }

        $errors = [];
        $post = $existingPost;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $post = array_merge($existingPost, $this->postDataFromRequest());
            $errors = $this->validatePostData($post);

            if (empty($errors)) {
                $post['slug'] = Post::generateSlug($post['title'], $id);
                Post::update($id, $post);
                $updatedPost = Post::find($id) ?? $post;
                $changes = $this->auditChanges($existingPost, $updatedPost, $this->postAuditFields());

                $this->recordAdminActivity('post_update', $this->currentAdmin(), '', 'success', '编辑文章《' . (string) ($updatedPost['title'] ?? $post['title']) . '》（' . $this->auditChangeSummary($changes) . '）。', [
                    'resource_type' => 'post',
                    'resource_id' => $id,
                    'title' => (string) ($updatedPost['title'] ?? $post['title']),
                    'changes' => $changes,
                ]);

                $this->flash('文章已更新');
                $this->redirect('/admin/posts');
                return;
            }

            $this->recordValidationFailedActivity('post_update_failed', '编辑文章失败：表单校验未通过。', $errors, [
                'resource_type' => 'post',
                'resource_id' => $id,
                'title' => (string) ($post['title'] ?? $existingPost['title'] ?? ''),
            ]);

            if ($this->wantsJson()) {
                $this->jsonValidationFailure('文章更新失败，请检查表单内容', $errors);
                return;
            }
        }

        $this->render('admin/posts/edit', [
            'admin' => $_SESSION['admin'] ?? null,
            'post' => $post,
            'categories' => Category::all(),
            'errors' => $errors,
        ]);
    }

    public function deletePost(int $id): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/posts');
            return;
        }

        $existingPost = Post::find($id);

        if (Post::delete($id)) {
            $this->recordAdminActivity('post_delete', $this->currentAdmin(), '', 'success', '删除文章《' . (string) ($existingPost['title'] ?? ('ID ' . $id)) . '》。', [
                'resource_type' => 'post',
                'resource_id' => $id,
                'title' => (string) ($existingPost['title'] ?? ''),
                'snapshot' => is_array($existingPost) ? $this->auditSnapshot($existingPost, $this->postAuditFields()) : [],
            ]);
            $this->flash('文章已删除');
        } else {
            $this->recordAdminActivity('post_delete_failed', $this->currentAdmin(), '', 'warning', '删除文章失败：ID ' . $id . ' 不存在。', [
                'resource_type' => 'post',
                'resource_id' => $id,
            ]);
            $this->flash('文章不存在或已被删除', 'error');
        }

        $this->redirect('/admin/posts');
    }

    public function categories(): void
    {
        $this->requireLogin();

        $page = $this->paginateAdminList(Category::allWithPostCount(), '/admin/categories');

        $this->render('admin/categories/index', [
            'admin' => $_SESSION['admin'] ?? null,
            'categories' => $page['items'],
            'pagination' => $page['pagination'],
            'category' => $this->defaultCategoryData(),
            'errors' => [],
            'flash' => $this->pullFlash(),
            'mode' => 'create',
        ]);
    }

    public function createCategory(): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/categories');
            return;
        }

        $category = $this->categoryDataFromRequest();
        $errors = $this->validateCategoryData($category);

        if (empty($errors)) {
            $slug = Category::generateSlug($category['name']);
            $categoryId = Category::create(
                $category['name'],
                $slug,
                $category['description'] !== '' ? $category['description'] : null
            );

            $this->recordAdminActivity('category_create', $this->currentAdmin(), '', 'success', '创建分类《' . (string) $category['name'] . '》。', [
                'resource_type' => 'category',
                'resource_id' => $categoryId,
                'name' => (string) $category['name'],
                'snapshot' => $this->auditSnapshot(array_merge($category, ['slug' => $slug]), $this->categoryAuditFields()),
            ]);

            $this->flash('分类已创建');
            $this->redirect('/admin/categories');
            return;
        }

        $this->recordValidationFailedActivity('category_create_failed', '创建分类失败：表单校验未通过。', $errors, [
            'resource_type' => 'category',
            'name' => (string) ($category['name'] ?? ''),
        ]);

        if ($this->wantsJson()) {
            $this->jsonValidationFailure('分类创建失败，请检查表单内容', $errors);
            return;
        }

        $page = $this->paginateAdminList(Category::allWithPostCount(), '/admin/categories');

        $this->render('admin/categories/index', [
            'admin' => $_SESSION['admin'] ?? null,
            'categories' => $page['items'],
            'pagination' => $page['pagination'],
            'category' => $category,
            'errors' => $errors,
            'flash' => null,
            'mode' => 'create',
        ]);
    }

    public function editCategory(int $id): void
    {
        $this->requireLogin();

        $existingCategory = Category::find($id);
        if ($existingCategory === null) {
            $this->flash('分类不存在或已被删除', 'error');
            $this->redirect('/admin/categories');
            return;
        }

        $errors = [];
        $category = $existingCategory;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $category = array_merge($existingCategory, $this->categoryDataFromRequest());
            $errors = $this->validateCategoryData($category, $id);

            if (empty($errors)) {
                $slug = Category::generateSlug($category['name'], $id);
                Category::update(
                    $id,
                    $category['name'],
                    $slug,
                    $category['description'] !== '' ? $category['description'] : null
                );
                $updatedCategory = Category::find($id) ?? array_merge($category, ['slug' => $slug]);
                $changes = $this->auditChanges($existingCategory, $updatedCategory, $this->categoryAuditFields());

                $this->recordAdminActivity('category_update', $this->currentAdmin(), '', 'success', '编辑分类《' . (string) ($updatedCategory['name'] ?? $category['name']) . '》（' . $this->auditChangeSummary($changes) . '）。', [
                    'resource_type' => 'category',
                    'resource_id' => $id,
                    'name' => (string) ($updatedCategory['name'] ?? $category['name']),
                    'changes' => $changes,
                ]);

                $this->flash('分类已更新');
                $this->redirect('/admin/categories');
                return;
            }

            $this->recordValidationFailedActivity('category_update_failed', '编辑分类失败：表单校验未通过。', $errors, [
                'resource_type' => 'category',
                'resource_id' => $id,
                'name' => (string) ($category['name'] ?? $existingCategory['name'] ?? ''),
            ]);

            if ($this->wantsJson()) {
                $this->jsonValidationFailure('分类更新失败，请检查表单内容', $errors);
                return;
            }
        }

        $page = $this->paginateAdminList(Category::allWithPostCount(), '/admin/categories');

        $this->render('admin/categories/index', [
            'admin' => $_SESSION['admin'] ?? null,
            'categories' => $page['items'],
            'pagination' => $page['pagination'],
            'category' => $category,
            'errors' => $errors,
            'flash' => null,
            'mode' => 'edit',
        ]);
    }

    public function deleteCategory(int $id): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/categories');
            return;
        }

        $existingCategory = Category::find($id);
        $fallbackCategoryId = Category::defaultGroupId($id);

        if (Category::delete($id)) {
            $this->recordAdminActivity('category_delete', $this->currentAdmin(), '', 'success', '删除分类《' . (string) ($existingCategory['name'] ?? ('ID ' . $id)) . '》。', [
                'resource_type' => 'category',
                'resource_id' => $id,
                'name' => (string) ($existingCategory['name'] ?? ''),
                'moved_posts_to_category_id' => $fallbackCategoryId,
                'snapshot' => is_array($existingCategory) ? $this->auditSnapshot($existingCategory, $this->categoryAuditFields()) : [],
            ]);
            $this->flash('分类已删除，原分类下文章已设为未分类');
        } else {
            $this->recordAdminActivity('category_delete_failed', $this->currentAdmin(), '', 'warning', '删除分类失败：ID ' . $id . ' 不存在。', [
                'resource_type' => 'category',
                'resource_id' => $id,
            ]);
            $this->flash('分类不存在或已被删除', 'error');
        }

        $this->redirect('/admin/categories');
    }

    public function settings(): void
    {
        $this->requireLogin();
        SiteContent::seedDefaults();

        $settings = SiteContent::settings();
        $announcementMode = (string) ($settings['sidebar_announcement_mode'] ?? 'text');
        $announcement = [
            'content' => (string) ($settings['sidebar_announcement_content'] ?? ''),
            'content_mode' => in_array($announcementMode, ['text', 'markdown', 'html'], true) ? $announcementMode : 'text',
        ];

        $this->render('admin/settings', [
            'admin' => $_SESSION['admin'] ?? null,
            'settings' => $settings,
            'announcement' => $announcement,
            'heroSlides' => SiteContent::heroSlides(),
            'flash' => $this->pullFlash(),
        ]);
    }

    public function backendSettings(): void
    {
        $this->requireLogin();
        SiteContent::seedDefaults();

        $this->render('admin/backend-settings', [
            'admin' => $_SESSION['admin'] ?? null,
            'siteSettings' => SiteContent::settings(),
            'blogVersion' => $this->currentBlogVersion(),
            'updateCheckUrlConfigured' => trim((string) Config::get('app.update_check_url', '')) !== '',
            'sessionInfo' => [
                'login_at' => isset($_SESSION['admin_login_at']) ? (int) $_SESSION['admin_login_at'] : 0,
                'expires_at' => isset($_SESSION['admin_expires_at']) ? (int) $_SESSION['admin_expires_at'] : 0,
                'ip_address' => (string) ($_SESSION['admin_ip_address'] ?? ''),
                'user_agent' => (string) ($_SESSION['admin_user_agent'] ?? ''),
            ],
            'flash' => $this->pullFlash(),
        ]);
    }

    public function updateSettings(): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/settings');
            return;
        }

        SiteContent::seedDefaults();
        $currentSettings = SiteContent::settings();
        $scope = (string) ($_POST['settings_scope'] ?? '');
        $auditBefore = $this->settingsAuditSnapshot($scope, $currentSettings);
        $scopeLabel = $this->settingsScopeLabel($scope);

        try {
            if ($scope === 'basic') {
                SiteContent::updateSettings([
                    'site_title' => trim((string) ($_POST['site_title'] ?? '')),
                    'profile_name' => trim((string) ($_POST['profile_name'] ?? '')),
                    'site_logo' => $this->resolveUploadedSettingImage('site_logo_file', (string) ($currentSettings['site_logo'] ?? ''), '顶栏图标', 'site-logo'),
                    'site_avatar' => $this->resolveUploadedSettingImage('site_avatar_file', (string) ($currentSettings['site_avatar'] ?? ''), '顶栏头像', 'site-avatar'),
                    'profile_avatar' => $this->resolveUploadedSettingImage('profile_avatar_file', (string) ($currentSettings['profile_avatar'] ?? ''), '侧栏头像', 'profile-avatar'),
                    'profile_cover' => $this->resolveUploadedSettingImage('profile_cover_file', (string) ($currentSettings['profile_cover'] ?? ''), '侧栏背景图', 'profile-cover'),
                ]);
            } elseif ($scope === 'home') {
                SiteContent::updateHeroSlidesFromLines($this->buildHeroSlidesLinesFromRequest());
            } elseif ($scope === 'announcement') {
                SiteContent::updateSidebarAnnouncement(
                    (string) ($_POST['announcement_content'] ?? ''),
                    (string) ($_POST['announcement_mode'] ?? 'text')
                );
            } elseif ($scope === 'about') {
                SiteContent::updateSettings([
                    'about_title' => trim((string) ($_POST['about_title'] ?? '')),
                    'about_subtitle' => trim((string) ($_POST['about_subtitle'] ?? '')),
                    'about_content' => (string) ($_POST['about_content'] ?? ''),
                    'about_mode' => trim((string) ($_POST['about_mode'] ?? 'markdown')),
                    'about_skills' => (string) ($_POST['about_skills'] ?? ''),
                    'about_links' => $this->buildAboutLinksLinesFromRequest(),
                    'about_avatar' => $this->resolveUploadedSettingImage('about_avatar_file', (string) ($currentSettings['about_avatar'] ?? ''), '关于页头像', 'about-avatar'),
                    'about_cover' => $this->resolveUploadedSettingImage('about_cover_file', (string) ($currentSettings['about_cover'] ?? ''), '关于页横幅', 'about-cover'),
                ]);
            } elseif ($scope === 'guestbook') {
                SiteContent::updateSettings([
                    'guestbook_title' => trim((string) ($_POST['guestbook_title'] ?? '')),
                    'guestbook_subtitle' => trim((string) ($_POST['guestbook_subtitle'] ?? '')),
                    'guestbook_notice' => trim((string) ($_POST['guestbook_notice'] ?? '')),
                ]);
            } elseif ($scope === 'footer') {
                SiteContent::updateSettings([
                    'footer_brand' => trim((string) ($_POST['footer_brand'] ?? '')),
                    'footer_text' => trim((string) ($_POST['footer_text'] ?? '')),
                    'footer_link_text' => trim((string) ($_POST['footer_link_text'] ?? '')),
                    'footer_link_url' => trim((string) ($_POST['footer_link_url'] ?? '')),
                    'footer_powered' => trim((string) ($_POST['footer_powered'] ?? '')),
                    'footer_logo' => $this->resolveUploadedSettingImage('footer_logo_file', (string) ($currentSettings['footer_logo'] ?? ''), '底栏 Logo', 'footer-logo'),
                ]);
            } else {
                throw new \RuntimeException('未知的设置分区。');
            }
        } catch (\RuntimeException $exception) {
            $this->recordAdminActivity('settings_update_failed', $this->currentAdmin(), '', 'warning', '保存前台设置失败：' . $exception->getMessage(), [
                'resource_type' => 'settings',
                'scope' => $scope,
                'scope_label' => $scopeLabel,
                'error' => $exception->getMessage(),
            ]);

            if ($this->wantsJson()) {
                $this->jsonResponse([
                    'ok' => false,
                    'type' => 'error',
                    'message' => $exception->getMessage(),
                ], 422);
                return;
            }

            $this->flash($exception->getMessage(), 'error');
            $this->redirect('/admin/settings');
            return;
        }

        $updatedSettings = SiteContent::settings();
        $auditAfter = $this->settingsAuditSnapshot($scope, $updatedSettings);
        $changes = $this->auditChanges($auditBefore, $auditAfter, $this->settingsAuditFields($scope));

        $this->recordAdminActivity('settings_update', $this->currentAdmin(), '', 'success', '更新前台设置：' . $scopeLabel . '（' . $this->auditChangeSummary($changes) . '）。', [
            'resource_type' => 'settings',
            'scope' => $scope,
            'scope_label' => $scopeLabel,
            'changes' => $changes,
        ]);

        if ($this->wantsJson()) {
            $this->jsonResponse([
                'ok' => true,
                'type' => 'success',
                'message' => '保存成功',
                'settings' => $updatedSettings,
                'heroSlides' => SiteContent::heroSlides(),
            ]);
            return;
        }

        $this->flash('前台设置已保存');
        $this->redirect('/admin/settings');
    }

    public function announcements(): void
    {
        $this->requireLogin();
        SiteContent::seedDefaults();

        $page = $this->paginateAdminList(SiteContent::allAnnouncements(), '/admin/announcements');

        $this->render('admin/announcements/index', [
            'admin' => $_SESSION['admin'] ?? null,
            'announcements' => $page['items'],
            'pagination' => $page['pagination'],
            'flash' => $this->pullFlash(),
        ]);
    }

    public function createAnnouncement(): void
    {
        $this->requireLogin();
        SiteContent::seedDefaults();

        $errors = [];
        $announcement = [
            'level' => 'normal',
            'content' => '',
            'content_mode' => 'text',
            'is_active' => 1,
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $announcement = $this->announcementDataFromRequest();
            $errors = $this->validateAnnouncementData($announcement);

            if (empty($errors)) {
                $announcementId = SiteContent::createAnnouncement(
                    (string) $announcement['level'],
                    (string) $announcement['content'],
                    (string) $announcement['content_mode'],
                    (bool) $announcement['is_active']
                );

                $this->recordAdminActivity('announcement_create', $this->currentAdmin(), '', 'success', '创建公告 #' . $announcementId . '。', [
                    'resource_type' => 'announcement',
                    'resource_id' => $announcementId,
                    'snapshot' => $this->auditSnapshot($announcement, $this->announcementAuditFields()),
                ]);

                $this->flash('公告已创建');
                $this->redirect('/admin/announcements');
                return;
            }

            $this->recordValidationFailedActivity('announcement_create_failed', '创建公告失败：表单校验未通过。', $errors, [
                'resource_type' => 'announcement',
            ]);

            if ($this->wantsJson()) {
                $this->jsonValidationFailure('公告创建失败，请检查表单内容', $errors);
                return;
            }
        }

        $this->render('admin/announcements/form', [
            'admin' => $_SESSION['admin'] ?? null,
            'announcement' => $announcement,
            'errors' => $errors,
            'mode' => 'create',
        ]);
    }

    public function editAnnouncement(int $id): void
    {
        $this->requireLogin();
        SiteContent::seedDefaults();

        $existing = SiteContent::findAnnouncement($id);
        if ($existing === null) {
            $this->flash('公告不存在或已被删除', 'error');
            $this->redirect('/admin/announcements');
            return;
        }

        $errors = [];
        $announcement = $existing;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $announcement = array_merge($existing, $this->announcementDataFromRequest());
            $errors = $this->validateAnnouncementData($announcement);

            if (empty($errors)) {
                SiteContent::updateAnnouncementById(
                    $id,
                    (string) $announcement['level'],
                    (string) $announcement['content'],
                    (string) $announcement['content_mode'],
                    (bool) $announcement['is_active']
                );
                $updatedAnnouncement = SiteContent::findAnnouncement($id) ?? $announcement;
                $changes = $this->auditChanges($existing, $updatedAnnouncement, $this->announcementAuditFields());

                $this->recordAdminActivity('announcement_update', $this->currentAdmin(), '', 'success', '编辑公告 #' . $id . '（' . $this->auditChangeSummary($changes) . '）。', [
                    'resource_type' => 'announcement',
                    'resource_id' => $id,
                    'changes' => $changes,
                ]);

                $this->flash('公告已更新');
                $this->redirect('/admin/announcements');
                return;
            }

            $this->recordValidationFailedActivity('announcement_update_failed', '编辑公告失败：表单校验未通过。', $errors, [
                'resource_type' => 'announcement',
                'resource_id' => $id,
            ]);

            if ($this->wantsJson()) {
                $this->jsonValidationFailure('公告更新失败，请检查表单内容', $errors);
                return;
            }
        }

        $this->render('admin/announcements/form', [
            'admin' => $_SESSION['admin'] ?? null,
            'announcement' => $announcement,
            'errors' => $errors,
            'mode' => 'edit',
        ]);
    }

    public function deleteAnnouncement(int $id): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/announcements');
            return;
        }

        $existing = SiteContent::findAnnouncement($id);

        if (SiteContent::deleteAnnouncement($id)) {
            $this->recordAdminActivity('announcement_delete', $this->currentAdmin(), '', 'success', '删除公告 #' . $id . '。', [
                'resource_type' => 'announcement',
                'resource_id' => $id,
                'snapshot' => is_array($existing) ? $this->auditSnapshot($existing, $this->announcementAuditFields()) : [],
            ]);
            $this->flash('公告已删除');
        } else {
            $this->recordAdminActivity('announcement_delete_failed', $this->currentAdmin(), '', 'warning', '删除公告失败：ID ' . $id . ' 不存在。', [
                'resource_type' => 'announcement',
                'resource_id' => $id,
            ]);
            $this->flash('公告不存在或已被删除', 'error');
        }

        $this->redirect('/admin/announcements');
    }

    public function guestbook(): void
    {
        $this->requireLogin();
        GuestbookMessage::createTable();

        $page = $this->paginateAdminList(GuestbookMessage::all(), '/admin/guestbook');

        $this->render('admin/guestbook/index', [
            'admin' => $_SESSION['admin'] ?? null,
            'messages' => $page['items'],
            'pagination' => $page['pagination'],
            'stats' => [
                'total' => GuestbookMessage::countAll(),
                'replied' => GuestbookMessage::countReplied(),
                'unreplied' => GuestbookMessage::countUnreplied(),
                'hidden' => GuestbookMessage::countByStatus(GuestbookMessage::STATUS_HIDDEN),
                'deleted' => GuestbookMessage::countDeleted(),
            ],
            'flash' => $this->pullFlash(),
        ]);
    }

    public function moderateGuestbookMessage(int $id, string $action): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/guestbook');
            return;
        }

        $message = GuestbookMessage::find($id);
        if ($message === null) {
            $this->recordAdminActivity('guestbook_action_failed', $this->currentAdmin(), '', 'warning', '留言操作失败：留言 #' . $id . ' 不存在。', [
                'resource_type' => 'guestbook_message',
                'resource_id' => $id,
                'requested_action' => $action,
            ]);
            $this->flash('留言不存在', 'error');
            $this->redirect('/admin/guestbook');
            return;
        }

        switch ($action) {
            case 'approve':
                GuestbookMessage::updateStatus($id, GuestbookMessage::STATUS_APPROVED);
                $this->recordGuestbookActivity('guestbook_approve', $message, GuestbookMessage::find($id) ?? $message, '通过留言 #' . $id . '。');
                $this->flash('留言已通过');
                break;
            case 'hide':
                GuestbookMessage::updateStatus($id, GuestbookMessage::STATUS_HIDDEN);
                $this->recordGuestbookActivity('guestbook_hide', $message, GuestbookMessage::find($id) ?? $message, '隐藏留言 #' . $id . '。');
                $this->flash('留言已隐藏');
                break;
            case 'delete':
                if (GuestbookMessage::delete($id)) {
                    $this->recordGuestbookActivity('guestbook_delete', $message, GuestbookMessage::find($id) ?? array_merge($message, ['is_deleted' => 1]), '删除留言 #' . $id . '。');
                } else {
                    $this->recordAdminActivity('guestbook_delete_failed', $this->currentAdmin(), '', 'warning', '删除留言失败：留言 #' . $id . ' 已被删除或不存在。', [
                        'resource_type' => 'guestbook_message',
                        'resource_id' => $id,
                        'snapshot' => $this->auditSnapshot($message, $this->guestbookAuditFields()),
                    ]);
                }
                $this->flash('留言已删除');
                break;
            case 'restore':
                if (GuestbookMessage::restore($id)) {
                    $this->recordGuestbookActivity('guestbook_restore', $message, GuestbookMessage::find($id) ?? $message, '恢复留言 #' . $id . '。');
                    $this->flash('留言已恢复');
                } else {
                    $this->recordAdminActivity('guestbook_restore_failed', $this->currentAdmin(), '', 'warning', '恢复留言失败：留言 #' . $id . ' 未被删除或已恢复。', [
                        'resource_type' => 'guestbook_message',
                        'resource_id' => $id,
                    ]);
                    $this->flash('留言未被删除或已恢复', 'error');
                }
                break;
            default:
                $this->recordAdminActivity('guestbook_action_unknown', $this->currentAdmin(), '', 'warning', '未知留言操作：' . $action . '。', [
                    'resource_type' => 'guestbook_message',
                    'resource_id' => $id,
                    'requested_action' => $action,
                ]);
                $this->flash('未知操作', 'error');
        }

        $this->redirect('/admin/guestbook');
    }

    public function replyGuestbookMessage(int $id): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/guestbook');
            return;
        }

        $message = GuestbookMessage::find($id);
        if ($message === null) {
            $this->recordAdminActivity('guestbook_reply_update_failed', $this->currentAdmin(), '', 'warning', '更新留言回复失败：留言 #' . $id . ' 不存在。', [
                'resource_type' => 'guestbook_message',
                'resource_id' => $id,
            ]);
            $this->flash('留言不存在', 'error');
            $this->redirect('/admin/guestbook');
            return;
        }

        $reply = trim((string) ($_POST['admin_reply'] ?? ''));
        if (mb_strlen($reply) > 1000) {
            $this->recordValidationFailedActivity('guestbook_reply_update_failed', '更新留言回复失败：表单校验未通过。', [
                'admin_reply' => '站长回复不能超过 1000 个字符',
            ], [
                'resource_type' => 'guestbook_message',
                'resource_id' => $id,
                'nickname' => (string) ($message['nickname'] ?? ''),
            ]);
            $this->flash('站长回复不能超过 1000 个字符', 'error');
            $this->redirect('/admin/guestbook');
            return;
        }

        GuestbookMessage::updateReply($id, $reply);
        $this->recordGuestbookActivity('guestbook_reply_update', $message, GuestbookMessage::find($id) ?? $message, ($reply !== '' ? '保存留言 #' . $id . ' 的站长回复。' : '清空留言 #' . $id . ' 的站长回复。'));
        $this->flash($reply !== '' ? '站长回复已保存' : '站长回复已清空');
        $this->redirect('/admin/guestbook');
    }

    private function announcementDataFromRequest(): array
    {
        return [
            'level' => trim((string) ($_POST['level'] ?? 'normal')),
            'content' => (string) ($_POST['content'] ?? ''),
            'content_mode' => (string) ($_POST['content_mode'] ?? 'text'),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
    }

    private function validateAnnouncementData(array $data): array
    {
        $validator = new Validator();
        $validator
            ->required('level', $data['level'] ?? '', '公告级别不能为空')
            ->in('level', (string) ($data['level'] ?? 'normal'), ['normal', 'important', 'urgent', 'archived'], '公告级别不正确')
            ->required('content', $data['content'] ?? '', '公告内容不能为空')
            ->in('content_mode', (string) ($data['content_mode'] ?? 'text'), ['text', 'markdown', 'html'], '公告格式不正确');

        return $validator->errors();
    }

    private function defaultPostData(): array
    {
        $defaultCategoryId = Category::defaultGroupId();

        return [
            'title' => '',
            'summary' => '',
            'content' => '',
            'content_mode' => 'markdown',
            'cover_image' => '',
            'category_id' => $defaultCategoryId !== null ? (string) $defaultCategoryId : '',
            'tags' => '',
            'status' => 1,
        ];
    }

    private function paginateAdminList(array $items, string $basePath, int $perPage = self::ADMIN_LIST_PER_PAGE): array
    {
        $perPage = max(1, $perPage);
        $total = count($items);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($this->currentAdminPage(), $lastPage);
        $offset = ($page - 1) * $perPage;
        $query = $_GET;
        unset($query['page']);

        return [
            'items' => array_slice($items, $offset, $perPage),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'has_previous' => $page > 1,
                'has_next' => $page < $lastPage,
                'previous_url' => $this->adminPageUrl($basePath, max(1, $page - 1), $query),
                'next_url' => $this->adminPageUrl($basePath, min($lastPage, $page + 1), $query),
                'base_path' => $basePath,
                'query' => $query,
            ],
        ];
    }

    private function currentAdminPage(): int
    {
        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
        return is_int($page) && $page > 0 ? $page : 1;
    }

    private function adminPageUrl(string $basePath, int $page, array $query = []): string
    {
        $query['page'] = max(1, $page);
        return $basePath . '?' . http_build_query($query);
    }
    private function defaultCategoryData(): array
    {
        return [
            'id' => null,
            'name' => '',
            'description' => '',
        ];
    }

    private function postDataFromRequest(): array
    {
        $coverImage = trim($_POST['cover_image'] ?? '');

        return [
            'title' => trim($_POST['title'] ?? ''),
            'summary' => trim($_POST['summary'] ?? ''),
            'content' => trim($_POST['content'] ?? ''),
            'content_mode' => $_POST['content_mode'] ?? 'markdown',
            'cover_image' => $coverImage !== '' ? $coverImage : '/assets/img/ZMoon.png',
            'category_id' => $_POST['category_id'] ?? '',
            'tags' => $this->normalizePostTags($_POST['tags'] ?? ''),
            'status' => (int) ($_POST['status'] ?? 1),
        ];
    }

    private function categoryDataFromRequest(): array
    {
        return [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
        ];
    }

    private function normalizeCategoryId(mixed $categoryId): ?int
    {
        if ($categoryId === '' || $categoryId === null) {
            return null;
        }

        return ctype_digit((string) $categoryId) ? (int) $categoryId : 0;
    }

    private function normalizePostTags(mixed $tags): string
    {
        $items = preg_split('/[,，\s]+/u', trim((string) $tags), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $normalized = [];

        foreach ($items as $item) {
            $tag = trim((string) $item);
            if ($tag === '') {
                continue;
            }

            $key = mb_strtolower($tag, 'UTF-8');
            if (!array_key_exists($key, $normalized)) {
                $normalized[$key] = $tag;
            }

            if (count($normalized) >= 3) {
                break;
            }
        }

        return implode(',', array_values($normalized));
    }

    private function validatePostData(array $data): array
    {
        $validator = new Validator();
        $validator
            ->required('title', $data['title'] ?? '', '标题不能为空')
            ->max('title', $data['title'] ?? '', 255, '标题不能超过 255 个字符')
            ->max('summary', $data['summary'] ?? '', 500, '摘要不能超过 500 个字符')
            ->max('cover_image', $data['cover_image'] ?? '', 255, '封面图地址不能超过 255 个字符')
            ->max('tags', $data['tags'] ?? '', 500, '标签不能超过 500 个字符')
            ->required('content', $data['content'] ?? '', '内容不能为空')
            ->in('content_mode', (string) ($data['content_mode'] ?? 'markdown'), ['text', 'markdown', 'html'], '编辑模式不正确')
            ->in('status', (int) ($data['status'] ?? 1), [0, 1], '文章状态不正确');

        $categoryId = $this->normalizeCategoryId($data['category_id'] ?? null);
        if ($categoryId === 0 || ($categoryId !== null && !Category::exists($categoryId))) {
            $errors = $validator->errors();
            $errors['category_id'] = '请选择有效的分类';
            return $errors;
        }

        return $validator->errors();
    }

    private function validateCategoryData(array $data, ?int $ignoreId = null): array
    {
        $validator = new Validator();
        $validator
            ->required('name', $data['name'] ?? '', '分类名称不能为空')
            ->max('name', $data['name'] ?? '', 50, '分类名称不能超过 50 个字符')
            ->max('description', $data['description'] ?? '', 255, '分类描述不能超过 255 个字符');

        return $validator->errors();
    }

    private function resolveUploadedSettingImage(string $field, string $currentValue, string $label, string $prefix): string
    {
        $file = $_FILES[$field] ?? null;
        if (!is_array($file)) {
            return $currentValue;
        }

        return $this->resolveUploadedImageValue($file, $currentValue, $label, $prefix);
    }

    private function resolveUploadedImageValue(array $file, string $currentValue, string $label, string $prefix): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return $currentValue;
        }

        return $this->storeUploadedImage($file, $label, $prefix);
    }

    private function buildHeroSlidesLinesFromRequest(): string
    {
        $titles = isset($_POST['hero_slide_title']) && is_array($_POST['hero_slide_title']) ? $_POST['hero_slide_title'] : [];
        $links = isset($_POST['hero_slide_link']) && is_array($_POST['hero_slide_link']) ? $_POST['hero_slide_link'] : [];
        $existingImages = isset($_POST['hero_slide_existing']) && is_array($_POST['hero_slide_existing']) ? $_POST['hero_slide_existing'] : [];
        $uploadedImages = $this->normalizeUploadedFilesArray('hero_slide_image');

        $total = max(count($titles), count($links), count($existingImages), count($uploadedImages));
        $lines = [];

        for ($index = 0; $index < $total; $index++) {
            $title = trim((string) ($titles[$index] ?? ''));
            if ($title === '') {
                continue;
            }

            $image = $this->resolveUploadedImageValue(
                $uploadedImages[$index] ?? ['error' => UPLOAD_ERR_NO_FILE],
                trim((string) ($existingImages[$index] ?? '')),
                '第 ' . ($index + 1) . ' 个轮播图图片',
                'hero-slide-' . ($index + 1)
            );

            if ($image === '') {
                throw new \RuntimeException('第 ' . ($index + 1) . ' 个轮播图请上传图片。');
            }

            $link = trim((string) ($links[$index] ?? ''));
            $lines[] = implode('|', [
                $image,
                $link !== '' ? $link : '/',
                $title,
            ]);
        }

        return implode("\n", $lines);
    }

    private function buildCopyButtonsLinesFromRequest(): string
    {
        $labels = isset($_POST['copy_button_label']) && is_array($_POST['copy_button_label']) ? $_POST['copy_button_label'] : [];
        $values = isset($_POST['copy_button_value']) && is_array($_POST['copy_button_value']) ? $_POST['copy_button_value'] : [];
        $total = max(count($labels), count($values));
        $lines = [];

        for ($index = 0; $index < $total; $index++) {
            $label = trim((string) ($labels[$index] ?? ''));
            $copyValue = trim((string) ($values[$index] ?? ''));

            if ($label === '' || $copyValue === '') {
                continue;
            }

            $lines[] = $label . '|' . $copyValue;
        }

        return implode("\n", $lines);
    }

    private function buildAboutLinksLinesFromRequest(): string
    {
        $labels = isset($_POST['about_link_label']) && is_array($_POST['about_link_label']) ? $_POST['about_link_label'] : [];
        $urls = isset($_POST['about_link_url']) && is_array($_POST['about_link_url']) ? $_POST['about_link_url'] : [];
        $total = max(count($labels), count($urls));
        $lines = [];

        for ($index = 0; $index < $total; $index++) {
            $label = trim((string) ($labels[$index] ?? ''));
            $url = trim((string) ($urls[$index] ?? ''));

            if ($label === '' || $url === '') {
                continue;
            }

            $lines[] = implode('|', [
                $label,
                $this->aboutLinkIcon($label),
                $url,
            ]);
        }

        return implode("\n", $lines);
    }

    private function aboutLinkIcon(string $label): string
    {
        return match ($label) {
            'GitHub' => 'fa-brands fa-github',
            'Gitee' => 'fa-solid fa-code-branch',
            'QQ' => 'fa-brands fa-qq',
            '邮箱' => 'fa-solid fa-envelope',
            '微信' => 'fa-brands fa-weixin',
            default => 'fa-solid fa-link',
        };
    }

    /**
     * @return array<int, array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    private function normalizeUploadedFilesArray(string $field): array
    {
        $files = $_FILES[$field] ?? null;
        if (
            !is_array($files)
            || !isset($files['name'], $files['type'], $files['tmp_name'], $files['error'], $files['size'])
            || !is_array($files['name'])
        ) {
            return [];
        }

        $normalized = [];
        foreach (array_keys($files['name']) as $index) {
            $normalized[$index] = [
                'name' => (string) ($files['name'][$index] ?? ''),
                'type' => (string) ($files['type'][$index] ?? ''),
                'tmp_name' => (string) ($files['tmp_name'][$index] ?? ''),
                'error' => (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($files['size'][$index] ?? 0),
            ];
        }

        return $normalized;
    }

    private function storeUploadedImage(array $file, string $label, string $prefix): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($this->uploadErrorMessage($error, $label));
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new \RuntimeException($label . ' 上传失败，请重新选择文件。');
        }

        $originalName = (string) ($file['name'] ?? '');
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (!in_array($extension, $allowedExtensions, true)) {
            throw new \RuntimeException($label . ' 仅支持 jpg、jpeg、png、webp、gif 格式。');
        }

        if (@getimagesize($tmpName) === false) {
            throw new \RuntimeException($label . ' 不是有效的图片文件。');
        }

        $uploadDirectory = dirname(__DIR__, 2) . '/public/uploads';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
            throw new \RuntimeException('上传目录创建失败，请检查 /public/uploads 是否可写。');
        }

        if (!is_writable($uploadDirectory)) {
            @chmod($uploadDirectory, 0775);
        }

        if (!is_writable($uploadDirectory)) {
            throw new \RuntimeException('上传目录不可写，请将 /public/uploads 的属主设置为 PHP 运行用户，并赋予 775 或 755 写入权限。');
        }

        $safePrefix = preg_replace('/[^a-z0-9-]+/', '-', strtolower($prefix)) ?: 'image';
        $safePrefix = trim($safePrefix, '-') ?: 'image';
        $filename = $safePrefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(5)) . '.' . $extension;
        $targetPath = $uploadDirectory . '/' . $filename;

        if (!@move_uploaded_file($tmpName, $targetPath)) {
            throw new \RuntimeException($label . ' 保存失败，请检查 /public/uploads 是否允许 PHP 写入。');
        }

        @chmod($targetPath, 0644);

        return '/uploads/' . $filename;
    }

    private function uploadErrorMessage(int $error, string $label): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $label . ' 超出上传大小限制。',
            UPLOAD_ERR_PARTIAL => $label . ' 上传不完整，请重新上传。',
            UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时目录，无法上传 ' . $label . '。',
            UPLOAD_ERR_CANT_WRITE => $label . ' 写入失败，请检查目录权限。',
            UPLOAD_ERR_EXTENSION => $label . ' 被服务器扩展阻止上传。',
            default => $label . ' 上传失败，请重试。',
        };
    }

    private function requireLogin(): void
    {
        $this->startSession();

        $invalidReason = null;
        if (!$this->isLoggedIn($invalidReason)) {
            if ($invalidReason === 'data') {
                $this->handleAdminDataAnomaly();
            }

            $this->clearAdminSession();
            $this->redirect('/admin/login');
        }
    }

    private function isLoggedIn(?string &$invalidReason = null): bool
    {
        $invalidReason = null;

        if (!isset($_SESSION['admin']) || !is_array($_SESSION['admin'])) {
            return false;
        }

        $adminId = (int) ($_SESSION['admin']['id'] ?? 0);
        $sessionUsername = trim((string) ($_SESSION['admin']['username'] ?? ''));
        $expiresAt = (int) ($_SESSION['admin_expires_at'] ?? 0);

        if ($adminId <= 0) {
            $invalidReason = 'data';
            return false;
        }

        if ($expiresAt <= time()) {
            return false;
        }

        $admin = Admin::findById($adminId);
        if ($admin === null) {
            $invalidReason = 'data';
            return false;
        }

        $databaseUsername = (string) ($admin['username'] ?? '');
        if ($sessionUsername === '' || $databaseUsername === '' || $sessionUsername !== $databaseUsername) {
            $invalidReason = 'data';
            return false;
        }

        $_SESSION['admin'] = [
            'id' => (int) $admin['id'],
            'username' => $databaseUsername,
        ];

        $lastRegeneratedAt = (int) ($_SESSION['admin_last_regenerated_at'] ?? 0);
        if ($lastRegeneratedAt <= 0 || time() - $lastRegeneratedAt >= self::ADMIN_SESSION_REGENERATE_INTERVAL) {
            session_regenerate_id(true);
            $_SESSION['admin_last_regenerated_at'] = time();
        }

        return true;
    }
    private function handleAdminDataAnomaly(): void
    {
        $this->clearAdminSession();

        if ($this->wantsJson()) {
            $_SESSION['admin_login_notice'] = '数据异常，请重新登录';
            $this->rememberAdminLoginNoticeCookie('data');
            $this->jsonResponse([
                'ok' => false,
                'type' => 'error',
                'message' => '数据异常，请重新登录',
                'login_url' => '/admin/login?notice=data',
            ], 401);
            exit;
        }

        $this->render('admin/login', [
            'error' => '',
            'username' => '',
            'loginNotice' => '数据异常，请重新登录',
            'siteSettings' => SiteContent::settings(),
        ]);
        exit;
    }

    private function clientIp(): string
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($ip === '' || strlen($ip) > 45) {
            return 'unknown';
        }

        return $ip;
    }

    private function userAgent(): string
    {
        return mb_substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255, 'UTF-8');
    }

    private function formatLockRemaining(int $seconds): string
    {
        $seconds = max(1, $seconds);
        $minutes = (int) ceil($seconds / 60);

        if ($minutes >= 1) {
            return $minutes . ' 分钟';
        }

        return $seconds . ' 秒';
    }

    private function recordAdminActivity(string $action, ?array $admin, string $username, string $status, string $message, array $metadata = []): void
    {
        try {
            AdminActivityLog::record($action, [
                'admin_id' => is_array($admin) ? (int) ($admin['id'] ?? 0) : null,
                'username' => $username !== '' ? $username : (is_array($admin) ? (string) ($admin['username'] ?? '') : ''),
                'status' => $status,
                'ip_address' => $this->clientIp(),
                'user_agent' => $this->userAgent(),
                'message' => $message,
                'metadata' => $metadata,
            ]);
        } catch (\Throwable $e) {
            // 审计记录失败不应阻断登录主流程。
        }
    }


    private function currentAdmin(): ?array
    {
        return isset($_SESSION['admin']) && is_array($_SESSION['admin']) ? $_SESSION['admin'] : null;
    }

    private function recordGuestbookActivity(string $action, array $before, array $after, string $message): void
    {
        $id = (int) ($after['id'] ?? $before['id'] ?? 0);
        $changes = $this->auditChanges($before, $after, $this->guestbookAuditFields());

        $this->recordAdminActivity($action, $this->currentAdmin(), '', 'success', $message, [
            'resource_type' => 'guestbook_message',
            'resource_id' => $id,
            'nickname' => (string) ($before['nickname'] ?? $after['nickname'] ?? ''),
            'changes' => $changes,
        ]);
    }

    private function recordValidationFailedActivity(string $action, string $message, array $errors, array $metadata = []): void
    {
        $normalizedErrors = [];
        $errorIndex = 1;
        foreach ($errors as $field => $error) {
            $label = is_int($field) ? ('错误 ' . $errorIndex) : (string) $field;
            $normalizedErrors[$label] = $this->auditValue($error);
            $errorIndex++;
        }

        $this->recordAdminActivity($action, $this->currentAdmin(), '', 'warning', $message, array_merge($metadata, [
            'errors' => $normalizedErrors,
        ]));
    }

    private function auditChanges(array $before, array $after, array $fields): array
    {
        $changes = [];
        foreach ($fields as $field => $label) {
            $old = $this->auditValue($before[$field] ?? null);
            $new = $this->auditValue($after[$field] ?? null);
            if ($old === $new) {
                continue;
            }

            $changes[(string) $field] = [
                'label' => (string) $label,
                'old' => $old,
                'new' => $new,
            ];
        }

        return $changes;
    }

    private function auditSnapshot(array $data, array $fields): array
    {
        $snapshot = [];
        foreach ($fields as $field => $label) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $snapshot[(string) $field] = [
                'label' => (string) $label,
                'value' => $this->auditValue($data[$field]),
            ];
        }

        return $snapshot;
    }

    private function auditValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (mb_strlen($value, 'UTF-8') > 1200) {
            return mb_substr($value, 0, 1200, 'UTF-8') . '...';
        }

        return $value;
    }

    private function auditChangeSummary(array $changes): string
    {
        if (empty($changes)) {
            return '无字段变化';
        }

        $labels = [];
        foreach ($changes as $change) {
            $label = trim((string) ($change['label'] ?? ''));
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        $labels = array_values(array_unique($labels));
        $shown = array_slice($labels, 0, 4);
        $summary = implode('、', $shown);
        if (count($labels) > count($shown)) {
            $summary .= '等 ' . count($labels) . ' 项';
        }

        return $summary !== '' ? $summary : count($changes) . ' 项';
    }

    private function postAuditFields(): array
    {
        return [
            'title' => '文章标题',
            'slug' => '固定链接',
            'summary' => '摘要',
            'content' => '正文',
            'content_mode' => '正文格式',
            'cover_image' => '封面图',
            'category_id' => '分类 ID',
            'tags' => '标签',
            'status' => '发布状态',
            'published_at' => '发布时间',
        ];
    }

    private function categoryAuditFields(): array
    {
        return [
            'name' => '分类名称',
            'slug' => '固定链接',
            'description' => '分类描述',
        ];
    }

    private function announcementAuditFields(): array
    {
        return [
            'level' => '公告级别',
            'content' => '公告内容',
            'content_mode' => '内容格式',
            'is_active' => '显示状态',
        ];
    }

    private function guestbookAuditFields(): array
    {
        return [
            'status' => '审核状态',
            'is_deleted' => '删除状态',
            'admin_reply' => '站长回复',
            'replied_at' => '回复时间',
        ];
    }

    private function settingsAuditFields(string $scope): array
    {
        return match ($scope) {
            'basic' => [
                'site_title' => '站点标题',
                'profile_name' => '侧栏名称',
                'site_logo' => '顶栏图标',
                'site_avatar' => '顶栏头像',
                'profile_avatar' => '侧栏头像',
                'profile_cover' => '侧栏背景图',
            ],
            'home' => [
                'hero_slides' => '首页轮播图',
            ],
            'announcement' => [
                'sidebar_announcement_content' => '侧栏公告内容',
                'sidebar_announcement_mode' => '侧栏公告格式',
            ],
            'about' => [
                'about_title' => '关于页标题',
                'about_subtitle' => '关于页副标题',
                'about_content' => '关于页内容',
                'about_mode' => '关于页格式',
                'about_skills' => '技能标签',
                'about_links' => '社交链接',
                'about_avatar' => '关于页头像',
                'about_cover' => '关于页横幅',
            ],
            'guestbook' => [
                'guestbook_title' => '留言板标题',
                'guestbook_subtitle' => '留言板副标题',
                'guestbook_notice' => '留言提示',
            ],
            'footer' => [
                'footer_brand' => '底栏品牌',
                'footer_text' => '底栏文案',
                'footer_link_text' => '底栏链接文字',
                'footer_link_url' => '底栏链接地址',
                'footer_powered' => '底栏技术文案',
                'footer_logo' => '底栏 Logo',
            ],
            default => [],
        };
    }

    private function settingsAuditSnapshot(string $scope, array $settings): array
    {
        $snapshot = [];
        foreach ($this->settingsAuditFields($scope) as $field => $_label) {
            $snapshot[$field] = match ($field) {
                'hero_slides' => SiteContent::heroSlidesToLines(),
                default => (string) ($settings[$field] ?? ''),
            };
        }

        return $snapshot;
    }

    private function settingsScopeLabel(string $scope): string
    {
        return match ($scope) {
            'basic' => '基础信息',
            'home' => '首页内容',
            'announcement' => '侧栏公告',
            'about' => '关于页面',
            'guestbook' => '留言板',
            'footer' => '底栏信息',
            default => '未知分区',
        };
    }
    private function clearAdminSession(): void
    {
        unset(
            $_SESSION['admin'],
            $_SESSION['admin_login_at'],
            $_SESSION['admin_expires_at'],
            $_SESSION['admin_last_regenerated_at'],
            $_SESSION['admin_ip_address'],
            $_SESSION['admin_user_agent']
        );
    }

    private function pullAdminLoginNotice(): ?string
    {
        $notice = $_SESSION['admin_login_notice'] ?? null;
        unset($_SESSION['admin_login_notice']);

        if (!is_string($notice)) {
            return null;
        }

        $notice = trim($notice);
        return $notice !== '' ? $notice : null;
    }

    private function rememberAdminLoginNoticeCookie(string $notice): void
    {
        $this->setAdminLoginNoticeCookie($notice, time() + 300);
    }

    private function clearAdminLoginNoticeCookie(): void
    {
        $this->setAdminLoginNoticeCookie('', time() - 3600);
    }

    private function setAdminLoginNoticeCookie(string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie('admin_login_notice', $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            ini_set('session.gc_maxlifetime', (string) self::ADMIN_SESSION_TTL);

            $params = session_get_cookie_params();
            session_set_cookie_params([
                'lifetime' => self::ADMIN_SESSION_TTL,
                'path' => $params['path'] ?: '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => true,
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);

            session_start();
        }
    }

    private function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['flash'] = [
            'message' => $message,
            'type' => $type,
        ];
    }

    private function pullFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        return is_array($flash) ? $flash : null;
    }

    private function wantsJson(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

        return str_contains($accept, 'application/json')
            || $requestedWith === 'xmlhttprequest'
            || str_starts_with($requestPath, '/admin/api/');
    }

    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    public function profile(): void
    {
        $this->requireLogin();
        SiteContent::seedDefaults();

        $admin = $_SESSION['admin'] ?? null;
        $settings = SiteContent::settings();

        $this->render('admin/profile', [
            'admin' => $admin,
            'settings' => $settings,
            'copyButtons' => SiteContent::copyButtons(),
            'flash' => $this->pullFlash(),
        ]);
    }

    public function updateProfile(): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/profile');
            return;
        }

        $admin = $_SESSION['admin'] ?? null;
        if ($admin === null) {
            $this->redirect('/admin/login');
            return;
        }

        SiteContent::seedDefaults();
        $currentSettings = SiteContent::settings();

        $adminId = (int) ($admin['id'] ?? 0);
        $username = trim((string) ($_POST['username'] ?? ''));
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $currentAvatar = trim((string) ($currentSettings['profile_avatar'] ?? SiteContent::DEFAULT_AVATAR));
        if ($currentAvatar === '') {
            $currentAvatar = SiteContent::DEFAULT_AVATAR;
        }
        $profileAvatar = $currentAvatar;

        $currentHomeCover = trim((string) ($currentSettings['profile_home_cover'] ?? ''));
        if ($currentHomeCover === '') {
            $currentHomeCover = trim((string) ($currentSettings['profile_cover'] ?? SiteContent::DEFAULT_PROFILE_COVER));
        }
        if ($currentHomeCover === '') {
            $currentHomeCover = SiteContent::DEFAULT_PROFILE_COVER;
        }
        $profileHomeCover = $currentHomeCover;

        $currentMotto = trim((string) ($currentSettings['profile_motto'] ?? ''));
        if ($currentMotto === '') {
            $currentMotto = trim((string) ($currentSettings['profile_text'] ?? ''));
        }

        $hasProfileDisplayFields = array_key_exists('profile_motto', $_POST)
            || array_key_exists('copy_button_label', $_POST)
            || array_key_exists('copy_button_value', $_POST);
        $profileMotto = $hasProfileDisplayFields ? trim((string) ($_POST['profile_motto'] ?? '')) : $currentMotto;
        $currentCopyButtonsLines = SiteContent::copyButtonsToLines();
        $copyButtonsLines = $hasProfileDisplayFields ? $this->buildCopyButtonsLinesFromRequest() : $currentCopyButtonsLines;

        $errors = [];

        if ($username === '') {
            $errors[] = '管理员用户名不能为空';
        } elseif (mb_strlen($username) > 50) {
            $errors[] = '管理员用户名不能超过 50 个字符';
        } else {
            $existingAdmin = Admin::findByUsername($username);
            if ($existingAdmin !== null && (int) ($existingAdmin['id'] ?? 0) !== $adminId) {
                $errors[] = '该用户名已被使用';
            }
        }

        if ($hasProfileDisplayFields && mb_strlen($profileMotto, 'UTF-8') > 300) {
            $errors[] = '个人座右铭不能超过 300 个字符';
        }

        if ($newPassword !== '') {
            if ($currentPassword === '') {
                $errors[] = '修改密码时必须输入当前密码';
            } else {
                $dbAdmin = Admin::findById($adminId);
                if ($dbAdmin === null || !password_verify($currentPassword, (string) ($dbAdmin['password'] ?? ''))) {
                    $errors[] = '当前密码不正确';
                }
            }

            if (mb_strlen($newPassword) < 6) {
                $errors[] = '新密码长度不能少于 6 个字符';
            }

            if ($newPassword !== $confirmPassword) {
                $errors[] = '两次输入的新密码不一致';
            }
        }

        try {
            $profileAvatar = $this->resolveUploadedSettingImage('profile_avatar_file', $currentAvatar, '个人头像', 'profile-avatar');
            $profileHomeCover = $this->resolveUploadedSettingImage('profile_home_cover_file', $currentHomeCover, '个人主页背景图', 'profile-home-cover');
        } catch (\RuntimeException $exception) {
            $errors[] = $exception->getMessage();
        }

        if (!empty($errors)) {
            $this->recordValidationFailedActivity('profile_update_failed', '更新个人资料失败：表单校验未通过。', $errors, [
                'resource_type' => 'admin_profile',
                'resource_id' => $adminId,
                'attempted_username' => $username,
                'password_change_requested' => $newPassword !== '',
                'avatar_change_requested' => isset($_FILES['profile_avatar_file']) && is_array($_FILES['profile_avatar_file']) && (int) ($_FILES['profile_avatar_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE,
                'home_cover_change_requested' => isset($_FILES['profile_home_cover_file']) && is_array($_FILES['profile_home_cover_file']) && (int) ($_FILES['profile_home_cover_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE,
                'display_change_requested' => $hasProfileDisplayFields,
            ]);
            if ($this->wantsJson()) {
                $this->jsonValidationFailure('个人资料更新失败，请检查表单内容', $errors);
                return;
            }

            $_SESSION['profile_errors'] = $errors;
            $_SESSION['profile_old'] = $_POST;
            $this->redirect('/admin/profile');
            return;
        }

        $existingAdmin = Admin::findById($adminId) ?? $admin;
        $profileBefore = [
            'username' => (string) ($existingAdmin['username'] ?? ''),
            'profile_avatar' => $currentAvatar,
            'profile_home_cover' => $currentHomeCover,
            'profile_motto' => $currentMotto,
            'copy_buttons' => $currentCopyButtonsLines,
        ];
        $now = (new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai')))->format('Y-m-d H:i:s');

        \App\Core\Database::query(
            "UPDATE admin SET username = ?, updated_at = ? WHERE id = ?",
            [$username, $now, $adminId]
        );

        if ($newPassword !== '') {
            $hash = Admin::hashPassword($newPassword);
            Admin::updatePasswordHash($adminId, $hash);
        }

        $settingsToUpdate = [];
        if ($profileAvatar !== $currentAvatar) {
            $settingsToUpdate['profile_avatar'] = $profileAvatar;
            $settingsToUpdate['site_avatar'] = $profileAvatar;
        }
        if ($profileHomeCover !== $currentHomeCover) {
            $settingsToUpdate['profile_home_cover'] = $profileHomeCover;
        }
        if ($hasProfileDisplayFields) {
            $settingsToUpdate['profile_motto'] = $profileMotto;
            $settingsToUpdate['profile_text'] = $profileMotto;
        }
        if (!empty($settingsToUpdate)) {
            SiteContent::updateSettings($settingsToUpdate);
        }
        if ($hasProfileDisplayFields) {
            SiteContent::updateCopyButtonsFromLines($copyButtonsLines);
        }

        $_SESSION['admin']['username'] = $username;

        $updatedCopyButtonsLines = $hasProfileDisplayFields ? SiteContent::copyButtonsToLines() : $currentCopyButtonsLines;
        $changes = $this->auditChanges($profileBefore, [
            'username' => $username,
            'profile_avatar' => $profileAvatar,
            'profile_home_cover' => $profileHomeCover,
            'profile_motto' => $profileMotto,
            'copy_buttons' => $updatedCopyButtonsLines,
        ], [
            'username' => '管理员用户名',
            'profile_avatar' => '个人头像',
            'profile_home_cover' => '个人主页背景图',
            'profile_motto' => '个人座右铭',
            'copy_buttons' => '侧栏复制内容',
        ]);
        if ($newPassword !== '') {
            $changes['password'] = [
                'label' => '登录密码',
                'old' => '未展示',
                'new' => '已重置',
            ];
        }

        $this->recordAdminActivity('profile_update', ['id' => $adminId, 'username' => $username], '', 'success', '更新个人资料（' . $this->auditChangeSummary($changes) . '）。', [
            'resource_type' => 'admin_profile',
            'resource_id' => $adminId,
            'changes' => $changes,
        ]);

        if ($this->wantsJson()) {
            $this->jsonResponse([
                'ok' => true,
                'type' => 'success',
                'message' => '个人资料更新成功',
                'profile' => [
                    'username' => $username,
                    'avatar' => $profileAvatar,
                ],
                'settings' => [
                    'profile_avatar' => $profileAvatar,
                    'site_avatar' => $profileAvatar,
                    'profile_home_cover' => $profileHomeCover,
                    'profile_motto' => $profileMotto,
                    'profile_text' => $profileMotto,
                ],
                'copyButtons' => SiteContent::copyButtons(),
            ]);
            return;
        }

        $this->flash('个人资料更新成功');
        $this->redirect('/admin/profile');
    }

    private function jsonValidationFailure(string $message, array $errors, array $extra = []): void
    {
        $firstError = $this->firstValidationError($errors);
        $payload = array_merge([
            'ok' => false,
            'type' => 'error',
            'message' => $firstError !== '' ? $firstError : $message,
            'errors' => $errors,
        ], $extra);

        $this->jsonResponse($payload, 422);
    }

    private function firstValidationError(array $errors): string
    {
        foreach ($errors as $error) {
            if (is_array($error)) {
                $nested = $this->firstValidationError($error);
                if ($nested !== '') {
                    return $nested;
                }
                continue;
            }

            $text = trim((string) $error);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function redirect(string $url): void
    {
        if ($this->wantsJson()) {
            $flash = $this->pullFlash();
            $type = ((string) ($flash['type'] ?? 'success')) === 'error' ? 'error' : 'success';
            $message = trim((string) ($flash['message'] ?? ''));
            $isLoginRedirect = str_starts_with($url, '/admin/login');

            if ($isLoginRedirect) {
                $this->jsonResponse([
                    'ok' => false,
                    'type' => 'error',
                    'message' => $message !== '' ? $message : '请重新登录',
                    'redirect' => $url,
                    'login_url' => $url,
                ], 401);
                exit;
            }

            if ($message === '') {
                $message = $type === 'error' ? '操作失败' : '操作成功';
            }

            $this->jsonResponse([
                'ok' => $type !== 'error',
                'type' => $type,
                'message' => $message,
                'redirect' => $url,
            ], $type === 'error' ? 400 : 200);
            exit;
        }

        header('Location: ' . $url);
        exit;
    }

    private function render(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);

        $viewFile = dirname(__DIR__, 2) . '/resources/views/' . $view . '.php';

        if (!file_exists($viewFile)) {
            http_response_code(500);
            echo 'View not found';
            return;
        }

        require $viewFile;
    }
}
