<?php

namespace App\Models;

use App\Models\Concerns\HasMitraScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectTimeline extends Model
{
    use HasMitraScope;

    protected $fillable = [
        'mitra_id',
        'project_id',
        'actor_id',
        'type',
        'event_key',
        'body',
        'metadata',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'edited_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(ProjectTimelineMention::class, 'timeline_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(ProjectNotification::class, 'timeline_id');
    }

    public static function recordSystem(Project $project, ?User $actor, string $eventKey, array $metadata = []): self
    {
        return self::query()->create([
            'mitra_id' => $project->mitra_id,
            'project_id' => $project->id,
            'actor_id' => $actor?->id,
            'type' => 'system_log',
            'event_key' => $eventKey,
            'metadata' => $metadata,
        ]);
    }
}
