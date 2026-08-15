<?php

namespace App\Models;

use App\Models\Concerns\HasMitraScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectVariationOrderItem extends Model
{
    use HasMitraScope;

    protected $fillable = [
        'mitra_id',
        'project_variation_order_id',
        'rab_jasa_id',
        'pekerjaan_jasa_id',
        'harga_jasa_mitra_id',
        'quantity_delta',
        'harga_satuan',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:3',
            'harga_satuan' => 'decimal:2',
        ];
    }

    public function variationOrder(): BelongsTo
    {
        return $this->belongsTo(ProjectVariationOrder::class, 'project_variation_order_id');
    }

    public function rabJasa(): BelongsTo
    {
        return $this->belongsTo(ProjectRabJasa::class, 'rab_jasa_id');
    }

    public function hargaJasaMitra(): BelongsTo
    {
        return $this->belongsTo(MitraHargaJasa::class, 'harga_jasa_mitra_id');
    }

    public function pekerjaanJasa(): BelongsTo
    {
        return $this->belongsTo(PekerjaanJasa::class);
    }
}
