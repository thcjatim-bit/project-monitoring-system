<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;

class TenantDatabaseContext
{
    private ?int $mitraId = null;

    private bool $isThc = false;

    public function __construct(private ConnectionInterface $connection) {}

    public function forUser(?User $user): void
    {
        if ($user?->mitra_id === null && $user !== null) {
            $this->set(null, true);

            return;
        }

        $this->set($user?->mitra_id, false);
    }

    public function set(?int $mitraId, bool $isThc): void
    {
        $this->mitraId = $mitraId;
        $this->isThc = $isThc;

        if ($this->connection->getDriverName() !== 'pgsql') {
            return;
        }

        $this->connection->select("select set_config('app.mitra_id', ?, false)", [$mitraId === null ? '' : (string) $mitraId]);
        $this->connection->select("select set_config('app.is_thc', ?, false)", [$isThc ? 'on' : 'off']);
    }

    public function mitraId(): ?int
    {
        return $this->mitraId;
    }

    public function isThc(): bool
    {
        return $this->isThc;
    }
}
