<?php

namespace App\Models;

use App\Models\Concerns\HasMitraScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MitraHargaJasa extends Model
{
    use HasMitraScope;

    protected $fillable = [
        'mitra_id',
        'pks_id',
        'pekerjaan_jasa_id',
        'harga',
        'status',
        'berlaku_mulai',
        'diajukan_oleh',
        'diputuskan_oleh',
        'diputuskan_at',
        'revisi_dari_id',
    ];

    protected function casts(): array
    {
        return [
            'harga' => 'decimal:2',
            'berlaku_mulai' => 'date',
            'diputuskan_at' => 'datetime',
        ];
    }

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class);
    }

    public function pks(): BelongsTo
    {
        return $this->belongsTo(Pks::class, 'pks_id');
    }

    public function pekerjaanJasa(): BelongsTo
    {
        return $this->belongsTo(PekerjaanJasa::class);
    }

    public function revisiDari(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revisi_dari_id');
    }

    public function revisi(): HasMany
    {
        return $this->hasMany(self::class, 'revisi_dari_id');
    }
}
