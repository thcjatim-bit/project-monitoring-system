<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialStok extends Model
{
    protected $fillable = ['warehouse_id', 'material_id', 'qty'];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
