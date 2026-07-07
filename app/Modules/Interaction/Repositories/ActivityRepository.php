<?php

declare(strict_types=1);

namespace App\Modules\Interaction\Repositories;

use App\Models\AdminActivityLog;

class ActivityRepository
{
    public function createTable(): void
    {
        AdminActivityLog::createTable();
    }

    public function all(int $limit = 500): array
    {
        return AdminActivityLog::all($limit);
    }

    public function record(string $action, array $data = []): void
    {
        AdminActivityLog::record($action, $data);
    }

    public function prune(int $olderThanDays = 90): void
    {
        AdminActivityLog::prune($olderThanDays);
    }
}
