<?php

declare(strict_types=1);

namespace App\Core\Http;

class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private array $query;
    private array $request;
    private array $cookies;
    private array $files;
    private array $server;
    private array $headers;
    private ?string $content = null;

    public function __construct(
        array $query = [],
        array $request = [],
        array $cookies = [],
        array $files = [],
        array $server = []
    ) {
        $this->query = $query;
        $this->request = $request;
        $this->cookies = $cookies;
        $this->files = $files;
        $this->server = $server;
        $this->method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $this->uri = (string) ($server['REQUEST_URI'] ?? '/');
        $this->path = $this->normalizePath($this->uri);
        $this->headers = $this->collectHeaders($server);
    }

    public static function capture(): self
    {
        return new self($_GET, $_POST, $_COOKIE, $_FILES, $_SERVER);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function routePath(): string
    {
        return trim($this->path, '/');
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->request;
        }

        return $this->request[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->request[$key] ?? $this->query[$key] ?? $default;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function file(string $key): mixed
    {
        return $this->files[$key] ?? null;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function header(string $name, mixed $default = null): mixed
    {
        $key = strtolower(str_replace('_', '-', $name));

        return $this->headers[$key] ?? $default;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->request);
    }

    public function content(): string
    {
        if ($this->content === null) {
            $content = file_get_contents('php://input');
            $this->content = is_string($content) ? $content : '';
        }

        return $this->content;
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        $accept = strtolower((string) $this->header('Accept', ''));

        return str_contains($accept, 'application/json') || $this->isAjax();
    }

    public function isJson(): bool
    {
        $contentType = strtolower((string) $this->header('Content-Type', ''));

        return str_contains($contentType, 'application/json');
    }

    private function normalizePath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function collectHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                $name = substr((string) $key, 5);
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = (string) $key;
            } else {
                continue;
            }

            $name = strtolower(str_replace('_', '-', $name));
            $headers[$name] = $value;
        }

        return $headers;
    }
}
