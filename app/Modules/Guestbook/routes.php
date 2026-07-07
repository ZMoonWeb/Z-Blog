<?php

declare(strict_types=1);

use App\Core\Middleware\CsrfMiddleware;
use App\Core\Routing\Router;
use App\Modules\Guestbook\Controllers\AdminGuestbookController;
use App\Modules\Guestbook\Controllers\GuestbookController;

return static function (Router $router): void {
    $router->post('/guestbook', [GuestbookController::class, 'store'])->middleware(CsrfMiddleware::class);
    $router->any('/guestbook', [GuestbookController::class, 'index']);
    $router->any('/guestbook/new', [GuestbookController::class, 'compose']);
    $router->any('/guestbook/{id:\d+}', static function (string $id): void {
        (new GuestbookController())->detail((int) $id);
    });

    $router->any('/admin/guestbook', [AdminGuestbookController::class, 'index']);
    $router->any('/admin/guestbook/{id:\d+}/{action:approve|hide|delete|restore}', static function (string $id, string $action): void {
        (new AdminGuestbookController())->moderate((int) $id, $action);
    })->middleware(CsrfMiddleware::class);
    $router->any('/admin/guestbook/{id:\d+}/reply', static function (string $id): void {
        (new AdminGuestbookController())->reply((int) $id);
    })->middleware(CsrfMiddleware::class);
};
