<?php

declare(strict_types=1);

namespace App\Core;

class VisitorIdentifier
{
    private const TOKEN_COOKIE = 'blog_visitor_token';
    private const FINGERPRINT_COOKIE = 'blog_browser_fingerprint';

    public static function hashes(): array
    {
        $identity = self::likeIdentity();

        return $identity['all_hashes'];
    }

    /**
     * @return array{primary_hash: string, alias_hashes: array<int, string>, all_hashes: array<int, string>}
     */
    public static function likeIdentity(): array
    {
        $hashes = [];
        $browserFingerprintHash = self::browserFingerprintHash();

        if ($browserFingerprintHash !== '') {
            $hashes[] = $browserFingerprintHash;
        }

        $tokenHash = self::tokenHash();
        if ($tokenHash !== '') {
            $hashes[] = $tokenHash;
        }

        $hashes = array_values(array_unique($hashes));

        return [
            'primary_hash' => $hashes[0] ?? '',
            'alias_hashes' => array_slice($hashes, 1),
            'all_hashes' => $hashes,
        ];
    }

    public static function tokenHash(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $token = isset($_COOKIE[self::TOKEN_COOKIE]) ? (string) $_COOKIE[self::TOKEN_COOKIE] : '';
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            $token = isset($_SESSION['visitor_token']) ? (string) $_SESSION['visitor_token'] : '';
        }

        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            $token = bin2hex(random_bytes(16));
        }

        $_SESSION['visitor_token'] = $token;
        $_COOKIE[self::TOKEN_COOKIE] = $token;

        if (!headers_sent()) {
            setcookie(self::TOKEN_COOKIE, $token, [
                'expires' => time() + 60 * 60 * 24 * 365,
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        return hash('sha256', $token);
    }

    private static function browserFingerprintHash(): string
    {
        $browserFingerprint = self::browserFingerprint();

        if ($browserFingerprint === '') {
            return '';
        }

        return hash('sha256', 'browser-fingerprint:' . $browserFingerprint);
    }

    private static function browserFingerprint(): string
    {
        $candidates = [
            $_POST['browser_fingerprint'] ?? '',
            $_SERVER['HTTP_X_BROWSER_FINGERPRINT'] ?? '',
            $_COOKIE[self::FINGERPRINT_COOKIE] ?? '',
        ];

        foreach ($candidates as $candidate) {
            $fingerprint = strtolower(trim((string) $candidate));
            if (preg_match('/^[a-f0-9]{64}$/', $fingerprint) === 1) {
                self::rememberBrowserFingerprint($fingerprint);

                return $fingerprint;
            }
        }

        return '';
    }

    private static function rememberBrowserFingerprint(string $fingerprint): void
    {
        $_COOKIE[self::FINGERPRINT_COOKIE] = $fingerprint;

        if (!headers_sent()) {
            setcookie(self::FINGERPRINT_COOKIE, $fingerprint, [
                'expires' => time() + 60 * 60 * 24 * 365,
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
    }
}
