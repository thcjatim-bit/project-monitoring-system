<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = ['kode', 'nama', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }
}
