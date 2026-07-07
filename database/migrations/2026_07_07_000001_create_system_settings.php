<?php

declare(strict_types=1);

use App\Core\Database\Migration;
use PDO;

return new class extends Migration {
    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
            setting_value VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
};
