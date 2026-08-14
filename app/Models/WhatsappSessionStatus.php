<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappSessionStatus extends Model
{
    protected $fillable = ['session', 'status', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
