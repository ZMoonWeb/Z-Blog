<?php

declare(strict_types=1);

namespace App\Core\Database;

use PDO;

class MigrationRunner
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \App\Core\Database::getInstance();
    }

    public function runDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = glob(rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            $migration = require $file;
            if ($migration instanceof Migration) {
                $migration->up($this->db);
            }
        }
    }
}
