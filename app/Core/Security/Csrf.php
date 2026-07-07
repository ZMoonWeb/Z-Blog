<?php

declare(strict_types=1);

namespace App\Core\Security;

use App\Core\Http\Request;

class Csrf
{
    private const SESSION_KEY = '_csrf_tokens';
    private const DEFAULT_NAMESPACE = 'default';
    private const INPUT_NAME = '_csrf';

    public static function token(string $namespace = self::DEFAULT_NAMESPACE): string
    {
        SessionManager::start();

        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        if (empty($_SESSION[self::SESSION_KEY][$namespace]) || !is_string($_SESSION[self::SESSION_KEY][$namespace])) {
            $_SESSION[self::SESSION_KEY][$namespace] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY][$namespace];
    }

    public static function regenerate(string $namespace = self::DEFAULT_NAMESPACE): string
    {
        SessionManager::start();

        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        $_SESSION[self::SESSION_KEY][$namespace] = bin2hex(random_bytes(32));

        return $_SESSION[self::SESSION_KEY][$namespace];
    }

    public static function field(string $namespace = self::DEFAULT_NAMESPACE): string
    {
        return '<input type="hidden" name="' . self::INPUT_NAME . '" value="' . htmlspecialchars(self::token($namespace), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function validate(?string $token = null, string $namespace = self::DEFAULT_NAMESPACE): bool
    {
        SessionManager::start();

        $expected = $_SESSION[self::SESSION_KEY][$namespace] ?? '';
        if (!is_string($expected) || $expected === '') {
            return false;
        }

        $token = $token ?? self::tokenFromGlobals();

        return is_string($token) && hash_equals($expected, $token);
    }

    public static function validateRequest(Request $request, string $namespace = self::DEFAULT_NAMESPACE): bool
    {
        $token = $request->post(self::INPUT_NAME)
            ?? $request->header('X-CSRF-Token')
            ?? $request->header('X-CSRF');

        return self::validate(is_string($token) ? $token : null, $namespace);
    }

    public static function inputName(): string
    {
        return self::INPUT_NAME;
    }

    private static function tokenFromGlobals(): ?string
    {
        $token = $_POST[self::INPUT_NAME]
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_SERVER['HTTP_X_CSRF']
            ?? null;

        return is_string($token) ? $token : null;
    }
}
