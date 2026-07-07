<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

class ServerMetricsService
{
    public function phpVersion(): string
    {
        return PHP_VERSION;
    }

    public function serverInfo(): array
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

        $cpuPercent = null;
        $cpuCores = null;
        $cpuModel = '未知';
        if ($isLinux) {
            $cpuinfo = $this->readSystemFile('/proc/cpuinfo');
            if ($cpuinfo !== null) {
                $cpuCores = substr_count($cpuinfo, 'processor');
                if (preg_match('/model name\s*:\s*(.+)/', $cpuinfo, $cm)) {
                    $cpuModel = trim($cm[1]);
                }
            }

            $cpuPercent = $this->sampleCpuUsage();
        }

        $mem = ['total' => 0, 'available' => 0, 'used' => 0, 'percent' => null];
        $raw = $isLinux ? $this->readSystemFile('/proc/meminfo') : null;
        if ($raw !== null) {
            $get = static function (string $key) use ($raw): int {
                return preg_match('/^' . $key . ':\s+(\d+)/m', $raw, $m) ? (int) $m[1] : 0;
            };

            $totalKb = $get('MemTotal');
            $freeKb = $get('MemFree');
            $buffersKb = $get('Buffers');
            $cachedKb = $get('Cached') + $get('SReclaimable');
            $availKb = $freeKb + $buffersKb + $cachedKb;
            $mem['total'] = $totalKb * 1024;
            $mem['available'] = $availKb * 1024;
            $mem['used'] = max(0, $mem['total'] - $mem['available']);
            $mem['percent'] = $mem['total'] > 0 ? round($mem['used'] / $mem['total'] * 100, 1) : null;
        }

        $diskFree = function_exists('disk_free_space') ? (float) @disk_free_space('/') : 0.0;
        $diskTotal = function_exists('disk_total_space') ? (float) @disk_total_space('/') : 0.0;
        $diskUsed = max(0, $diskTotal - $diskFree);
        $diskPercent = $diskTotal > 0 ? round($diskUsed / $diskTotal * 100, 1) : null;

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

    public function metricsPayload(): array
    {
        $info = $this->serverInfo();
        $cpu = $info['cpu'] ?? [];
        $memory = $info['memory'] ?? [];
        $disk = $info['disk'] ?? [];

        return [
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
        ];
    }

    private function sampleCpuUsage(): ?float
    {
        $read = function (): ?array {
            $raw = $this->readSystemFile('/proc/stat');
            if ($raw === null || !preg_match('/^cpu\s+(.+)/m', $raw, $m)) {
                return null;
            }

            $vals = array_map('floatval', preg_split('/\s+/', trim($m[1])));
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
}
