<?php

declare(strict_types=1);

namespace App\Modules\Update\Services;

class UpdateBackupService
{
    public function backupPath(string $basePath): string
    {
        return rtrim($basePath, '/\\') . '/storage/backups/update-' . date('Ymd-His') . '.zip';
    }
}
