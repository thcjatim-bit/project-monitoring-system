<?php

namespace App\Http\Controllers;

use App\Models\MaterialStok;
use App\Models\MaterialTransaksi;
use App\Models\SuratJalan;
use App\Models\Warehouse;
use App\Rules\ActiveMaterial;
use App\Services\SuratJalanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\View\View;

class SuratJalanController extends Controller
{
    public function index(Request $request): View
    {
        $readOnlyTransit = $request->user()->mitra_id !== null
            && $request->user()->hasIzin('read_transit')
            && ! $request->user()->hasIzin('operate_warehouse');
        $warehouseIds = $readOnlyTransit
            ? []
            : $this->assignedWarehouses($request)->modelKeys();

        $transfers = SuratJalan::query()
            ->with(['origin', 'destination', 'items.material.unit'])
            ->when(
                $readOnlyTransit,
                fn ($query) => $query->where('mitra_id', $request->user()->mitra_id),
                fn ($query) => $query->where(function ($query) use ($warehouseIds): void {
                    $query->whereIn('warehouse_asal_id', $warehouseIds)
                        ->orWhereIn('warehouse_tujuan_id', $warehouseIds);
                }),
            )
            ->latest('tanggal')
            ->latest('id')
            ->get();

        return view('warehouse.transfers', [
            'readOnlyTransit' => $readOnlyTransit,
            'transfers' => $transfers,
        ]);
    }

    public function show(Request $request, SuratJalan $suratJalan): View
    {
        $suratJalan->load([
            'origin', 'destination', 'mitra', 'issuer', 'receiver',
            'returnedFrom', 'materialRequest', 'project',
            'items.material.unit', 'items.serialNumber', 'items.drum',
        ]);
        $canOperateOrigin = $request->user()->canOperateWarehouse($suratJalan->origin, 'operate_warehouse');
        $canOperateDestination = $request->user()->canOperateWarehouse($suratJalan->destination, 'operate_warehouse');

        abort_unless($canOperateOrigin || $canOperateDestination || $request->user()->hasIzin('read_transit'), 403);

        return view('warehouse.transfer-show', [
            'suratJalan' => $suratJalan,
            'transactions' => MaterialTransaksi::query()
                ->with(['material.unit', 'warehouse', 'actor', 'correctionSource'])
                ->where('surat_jalan_id', $suratJalan->id)
                ->latest()
                ->get(),
            'canReceive' => $canOperateDestination && $suratJalan->status === 'terbit',
            'canManageOrigin' => $canOperateOrigin,
            'canManageDestination' => $canOperateDestination,
        ]);
    }

