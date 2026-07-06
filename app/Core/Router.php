<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\AdminController;
use App\Controllers\HomeController;
use App\Controllers\PageController;
use App\Controllers\PostController;
use App\Models\AdminActivityLog;
use App\Models\AdminLoginAttempt;
use App\Models\Comment;
use App\Models\GuestbookMessage;
use App\Models\Like;
use App\Models\PostInteractionLog;
use App\Models\SiteContent;

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
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = trim($uri, '/');

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

        if ($uri === '') {
            $controller = new HomeController();
            $controller->index();
            return;
        }

        if ($uri === 'hot') {
            $controller = new HomeController();
            $controller->index('hot');
            return;
        }

        if ($uri === 'notice') {
            $controller = new HomeController();
            $controller->index('notice');
            return;
        }

        if ($uri === 'guestbook') {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller = new PageController();
                $controller->postGuestbook();
            } else {
                $controller = new HomeController();
                $controller->index('guestbook');
            }
            return;
        }

        if ($uri === 'guestbook/new') {
            $controller = new HomeController();
            $controller->index('guestbook', 'compose');
            return;
        }

        if (preg_match('#^guestbook/(\d+)$#', $uri, $matches)) {
            $controller = new HomeController();
            $controller->index('guestbook', 'detail', (int) $matches[1]);
            return;
        }

        if ($uri === 'about') {
            $controller = new HomeController();
            $controller->index('about');
            return;
        }

        if ($uri === 'me') {
            $controller = new HomeController();
            $controller->profile();
            return;
        }

        if (preg_match('#^post/(.+)/like$#', $uri, $matches)) {
            $controller = new PostController();
            $controller->like(rawurldecode($matches[1]));
            return;
        }

        if (preg_match('#^post/(.+)/comment$#', $uri, $matches)) {
            $controller = new PostController();
            $controller->comment(rawurldecode($matches[1]));
            return;
        }

        if (preg_match('#^post/(.+)$#', $uri, $matches)) {
            $controller = new PostController();
            $controller->show(rawurldecode($matches[1]));
            return;
        }

        if ($uri === 'admin') {
            $controller = new AdminController();
            $controller->index();
            return;
        }

        if ($uri === 'admin/api/server-metrics') {
            $controller = new AdminController();
            $controller->serverMetrics();
            return;
        }

        if ($uri === 'admin/api/check-update') {
            $controller = new AdminController();
            $controller->checkUpdate();
            return;
        }

        if ($uri === 'admin/api/update-notes') {
            $controller = new AdminController();
            $controller->updateNotes();
            return;
        }

        if ($uri === 'admin/api/apply-update') {
            $controller = new AdminController();
            $controller->applyUpdate();
            return;
        }

        if ($uri === 'admin/login') {
            $controller = new AdminController();
            $controller->login();
            return;
        }

        if ($uri === 'admin/logout') {
            $controller = new AdminController();
            $controller->logout();
            return;
        }

        if ($uri === 'admin/posts') {
            $controller = new AdminController();
            $controller->posts();
            return;
        }

        if ($uri === 'admin/interactions') {
            $controller = new AdminController();
            $controller->interactions();
            return;
        }

        if ($uri === 'admin/activity') {
            $controller = new AdminController();
            $controller->activity();
            return;
        }
        if ($uri === 'admin/posts/create') {
            $controller = new AdminController();
            $controller->createPost();
            return;
        }

        if (preg_match('#^admin/posts/(\d+)/edit$#', $uri, $matches)) {
            $controller = new AdminController();
            $controller->editPost((int) $matches[1]);
            return;
        }

        if (preg_match('#^admin/posts/(\d+)/delete$#', $uri, $matches)) {
            $controller = new AdminController();
            $controller->deletePost((int) $matches[1]);
            return;
        }

        if ($uri === 'admin/settings') {
            $controller = new AdminController();
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->updateSettings();
            } else {
                $controller->settings();
            }
            return;
        }

        if ($uri === 'admin/backend-settings') {
            $controller = new AdminController();
            $controller->backendSettings();
            return;
        }

        if ($uri === 'admin/profile') {
            $controller = new AdminController();
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->updateProfile();
            } else {
                $controller->profile();
            }
            return;
        }

        if ($uri === 'admin/categories') {
            $controller = new AdminController();
            $controller->categories();
            return;
        }

        if ($uri === 'admin/categories/create') {
            $controller = new AdminController();
            $controller->createCategory();
            return;
        }

        if (preg_match('#^admin/categories/(\d+)/edit$#', $uri, $matches)) {
            $controller = new AdminController();
            $controller->editCategory((int) $matches[1]);
            return;
        }

        if (preg_match('#^admin/categories/(\d+)/delete$#', $uri, $matches)) {
            $controller = new AdminController();
            $controller->deleteCategory((int) $matches[1]);
            return;
        }

        if ($uri === 'admin/announcements') {
            $controller = new AdminController();
            $controller->announcements();
            return;
        }

        if ($uri === 'admin/announcements/create') {
            $controller = new AdminController();
            $controller->createAnnouncement();
            return;
        }

        if (preg_match('#^admin/announcements/(\d+)/edit$#', $uri, $matches)) {
            $controller = new AdminController();
            $controller->editAnnouncement((int) $matches[1]);
            return;
        }

        if (preg_match('#^admin/announcements/(\d+)/delete$#', $uri, $matches)) {
            $controller = new AdminController();
            $controller->deleteAnnouncement((int) $matches[1]);
            return;
        }

        if ($uri === 'admin/guestbook') {
            $controller = new AdminController();
            $controller->guestbook();
            return;
        }

        if (preg_match('#^admin/guestbook/(\d+)/(approve|hide|delete|restore)$#', $uri, $matches)) {
            $controller = new AdminController();
            $controller->moderateGuestbookMessage((int) $matches[1], $matches[2]);
            return;
        }

        if (preg_match('#^admin/guestbook/(\d+)/reply$#', $uri, $matches)) {
            $controller = new AdminController();
            $controller->replyGuestbookMessage((int) $matches[1]);
            return;
        }

        http_response_code(404);
        $this->renderError('404', '页面未找到');
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
