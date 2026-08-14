<?php

namespace App\Models;

use Database\Factories\IzinFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Izin extends Model
{
    /** @use HasFactory<IzinFactory> */
    use HasFactory;

    protected $table = 'izins';

    protected $fillable = ['kode', 'nama'];

    public function grups(): BelongsToMany
    {
        return $this->belongsToMany(Grup::class, 'grup_izin');
    }
}
