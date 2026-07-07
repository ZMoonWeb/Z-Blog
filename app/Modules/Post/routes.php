<?php

declare(strict_types=1);

use App\Core\Middleware\CsrfMiddleware;
use App\Core\Routing\Router;
use App\Modules\Post\Controllers\AdminPostController;
use App\Modules\Post\Controllers\PostController;

return static function (Router $router): void {
    $router->any('/post/{slug:.+}/like', [PostController::class, 'like'])->middleware(CsrfMiddleware::class);
    $router->any('/post/{slug:.+}/comment', [PostController::class, 'comment'])->middleware(CsrfMiddleware::class);
    $router->any('/post/{slug:.+}', [PostController::class, 'show']);

    $router->any('/admin/posts', [AdminPostController::class, 'index']);
    $router->any('/admin/posts/create', [AdminPostController::class, 'create'])->middleware(CsrfMiddleware::class);
    $router->any('/admin/posts/{id:\d+}/edit', static function (string $id): void {
        (new AdminPostController())->edit((int) $id);
    })->middleware(CsrfMiddleware::class);
    $router->any('/admin/posts/{id:\d+}/delete', static function (string $id): void {
        (new AdminPostController())->delete((int) $id);
    })->middleware(CsrfMiddleware::class);
};
