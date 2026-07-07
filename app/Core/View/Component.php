<?php

declare(strict_types=1);

namespace App\Core\View;

class Component
{
    private string $name;
    private array $data;
    private View $view;

    public function __construct(string $name, array $data = [], ?View $view = null)
    {
        $this->name = $name;
        $this->data = $data;
        $this->view = $view ?? new View();
    }

    public static function make(string $name, array $data = []): self
    {
        return new self($name, $data);
    }

    public function render(): string
    {
        return $this->view->component($this->name, $this->data);
    }

    public function __toString(): string
    {
        try {
            return $this->render();
        } catch (\Throwable) {
            return '';
        }
    }
}
