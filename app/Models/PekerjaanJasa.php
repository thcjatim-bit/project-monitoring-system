<?php

namespace App\Models;

use App\Models\Concerns\HasImmutableMasterCode;
use Illuminate\Database\Eloquent\Model;

class PekerjaanJasa extends Model
{
    use HasImmutableMasterCode;

    public function masterCodeEntity(): string
    {
        return 'pekerjaan_jasa';
    }

    protected $fillable = ['kode', 'nama', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }
}
