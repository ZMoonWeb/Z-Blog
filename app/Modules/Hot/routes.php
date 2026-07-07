<?php

declare(strict_types=1);

use App\Core\Routing\Router;
use App\Modules\Home\Controllers\HomeController;

return static function (Router $router): void {
    $router->any('/hot', static function (): void {
        (new HomeController())->index('hot');
    });
};
