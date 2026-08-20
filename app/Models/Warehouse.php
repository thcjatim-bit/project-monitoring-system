<?php

namespace App\Models;

use App\Models\Concerns\HasImmutableMasterCode;
use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use HasFactory;

    use HasImmutableMasterCode;

    public function masterCodeEntity(): string
    {
        return 'warehouse';
    }

    protected $fillable = ['kode', 'nama', 'mitra_id', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_warehouses')->withTimestamps();
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(MaterialStok::class);
    }
}
