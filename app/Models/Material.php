<?php

namespace App\Models;

use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    /** @use HasFactory<MaterialFactory> */
    use HasFactory;

    protected $fillable = ['kode', 'nama', 'unit', 'jenis', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(MaterialStok::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(MaterialTransaksi::class);
    }
}
