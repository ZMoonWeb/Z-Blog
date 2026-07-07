<?php

declare(strict_types=1);

use App\Core\Middleware\CsrfMiddleware;
use App\Core\Routing\Router;
use App\Modules\Auth\Controllers\AdminAuthController;

return static function (Router $router): void {
    $router->any('/admin/login', [AdminAuthController::class, 'login'])->middleware(CsrfMiddleware::class);
    $router->any('/admin/logout', [AdminAuthController::class, 'logout'])->middleware(CsrfMiddleware::class);
};
