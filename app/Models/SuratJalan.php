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
        'material_request_id',
        'project_id',
        'retur_dari_id',
        'issued_by',
        'issued_at',
        'status',
        'transit_resolution',
        'pengirim',
        'sopir',
        'plat_nomor',
        'received_by',
        'received_at',
        'resolved_by',
        'resolved_at',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'issued_at' => 'datetime',
            'received_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function asal(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_asal_id');
    }

    public function tujuan(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_tujuan_id');
    }

    public function namaAsal(): string
    {
        return $this->asal?->nama ?? 'Warehouse asal tidak tersedia';
    }

    public function namaTujuan(): string
    {
        return $this->tujuan?->nama ?? 'Warehouse tujuan tidak tersedia';
    }

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class);
    }

    public function materialRequest(): BelongsTo
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function returDari(): BelongsTo
    {
        return $this->belongsTo(self::class, 'retur_dari_id');
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
