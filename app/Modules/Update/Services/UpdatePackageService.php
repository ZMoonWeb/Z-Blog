<?php

declare(strict_types=1);

namespace App\Modules\Update\Services;

class UpdatePackageService
{
    public function backupPath(string $basePath, ?UpdateBackupService $backups = null): string
    {
        $backups ??= new UpdateBackupService();

        return $backups->backupPath($basePath);
    }

    public function verifyChecksum(string $file, string $expectedSha256, ?UpdateVerifier $verifier = null): bool
    {
        $verifier ??= new UpdateVerifier();

        return $verifier->checksumMatches($file, $expectedSha256);
    }
}
