<?php

namespace App\Queries;

use App\Models\MaterialRequest;
use App\Models\MaterialStok;
use App\Models\MaterialTransaksi;
use App\Models\Mitra;
use App\Models\MitraHargaJasa;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\ProjectProgress;
use App\Models\ProjectRekon;
use App\Models\ProjectRekonItem;
use App\Models\ProjectTimeline;
use App\Support\ApiFilter;
use App\Support\ApiKeyPrincipal;
use App\Support\SpiThreshold;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ApiReadQuery
{
    public function __construct(
        private ProjectCurveQuery $curveQuery,
        private ProjectMaterialReadinessQuery $materialQuery,
    ) {}

    /** @return Collection<int,array<string,mixed>> */
    public function projectRows(ApiFilter $filter): Collection
    {
        return $this->projectMetrics($filter)
            ->map(fn (array $metric): array => $this->projectPayload($metric['project'], $metric['curve'], $metric['material']))
            ->values();
    }

    /** @return array<string,mixed> */
    public function portfolio(ApiFilter $filter): array
    {
        $metrics = $this->projectMetrics($filter);
        $activeMetrics = $metrics->where(fn (array $metric): bool => $metric['project']->status_project === 'aktif')->values();

        return [
            'kpis' => $this->portfolioKpis($activeMetrics),
            'trend' => $this->trend($activeMetrics, $filter->reportingAsOf),
            'status_distribution' => $this->riskDistribution($metrics),
            'project_status_distribution' => $this->projectStatusDistribution($metrics),
            'health_matrix' => $metrics->map(fn (array $metric): array => $this->projectPayload($metric['project'], $metric['curve'], $metric['material']))->values()->all(),
            'activity' => $this->activity($metrics, $filter->reportingAsOf),
            'decision_queue' => $this->decisionQueue($metrics, $filter->reportingAsOf),
            'project_count' => $metrics->count(),
        ];
    }

    /** @return array<string,mixed> */
    public function projectDetail(Project $project, ApiFilter $filter): array
    {
        $curve = $this->curveQuery->calculate($project, $filter->reportingAsOf);
        $material = in_array('material_readiness', $filter->includes, true)
            ? $this->materialQuery->calculate($project, $filter->reportingAsOf)
            : null;
        $payload = $this->projectPayload($project, $curve, $material);

        if (in_array('steps', $filter->includes, true)) {
            $payload['steps'] = $project->steps->map(fn ($step): array => [
                'step' => (string) $step->step,
                'label' => $step->label(),
                'urutan' => (int) $step->urutan,
                'status' => (string) $step->status,
                'completed_at' => $this->dateTime($step->completed_at),
            ])->values()->all();
        }
        if (in_array('curve', $filter->includes, true)) {
            $payload['curve'] = $this->curvePayload($curve);
        }
        if (in_array('material_readiness', $filter->includes, true)) {
            $payload['material_readiness'] = $this->materialReadinessPayload($material ?? []);
        }
        if (in_array('material_requests', $filter->includes, true)) {
            $payload['material_requests'] = $this->materialRequests($filter, $project)->all();
        }
        if (in_array('material_transactions', $filter->includes, true)) {
            $payload['material_transactions'] = $this->materialTransactions($filter, $project)->all();
        }
        if (in_array('service_prices', $filter->includes, true)) {
            $payload['service_prices'] = $this->servicePrices($filter, $project->mitra_id)->all();
        }
        if (in_array('photo_links', $filter->includes, true)) {
            $payload['photo_links'] = $this->photoLinks($project)->all();
        }

        return $payload;
    }

    /** @return array<string,mixed> */
    public function curve(Project $project, ApiFilter $filter): array
    {
        return $this->curvePayload($this->curveQuery->calculate($project, $filter->reportingAsOf));
    }

    /** @return Collection<int,array<string,mixed>> */
    public function stocks(ApiFilter $filter, ApiKeyPrincipal $principal): Collection
    {
        $stocks = MaterialStok::query()
            ->with(['warehouse.mitra', 'material.unit', 'mitra'])
            ->when($filter->periodFrom !== null, fn (Builder $query) => $query->whereDate('updated_at', '>=', $filter->periodFrom->toDateString()))
            ->when($filter->periodTo !== null, fn (Builder $query) => $query->whereDate('updated_at', '<=', $filter->periodTo->toDateString()))
            ->whereDate('updated_at', '<=', $filter->reportingAsOf->toDateString())
            ->when(! $principal->isThc(), function (Builder $query) use ($principal): void {
                $query->where(function (Builder $query) use ($principal): void {
                    $query->where('mitra_id', $principal->mitraId())
                        ->orWhereHas('warehouse', fn (Builder $warehouse) => $warehouse->where('mitra_id', $principal->mitraId()));
                });
            })
            ->when($filter->mitras !== [], function (Builder $query) use ($filter): void {
                $mitraIds = $this->mitraIds($filter->mitras);
                $query->where(function (Builder $query) use ($mitraIds): void {
                    $query->whereIn('mitra_id', $mitraIds)
                        ->orWhereHas('warehouse', fn (Builder $warehouse) => $warehouse->whereIn('mitra_id', $mitraIds));
                });
            })
            ->orderBy('id')
            ->get();

        $projectIds = $stocks->where('lokasi_tipe', 'project')->pluck('lokasi_id')->filter()->unique()->values();
        $projects = $projectIds->isEmpty()
            ? collect()
            : Project::query()->whereIn('id', $projectIds)->get()->keyBy('id');

        return $stocks
            ->map(function (MaterialStok $stock) use ($projects): array {
                $project = $stock->lokasi_tipe === 'project' ? $projects->get($stock->lokasi_id) : null;

                return [
                    'warehouse' => $this->warehousePayload($stock->warehouse),
                    'material' => $this->materialReferencePayload($stock->material),
                    'mitra' => $this->mitraPayload($stock->mitra ?? $stock->warehouse?->mitra),
                    'location_type' => (string) $stock->lokasi_tipe,
                    'project' => $project === null ? null : $this->projectReference($project),
                    'quantity' => $this->decimal($stock->qty),
                    'updated_at' => $this->dateTime($stock->updated_at),
                ];
            })
            ->filter(function (array $row) use ($filter): bool {
                if ($filter->projects === []) {
                    return true;
                }

                return $row['project'] !== null && in_array($row['project']['id_project'], $filter->projects, true);
            })
            ->values();
    }

    /** @return Collection<int,array<string,mixed>> */
    public function materialRequests(ApiFilter $filter, ?Project $project = null): Collection
    {
        return MaterialRequest::query()
            ->with(['project.mitra', 'mitra', 'items.material.unit'])
            ->when($project !== null, fn (Builder $query) => $query->where('project_id', $project->id))
            ->when($filter->projects !== [], fn (Builder $query) => $query->whereHas('project', fn (Builder $projectQuery) => $projectQuery->whereIn('id_project', $filter->projects)))
            ->when($filter->mitras !== [], fn (Builder $query) => $query->whereHas('mitra', fn (Builder $mitraQuery) => $mitraQuery->whereIn('kode', $filter->mitras)))
            ->when($filter->periodFrom !== null, fn (Builder $query) => $query->whereDate('created_at', '>=', $filter->periodFrom->toDateString()))
            ->when($filter->periodTo !== null, fn (Builder $query) => $query->whereDate('created_at', '<=', $filter->periodTo->toDateString()))
            ->whereDate('created_at', '<=', $filter->reportingAsOf->toDateString())
            ->latest('id')
            ->get()
            ->map(fn (MaterialRequest $request): array => [
                'project' => $request->project === null ? null : $this->projectReference($request->project),
                'mitra' => $this->mitraPayload($request->mitra),
                'status' => (string) $request->status,
                'items' => $request->items->map(fn ($item): array => [
                    'material' => $this->materialReferencePayload($item->material),
                    'quantity' => $this->decimal($item->qty),
                ])->values()->all(),
                'created_at' => $this->dateTime($request->created_at),
                'decided_at' => $this->dateTime($request->decided_at),
            ])
            ->values();
    }

    /** @return Collection<int,array<string,mixed>> */
    public function materialTransactions(ApiFilter $filter, ?Project $project = null, ?ApiKeyPrincipal $principal = null): Collection
    {
        return MaterialTransaksi::query()
            ->with(['material.unit', 'warehouse.mitra', 'project.mitra'])
            ->when($project !== null, fn (Builder $query) => $query->where('project_id', $project->id))
            ->when($filter->projects !== [], fn (Builder $query) => $query->whereHas('project', fn (Builder $projectQuery) => $projectQuery->whereIn('id_project', $filter->projects)))
            ->when($filter->mitras !== [], fn (Builder $query) => $query->where(function (Builder $query) use ($filter): void {
                $query->whereIn('mitra_id', $this->mitraIds($filter->mitras))
                    ->orWhereHas('project.mitra', fn (Builder $mitraQuery) => $mitraQuery->whereIn('kode', $filter->mitras));
            }))
            ->when($principal !== null && ! $principal->isThc(), function (Builder $query) use ($principal): void {
                $query->where(function (Builder $query) use ($principal): void {
                    $query->where('mitra_id', $principal->mitraId())
                        ->orWhereHas('project', fn (Builder $projectQuery) => $projectQuery->where('mitra_id', $principal->mitraId()))
                        ->orWhereHas('warehouse', fn (Builder $warehouseQuery) => $warehouseQuery->where('mitra_id', $principal->mitraId()));
                });
            })
            ->when($filter->periodFrom !== null, fn (Builder $query) => $query->whereDate('created_at', '>=', $filter->periodFrom->toDateString()))
            ->when($filter->periodTo !== null, fn (Builder $query) => $query->whereDate('created_at', '<=', $filter->periodTo->toDateString()))
            ->whereDate('created_at', '<=', $filter->reportingAsOf->toDateString())
            ->latest('id')
            ->get()
            ->map(fn (MaterialTransaksi $transaction): array => [
                'project' => $transaction->project === null ? null : $this->projectReference($transaction->project),
                'warehouse' => $this->warehousePayload($transaction->warehouse),
                'material' => $this->materialReferencePayload($transaction->material),
                'transaction_type' => (string) ($transaction->jenis_transaksi ?? 'unknown'),
                'location_type' => (string) ($transaction->lokasi_tipe ?? 'warehouse'),
                'quantity_delta' => $this->decimal($transaction->qty_delta),
                'reason' => (string) $transaction->reason,
                'occurred_at' => $this->dateTime($transaction->created_at),
            ])
            ->values();
    }

    /** @return Collection<int,array<string,mixed>> */
    public function reconciliations(ApiFilter $filter): Collection
    {
        $rekons = ProjectRekon::query()
            ->with(['project.mitra', 'mitra', 'correctionSource', 'items.material.unit', 'items.warehouse'])
            ->when($filter->projects !== [], fn (Builder $query) => $query->whereHas('project', fn (Builder $projectQuery) => $projectQuery->whereIn('id_project', $filter->projects)))
            ->when($filter->mitras !== [], fn (Builder $query) => $query->whereHas('mitra', fn (Builder $mitraQuery) => $mitraQuery->whereIn('kode', $filter->mitras)))
            ->when($filter->periodFrom !== null, fn (Builder $query) => $query->whereDate('created_at', '>=', $filter->periodFrom->toDateString()))
            ->when($filter->periodTo !== null, fn (Builder $query) => $query->whereDate('created_at', '<=', $filter->periodTo->toDateString()))
            ->whereDate('created_at', '<=', $filter->reportingAsOf->toDateString())
            ->orderBy('id')
            ->get();

        $activeIds = $rekons->groupBy('project_id')->map(function (Collection $projectRekons): ?int {
            $approved = $projectRekons->where('status', 'disetujui');
            $corrected = $approved->pluck('koreksi_dari_id')->filter()->map(fn ($id): int => (int) $id)->all();

            return $approved->reject(fn (ProjectRekon $rekon): bool => in_array((int) $rekon->id, $corrected, true))->sortByDesc('id')->first()?->id;
        });

        return $rekons->map(fn (ProjectRekon $rekon): array => [
            'project' => $this->projectReference($rekon->project),
            'mitra' => $this->mitraPayload($rekon->mitra),
            'number' => (string) $rekon->nomor,
            'source' => (string) $rekon->source,
            'status' => (string) $rekon->status,
            'corrects' => $rekon->correctionSource?->nomor,
            'is_active' => (int) $activeIds->get($rekon->project_id) === (int) $rekon->id,
            'approved_at' => $this->dateTime($rekon->approved_at),
            'items' => $rekon->items->map(fn (ProjectRekonItem $item): array => [
                'warehouse' => $this->warehousePayload($item->warehouse),
                'material' => $this->materialReferencePayload($item->material),
                'quantity_out' => $this->decimal($item->keluar_gudang),
                'quantity_installed' => $this->decimal($item->terpasang),
                'quantity_project_balance' => $this->decimal($item->sisa_project),
                'quantity_returned' => $this->decimal($item->dikembalikan),
                'quantity_lost_or_damaged' => $this->decimal($item->hilang_rusak),
                'loss_category' => $item->kategori_hilang_rusak,
                'responsible_party' => (string) $item->penanggung_jawab,
            ])->values()->all(),
        ])->values();
    }

    /** @return Collection<int,array<string,mixed>> */
    public function servicePrices(ApiFilter $filter, ?int $mitraId = null): Collection
    {
        return MitraHargaJasa::query()
            ->with(['mitra', 'pks', 'pekerjaanJasa'])
            ->when($mitraId !== null, fn (Builder $query) => $query->where('mitra_id', $mitraId))
            ->when($filter->mitras !== [], fn (Builder $query) => $query->whereHas('mitra', fn (Builder $mitraQuery) => $mitraQuery->whereIn('kode', $filter->mitras)))
            ->when($filter->periodFrom !== null, fn (Builder $query) => $query->whereDate('berlaku_mulai', '>=', $filter->periodFrom->toDateString()))
            ->when($filter->periodTo !== null, fn (Builder $query) => $query->whereDate('berlaku_mulai', '<=', $filter->periodTo->toDateString()))
            ->whereDate('berlaku_mulai', '<=', $filter->reportingAsOf->toDateString())
            ->orderBy('id')
            ->get()
            ->map(fn (MitraHargaJasa $price): array => [
                'mitra' => $this->mitraPayload($price->mitra),
                'pks_number' => $price->pks?->nomor,
                'service' => $price->pekerjaanJasa === null ? null : [
                    'code' => (string) $price->pekerjaanJasa->kode,
                    'name' => (string) $price->pekerjaanJasa->nama,
                ],
                'price' => $this->decimal($price->harga, 2),
                'status' => (string) $price->status,
                'valid_from' => $price->berlaku_mulai?->toDateString(),
                'decided_at' => $this->dateTime($price->diputuskan_at),
            ])
            ->values();
    }

    /** @return Collection<int,array<string,mixed>> */
    public function photoLinks(Project $project): Collection
    {
        return ProjectPhoto::query()
            ->with('step')
            ->where('project_id', $project->id)
            ->whereNotNull('drive_url')
            ->where('drive_url', '!=', '')
            ->latest('id')
            ->get()
            ->map(fn (ProjectPhoto $photo): array => [
                'url' => (string) $photo->drive_url,
                'step' => $photo->step?->step,
                'capture_date' => $photo->capture_date?->toDateString(),
            ])
            ->values();
    }

    /** @return Collection<int,array<string,mixed>> */
    private function projectMetrics(ApiFilter $filter): Collection
    {
        $projects = Project::query()
            ->with('mitra')
            ->when($filter->projects !== [], fn (Builder $query) => $query->whereIn('id_project', $filter->projects))
            ->when($filter->mitras !== [], fn (Builder $query) => $query->whereHas('mitra', fn (Builder $mitraQuery) => $mitraQuery->whereIn('kode', $filter->mitras)))
            ->when($filter->projectStatuses !== [], fn (Builder $query) => $query->whereIn('status_project', $filter->projectStatuses))
            ->when($filter->periodFrom !== null, fn (Builder $query) => $query->whereDate('created_at', '>=', $filter->periodFrom->toDateString()))
            ->when($filter->periodTo !== null, fn (Builder $query) => $query->whereDate('created_at', '<=', $filter->periodTo->toDateString()))
            ->whereDate('created_at', '<=', $filter->reportingAsOf->toDateString())
            ->orderBy('id_project')
            ->orderBy('id')
            ->get();

        return $projects
            ->map(function (Project $project) use ($filter): array {
                $curve = $this->curveQuery->calculate($project, $filter->reportingAsOf);
                $material = $this->materialQuery->calculate($project, $filter->reportingAsOf);

                return ['project' => $project, 'curve' => $curve, 'material' => $material];
            })
            ->filter(function (array $metric) use ($filter): bool {
                if ($filter->riskStatuses === []) {
                    return true;
                }

                return in_array($this->riskStatus($metric['curve']['spi_status']), $filter->riskStatuses, true);
            })
            ->values();
    }

    /** @return array<string,mixed> */
    private function projectPayload(Project $project, array $curve, ?array $material): array
    {
        return [
            'id_project' => (string) $project->id_project,
            'name' => (string) $project->nama,
            'mitra' => $this->mitraPayload($project->mitra),
            'status_project' => (string) $project->status_project,
            'toc' => $project->toc?->toDateString(),
            'verified_percent' => (float) $curve['verified_percent'],
            'pending_percent' => (float) $curve['pending_percent'],
            'spi' => $curve['spi'] === null ? null : (float) $curve['spi'],
            'spi_status' => $this->riskStatus($curve['spi_status']),
            'risk_status' => $this->riskStatus($curve['spi_status']),
            'risk_reasons' => $this->riskReasons($curve),
            'material_readiness_percent' => $material === null ? null : (float) $material['readiness_percent'],
            'updated_at' => $this->dateTime($project->updated_at),
        ];
    }

    /** @return array<string,mixed> */
    private function curvePayload(array $curve): array
    {
        return [
            'as_of' => (string) $curve['as_of'],
            'grand_total_rab_jasa' => $this->decimal($curve['grand_total_rab_jasa'], 2),
            'verified_percent' => (float) $curve['verified_percent'],
            'pending_percent' => (float) $curve['pending_percent'],
            'plan_percent' => (float) $curve['plan_percent'],
            'spi' => $curve['spi'] === null ? null : (float) $curve['spi'],
            'spi_status' => $this->riskStatus($curve['spi_status']),
            'verified_series' => $curve['verified_series'],
            'pending_series' => $curve['pending_series'],
            'pending_shadow_series' => $curve['pending_shadow_series'],
            'baseline_series' => $curve['baseline_series'],
            'original_baseline_series' => $curve['original_baseline_series'],
            'revised_baseline_series' => $curve['revised_baseline_series'],
            'original_baseline' => $curve['original_baseline'],
            'revised_baseline' => $curve['revised_baseline'],
            'active_baseline_kind' => $curve['active_baseline_kind'],
            'overdue' => (bool) $curve['overdue'],
            'baseline_flat_after_toc' => (bool) $curve['baseline_flat_after_toc'],
            'x_axis_end' => (string) $curve['x_axis_end'],
        ];
    }

    /** @return array<string,mixed> */
    private function materialReadinessPayload(array $material): array
    {
        return [
            'required' => $this->decimal($material['required'] ?? 0),
            'delivered' => $this->decimal($material['delivered'] ?? 0),
            'transit' => $this->decimal($material['transit'] ?? 0),
            'available' => $this->decimal($material['available'] ?? 0),
            'readiness_percent' => isset($material['readiness_percent']) ? (float) $material['readiness_percent'] : null,
            'state' => $material['state'] ?? 'empty',
            'items' => collect($material['items'] ?? [])->map(fn (array $item): array => [
                'material' => $this->materialReferencePayload($item['material'] ?? null),
                'required' => $this->decimal($item['required'] ?? 0),
                'delivered' => $this->decimal($item['delivered'] ?? 0),
                'transit' => $this->decimal($item['transit'] ?? 0),
                'readiness_percent' => (float) ($item['readiness_percent'] ?? 0),
            ])->values()->all(),
        ];
    }

    /** @return array<string,mixed>|null */
    private function materialReferencePayload(?object $material): ?array
    {
        if ($material === null) {
            return null;
        }

        return [
            'code' => (string) $material->kode,
            'name' => (string) $material->nama,
            'unit' => $material->unit?->nama ?? null,
        ];
    }

    /** @return array<string,mixed>|null */
    private function warehousePayload(?object $warehouse): ?array
    {
        return $warehouse === null ? null : [
            'code' => (string) $warehouse->kode,
            'name' => (string) $warehouse->nama,
        ];
    }

    /** @return array<string,mixed>|null */
    private function mitraPayload(?object $mitra): ?array
    {
        return $mitra === null ? null : [
            'code' => (string) $mitra->kode,
            'name' => (string) $mitra->nama,
        ];
    }

    /** @return array{id_project:string,name:string,mitra:array<string,mixed>|null} */
    private function projectReference(Project $project): array
    {
        return [
            'id_project' => (string) $project->id_project,
            'name' => (string) $project->nama,
            'mitra' => $this->mitraPayload($project->mitra),
        ];
    }

    /** @return array<string,mixed> */
    private function portfolioKpis(Collection $metrics): array
    {
        $grandTotal = 0.0;
        $verifiedValue = 0.0;
        $pendingValue = 0.0;
        $baselinedTotal = 0.0;
        $baselinedVerified = 0.0;
        $baselinedPlan = 0.0;
        $baselinedProjects = 0;
        $attention = 0;
        $readiness = [];
        $transitProjects = 0;

        foreach ($metrics as $metric) {
            $curve = $metric['curve'];
            $total = (float) $curve['grand_total_rab_jasa'];
            $grandTotal += $total;
            $verifiedValue += $total * (float) $curve['verified_percent'] / 100;
            $pendingValue += $total * (float) $curve['pending_percent'] / 100;
            if ($curve['active_baseline_kind'] !== null) {
                $baselinedProjects++;
                $baselinedTotal += $total;
                $baselinedVerified += $total * (float) $curve['verified_percent'] / 100;
                $baselinedPlan += $total * (float) $curve['plan_percent'] / 100;
            }
            if (in_array($curve['spi_status'], ['yellow', 'red'], true)) {
                $attention++;
            }
            if ((float) ($metric['material']['required'] ?? 0) > 0) {
                $readiness[] = (float) $metric['material']['readiness_percent'];
                if ((float) $metric['material']['transit'] > 0) {
                    $transitProjects++;
                }
            }
        }

        $planPercent = $this->percent($baselinedPlan, $baselinedTotal);
        $spi = $planPercent > 0 ? round($this->percent($baselinedVerified, $baselinedTotal) / $planPercent, 4) : null;

        return [
            'active_projects' => $metrics->count(),
            'verified_percent' => $this->percent($verifiedValue, $grandTotal),
            'pending_percent' => $this->percent($pendingValue, $grandTotal),
            'plan_percent' => $planPercent,
            'attention_projects' => $attention,
            'baselined_projects' => $baselinedProjects,
            'active_rab_value' => $this->decimal($grandTotal, 2),
            'spi' => $spi,
            'spi_status' => $spi === null ? 'na' : $this->riskStatus(SpiThreshold::status($spi)),
            'material_projects' => count($readiness),
            'material_transit_projects' => $transitProjects,
            'material_readiness_percent' => $readiness === [] ? null : round(array_sum($readiness) / count($readiness), 2),
        ];
    }

    /** @return array<string,mixed> */
    private function trend(Collection $metrics, CarbonInterface $asOf): array
    {
        $asOfDate = $asOf->toDateString();
        $dates = collect([$asOfDate]);
        foreach ($metrics as $metric) {
            foreach (['verified_series', 'baseline_series'] as $key) {
                foreach ($metric['curve'][$key] as $point) {
                    if ($point['date'] <= $asOfDate) {
                        $dates->push($point['date']);
                    }
                }
            }
        }

        return [
            'as_of' => $asOfDate,
            'points' => $dates->unique()->sort()->values()->map(function (string $date) use ($metrics): array {
                $total = 0.0;
                $verified = 0.0;
                $baselineTotal = 0.0;
                $target = 0.0;
                foreach ($metrics as $metric) {
                    $curve = $metric['curve'];
                    $projectTotal = (float) $curve['grand_total_rab_jasa'];
                    $total += $projectTotal;
                    $verified += $projectTotal * $this->seriesValueAt($curve['verified_series'], $date) / 100;
                    if ($curve['active_baseline_kind'] !== null) {
                        $baselineTotal += $projectTotal;
                        $target += $projectTotal * $this->seriesValueAt($curve['baseline_series'], $date) / 100;
                    }
                }

                return [
                    'date' => $date,
                    'verified_percent' => $this->percent($verified, $total),
                    'target_percent' => $this->percent($target, $baselineTotal),
                    'verified_value' => $this->decimal($verified, 2),
                    'target_value' => $this->decimal($target, 2),
                    'target_available' => $baselineTotal > 0,
                ];
            })->all(),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function riskDistribution(Collection $metrics): array
    {
        $counts = array_fill_keys(['healthy', 'watch', 'critical', 'na'], 0);
        foreach ($metrics as $metric) {
            $counts[$this->riskStatus($metric['curve']['spi_status'])]++;
        }

        return collect($counts)->map(fn (int $count, string $status): array => [
            'status' => $status,
            'count' => $count,
            'percent' => $metrics->count() === 0 ? 0.0 : round($count / $metrics->count() * 100, 2),
        ])->values()->all();
    }

    /** @return array<int,array<string,mixed>> */
    private function projectStatusDistribution(Collection $metrics): array
    {
        $counts = $metrics->groupBy(fn (array $metric): string => (string) $metric['project']->status_project)->map->count();

        return collect(['aktif', 'selesai'])->map(fn (string $status): array => [
            'status_project' => $status,
            'count' => (int) $counts->get($status, 0),
        ])->all();
    }

    /** @return array<int,array<string,mixed>> */
    private function activity(Collection $metrics, CarbonInterface $asOf): array
    {
        $projectIds = $metrics->map(fn (array $metric): int => (int) $metric['project']->id)->all();
        if ($projectIds === []) {
            return [];
        }

        return ProjectTimeline::query()
            ->whereIn('project_id', $projectIds)
            ->where('type', '!=', 'internal_note')
            ->where('created_at', '<=', $asOf->endOfDay())
            ->with(['project.mitra', 'actor'])
            ->latest('created_at')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (ProjectTimeline $timeline): array => [
                'project' => $this->projectReference($timeline->project),
                'type' => (string) $timeline->type,
                'event_key' => $timeline->event_key,
                'body' => $timeline->type === 'comment' ? $timeline->body : null,
                'actor' => $timeline->actor?->name,
                'occurred_at' => $this->dateTime($timeline->created_at),
            ])
            ->values()
            ->all();
    }

    /** @return array<int,array<string,mixed>> */
    private function decisionQueue(Collection $metrics, CarbonInterface $asOf): array
    {
        $items = collect();
        foreach ($metrics->where(fn (array $metric): bool => $metric['project']->status_project === 'aktif') as $metric) {
            $project = $metric['project'];
            $curve = $metric['curve'];
            if ($curve['spi_status'] === 'red' || $curve['spi_status'] === 'yellow') {
                $items->push($this->queueItem(
                    'spi',
                    $curve['spi_status'] === 'red' ? 'critical' : 'watch',
                    $project,
                    'SPI '.$curve['spi_label'],
                    'Realisasi jasa tertinggal dari baseline yang berlaku.',
                    $project->updated_at,
                    $curve['spi_status'] === 'red' ? 100 : 80,
                ));
            }
            $material = $metric['material'];
            if ((float) $material['required'] > 0 && $material['state'] !== 'ready') {
                $items->push($this->queueItem(
                    'material',
                    'watch',
                    $project,
                    'Kesiapan Material '.$this->decimal($material['readiness_percent']).'%',
                    'Material Transit tidak dihitung sebagai stok tersedia.',
                    $project->updated_at,
                    60,
                ));
            }
            $pending = ProjectProgress::query()
                ->where('project_id', $project->id)
                ->where('status', 'pending')
                ->whereDate('actual_date', '<=', $asOf->toDateString())
                ->count();
            if ($pending > 0) {
                $items->push($this->queueItem(
                    'evidence',
                    'low',
                    $project,
                    $pending.' bukti pekerjaan menunggu verifikasi',
                    'Progres pending belum masuk Realisasi jasa.',
                    $project->updated_at,
                    30,
                ));
            }
            if ($project->toc !== null && $project->toc->betweenIncluded($asOf->startOfDay(), $asOf->copy()->addDays(30)->endOfDay())) {
                $items->push($this->queueItem(
                    'toc',
                    'watch',
                    $project,
                    'TOC mendekat',
                    'TOC berada dalam 30 hari dari reporting_as_of.',
                    $project->updated_at,
                    50,
                ));
            }
        }

        return $items->sort(function (array $left, array $right): int {
            $priority = $right['priority'] <=> $left['priority'];
            if ($priority !== 0) {
                return $priority;
            }

            return strcmp((string) $left['project']['id_project'], (string) $right['project']['id_project']);
        })->values()->map(function (array $item): array {
            unset($item['priority']);

            return $item;
        })->all();
    }

    /** @return array<string,mixed> */
    private function queueItem(string $category, string $risk, Project $project, string $title, string $reason, mixed $updatedAt, int $priority): array
    {
        return [
            'category' => $category,
            'risk_status' => $risk === 'critical' ? 'critical' : ($risk === 'watch' ? 'watch' : 'na'),
            'title' => $title,
            'reason' => $reason,
            'project' => $this->projectReference($project),
            'updated_at' => $this->dateTime($updatedAt),
            'priority' => $priority,
        ];
    }

    /** @return array<int,int> */
    private function mitraIds(array $codes): array
    {
        return Mitra::query()->whereIn('kode', $codes)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    private function riskStatus(string $spiStatus): string
    {
        return match ($spiStatus) {
            'green' => 'healthy',
            'yellow' => 'watch',
            'red' => 'critical',
            default => 'na',
        };
    }

    /** @return array<int,string> */
    private function riskReasons(array $curve): array
    {
        return match ($curve['spi_status']) {
            'red' => ['spi_critical'],
            'yellow' => ['spi_watch'],
            'na' => ['baseline_unavailable'],
            default => [],
        };
    }

    /** @param array<int,array{date:string,percent:float}> $series */
    private function seriesValueAt(array $series, string $date): float
    {
        $value = 0.0;
        foreach ($series as $point) {
            if ($point['date'] > $date) {
                break;
            }
            $value = (float) $point['percent'];
        }

        return $value;
    }

    private function percent(float $value, float $total): float
    {
        return $total <= 0 ? 0.0 : min(100.0, round($value / $total * 100, 2));
    }

    private function decimal(mixed $value, int $scale = 3): string
    {
        return number_format((float) $value, $scale, '.', '');
    }

    private function dateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! $value instanceof CarbonInterface) {
            $value = CarbonImmutable::parse($value);
        }

        return CarbonImmutable::instance($value)->utc()->toISOString();
    }
}
