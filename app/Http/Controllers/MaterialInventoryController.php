<?php

namespace App\Http\Controllers;

use App\Models\Drum;
use App\Models\Material;
use App\Models\MaterialStok;
use App\Models\MaterialTransaksi;
use App\Models\SuratJalan;
use App\Models\Warehouse;
use App\Queries\SuratJalanFormQuery;
use App\Rules\ActiveMaterial;
use App\Services\MaterialInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MaterialInventoryController extends Controller
{
    public function index(Request $request, SuratJalanFormQuery $transferForm): View
    {
        $warehouses = $this->assignedWarehouses($request);
        $warehouseIds = $warehouses->modelKeys();
        $destinationWarehouses = $this->destinationWarehouses($request);
        // Form request-driven ini dibangun untuk petugas gudang THC, jadi User Mitra tidak
        // mendapat formnya maupun data yang menyuapinya. Ini penyembunyian elemen antarmuka,
        // bukan aturan otorisasi: `SuratJalanController::issue()` tetap penjaga sesungguhnya
        // dan aturannya tidak diubah tiket ini.
        $canIssueTransfer = $request->user()->mitra_id === null;

        return view('warehouse.index', [
            'warehouses' => $warehouses,
            'destinationWarehouses' => $destinationWarehouses,
            'canIssueTransfer' => $canIssueTransfer,
            // Pilihan awal kedua dropdown gudang berangkat dari old(): POST yang ditolak kembali
            // ke halaman ini, dan konteks gudangnya harus cocok dengan baris yang dipulihkan.
            'transferFormData' => $canIssueTransfer
                ? $transferForm->forOperator(
                    $warehouses,
                    $destinationWarehouses,
                    $this->oldInteger('warehouse_asal_id'),
                    $this->oldInteger('warehouse_tujuan_id'),
                    $this->oldInteger('material_request_id'),
                )
                : null,
            'materials' => $this->activeMaterials(),
            'stocks' => MaterialStok::query()
                ->with(['warehouse', 'material.unit'])
                ->whereIn('warehouse_id', $warehouseIds)
                ->where('lokasi_tipe', 'warehouse')
                ->where('qty', '>', 0)
                ->orderBy('warehouse_id')
                ->orderBy('material_id')
                ->get(),
            'drums' => Drum::query()
                ->with('material.unit')
                ->where('lokasi_tipe', 'warehouse')
                ->whereIn('lokasi_id', $warehouseIds)
                ->where('sisa', '>', 0)
                ->orderBy('drum_id')
                ->get(),
            'transactions' => MaterialTransaksi::query()
                ->with(['warehouse', 'material.unit', 'actor'])
                ->whereIn('warehouse_id', $warehouseIds)
                ->latest()
                ->limit(20)
                ->get(),
            'suratJalanMasuk' => SuratJalan::query()
                ->with(['origin', 'destination', 'items.material.unit'])
                ->where('status', 'terbit')
                ->whereIn('warehouse_tujuan_id', $warehouseIds)
                ->latest('tanggal')
                ->latest('id')
                ->get(),
        ]);
    }

    public function receive(Request $request, MaterialInventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('aktif', true)],
            'material_id' => ['required', 'integer', $this->activeMaterialRule()],
            'qty' => ['required', 'numeric', 'gt:0'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'drum_id' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        abort_unless($request->user()->canOperateWarehouse($warehouse, 'operate_warehouse'), 403);
        $material = Material::findOrFail($data['material_id']);
        $this->validateIdentityFields($material->jenis, $data);
        $inventory->receive($request->user(), $warehouse, (int) $data['material_id'], (string) $data['qty'], $data['reason'], $data['serial_number'] ?? null, $data['drum_id'] ?? null);

        return back()->with('status', 'Penerimaan material dicatat.');
    }

    public function issue(Request $request, MaterialInventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('aktif', true)],
            'material_id' => ['required', 'integer', $this->activeMaterialRule()],
            'qty' => ['required', 'numeric', 'gt:0'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'drum_id' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        abort_unless($request->user()->canOperateWarehouse($warehouse, 'operate_warehouse'), 403);
        $material = Material::findOrFail($data['material_id']);
        $this->validateIdentityFields($material->jenis, $data);
        $inventory->issue($request->user(), $warehouse, (int) $data['material_id'], (string) $data['qty'], $data['reason'], $data['serial_number'] ?? null, $data['drum_id'] ?? null);

        return back()->with('status', 'Pengeluaran material dicatat.');
    }

    public function splitDrum(Request $request, MaterialInventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('aktif', true)],
            'drum_id' => ['required', 'string', 'max:255'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        abort_unless($request->user()->canOperateWarehouse($warehouse, 'operate_warehouse'), 403);
        $inventory->splitDrum($request->user(), $warehouse, $data['drum_id'], (string) $data['qty'], $data['reason']);

        return back()->with('status', 'Potongan drum dicatat.');
    }

    private function activeMaterialRule(): ActiveMaterial
    {
        return new ActiveMaterial;
    }

    private function activeMaterials(): Collection
    {
        return Material::query()
            ->with('unit')
            ->activeWithUnit()
            ->orderBy('nama')
            ->get();
    }

    private function oldInteger(string $field): ?int
    {
        $value = old($field);

        return is_numeric($value) ? (int) $value : null;
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

    private function destinationWarehouses(Request $request): Collection
    {
        return Warehouse::query()
            ->with('mitra')
            ->where('aktif', true)
            ->when($request->user()->mitra_id !== null, fn ($query) => $query->where('mitra_id', $request->user()->mitra_id))
            ->orderBy('nama')
            ->get();
    }

    private function validateIdentityFields(string $jenis, array $data): void
    {
        if ($jenis === 'biasa' && (($data['serial_number'] ?? null) !== null || ($data['drum_id'] ?? null) !== null)) {
            throw ValidationException::withMessages(['material_id' => 'Material biasa tidak menggunakan identitas SN atau drum.']);
        }
        if ($jenis === 'ber_sn' && empty($data['serial_number'])) {
            throw ValidationException::withMessages(['serial_number' => 'Serial Number wajib diisi untuk material ber-SN.']);
        }
        if ($jenis === 'drum_kabel' && empty($data['drum_id'])) {
            throw ValidationException::withMessages(['drum_id' => 'Drum ID wajib diisi untuk material drum kabel.']);
        }
        if ($jenis === 'ber_sn' && ($data['drum_id'] ?? null) !== null) {
            throw ValidationException::withMessages(['drum_id' => 'Material ber-SN tidak menggunakan Drum ID.']);
        }
        if ($jenis === 'drum_kabel' && ($data['serial_number'] ?? null) !== null) {
            throw ValidationException::withMessages(['serial_number' => 'Material drum kabel tidak menggunakan Serial Number.']);
        }
    }
}
