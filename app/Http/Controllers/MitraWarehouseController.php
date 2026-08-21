<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MitraWarehouseController extends Controller
{
    public function index(Request $request): View
    {
        $mitraId = $request->user()->mitra_id;

        return view('warehouse.mitra-assignment', [
            'warehouses' => Warehouse::query()
                ->where('mitra_id', $mitraId)
                ->with(['users' => fn ($query) => $query->where('mitra_id', $mitraId)])
                ->orderBy('nama')
                ->get(),
            'users' => User::query()->where('mitra_id', $mitraId)->where('aktif', true)->orderBy('name')->get(),
        ]);
    }

    public function assign(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse = $this->ownWarehouse($request, $warehouse);
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->where('mitra_id', $request->user()->mitra_id)->where('aktif', true)],
        ]);
        $warehouse->users()->syncWithoutDetaching([(int) $data['user_id']]);

        return back()->with('status', 'Penugasan Warehouse Mitra disimpan.');
    }

    public function unassign(Request $request, Warehouse $warehouse, User $user): RedirectResponse
    {
        $warehouse = $this->ownWarehouse($request, $warehouse);
        abort_unless((int) $user->mitra_id === (int) $request->user()->mitra_id, 404);
        $warehouse->users()->detach($user);

        return back()->with('status', 'Penugasan Warehouse Mitra dihapus.');
    }

    private function ownWarehouse(Request $request, Warehouse $warehouse): Warehouse
    {
        abort_unless((int) $warehouse->mitra_id === (int) $request->user()->mitra_id, 404);
        abort_unless($warehouse->aktif, 422, 'Warehouse nonaktif tidak dapat ditugaskan.');

        return $warehouse;
    }
}
