<?php

declare(strict_types=1);

use App\Core\Database\Migration;
use App\Models\AdminActivityLog;
use App\Models\PostInteractionLog;
use PDO;

return new class extends Migration {
    public function up(PDO $db): void
    {
        PostInteractionLog::createTable();
        AdminActivityLog::createTable();
    }
};
