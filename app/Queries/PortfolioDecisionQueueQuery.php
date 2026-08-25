<?php

namespace App\Queries;

use App\Models\Project;
use App\Models\ProjectBaseline;
use App\Models\ProjectProgress;
use App\Models\ProjectRabMaterial;
use App\Models\SuratJalan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Read model untuk pengecualian yang perlu ditindaklanjuti dari Portfolio Cockpit.
 *
 * Queue hanya mengarahkan ke sumber data. Ia tidak mengubah state domain apa pun.
 */
class PortfolioDecisionQueueQuery
{
    private const TRANSIT_LIMIT_DAYS = 3;

    /** Product contract for the Portfolio Cockpit prototype: 30 calendar days. */
    private const TOC_NEAR_DAYS = 30;

    /** @return Collection<int, array<string, mixed>> */
    public function for(User $viewer, Collection $metrics, array $filters): Collection
    {
        $items = collect();
        $metricsByProjectId = $metrics->keyBy(fn (array $metric): int => (int) $metric['project']->id);

        if ($viewer->hasIzin('read_project') && $viewer->hasIzin('read_project_progress')) {
            $this->appendSpi($items, $metrics, $filters);
            $this->appendPendingEvidence($items, $metricsByProjectId, $filters);
        }

        if ($viewer->hasIzin('read_project')) {
            $this->appendMaterial($items, $metrics, $filters);
            $this->appendToc($items, $metricsByProjectId, $filters);
        }

        if ($viewer->hasIzin('operate_warehouse')) {
            $this->appendDelayedTransit($items, $metricsByProjectId, $filters);
        }

        return $items
            ->sort(function (array $left, array $right): int {
                $priority = $right['priority'] <=> $left['priority'];
                if ($priority !== 0) {
                    return $priority;
                }

                $updatedAt = $right['updated_at']->getTimestamp() <=> $left['updated_at']->getTimestamp();
                if ($updatedAt !== 0) {
                    return $updatedAt;
                }

                return $left['project_id'] <=> $right['project_id'];
            })
            ->values();
    }

    /** @param Collection<int, array<string, mixed>> $items */
    private function appendSpi(Collection $items, Collection $metrics, array $filters): void
    {
        $updatedAtByProject = $this->spiUpdatedAtByProject($metrics, $filters);

        $metrics
            ->filter(fn (array $metric): bool => in_array($metric['curve']['spi_status'], ['yellow', 'red'], true))
            ->each(function (array $metric) use ($items, $filters, $updatedAtByProject): void {
                /** @var Project $project */
                $project = $metric['project'];
                $curve = $metric['curve'];
                $isHigh = $curve['spi_status'] === 'red';
                $spi = $curve['spi'] === null ? 'N/A' : number_format((float) $curve['spi'], 2, '.', '');

                $items->push($this->item(
                    category: 'spi',
                    categoryLabel: 'SPI rendah',
                    risk: $isHigh ? 'tinggi' : 'waspada',
                    riskLabel: $isHigh ? 'Tinggi' : 'Waspada',
                    title: 'SPI '.$spi.' · '.$project->nama,
                    description: 'Realisasi jasa tertinggal dari baseline yang berlaku; item ini perlu ditinjau di Project Control Room.',
                    project: $project,
                    updatedAt: $updatedAtByProject->get((int) $project->id) ?? $this->projectUpdatedAt($project, $filters),
                    sourceLabel: 'Project Control Room',
                    url: route('projects.show', $project),
                    priority: $isHigh ? 100 : 80,
                ));
            });
    }

