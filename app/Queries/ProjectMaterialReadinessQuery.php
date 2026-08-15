<?php

namespace App\Queries;

use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\Project;
use App\Models\ProjectRabMaterial;
use App\Models\SuratJalan;
use App\Models\SuratJalanItem;
use Illuminate\Support\Facades\DB;

class ProjectMaterialReadinessQuery
{
    /** @return array{required: float, delivered: float, transit: float, available: float, readiness_percent: float, state: string, items: array<int, array<string, mixed>>} */
    public function calculate(Project $project): array
    {
        $requirements = ProjectRabMaterial::query()
            ->with(['material.unit'])
            ->where('project_id', $project->id)
            ->orderBy('id')
            ->get();

        if ($requirements->isEmpty()) {
            return [
                'required' => 0.0,
                'delivered' => 0.0,
                'transit' => 0.0,
                'available' => 0.0,
                'readiness_percent' => 0.0,
                'state' => 'empty',
                'items' => [],
            ];
        }

        $suratJalanIds = SuratJalan::query()
            ->where(function ($query) use ($project): void {
                $query->where('project_id', $project->id)
                    ->orWhereHas('materialRequest', fn ($request) => $request->where('project_id', $project->id));
            })
            ->pluck('id');

        $quantities = SuratJalanItem::query()
            ->whereIn('surat_jalan_id', $suratJalanIds)
            ->whereHas('suratJalan', fn ($query) => $query->where('status', '!=', 'dibatalkan'))
            ->select([
                'material_id',
                DB::raw('SUM(GREATEST(qty_diterima - qty_diretur, 0)) AS delivered_qty'),
                DB::raw('SUM(GREATEST(qty - qty_diterima, 0)) AS transit_qty'),
            ])
            ->groupBy('material_id')
            ->get()
            ->keyBy('material_id');

        $requestIds = MaterialRequest::query()
            ->where('project_id', $project->id)
            ->pluck('id');
        $requestIdsByMaterial = MaterialRequestItem::query()
            ->whereIn('material_request_id', $requestIds)
            ->whereIn('material_id', $requirements->pluck('material_id')->unique())
            ->get(['material_id', 'material_request_id'])
            ->groupBy('material_id');
        $linksByMaterial = SuratJalanItem::query()
            ->whereIn('surat_jalan_id', $suratJalanIds)
            ->whereIn('material_id', $requirements->pluck('material_id')->unique())
            ->get(['material_id', 'surat_jalan_id'])
            ->groupBy('material_id');

        $items = $requirements->groupBy('material_id')->map(function ($lines, int|string $materialId) use ($quantities, $linksByMaterial, $requestIdsByMaterial): array {
            $required = (float) $lines->sum('qty');
            $quantity = $quantities->get($materialId);
            $delivered = (float) ($quantity?->delivered_qty ?? 0);
            $transit = (float) ($quantity?->transit_qty ?? 0);
            $material = $lines->first()->material;
            $suratJalanIds = $linksByMaterial->get($materialId, collect())->pluck('surat_jalan_id')->unique()->values()->all();
            $requestIds = $requestIdsByMaterial->get($materialId, collect())->pluck('material_request_id')->unique()->values()->all();

            return [
                'material_id' => (int) $materialId,
                'material' => $material,
                'required' => $required,
                'delivered' => $delivered,
                'transit' => $transit,
                'readiness_percent' => $required > 0 ? min(100, $delivered / $required * 100) : 0.0,
                'request_ids' => $requestIds,
                'surat_jalan_ids' => $suratJalanIds,
            ];
        })->values();

        $required = (float) $items->sum('required');
        $delivered = (float) $items->sum('delivered');
        $transit = (float) $items->sum('transit');

        return [
            'required' => $required,
            'delivered' => $delivered,
            'transit' => $transit,
            'available' => $delivered,
            'readiness_percent' => $required > 0 ? min(100, $delivered / $required * 100) : 0.0,
            'state' => $delivered > 0 ? ($delivered + 0.0005 >= $required ? 'ready' : 'partial') : 'no_delivery',
            'items' => $items->all(),
        ];
    }
}
