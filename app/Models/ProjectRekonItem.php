<?php

namespace App\Models;

use App\Models\Concerns\HasMitraScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRekonItem extends Model
{
    use HasMitraScope;

    protected $fillable = [
        'mitra_id',
        'project_rekon_id',
        'warehouse_id',
        'material_id',
        'material_sn_id',
        'drum_id',
        'keluar_gudang',
        'terpasang',
        'sisa_project',
        'dikembalikan',
        'hilang_rusak',
        'kategori_hilang_rusak',
        'penanggung_jawab',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'keluar_gudang' => 'decimal:3',
            'terpasang' => 'decimal:3',
            'sisa_project' => 'decimal:3',
            'dikembalikan' => 'decimal:3',
            'hilang_rusak' => 'decimal:3',
        ];
    }

    public function rekon(): BelongsTo
    {
        return $this->belongsTo(ProjectRekon::class, 'project_rekon_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
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
