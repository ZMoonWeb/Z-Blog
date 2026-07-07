<?php

declare(strict_types=1);

namespace App\Modules\Interaction\Services;

use App\Modules\Interaction\Repositories\ActivityRepository;
use App\Modules\Interaction\Repositories\InteractionRepository;

class InteractionService
{
    public function __construct(
        private ?InteractionRepository $interactions = null,
        private ?ActivityRepository $activities = null
    ) {
        $this->interactions ??= new InteractionRepository();
        $this->activities ??= new ActivityRepository();
    }

    public function prepareInteractionTables(): void
    {
        $this->interactions->createTables();
    }

    public function recent(int $limit = 300): array
    {
        return $this->interactions->recent($limit);
    }

    public function prepareActivityTable(): void
    {
        $this->activities->createTable();
    }

    public function activities(int $limit = 500): array
    {
        return $this->activities->all($limit);
    }

    public function recordActivity(string $action, array $data = []): void
    {
        $this->activities->record($action, $data);
    }

    public function pruneActivities(int $olderThanDays = 90): void
    {
        $this->activities->prune($olderThanDays);
    }
}
