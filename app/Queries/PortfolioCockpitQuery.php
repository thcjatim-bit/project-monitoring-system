<?php

namespace App\Queries;

use App\Models\Mitra;
use App\Models\Project;
use App\Models\ProjectTimeline;
use App\Models\User;
use App\Support\SpiThreshold;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Read model untuk Portfolio Cockpit.
 *
 * Cockpit membaca dan menautkan; ia tidak pernah memutasi Project, Material,
 * Surat Jalan, Progres, Step, atau Rekon Material. Isolasi mitra datang dari
 * global scope Project dan Row-Level Security, jadi query ini tidak pernah
 * menyaring tenant sendiri. Izin per aksi mengikuti ADR-0006: angka Progres dan
 * Material hanya dihitung bila user memegang izin modul pemiliknya.
 */
class PortfolioCockpitQuery
{
    /** Kunci filter status risiko (Indonesia) ke status SPI internal ADR-0010. */
    private const RISK_FILTER_TO_SPI_STATUS = [
        'semua' => null,
        'hijau' => 'green',
        'kuning' => 'yellow',
        'merah' => 'red',
        'na' => 'na',
    ];

    private const RISK_LABELS = [
        'semua' => 'Semua status risiko',
        'hijau' => 'Hijau',
        'kuning' => 'Kuning',
        'merah' => 'Merah',
        'na' => 'N/A',
    ];

    private const RISK_STATUS_LABELS = [
        'green' => 'Hijau',
        'yellow' => 'Kuning',
        'red' => 'Merah',
        'na' => 'N/A',
    ];

    private const PROJECT_STATUS_LABELS = [
        'aktif' => 'Aktif',
        'selesai' => 'Selesai',
    ];

    private const ACTIVITY_LABELS = [
        'progress_submitted' => 'Progres jasa diajukan',
        'progress_verified' => 'Progres jasa diverifikasi',
        'progress_rejected' => 'Progres jasa ditolak',
        'step_changed' => 'Step Project diperbarui',
        'toc_changed' => 'TOC Project diperbarui',
        'variation_order_created' => 'Variation Order dibuat',
        'variation_order_approved' => 'Variation Order disetujui',
        'photo_uploaded' => 'Foto Pekerjaan ditambahkan',
        'rab_material_added' => 'RAB Material ditambahkan',
    ];

