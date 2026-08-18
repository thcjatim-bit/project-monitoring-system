<?php

namespace App\Queries;

use App\Models\Mitra;
use App\Models\Project;
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

    private const MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function __construct(
        private ProjectCurveQuery $curveQuery,
        private ProjectMaterialReadinessQuery $materialQuery,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function for(User $viewer, array $input = [], ?CarbonImmutable $now = null): array
    {
        $now = $now ?? CarbonImmutable::now();
        $filters = $this->filters($input, $now);
        $canReadProgress = $viewer->hasIzin('read_project_progress');
        $canReadMaterial = $viewer->hasIzin('read_project_material') || $viewer->hasIzin('manage_project_material');

        $projects = Project::query()
            ->with('mitra')
            ->when($filters['project'] !== null, fn ($query) => $query->whereKey($filters['project']))
            ->when($filters['mitra'] !== null, fn ($query) => $query->where('mitra_id', $filters['mitra']))
            ->orderBy('id_project')
            ->get();

        $metrics = $projects
            ->filter(fn (Project $project): bool => $project->status_project === 'aktif')
            ->map(fn (Project $project): array => [
                'project' => $project,
                'curve' => $this->curveQuery->calculate($project, $filters['as_of'], $canReadProgress),
                'material' => $canReadMaterial ? $this->materialQuery->calculate($project) : null,
            ])
            ->values();

        $spiStatus = self::RISK_FILTER_TO_SPI_STATUS[$filters['risiko']];
        if ($spiStatus !== null) {
            $metrics = $metrics
                ->filter(fn (array $metric): bool => $metric['curve']['spi_status'] === $spiStatus)
                ->values();
        }

        return $this->payload(
            viewer: $viewer,
            filters: $filters,
            options: $this->options($viewer, $now),
            kpis: $this->kpis($metrics, $canReadProgress, $canReadMaterial),
            scopedProjectCount: $projects->count(),
            matchedProjectCount: $metrics->count(),
            canReadProgress: $canReadProgress,
            canReadMaterial: $canReadMaterial,
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

        return $this->payload(
            viewer: $viewer,
            filters: $this->filters($input, $now),
            options: $this->survivingOptions($viewer, $now),
            kpis: $this->emptyKpis(),
            scopedProjectCount: 0,
            matchedProjectCount: 0,
            canReadProgress: $viewer->hasIzin('read_project_progress'),
            canReadMaterial: $viewer->hasIzin('read_project_material') || $viewer->hasIzin('manage_project_material'),
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
            'risikos' => self::RISK_LABELS,
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
                'risikos' => self::RISK_LABELS,
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
