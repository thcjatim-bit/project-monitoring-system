<?php

namespace App\Services;

use App\Models\Drum;
use App\Models\Material;
use App\Models\MaterialSn;
use App\Models\MaterialTransaksi;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaterialInventoryService
{
    public function receive(
        User $actor,
        Warehouse $warehouse,
        int $materialId,
        string $qty,
        string $reason,
        ?string $serialNumber = null,
        ?string $drumId = null,
    ): MaterialTransaksi {
        $this->ensurePositiveQuantity($qty);

        return DB::transaction(function () use ($actor, $warehouse, $materialId, $qty, $reason, $serialNumber, $drumId): MaterialTransaksi {
            $material = Material::query()->findOrFail($materialId);

            return match ($material->jenis) {
                'biasa' => $this->record($actor, $warehouse, $materialId, $qty, $reason),
                'ber_sn' => $this->receiveSerialNumber($actor, $warehouse, $material, $qty, $reason, $serialNumber),
                'drum_kabel' => $this->receiveDrum($actor, $warehouse, $material, $qty, $reason, $drumId),
                default => throw ValidationException::withMessages(['material_id' => 'Jenis material tidak didukung.']),
            };
        });
    }

    public function issue(
        User $actor,
        Warehouse $warehouse,
        int $materialId,
        string $qty,
        string $reason,
        ?string $serialNumber = null,
        ?string $drumId = null,
    ): MaterialTransaksi {
        $this->ensurePositiveQuantity($qty);

        return DB::transaction(function () use ($actor, $warehouse, $materialId, $qty, $reason, $serialNumber, $drumId): MaterialTransaksi {
            $material = Material::query()->findOrFail($materialId);

            return match ($material->jenis) {
                'biasa' => $this->issueOrdinary($actor, $warehouse, $materialId, $qty, $reason),
                'ber_sn' => $this->issueSerialNumber($actor, $warehouse, $material, $reason, $serialNumber),
                'drum_kabel' => $this->issueDrum($actor, $warehouse, $material, $qty, $reason, $drumId),
                default => throw ValidationException::withMessages(['material_id' => 'Jenis material tidak didukung.']),
            };
        });
    }

    public function splitDrum(
        User $actor,
        Warehouse $warehouse,
        string $parentDrumId,
        string $qty,
        string $reason,
    ): Drum {
        $this->ensurePositiveQuantity($qty);

        return DB::transaction(function () use ($actor, $warehouse, $parentDrumId, $qty, $reason): Drum {
            $parent = Drum::query()->where('drum_id', $parentDrumId)->lockForUpdate()->first();
            if ($parent === null || $parent->lokasi_tipe !== 'warehouse' || (int) $parent->lokasi_id !== (int) $warehouse->id) {
                throw ValidationException::withMessages(['drum_id' => 'Drum tidak tersedia di Warehouse ini.']);
            }
            if ((float) $parent->sisa + 0.0005 < (float) $qty) {
                throw ValidationException::withMessages(['qty' => 'Sisa meter drum tidak mencukupi.']);
            }

            $suffix = $parent->children()->lockForUpdate()->get()->count() + 1;
            $child = Drum::query()->create([
                'material_id' => $parent->material_id,
                'drum_id' => $parent->drum_id.'-'.$suffix,
                'panjang_awal' => $qty,
                'sisa' => $qty,
                'induk_drum_id' => $parent->id,
                'lokasi_tipe' => 'warehouse',
                'lokasi_id' => $warehouse->id,
            ]);

            $parent->update(['sisa' => (string) ((float) $parent->sisa - (float) $qty)]);
            $this->record($actor, $warehouse, $parent->material_id, '-'.$qty, $reason, null, $parent->id, 'drum_split');
            $this->record($actor, $warehouse, $parent->material_id, $qty, $reason, null, $child->id, 'drum_split');

            return $child->fresh();
        });
    }

    private function receiveSerialNumber(
        User $actor,
        Warehouse $warehouse,
        Material $material,
        string $qty,
        string $reason,
        ?string $serialNumber,
    ): MaterialTransaksi {
        if ($serialNumber === null || $serialNumber === '' || (float) $qty !== 1.0) {
            throw ValidationException::withMessages(['serial_number' => 'Material ber-SN wajib memiliki satu Serial Number dan qty 1.']);
        }
        if (MaterialSn::query()->where('serial_number', $serialNumber)->exists()) {
            throw ValidationException::withMessages(['serial_number' => 'Serial Number sudah terdaftar.']);
        }

        $serial = MaterialSn::query()->create([
            'material_id' => $material->id,
            'serial_number' => $serialNumber,
            'lokasi_tipe' => 'warehouse',
            'lokasi_id' => $warehouse->id,
            'status' => 'tersedia',
        ]);

        return $this->record($actor, $warehouse, $material->id, $qty, $reason, $serial->id);
    }

    private function receiveDrum(
        User $actor,
        Warehouse $warehouse,
        Material $material,
        string $qty,
        string $reason,
        ?string $drumId,
    ): MaterialTransaksi {
        if ($drumId === null || $drumId === '') {
            throw ValidationException::withMessages(['drum_id' => 'Material drum kabel wajib memiliki Drum ID.']);
        }
        if (Drum::query()->where('drum_id', $drumId)->exists()) {
            throw ValidationException::withMessages(['drum_id' => 'Drum ID sudah terdaftar.']);
        }

        $drum = Drum::query()->create([
            'material_id' => $material->id,
            'drum_id' => $drumId,
            'panjang_awal' => $qty,
            'sisa' => $qty,
            'lokasi_tipe' => 'warehouse',
            'lokasi_id' => $warehouse->id,
        ]);

        return $this->record($actor, $warehouse, $material->id, $qty, $reason, null, $drum->id);
    }

    private function issueSerialNumber(
        User $actor,
        Warehouse $warehouse,
        Material $material,
        string $reason,
        ?string $serialNumber,
    ): MaterialTransaksi {
        if ($serialNumber === null || $serialNumber === '') {
            throw ValidationException::withMessages(['serial_number' => 'Serial Number wajib diisi.']);
        }

        $serial = MaterialSn::query()
            ->where('material_id', $material->id)
            ->where('serial_number', $serialNumber)
            ->lockForUpdate()
            ->first();
        if ($serial === null || $serial->status !== 'tersedia' || $serial->lokasi_tipe !== 'warehouse' || (int) $serial->lokasi_id !== (int) $warehouse->id) {
            throw ValidationException::withMessages(['serial_number' => 'Serial Number tidak tersedia di Warehouse ini.']);
        }

        $transaction = $this->record($actor, $warehouse, $material->id, '-1', $reason, $serial->id);
        $serial->update(['status' => 'keluar']);

        return $transaction;
    }

    private function issueDrum(
        User $actor,
        Warehouse $warehouse,
        Material $material,
        string $qty,
        string $reason,
        ?string $drumId,
    ): MaterialTransaksi {
        if ($drumId === null || $drumId === '') {
            throw ValidationException::withMessages(['drum_id' => 'Drum ID wajib diisi.']);
        }

        $drum = Drum::query()
            ->where('material_id', $material->id)
            ->where('drum_id', $drumId)
            ->lockForUpdate()
            ->first();
        if ($drum === null || $drum->lokasi_tipe !== 'warehouse' || (int) $drum->lokasi_id !== (int) $warehouse->id) {
            throw ValidationException::withMessages(['drum_id' => 'Drum tidak tersedia di Warehouse ini.']);
        }
        if ((float) $drum->sisa + 0.0005 < (float) $qty) {
            throw ValidationException::withMessages(['qty' => 'Sisa meter drum tidak mencukupi.']);
        }

        $drum->update(['sisa' => (string) ((float) $drum->sisa - (float) $qty)]);

        return $this->record($actor, $warehouse, $material->id, '-'.$qty, $reason, null, $drum->id);
    }

    private function issueOrdinary(User $actor, Warehouse $warehouse, int $materialId, string $qty, string $reason): MaterialTransaksi
    {
        $stock = $warehouse->stocks()->where('material_id', $materialId)->value('qty');
        if ($stock === null || (float) $stock + 0.0005 < (float) $qty) {
            throw ValidationException::withMessages(['qty' => 'Saldo material tidak mencukupi.']);
        }

        return $this->record($actor, $warehouse, $materialId, '-'.$qty, $reason);
    }

    private function record(
        User $actor,
        Warehouse $warehouse,
        int $materialId,
        string $qty,
        string $reason,
        ?int $materialSnId = null,
        ?int $drumId = null,
        string $jenisTransaksi = 'stok',
    ): MaterialTransaksi {
        return MaterialTransaksi::query()->create([
            'warehouse_id' => $warehouse->id,
            'material_id' => $materialId,
            'jenis_transaksi' => $jenisTransaksi,
            'lokasi_tipe' => 'warehouse',
            'lokasi_id' => $warehouse->id,
            'qty_delta' => $qty,
            'material_sn_id' => $materialSnId,
            'drum_id' => $drumId,
            'mitra_id' => $warehouse->mitra_id,
            'reason' => $reason,
            'actor_id' => $actor->id,
        ]);
    }

    private function ensurePositiveQuantity(string $qty): void
    {
        if ((float) $qty <= 0.0) {
            throw ValidationException::withMessages(['qty' => 'Jumlah harus lebih besar dari nol.']);
        }
    }
}
