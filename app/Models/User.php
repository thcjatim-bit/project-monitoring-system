<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'no_wa',
        'mitra_id',
        'grup_id',
        'aktif',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'aktif' => 'boolean',
        ];
    }

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class);
    }

    public function grup(): BelongsTo
    {
        return $this->belongsTo(Grup::class);
    }

    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'user_warehouses')->withTimestamps();
    }

    public function projectNotifications(): HasMany
    {
        return $this->hasMany(ProjectNotification::class);
    }

    public function canOperateWarehouse(Warehouse $warehouse, string $izin): bool
    {
        return $this->hasIzin($izin) && $this->warehouses()->whereKey($warehouse->id)->exists();
    }

    public function hasIzin(string $kode): bool
    {
        return $this->grup()
            ->whereHas('izins', fn ($query) => $query->where('kode', $kode))
            ->exists();
    }

    public function homeRouteName(): string
    {
        if ($this->mitra_id !== null) {
            return $this->hasIzin('read_dashboard') ? 'mitra.dashboard' : 'mitra.landing';
        }

        if ($this->hasIzin('read_dashboard')) {
            return 'dashboard';
        }

        return $this->hasIzin('read_project') ? 'projects.index' : 'access.landing';
    }
}
