<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\View\View as ViewRenderer;

class View
{
    public static function render(string $view, array $data = []): string
    {
        return (new ViewRenderer())->render($view, $data);
    }

    public static function component(string $name, array $data = []): string
    {
        return (new ViewRenderer())->component($name, $data);
    }
}