    /** @param Collection<int, array<string, mixed>> $metricsByProjectId */
    private function appendDelayedTransit(Collection $items, Collection $metricsByProjectId, array $filters): void
    {
        if ($metricsByProjectId->isEmpty()) {
            return;
        }

        $asOf = CarbonImmutable::instance($filters['as_of']);
        $cutoff = $asOf->startOfDay()->subDays(self::TRANSIT_LIMIT_DAYS);

        SuratJalan::query()
            ->whereIn('project_id', $metricsByProjectId->keys()->all())
            ->where('status', 'terbit')
            ->whereNotNull('issued_at')
            ->where('issued_at', '<', $cutoff)
            ->with(['asal', 'tujuan', 'items.material.unit'])
            ->orderBy('issued_at')
            ->get()
            ->each(function (SuratJalan $suratJalan) use ($items, $metricsByProjectId, $asOf): void {
                /** @var Project|null $project */
                $project = $metricsByProjectId->get((int) $suratJalan->project_id)['project'] ?? null;
                if ($project === null) {
                    return;
                }

                $issuedAt = CarbonImmutable::instance($suratJalan->issued_at);
                $age = $issuedAt->startOfDay()->diffInDays($asOf->startOfDay());
                $asal = $suratJalan->asal?->nama ?? 'Warehouse asal tidak tersedia';
                $tujuan = $suratJalan->tujuan?->nama ?? 'Warehouse tujuan tidak tersedia';

                $items->push($this->item(
                    category: 'transit',
                    categoryLabel: 'Transit melewati batas',
                    risk: 'tinggi',
                    riskLabel: 'Tinggi',
                    title: 'Transit '.$age.' hari · '.$suratJalan->nomor,
                    description: 'Surat Jalan '.$suratJalan->nomor.' masih terbit dari '.$asal.' ke '.$tujuan.' setelah lebih dari 3 hari; tindak lanjuti di sumber Transit.',
                    project: $project,
                    updatedAt: $issuedAt,
                    sourceLabel: 'Surat Jalan / Transit',
                    url: route('warehouse.transfers.print', $suratJalan),
                    priority: 90,
                ));
            });
    }

