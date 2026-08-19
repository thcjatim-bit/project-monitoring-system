<?php

namespace App\Queries;

use App\Models\Drum;
use App\Models\MaterialRequest;
use App\Models\MaterialSn;
use App\Models\MaterialStok;
use App\Models\PemakaianMaterial;
use App\Models\Project;
use App\Models\ProjectTimeline;
use App\Models\SuratJalan;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class MitraDashboardQuery
{
    public function __construct(
        private ProjectCurveQuery $curveQuery,
        private ProjectMaterialReadinessQuery $materialReadinessQuery,
    ) {}

    /** @return array<string, mixed> */
    public function for(User $actor): array
    {
        $canReadProjects = $actor->hasIzin('read_project');
        $projects = $canReadProjects ? Project::query()->latest()->get() : new EloquentCollection;
        $projectCards = $projects->map(function (Project $project) use ($actor): array {
            $curve = $actor->hasIzin('read_project_progress') ? $this->curveQuery->calculate($project) : null;
            $readiness = $actor->hasIzin('read_project_material') ? $this->materialReadinessQuery->calculate($project) : null;

            return [
                'project' => $project,
                'verified_percent' => $curve['verified_percent'] ?? null,
                'readiness' => $readiness,
            ];
        });

        return [
            'projects' => $projectCards,
            'projectCounts' => [
                'active' => $projects->where('status_project', 'aktif')->count(),
                'completed' => $projects->where('status_project', 'selesai')->count(),
            ],
            'stocks' => $actor->hasIzin('read_master_data') ? $this->stocks() : collect(),
            'requests' => $actor->hasIzin('read_material_request')
                ? MaterialRequest::query()->with(['project', 'items.material.unit'])->latest()->limit(8)->get()
                : new EloquentCollection,
            'usages' => $actor->hasIzin('read_material_usage')
                ? PemakaianMaterial::query()->with(['project', 'material.unit', 'warehouse'])->latest()->limit(8)->get()
                : new EloquentCollection,
            'transits' => $actor->hasIzin('read_transit')
                ? SuratJalan::query()
                    ->where('status', 'terbit')
                    ->with(['origin', 'destination', 'project'])
                    ->latest('issued_at')
                    ->limit(8)
                    ->get()
                : new EloquentCollection,
            'activities' => $actor->hasIzin('read_project_timeline')
                ? ProjectTimeline::query()->where('type', '!=', 'internal_note')->with(['project', 'actor'])->latest()->limit(8)->get()
                : new EloquentCollection,
        ];
    }

    /** @return Collection<int, array{warehouse: Warehouse, material: object, qty: float}> */
    private function stocks(): Collection
    {
        $stocks = collect();

        MaterialStok::query()
            ->where('lokasi_tipe', 'warehouse')
            ->where('qty', '>', 0)
            ->whereHas('material', fn ($query) => $query->where('jenis', 'biasa'))
            ->with(['warehouse', 'material.unit'])
            ->orderBy('warehouse_id')
            ->orderBy('material_id')
            ->get()
            ->each(function (MaterialStok $stock) use ($stocks): void {
                $stocks->push(['warehouse' => $stock->warehouse, 'material' => $stock->material, 'qty' => (float) $stock->qty]);
            });

        $warehouses = Warehouse::query()->where('aktif', true)->get()->keyBy('id');
        MaterialSn::query()
            ->where('lokasi_tipe', 'warehouse')
            ->where('status', 'tersedia')
            ->with('material.unit')
            ->get()
            ->groupBy(fn (MaterialSn $sn): string => $sn->lokasi_id.'-'.$sn->material_id)
            ->each(function (Collection $rows) use ($stocks, $warehouses): void {
                $first = $rows->first();
                $warehouse = $warehouses->get($first->lokasi_id);
                if ($warehouse !== null) {
                    $stocks->push(['warehouse' => $warehouse, 'material' => $first->material, 'qty' => (float) $rows->count()]);
                }
            });

        Drum::query()
            ->where('lokasi_tipe', 'warehouse')
            ->where('sisa', '>', 0)
            ->with('material.unit')
            ->get()
            ->groupBy(fn (Drum $drum): string => $drum->lokasi_id.'-'.$drum->material_id)
            ->each(function (Collection $rows) use ($stocks, $warehouses): void {
                $first = $rows->first();
                $warehouse = $warehouses->get($first->lokasi_id);
                if ($warehouse !== null) {
                    $stocks->push(['warehouse' => $warehouse, 'material' => $first->material, 'qty' => (float) $rows->sum('sisa')]);
                }
            });

        return $stocks;
    }
}
