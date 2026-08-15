<?php

namespace App\Models;

use App\Models\Concerns\HasMitraScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SuratJalan extends Model
{
    use HasMitraScope;

    protected $fillable = [
        'nomor',
        'tanggal',
        'warehouse_asal_id',
        'warehouse_tujuan_id',
        'mitra_id',
        'issued_by',
        'issued_at',
        'status',
        'pengirim',
        'sopir',
        'plat_nomor',
        'received_by',
        'received_at',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'issued_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_asal_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_tujuan_id');
    }

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SuratJalanItem::class);
    }
}
