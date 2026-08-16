<?php

namespace App\Models;

use App\Models\Concerns\HasMitraScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPhoto extends Model
{
    use HasMitraScope;

    protected $fillable = [
        'mitra_id',
        'project_id',
        'project_step_id',
        'uploaded_by',
        'original_name',
        'stored_path',
        'mime_type',
        'original_size',
        'width',
        'height',
        'capture_date',
        'sync_status',
        'synced_at',
        'sync_error',
        'drive_url',
    ];

    protected function casts(): array
    {
        return [
            'capture_date' => 'date',
            'synced_at' => 'datetime',
            'original_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(ProjectStep::class, 'project_step_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
