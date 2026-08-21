<?php

namespace App\Models;

use App\Models\Concerns\HasMitraScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasMitraScope;

    protected static function booted(): void
    {
        static::updating(function (self $project): void {
            if ($project->isDirty('id_project') && $project->getOriginal('id_project') !== null) {
                throw new \LogicException('ID Project tidak dapat diubah setelah diterbitkan.');
            }
        });

        static::created(function (self $project): void {
            ProjectStep::initialize($project);
        });
    }

    protected $fillable = ['id_project', 'nama', 'mitra_id', 'status_project', 'toc'];

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

    public function rabJasas(): HasMany
    {
        return $this->hasMany(ProjectRabJasa::class);
    }

    public function rabMaterials(): HasMany
    {
        return $this->hasMany(ProjectRabMaterial::class);
    }

    public function materialUsages(): HasMany
    {
        return $this->hasMany(PemakaianMaterial::class);
    }

    public function rekons(): HasMany
    {
        return $this->hasMany(ProjectRekon::class);
    }

    public function baselines(): HasMany
    {
        return $this->hasMany(ProjectBaseline::class);
    }

    public function baselineProposals(): HasMany
    {
        return $this->hasMany(ProjectBaselineProposal::class);
    }

    public function variationOrders(): HasMany
    {
        return $this->hasMany(ProjectVariationOrder::class);
    }

    public function progresses(): HasMany
    {
        return $this->hasMany(ProjectProgress::class);
    }

    public function timelines(): HasMany
    {
        return $this->hasMany(ProjectTimeline::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProjectPhoto::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ProjectStep::class)->orderBy('urutan');
    }
}
