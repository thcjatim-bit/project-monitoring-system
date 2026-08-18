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

        if ($this->rateLimiter->tooManyAttempts($this->failureKey($request), 10)) {
            return ApiResponse::error($request, 'rate_limited', 429, 'Terlalu banyak kegagalan autentikasi.');
        }

        $apiKey = ApiKey::query()->with('mitra')->where('key_hash', hash('sha256', $token))->first();
        if ($apiKey === null || ! $apiKey->isActive()) {
            if ($apiKey !== null && $apiKey->mitra_id !== null && $apiKey->mitra?->aktif !== true && $apiKey->revoked_at === null) {
                $apiKey->forceFill(['revoked_at' => now()])->save();
            }
            $this->rateLimiter->hit($this->failureKey($request), 60);
            $this->apiKeyService->audit(null, 'authentication_failed', null, [
                'reason' => $apiKey === null ? 'invalid' : $this->inactiveReason($apiKey),
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

        if ($this->rateLimited($request, $apiKey)) {
            return ApiResponse::error($request, 'rate_limited', 429, 'Batas permintaan API tercapai.');
        }

        $principal = new ApiKeyPrincipal($apiKey);
        $request->attributes->set(ApiKeyPrincipal::ATTRIBUTE, $principal);
        $apiKey->forceFill(['last_used_at' => now()])->save();
        $this->apiKeyService->audit($apiKey, 'authenticated', null, [], ApiResponse::requestId($request));

        try {
            $this->tenantDatabaseContext->set($principal->mitraId(), $principal->isThc());

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

    private function rateLimited(Request $request, ApiKey $apiKey): bool
    {
        $minuteKey = 'api-key:minute:'.$apiKey->getKey();
        $burstKey = 'api-key:burst:'.$apiKey->getKey();
        if ($this->rateLimiter->tooManyAttempts($minuteKey, 60) || $this->rateLimiter->tooManyAttempts($burstKey, 20)) {
            $this->apiKeyService->audit($apiKey, 'rate_limited', null, [], ApiResponse::requestId($request));

            return true;
        }

        $this->rateLimiter->hit($minuteKey, 60);
        $this->rateLimiter->hit($burstKey, 1);

        return false;
    }

    private function failureKey(Request $request): string
    {
        return 'api-key:failed-ip:'.($request->ip() ?? 'unknown');
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
            $this->rateLimiter->hit($this->failureKey($request), 60);
            $this->apiKeyService->audit(null, 'authentication_failed', null, ['reason' => $reason], ApiResponse::requestId($request));
        }

        return ApiResponse::error($request, $status === 429 ? 'rate_limited' : 'api_key_invalid', $status, $message);
    }
}
