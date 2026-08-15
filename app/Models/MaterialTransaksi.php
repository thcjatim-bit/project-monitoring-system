<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialTransaksi extends Model
{
    protected $table = 'material_transaksis';

    protected $fillable = [
        'warehouse_id',
        'material_id',
        'jenis_transaksi',
        'lokasi_tipe',
        'lokasi_id',
        'qty_delta',
        'material_sn_id',
        'drum_id',
        'project_id',
        'mitra_id',
        'reason',
        'catatan',
        'actor_id',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function suratJalan(): BelongsTo
    {
        return $this->belongsTo(SuratJalan::class);
    }

    public function serialNumber(): BelongsTo
    {
        return $this->belongsTo(MaterialSn::class, 'material_sn_id');
    }

    public function drum(): BelongsTo
    {
        return $this->belongsTo(Drum::class);
    }
}
