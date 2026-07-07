<?php

declare(strict_types=1);

use App\Core\Middleware\CsrfMiddleware;
use App\Core\Routing\Router;
use App\Modules\Category\Controllers\AdminCategoryController;

return static function (Router $router): void {
    $router->any('/admin/categories', [AdminCategoryController::class, 'index']);
    $router->any('/admin/categories/create', [AdminCategoryController::class, 'create'])->middleware(CsrfMiddleware::class);
    $router->any('/admin/categories/{id:\d+}/edit', static function (string $id): void {
        (new AdminCategoryController())->edit((int) $id);
    })->middleware(CsrfMiddleware::class);
    $router->any('/admin/categories/{id:\d+}/delete', static function (string $id): void {
        (new AdminCategoryController())->delete((int) $id);
    })->middleware(CsrfMiddleware::class);
};
