<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PekerjaanJasa extends Model
{
    protected $fillable = ['kode', 'nama', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }
}
