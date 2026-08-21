<?php

namespace App\Models;

use App\Models\Concerns\HasMitraScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectBaselineProposal extends Model
{
    use HasMitraScope;

    protected $fillable = [
        'mitra_id',
        'project_id',
        'status',
        'toc',
        'diajukan_oleh',
        'diputuskan_oleh',
        'diputuskan_at',
    ];

    protected function casts(): array
    {
        return [
            'toc' => 'date',
            'diputuskan_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(ProjectBaselineProposalDay::class);
    }
}
