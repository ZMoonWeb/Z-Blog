<?php

declare(strict_types=1);

return [
    'max_size' => (int) ($_ENV['UPLOAD_MAX_SIZE'] ?? (2 * 1024 * 1024)),
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
    'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    'path' => $_ENV['UPLOAD_PATH'] ?? 'public/uploads',
];
