<?php

namespace App\Models;

use App\Models\Concerns\HasMitraScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectProgress extends Model
{
    use HasMitraScope;

    protected $fillable = [
        'mitra_id',
        'project_id',
        'project_rab_jasa_id',
        'reported_by',
        'actual_date',
        'qty',
        'status',
        'verified_by',
        'verified_at',
        'verification_note',
    ];

    protected function casts(): array
    {
        return [
            'actual_date' => 'date',
            'qty' => 'decimal:3',
            'verified_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function rabJasa(): BelongsTo
    {
        return $this->belongsTo(ProjectRabJasa::class, 'project_rab_jasa_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
