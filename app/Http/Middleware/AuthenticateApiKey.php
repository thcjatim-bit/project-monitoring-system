<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Services\ApiKeyService;
use App\Support\ApiKeyPrincipal;
use App\Support\ApiResponse;
use App\Support\TenantDatabaseContext;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function __construct(
        private ApiKeyService $apiKeyService,
        private TenantDatabaseContext $tenantDatabaseContext,
        private RateLimiter $rateLimiter,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        ApiResponse::requestId($request);

        $this->tenantDatabaseContext->set(null, false);

        try {
            if ($this->credentialInAlternateLocation($request)) {
                return $this->fail($request, 'duplicate_credentials', 401, 'API Key hanya boleh dikirim melalui Authorization Bearer.');
            }

            $headers = $this->authorizationHeaders($request);
            if (count($headers) !== 1) {
                return $this->fail($request, 'missing', 401, 'API Key tidak valid.');
            }

            if (! preg_match('/^Bearer\s+([^\s]+)$/', $headers[0], $matches)) {
                return $this->fail($request, 'invalid_format', 401, 'API Key tidak valid.');
            }

            $token = $matches[1];
            if (! str_starts_with($token, ApiKeyService::PREFIX)) {
                return $this->fail($request, 'invalid_format', 401, 'API Key tidak valid.');
            }

            $authenticationRetryAfter = $this->authenticationRateLimitRetryAfter($request);
            if ($authenticationRetryAfter !== null) {
                return $this->rateLimitedResponse($request, 'Terlalu banyak kegagalan autentikasi.', $authenticationRetryAfter);
            }

            $apiKey = ApiKey::query()->where('key_hash', hash('sha256', $token))->first();
            if ($apiKey !== null) {
                $this->tenantDatabaseContext->set($apiKey->mitra_id, $apiKey->mitra_id === null);
                $apiKey->load('mitra');
            }
            if ($apiKey === null || ! $apiKey->isActive()) {
                $reason = $apiKey === null ? 'invalid' : $this->inactiveReason($apiKey);
                if ($apiKey !== null && $apiKey->mitra_id !== null && $apiKey->mitra?->aktif !== true && $apiKey->revoked_at === null) {
                    $apiKey->forceFill(['revoked_at' => now()])->save();
                }
                if ($apiKey !== null && $apiKey->grace_until?->lte(now()) && $apiKey->revoked_at === null) {
                    $apiKey->forceFill(['revoked_at' => now()])->save();
                }
                $this->rateLimiter->hit($this->failureKey($request), 60);
                $this->apiKeyService->audit(null, 'authentication_failed', null, [
                    'reason' => $reason,
                ], ApiResponse::requestId($request));

                return ApiResponse::error($request, 'api_key_invalid', 401, 'API Key tidak valid.');
            }

            if (! $apiKey->allows('read_api')) {
                $this->apiKeyService->audit($apiKey, 'permission_denied', null, ['permission' => 'read_api'], ApiResponse::requestId($request));

                return ApiResponse::error($request, 'api_permission_denied', 403, 'API Key tidak memiliki izin read_api.');
            }

            if (! $this->acceptsJson($request)) {
                return ApiResponse::error($request, 'not_acceptable', 406, 'API hanya menyediakan media type application/json.');
            }

            $apiRetryAfter = $this->apiRateLimitRetryAfter($request, $apiKey);
            if ($apiRetryAfter !== null) {
                return $this->rateLimitedResponse($request, 'Batas permintaan API tercapai.', $apiRetryAfter);
            }

            $principal = new ApiKeyPrincipal($apiKey);
            $request->attributes->set(ApiKeyPrincipal::ATTRIBUTE, $principal);
            $apiKey->forceFill(['last_used_at' => now()])->save();
            $this->apiKeyService->audit($apiKey, 'authenticated', null, [], ApiResponse::requestId($request));

            return $next($request);
        } finally {
            $this->tenantDatabaseContext->set(null, false);
        }
    }

    /** @return array<int,string> */
    private function authorizationHeaders(Request $request): array
    {
        foreach ($request->headers->all() as $name => $values) {
            if (strtolower($name) === 'authorization') {
                return is_array($values) ? array_values(array_map('strval', $values)) : [(string) $values];
            }
        }

        return [];
    }

    private function credentialInAlternateLocation(Request $request): bool
    {
        foreach (['api_key', 'access_token', 'token'] as $name) {
            if ($request->query->has($name) || $request->request->has($name) || $request->cookies->has($name)) {
                return true;
            }
        }

        return false;
    }

    private function apiRateLimitRetryAfter(Request $request, ApiKey $apiKey): ?int
    {
        $minuteKey = 'api-key:minute:'.$apiKey->getKey();
        $burstKey = 'api-key:burst:'.$apiKey->getKey();
        $minuteLimited = $this->rateLimiter->tooManyAttempts($minuteKey, 60);
        $burstLimited = $this->rateLimiter->tooManyAttempts($burstKey, 20);
        if ($minuteLimited || $burstLimited) {
            $this->apiKeyService->audit($apiKey, 'rate_limited', null, [], ApiResponse::requestId($request));

            $retryAfter = 1;
            if ($minuteLimited) {
                $retryAfter = max($retryAfter, $this->rateLimiter->availableIn($minuteKey));
            }
            if ($burstLimited) {
                $retryAfter = max($retryAfter, $this->rateLimiter->availableIn($burstKey));
            }

            return $retryAfter;
        }

        $this->rateLimiter->hit($minuteKey, 60);
        $this->rateLimiter->hit($burstKey, 1);

        return null;
    }

    private function failureKey(Request $request): string
    {
        return 'api-key:failed-ip:'.($request->ip() ?? 'unknown');
    }

    private function authenticationRateLimitRetryAfter(Request $request): ?int
    {
        $failureKey = $this->failureKey($request);
        if (! $this->rateLimiter->tooManyAttempts($failureKey, 10)) {
            return null;
        }

        $this->apiKeyService->audit(null, 'rate_limited', null, ['reason' => 'authentication_failures'], ApiResponse::requestId($request));

        return max(1, $this->rateLimiter->availableIn($failureKey));
    }

    private function inactiveReason(ApiKey $apiKey): string
    {
        if ($apiKey->revoked_at !== null) {
            return 'revoked';
        }
        if ($apiKey->expires_at?->lte(now())) {
            return 'expired';
        }
        if ($apiKey->grace_until?->lte(now())) {
            return 'rotation_grace_expired';
        }

        return 'inactive_mitra';
    }

    private function acceptsJson(Request $request): bool
    {
        $accept = trim((string) $request->headers->get('Accept', ''));

        return $accept === '' || str_contains($accept, 'application/json') || str_contains($accept, '*/*');
    }

    private function fail(Request $request, string $reason, int $status, string $message): Response
    {
        if ($status === 401) {
            $authenticationRetryAfter = $this->authenticationRateLimitRetryAfter($request);
            if ($authenticationRetryAfter !== null) {
                return $this->rateLimitedResponse($request, 'Terlalu banyak kegagalan autentikasi.', $authenticationRetryAfter);
            }

            $this->rateLimiter->hit($this->failureKey($request), 60);
            $this->apiKeyService->audit(null, 'authentication_failed', null, ['reason' => $reason], ApiResponse::requestId($request));
        }

        return ApiResponse::error($request, $status === 429 ? 'rate_limited' : 'api_key_invalid', $status, $message);
    }

    private function rateLimitedResponse(Request $request, string $message, int $retryAfter): Response
    {
        return ApiResponse::error($request, 'rate_limited', 429, $message)
            ->header('Retry-After', (string) max(1, $retryAfter));
    }
}
