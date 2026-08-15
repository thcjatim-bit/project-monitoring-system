<?php

namespace App\Models;

use App\Models\Concerns\HasMitraScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuratJalanItem extends Model
{
    use HasMitraScope;

    protected $fillable = [
        'surat_jalan_id',
        'mitra_id',
        'material_id',
        'material_sn_id',
        'drum_id',
        'qty',
        'qty_diterima',
        'qty_diretur',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'qty_diterima' => 'decimal:3',
            'qty_diretur' => 'decimal:3',
        ];
    }

    public function suratJalan(): BelongsTo
    {
        return $this->belongsTo(SuratJalan::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
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
