<?php

namespace App\Models;

use App\Models\Concerns\HasMitraScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectRekon extends Model
{
    use HasMitraScope;

    protected $fillable = [
        'nomor',
        'mitra_id',
        'project_id',
        'koreksi_dari_id',
        'source',
        'status',
        'opened_by',
        'approved_by',
        'approved_at',
        'catatan',
        'decision_note',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function correctionSource(): BelongsTo
    {
        return $this->belongsTo(self::class, 'koreksi_dari_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(self::class, 'koreksi_dari_id');
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProjectRekonItem::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'disetujui');
    }

    public function isCorrection(): bool
    {
        return $this->koreksi_dari_id !== null;
    }
}
