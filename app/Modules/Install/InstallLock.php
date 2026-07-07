<?php

declare(strict_types=1);

namespace App\Modules\Install;

class InstallLock
{
    public function __construct(private string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
    }

    public function exists(): bool
    {
        return is_file($this->path());
    }

    public function write(): void
    {
        $directory = dirname($this->path());
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($this->path(), 'installed=' . date('c') . PHP_EOL, LOCK_EX);
    }

    public function path(): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'install.lock';
    }
}
