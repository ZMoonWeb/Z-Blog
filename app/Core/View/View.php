<?php

declare(strict_types=1);

namespace App\Core\View;

use RuntimeException;

class View
{
    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = rtrim($basePath ?? dirname(__DIR__, 3) . '/resources/views', '/\\');
    }

    public static function make(?string $basePath = null): self
    {
        return new self($basePath);
    }

    public function render(string $view, array $data = []): string
    {
        $viewFile = $this->resolve($view);

        ob_start();
        try {
            extract($data, EXTR_SKIP);
            require $viewFile;

            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            throw $e;
        }
    }

    public function component(string $name, array $data = []): string
    {
        return $this->render('components/' . $name, $data);
    }

    public function exists(string $view): bool
    {
        try {
            $this->resolve($view);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    private function resolve(string $view): string
    {
        $view = str_replace(['\\', '.'], '/', trim($view));
        $view = trim($view, '/');

        if ($view === '' || str_contains($view, '..')) {
            throw new RuntimeException('Invalid view path');
        }

        $path = $this->basePath . DIRECTORY_SEPARATOR . $view . '.php';
        $realBase = realpath($this->basePath);
        $realPath = realpath($path);

        if (!is_string($realBase) || !is_string($realPath) || !is_file($realPath)) {
            throw new RuntimeException('View not found: ' . $view);
        }

        if (!str_starts_with($realPath, $realBase . DIRECTORY_SEPARATOR) && $realPath !== $realBase) {
            throw new RuntimeException('View path escapes base directory');
        }

        return $realPath;
    }
}
