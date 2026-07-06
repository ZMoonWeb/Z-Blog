<?php

declare(strict_types=1);

namespace App\Core;

class App
{
    public function run(): void
    {
        $router = new Router();
        $router->dispatch();
    }
}
