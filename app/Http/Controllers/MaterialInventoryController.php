<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Services\MaterialInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaterialInventoryController extends Controller
{
    public function receive(Request $request, MaterialInventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('aktif', true)],
            'material_id' => ['required', 'integer', Rule::exists('materials', 'id')->where('aktif', true)],
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
            'material_id' => ['required', 'integer', Rule::exists('materials', 'id')->where('aktif', true)],
            'qty' => ['required', 'numeric', 'gt:0'], 'reason' => ['required', 'string', 'max:255'],
        ]);
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        abort_unless($request->user()->canOperateWarehouse($warehouse, 'operate_warehouse'), 403);
        $inventory->issue($request->user(), $warehouse, (int) $data['material_id'], (string) $data['qty'], $data['reason']);

        return back()->with('status', 'Pengeluaran material dicatat.');
    }
}
