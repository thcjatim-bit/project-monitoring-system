<?php

namespace App\Services;

use App\Models\Drum;
use App\Models\Material;
use App\Models\MaterialRequest;
use App\Models\MaterialSn;
use App\Models\MaterialStok;
use App\Models\MaterialTransaksi;
use App\Models\Project;
use App\Models\ProjectTimeline;
use App\Models\SuratJalan;
use App\Models\SuratJalanItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\QtyTolerance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SuratJalanService
{
    public function __construct(private readonly MaterialInventoryService $inventory) {}

    /** @param array{warehouse_asal_id:int, warehouse_tujuan_id:int, tanggal:string, pengirim:string, project_id?:int|null, material_request_id?:int|null, sopir?:string|null, plat_nomor?:string|null, items:array<int,array{material_id:int,qty:string|int|float,serial_number?:string|null,drum_id?:string|null,catatan?:string|null}>} $data */
    public function issueDirect(User $actor, array $data): SuratJalan
    {
        return DB::transaction(fn (): SuratJalan => $this->issueTransfer($actor, $data));
    }

    /** @param array<int,array{surat_jalan_item_id:int,qty:string|int|float}> $receivedItems */
    public function receive(User $actor, SuratJalan $suratJalan, array $receivedItems = []): SuratJalan
    {
        return DB::transaction(function () use ($actor, $suratJalan, $receivedItems): SuratJalan {
            $suratJalan = SuratJalan::query()->with('items.material')->lockForUpdate()->findOrFail($suratJalan->id);
            if ($suratJalan->status !== 'terbit') {
                throw ValidationException::withMessages(['status' => 'Surat Jalan sudah tidak berstatus terbit.']);
            }

            $origin = Warehouse::query()->findOrFail($suratJalan->warehouse_asal_id);
            $destination = Warehouse::query()->findOrFail($suratJalan->warehouse_tujuan_id);
            $requested = $this->requestedReceiptQuantities($suratJalan, $receivedItems);

            foreach ($suratJalan->items as $item) {
                $qty = $requested[$item->id] ?? '0';
                if ((float) $qty <= 0) {
                    continue;
                }

                $this->ensureTransitAvailability($item, $suratJalan, $qty);
                $this->moveFromTransit($actor, $suratJalan, $item, $origin, $destination, $qty);
                $item->update(['qty_diterima' => $this->formatQuantity((float) $item->qty_diterima + (float) $qty)]);
            }

            if ($this->isFullyReceived($suratJalan->fresh('items'))) {
                $suratJalan->update([
                    'status' => 'diterima',
                    'received_by' => $actor->id,
                    'received_at' => now(),
                ]);
            }

            $this->updateMaterialRequestStatus($suratJalan);
            $this->recordProjectEvent($suratJalan, $actor, 'surat_jalan_received', [
                'status' => $suratJalan->status,
            ]);

            return $suratJalan->fresh(['origin', 'destination', 'items.material', 'items.serialNumber', 'items.drum', 'receiver']);
        });
    }

    public function resolveTransit(User $actor, SuratJalan $suratJalan, string $resolution): SuratJalan
    {
        if (! in_array($resolution, ['hilang_dalam_perjalanan', 'kembali_ke_asal'], true)) {
            throw ValidationException::withMessages(['resolution' => 'Penyelesaian Transit tidak valid.']);
        }

        return DB::transaction(function () use ($actor, $suratJalan, $resolution): SuratJalan {
            $suratJalan = SuratJalan::query()->with('items.material')->lockForUpdate()->findOrFail($suratJalan->id);
            if ($suratJalan->status !== 'terbit') {
                throw ValidationException::withMessages(['status' => 'Transit hanya dapat diselesaikan saat Surat Jalan berstatus terbit.']);
            }

            $origin = Warehouse::query()->findOrFail($suratJalan->warehouse_asal_id);
            foreach ($suratJalan->items as $item) {
                $remaining = $this->remainingQuantity($item);
                if ($remaining <= 0) {
                    continue;
                }

                $this->ensureTransitAvailability($item, $suratJalan, $this->formatQuantity($remaining));
                if ($resolution === 'kembali_ke_asal') {
                    $this->moveFromTransit($actor, $suratJalan, $item, $origin, $origin, $this->formatQuantity($remaining));
                } else {
                    $this->record(
                        $actor,
                        $suratJalan,
                        $item,
                        $origin,
                        'transit',
                        $suratJalan->id,
                        '-'.$this->formatQuantity($remaining),
                        $suratJalan->mitra_id,
                        'hilang_dalam_perjalanan',
                    );
                    $this->markIdentityLost($item);
                }
            }

            $suratJalan->update([
                'status' => 'diterima',
                'transit_resolution' => $resolution,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
                'received_by' => $suratJalan->received_by ?? $actor->id,
                'received_at' => $suratJalan->received_at ?? now(),
            ]);
            $this->recordProjectEvent($suratJalan, $actor, 'surat_jalan_resolved', [
                'resolution' => $resolution,
            ]);

            return $suratJalan->fresh(['origin', 'destination', 'items.material', 'items.serialNumber', 'items.drum', 'receiver']);
        });
    }

    public function cancel(User $actor, SuratJalan $suratJalan): SuratJalan
    {
        return DB::transaction(function () use ($actor, $suratJalan): SuratJalan {
            $suratJalan = SuratJalan::query()->with('items.material')->lockForUpdate()->findOrFail($suratJalan->id);
            if ($suratJalan->status !== 'terbit') {
                throw ValidationException::withMessages(['status' => 'Surat Jalan hanya dapat dibatalkan saat masih terbit.']);
            }
            if ($suratJalan->items->contains(fn (SuratJalanItem $item): bool => (float) $item->qty_diterima > 0)) {
                throw ValidationException::withMessages(['status' => 'Surat Jalan yang sudah diterima tidak dapat dibatalkan.']);
            }

            $origin = Warehouse::query()->findOrFail($suratJalan->warehouse_asal_id);
            foreach ($suratJalan->items as $item) {
                $this->ensureTransitAvailability($item, $suratJalan, (string) $item->qty);
                $this->moveFromTransit($actor, $suratJalan, $item, $origin, $origin, (string) $item->qty);
            }

            $suratJalan->update(['status' => 'dibatalkan']);
            $this->recordProjectEvent($suratJalan, $actor, 'surat_jalan_cancelled');

            return $suratJalan->fresh(['origin', 'destination', 'items.material', 'items.serialNumber', 'items.drum']);
        });
    }

    /** @param array{tanggal:string,pengirim:string,sopir?:string|null,plat_nomor?:string|null,items?:array<int,array{surat_jalan_item_id:int,qty:string|int|float}>} $data */
    public function createReturn(User $actor, SuratJalan $original, array $data): SuratJalan
    {
        return DB::transaction(function () use ($actor, $original, $data): SuratJalan {
            $original = SuratJalan::query()->with('items.material')->lockForUpdate()->findOrFail($original->id);
            if ($original->status !== 'diterima') {
                throw ValidationException::withMessages(['status' => 'Retur hanya dapat dibuat dari Surat Jalan yang sudah diterima.']);
            }

            $origin = Warehouse::query()->findOrFail($original->warehouse_tujuan_id);
            $destination = Warehouse::query()->findOrFail($original->warehouse_asal_id);
            $returnQuantities = $this->requestedReturnQuantities($original, $data['items'] ?? []);
            $items = [];
            foreach ($original->items as $item) {
                $qty = $returnQuantities[$item->id] ?? '0';
                if ((float) $qty <= 0) {
                    continue;
                }

                $items[] = [
                    'material_id' => $item->material_id,
                    'qty' => $qty,
                    'serial_number' => $item->serialNumber?->serial_number,
                    'drum_id' => $item->drum?->drum_id,
                ];
            }
            if ($items === []) {
                throw ValidationException::withMessages(['items' => 'Tidak ada material yang dapat diretur.']);
            }

            $return = $this->issueTransfer($actor, [
                'warehouse_asal_id' => $origin->id,
                'warehouse_tujuan_id' => $destination->id,
                'tanggal' => $data['tanggal'],
                'pengirim' => $data['pengirim'],
                'project_id' => $original->project_id,
                'material_request_id' => $original->material_request_id,
                'sopir' => $data['sopir'] ?? null,
                'plat_nomor' => $data['plat_nomor'] ?? null,
                'items' => $items,
            ], $original->id);

            foreach ($original->items as $item) {
                $qty = $returnQuantities[$item->id] ?? '0';
                if ((float) $qty > 0) {
                    $item->update(['qty_diretur' => $this->formatQuantity((float) $item->qty_diretur + (float) $qty)]);
                }
            }
            $this->recordProjectEvent($return, $actor, 'surat_jalan_returned', [
                'returned_from_id' => $original->id,
            ]);

            return $return->fresh(['origin', 'destination', 'returnedFrom', 'items.material', 'items.serialNumber', 'items.drum']);
        });
    }

    public function correct(User $actor, MaterialTransaksi $original, string $correctedQty, string $reason): MaterialTransaksi
    {
        $correctedQty = $this->formatQuantity((float) $correctedQty);
        if ((float) $correctedQty === 0.0) {
            throw ValidationException::withMessages(['qty_delta' => 'Nilai koreksi tidak boleh nol.']);
        }
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan koreksi wajib diisi.']);
        }

        return DB::transaction(function () use ($actor, $original, $correctedQty, $reason): MaterialTransaksi {
            $original = MaterialTransaksi::query()->with('suratJalan')->findOrFail($original->id);
            if ($original->koreksi_dari_id !== null || $original->suratJalan?->status !== 'diterima') {
                throw ValidationException::withMessages(['status' => 'Hanya transaksi Surat Jalan yang sudah diterima yang dapat dikoreksi.']);
            }
            if (($original->qty_delta > 0) !== ((float) $correctedQty > 0)) {
                throw ValidationException::withMessages(['qty_delta' => 'Arah transaksi koreksi harus tetap sama.']);
            }
            if ($original->material_sn_id !== null && abs((float) $correctedQty) !== 1.0) {
                throw ValidationException::withMessages(['qty_delta' => 'Koreksi material ber-SN harus tetap qty 1.']);
            }

            $attributes = [
                'warehouse_id' => $original->warehouse_id,
                'material_id' => $original->material_id,
                'lokasi_tipe' => $original->lokasi_tipe,
                'lokasi_id' => $original->lokasi_id,
                'material_sn_id' => $original->material_sn_id,
                'drum_id' => $original->drum_id,
                'project_id' => $original->project_id,
                'mitra_id' => $original->mitra_id,
                'surat_jalan_id' => $original->surat_jalan_id,
                'koreksi_dari_id' => $original->id,
                'reason' => $reason,
                'actor_id' => $actor->id,
            ];

            MaterialTransaksi::query()->create($attributes + [
                'jenis_transaksi' => 'koreksi',
                'qty_delta' => $this->formatQuantity(-1 * (float) $original->qty_delta),
            ]);

            return MaterialTransaksi::query()->create($attributes + [
                'jenis_transaksi' => 'koreksi',
                'qty_delta' => $correctedQty,
            ]);
        });
    }

    /** @param array{warehouse_asal_id:int,warehouse_tujuan_id:int,tanggal:string,pengirim:string,project_id?:int|null,material_request_id?:int|null,sopir?:string|null,plat_nomor?:string|null,items:array<int,array{material_id:int,qty:string|int|float,serial_number?:string|null,drum_id?:string|null,catatan?:string|null}>} $data */
    private function issueTransfer(User $actor, array $data, ?int $returnedFromId = null): SuratJalan
    {
        $origin = Warehouse::query()->lockForUpdate()->findOrFail($data['warehouse_asal_id']);
        $destination = Warehouse::query()->lockForUpdate()->findOrFail($data['warehouse_tujuan_id']);
        if ($origin->id === $destination->id) {
            throw ValidationException::withMessages(['warehouse_tujuan_id' => 'Warehouse tujuan harus berbeda dari asal.']);
        }
        $this->ensureEachDrumAppearsOnce($data['items']);

        $tanggal = CarbonImmutable::parse($data['tanggal']);
        $mitraId = $origin->mitra_id ?? $destination->mitra_id;
        $materialRequest = $this->lockMaterialRequest($data['material_request_id'] ?? null, $mitraId);
        $deviations = $materialRequest === null
            ? []
            : $this->classifyRequestDeviations($materialRequest, $data['items']);
        $this->ensureDeviatingLinesAreExplained($data['items'], $deviations);
        $projectId = $data['project_id'] ?? $materialRequest?->project_id;
        if ($projectId !== null) {
            $project = Project::query()->findOrFail($projectId);
            if ($project->mitra_id !== $mitraId || ($materialRequest?->project_id !== null && $materialRequest->project_id !== $project->id)) {
                throw ValidationException::withMessages(['project_id' => 'Project tidak cocok dengan Mitra atau Request Material.']);
            }
        }
        $suratJalan = SuratJalan::query()->create([
            'nomor' => $this->nextNumber($tanggal),
            'tanggal' => $tanggal->toDateString(),
            'warehouse_asal_id' => $origin->id,
            'warehouse_tujuan_id' => $destination->id,
            'mitra_id' => $mitraId,
            'material_request_id' => $materialRequest?->id,
            'project_id' => $projectId,
            'retur_dari_id' => $returnedFromId,
            'issued_by' => $actor->id,
            'issued_at' => now(),
            'status' => 'terbit',
            'pengirim' => $data['pengirim'],
            'sopir' => $data['sopir'] ?? null,
            'plat_nomor' => $data['plat_nomor'] ?? null,
        ]);

        $deviationMaterialNames = [
            'material_asing' => [],
            'qty_melebihi' => [],
        ];
        foreach ($data['items'] as $itemData) {
            $this->ensurePositiveQuantity((string) $itemData['qty']);
            $material = Material::query()->findOrFail($itemData['material_id']);
            $deviation = $deviations[(int) $itemData['material_id']] ?? null;
            if ($deviation !== null) {
                $deviationMaterialNames[$deviation][] = $material->nama;
            }
            $item = $this->createItem($actor, $suratJalan, $material, $origin, $itemData, $mitraId, $deviation);
            $this->moveToTransit($actor, $suratJalan, $item, $origin, $mitraId);
        }

        $this->recordProjectEvent($suratJalan, $actor, 'surat_jalan_issued', [
            'status' => $suratJalan->status,
        ]);
        if ($deviations !== []) {
            $this->recordProjectEvent($suratJalan, $actor, 'surat_jalan_deviation', [
                'material_asing' => array_values(array_unique($deviationMaterialNames['material_asing'])),
                'qty_melebihi' => array_values(array_unique($deviationMaterialNames['qty_melebihi'])),
            ]);
        }

        return $suratJalan->load(['origin', 'destination', 'items.material', 'items.serialNumber', 'items.drum']);
    }

    private function lockMaterialRequest(?int $requestId, ?int $mitraId): ?MaterialRequest
    {
        if ($requestId === null) {
            return null;
        }

        $request = MaterialRequest::query()->with('items')->lockForUpdate()->findOrFail($requestId);
        if (! in_array($request->status, MaterialRequest::FULFILLABLE_STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Hanya Request Material yang sudah disetujui dapat dipenuhi.']);
        }
        if ($request->mitra_id !== $mitraId) {
            throw ValidationException::withMessages(['status' => 'Request Material tidak cocok dengan Warehouse tujuan.']);
        }

        return $request;
    }

    /**
     * Daftar Request Material adalah prefill, bukan plafon: material di luar daftar dan qty melebihi
     * sisa tetap boleh terbit, hanya ditandai. Klasifikasi ini dihitung sekali saat terbit dan
     * disimpan di baris, jadi Surat Jalan yang sudah terbit tidak berubah arti ketika sisa bergerak.
     *
     * @param  array<int,array{material_id:int,qty:string|int|float}>  $items
     * @return array<int,string> jenis penyimpangan per material_id
     */
    private function classifyRequestDeviations(MaterialRequest $request, array $items): array
    {
        $requested = $request->items
            ->groupBy('material_id')
            ->map(fn ($materialItems): float => (float) $materialItems->sum('qty'));
        $sent = SuratJalanItem::query()
            ->whereHas('suratJalan', fn ($query) => $query->where('material_request_id', $request->id))
            ->join('surat_jalans', 'surat_jalans.id', '=', 'surat_jalan_items.surat_jalan_id')
            ->where('surat_jalans.mitra_id', $request->mitra_id)
            ->where('surat_jalans.status', '!=', 'dibatalkan')
            ->select('surat_jalan_items.material_id', DB::raw(SuratJalanItem::SENT_QUANTITY.' as qty_sent'))
            ->groupBy('material_id')
            ->pluck('qty_sent', 'material_id')
            ->map(fn ($qty): float => (float) $qty);
        $fulfillment = collect($items)->groupBy('material_id')->map(fn ($materialItems): float => (float) collect($materialItems)->sum('qty'));

        $deviations = [];
        foreach ($fulfillment as $materialId => $qty) {
            $materialId = (int) $materialId;
            if ($requested->get($materialId) === null) {
                $deviations[$materialId] = 'material_asing';

                continue;
            }
            $remaining = $requested->get($materialId, 0.0) - $sent->get($materialId, 0.0);
            if ($qty > $remaining + QtyTolerance::VALUE) {
                $deviations[$materialId] = 'qty_melebihi';
            }
        }

        return $deviations;
    }

    /**
     * @param  array<int,array{material_id:int,catatan?:string|null}>  $items
     * @param  array<int,string>  $deviations
     */
    private function ensureDeviatingLinesAreExplained(array $items, array $deviations): void
    {
        foreach ($items as $itemData) {
            if (! isset($deviations[(int) $itemData['material_id']])) {
                continue;
            }
            if (trim((string) ($itemData['catatan'] ?? '')) === '') {
                throw ValidationException::withMessages(['items' => 'Baris yang menyimpang dari Request Material wajib memiliki catatan.']);
            }
        }
    }

    private function updateMaterialRequestStatus(SuratJalan $suratJalan): void
    {
        if ($suratJalan->material_request_id === null) {
            return;
        }

        $request = MaterialRequest::query()->with('items')->lockForUpdate()->findOrFail($suratJalan->material_request_id);
        if ($request->status === 'ditutup') {
            return;
        }

        $received = SuratJalanItem::query()
            ->whereHas('suratJalan', fn ($query) => $query->where('material_request_id', $request->id))
            ->select('material_id', DB::raw('SUM(qty_diterima) as qty_received'))
            ->groupBy('material_id')
            ->pluck('qty_received', 'material_id')
            ->map(fn ($qty): float => (float) $qty);
        $requested = $request->items
            ->groupBy('material_id')
            ->map(fn ($materialItems): float => (float) $materialItems->sum('qty'));
        $hasReceipt = false;
        $isComplete = true;

        foreach ($requested as $materialId => $qty) {
            $receivedQty = $received->get((int) $materialId, 0.0);
            $hasReceipt = $hasReceipt || $receivedQty > 0.0005;
            $isComplete = $isComplete && $receivedQty + 0.0005 >= $qty;
        }

        $status = $isComplete ? 'selesai' : ($hasReceipt ? 'terpenuhi_sebagian' : 'disetujui');
        if ($request->status !== $status) {
            $request->update(['status' => $status]);
        }
    }

    /** @param array<int,array{surat_jalan_item_id:int,qty:string|int|float}> $receivedItems @return array<int,string> */
    private function requestedReceiptQuantities(SuratJalan $suratJalan, array $receivedItems): array
    {
        if ($receivedItems === []) {
            return $suratJalan->items->mapWithKeys(fn (SuratJalanItem $item): array => [$item->id => $this->formatQuantity($this->remainingQuantity($item))])->all();
        }

        $quantities = [];
        foreach ($receivedItems as $data) {
            $item = $suratJalan->items->firstWhere('id', $data['surat_jalan_item_id']);
            if ($item === null) {
                throw ValidationException::withMessages(['items' => 'Baris Surat Jalan tidak ditemukan.']);
            }
            $qty = (float) $data['qty'];
            if ($qty <= 0 || $qty > $this->remainingQuantity($item) + 0.0005) {
                throw ValidationException::withMessages(['items' => 'Qty diterima melebihi sisa Transit.']);
            }
            $quantities[$item->id] = $this->formatQuantity($qty);
        }

        return $quantities;
    }

    /** @param array<int,array{surat_jalan_item_id:int,qty:string|int|float}> $returnItems @return array<int,string> */
    private function requestedReturnQuantities(SuratJalan $suratJalan, array $returnItems): array
    {
        if ($returnItems === []) {
            return $suratJalan->items->mapWithKeys(fn (SuratJalanItem $item): array => [
                $item->id => $this->formatQuantity((float) $item->qty_diterima - (float) $item->qty_diretur),
            ])->all();
        }

        $quantities = [];
        foreach ($returnItems as $data) {
            $item = $suratJalan->items->firstWhere('id', $data['surat_jalan_item_id']);
            if ($item === null) {
                throw ValidationException::withMessages(['items' => 'Baris Surat Jalan tidak ditemukan.']);
            }
            $qty = (float) $data['qty'];
            $available = (float) $item->qty_diterima - (float) $item->qty_diretur;
            if ($qty <= 0 || $qty > $available + 0.0005) {
                throw ValidationException::withMessages(['items' => 'Qty retur melebihi qty yang sudah diterima.']);
            }
            $quantities[$item->id] = $this->formatQuantity($qty);
        }

        return $quantities;
    }

    private function isFullyReceived(SuratJalan $suratJalan): bool
    {
        return $suratJalan->items->every(fn (SuratJalanItem $item): bool => $this->remainingQuantity($item) <= 0.0005);
    }

    private function remainingQuantity(SuratJalanItem $item): float
    {
        return max(0, (float) $item->qty - (float) $item->qty_diterima);
    }

    /** @param array{material_id:int,qty:string|int|float,serial_number?:string|null,drum_id?:string|null,catatan?:string|null} $data */
    private function createItem(User $actor, SuratJalan $suratJalan, Material $material, Warehouse $origin, array $data, ?int $mitraId, ?string $deviation = null): SuratJalanItem
    {
        $qty = (string) $data['qty'];
        $item = [
            'surat_jalan_id' => $suratJalan->id,
            'mitra_id' => $mitraId,
            'material_id' => $material->id,
            'qty' => $qty,
            'catatan' => $this->normalizeNote($data['catatan'] ?? null),
            'jenis_penyimpangan' => $deviation,
        ];

        if ($material->jenis === 'biasa') {
            if (($data['serial_number'] ?? null) !== null || ($data['drum_id'] ?? null) !== null) {
                throw ValidationException::withMessages(['items' => 'Material biasa tidak menggunakan identitas SN atau drum.']);
            }
            $this->ensureOrdinaryAvailability($origin, $material, $qty);
        } elseif ($material->jenis === 'ber_sn') {
            if (empty($data['serial_number']) || (float) $qty !== 1.0 || ($data['drum_id'] ?? null) !== null) {
                throw ValidationException::withMessages(['items' => 'Material ber-SN wajib membawa satu Serial Number.']);
            }
            $serial = MaterialSn::query()->where('material_id', $material->id)->where('serial_number', $data['serial_number'])->lockForUpdate()->first();
            if ($serial === null || $serial->status !== 'tersedia' || $serial->lokasi_tipe !== 'warehouse' || (int) $serial->lokasi_id !== $origin->id) {
                throw ValidationException::withMessages(['items' => 'Serial Number tidak tersedia di Warehouse asal.']);
            }
            $item['material_sn_id'] = $serial->id;
        } elseif ($material->jenis === 'drum_kabel') {
            if (empty($data['drum_id']) || ($data['serial_number'] ?? null) !== null) {
                throw ValidationException::withMessages(['items' => 'Material drum kabel wajib membawa Drum ID.']);
            }
            $drum = Drum::query()->where('material_id', $material->id)->where('drum_id', $data['drum_id'])->lockForUpdate()->first();
            if ($drum === null || $drum->lokasi_tipe !== 'warehouse' || (int) $drum->lokasi_id !== $origin->id) {
                throw ValidationException::withMessages(['items' => 'Drum tidak tersedia di Warehouse asal.']);
            }
            $carrier = $this->drumForItem($actor, $suratJalan, $origin, $drum, $qty);
            $item['drum_id'] = $carrier->id;
            $item['qty'] = $this->formatQuantity((float) $carrier->sisa);
        } else {
            throw ValidationException::withMessages(['items' => 'Jenis material tidak didukung.']);
        }

        return SuratJalanItem::query()->create($item);
    }

    /**
     * Drum yang menjadi identitas baris Surat Jalan. Qty di bawah sisa melahirkan turunan
     * yang berangkat sementara induknya tinggal di gudang asal; qty tepat sama dengan sisa
     * mengirim Drum itu sendiri. Baris selalu membawa seluruh sisa Drum yang berangkat.
     */
    private function drumForItem(User $actor, SuratJalan $suratJalan, Warehouse $origin, Drum $drum, string $qty): Drum
    {
        $sisa = (float) $drum->sisa;
        if ($sisa + 0.0005 < (float) $qty) {
            throw ValidationException::withMessages(['items' => 'Qty melebihi sisa Drum.']);
        }
        if ((float) $qty + 0.0005 >= $sisa) {
            return $drum;
        }

        return $this->inventory->splitDrum($actor, $origin, $drum->drum_id, $qty, 'Surat Jalan '.$suratJalan->nomor);
    }

    /** @param array<int,array{drum_id?:string|null}> $items */
    private function ensureEachDrumAppearsOnce(array $items): void
    {
        $drumIds = array_values(array_filter(
            array_map(fn (array $item): ?string => $item['drum_id'] ?? null, $items),
            fn (?string $drumId): bool => $drumId !== null && $drumId !== '',
        ));
        if (count($drumIds) !== count(array_unique($drumIds))) {
            throw ValidationException::withMessages(['items' => 'Satu Drum hanya boleh muncul sekali per Surat Jalan.']);
        }
    }

    private function normalizeNote(?string $note): ?string
    {
        $note = trim((string) $note);

        return $note === '' ? null : $note;
    }

    private function moveToTransit(User $actor, SuratJalan $suratJalan, SuratJalanItem $item, Warehouse $origin, ?int $mitraId): void
    {
        $this->record($actor, $suratJalan, $item, $origin, 'warehouse', $origin->id, '-'.$item->qty, $mitraId, 'transfer');
        $this->record($actor, $suratJalan, $item, $origin, 'transit', $suratJalan->id, $item->qty, $mitraId, 'transfer');
        $this->moveIdentity($item, 'transit', $suratJalan->id, $mitraId);
    }

    private function moveFromTransit(User $actor, SuratJalan $suratJalan, SuratJalanItem $item, Warehouse $origin, Warehouse $destination, string $qty): void
    {
        $this->record($actor, $suratJalan, $item, $origin, 'transit', $suratJalan->id, '-'.$qty, $suratJalan->mitra_id, 'transfer');
        $this->record($actor, $suratJalan, $item, $destination, 'warehouse', $destination->id, $qty, $destination->mitra_id, 'receipt');
        if ($item->material_sn_id !== null || $item->drum_id !== null) {
            $this->moveIdentity($item, 'warehouse', $destination->id, $destination->mitra_id);
        }
    }

    private function record(User $actor, SuratJalan $suratJalan, SuratJalanItem $item, Warehouse $warehouse, string $locationType, int $locationId, string $qtyDelta, ?int $mitraId, string $jenisTransaksi): MaterialTransaksi
    {
        return MaterialTransaksi::query()->create([
            'warehouse_id' => $warehouse->id,
            'material_id' => $item->material_id,
            'jenis_transaksi' => $jenisTransaksi,
            'lokasi_tipe' => $locationType,
            'lokasi_id' => $locationId,
            'qty_delta' => $qtyDelta,
            'material_sn_id' => $item->material_sn_id,
            'drum_id' => $item->drum_id,
            'mitra_id' => $mitraId,
            'surat_jalan_id' => $suratJalan->id,
            'reason' => 'Surat Jalan '.$suratJalan->nomor,
            'actor_id' => $actor->id,
        ]);
    }

    private function moveIdentity(SuratJalanItem $item, string $locationType, int $locationId, ?int $mitraId): void
    {
        if ($item->material_sn_id !== null) {
            MaterialSn::query()->whereKey($item->material_sn_id)->update([
                'lokasi_tipe' => $locationType,
                'lokasi_id' => $locationId,
                'mitra_id' => $mitraId,
            ]);
        }
        if ($item->drum_id !== null) {
            Drum::query()->whereKey($item->drum_id)->update([
                'lokasi_tipe' => $locationType,
                'lokasi_id' => $locationId,
                'mitra_id' => $mitraId,
            ]);
        }
    }

    private function markIdentityLost(SuratJalanItem $item): void
    {
        if ($item->material_sn_id !== null) {
            MaterialSn::query()->whereKey($item->material_sn_id)->update(['status' => 'hilang']);
        }
    }

    private function ensureOrdinaryAvailability(Warehouse $origin, Material $material, string $qty): void
    {
        $stock = MaterialStok::query()->where('warehouse_id', $origin->id)->where('material_id', $material->id)
            ->where('lokasi_tipe', 'warehouse')->where('lokasi_id', $origin->id)->first();
        if ($stock === null || (float) $stock->qty + 0.0005 < (float) $qty) {
            throw ValidationException::withMessages(['items' => 'Saldo material tidak mencukupi di Warehouse asal.']);
        }
    }

    private function ensureTransitAvailability(SuratJalanItem $item, SuratJalan $suratJalan, string $qty): void
    {
        if ($item->material_sn_id !== null) {
            if ((float) $qty !== $this->remainingQuantity($item)) {
                throw ValidationException::withMessages(['items' => 'Material ber-SN harus diterima seluruhnya.']);
            }
            $serial = MaterialSn::query()->lockForUpdate()->find($item->material_sn_id);
            if ($serial === null || $serial->lokasi_tipe !== 'transit' || (int) $serial->lokasi_id !== $suratJalan->id) {
                throw ValidationException::withMessages(['status' => 'Serial Number tidak berada di Transit Surat Jalan ini.']);
            }

            return;
        }

        if ($item->drum_id !== null) {
            if ((float) $qty !== $this->remainingQuantity($item)) {
                throw ValidationException::withMessages(['items' => 'Material drum kabel harus diterima seluruhnya.']);
            }
            $drum = Drum::query()->lockForUpdate()->find($item->drum_id);
            if ($drum === null || $drum->lokasi_tipe !== 'transit' || (int) $drum->lokasi_id !== $suratJalan->id) {
                throw ValidationException::withMessages(['status' => 'Drum tidak berada di Transit Surat Jalan ini.']);
            }

            return;
        }

        $stock = MaterialStok::query()->where('warehouse_id', $suratJalan->warehouse_asal_id)->where('material_id', $item->material_id)
            ->where('lokasi_tipe', 'transit')->where('lokasi_id', $suratJalan->id)->first();
        if ($stock === null || (float) $stock->qty + 0.0005 < (float) $qty) {
            throw ValidationException::withMessages(['status' => 'Saldo Transit tidak sesuai dengan qty yang diterima.']);
        }
    }

    private function nextNumber(CarbonImmutable $tanggal): string
    {
        $prefix = 'SJ-'.$tanggal->format('ym').'-';
        if (DB::getDriverName() === 'pgsql') {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', [$prefix]);
        }
        DB::table('surat_jalan_sequences')->insertOrIgnore(['prefix' => $prefix, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('surat_jalan_sequences')->where('prefix', $prefix)->increment('last_number');

        return $prefix.str_pad((string) DB::table('surat_jalan_sequences')->where('prefix', $prefix)->value('last_number'), 4, '0', STR_PAD_LEFT);
    }

    private function ensurePositiveQuantity(string $qty): void
    {
        if ((float) $qty <= 0.0) {
            throw ValidationException::withMessages(['items' => 'Jumlah harus lebih besar dari nol.']);
        }
    }

    /** @param array<string, mixed> $metadata */
    private function recordProjectEvent(SuratJalan $suratJalan, User $actor, string $eventKey, array $metadata = []): void
    {
        if ($suratJalan->project_id === null) {
            return;
        }

        $project = Project::query()->find($suratJalan->project_id);
        if ($project !== null) {
            ProjectTimeline::recordSystem($project, $actor, $eventKey, $metadata + ['surat_jalan_id' => $suratJalan->id]);
        }
    }

    private function formatQuantity(float $qty): string
    {
        return number_format($qty, 3, '.', '');
    }
}
