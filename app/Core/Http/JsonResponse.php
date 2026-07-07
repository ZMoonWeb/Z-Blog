<?php

declare(strict_types=1);

namespace App\Core\Http;

class JsonResponse extends Response
{
    public function __construct(mixed $data = null, int $statusCode = 200, array $headers = [])
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            $json = '{"error":"JSON encoding failed"}';
        }

        parent::__construct($json, $statusCode, array_merge([
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
        ], $headers));
    }
}
