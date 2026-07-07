<?php

declare(strict_types=1);

use App\Core\Middleware\CsrfMiddleware;
use App\Core\Routing\Router;
use App\Modules\SiteSetting\Controllers\AdminSettingController;

return static function (Router $router): void {
    $router->post('/admin/settings', [AdminSettingController::class, 'update'])->middleware(CsrfMiddleware::class);
    $router->any('/admin/settings', [AdminSettingController::class, 'index']);
    $router->any('/admin/backend-settings', [AdminSettingController::class, 'backend']);
};
