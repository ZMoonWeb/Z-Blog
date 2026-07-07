<?php

declare(strict_types=1);

use App\Core\Middleware\CsrfMiddleware;
use App\Core\Routing\Router;
use App\Modules\Profile\Controllers\AdminProfileController;
use App\Modules\Profile\Controllers\ProfileController;

return static function (Router $router): void {
    $router->post('/admin/profile', [AdminProfileController::class, 'update'])->middleware(CsrfMiddleware::class);
    $router->any('/admin/profile', [AdminProfileController::class, 'show']);
    $router->any('/me', [ProfileController::class, 'show']);
};
