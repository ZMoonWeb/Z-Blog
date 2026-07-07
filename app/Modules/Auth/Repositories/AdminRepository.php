<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repositories;

use App\Models\Admin;

class AdminRepository
{
    public function findByUsername(string $username): ?array
    {
        return Admin::findByUsername($username);
    }

    public function findById(int $id): ?array
    {
        return Admin::findById($id);
    }

    public function hashPassword(string $password): string
    {
        return Admin::hashPassword($password);
    }

    public function passwordNeedsRehash(string $hash): bool
    {
        return Admin::passwordNeedsRehash($hash);
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        Admin::updatePasswordHash($id, $hash);
    }
}
