<?php

namespace App\Services;

use App\Models\MaterialTransaksi;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaterialInventoryService
{
    public function receive(User $actor, Warehouse $warehouse, int $materialId, string $qty, string $reason): MaterialTransaksi
    {
        return $this->record($actor, $warehouse, $materialId, $qty, $reason);
    }

    public function issue(User $actor, Warehouse $warehouse, int $materialId, string $qty, string $reason): MaterialTransaksi
    {
        $stock = $warehouse->stocks()->where('material_id', $materialId)->value('qty');
        if ($stock === null || (float) $stock + 0.0005 < (float) $qty) {
            throw ValidationException::withMessages(['qty' => 'Saldo material tidak mencukupi.']);
        }

        return $this->record($actor, $warehouse, $materialId, '-'.$qty, $reason);
    }

    private function record(User $actor, Warehouse $warehouse, int $materialId, string $qty, string $reason): MaterialTransaksi
    {
        if ((float) $qty === 0.0 || (float) ltrim($qty, '-') < 0.0) {
            throw ValidationException::withMessages(['qty' => 'Jumlah harus lebih besar dari nol.']);
        }

        return DB::transaction(fn () => MaterialTransaksi::create([
            'warehouse_id' => $warehouse->id,
            'material_id' => $materialId,
            'qty_delta' => $qty,
            'reason' => $reason,
            'actor_id' => $actor->id,
        ]));
    }
}
