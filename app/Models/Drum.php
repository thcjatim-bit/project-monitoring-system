<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Drum extends Model
{
    protected $fillable = [
        'material_id',
        'drum_id',
        'panjang_awal',
        'sisa',
        'induk_drum_id',
        'lokasi_tipe',
        'lokasi_id',
        'project_id',
    ];

    protected function casts(): array
    {
        return ['panjang_awal' => 'decimal:3', 'sisa' => 'decimal:3'];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'induk_drum_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'induk_drum_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(MaterialTransaksi::class);
    }
}
