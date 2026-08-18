<?php

namespace App\Queries;

use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\Project;
use App\Models\ProjectRabMaterial;
use App\Models\SuratJalan;
use App\Models\SuratJalanItem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ProjectMaterialReadinessQuery
{
    /** @return array{required: float, delivered: float, transit: float, available: float, readiness_percent: float, state: string, items: array<int, array<string, mixed>>} */
    public function calculate(Project $project, CarbonInterface|string|null $asOf = null): array
    {
        $asOfDate = $asOf instanceof CarbonInterface
            ? CarbonImmutable::instance($asOf)
            : ($asOf === null ? null : CarbonImmutable::parse($asOf));
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
            ->when($asOfDate !== null, fn ($query) => $query->whereDate('tanggal', '<=', $asOfDate->toDateString()))
            ->pluck('id');

        $quantities = SuratJalanItem::query()
            ->join('surat_jalans', 'surat_jalans.id', '=', 'surat_jalan_items.surat_jalan_id')
            ->whereIn('surat_jalan_items.surat_jalan_id', $suratJalanIds)
            ->where('surat_jalans.status', '!=', 'dibatalkan')
            ->whereNull('surat_jalans.retur_dari_id')
            ->select('surat_jalan_items.material_id')
            ->when($asOfDate === null, function ($query): void {
                $query
                    ->selectRaw('SUM(GREATEST(surat_jalan_items.qty_diterima - surat_jalan_items.qty_diretur, 0)) AS delivered_qty')
                    ->selectRaw('SUM(GREATEST(surat_jalan_items.qty - surat_jalan_items.qty_diterima, 0)) AS transit_qty');
            }, function ($query) use ($asOfDate): void {
                $cutoff = $asOfDate->endOfDay()->toDateTimeString();
                $query
                    ->selectRaw(
                        'SUM(CASE WHEN surat_jalans.received_at IS NULL OR surat_jalans.received_at <= ? THEN GREATEST(surat_jalan_items.qty_diterima - surat_jalan_items.qty_diretur, 0) ELSE 0 END) AS delivered_qty',
                        [$cutoff],
                    )
                    ->selectRaw(
                        'SUM(CASE WHEN surat_jalans.received_at IS NOT NULL AND surat_jalans.received_at > ? THEN surat_jalan_items.qty ELSE GREATEST(surat_jalan_items.qty - surat_jalan_items.qty_diterima, 0) END) AS transit_qty',
                        [$cutoff],
                    );
            })
            ->when($asOfDate !== null, fn ($query) => $query->whereDate('surat_jalans.tanggal', '<=', $asOfDate->toDateString()))
            ->groupBy('surat_jalan_items.material_id')
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
