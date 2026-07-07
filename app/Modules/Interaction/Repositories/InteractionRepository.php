<?php

declare(strict_types=1);

namespace App\Modules\Interaction\Repositories;

use App\Models\Comment;
use App\Models\Like;
use App\Models\PostInteractionLog;

class InteractionRepository
{
    public function createTables(): void
    {
        Comment::createTable();
        Like::createTable();
        PostInteractionLog::createTable();
    }

    public function recent(int $limit = 300): array
    {
        return PostInteractionLog::allWithPosts($limit);
    }

    public function record(string $action, array $data): void
    {
        PostInteractionLog::record($action, $data);
    }
}
