<?php

declare(strict_types=1);

return [
    'name' => $_ENV['SESSION_NAME'] ?? 'zblog_session',
    'admin_name' => $_ENV['SESSION_ADMIN_NAME'] ?? 'zblog_admin_session',
    'ttl' => (int) ($_ENV['SESSION_TTL'] ?? 7200),
    'regenerate_interval' => (int) ($_ENV['SESSION_REGENERATE_INTERVAL'] ?? 900),
    'same_site' => $_ENV['SESSION_SAME_SITE'] ?? 'Lax',
    'secure' => array_key_exists('SESSION_SECURE', $_ENV)
        ? filter_var($_ENV['SESSION_SECURE'], FILTER_VALIDATE_BOOLEAN)
        : null,
    'http_only' => filter_var($_ENV['SESSION_HTTP_ONLY'] ?? true, FILTER_VALIDATE_BOOLEAN),
];
