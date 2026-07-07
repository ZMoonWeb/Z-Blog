<?php

declare(strict_types=1);

namespace App\Core\Security;

use App\Core\Config;

class SecurityHeaders
{
    public static function sendSecurityHeaders(): void
    {
        self::send();
    }

    public static function send(): void
    {
        if (headers_sent() || !(bool) Config::get('security.headers.enabled', true)) {
            return;
        }

        self::headerIfMissing('X-Content-Type-Options', 'nosniff');
        self::headerIfMissing('Referrer-Policy', (string) Config::get('security.headers.referrer_policy', 'strict-origin-when-cross-origin'));
        self::headerIfMissing('Permissions-Policy', (string) Config::get('security.headers.permissions_policy', 'camera=(), microphone=(), geolocation=()'));
        self::headerIfMissing('X-Frame-Options', (string) Config::get('security.headers.frame_options', 'SAMEORIGIN'));

        if ((bool) Config::get('security.csp.enabled', true)) {
            $header = (bool) Config::get('security.csp.report_only', false)
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';

            self::headerIfMissing($header, (string) Config::get('security.csp.value', self::defaultCsp()));
        }
    }

    private static function headerIfMissing(string $name, string $value): void
    {
        if ($value === '' || self::hasHeader($name)) {
            return;
        }

        header($name . ': ' . $value);
    }

    private static function hasHeader(string $name): bool
    {
        $prefix = strtolower($name) . ':';

        foreach (headers_list() as $header) {
            if (str_starts_with(strtolower($header), $prefix)) {
                return true;
            }
        }

        return false;
    }

    private static function defaultCsp(): string
    {
        return "default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline'; font-src 'self' data: https://cdnjs.cloudflare.com; object-src 'none'; base-uri 'self'; frame-ancestors 'self'";
    }
}
