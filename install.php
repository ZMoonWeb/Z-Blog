<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

use App\Core\Config;
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

$alreadyInstalled = isAppInstalled();

function createInstallerPdo(bool $withDatabase = true): PDO
{
    Config::load(__DIR__);

    $dsn = "mysql:host=" . Config::get('database.host') . ";port=" . Config::get('database.port');

    if ($withDatabase) {
        $dsn .= ";dbname=" . Config::get('database.database');
    }

    $dsn .= ";charset=utf8mb4";

    return new PDO(
        $dsn,
        Config::get('database.username'),
        Config::get('database.password'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
    );
}

function isAppInstalled(): bool
{
    try {
        $pdo = createInstallerPdo();

        $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
        if ($stmt->fetchColumn() === false) {
            return false;
        }

        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'installed' LIMIT 1");
        $stmt->execute();

        return $stmt->fetchColumn() === '1';
    } catch (Throwable $e) {
        return false;
    }
}

function createSystemSettingsTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function markAppInstalled(PDO $pdo): void
{
    createSystemSettingsTable($pdo);

    $now = (new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai')))->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        "INSERT INTO system_settings (setting_key, setting_value, created_at, updated_at)
        VALUES ('installed', '1', ?, ?)
        ON DUPLICATE KEY UPDATE setting_value = '1', updated_at = VALUES(updated_at)"
    );
    $stmt->execute([$now, $now]);
}

function checkPhpVersion(): array
{
    $ok = PHP_VERSION_ID >= 80100;
    return ['name' => 'PHP 版本 >= 8.1', 'ok' => $ok, 'current' => PHP_VERSION];
}

function checkExtension(string $ext): array
{
    $ok = extension_loaded($ext);
    return ['name' => "{$ext} 扩展", 'ok' => $ok, 'current' => $ok ? '已安装' : '未安装'];
}

function checkEnvFile(): array
{
    $path = __DIR__ . '/.env';
    if (!file_exists($path)) {
        return ['name' => '.env 配置文件', 'ok' => false, 'current' => '未找到'];
    }

    $values = parseInstallerEnvFile($path);
    $required = [
        'APP_TIMEZONE',
        'APP_URL',
        'DB_HOST',
        'DB_PORT',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_CHARSET',
        'MAIL_MAILER',
        'MAIL_HOST',
        'MAIL_PORT',
        'MAIL_FROM_ADDRESS',
        'MAIL_FROM_NAME',
        'LOG_CHANNEL',
        'LOG_LEVEL',
        'LOG_PATH',
        'LOG_REQUESTS',
    ];
    $missing = [];
    foreach ($required as $key) {
        if (!array_key_exists($key, $values) || trim($values[$key], " \t\n\r\0\x0B\"'") === '') {
            $missing[] = $key;
        }
    }

    if ($missing !== []) {
        return ['name' => '.env 配置文件', 'ok' => false, 'current' => '缺少 ' . implode(', ', $missing)];
    }

    return ['name' => '.env 配置文件', 'ok' => true, 'current' => '已配置'];
}

function parseInstallerEnvFile(string $path): array
{
    $content = @file_get_contents($path);
    if (!is_string($content)) {
        return [];
    }

    $values = [];
    foreach (preg_split('/\R/', $content) ?: [] as $line) {
        if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches) === 1) {
            $values[$matches[1]] = rtrim($matches[2]);
        }
    }

    return $values;
}

function normalizeInstallerPathForCompare(string $path): string
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

