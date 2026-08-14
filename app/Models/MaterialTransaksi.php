<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialTransaksi extends Model
{
    protected $table = 'material_transaksis';

    protected $fillable = ['warehouse_id', 'material_id', 'qty_delta', 'reason', 'actor_id'];

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
}
