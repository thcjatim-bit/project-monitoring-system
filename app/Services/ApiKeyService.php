<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\ApiKeyAudit;
use App\Models\Mitra;
use App\Models\User;
use App\Support\ApiKeyCredential;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApiKeyService
{
    public const PREFIX = 'pms_live_';

    public const DEFAULT_EXPIRY_DAYS = 90;

    public const MAX_EXPIRY_DAYS = 365;

    public const ROTATION_GRACE_HOURS = 24;

    /** @param array<int,string> $permissions */
    public function create(
        User $actor,
        string $name,
        ?int $mitraId = null,
        int $expiresInDays = self::DEFAULT_EXPIRY_DAYS,
        array $permissions = ['read_api'],
    ): ApiKeyCredential {
        $this->ensureManager($actor);
        $this->ensureExpiryDays($expiresInDays);
        $this->ensurePermissions($permissions);

        if ($mitraId !== null) {
            Mitra::query()->whereKey($mitraId)->where('aktif', true)->firstOrFail();
        }

        $plaintext = self::PREFIX.$this->randomSegment();
        $apiKey = ApiKey::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'key_hash' => hash('sha256', $plaintext),
            'mitra_id' => $mitraId,
            'permissions' => array_values(array_unique($permissions)),
            'expires_at' => now()->addDays($expiresInDays),
            'created_by' => $actor->id,
        ]);

        $this->audit($apiKey, 'created', $actor, [
            'scope' => $mitraId === null ? 'thc' : 'mitra',
            'expires_in_days' => $expiresInDays,
            'permissions' => array_values(array_unique($permissions)),
        ]);

        return new ApiKeyCredential($apiKey, $plaintext);
    }

    public function revoke(ApiKey $apiKey, User $actor, string $reason = 'manual_revoke'): ApiKey
    {
        $this->ensureManager($actor);

        return DB::transaction(function () use ($apiKey, $actor, $reason): ApiKey {
            $apiKey = ApiKey::query()->lockForUpdate()->findOrFail($apiKey->getKey());
            if ($apiKey->revoked_at === null) {
                $apiKey->forceFill(['revoked_at' => now()])->save();
                $this->audit($apiKey, 'revoked', $actor, ['reason' => $reason]);
            }

            return $apiKey;
        });
    }

    public function rotate(ApiKey $apiKey, User $actor): ApiKeyCredential
    {
        $this->ensureManager($actor);

        return DB::transaction(function () use ($apiKey, $actor): ApiKeyCredential {
            $apiKey = ApiKey::query()->lockForUpdate()->findOrFail($apiKey->getKey());
            if (! $apiKey->isActive()) {
                throw ValidationException::withMessages(['api_key' => 'API Key tidak aktif dan tidak dapat dirotasi.']);
            }

            $remainingDays = max(1, min(
                self::MAX_EXPIRY_DAYS,
                CarbonImmutable::now()->diffInDays(CarbonImmutable::instance($apiKey->expires_at)),
            ));
            $credential = $this->create(
                actor: $actor,
                name: $apiKey->name,
                mitraId: $apiKey->mitra_id,
                expiresInDays: $remainingDays,
                permissions: $apiKey->permissions ?? [],
            );

            $apiKey->forceFill(['grace_until' => now()->addHours(self::ROTATION_GRACE_HOURS)])->save();
            $this->audit($apiKey, 'rotated', $actor, [
                'new_key_id' => $credential->apiKey->getKey(),
                'grace_hours' => self::ROTATION_GRACE_HOURS,
            ]);

            return $credential;
        });
    }

    /** @param array<string,mixed> $metadata */
    public function audit(?ApiKey $apiKey, string $event, ?User $actor = null, array $metadata = [], ?string $requestId = null): ApiKeyAudit
    {
        $redacted = [];
        foreach ($metadata as $key => $value) {
            if (in_array(strtolower((string) $key), ['key', 'raw_key', 'plaintext', 'candidate_hash', 'authorization', 'authorization_header', 'token'], true)) {
                continue;
            }
            if (is_scalar($value) || $value === null || is_array($value)) {
                $redacted[$key] = $value;
            }
        }

        return ApiKeyAudit::query()->create([
            'api_key_id' => $apiKey?->getKey(),
            'actor_id' => $actor?->id,
            'event' => $event,
            'request_id' => $requestId,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'metadata' => $redacted,
            'created_at' => now(),
        ]);
    }

    private function ensureManager(User $actor): void
    {
        if ($actor->mitra_id !== null || ! $actor->hasIzin('manage_api_keys')) {
            throw ValidationException::withMessages(['api_key' => 'Hanya user THC berizin yang dapat mengelola API Key.']);
        }
    }

    private function ensureExpiryDays(int $days): void
    {
        if ($days < 1 || $days > self::MAX_EXPIRY_DAYS) {
            throw ValidationException::withMessages(['expires_in_days' => 'Masa berlaku API Key harus 1 sampai 365 hari.']);
        }
    }

    /** @param array<int,string> $permissions */
    private function ensurePermissions(array $permissions): void
    {
        $permissions = array_values(array_unique($permissions));
        if ($permissions === [] || array_diff($permissions, ['read_api']) !== []) {
            throw ValidationException::withMessages(['permissions' => 'API Key hanya dapat diberi izin read_api.']);
        }
    }

    private function randomSegment(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
