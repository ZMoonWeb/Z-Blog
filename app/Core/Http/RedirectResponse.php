<?php

declare(strict_types=1);

namespace App\Core\Http;

class RedirectResponse extends Response
{
    public function __construct(string $url, int $statusCode = 302, array $headers = [])
    {
        $url = str_replace(["\r", "\n"], '', $url);

        parent::__construct('', $statusCode, array_merge([
            'Location' => $url,
        ], $headers));
    }
}
