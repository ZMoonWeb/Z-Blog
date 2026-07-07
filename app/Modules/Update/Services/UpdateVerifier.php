<?php

declare(strict_types=1);

namespace App\Modules\Update\Services;

use App\Core\Config;

class UpdateVerifier
{
    public function trustedUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $trustedHosts = array_map('strtolower', (array) Config::get('update.trusted_hosts', []));

        return $host !== '' && in_array($host, $trustedHosts, true);
    }

    public function checksumMatches(string $file, string $expectedSha256): bool
    {
        $expectedSha256 = strtolower(trim($expectedSha256));
        if ($expectedSha256 === '' || !is_file($file)) {
            return false;
        }

        $actualSha256 = hash_file('sha256', $file);

        return is_string($actualSha256) && hash_equals($expectedSha256, $actualSha256);
    }
}
