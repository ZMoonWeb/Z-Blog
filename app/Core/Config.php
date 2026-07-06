<?php

declare(strict_types=1);

namespace App\Core;

use Dotenv\Dotenv;

class Config
{
    private static array $config = [];
    private static bool $loaded = false;

    public static function load(string $basePath): void
    {
        if (self::$loaded) {
            return;
        }

        $dotenv = Dotenv::createImmutable($basePath);
        $dotenv->safeLoad();

        $configDir = $basePath . '/config';
        if (is_dir($configDir)) {
            foreach (glob($configDir . '/*.php') as $file) {
                $key = basename($file, '.php');
                self::$config[$key] = require $file;
            }
        }

        $timezone = trim((string) self::get('app.timezone', 'Asia/Shanghai'));
        try {
            new \DateTimeZone($timezone);
            date_default_timezone_set($timezone);
        } catch (\Throwable) {
            date_default_timezone_set('Asia/Shanghai');
        }

        Logger::configure($basePath);

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }
}
