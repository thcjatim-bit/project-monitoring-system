<?php

namespace App\Models;

use App\Models\Concerns\HasMitraScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectBaseline extends Model
{
    use HasMitraScope;

    protected $fillable = ['mitra_id', 'project_id', 'kind', 'version', 'toc', 'supersedes_id', 'dibuat_oleh'];

    protected function casts(): array
    {
        return [
            'toc' => 'date',
            'version' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(ProjectBaselineDay::class);
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }
}
