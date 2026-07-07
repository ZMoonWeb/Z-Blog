<?php

declare(strict_types=1);

use App\Core\Database\Migration;
use App\Models\GuestbookMessage;
use PDO;

return new class extends Migration {
    public function up(PDO $db): void
    {
        GuestbookMessage::createTable();
    }
};
