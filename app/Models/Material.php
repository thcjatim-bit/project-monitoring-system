<?php

namespace App\Models;

use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    /** @use HasFactory<MaterialFactory> */
    use HasFactory;

    protected $fillable = ['kode', 'nama', 'unit_id', 'jenis', 'ambang_minimum', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'ambang_minimum' => 'decimal:3'];
    }

    public function scopeActiveWithUnit(Builder $query): Builder
    {
        return $query->where('aktif', true)
            ->whereHas('unit', fn ($unitQuery) => $unitQuery->where('aktif', true));
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(MaterialStok::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(MaterialTransaksi::class);
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(MaterialSn::class);
    }

    public function drums(): HasMany
    {
        return $this->hasMany(Drum::class);
    }
}
