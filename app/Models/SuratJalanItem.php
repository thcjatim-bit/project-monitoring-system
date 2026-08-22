<?php

namespace App\Models;

use App\Models\Concerns\HasMitraScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuratJalanItem extends Model
{
    use HasMitraScope;

    /**
     * "Sudah terkirim" untuk satu Request Material: baris yang masih `terbit` dihitung dari qty
     * yang berangkat, baris yang sudah `diterima` dari qty yang benar-benar sampai. Ini basis
     * yang mendefinisikan sisa pada ADR-0024, jadi klasifikator penyimpangan dan angka yang
     * dilihat operator harus memakai ekspresi yang sama persis.
     */
    public const SENT_QUANTITY = "SUM(CASE WHEN surat_jalans.status = 'terbit' THEN surat_jalan_items.qty ELSE surat_jalan_items.qty_diterima END)";

    protected $fillable = [
        'surat_jalan_id',
        'mitra_id',
        'material_id',
        'material_sn_id',
        'drum_id',
        'qty',
        'qty_diterima',
        'qty_diretur',
        'catatan',
        'jenis_penyimpangan',
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
