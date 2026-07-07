<?php

declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'Blog',
    'version' => trim((string) ($_ENV['APP_VERSION'] ?? '1.0.1')) ?: '1.0.1',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Asia/Shanghai',
    'update_check_url' => $_ENV['APP_UPDATE_CHECK_URL'] ?? 'https://api.zmoon.top',
    'update_release_url' => $_ENV['APP_UPDATE_RELEASE_URL'] ?? 'https://github.com',
    'posts_per_page' => (int) ($_ENV['APP_POSTS_PER_PAGE'] ?? 10),
];
