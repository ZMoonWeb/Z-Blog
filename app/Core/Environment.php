<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

class Environment
{
    public static function check(): array
    {
        $errors = [];

        if (PHP_VERSION_ID < 80100) {
            $errors[] = 'PHP 版本需要 >= 8.1，当前版本: ' . PHP_VERSION;
        }

        $requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring'];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $errors[] = "缺少 {$ext} 扩展";
            }
        }

        if (!file_exists(dirname(__DIR__, 2) . '/.env')) {
            $errors[] = '缺少 .env 文件，请复制 .env.example 为 .env 并填写配置';
        }

        foreach (['APP_TIMEZONE' => 'app.timezone'] as $envKey => $configKey) {
            if (trim((string) Config::get($configKey, '')) === '') {
                $errors[] = '缺少 ' . $envKey . ' 配置';
            }
        }

        foreach (['mail.mailer', 'mail.host', 'mail.from.address', 'logging.channel', 'logging.level', 'logging.path'] as $configKey) {
            if (trim((string) Config::get($configKey, '')) === '') {
                $errors[] = '缺少 ' . $configKey . ' 配置';
            }
        }

        if (empty($errors)) {
            try {
                $host = Config::get('database.host');
                $port = Config::get('database.port');
                $database = Config::get('database.database');
                $username = Config::get('database.username');
                $password = Config::get('database.password');
                $charset = Config::get('database.charset');

                $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
                new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 3,
                ]);
            } catch (PDOException $e) {
                $errors[] = '数据库连接失败: ' . $e->getMessage();
            }
        }

        return $errors;
    }

    public static function renderErrors(array $errors): void
    {
        http_response_code(500);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>环境检测失败</title><link rel="icon" href="/assets/img/ZMoon.png" type="image/png">';
        echo '<style>@import url("/assets/css/common/fonts.css");body{font-family:var(--font-misans);font-weight:400;max-width:600px;margin:80px auto;padding:0 20px;color:#333}';
        echo 'h1{color:#dc3545}ul{line-height:2}li{color:#721c24;background:#f8d7da;padding:8px 16px;margin:8px 0;border-radius:4px}';
        echo '.box{background:#f8f9fa;padding:24px;border-radius:8px}</style></head><body>';
        echo '<div class="box"><h1>环境检测未通过</h1><ul>';
        foreach ($errors as $error) {
            echo "<li>{$error}</li>";
        }
        echo '</ul></div></body></html>';
    }
}
