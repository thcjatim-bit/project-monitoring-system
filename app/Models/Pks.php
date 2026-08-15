<?php

namespace App\Models;

use App\Models\Concerns\HasMitraScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pks extends Model
{
    use HasMitraScope;

    protected $table = 'pks';

    protected $fillable = ['mitra_id', 'nomor', 'tanggal_mulai', 'tanggal_berakhir', 'lampiran_path'];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_berakhir' => 'date',
        ];
    }

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class);
    }

    public function hargaJasas(): HasMany
    {
        return $this->hasMany(MitraHargaJasa::class, 'pks_id');
    }
}
