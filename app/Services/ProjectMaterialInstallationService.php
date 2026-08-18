<?php

namespace App\Services;

use App\Models\Drum;
use App\Models\Material;
use App\Models\MaterialSn;
use App\Models\MaterialStok;
use App\Models\MaterialTransaksi;
use App\Models\Project;
use App\Models\ProjectTimeline;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectMaterialInstallationService
{
    private const EPSILON = 0.0005;

    /** @param array{warehouse_id:int,material_id:int,qty:string|int|float,material_sn_id?:int|null,drum_id?:int|null,catatan?:string|null} $data */
    public function record(User $actor, Project $project, array $data): array
    {
        if ($actor->mitra_id === null || (int) $actor->mitra_id !== (int) $project->mitra_id) {
            throw ValidationException::withMessages(['project_id' => 'Hanya Mitra pemilik Project yang dapat mencatat material terpasang.']);
        }

        return DB::transaction(function () use ($actor, $project, $data): array {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            $warehouse = Warehouse::query()->findOrFail($data['warehouse_id']);
            $material = Material::query()->findOrFail($data['material_id']);
            $qty = $this->formatPositive($data['qty']);

            if ($warehouse->mitra_id === null || (int) $warehouse->mitra_id !== (int) $project->mitra_id) {
                throw ValidationException::withMessages(['warehouse_id' => 'Sumber material bukan Warehouse Mitra pemilik Project.']);
            }

            $stock = MaterialStok::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('material_id', $material->id)
                ->where('lokasi_tipe', 'project')
                ->where('lokasi_id', $project->id)
                ->first();
            if ($stock === null || (float) $stock->qty + self::EPSILON < (float) $qty) {
                throw ValidationException::withMessages(['qty' => 'Saldo material Project tidak mencukupi.']);
            }

            $identity = $this->ensureIdentityAtProject($material, $project, $qty, $data);
            $attributes = [
                'warehouse_id' => $warehouse->id,
                'material_id' => $material->id,
                'material_sn_id' => $data['material_sn_id'] ?? null,
                'drum_id' => $data['drum_id'] ?? null,
                'project_id' => $project->id,
                'mitra_id' => $project->mitra_id,
                'jenis_transaksi' => 'terpasang',
                'reason' => 'Material terpasang Project '.$project->id,
                'catatan' => $data['catatan'] ?? null,
                'actor_id' => $actor->id,
            ];

            $fromProject = MaterialTransaksi::query()->create($attributes + [
                'lokasi_tipe' => 'project',
                'lokasi_id' => $project->id,
                'qty_delta' => '-'.$qty,
            ]);
            $toInstalled = MaterialTransaksi::query()->create($attributes + [
                'lokasi_tipe' => 'terpasang',
                'lokasi_id' => $project->id,
                'qty_delta' => $qty,
            ]);

            $this->moveIdentityToInstalled($identity, $project);
            ProjectTimeline::recordSystem($project, $actor, 'material_installed', [
                'material_transaction_id' => $toInstalled->id,
                'qty' => $qty,
            ]);

            return [
                'from_project' => $fromProject,
                'to_installed' => $toInstalled,
            ];
        });
    }

    /** @param array{material_sn_id?:int|null,drum_id?:int|null} $data */
    private function ensureIdentityAtProject(Material $material, Project $project, string $qty, array $data): ?object
    {
        $serialId = $data['material_sn_id'] ?? null;
        $drumId = $data['drum_id'] ?? null;

        if ($material->jenis === 'biasa') {
            if ($serialId !== null || $drumId !== null) {
                throw ValidationException::withMessages(['material_id' => 'Material biasa tidak menggunakan identitas SN atau Drum.']);
            }

            return null;
        }

        if ($material->jenis === 'ber_sn') {
            if ($serialId === null || $drumId !== null || (float) $qty !== 1.0) {
                throw ValidationException::withMessages(['material_sn_id' => 'Material ber-SN wajib dipasang satu unit dengan satu Serial Number.']);
            }
            $serial = MaterialSn::query()->lockForUpdate()->find($serialId);
            if ($serial === null || (int) $serial->material_id !== (int) $material->id
                || $serial->lokasi_tipe !== 'project' || (int) $serial->lokasi_id !== (int) $project->id
                || (int) $serial->project_id !== (int) $project->id || $serial->status !== 'keluar') {
                throw ValidationException::withMessages(['material_sn_id' => 'Serial Number tidak tersedia pada Project ini.']);
            }

            return $serial;
        }

        if ($material->jenis === 'drum_kabel') {
            if ($drumId === null || $serialId !== null) {
                throw ValidationException::withMessages(['drum_id' => 'Material drum kabel wajib memiliki Drum ID.']);
            }
            $drum = Drum::query()->lockForUpdate()->find($drumId);
            if ($drum === null || (int) $drum->material_id !== (int) $material->id
                || $drum->lokasi_tipe !== 'project' || (int) $drum->lokasi_id !== (int) $project->id
                || (int) $drum->project_id !== (int) $project->id
                || (float) $drum->sisa + self::EPSILON < (float) $qty) {
                throw ValidationException::withMessages(['drum_id' => 'Sisa Drum tidak tersedia pada Project ini.']);
            }

            return $drum;
        }

        throw ValidationException::withMessages(['material_id' => 'Jenis material tidak didukung.']);
    }

    private function moveIdentityToInstalled(?object $identity, Project $project): void
    {
        if ($identity instanceof MaterialSn) {
            $identity->update([
                'lokasi_tipe' => 'terpasang',
                'lokasi_id' => $project->id,
                'project_id' => $project->id,
            ]);
        }

        if (! $identity instanceof Drum) {
            return;
        }

        $identity->refresh();
        if ((float) $identity->sisa <= self::EPSILON) {
            $identity->update([
                'lokasi_tipe' => 'terpasang',
                'lokasi_id' => $project->id,
                'project_id' => $project->id,
            ]);
        }
    }

    private function formatPositive(string|int|float $qty): string
    {
        if (! is_numeric($qty) || (float) $qty <= 0) {
            throw ValidationException::withMessages(['qty' => 'Jumlah material terpasang harus lebih besar dari nol.']);
        }

        return number_format((float) $qty, 3, '.', '');
    }
}
