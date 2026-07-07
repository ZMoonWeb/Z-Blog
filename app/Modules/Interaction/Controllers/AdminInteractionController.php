<?php

declare(strict_types=1);

namespace App\Modules\Interaction\Controllers;

use App\Modules\Admin\Support\AdminControllerBase;
use App\Modules\Interaction\Services\InteractionService;

class AdminInteractionController extends AdminControllerBase
{
    public function __construct(private ?InteractionService $interactions = null)
    {
        parent::__construct();
        $this->interactions ??= new InteractionService();
    }

    public function interactions(): void
    {
        $this->requireLogin();
        $this->interactions->prepareInteractionTables();

        $page = $this->paginateAdminList($this->interactions->recent(), '/admin/interactions');

        $this->render('admin/interactions/index', [
            'admin' => $_SESSION['admin'] ?? null,
            'events' => $page['items'],
            'pagination' => $page['pagination'],
        ]);
    }

    public function activity(): void
    {
        $this->requireLogin();
        $this->interactions->prepareActivityTable();

        $page = $this->paginateAdminList($this->interactions->activities(), '/admin/activity');

        $this->render('admin/activity/index', [
            'admin' => $_SESSION['admin'] ?? null,
            'activities' => $page['items'],
            'pagination' => $page['pagination'],
        ]);
    }
}
