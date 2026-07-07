<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\AdminActivityLog;
use App\Models\AdminLoginAttempt;
use App\Models\Comment;
use App\Models\GuestbookMessage;
use App\Models\Like;
use App\Models\PostInteractionLog;
use App\Models\SiteContent;
use App\Core\Routing\Router as RouteTable;

class Router
{
    private function isInstalled(): bool
    {
        try {
            $db = Database::getInstance();

            $stmt = $db->query("SHOW TABLES LIKE 'system_settings'");
            if ($stmt->fetch() === false) {
                return false;
            }

            $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'installed' LIMIT 1");
            $stmt->execute();

            return $stmt->fetchColumn() === '1';
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function dispatch(): void
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri = trim(is_string($uri) ? $uri : '', '/');

        // 允许未安装时直接访问安装页面。
        if ($uri === 'install') {
            $installFile = dirname(__DIR__, 2) . '/install.php';
            if (file_exists($installFile)) {
                require_once $installFile;
                return;
            }
            http_response_code(404);
            $this->renderError('404', '安装文件不存在');
            return;
        }

        // 路由分发前只检查系统是否已经安装。
        // 缺失扩展等环境问题统一交给安装向导展示。
        if (!$this->isInstalled()) {
            $this->renderNotInstalled();
            return;
        }

        $this->runLightMigrations();

        if ($this->dispatchRouteTable()) {
            return;
        }

        http_response_code(404);
        $this->renderError('404', '页面未找到');
    }

    private function dispatchRouteTable(): bool
    {
        $router = new RouteTable();
        $basePath = dirname(__DIR__, 2);

        foreach ($this->routeFiles($basePath) as $path) {
            if (!is_file($path)) {
                continue;
            }

            $routes = require $path;
            if (is_callable($routes)) {
                $routes($router);
            }
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';

        return $router->dispatch((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), $path);
    }

    private function routeFiles(string $basePath): array
    {
        $files = [];

        foreach (['routes/web.php', 'routes/admin.php', 'routes/api.php'] as $routeFile) {
            $files[] = $basePath . DIRECTORY_SEPARATOR . $routeFile;
        }

        foreach ((array) Config::get('modules.enabled', []) as $module) {
            $module = (string) $module;
            if ($module === '' || preg_match('/[^A-Za-z0-9_]/', $module) === 1) {
                continue;
            }

            $files[] = $basePath . DIRECTORY_SEPARATOR . 'app'
                . DIRECTORY_SEPARATOR . 'Modules'
                . DIRECTORY_SEPARATOR . $module
                . DIRECTORY_SEPARATOR . 'routes.php';
        }

        return $files;
    }

    private function runLightMigrations(): void
    {
        try {
            $db = Database::getInstance();

            $stmt = $db->query("SHOW COLUMNS FROM posts LIKE 'content_mode'");
            if ($stmt->fetch() === false) {
                $db->exec("ALTER TABLE posts ADD COLUMN content_mode VARCHAR(20) NOT NULL DEFAULT 'markdown' AFTER content");
            }

            $stmt = $db->query("SHOW COLUMNS FROM posts LIKE 'tags'");
            if ($stmt->fetch() === false) {
                $db->exec("ALTER TABLE posts ADD COLUMN tags VARCHAR(500) DEFAULT NULL AFTER category_id");
            }

            Comment::createTable();
            Like::createTable();
            PostInteractionLog::createTable();
            AdminLoginAttempt::createTable();
            AdminActivityLog::createTable();
            GuestbookMessage::createTable();
            SiteContent::seedDefaults();
        } catch (\Throwable $e) {
            // 保持路由可用；详细数据库错误会在使用受影响功能时暴露。
        }
    }

    private function renderNotInstalled(): void
    {
        http_response_code(503);

        $cssVersion = @filemtime(dirname(__DIR__, 2) . '/public/assets/css/front/not-installed.css') ?: time();

        echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="only light">
    <title>系统尚未安装</title>
    <link rel="icon" href="/assets/img/ZMoon.png" type="image/png">
    <link rel="stylesheet" href="/assets/css/front/not-installed.css?v={$cssVersion}">
</head>
<body>
    <main class="not-installed-message">系统尚未安装，请先完成安装。</main>
</body>
</html>
HTML;
    }

    private function renderError(string $code, string $message): void
    {
        $cssVersion = @filemtime(dirname(__DIR__, 2) . '/public/assets/css/common/error.css') ?: time();
        echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$code} - {$message}</title>
    <link rel="icon" href="/assets/img/ZMoon.png" type="image/png">
    <link rel="stylesheet" href="/assets/css/common/error.css?v={$cssVersion}">
</head>
<body>
    <div class="container">
        <h1>{$code}</h1>
        <p>{$message}</p>
        <a href="/">返回首页</a>
    </div>
</body>
</html>
HTML;
    }
}
