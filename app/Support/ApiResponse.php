<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ApiResponse
{
    public static function success(
        Request $request,
        mixed $data,
        ?ApiFilter $filter = null,
        ?ApiPage $page = null,
    ): JsonResponse {
        $links = ['self' => $request->fullUrl()];
        $meta = [
            'api_version' => 'v1',
            'read_at' => self::readAt($request),
            'reporting_as_of' => ($filter?->reportingAsOf ?? CarbonImmutable::now('Asia/Jakarta'))->toDateString(),
            'scope' => self::scope($request),
            'filters' => $filter?->toArray() ?? [],
            'request_id' => self::requestId($request),
        ];

        if ($page !== null) {
            $meta['pagination'] = [
                'size' => $page->size,
                'has_more' => $page->hasMore,
                'next_cursor' => $page->nextCursor,
                'prev_cursor' => $page->previousCursor,
            ];
            if ($page->nextCursor !== null) {
                $links['next'] = self::pageLink($request, $page->nextCursor);
            }
            if ($page->previousCursor !== null) {
                $links['prev'] = self::pageLink($request, $page->previousCursor);
            }
        }

        return response()
            ->json(['data' => $data, 'meta' => $meta, 'links' => $links])
            ->header('X-Request-Id', self::requestId($request));
    }

    /** @param array<string,mixed> $details */
    public static function error(Request $request, string $code, int $status, string $message, array $details = []): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== []) {
            $error['details'] = $details;
        }

        return response()
            ->json([
                'errors' => [$error],
                'meta' => [
                    'api_version' => 'v1',
                    'request_id' => self::requestId($request),
                ],
            ], $status)
            ->header('X-Request-Id', self::requestId($request));
    }

    public static function requestId(Request $request): string
    {
        $requestId = $request->attributes->get('api_request_id');
        if (! is_string($requestId) || $requestId === '') {
            $requestId = (string) Str::uuid();
            $request->attributes->set('api_request_id', $requestId);
        }

        return $requestId;
    }

    public static function readAt(Request $request): string
    {
        $readAt = $request->attributes->get('api_read_at');
        if (! $readAt instanceof CarbonImmutable) {
            $readAt = CarbonImmutable::now('UTC');
            $request->attributes->set('api_read_at', $readAt);
        }

        return $readAt->toISOString();
    }

    /** @return array{type:string,mitra:string|null} */
    private static function scope(Request $request): array
    {
        $principal = $request->attributes->get(ApiKeyPrincipal::ATTRIBUTE);

        return $principal instanceof ApiKeyPrincipal
            ? ['type' => $principal->isThc() ? 'thc' : 'mitra', 'mitra' => $principal->mitraCode()]
            : ['type' => 'unknown', 'mitra' => null];
    }

    private static function pageLink(Request $request, string $cursor): string
    {
        $query = $request->query();
        $query['page'] = array_merge(is_array($query['page'] ?? null) ? $query['page'] : [], ['cursor' => $cursor]);

        return $request->url().'?'.http_build_query($query);
    }
}
