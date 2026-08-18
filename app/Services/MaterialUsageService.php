<?php

namespace App\Services;

use App\Models\Drum;
use App\Models\Material;
use App\Models\MaterialSn;
use App\Models\MaterialStok;
use App\Models\MaterialTransaksi;
use App\Models\PemakaianMaterial;
use App\Models\Project;
use App\Models\ProjectTimeline;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaterialUsageService
{
    /** @param array{warehouse_id:int,material_id:int,qty:string|int|float,material_sn_id?:int|null,drum_id?:int|null,catatan?:string|null} $data */
    public function submit(User $actor, Project $project, array $data): PemakaianMaterial
    {
        if ($actor->mitra_id === null || (int) $actor->mitra_id !== (int) $project->mitra_id) {
            throw ValidationException::withMessages(['project_id' => 'Hanya Mitra pemilik Project yang dapat mengajukan Pemakaian Material.']);
        }

        return DB::transaction(function () use ($actor, $project, $data): PemakaianMaterial {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            $warehouse = Warehouse::query()->lockForUpdate()->findOrFail($data['warehouse_id']);
            $material = Material::query()->findOrFail($data['material_id']);
            $qty = $this->formatQuantity($data['qty']);

            $this->ensureProjectWarehouse($project, $warehouse);
            $this->ensurePositive($qty);
            $this->ensureIdentityAvailable($material, $warehouse, $qty, $data);

            $usage = PemakaianMaterial::query()->create([
                'mitra_id' => $project->mitra_id,
                'project_id' => $project->id,
                'warehouse_id' => $warehouse->id,
                'material_id' => $material->id,
                'material_sn_id' => $data['material_sn_id'] ?? null,
                'drum_id' => $data['drum_id'] ?? null,
                'qty' => $qty,
                'requested_by' => $actor->id,
                'status' => 'diajukan',
                'catatan' => $data['catatan'] ?? null,
            ]);

            ProjectTimeline::recordSystem($project, $actor, 'material_usage_submitted', [
                'pemakaian_material_id' => $usage->id,
                'status' => $usage->status,
            ]);

            return $usage->load(['project', 'warehouse', 'material.unit', 'serialNumber', 'drum']);
        });
    }

    public function cancel(PemakaianMaterial $usage, User $actor): PemakaianMaterial
    {
        if ($actor->mitra_id === null || (int) $actor->mitra_id !== (int) $usage->mitra_id) {
            throw ValidationException::withMessages(['status' => 'Hanya Mitra pemilik Pemakaian Material yang dapat membatalkan pengajuan.']);
        }

        return DB::transaction(function () use ($usage, $actor): PemakaianMaterial {
            $usage = PemakaianMaterial::query()->with('project')->lockForUpdate()->findOrFail($usage->id);
            if ($usage->status !== 'diajukan') {
                throw ValidationException::withMessages(['status' => 'Pemakaian Material hanya dapat dibatalkan saat masih diajukan.']);
            }

            $usage->update(['status' => 'dibatalkan']);
            ProjectTimeline::recordSystem($usage->project, $actor, 'material_usage_cancelled', [
                'pemakaian_material_id' => $usage->id,
                'status' => $usage->status,
            ]);

            return $usage->fresh();
        });
    }

    public function decide(PemakaianMaterial $usage, User $actor, string $decision, ?string $note = null): PemakaianMaterial
    {
        if ($actor->mitra_id !== null) {
            throw ValidationException::withMessages(['status' => 'Hanya user THC yang dapat memutuskan Pemakaian Material.']);
        }
        if (! in_array($decision, ['disetujui', 'ditolak'], true)) {
            throw ValidationException::withMessages(['status' => 'Keputusan Pemakaian Material tidak valid.']);
        }

        return DB::transaction(function () use ($usage, $actor, $decision, $note): PemakaianMaterial {
            $usage = PemakaianMaterial::query()
                ->with(['project', 'warehouse', 'material'])
                ->lockForUpdate()
                ->findOrFail($usage->id);
            if ($usage->status !== 'diajukan') {
                throw ValidationException::withMessages(['status' => 'Pemakaian Material sudah diputuskan.']);
            }

            if ($decision === 'disetujui') {
                $this->writeApprovedMovement($usage, $actor);
            }

            $usage->update([
                'status' => $decision,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);
            ProjectTimeline::recordSystem($usage->project, $actor, $decision === 'disetujui' ? 'material_usage_approved' : 'material_usage_rejected', [
                'pemakaian_material_id' => $usage->id,
                'status' => $usage->status,
            ]);

            return $usage->fresh(['project', 'warehouse', 'material.unit', 'serialNumber', 'drum', 'decider']);
        });
    }

    private function writeApprovedMovement(PemakaianMaterial $usage, User $actor): void
    {
        $project = Project::query()->lockForUpdate()->findOrFail($usage->project_id);
        $warehouse = Warehouse::query()->lockForUpdate()->findOrFail($usage->warehouse_id);
        $material = Material::query()->findOrFail($usage->material_id);
        $qty = $this->formatQuantity($usage->qty);
        $identity = $this->ensureIdentityAvailable($material, $warehouse, $qty, [
            'material_sn_id' => $usage->material_sn_id,
            'drum_id' => $usage->drum_id,
        ]);

        if ($identity === null) {
            $stock = MaterialStok::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('material_id', $material->id)
                ->where('lokasi_tipe', 'warehouse')
                ->where('lokasi_id', $warehouse->id)
                ->lockForUpdate()
                ->first();
            if ($stock === null || (float) $stock->qty + 0.0005 < (float) $qty) {
                throw ValidationException::withMessages(['qty' => 'Saldo material tidak mencukupi saat approval.']);
            }
        }

        $attributes = [
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'material_sn_id' => $usage->material_sn_id,
            'drum_id' => $usage->drum_id,
            'project_id' => $project->id,
            'mitra_id' => $project->mitra_id,
            'pemakaian_material_id' => $usage->id,
            'jenis_transaksi' => 'pemakaian',
            'reason' => 'Pemakaian Material '.$usage->id,
            'actor_id' => $actor->id,
        ];

        MaterialTransaksi::query()->create($attributes + [
            'lokasi_tipe' => 'warehouse',
            'lokasi_id' => $warehouse->id,
            'qty_delta' => '-'.$qty,
        ]);
        MaterialTransaksi::query()->create($attributes + [
            'lokasi_tipe' => 'project',
            'lokasi_id' => $project->id,
            'qty_delta' => $qty,
        ]);

        if ($usage->material_sn_id !== null) {
            MaterialSn::query()->whereKey($usage->material_sn_id)->update([
                'lokasi_tipe' => 'project',
                'lokasi_id' => $project->id,
                'project_id' => $project->id,
                'status' => 'keluar',
            ]);
        }
        if ($usage->drum_id !== null) {
            Drum::query()->whereKey($usage->drum_id)->update([
                'lokasi_tipe' => 'project',
                'lokasi_id' => $project->id,
                'project_id' => $project->id,
            ]);
        }
    }

    /** @param array{material_sn_id?:int|null,drum_id?:int|null} $data */
    private function ensureIdentityAvailable(Material $material, Warehouse $warehouse, string $qty, array $data): ?object
    {
        $serialId = $data['material_sn_id'] ?? null;
        $drumId = $data['drum_id'] ?? null;

        if ($material->jenis === 'biasa') {
            if ($serialId !== null || $drumId !== null) {
                throw ValidationException::withMessages(['material_id' => 'Material biasa tidak menggunakan identitas SN atau drum.']);
            }

            return null;
        }

        if ($material->jenis === 'ber_sn') {
            if ($serialId === null || $drumId !== null || (float) $qty !== 1.0) {
                throw ValidationException::withMessages(['material_sn_id' => 'Material ber-SN wajib memiliki satu Serial Number dan qty 1.']);
            }
            $serial = MaterialSn::query()->lockForUpdate()->find($serialId);
            if ($serial === null || (int) $serial->material_id !== (int) $material->id
                || $serial->status !== 'tersedia' || $serial->lokasi_tipe !== 'warehouse'
                || (int) $serial->lokasi_id !== (int) $warehouse->id) {
                throw ValidationException::withMessages(['material_sn_id' => 'Serial Number tidak tersedia di Warehouse asal.']);
            }

            return $serial;
        }

        if ($material->jenis === 'drum_kabel') {
            if ($drumId === null || $serialId !== null) {
                throw ValidationException::withMessages(['drum_id' => 'Material drum kabel wajib memiliki Drum ID.']);
            }
            $drum = Drum::query()->lockForUpdate()->find($drumId);
            if ($drum === null || (int) $drum->material_id !== (int) $material->id
                || $drum->lokasi_tipe !== 'warehouse' || (int) $drum->lokasi_id !== (int) $warehouse->id) {
                throw ValidationException::withMessages(['drum_id' => 'Drum tidak tersedia di Warehouse asal.']);
            }
            if ((float) $drum->sisa !== (float) $qty) {
                throw ValidationException::withMessages(['qty' => 'Pemakaian harus membawa seluruh sisa Drum. Potong Drum terlebih dahulu.']);
            }

            return $drum;
        }

        throw ValidationException::withMessages(['material_id' => 'Jenis material tidak didukung.']);
    }

    private function ensureProjectWarehouse(Project $project, Warehouse $warehouse): void
    {
        if ($warehouse->mitra_id === null || (int) $warehouse->mitra_id !== (int) $project->mitra_id) {
            throw ValidationException::withMessages(['warehouse_id' => 'Pemakaian harus berasal dari Warehouse Mitra pemilik Project.']);
        }
    }

    private function ensurePositive(string $qty): void
    {
        if ((float) $qty <= 0) {
            throw ValidationException::withMessages(['qty' => 'Jumlah harus lebih besar dari nol.']);
        }
    }

    private function formatQuantity(string|int|float $qty): string
    {
        if (! is_numeric($qty)) {
            throw ValidationException::withMessages(['qty' => 'Jumlah material harus berupa angka.']);
        }

        return number_format((float) $qty, 3, '.', '');
    }
}
