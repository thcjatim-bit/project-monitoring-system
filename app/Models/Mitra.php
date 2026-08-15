<?php

namespace App\Models;

use Database\Factories\MitraFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Mitra extends Model
{
    /** @use HasFactory<MitraFactory> */
    use HasFactory;

    protected $fillable = ['kode', 'nama', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function adminMitraPertama(): HasOne
    {
        return $this->hasOne(User::class)->ofMany(['created_at' => 'min', 'id' => 'min']);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function pks(): HasMany
    {
        return $this->hasMany(Pks::class);
    }

    public function hargaJasas(): HasMany
    {
        return $this->hasMany(MitraHargaJasa::class);
    }
}
