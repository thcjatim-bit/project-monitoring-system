<?php

namespace App\Models;

use Database\Factories\GrupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Grup extends Model
{
    /** @use HasFactory<GrupFactory> */
    use HasFactory;

    protected $fillable = ['nama', 'preset'];

    public function izins(): BelongsToMany
    {
        return $this->belongsToMany(Izin::class);
    }
}
