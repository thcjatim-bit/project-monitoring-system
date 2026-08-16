<?php

namespace App\Models;

use App\Models\Concerns\HasMitraScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectStep extends Model
{
    use HasMitraScope;

    public const STEPS = [
        'design' => ['label' => 'Design', 'urutan' => 1],
        'survey' => ['label' => 'Survey', 'urutan' => 2],
        'drm' => ['label' => 'DRM', 'urutan' => 3],
        'spk' => ['label' => 'SPK', 'urutan' => 4],
        'pengadaan_material' => ['label' => 'Pengadaan Material', 'urutan' => 5],
        'delivery_material' => ['label' => 'Delivery Material', 'urutan' => 6],
        'mos' => ['label' => 'MOS', 'urutan' => 7],
        'deployment' => ['label' => 'Deployment', 'urutan' => 8],
        'test_comm' => ['label' => 'Test Comm', 'urutan' => 9],
        'atp' => ['label' => 'ATP', 'urutan' => 10],
        'go_live' => ['label' => 'GO Live', 'urutan' => 11],
    ];

    protected $fillable = [
        'mitra_id',
        'project_id',
        'step',
        'urutan',
        'status',
        'completed_at',
        'completed_by',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public static function initialize(Project $project): void
    {
        foreach (self::STEPS as $step => $definition) {
            self::query()->firstOrCreate(
                ['project_id' => $project->id, 'step' => $step],
                [
                    'mitra_id' => $project->mitra_id,
                    'urutan' => $definition['urutan'],
                    'status' => $definition['urutan'] === 1 ? 'active' : 'pending',
                ],
            );
        }
    }

    public function label(): string
    {
        return self::STEPS[$this->step]['label'] ?? $this->step;
    }
}