function installerPathAllowedByOpenBasedir(string $path): bool
{
    $openBasedir = trim((string) ini_get('open_basedir'));
    if ($openBasedir === '') {
        return true;
    }

    $target = normalizeInstallerPathForCompare($path);
    foreach (explode(PATH_SEPARATOR, $openBasedir) as $base) {
        $base = trim($base);
        if ($base === '') {
            continue;
        }

        $allowed = normalizeInstallerPathForCompare($base);
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

function readInstallerSystemFile(string $path): ?string
{
    if (!installerPathAllowedByOpenBasedir($path)) {
        return null;
    }

    if (!@is_file($path) || !@is_readable($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    return is_string($raw) ? $raw : null;
}

function checkSystemMetricsAccess(): array
{
    $isLinux = stripos(PHP_OS_FAMILY, 'Linux') === 0 || stripos(PHP_OS, 'Linux') === 0;
    if (!$isLinux) {
        return ['name' => '系统监控 /proc 访问', 'ok' => true, 'current' => '非 Linux 跳过'];
    }

    if (!installerPathAllowedByOpenBasedir('/proc/stat')) {
        return ['name' => '系统监控 /proc 访问', 'ok' => false, 'current' => 'open_basedir 需加入 /proc/'];
    }

    foreach (['/proc/stat', '/proc/meminfo', '/proc/cpuinfo', '/proc/uptime'] as $file) {
        if (readInstallerSystemFile($file) === null) {
            return ['name' => '系统监控 /proc 访问', 'ok' => false, 'current' => $file . ' 不可读'];
        }
    }

    return ['name' => '系统监控 /proc 访问', 'ok' => true, 'current' => '可读取 /proc'];
}

function checkDatabase(): array
{
    try {
        Config::load(__DIR__);
        $host = Config::get('database.host');
        $port = Config::get('database.port');
        $db   = Config::get('database.database');
        $user = Config::get('database.username');
        $pass = Config::get('database.password');

        $pdo = new PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
        );
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        return ['name' => '数据库连接', 'ok' => true, 'current' => "{$host}:{$port}/{$db}"];
    } catch (Throwable $e) {
        return ['name' => '数据库连接', 'ok' => false, 'current' => $e->getMessage()];
    }
}

$checks = [
    checkPhpVersion(),
    checkExtension('pdo'),
    checkExtension('pdo_mysql'),
    checkExtension('mbstring'),
    checkEnvFile(),
    checkSystemMetricsAccess(),
    checkDatabase(),
];
$allOk = true;

foreach ($checks as $c) {
    if (!$c['ok']) {
        $allOk = false;
        break;
    }
}

if (!$alreadyInstalled && $_SERVER['REQUEST_METHOD'] === 'POST' && $allOk && ($_POST['action'] ?? '') !== 'next') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';
    $error = '';

    if ($username === '' || $password === '') {
        $error = '用户名和密码不能为空';
    } elseif ($password !== $confirm) {
        $error = '两次输入的密码不一致';
    } elseif (strlen($password) < 6) {
        $error = '密码长度不能少于 6 位';
    } else {
        try {
            Config::load(__DIR__);
            $dbName = Config::get('database.database');

            $pdo = new PDO(
                "mysql:host=" . Config::get('database.host') . ";port=" . Config::get('database.port') . ";charset=utf8mb4",
                Config::get('database.username'),
                Config::get('database.password'),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->exec("USE `{$dbName}`");

            Admin::createTable();
            Category::createTable();
            Post::createTable();
            Comment::createTable();
            Like::createTable();
            PostInteractionLog::createTable();
            AdminLoginAttempt::createTable();
            AdminActivityLog::createTable();
            GuestbookMessage::createTable();
            SiteContent::seedDefaults();

            $stmt = $pdo->query("SELECT COUNT(*) FROM admin");
            if ($stmt->fetchColumn() == 0) {
                Admin::create($username, $password);
            }


            markAppInstalled($pdo);
            $_SESSION['install_completed'] = true;

            header('Location: /install');
            exit;
        } catch (Throwable $e) {
            $error = '安装失败: ' . $e->getMessage();
        }
    }
}

if ($alreadyInstalled && $allOk) {
    $step = 'installed';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $allOk) {
    $step = 'setup';
} else {
    $step = 'check';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="only light">
    <title>Z-Blog安装向导</title>
    <link rel="icon" href="/assets/img/ZMoon.png" type="image/png">
    <!-- 关键内联样式：确保外部 CSS 下载完成前，首屏背景、窗口和加载层已经具备正确视觉效果。
         公共样式表会声明 MiSans；下方预加载 Regular 字重以减少字体切换。 -->
    <style>
        html, body { margin: 0; padding: 0; background: #f2f2f7; }
        body {
            visibility: visible;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
            background-image: url("/assets/img/backgrounds/install-desktop.jpeg");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            font-family: 'MiSans', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
            font-weight: 400;
        }
        .installer-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            opacity: 1;
            pointer-events: none;
        }
        body.resources-ready .installer-wrapper { opacity: 1; pointer-events: auto; }
        .window {
            background: rgba(255, 255, 255, 0.55);
            backdrop-filter: blur(12px) saturate(120%);
            -webkit-backdrop-filter: blur(12px) saturate(120%);
            border: 0.5px solid rgba(255, 255, 255, 0.65);
            border-radius: 20px;
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.08), 0 2px 12px rgba(0, 0, 0, 0.04), inset 0 0.5px 0 rgba(255, 255, 255, 0.7);
            overflow: hidden;
        }
        .loading-screen {
            position: fixed; inset: 0; z-index: 99999;
            background: #f2f2f7;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            gap: 20px;
        }
        @media (max-width: 768px) {
            body {
                background-image: url("/assets/img/backgrounds/install-mobile-1.png");
                background-attachment: scroll;
            }
        }
        .loading-screen.fade-out { opacity: 0; pointer-events: none; transition: opacity 0.5s ease; }
        .loading-ring {
            width: 32px; height: 32px;
            border: 3px solid rgba(0, 122, 255, 0.12);
            border-top-color: #007aff;
            border-radius: 50%;
            animation: __loadingSpin 0.75s linear infinite;
        }
        .loading-text { font-size: 12px; color: #aeaeb2; }
        @keyframes __loadingSpin { to { transform: rotate(360deg); } }
    </style>
    <link rel="preload" href="/assets/font/MiSans/woff2/MiSans-Regular.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/assets/css/install/index.css?v=<?= time() ?>">
</head>
<body>

<!-- 加载层 -->
<div class="loading-screen" id="loadingScreen">
    <div class="loading-ring"></div>
    <span class="loading-text">正在加载资源</span>
</div>

<div class="installer-wrapper">
    <div class="window">
        <div class="window-header">
            <span class="window-title">Z-Blog安装向导</span>
        </div>

        <div class="window-body">
            <?php if ($step === 'check'): ?>
                <div class="step-indicator">
                    <div class="step-dot active"></div>
                    <div class="step-dot"></div>
                </div>

                <div class="section-heading fade-in">
                    <h2>环境检测</h2>
                    <p><?= $alreadyInstalled ? '检测到运行环境异常，请修复后继续使用' : '检测到系统尚未完成安装，请先完成以下安装向导' ?></p>
                </div>

                <?php if (!$allOk): ?>
                    <div class="alert alert-error">
                        <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <span>部分环境检测未通过，请修复后刷新重试</span>
                    </div>
                <?php endif; ?>

                <div class="check-list">
                    <?php foreach ($checks as $i => $c): ?>
                        <div class="check-item">
                            <div class="check-item-left">
                                <div class="check-icon <?= $c['ok'] ? 'ok' : 'fail' ?>">
                                    <?php if ($c['ok']): ?>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                    <?php else: ?>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="18" y1="6" x2="6" y2="18"></line>
                                            <line x1="6" y1="6" x2="18" y2="18"></line>
                                        </svg>
                                    <?php endif; ?>
                                </div>
                                <span class="check-name"><?= htmlspecialchars($c['name']) ?></span>
                            </div>
                            <span class="check-current <?= $c['ok'] ? '' : 'fail' ?>" title="<?= htmlspecialchars($c['current']) ?>"><?= htmlspecialchars($c['current']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($allOk): ?>
                    <form method="post">
                        <input type="hidden" name="action" value="next">
                        <button type="submit" class="btn btn-primary">
                            <span>继续</span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </button>
                    </form>
                <?php else: ?>
                    <button class="btn btn-primary" disabled>环境检测未通过</button>
                <?php endif; ?>

            <?php elseif ($step === 'setup'): ?>
                <div class="step-indicator">
                    <div class="step-dot done"></div>
                    <div class="step-dot active"></div>
                </div>

                <div class="section-heading fade-in">
                    <h2>创建管理员</h2>
                    <p>设置后台管理账号的用户名和密码</p>
                </div>

                <div class="alert alert-error" id="clientError" style="display: none;">
                    <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span id="clientErrorText"></span>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="post" class="fade-in" style="animation-delay: 0.1s" onsubmit="return validateInstallerForm()">
                    <div class="form-group">
                        <label class="form-label" for="username">用户名</label>
                        <input class="form-input" type="text" id="username" name="username" placeholder="输入管理员用户名" autocomplete="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">密码</label>
                        <div class="password-wrapper">
                            <input class="form-input" type="password" id="password" name="password" placeholder="至少 6 位字符" autocomplete="new-password">
                            <button type="button" class="password-toggle" onclick="togglePassword('password', this)" aria-label="显示密码">
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="eye-closed" style="display:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm">确认密码</label>
                        <div class="password-wrapper">
                            <input class="form-input" type="password" id="confirm" name="confirm" placeholder="再次输入密码" autocomplete="new-password">
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm', this)" aria-label="显示密码">
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="eye-closed" style="display:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success" id="installSubmitBtn" style="margin-top: 6px">
                        <span class="btn-spinner" aria-hidden="true"></span>
                        <svg class="btn-submit-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        <span class="btn-text">完成安装</span>
                    </button>
                </form>

            <?php elseif (in_array($step, ['already_installed', 'installed'], true)): ?>
                <?php
                    $isFirstCompleted = (bool) ($_SESSION['install_completed'] ?? false);
                    unset($_SESSION['install_completed']);
                ?>

                <div class="success-visual fade-in">
                    <div class="success-circle">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>
                    <div class="success-text">
                        <?php if ($isFirstCompleted): ?>
                            <h3>安装完成</h3>
                            <p>系统已成功初始化，现在可以开始使用了</p>
                        <?php else: ?>
                            <h3>初始化已完成</h3>
                            <p>系统已经完成安装，请访问首页继续使用</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="action-links">
                    <a href="/" class="btn btn-primary">
                        <span>访问首页</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </a>
                    <?php if ($isFirstCompleted): ?>
                        <a href="/admin/login" class="btn btn-ghost">进入后台管理</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="window-footer">
            遇到问题可加入 Q 群 859278686 讨论
        </div>
    </div>
</div>

<script src="/assets/js/install/index.js?v=<?= time() ?>"></script>
</body>
</html>
