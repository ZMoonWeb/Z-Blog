<?php

declare(strict_types=1);

use App\Core\Middleware\CsrfMiddleware;
use App\Core\Routing\Router;
use App\Modules\Announcement\Controllers\AdminAnnouncementController;
use App\Modules\Home\Controllers\HomeController;

return static function (Router $router): void {
    $router->any('/notice', static function (): void {
        (new HomeController())->index('notice');
    });

    $router->any('/admin/announcements', [AdminAnnouncementController::class, 'index']);
    $router->any('/admin/announcements/create', [AdminAnnouncementController::class, 'create'])->middleware(CsrfMiddleware::class);
    $router->any('/admin/announcements/{id:\d+}/edit', static function (string $id): void {
        (new AdminAnnouncementController())->edit((int) $id);
    })->middleware(CsrfMiddleware::class);
    $router->any('/admin/announcements/{id:\d+}/delete', static function (string $id): void {
        (new AdminAnnouncementController())->delete((int) $id);
    })->middleware(CsrfMiddleware::class);
};
