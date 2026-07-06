<?php

declare(strict_types=1);

return [
    'channel' => $_ENV['LOG_CHANNEL'] ?? 'single',
    'level' => $_ENV['LOG_LEVEL'] ?? 'info',
    'path' => $_ENV['LOG_PATH'] ?? 'storage/logs/app.log',
    'max_files' => (int) ($_ENV['LOG_MAX_FILES'] ?? 14),
    'requests' => filter_var($_ENV['LOG_REQUESTS'] ?? true, FILTER_VALIDATE_BOOLEAN),
];