<?php

namespace App\Support;

use App\Models\ApiKey;

final class ApiKeyPrincipal
{
    public const ATTRIBUTE = 'api_key_principal';

    public function __construct(public readonly ApiKey $apiKey) {}

    public function id(): string
    {
        return (string) $this->apiKey->getKey();
    }

    public function mitraId(): ?int
    {
        return $this->apiKey->mitra_id === null ? null : (int) $this->apiKey->mitra_id;
    }

    public function isThc(): bool
    {
        return $this->mitraId() === null;
    }

    public function mitraCode(): ?string
    {
        return $this->apiKey->mitra?->kode;
    }

    public function allows(string $permission): bool
    {
        return $this->apiKey->allows($permission);
    }

    public function scopeKey(): string
    {
        return $this->isThc() ? 'thc' : 'mitra:'.(string) $this->mitraId();
    }
}
