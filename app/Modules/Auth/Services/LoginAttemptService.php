<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Models\AdminLoginAttempt;

class LoginAttemptService
{
    public const ADMIN_LOGIN_MAX_ATTEMPTS = 3;
    public const ADMIN_LOGIN_LOCK_SECONDS = 600;
    public const ADMIN_LOGIN_WINDOW_SECONDS = 600;

    public function createTable(): void
    {
        AdminLoginAttempt::createTable();
    }

    public function status(string $ipAddress): array
    {
        return AdminLoginAttempt::status($ipAddress);
    }

    public function recordFailure(string $ipAddress, int $maxAttempts, int $lockSeconds, int $windowSeconds): array
    {
        return AdminLoginAttempt::recordFailure($ipAddress, $maxAttempts, $lockSeconds, $windowSeconds);
    }

    public function clear(string $ipAddress): void
    {
        AdminLoginAttempt::clear($ipAddress);
    }

    public function prune(int $olderThanSeconds = 86400): void
    {
        AdminLoginAttempt::prune($olderThanSeconds);
    }
}
