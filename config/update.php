<?php

declare(strict_types=1);

return [
    'enabled' => filter_var($_ENV['UPDATE_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
    'trusted_hosts' => ['github.com', 'api.github.com', 'api.zmoon.top'],
    'require_checksum' => filter_var($_ENV['UPDATE_REQUIRE_CHECKSUM'] ?? true, FILTER_VALIDATE_BOOLEAN),
    'require_signature' => filter_var($_ENV['UPDATE_REQUIRE_SIGNATURE'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'backup_before_apply' => filter_var($_ENV['UPDATE_BACKUP_BEFORE_APPLY'] ?? true, FILTER_VALIDATE_BOOLEAN),
    'protected_paths' => ['.env', 'storage', 'public/uploads'],
];
