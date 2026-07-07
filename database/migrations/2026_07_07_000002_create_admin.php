<?php

declare(strict_types=1);

use App\Core\Database\Migration;
use App\Models\Admin;
use App\Models\AdminLoginAttempt;
use PDO;

return new class extends Migration {
    public function up(PDO $db): void
    {
        Admin::createTable();
        AdminLoginAttempt::createTable();
    }
};
