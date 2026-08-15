<?php

namespace App\Models;

use App\Models\Concerns\HasMitraScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBaselineDay extends Model
{
    use HasMitraScope;

    protected $fillable = ['mitra_id', 'project_baseline_id', 'plan_date', 'cumulative_percent'];

    protected function casts(): array
    {
        return [
            'plan_date' => 'date',
            'cumulative_percent' => 'decimal:3',
        ];
    }

    public function baseline(): BelongsTo
    {
        return $this->belongsTo(ProjectBaseline::class, 'project_baseline_id');
    }
}
