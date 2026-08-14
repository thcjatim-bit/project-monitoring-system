<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class TenantDatabaseContext
{
    private ?int $mitraId = null;

    private bool $isThc = false;

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

        $connection = DB::connection();

        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $connection->select("select set_config('app.mitra_id', ?, false)", [$mitraId === null ? '' : (string) $mitraId]);
        $connection->select("select set_config('app.is_thc', ?, false)", [$isThc ? 'on' : 'off']);
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
