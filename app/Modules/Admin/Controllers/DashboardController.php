<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Modules\Admin\Services\DashboardService;
use App\Modules\Admin\Services\ServerMetricsService;
use App\Modules\Admin\Support\AdminControllerBase;

class DashboardController extends AdminControllerBase
{
    public function __construct(
        private ?DashboardService $dashboard = null,
        private ?ServerMetricsService $serverMetrics = null
    ) {
        parent::__construct();
        $this->serverMetrics ??= new ServerMetricsService();
        $this->dashboard ??= new DashboardService($this->serverMetrics);
    }

    public function index(): void
    {
        $this->requireLogin();

        $this->render('admin/dashboard', $this->dashboard->dashboardData());
    }

    public function serverMetrics(): void
    {
        $this->requireLogin();
        $this->startSession();

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');

        echo json_encode($this->serverMetrics->metricsPayload(), JSON_UNESCAPED_UNICODE);
    }
}
