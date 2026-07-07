<?php

declare(strict_types=1);

namespace App\Modules\Update\Services;

use App\Core\Config;

class UpdateCheckService
{
    public function manifestFromArray(array $data): UpdateManifest
    {
        return new UpdateManifest($data);
    }

    public function hasTrustedDownload(UpdateManifest $manifest, ?UpdateVerifier $verifier = null): bool
    {
        $verifier ??= new UpdateVerifier();

        return $verifier->trustedUrl($manifest->downloadUrl());
    }

    public function currentBlogVersion(): string
    {
        $version = trim((string) Config::get('app.version', '1.0.1'));
        return $version !== '' ? $version : '1.0.1';
    }

    public function updateUrl(): string
    {
        return trim((string) Config::get('app.update_check_url', ''));
    }

    public function isValidUpdateCheckUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $trustedHosts = (array) Config::get('update.trusted_hosts', []);
        if ($trustedHosts === []) {
            return true;
        }

        return (new UpdateVerifier())->trustedUrl($url);
    }

    public function updateReleaseUrl(array $remote = []): string
    {
        $url = trim((string) ($remote['release_url'] ?? $remote['github_url'] ?? $remote['repo_url'] ?? ''));
        if ($url !== '' && $this->isValidUpdateCheckUrl($url)) {
            return $url;
        }

        $fallback = trim((string) Config::get('app.update_release_url', 'https://github.com'));
        return $this->isValidUpdateCheckUrl($fallback) ? $fallback : 'https://github.com';
    }

    public function updateRequestTime(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai')))->format(DATE_ATOM);
    }

    public function beijingTime(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Shanghai')))->format('Y-m-d H:i:s');
    }

    public function normalizeUpdateNotes(array $remote): array
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

    public function isSafeUpdateVersion(string $version): bool
    {
        return $version !== ''
            && strlen($version) <= 32
            && preg_match('/^[0-9A-Za-z][0-9A-Za-z._+\-]*$/', $version) === 1;
    }

    public function requestUpdateInfo(string $url, array $fields): array
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

    public function resolveUpdateAvailable(array $remote, string $currentVersion, string $latestVersion): bool
    {
        if (array_key_exists('update_available', $remote)) {
            return $this->booleanFromMixed($remote['update_available']) === true;
        }

        if (array_key_exists('is_latest', $remote)) {
            return $this->booleanFromMixed($remote['is_latest']) !== true;
        }

        return version_compare($this->versionForCompare($latestVersion), $this->versionForCompare($currentVersion), '>');
    }

    public function versionForCompare(string $version): string
    {
        $version = trim($version);
        $version = preg_replace('/^[vV]\s*/', '', $version) ?: $version;
        return $version !== '' ? $version : '0.0.0';
    }

    private function httpStatusCode(array $headers): int
    {
        $statusLine = (string) ($headers[0] ?? '');
        if (preg_match('/\s(\d{3})\s?/', $statusLine, $matches)) {
            return (int) $matches[1];
        }

        return 200;
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
}
