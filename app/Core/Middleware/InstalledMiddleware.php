<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Database;

class InstalledMiddleware
{
    public function handle(callable $next): mixed
    {
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        if ($path === '/install') {
            return $next();
        }

        if ($this->isInstalled()) {
            return $next();
        }

        http_response_code(503);
        echo '系统尚未安装，请先完成安装。';

        return null;
    }

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
        } catch (\Throwable) {
            return false;
        }
    }
}