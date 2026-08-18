<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiCursor;
use App\Support\ApiException;
use App\Support\ApiFilter;
use App\Support\ApiKeyPrincipal;
use App\Support\ApiPage;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

abstract class ApiController extends Controller
{
    protected function run(Request $request, Closure $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (ApiException $exception) {
            return ApiResponse::error($request, $exception->errorCode, $exception->status, $exception->getMessage(), $exception->details);
        } catch (ValidationException $exception) {
            return ApiResponse::error($request, 'invalid_parameter', 422, 'Parameter API tidak valid.', $exception->errors());
        }
    }

    protected function principal(Request $request): ApiKeyPrincipal
    {
        $principal = $request->attributes->get(ApiKeyPrincipal::ATTRIBUTE);
        if (! $principal instanceof ApiKeyPrincipal) {
            throw new ApiException('api_key_invalid', 401, 'API Key tidak valid.');
        }

        return $principal;
    }

    protected function filters(Request $request): ApiFilter
    {
        return ApiFilter::fromRequest($request, $this->principal($request));
    }

    protected function page(Request $request, Collection $items, ApiFilter $filter, string $endpoint): ApiPage
    {
        $principal = $this->principal($request);
        $offset = $filter->cursor === null
            ? 0
            : ApiCursor::decode($filter->cursor, $endpoint, $filter, $principal);
        $window = $items->slice($offset, $filter->pageSize + 1)->values();
        $hasMore = $window->count() > $filter->pageSize;
        if ($hasMore) {
            $window = $window->take($filter->pageSize)->values();
        }
        $nextCursor = $hasMore ? ApiCursor::encode($endpoint, $filter, $principal, $offset + $filter->pageSize) : null;
        $previousCursor = $offset > 0
            ? ApiCursor::encode($endpoint, $filter, $principal, max(0, $offset - $filter->pageSize))
            : null;

        return new ApiPage($window, $filter->pageSize, $hasMore, $nextCursor, $previousCursor);
    }

    protected function success(Request $request, mixed $data, ?ApiFilter $filter = null, ?ApiPage $page = null): JsonResponse
    {
        return ApiResponse::success($request, $data, $filter, $page);
    }
}
