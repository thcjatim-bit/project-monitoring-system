<?php

namespace App\Queries;

use App\Models\Project;
use App\Models\ProjectRekon;
use App\Models\ProjectRekonItem;
use Illuminate\Support\Collection;

class ProjectRekonQuery
{
    /** @return array{project_id:int,status_project:string,active_rekon:array<string,mixed>|null,rekons:array<int,array<string,mixed>>,read_at:string} */
    public function forProject(Project $project): array
    {
        $rekons = ProjectRekon::query()
            ->where('project_id', $project->id)
            ->with(['items.material.unit'])
            ->orderBy('id')
            ->get();
        $active = $this->activeApproved($rekons);

        return [
            'project_id' => (int) $project->id,
            'status_project' => (string) $project->status_project,
            'active_rekon' => $active === null ? null : $this->serializeRekon($active),
            'rekons' => $rekons->map(fn (ProjectRekon $rekon): array => $this->serializeRekon($rekon))->all(),
            'read_at' => now()->toISOString(),
        ];
    }

    /** @return array<string,mixed>|null */
    public function activeForProject(Project $project): ?array
    {
        $active = $this->activeApproved(ProjectRekon::query()
            ->where('project_id', $project->id)
            ->where('status', 'disetujui')
            ->with(['items.material.unit'])
            ->orderBy('id')
            ->get());

        return $active === null ? null : $this->serializeRekon($active);
    }

    private function activeApproved(Collection $rekons): ?ProjectRekon
    {
        $approved = $rekons->where('status', 'disetujui');
        if ($approved->isEmpty()) {
            return null;
        }

        $correctedIds = $approved->pluck('koreksi_dari_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $approved
            ->reject(fn (ProjectRekon $rekon): bool => in_array((int) $rekon->id, $correctedIds, true))
            ->sortByDesc('id')
            ->first();
    }

    /** @return array{id:int,nomor:string,source:string,status:string,koreksi_dari_id:int|null,approved_at:string|null,items:array<int,array<string,mixed>>} */
    private function serializeRekon(ProjectRekon $rekon): array
    {
        return [
            'id' => (int) $rekon->id,
            'nomor' => (string) $rekon->nomor,
            'source' => (string) $rekon->source,
            'status' => (string) $rekon->status,
            'koreksi_dari_id' => $rekon->koreksi_dari_id === null ? null : (int) $rekon->koreksi_dari_id,
            'approved_at' => $rekon->approved_at?->toISOString(),
            'items' => $rekon->items->map(fn (ProjectRekonItem $item): array => [
                'id' => (int) $item->id,
                'warehouse_id' => (int) $item->warehouse_id,
                'material_id' => (int) $item->material_id,
                'material' => $item->material === null ? null : [
                    'id' => (int) $item->material->id,
                    'kode' => (string) $item->material->kode,
                    'nama' => (string) $item->material->nama,
                    'unit' => $item->material->unit?->nama,
                ],
                'material_sn_id' => $item->material_sn_id === null ? null : (int) $item->material_sn_id,
                'drum_id' => $item->drum_id === null ? null : (int) $item->drum_id,
                'keluar_gudang' => (float) $item->keluar_gudang,
                'terpasang' => (float) $item->terpasang,
                'sisa_project' => (float) $item->sisa_project,
                'dikembalikan' => (float) $item->dikembalikan,
                'hilang_rusak' => (float) $item->hilang_rusak,
                'kategori_hilang_rusak' => $item->kategori_hilang_rusak,
                'penanggung_jawab' => (string) $item->penanggung_jawab,
            ])->all(),
        ];
    }
}
