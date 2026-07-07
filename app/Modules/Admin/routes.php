<?php

declare(strict_types=1);

use App\Core\Routing\Router;
use App\Modules\Admin\Controllers\DashboardController;

return static function (Router $router): void {
    $router->any('/admin', [DashboardController::class, 'index']);
    $router->any('/admin/api/server-metrics', [DashboardController::class, 'serverMetrics']);
};