    public function issue(Request $request, SuratJalanService $service): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_asal_id' => ['required', 'integer', $this->activeWarehouseRule()],
            'warehouse_tujuan_id' => ['required', 'integer', $this->activeWarehouseRule()],
            'material_request_id' => ['nullable', 'integer', Rule::exists('material_requests', 'id')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'tanggal' => ['required', 'date'],
            'pengirim' => ['required', 'string', 'max:255'],
            'sopir' => ['nullable', 'string', 'max:255'],
            'plat_nomor' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'integer', $this->activeMaterialRule()],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.serial_number' => ['nullable', 'string', 'max:255'],
            'items.*.drum_id' => ['nullable', 'string', 'max:255'],
            'items.*.catatan' => ['nullable', 'string', 'max:1000'],
            // `items.*.asal` sengaja tidak punya aturan. Asal-usul baris adalah kenyamanan UI,
            // bukan sumber kebenaran: server menghitung ulang klasifikasi dan tidak pernah
            // membaca nilai ini. Memvalidasinya berarti sebuah submit yang isinya sah bisa
            // ditolak hanya karena string yang tidak dibaca siapa pun tidak cocok.
        ]);

        $origin = Warehouse::query()->findOrFail($data['warehouse_asal_id']);
        $destination = Warehouse::query()->findOrFail($data['warehouse_tujuan_id']);
        abort_unless($request->user()->canOperateWarehouse($origin, 'operate_warehouse'), 403);
        abort_unless($request->user()->mitra_id === null || $destination->mitra_id === $request->user()->mitra_id, 403);

        $suratJalan = $service->issueDirect($request->user(), $data);

        return redirect()->route('warehouse.transfers.print', $suratJalan)->with('status', 'Surat Jalan diterbitkan.');
    }

    public function receive(Request $request, SuratJalan $suratJalan, SuratJalanService $service): RedirectResponse
    {
        $destination = Warehouse::query()->findOrFail($suratJalan->warehouse_tujuan_id);
        abort_unless($request->user()->canOperateWarehouse($destination, 'operate_warehouse'), 403);

        $data = $request->validate([
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.surat_jalan_item_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
        ]);

        $service->receive($request->user(), $suratJalan, $data['items'] ?? []);

        return back()->with('status', 'Surat Jalan diterima dan stok masuk ke Warehouse tujuan.');
    }

    public function resolve(Request $request, SuratJalan $suratJalan, SuratJalanService $service): RedirectResponse
    {
        $data = $request->validate([
            'resolution' => ['required', Rule::in(['hilang_dalam_perjalanan', 'kembali_ke_asal'])],
        ]);
        $this->ensureAssigned($request, $suratJalan->warehouse_asal_id);

        $service->resolveTransit($request->user(), $suratJalan, $data['resolution']);

        return back()->with('status', 'Selisih Transit telah diselesaikan.');
    }

    public function cancel(Request $request, SuratJalan $suratJalan, SuratJalanService $service): RedirectResponse
    {
        $this->ensureAssigned($request, $suratJalan->warehouse_asal_id);
        $service->cancel($request->user(), $suratJalan);

        return back()->with('status', 'Surat Jalan dibatalkan dan stok dikembalikan ke Warehouse asal.');
    }

    public function createReturn(Request $request, SuratJalan $suratJalan, SuratJalanService $service): RedirectResponse
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'pengirim' => ['required', 'string', 'max:255'],
            'sopir' => ['nullable', 'string', 'max:255'],
            'plat_nomor' => ['nullable', 'string', 'max:255'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.surat_jalan_item_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
        ]);
        $this->ensureAssigned($request, $suratJalan->warehouse_tujuan_id);

        $return = $service->createReturn($request->user(), $suratJalan, $data);

        return redirect()->route('warehouse.transfers.print', $return)->with('status', 'Retur Surat Jalan diterbitkan.');
    }

    public function correct(Request $request, MaterialTransaksi $materialTransaksi, SuratJalanService $service): RedirectResponse
    {
        $data = $request->validate([
            'qty_delta' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $this->ensureAssigned($request, $materialTransaksi->warehouse_id);

        $service->correct($request->user(), $materialTransaksi, (string) $data['qty_delta'], $data['reason']);

        return back()->with('status', 'Buku transaksi dikoreksi dengan baris kompensasi.');
    }

    public function print(SuratJalan $suratJalan): Response
    {
        return response()->view('warehouse.surat-jalan-print', [
            'suratJalan' => $suratJalan->load(['origin', 'destination', 'mitra', 'materialRequest', 'project', 'issuer', 'receiver', 'items.material.unit', 'items.serialNumber', 'items.drum']),
        ]);
    }

    public function transit(): Response
    {
        $user = request()->user();
        $readOnlyTransit = $user->mitra_id !== null
            && $user->hasIzin('read_transit')
            && ! $user->hasIzin('operate_warehouse');
        $warehouseIds = $readOnlyTransit ? [] : $this->assignedWarehouses(request())->modelKeys();

        $stocks = MaterialStok::query()
            ->with(['material.unit', 'warehouse', 'suratJalan.origin', 'suratJalan.destination', 'suratJalan.items'])
            ->where('lokasi_tipe', 'transit')
            ->where('qty', '>', 0)
            ->when(
                $readOnlyTransit,
                fn ($query) => $query->where('mitra_id', $user->mitra_id),
                fn ($query) => $query->whereIn('warehouse_id', $warehouseIds),
            )
            ->orderBy('lokasi_id')
            ->get();

        return response()->view('warehouse.transit', [
            'readOnlyTransit' => $readOnlyTransit,
            'stocks' => $this->transitRows($stocks),
        ]);
    }

    private function transitRows(Collection $stocks): Collection
    {
        return $stocks->flatMap(function (MaterialStok $stock): Collection {
            $items = $stock->suratJalan?->items;
            $items = $items?->filter(fn ($item): bool => (int) $item->material_id === (int) $stock->material_id)
                ->filter(fn ($item): bool => (float) $item->qty > (float) $item->qty_diterima);

            if ($items === null || $items->isEmpty()) {
                return collect([[
                    'material' => $stock->material,
                    'suratJalan' => $stock->suratJalan,
                    'qty' => $stock->qty,
                    'transit_label' => 'Dalam Transit',
                ]]);
            }

            return $items->map(fn ($item): array => [
                'material' => $stock->material,
                'suratJalan' => $stock->suratJalan,
                'qty' => max(0, (float) $item->qty - (float) $item->qty_diterima),
                'transit_label' => (float) $item->qty_diterima > 0.0 ? 'Sebagian diterima' : 'Dalam Transit',
            ]);
        });
    }

    private function activeWarehouseRule(): Exists
    {
        return Rule::exists('warehouses', 'id')->where('aktif', true);
    }

    private function activeMaterialRule(): ActiveMaterial
    {
        return new ActiveMaterial;
    }

    private function assignedWarehouses(Request $request): Collection
    {
        return Warehouse::query()
            ->with('mitra')
            ->where('aktif', true)
            ->whereHas('users', fn ($query) => $query->whereKey($request->user()->id))
            ->orderBy('nama')
            ->get();
    }

    private function ensureAssigned(Request $request, int $warehouseId): void
    {
        $warehouse = Warehouse::query()->findOrFail($warehouseId);
        abort_unless($request->user()->canOperateWarehouse($warehouse, 'operate_warehouse'), 403);
    }
}