    private const MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function __construct(
        private ProjectCurveQuery $curveQuery,
        private ProjectMaterialReadinessQuery $materialQuery,
        private PortfolioDecisionQueueQuery $decisionQueueQuery,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function for(User $viewer, array $input = [], ?CarbonImmutable $now = null): array
    {
        $now = $now ?? CarbonImmutable::now();
        $canReadProgress = $viewer->hasIzin('read_project_progress');
        $canReadMaterial = $viewer->hasIzin('read_project_material') || $viewer->hasIzin('manage_project_material');
        $filters = $this->filtersForViewer($input, $now, $canReadProgress);

        $projects = Project::query()
            ->with('mitra')
            ->when($filters['project'] !== null, fn ($query) => $query->whereKey($filters['project']))
            ->when($filters['mitra'] !== null, fn ($query) => $query->where('mitra_id', $filters['mitra']))
            ->orderBy('id_project')
            ->get();

        $allMetrics = $projects
            ->map(fn (Project $project): array => [
                'project' => $project,
                'curve' => $this->curveQuery->calculate($project, $filters['as_of'], $canReadProgress),
                'material' => $canReadMaterial ? $this->materialQuery->calculate($project, $filters['as_of']) : null,
            ])
            ->values();

        $spiStatus = self::RISK_FILTER_TO_SPI_STATUS[$filters['risiko']];
        $metrics = $allMetrics;
        if ($spiStatus !== null && $canReadProgress) {
            $metrics = $metrics
                ->filter(fn (array $metric): bool => $metric['curve']['spi_status'] === $spiStatus)
                ->values();
        }
        $activeMetrics = $metrics
            ->filter(fn (array $metric): bool => $metric['project']->status_project === 'aktif')
            ->values();
        $canReadTimeline = $viewer->hasIzin('read_project_timeline');

        return $this->payload(
            viewer: $viewer,
            filters: $filters,
            options: $this->options($viewer, $now),
            kpis: $this->kpis($activeMetrics, $canReadProgress, $canReadMaterial),
            scopedProjectCount: $projects->count(),
            matchedProjectCount: $activeMetrics->count(),
            canReadProgress: $canReadProgress,
            canReadMaterial: $canReadMaterial,
            canReadTimeline: $canReadTimeline,
            trend: $this->trend($activeMetrics, $filters),
            healthMatrix: $this->healthMatrix($metrics, $viewer, $canReadProgress, $canReadMaterial),
            statusDistribution: $this->statusDistribution($metrics, $canReadProgress),
            projectStatusDistribution: $this->projectStatusDistribution($metrics),
            activity: $canReadTimeline ? $this->activity($metrics, $filters, $viewer) : collect(),
            decisionQueue: $this->decisionQueueQuery->for($viewer, $activeMetrics, $filters),
            generatedAt: $now,
            portfolioError: null,
        );
    }

    /**
     * Error state yang tetap dapat dipakai: filter dan konteks user bertahan,
     * angkanya tidak berpura-pura nyata.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function errorState(User $viewer, array $input = [], ?CarbonImmutable $now = null): array
    {
        $now = $now ?? CarbonImmutable::now();

        $canReadProgress = $viewer->hasIzin('read_project_progress');
        $canReadMaterial = $viewer->hasIzin('read_project_material') || $viewer->hasIzin('manage_project_material');
        $filters = $this->filtersForViewer($input, $now, $canReadProgress);

        return $this->payload(
            viewer: $viewer,
            filters: $filters,
            options: $this->survivingOptions($viewer, $now),
            kpis: $this->emptyKpis(),
            scopedProjectCount: 0,
            matchedProjectCount: 0,
            canReadProgress: $canReadProgress,
            canReadMaterial: $canReadMaterial,
            canReadTimeline: $viewer->hasIzin('read_project_timeline'),
            trend: $this->emptyTrend($filters),
            healthMatrix: collect(),
            statusDistribution: $this->emptyStatusDistribution(),
            projectStatusDistribution: $this->emptyProjectStatusDistribution(),
            activity: collect(),
            decisionQueue: collect(),
            generatedAt: $now,
            portfolioError: 'Portfolio Cockpit belum dapat dimuat. Coba lagi atau buka modul sumbernya.',
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{project: ?int, mitra: ?int, periode: string, periode_label: string, risiko: string, risiko_label: string, as_of: CarbonImmutable}
     */
    public function filters(array $input, ?CarbonImmutable $now = null): array
    {
        $now = $now ?? CarbonImmutable::now();
        $periode = $this->periode($input['periode'] ?? null, $now);
        $risiko = (string) ($input['risiko'] ?? 'semua');
        if (! array_key_exists($risiko, self::RISK_FILTER_TO_SPI_STATUS)) {
            $risiko = 'semua';
        }

        return [
            'project' => $this->positiveInteger($input['project'] ?? null),
            'mitra' => $this->positiveInteger($input['mitra'] ?? null),
            'periode' => $periode,
            'periode_label' => $this->periodeLabel($periode),
            'risiko' => $risiko,
            'risiko_label' => self::RISK_LABELS[$risiko],
            'as_of' => $this->asOf($periode, $now),
        ];
    }

    /**
     * Risk status is derived from Progres jasa. A viewer without that module
     * permission must not be shown a filter that silently has no effect.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function filtersForViewer(array $input, CarbonImmutable $now, bool $canReadProgress): array
    {
        $filters = $this->filters($input, $now);
        if (! $canReadProgress) {
            $filters['risiko'] = 'semua';
            $filters['risiko_label'] = self::RISK_LABELS['semua'];
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $kpis
     * @return array<string, mixed>
     */
    private function payload(
        User $viewer,
        array $filters,
        array $options,
        array $kpis,
        int $scopedProjectCount,
        int $matchedProjectCount,
        bool $canReadProgress,
        bool $canReadMaterial,
        bool $canReadTimeline,
        array $trend,
        Collection $healthMatrix,
        array $statusDistribution,
        array $projectStatusDistribution,
        Collection $activity,
        Collection $decisionQueue,
        CarbonImmutable $generatedAt,
        ?string $portfolioError,
    ): array {
        return [
            'filters' => $filters,
            'options' => $options,
            'kpis' => $kpis,
            'scopedProjectCount' => $scopedProjectCount,
            'matchedProjectCount' => $matchedProjectCount,
            'canReadProgress' => $canReadProgress,
            'canReadMaterial' => $canReadMaterial,
            'canReadTimeline' => $canReadTimeline,
            'trend' => $trend,
            'healthMatrix' => $healthMatrix,
            'statusDistribution' => $statusDistribution,
            'projectStatusDistribution' => $projectStatusDistribution,
            'activity' => $activity,
            'decisionQueue' => $decisionQueue,
            'decisionQueueAvailable' => $viewer->hasIzin('read_project') || $viewer->hasIzin('operate_warehouse'),
            'projectsUrl' => $viewer->hasIzin('read_project') ? route('projects.index') : null,
            'generatedAt' => $generatedAt,
            'portfolioError' => $portfolioError,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $metrics
     * @return array<string, mixed>
     */
    private function kpis(Collection $metrics, bool $canReadProgress, bool $canReadMaterial): array
    {
        if ($metrics->isEmpty()) {
            return $this->emptyKpis();
        }

        $grandTotal = 0.0;
        $verifiedValue = 0.0;
        $pendingValue = 0.0;
        $attention = 0;
        // SPI hanya bermakna untuk Project yang punya baseline berlaku. Project
        // tanpa baseline tidak boleh ikut menaikkan atau menurunkan SPI agregat.
        $baselinedTotal = 0.0;
        $baselinedVerifiedValue = 0.0;
        $baselinedPlanValue = 0.0;
        $baselinedProjects = 0;
        // Kesiapan Material dirata-ratakan per Project karena qty lintas Material
        // memakai Unit berbeda dan tidak boleh dijumlahkan menjadi satu angka.
        $readinessPercents = [];
        $transitProjects = 0;

        foreach ($metrics as $metric) {
            $curve = $metric['curve'];
            $total = (float) $curve['grand_total_rab_jasa'];
            $grandTotal += $total;
            // Persen sudah mematuhi ADR-0010 (hanya terverifikasi, dibatasi 100%),
            // jadi portofolio menimbang tiap Project dengan nilai RAB Jasa-nya.
            $verifiedValue += $total * (float) $curve['verified_percent'] / 100;
            $pendingValue += $total * (float) $curve['pending_percent'] / 100;

            if ($curve['active_baseline_kind'] !== null) {
                $baselinedProjects++;
                $baselinedTotal += $total;
                $baselinedVerifiedValue += $total * (float) $curve['verified_percent'] / 100;
                $baselinedPlanValue += $total * (float) $curve['plan_percent'] / 100;
            }

            if (in_array($curve['spi_status'], ['yellow', 'red'], true)) {
                $attention++;
            }

            if ($metric['material'] !== null && (float) $metric['material']['required'] > 0) {
                $readinessPercents[] = (float) $metric['material']['readiness_percent'];

                if ((float) $metric['material']['transit'] > 0) {
                    $transitProjects++;
                }
            }
        }

        $planPercent = $this->percent($baselinedPlanValue, $baselinedTotal);
        $spi = $canReadProgress && $planPercent > 0
            ? round($this->percent($baselinedVerifiedValue, $baselinedTotal) / $planPercent, 4)
            : null;

        return [
            'active_projects' => $metrics->count(),
            'verified_percent' => $canReadProgress ? $this->percent($verifiedValue, $grandTotal) : null,
            'pending_percent' => $canReadProgress ? $this->percent($pendingValue, $grandTotal) : null,
            'plan_percent' => $canReadProgress ? $planPercent : null,
            'attention_projects' => $canReadProgress ? $attention : null,
            'baselined_projects' => $canReadProgress ? $baselinedProjects : null,
            'active_rab_value' => round($grandTotal, 2),
            'spi' => $spi,
            'spi_label' => SpiThreshold::label($spi),
            'spi_status' => SpiThreshold::status($spi),
            'material_projects' => $canReadMaterial ? count($readinessPercents) : null,
            'material_transit_projects' => $canReadMaterial ? $transitProjects : null,
            'material_readiness_percent' => $canReadMaterial && $readinessPercents !== []
                ? round(array_sum($readinessPercents) / count($readinessPercents), 2)
                : null,
        ];
    }

    /**
     * Build cumulative portfolio points from the same Project curves used by
     * the KPI cards. A Project without a baseline contributes no target point,
     * but its verified value remains visible in the actual series.
     *
     * @param  Collection<int, array<string, mixed>>  $metrics
     * @param  array<string, mixed>  $filters
     * @return array{periode:string,periode_label:string,as_of:string,points:array<int,array<string,mixed>>}
     */
    private function trend(Collection $metrics, array $filters): array
    {
        $asOf = $filters['as_of'];
        $dates = collect([$asOf->toDateString()]);

        foreach ($metrics as $metric) {
            foreach (['verified_series', 'baseline_series'] as $seriesKey) {
                foreach ($metric['curve'][$seriesKey] as $point) {
                    if ($point['date'] <= $asOf->toDateString()) {
                        $dates->push($point['date']);
                    }
                }
            }
        }

        $points = $dates
            ->unique()
            ->sort()
            ->values()
            ->map(function (string $date) use ($metrics): array {
                $grandTotal = 0.0;
                $verifiedValue = 0.0;
                $baselineTotal = 0.0;
                $targetValue = 0.0;

                foreach ($metrics as $metric) {
                    $curve = $metric['curve'];
                    $total = (float) $curve['grand_total_rab_jasa'];
                    $grandTotal += $total;
                    $verifiedValue += $total * $this->seriesValueAt($curve['verified_series'], $date) / 100;

                    if ($curve['active_baseline_kind'] !== null) {
                        $baselineTotal += $total;
                        $targetValue += $total * $this->seriesValueAt($curve['baseline_series'], $date) / 100;
                    }
                }

                return [
                    'date' => $date,
                    'verified_percent' => $this->percent($verifiedValue, $grandTotal),
                    'target_percent' => $this->percent($targetValue, $baselineTotal),
                    'verified_value' => round($verifiedValue, 2),
                    'target_value' => round($targetValue, 2),
                    'target_available' => $baselineTotal > 0,
                ];
            })
            ->all();

        return [
            'periode' => $filters['periode'],
            'periode_label' => $filters['periode_label'],
            'as_of' => $asOf->toDateString(),
            'points' => $points,
        ];
    }

    /** @param array<int, array{date:string,percent:float}> $series */
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

    /**
     * @param  Collection<int, array<string, mixed>>  $metrics
     * @return Collection<int, array<string, mixed>>
     */
    private function healthMatrix(Collection $metrics, User $viewer, bool $canReadProgress, bool $canReadMaterial): Collection
    {
        return $metrics->map(function (array $metric) use ($viewer, $canReadProgress, $canReadMaterial): array {
            /** @var Project $project */
            $project = $metric['project'];
            $curve = $metric['curve'];
            $material = $metric['material'];
            $riskStatus = $canReadProgress ? $curve['spi_status'] : 'na';
            $materialPercent = null;
            if ($canReadMaterial && $material !== null && $material['state'] !== 'empty') {
                $materialPercent = (float) $material['readiness_percent'];
            }

            return [
                'project_id' => (int) $project->id,
                'id_project' => (string) $project->id_project,
                'nama' => (string) $project->nama,
                'mitra' => $project->mitra?->nama ?? 'Mitra tidak tersedia',
                'status_project' => (string) $project->status_project,
                'status_project_label' => self::PROJECT_STATUS_LABELS[$project->status_project] ?? $project->status_project,
                'verified_percent' => $canReadProgress ? (float) $curve['verified_percent'] : null,
                'pending_percent' => $canReadProgress ? (float) $curve['pending_percent'] : null,
                'spi' => $canReadProgress ? $curve['spi'] : null,
                'spi_label' => $canReadProgress ? $curve['spi_label'] : 'N/A',
                'spi_status' => $riskStatus,
                'risk_label' => self::RISK_LABELS[$riskStatus] ?? 'N/A',
                'material_readiness_percent' => $materialPercent,
                'material_state' => $canReadMaterial ? ($material['state'] ?? 'empty') : 'forbidden',
                'url' => $viewer->hasIzin('read_project') ? route('projects.show', $project) : null,
            ];
        })->values();
    }

    /**
     * The risk distribution intentionally uses the exact risk statuses used by
     * the filter, so filtering and the summary cannot disagree.
     *
     * @param  Collection<int, array<string, mixed>>  $metrics
     * @return array<int, array{key:string,label:string,count:int,percent:float}>
     */
    private function statusDistribution(Collection $metrics, bool $canReadProgress): array
    {
        $counts = array_fill_keys(array_keys(self::RISK_STATUS_LABELS), 0);
        foreach ($metrics as $metric) {
            $status = $canReadProgress ? $metric['curve']['spi_status'] : 'na';
            $key = array_key_exists($status, $counts) ? $status : 'na';
            $counts[$key]++;
        }

        $total = $metrics->count();

        return collect($counts)
            ->map(fn (int $count, string $key): array => [
                'key' => $key,
                'label' => self::RISK_STATUS_LABELS[$key],
                'count' => $count,
                'percent' => $total > 0 ? round($count / $total * 100, 2) : 0.0,
            ])
            ->values()
            ->all();
    }

    /**
     * Lifecycle status is kept beside the risk distribution because both are
     * useful portfolio facts and have different meanings in the domain.
     *
     * @param  Collection<int, array<string, mixed>>  $metrics
     * @return array<int, array{key:string,label:string,count:int,percent:float}>
     */
    private function projectStatusDistribution(Collection $metrics): array
    {
        $counts = array_fill_keys(array_keys(self::PROJECT_STATUS_LABELS), 0);
        foreach ($metrics as $metric) {
            $status = $metric['project']->status_project;
            if (array_key_exists($status, $counts)) {
                $counts[$status]++;
            }
        }

        $total = $metrics->count();

        return collect($counts)
            ->map(fn (int $count, string $key): array => [
                'key' => $key,
                'label' => self::PROJECT_STATUS_LABELS[$key],
                'count' => $count,
                'percent' => $total > 0 ? round($count / $total * 100, 2) : 0.0,
            ])
            ->values()
            ->all();
    }

    /**
     * Project timeline is the single source for the portfolio activity strip.
     * Internal notes are excluded in the query itself as a second line of
     * defence after the route-level authorization.
     *
     * @param  Collection<int, array<string, mixed>>  $metrics
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function activity(Collection $metrics, array $filters, User $viewer): Collection
    {
        $projectIds = $metrics
            ->map(fn (array $metric): int => (int) $metric['project']->id)
            ->values();
        if ($projectIds->isEmpty()) {
            return collect();
        }

        $from = CarbonImmutable::createFromFormat('Y-m-d', $filters['periode'].'-01')->startOfDay();
        $to = $filters['as_of']->endOfDay();

        return ProjectTimeline::query()
            ->with(['project.mitra', 'actor'])
            ->whereIn('project_id', $projectIds->all())
            ->whereIn('type', ['system_log', 'comment'])
            ->whereBetween('created_at', [$from, $to])
            ->latest('created_at')
            ->latest('id')
            ->limit(12)
            ->get()
            ->map(function (ProjectTimeline $timeline) use ($viewer): array {
                $project = $timeline->project;
                $isComment = $timeline->type === 'comment';
                $title = $isComment
                    ? 'Komentar Project'
                    : (self::ACTIVITY_LABELS[$timeline->event_key] ?? 'Aktivitas Project');
                $url = $viewer->hasIzin('read_project_timeline')
                    ? route('projects.timeline.index', $project)
                    : ($viewer->hasIzin('read_project') ? route('projects.show', $project) : null);

                return [
                    'id' => (int) $timeline->id,
                    'project_id' => (int) $project->id,
                    'id_project' => (string) $project->id_project,
                    'project_name' => (string) $project->nama,
                    'mitra' => $project->mitra?->nama ?? 'Mitra tidak tersedia',
                    'type' => $timeline->type,
                    'event_key' => $timeline->event_key,
                    'title' => $title,
                    'body' => $isComment ? (string) $timeline->body : null,
                    'actor' => $timeline->actor?->name,
                    'occurred_at' => $timeline->created_at ?? CarbonImmutable::now(),
                    'url' => $url,
                ];
            })
            ->values();
    }

    /** @return array{periode:string,periode_label:string,as_of:string,points:array<int,array<string,mixed>>} */
    private function emptyTrend(array $filters): array
    {
        return [
            'periode' => $filters['periode'],
            'periode_label' => $filters['periode_label'],
            'as_of' => $filters['as_of']->toDateString(),
            'points' => [],
        ];
    }

    /** @return array<int, array{key:string,label:string,count:int,percent:float}> */
    private function emptyStatusDistribution(): array
    {
        return collect(self::RISK_STATUS_LABELS)
            ->map(fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
                'count' => 0,
                'percent' => 0.0,
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array{key:string,label:string,count:int,percent:float}> */
    private function emptyProjectStatusDistribution(): array
    {
        return collect(self::PROJECT_STATUS_LABELS)
            ->map(fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
                'count' => 0,
                'percent' => 0.0,
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function emptyKpis(): array
    {
        return [
            'active_projects' => 0,
            'verified_percent' => 0.0,
            'pending_percent' => 0.0,
            'plan_percent' => 0.0,
            'attention_projects' => 0,
            'baselined_projects' => 0,
            'active_rab_value' => 0.0,
            'spi' => null,
            'spi_label' => SpiThreshold::label(null),
            'spi_status' => SpiThreshold::status(null),
            'material_projects' => 0,
            'material_transit_projects' => 0,
            'material_readiness_percent' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function options(User $viewer, CarbonImmutable $now): array
    {
        return [
            'projects' => Project::query()->orderBy('id_project')->get(['id', 'id_project', 'nama']),
            'mitras' => $viewer->mitra_id === null
                ? Mitra::query()->orderBy('nama')->get(['id', 'nama'])
                : Mitra::query()->whereKey($viewer->mitra_id)->get(['id', 'nama']),
            'periodes' => $this->periodeOptions($now),
            'risikos' => $viewer->hasIzin('read_project_progress')
                ? self::RISK_LABELS
                : ['semua' => 'Status risiko membutuhkan izin Progres jasa'],
        ];
    }

    /**
     * Filter Project dan Mitra tetap dapat dipilih selama daftarnya masih dapat
     * dibaca; kalau justru daftar itu yang gagal, filter lain tetap utuh.
     *
     * @return array<string, mixed>
     */
    private function survivingOptions(User $viewer, CarbonImmutable $now): array
    {
        try {
            return $this->options($viewer, $now);
        } catch (Throwable $exception) {
            report($exception);

            return [
                'projects' => new EloquentCollection,
                'mitras' => new EloquentCollection,
                'periodes' => $this->periodeOptions($now),
                'risikos' => $viewer->hasIzin('read_project_progress')
                    ? self::RISK_LABELS
                    : ['semua' => 'Status risiko membutuhkan izin Progres jasa'],
            ];
        }
    }

    /** @return array<string, string> */
    private function periodeOptions(CarbonImmutable $now): array
    {
        $options = [];
        for ($offset = 0; $offset < 12; $offset++) {
            $month = $now->startOfMonth()->subMonths($offset);
            $options[$month->format('Y-m')] = $this->periodeLabel($month->format('Y-m'));
        }

        return $options;
    }

    private function periodeLabel(string $periode): string
    {
        [$year, $month] = array_map('intval', explode('-', $periode));

        return self::MONTHS[$month].' '.$year;
    }

    private function periode(mixed $value, CarbonImmutable $now): string
    {
        if (is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1) {
            return $value;
        }

        return $now->format('Y-m');
    }

    private function asOf(string $periode, CarbonImmutable $now): CarbonImmutable
    {
        $endOfPeriode = CarbonImmutable::createFromFormat('Y-m-d', $periode.'-01')->endOfMonth()->startOfDay();

        return $endOfPeriode->gt($now->startOfDay()) ? $now->startOfDay() : $endOfPeriode;
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = (int) $value;

        return $number > 0 ? $number : null;
    }

    private function percent(float $value, float $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return min(100.0, round(($value / $total) * 100, 2));
    }
}