    /** @param Collection<int, array<string, mixed>> $items */
    private function appendMaterial(Collection $items, Collection $metrics, array $filters): void
    {
        $updatedAtByProject = $this->materialUpdatedAtByProject($metrics, $filters);

        $metrics->each(function (array $metric) use ($items, $filters, $updatedAtByProject): void {
            /** @var Project $project */
            $project = $metric['project'];
            $material = $metric['material'];
            if ($material === null || (float) $material['required'] <= 0 || $material['state'] === 'ready') {
                return;
            }

            $readiness = number_format((float) $material['readiness_percent'], 2, '.', '');
            $transit = number_format((float) $material['transit'], 3, '.', '');

            $items->push($this->item(
                category: 'material',
                categoryLabel: 'Material belum lengkap',
                risk: 'waspada',
                riskLabel: 'Waspada',
                title: 'Kesiapan Material '.$readiness.'% · '.$project->nama,
                description: 'Material tersedia baru '.$readiness.'%. Material Transit tidak dianggap sebagai Material tersedia (qty Transit: '.$transit.').',
                project: $project,
                updatedAt: $updatedAtByProject->get((int) $project->id) ?? $this->projectUpdatedAt($project, $filters),
                sourceLabel: 'Kesiapan Material Project',
                url: route('projects.show', $project),
                priority: (float) $material['readiness_percent'] <= 0 ? 70 : 60,
            ));
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  Collection<int, array<string, mixed>>  $metricsByProjectId
     */
    private function appendToc(Collection $items, Collection $metricsByProjectId, array $filters): void
    {
        $asOf = CarbonImmutable::instance($filters['as_of'])->startOfDay();
        $nearLimit = $asOf->addDays(self::TOC_NEAR_DAYS);
        $baselinesByProject = ProjectBaseline::query()
            ->whereIn('project_id', $metricsByProjectId->keys()->all())
            ->where('created_at', '<=', $asOf->endOfDay())
            ->orderBy('version')
            ->orderBy('id')
            ->get()
            ->groupBy('project_id')
            ->map(fn (Collection $baselines): ?ProjectBaseline => $baselines->last());

        $metricsByProjectId->each(function (array $metric) use ($items, $asOf, $nearLimit, $filters, $baselinesByProject): void {
            /** @var Project $project */
            $project = $metric['project'];
            $baseline = $baselinesByProject->get((int) $project->id);
            $projectUpdatedAt = $this->projectUpdatedAt($project, $filters);
            $toc = $baseline?->toc;

            if ($toc === null && $project->toc !== null && $projectUpdatedAt->lte($asOf->endOfDay())) {
                $toc = $project->toc;
            }
            if ($toc === null) {
                return;
            }

            $toc = CarbonImmutable::instance($toc)->startOfDay();
            if ($toc->lt($asOf) || $toc->gt($nearLimit)) {
                return;
            }

            $days = $asOf->diffInDays($toc);
            $items->push($this->item(
                category: 'toc',
                categoryLabel: 'TOC mendekat',
                risk: 'waspada',
                riskLabel: 'Waspada',
                title: 'TOC mendekat · '.$days.' hari · '.$project->nama,
                description: 'TOC jatuh pada '.$toc->format('d M Y').' dalam '.self::TOC_NEAR_DAYS.' hari; tinjau rencana penyelesaian Project.',
                project: $project,
                updatedAt: $baseline !== null ? CarbonImmutable::instance($baseline->updated_at) : $projectUpdatedAt,
                sourceLabel: 'Project Control Room',
                url: route('projects.show', $project),
                priority: 50,
            ));
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  Collection<int, array<string, mixed>>  $metricsByProjectId
     */
    private function appendPendingEvidence(Collection $items, Collection $metricsByProjectId, array $filters): void
    {
        if ($metricsByProjectId->isEmpty()) {
            return;
        }

        $asOf = CarbonImmutable::instance($filters['as_of']);
        ProjectProgress::query()
            ->whereIn('project_id', $metricsByProjectId->keys()->all())
            ->where('status', 'pending')
            ->whereDate('actual_date', '<=', $asOf->toDateString())
            ->where('created_at', '<=', $asOf->endOfDay())
            ->select('project_id')
            ->selectRaw('COUNT(*) as pending_count')
            ->selectRaw('SUM(qty) as pending_qty')
            ->selectRaw('MAX(updated_at) as latest_updated_at')
            ->groupBy('project_id')
            ->get()
            ->each(function ($pending) use ($items, $metricsByProjectId, $filters): void {
                /** @var Project|null $project */
                $project = $metricsByProjectId->get((int) $pending->project_id)['project'] ?? null;
                if ($project === null) {
                    return;
                }

                $updatedAt = $pending->latest_updated_at !== null
                    ? CarbonImmutable::parse($pending->latest_updated_at)
                    : $this->projectUpdatedAt($project, $filters);
                $count = (int) $pending->pending_count;
                $qty = number_format((float) $pending->pending_qty, 3, '.', '');

                $items->push($this->item(
                    category: 'evidence',
                    categoryLabel: 'Bukti pekerjaan pending',
                    risk: 'rendah',
                    riskLabel: 'Rendah',
                    title: $count.' bukti pekerjaan menunggu verifikasi · '.$project->nama,
                    description: 'Progres jasa pending (qty '.$qty.') belum diverifikasi THC dan tidak masuk Realisasi jasa.',
                    project: $project,
                    updatedAt: $updatedAt,
                    sourceLabel: 'Progres Jasa / Project Control Room',
                    url: route('projects.show', $project),
                    priority: 30,
                ));
            });
    }

    /** @return array<string, mixed> */
    private function item(
        string $category,
        string $categoryLabel,
        string $risk,
        string $riskLabel,
        string $title,
        string $description,
        Project $project,
        CarbonImmutable $updatedAt,
        string $sourceLabel,
        ?string $url,
        int|float $priority,
    ): array {
        return [
            'category' => $category,
            'category_label' => $categoryLabel,
            'risk' => $risk,
            'risk_level' => $risk,
            'risk_label' => $riskLabel,
            'title' => $title,
            'description' => $description,
            'reason' => $description,
            'project_id' => (int) $project->id,
            'id_project' => (string) $project->id_project,
            'project_name' => (string) $project->nama,
            'mitra' => $project->mitra?->nama ?? 'Mitra tidak tersedia',
            'updated_at' => $updatedAt,
            'occurred_at' => $updatedAt,
            'source_label' => $sourceLabel,
            'source' => $sourceLabel,
            'url' => $url,
            'source_url' => $url,
            'priority' => $priority,
        ];
    }

    private function projectUpdatedAt(Project $project, array $filters): CarbonImmutable
    {
        return $project->updated_at !== null
            ? CarbonImmutable::instance($project->updated_at)
            : CarbonImmutable::instance($filters['as_of']);
    }

    /** @param Collection<int, array<string, mixed>> $metrics */
    private function spiUpdatedAtByProject(Collection $metrics, array $filters): Collection
    {
        $projectIds = $metrics
            ->map(fn (array $metric): int => (int) $metric['project']->id)
            ->values()
            ->all();
        if ($projectIds === []) {
            return collect();
        }

        $asOf = CarbonImmutable::instance($filters['as_of']);
        $progress = ProjectProgress::query()
            ->whereIn('project_id', $projectIds)
            ->where('status', 'verified')
            ->whereDate('actual_date', '<=', $asOf->toDateString())
            ->where('updated_at', '<=', $asOf->endOfDay())
            ->select('project_id')
            ->selectRaw('MAX(updated_at) as latest_updated_at')
            ->groupBy('project_id')
            ->get();
        $baselines = ProjectBaseline::query()
            ->whereIn('project_id', $projectIds)
            ->where('created_at', '<=', $asOf->endOfDay())
            ->where('updated_at', '<=', $asOf->endOfDay())
            ->select('project_id')
            ->selectRaw('MAX(updated_at) as latest_updated_at')
            ->groupBy('project_id')
            ->get();

        $timestamps = collect();
        $this->mergeLatestTimestamps($timestamps, $progress);
        $this->mergeLatestTimestamps($timestamps, $baselines);

        return $timestamps;
    }

    /** @param Collection<int, array<string, mixed>> $metrics */
    private function materialUpdatedAtByProject(Collection $metrics, array $filters): Collection
    {
        $projectIds = $metrics
            ->map(fn (array $metric): int => (int) $metric['project']->id)
            ->values()
            ->all();
        if ($projectIds === []) {
            return collect();
        }

        $asOf = CarbonImmutable::instance($filters['as_of']);
        $requirements = ProjectRabMaterial::query()
            ->whereIn('project_id', $projectIds)
            ->where('updated_at', '<=', $asOf->endOfDay())
            ->select('project_id')
            ->selectRaw('MAX(updated_at) as latest_updated_at')
            ->groupBy('project_id')
            ->get();
        $shipments = SuratJalan::query()
            ->where(function ($query) use ($projectIds): void {
                $query
                    ->whereIn('project_id', $projectIds)
                    ->orWhereHas('materialRequest', fn ($request) => $request->whereIn('project_id', $projectIds));
            })
            ->whereDate('tanggal', '<=', $asOf->toDateString())
            ->where('updated_at', '<=', $asOf->endOfDay())
            ->select('project_id')
            ->selectRaw('MAX(updated_at) as latest_updated_at')
            ->groupBy('project_id')
            ->get();

        $timestamps = collect();
        $this->mergeLatestTimestamps($timestamps, $requirements);
        $this->mergeLatestTimestamps($timestamps, $shipments);

        return $timestamps;
    }

    /**
     * @param  Collection<int, CarbonImmutable>  $timestamps
     * @param  Collection<int, object>  $rows
     */
    private function mergeLatestTimestamps(Collection $timestamps, Collection $rows): void
    {
        $rows->each(function (object $row) use ($timestamps): void {
            if ($row->latest_updated_at === null) {
                return;
            }

            $projectId = (int) $row->project_id;
            $updatedAt = CarbonImmutable::parse($row->latest_updated_at);
            $current = $timestamps->get($projectId);
            if ($current === null || $updatedAt->gt($current)) {
                $timestamps->put($projectId, $updatedAt);
            }
        });
    }
}
