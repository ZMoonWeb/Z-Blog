<?php

declare(strict_types=1);

namespace App\Core\Http;

class Response
{
    protected string $content;
    protected int $statusCode;
    protected array $headers;

    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = [];

        foreach ($headers as $name => $value) {
            $this->header((string) $name, $value);
        }
    }

    public static function make(string $content = '', int $statusCode = 200, array $headers = []): self
    {
        return new self($content, $statusCode, $headers);
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function header(string $name, string|array $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function withHeader(string $name, string|array $value): self
    {
        return $this->header($name, $value);
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);

            foreach ($this->headers as $name => $value) {
                foreach ((array) $value as $singleValue) {
                    header($name . ': ' . (string) $singleValue, true);
                }
            }
        }

        echo $this->content;
    }
}
