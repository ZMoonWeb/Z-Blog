<?php

declare(strict_types=1);

use App\Core\Middleware\CsrfMiddleware;
use App\Core\Routing\Router;
use App\Modules\Update\Controllers\AdminUpdateController;

return static function (Router $router): void {
    $router->any('/admin/api/check-update', [AdminUpdateController::class, 'check'])->middleware(CsrfMiddleware::class);
    $router->any('/admin/api/update-notes', [AdminUpdateController::class, 'notes'])->middleware(CsrfMiddleware::class);
    $router->any('/admin/api/apply-update', [AdminUpdateController::class, 'apply'])->middleware(CsrfMiddleware::class);
};
