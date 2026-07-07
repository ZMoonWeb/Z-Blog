<?php

declare(strict_types=1);

use App\Core\Routing\Router;
use App\Modules\Interaction\Controllers\AdminInteractionController;

return static function (Router $router): void {
    $router->any('/admin/interactions', [AdminInteractionController::class, 'interactions']);
    $router->any('/admin/activity', [AdminInteractionController::class, 'activity']);
};
