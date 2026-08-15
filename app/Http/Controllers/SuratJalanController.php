<?php

namespace App\Http\Controllers;

use App\Models\MaterialStok;
use App\Models\SuratJalan;
use App\Models\Warehouse;
use App\Services\SuratJalanService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class SuratJalanController extends Controller
{
    public function issue(Request $request, SuratJalanService $service): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_asal_id' => ['required', 'integer', $this->activeWarehouseRule()],
            'warehouse_tujuan_id' => ['required', 'integer', $this->activeWarehouseRule()],
            'tanggal' => ['required', 'date'],
            'pengirim' => ['required', 'string', 'max:255'],
            'sopir' => ['nullable', 'string', 'max:255'],
            'plat_nomor' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'integer', $this->activeMaterialRule()],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.serial_number' => ['nullable', 'string', 'max:255'],
            'items.*.drum_id' => ['nullable', 'string', 'max:255'],
        ]);

        $origin = Warehouse::query()->findOrFail($data['warehouse_asal_id']);
        abort_unless($request->user()->canOperateWarehouse($origin, 'operate_warehouse'), 403);

        $suratJalan = $service->issueDirect($request->user(), $data);

        return redirect()->route('warehouse.transfers.print', $suratJalan)->with('status', 'Surat Jalan diterbitkan.');
    }

    public function receive(Request $request, SuratJalan $suratJalan, SuratJalanService $service): RedirectResponse
    {
        $destination = Warehouse::query()->findOrFail($suratJalan->warehouse_tujuan_id);
        abort_unless($request->user()->canOperateWarehouse($destination, 'operate_warehouse'), 403);

        $service->receive($request->user(), $suratJalan);

        return back()->with('status', 'Surat Jalan diterima dan stok masuk ke Warehouse tujuan.');
    }

    public function print(SuratJalan $suratJalan): Response
    {
        return response()->view('warehouse.surat-jalan-print', [
            'suratJalan' => $suratJalan->load(['origin', 'destination', 'mitra', 'issuer', 'receiver', 'items.material.unit', 'items.serialNumber', 'items.drum']),
        ]);
    }

    public function transit(): Response
    {
        return response()->view('warehouse.transit', [
            'stocks' => MaterialStok::query()
                ->with(['material.unit', 'warehouse'])
                ->where('lokasi_tipe', 'transit')
                ->where('qty', '>', 0)
                ->orderBy('lokasi_id')
                ->get(),
        ]);
    }

    private function activeWarehouseRule(): Exists
    {
        return Rule::exists('warehouses', 'id')->where('aktif', true);
    }

    private function activeMaterialRule(): Exists
    {
        return Rule::exists('materials', 'id')->where(function (Builder $query): void {
            $query->where('aktif', true)->whereExists(function (Builder $units): void {
                $units->selectRaw('1')
                    ->from('units')
                    ->whereColumn('units.id', 'materials.unit_id')
                    ->where('units.aktif', true);
            });
        });
    }
}
