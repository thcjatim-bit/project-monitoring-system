<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialSn extends Model
{
    protected $table = 'material_sns';

    protected $fillable = ['material_id', 'mitra_id', 'serial_number', 'lokasi_tipe', 'lokasi_id', 'status', 'project_id'];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
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
