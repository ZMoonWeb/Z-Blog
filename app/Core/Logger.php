<?php

declare(strict_types=1);

namespace App\Core;

class Logger
{
    private const LEVELS = [
        'debug' => 100,
        'info' => 200,
        'notice' => 250,
        'warning' => 300,
        'error' => 400,
        'critical' => 500,
    ];

    private static bool $configured = false;
    private static string $basePath = '';
    private static string $logPath = '';
    private static string $level = 'info';
    private static int $maxFiles = 14;
    private static bool $debug = false;
    private static bool $logRequests = true;

    public static function configure(string $basePath): void
    {
        if (self::$configured) {
            return;
        }

        self::$basePath = rtrim($basePath, '/\\');
        self::$level = self::normalizeLevel((string) Config::get('logging.level', 'info'));
        self::$maxFiles = max(1, (int) Config::get('logging.max_files', 14));
        self::$debug = (bool) Config::get('app.debug', false);
        self::$logRequests = filter_var(Config::get('logging.requests', true), FILTER_VALIDATE_BOOLEAN);

        $path = trim((string) Config::get('logging.path', 'storage/logs/app.log'));
        self::$logPath = self::resolvePath($path !== '' ? $path : 'storage/logs/app.log');
        self::ensureLogDirectory();

        error_reporting(E_ALL);
        ini_set('log_errors', '1');
        ini_set('error_log', self::dailyLogPath());
        ini_set('display_errors', self::$debug ? '1' : '0');

        set_error_handler([self::class, 'handlePhpError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);

        self::$configured = true;
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::log('critical', $message, $context);
    }

    public static function exception(\Throwable $exception, string $level = 'error'): void
    {
        self::log($level, $exception->getMessage(), [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => self::compactTrace($exception),
        ]);
    }

    public static function handlePhpError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if ((error_reporting() & $severity) === 0) {
            return false;
        }

        self::log(self::levelFromSeverity($severity), $message, [
            'severity' => $severity,
            'file' => $file,
            'line' => $line,
        ]);

        return true;
    }

    public static function handleException(\Throwable $exception): void
    {
        self::exception($exception, 'critical');

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $exception->getMessage() . PHP_EOL);
            return;
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        if (self::$debug) {
            echo '<pre style="white-space:pre-wrap">' . htmlspecialchars((string) $exception, ENT_QUOTES, 'UTF-8') . '</pre>';
            return;
        }

        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Server Error</title></head><body style="font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;padding:40px;color:#111"><h1>Server Error</h1><p>The error has been written to the system log.</p></body></html>';
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if (is_array($error) && in_array((int) ($error['type'] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            self::log('critical', (string) ($error['message'] ?? 'Fatal error'), [
                'severity' => (int) ($error['type'] ?? 0),
                'file' => (string) ($error['file'] ?? ''),
                'line' => (int) ($error['line'] ?? 0),
            ]);
        }

        self::logRequestIfNeeded();
        self::pruneOldLogs();
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        $level = self::normalizeLevel($level);
        if (!self::shouldLog($level)) {
            return;
        }

        $record = [
            'time' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'request' => self::requestContext(),
        ];

        $json = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if (!is_string($json)) {
            $json = '{"level":"error","message":"Log encode failed"}';
        }

        self::ensureLogDirectory();
        @file_put_contents(self::dailyLogPath(), $json . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private static function logRequestIfNeeded(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $status = http_response_code();
        if ($status < 100) {
            $status = 200;
        }

        if (!self::$logRequests && $status < 400) {
            return;
        }

        $startedAt = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $level = $status >= 500 ? 'error' : ($status >= 400 ? 'warning' : 'info');

        self::log($level, 'HTTP request', [
            'status' => $status,
            'duration_ms' => $durationMs,
        ]);
    }

    private static function requestContext(): array
    {
        if (PHP_SAPI === 'cli') {
            return ['sapi' => 'cli'];
        }

        return [
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];
    }

    private static function levelFromSeverity(int $severity): string
    {
        return match ($severity) {
            E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED, E_STRICT => 'notice',
            E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING => 'warning',
            default => 'error',
        };
    }

    private static function compactTrace(\Throwable $exception): array
    {
        return array_slice(array_map(static function (array $frame): array {
            return [
                'file' => $frame['file'] ?? '',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? '',
                'class' => $frame['class'] ?? '',
            ];
        }, $exception->getTrace()), 0, 12);
    }

    private static function normalizeLevel(string $level): string
    {
        $level = strtolower(trim($level));
        return array_key_exists($level, self::LEVELS) ? $level : 'info';
    }

    private static function shouldLog(string $level): bool
    {
        return self::LEVELS[$level] >= self::LEVELS[self::$level];
    }

    private static function resolvePath(string $path): string
    {
        if (preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#', $path) === 1) {
            return $path;
        }

        return self::$basePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    private static function dailyLogPath(): string
    {
        $directory = dirname(self::$logPath);
        $filename = basename(self::$logPath);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = $extension !== '' ? substr($filename, 0, -strlen($extension) - 1) : $filename;
        $suffix = (new \DateTimeImmutable('now'))->format('Y-m-d');

        return $directory . DIRECTORY_SEPARATOR . $name . '-' . $suffix . ($extension !== '' ? '.' . $extension : '.log');
    }

    private static function ensureLogDirectory(): void
    {
        $directory = dirname(self::$logPath);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
    }

    private static function pruneOldLogs(): void
    {
        if (self::$maxFiles <= 0) {
            return;
        }

        $directory = dirname(self::$logPath);
        if (!is_dir($directory)) {
            return;
        }

        $filename = basename(self::$logPath);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = $extension !== '' ? substr($filename, 0, -strlen($extension) - 1) : $filename;
        $pattern = $directory . DIRECTORY_SEPARATOR . $name . '-*' . ($extension !== '' ? '.' . $extension : '.log');
        $files = glob($pattern) ?: [];
        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        foreach (array_slice($files, self::$maxFiles) as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}