<?php

namespace App\Models;

use App\Models\Concerns\HasMitraScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectRabJasa extends Model
{
    use HasMitraScope;

    protected $fillable = [
        'mitra_id',
        'project_id',
        'pekerjaan_jasa_id',
        'harga_jasa_mitra_id',
        'variation_order_id',
        'qty',
        'harga_satuan',
        'total_nilai',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'harga_satuan' => 'decimal:2',
            'total_nilai' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function pekerjaanJasa(): BelongsTo
    {
        return $this->belongsTo(PekerjaanJasa::class);
    }

    public function hargaJasaMitra(): BelongsTo
    {
        return $this->belongsTo(MitraHargaJasa::class, 'harga_jasa_mitra_id');
    }

    public function variationOrder(): BelongsTo
    {
        return $this->belongsTo(ProjectVariationOrder::class, 'variation_order_id');
    }

    public function progresses(): HasMany
    {
        return $this->hasMany(ProjectProgress::class, 'project_rab_jasa_id');
    }
}
