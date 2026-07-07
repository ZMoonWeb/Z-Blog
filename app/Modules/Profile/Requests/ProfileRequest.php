<?php

declare(strict_types=1);

namespace App\Modules\Profile\Requests;

use App\Core\Security\PasswordPolicy;
use App\Models\Admin;

class ProfileRequest
{
    public function validate(array $data): array
    {
        $errors = [];
        $adminId = (int) ($data['admin_id'] ?? 0);
        $username = trim((string) ($data['username'] ?? ''));
        $currentPassword = (string) ($data['current_password'] ?? '');
        $newPassword = (string) ($data['new_password'] ?? '');
        $confirmPassword = (string) ($data['confirm_password'] ?? '');
        $profileMotto = (string) ($data['profile_motto'] ?? '');
        $hasProfileDisplayFields = (bool) ($data['has_profile_display_fields'] ?? false);

        if ($username === '') {
            $errors[] = '管理员用户名不能为空';
        } elseif (mb_strlen($username) > 50) {
            $errors[] = '管理员用户名不能超过 50 个字符';
        } else {
            $existingAdmin = Admin::findByUsername($username);
            if ($existingAdmin !== null && (int) ($existingAdmin['id'] ?? 0) !== $adminId) {
                $errors[] = '该用户名已被使用';
            }
        }

        if ($hasProfileDisplayFields && mb_strlen($profileMotto, 'UTF-8') > 300) {
            $errors[] = '个人座右铭不能超过 300 个字符';
        }

        if ($newPassword !== '') {
            if ($currentPassword === '') {
                $errors[] = '修改密码时必须输入当前密码';
            } else {
                $dbAdmin = Admin::findById($adminId);
                if ($dbAdmin === null || !password_verify($currentPassword, (string) ($dbAdmin['password'] ?? ''))) {
                    $errors[] = '当前密码不正确';
                }
            }

            foreach (PasswordPolicy::validate($newPassword) as $passwordError) {
                $errors[] = $passwordError;
            }

            if ($newPassword !== $confirmPassword) {
                $errors[] = '两次输入的新密码不一致';
            }
        }

        return $errors;
    }
}
