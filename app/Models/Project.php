<?php

namespace App\Models;

use App\Models\Concerns\HasMitraScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    use HasMitraScope;

    protected $fillable = ['id_project', 'nama', 'mitra_id', 'status', 'toc'];

    protected function casts(): array
    {
        return [
            'toc' => 'date',
        ];
    }

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class);
    }
}
