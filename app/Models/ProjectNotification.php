<?php

namespace App\Models;

use App\Models\Concerns\HasMitraScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectNotification extends Model
{
    use HasMitraScope;

    protected $fillable = [
        'mitra_id',
        'project_id',
        'timeline_id',
        'user_id',
        'type',
        'body',
        'read_at',
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function timeline(): BelongsTo
    {
        return $this->belongsTo(ProjectTimeline::class, 'timeline_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
