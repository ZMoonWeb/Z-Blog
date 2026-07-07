<?php

declare(strict_types=1);

namespace App\Core\Security;

use App\Core\Config;

class PasswordPolicy
{
    private const WEAK_PASSWORDS = [
        'password',
        'password123',
        '123456',
        '12345678',
        '123456789',
        'qwerty123',
        'admin123',
        'zblog123',
    ];

    public static function validate(string $password, array $rules = []): array
    {
        $rules = array_merge([
            'min_length' => (int) Config::get('security.password.min_length', 6),
            'require_letter' => (bool) Config::get('security.password.require_letter', true),
            'require_number' => (bool) Config::get('security.password.require_number', true),
            'reject_common' => (bool) Config::get('security.password.reject_common', true),
        ], $rules);

        $errors = [];
        $minLength = max(1, (int) $rules['min_length']);

        if (mb_strlen($password) < $minLength) {
            $errors[] = '密码长度不能少于 ' . $minLength . ' 位';
        }

        if ($rules['require_letter'] && !preg_match('/[A-Za-z]/', $password)) {
            $errors[] = '密码至少需要包含一个字母';
        }

        if ($rules['require_number'] && !preg_match('/\d/', $password)) {
            $errors[] = '密码至少需要包含一个数字';
        }

        if ($rules['reject_common'] && in_array(strtolower($password), self::WEAK_PASSWORDS, true)) {
            $errors[] = '密码过于常见，请更换更安全的密码';
        }

        return $errors;
    }

    public static function passes(string $password, array $rules = []): bool
    {
        return self::validate($password, $rules) === [];
    }
}
