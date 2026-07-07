<?php

declare(strict_types=1);

return [
    'csrf' => filter_var($_ENV['SECURITY_CSRF'] ?? true, FILTER_VALIDATE_BOOLEAN),
    'headers' => [
        'enabled' => filter_var($_ENV['SECURITY_HEADERS'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'referrer_policy' => $_ENV['SECURITY_REFERRER_POLICY'] ?? 'strict-origin-when-cross-origin',
        'permissions_policy' => $_ENV['SECURITY_PERMISSIONS_POLICY'] ?? 'camera=(), microphone=(), geolocation=()',
        'frame_options' => $_ENV['SECURITY_FRAME_OPTIONS'] ?? 'SAMEORIGIN',
    ],
    'csp' => [
        'enabled' => filter_var($_ENV['SECURITY_CSP'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'report_only' => filter_var($_ENV['SECURITY_CSP_REPORT_ONLY'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'value' => $_ENV['SECURITY_CSP_VALUE']
            ?? "default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline'; font-src 'self' data: https://cdnjs.cloudflare.com; object-src 'none'; base-uri 'self'; frame-ancestors 'self'",
    ],
    'password' => [
        'min_length' => (int) ($_ENV['SECURITY_PASSWORD_MIN_LENGTH'] ?? 6),
        'require_letter' => filter_var($_ENV['SECURITY_PASSWORD_REQUIRE_LETTER'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'require_number' => filter_var($_ENV['SECURITY_PASSWORD_REQUIRE_NUMBER'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'reject_common' => filter_var($_ENV['SECURITY_PASSWORD_REJECT_COMMON'] ?? true, FILTER_VALIDATE_BOOLEAN),
    ],
];
