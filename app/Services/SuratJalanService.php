<?php

namespace App\Services;

use App\Models\Drum;
use App\Models\Material;
use App\Models\MaterialSn;
use App\Models\MaterialStok;
use App\Models\MaterialTransaksi;
use App\Models\SuratJalan;
use App\Models\SuratJalanItem;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SuratJalanService
{
    /** @param array{warehouse_asal_id:int, warehouse_tujuan_id:int, tanggal:string, pengirim:string, sopir?:string|null, plat_nomor?:string|null, items:array<int,array{material_id:int,qty:string|int|float,serial_number?:string|null,drum_id?:string|null}>} $data */
    public function issueDirect(User $actor, array $data): SuratJalan
    {
        return DB::transaction(function () use ($actor, $data): SuratJalan {
            $origin = Warehouse::query()->lockForUpdate()->findOrFail($data['warehouse_asal_id']);
            $destination = Warehouse::query()->lockForUpdate()->findOrFail($data['warehouse_tujuan_id']);

            if ($origin->id === $destination->id) {
                throw ValidationException::withMessages(['warehouse_tujuan_id' => 'Warehouse tujuan harus berbeda dari asal.']);
            }

            $tanggal = CarbonImmutable::parse($data['tanggal']);
            $mitraId = $origin->mitra_id ?? $destination->mitra_id;
            $suratJalan = SuratJalan::query()->create([
                'nomor' => $this->nextNumber($tanggal),
                'tanggal' => $tanggal->toDateString(),
                'warehouse_asal_id' => $origin->id,
                'warehouse_tujuan_id' => $destination->id,
                'mitra_id' => $mitraId,
                'issued_by' => $actor->id,
                'issued_at' => now(),
                'status' => 'terbit',
                'pengirim' => $data['pengirim'],
                'sopir' => $data['sopir'] ?? null,
                'plat_nomor' => $data['plat_nomor'] ?? null,
            ]);

            foreach ($data['items'] as $itemData) {
                $this->ensurePositiveQuantity((string) $itemData['qty']);
                $material = Material::query()->findOrFail($itemData['material_id']);
                $item = $this->createItem($suratJalan, $material, $origin, $itemData, $mitraId);
                $this->moveToTransit($actor, $suratJalan, $item, $origin, $mitraId);
            }

            return $suratJalan->load(['origin', 'destination', 'items.material', 'items.serialNumber', 'items.drum']);
        });
    }

    public function receive(User $actor, SuratJalan $suratJalan): SuratJalan
    {
        return DB::transaction(function () use ($actor, $suratJalan): SuratJalan {
            $suratJalan = SuratJalan::query()->with('items.material')->lockForUpdate()->findOrFail($suratJalan->id);
            if ($suratJalan->status !== 'terbit') {
                throw ValidationException::withMessages(['status' => 'Surat Jalan sudah tidak berstatus terbit.']);
            }

            $origin = Warehouse::query()->findOrFail($suratJalan->warehouse_asal_id);
            $destination = Warehouse::query()->findOrFail($suratJalan->warehouse_tujuan_id);

            foreach ($suratJalan->items as $item) {
                $this->ensureTransitAvailability($item, $suratJalan);
                $this->moveFromTransit($actor, $suratJalan, $item, $origin, $destination);
            }

            $suratJalan->update([
                'status' => 'diterima',
                'received_by' => $actor->id,
                'received_at' => now(),
            ]);

            return $suratJalan->fresh(['origin', 'destination', 'items.material', 'items.serialNumber', 'items.drum', 'receiver']);
        });
    }

    private function createItem(SuratJalan $suratJalan, Material $material, Warehouse $origin, array $data, ?int $mitraId): SuratJalanItem
    {
        $qty = (string) $data['qty'];
        $item = [
            'surat_jalan_id' => $suratJalan->id,
            'mitra_id' => $mitraId,
            'material_id' => $material->id,
            'qty' => $qty,
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
            $serial = MaterialSn::query()
                ->where('material_id', $material->id)
                ->where('serial_number', $data['serial_number'])
                ->lockForUpdate()
                ->first();
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
            if ((float) $drum->sisa !== (float) $qty) {
                throw ValidationException::withMessages(['items' => 'Transfer harus membawa seluruh sisa Drum. Potong Drum terlebih dahulu.']);
            }
            $item['drum_id'] = $drum->id;
        } else {
            throw ValidationException::withMessages(['items' => 'Jenis material tidak didukung.']);
        }

        return SuratJalanItem::query()->create($item);
    }

    private function moveToTransit(User $actor, SuratJalan $suratJalan, SuratJalanItem $item, Warehouse $origin, ?int $mitraId): void
    {
        $this->record($actor, $suratJalan, $item, $origin, 'warehouse', $origin->id, '-'.$item->qty, $mitraId);
        $this->record($actor, $suratJalan, $item, $origin, 'transit', $suratJalan->id, $item->qty, $mitraId);
        $this->moveIdentity($item, 'transit', $suratJalan->id, $mitraId);
    }

    private function moveFromTransit(User $actor, SuratJalan $suratJalan, SuratJalanItem $item, Warehouse $origin, Warehouse $destination): void
    {
        $this->record($actor, $suratJalan, $item, $origin, 'transit', $suratJalan->id, '-'.$item->qty, $suratJalan->mitra_id);
        $this->record($actor, $suratJalan, $item, $destination, 'warehouse', $destination->id, $item->qty, $suratJalan->mitra_id);
        $this->moveIdentity($item, 'warehouse', $destination->id, $destination->mitra_id);
    }

    private function record(User $actor, SuratJalan $suratJalan, SuratJalanItem $item, Warehouse $warehouse, string $locationType, int $locationId, string $qtyDelta, ?int $mitraId): void
    {
        MaterialTransaksi::query()->create([
            'warehouse_id' => $warehouse->id,
            'material_id' => $item->material_id,
            'jenis_transaksi' => $locationType === 'transit' ? 'transfer' : ($qtyDelta[0] === '-' ? 'transfer' : 'receipt'),
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

    private function ensureOrdinaryAvailability(Warehouse $origin, Material $material, string $qty): void
    {
        $stock = MaterialStok::query()
            ->where('warehouse_id', $origin->id)
            ->where('material_id', $material->id)
            ->where('lokasi_tipe', 'warehouse')
            ->where('lokasi_id', $origin->id)
            ->lockForUpdate()
            ->first();
        if ($stock === null || (float) $stock->qty + 0.0005 < (float) $qty) {
            throw ValidationException::withMessages(['items' => 'Saldo material tidak mencukupi di Warehouse asal.']);
        }
    }

    private function ensureTransitAvailability(SuratJalanItem $item, SuratJalan $suratJalan): void
    {
        if ($item->material_sn_id !== null) {
            $serial = MaterialSn::query()->lockForUpdate()->find($item->material_sn_id);
            if ($serial === null || $serial->lokasi_tipe !== 'transit' || (int) $serial->lokasi_id !== $suratJalan->id) {
                throw ValidationException::withMessages(['status' => 'Serial Number tidak berada di Transit Surat Jalan ini.']);
            }
            return;
        }

        if ($item->drum_id !== null) {
            $drum = Drum::query()->lockForUpdate()->find($item->drum_id);
            if ($drum === null || $drum->lokasi_tipe !== 'transit' || (int) $drum->lokasi_id !== $suratJalan->id) {
                throw ValidationException::withMessages(['status' => 'Drum tidak berada di Transit Surat Jalan ini.']);
            }
            return;
        }

        $stock = MaterialStok::query()
            ->where('warehouse_id', $suratJalan->warehouse_asal_id)
            ->where('material_id', $item->material_id)
            ->where('lokasi_tipe', 'transit')
            ->where('lokasi_id', $suratJalan->id)
            ->lockForUpdate()
            ->first();
        if ($stock === null || (float) $stock->qty !== (float) $item->qty) {
            throw ValidationException::withMessages(['status' => 'Saldo Transit tidak sesuai dengan isi Surat Jalan.']);
        }
    }

    private function nextNumber(CarbonImmutable $tanggal): string
    {
        $prefix = 'SJ-'.$tanggal->format('ym').'-';
        if (DB::getDriverName() === 'pgsql') {
            DB::select("select pg_advisory_xact_lock(hashtext(?))", [$prefix]);
        }

        $last = SuratJalan::query()->where('nomor', 'like', $prefix.'%')->lockForUpdate()->pluck('nomor');
        $sequence = $last->map(fn (string $number): int => (int) substr($number, -4))->max() + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function ensurePositiveQuantity(string $qty): void
    {
        if ((float) $qty <= 0.0) {
            throw ValidationException::withMessages(['items' => 'Jumlah harus lebih besar dari nol.']);
        }
    }
}
