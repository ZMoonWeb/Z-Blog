<?php

declare(strict_types=1);

namespace App\Modules\Update\Services;

class UpdateManifest
{
    public function __construct(private array $data)
    {
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function version(): string
    {
        return trim((string) ($this->data['version'] ?? ''));
    }

    public function downloadUrl(): string
    {
        return trim((string) ($this->data['download_url'] ?? ''));
    }

    public function checksum(): string
    {
        return strtolower(trim((string) ($this->data['sha256'] ?? '')));
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
