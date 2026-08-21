<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Warehouse;
use App\Services\MitraWarehouseAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MitraWarehouseController extends Controller
{
    public function index(Request $request, MitraWarehouseAssignment $assignment): View
    {
        $roster = $assignment->assignmentsFor($request->user());

        return view('warehouse.mitra-assignment', [
            ...$roster,
        ]);
    }

    public function assign(Request $request, Warehouse $warehouse, MitraWarehouseAssignment $assignment): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->where('mitra_id', $request->user()->mitra_id)->where('aktif', true)],
        ]);
        $assignment->assign($request->user(), $warehouse, User::query()->findOrFail((int) $data['user_id']));

        return back()->with('status', 'Penugasan Warehouse Mitra disimpan.');
    }

    public function unassign(Request $request, Warehouse $warehouse, User $user, MitraWarehouseAssignment $assignment): RedirectResponse
    {
        $assignment->unassign($request->user(), $warehouse, $user);

        return back()->with('status', 'Penugasan Warehouse Mitra dihapus.');
    }

}
