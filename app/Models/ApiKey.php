<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ApiKey extends Model
{
    protected $table = 'api_keys';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'key_hash',
        'mitra_id',
        'permissions',
        'expires_at',
        'revoked_at',
        'grace_until',
        'rotated_from_id',
        'created_by',
        'last_used_at',
    ];

    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'grace_until' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rotatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rotated_from_id');
    }

    public function rotatedTo(): HasOne
    {
        return $this->hasOne(self::class, 'rotated_from_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(ApiKeyAudit::class);
    }

    public function allows(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [], true);
    }

    public function isActive(?CarbonInterface $now = null): bool
    {
        $now = $now ?? now();

        if ($this->revoked_at !== null || $this->expires_at === null || $this->expires_at->lte($now)) {
            return false;
        }

        if ($this->grace_until !== null && $this->grace_until->lte($now)) {
            return false;
        }

        return $this->mitra_id === null || ($this->relationLoaded('mitra') ? $this->mitra?->aktif === true : $this->mitra()->where('aktif', true)->exists());
    }
}
