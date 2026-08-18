<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKeyAudit extends Model
{
    protected $table = 'api_key_audits';

    public $timestamps = false;

    protected $fillable = [
        'api_key_id',
        'actor_id',
        'event',
        'request_id',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
    ];

    protected $hidden = ['api_key_id', 'actor_id'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
