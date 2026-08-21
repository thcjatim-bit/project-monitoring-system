<?php

namespace App\Services;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;

class MitraWarehouseAssignment
{
    /** @return array{warehouses: Collection<int, Warehouse>, users: Collection<int, User>} */
    public function assignmentsFor(User $actor): array
    {
        $this->assertMitraActor($actor);

        return [
            'warehouses' => Warehouse::query()
                ->where('mitra_id', $actor->mitra_id)
                ->with(['users' => fn ($query) => $query->where('mitra_id', $actor->mitra_id)])
                ->orderBy('nama')
                ->get(),
            'users' => User::query()->where('mitra_id', $actor->mitra_id)->where('aktif', true)->orderBy('name')->get(),
        ];
    }

    public function assign(User $actor, Warehouse $warehouse, User $target): void
    {
        $this->ownWarehouse($actor, $warehouse);
        abort_unless((int) $target->mitra_id === (int) $actor->mitra_id && $target->aktif, 404);

        $warehouse->users()->syncWithoutDetaching([$target->id]);
    }

    public function unassign(User $actor, Warehouse $warehouse, User $target): void
    {
        $this->ownWarehouse($actor, $warehouse);
        abort_unless((int) $target->mitra_id === (int) $actor->mitra_id, 404);

        $warehouse->users()->detach($target);
    }

    private function ownWarehouse(User $actor, Warehouse $warehouse): void
    {
        $this->assertMitraActor($actor);
        abort_unless((int) $warehouse->mitra_id === (int) $actor->mitra_id, 404);
        abort_unless($warehouse->aktif, 422, 'Warehouse nonaktif tidak dapat ditugaskan.');
    }

    private function assertMitraActor(User $actor): void
    {
        abort_unless($actor->mitra_id !== null && $actor->hasIzin('manage_mitra_warehouse'), 403);
    }
}
