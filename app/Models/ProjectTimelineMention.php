<?php

namespace App\Models;

use App\Models\Concerns\HasMitraScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTimelineMention extends Model
{
    use HasMitraScope;

    protected $fillable = [
        'mitra_id',
        'project_id',
        'timeline_id',
        'mentioned_user_id',
        'notification_status',
        'notified_at',
        'notification_error',
    ];

    protected function casts(): array
    {
        return ['notified_at' => 'datetime'];
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
        return $this->belongsTo(User::class, 'mentioned_user_id');
    }
}
