<?php

namespace App\Support;

final class ApiCursor
{
    public static function encode(string $endpoint, ApiFilter $filter, ApiKeyPrincipal $principal, int $offset): string
    {
        $payload = base64_encode(json_encode([
            'version' => 1,
            'endpoint' => $endpoint,
            'filter' => $filter->fingerprint(),
            'scope' => $principal->scopeKey(),
            'offset' => $offset,
        ], JSON_THROW_ON_ERROR));
        $body = rtrim(strtr($payload, '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $body, (string) config('app.key', 'pms-api-cursor'));

        return $body.'.'.$signature;
    }

    public static function decode(string $cursor, string $endpoint, ApiFilter $filter, ApiKeyPrincipal $principal): int
    {
        [$body, $signature] = array_pad(explode('.', $cursor, 2), 2, null);
        $expected = hash_hmac('sha256', (string) $body, (string) config('app.key', 'pms-api-cursor'));
        if ($body === '' || $signature === null || ! hash_equals($expected, $signature)) {
            throw new ApiException('invalid_parameter', 422, 'Cursor API tidak valid.');
        }

        $decoded = base64_decode(strtr($body, '-_', '+/').'===', true);
        $payload = is_string($decoded) ? json_decode($decoded, true) : null;
        if (! is_array($payload)
            || $payload['version'] !== 1
            || $payload['endpoint'] !== $endpoint
            || $payload['filter'] !== $filter->fingerprint()
            || $payload['scope'] !== $principal->scopeKey()
            || ! is_int($payload['offset'])
            || $payload['offset'] < 0) {
            throw new ApiException('invalid_parameter', 422, 'Cursor API tidak sesuai dengan endpoint atau filter.');
        }

        return $payload['offset'];
    }
}
