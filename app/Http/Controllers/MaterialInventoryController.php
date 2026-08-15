<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Services\MaterialInventoryService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class MaterialInventoryController extends Controller
{
    public function receive(Request $request, MaterialInventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('aktif', true)],
            'material_id' => ['required', 'integer', $this->activeMaterialRule()],
            'qty' => ['required', 'numeric', 'gt:0'], 'reason' => ['required', 'string', 'max:255'],
        ]);
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        abort_unless($request->user()->canOperateWarehouse($warehouse, 'operate_warehouse'), 403);
        $inventory->receive($request->user(), $warehouse, (int) $data['material_id'], (string) $data['qty'], $data['reason']);

        return back()->with('status', 'Penerimaan material dicatat.');
    }

    public function issue(Request $request, MaterialInventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('aktif', true)],
            'material_id' => ['required', 'integer', $this->activeMaterialRule()],
            'qty' => ['required', 'numeric', 'gt:0'], 'reason' => ['required', 'string', 'max:255'],
        ]);
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        abort_unless($request->user()->canOperateWarehouse($warehouse, 'operate_warehouse'), 403);
        $inventory->issue($request->user(), $warehouse, (int) $data['material_id'], (string) $data['qty'], $data['reason']);

        return back()->with('status', 'Pengeluaran material dicatat.');
    }

    private function activeMaterialRule(): Exists
    {
        return Rule::exists('materials', 'id')->where(function (Builder $query): void {
            $query->where('aktif', true)->where('jenis', 'biasa')->whereExists(function (Builder $units): void {
                $units->selectRaw('1')
                    ->from('units')
                    ->whereColumn('units.id', 'materials.unit_id')
                    ->where('units.aktif', true);
            });
        });
    }
}
