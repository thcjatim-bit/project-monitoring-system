<?php

namespace App\Models;

use App\Models\Concerns\HasMitraScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialRequest extends Model
{
    use HasMitraScope;

    /** Status Request Material yang masih boleh dipenuhi Surat Jalan. */
    public const FULFILLABLE_STATUSES = ['disetujui', 'terpenuhi_sebagian'];

    /** Status Request Material yang tidak boleh dipilih lagi setelah validasi form gagal. */
    public const TERMINAL_STATUSES = ['selesai', 'ditutup'];

    protected $fillable = [
        'mitra_id',
        'project_id',
        'requested_by',
        'status',
        'catatan',
        'decided_by',
        'decided_at',
        'decision_note',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialRequestItem::class);
    }

    public function suratJalans(): HasMany
    {
        return $this->hasMany(SuratJalan::class);
    }
}
