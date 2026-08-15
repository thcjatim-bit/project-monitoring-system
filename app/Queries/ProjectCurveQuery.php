<?php

namespace App\Queries;

use App\Models\Project;
use App\Models\ProjectBaseline;
use App\Models\ProjectProgress;
use App\Models\ProjectRabJasa;
use App\Models\ProjectVariationOrderItem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ProjectCurveQuery
{
    /** @return array<string, mixed> */
    public function calculate(Project $project, CarbonInterface|string|null $asOf = null): array
    {
        $asOfDate = $asOf instanceof CarbonInterface
            ? CarbonImmutable::instance($asOf)
            : ($asOf === null ? CarbonImmutable::today() : CarbonImmutable::parse($asOf));
        $rabLines = ProjectRabJasa::query()->where('project_id', $project->id)->get();
        $grandTotal = (float) $rabLines->sum('total_nilai');

        $adjustments = ProjectVariationOrderItem::query()
            ->where('status', 'applied')
            ->whereHas('variationOrder', fn ($query) => $query->where('project_id', $project->id))
            ->with('rabJasa')
            ->get();
        foreach ($adjustments as $adjustment) {
            if ($adjustment->rabJasa?->variation_order_id === null) {
                $grandTotal += (float) $adjustment->quantity_delta * (float) $adjustment->harga_satuan;
            }
        }
        $grandTotal = max(0, round($grandTotal, 2));

        $progresses = ProjectProgress::query()
            ->where('project_id', $project->id)
            ->whereIn('status', ['pending', 'verified'])
            ->with('rabJasa')
            ->orderBy('actual_date')
            ->get();
        $verifiedByDate = [];
        $pendingByDate = [];
        foreach ($progresses as $progress) {
            $value = (float) $progress->qty * (float) ($progress->rabJasa?->harga_satuan ?? 0);
            $bucket = $progress->status === 'verified' ? 'verifiedByDate' : 'pendingByDate';
            ${$bucket}[$progress->actual_date->toDateString()] = (${$bucket}[$progress->actual_date->toDateString()] ?? 0) + $value;
        }

        $verifiedTotal = array_sum($verifiedByDate);
        $pendingTotal = array_sum($pendingByDate);
        $verifiedPercent = $this->percent($verifiedTotal, $grandTotal);
        $pendingPercent = $this->percent($pendingTotal, $grandTotal);

        $baselines = ProjectBaseline::query()
            ->where('project_id', $project->id)
            ->with('days')
            ->orderBy('version')
            ->get();
        $original = $baselines->where('kind', 'original')->last();
        $revised = $baselines->where('kind', 'revised')->last();
        $active = $revised ?? $original;
        $planPercent = $this->planPercent($active, $asOfDate);
        $overdue = $active !== null && $asOfDate->startOfDay()->gt($active->toc->startOfDay());
        $baselineFlatAfterToc = $overdue && $revised === null;
        if ($baselineFlatAfterToc) {
            $planPercent = 100.0;
        }

        $spi = $planPercent > 0 ? round($verifiedPercent / $planPercent, 4) : null;
        $spiStatus = $spi === null ? 'na' : ($spi >= 1 ? 'green' : ($spi >= 0.9 ? 'yellow' : 'red'));
        $latestProgressDate = collect(array_keys($verifiedByDate + $pendingByDate))->max();
        $xAxisEnd = $asOfDate;
        if ($project->toc !== null && $project->toc->gt($xAxisEnd)) {
            $xAxisEnd = CarbonImmutable::instance($project->toc);
        }
        if ($latestProgressDate !== null && CarbonImmutable::parse($latestProgressDate)->gt($xAxisEnd)) {
            $xAxisEnd = CarbonImmutable::parse($latestProgressDate);
        }

        return [
            'as_of' => $asOfDate->toDateString(),
            'grand_total_rab_jasa' => $grandTotal,
            'verified_percent' => $verifiedPercent,
            'pending_percent' => $pendingPercent,
            'plan_percent' => $planPercent,
            'spi' => $spi,
            'spi_label' => $spi === null ? 'N/A' : number_format($spi, 2, '.', ''),
            'spi_status' => $spiStatus,
            'verified_series' => $this->series($verifiedByDate, $grandTotal),
            'pending_series' => $this->series($pendingByDate, $grandTotal),
            'baseline_series' => $this->baselineSeries($active, $xAxisEnd, $baselineFlatAfterToc),
            'original_baseline' => $this->baselinePayload($original),
            'revised_baseline' => $this->baselinePayload($revised),
            'overdue' => $overdue,
            'baseline_flat_after_toc' => $baselineFlatAfterToc,
            'x_axis_end' => $xAxisEnd->toDateString(),
        ];
    }

    private function percent(float $value, float $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return min(100.0, round(($value / $total) * 100, 2));
    }

    private function planPercent(?ProjectBaseline $baseline, CarbonInterface $asOf): float
    {
        if ($baseline === null) {
            return 0.0;
        }

        $day = $baseline->days
            ->filter(fn ($item): bool => $item->plan_date->startOfDay()->lte($asOf->startOfDay()))
            ->sortBy('plan_date')
            ->last();

        return $day === null ? 0.0 : (float) $day->cumulative_percent;
    }

    /** @return array<int, array{date:string,percent:float}> */
    private function series(array $values, float $total): array
    {
        $cumulative = 0.0;
        $series = [];
        foreach ($values as $date => $value) {
            $cumulative += $value;
            $series[] = ['date' => $date, 'percent' => $this->percent($cumulative, $total)];
        }

        return $series;
    }

    /** @return array<int, array{date:string,percent:float}> */
    private function baselineSeries(?ProjectBaseline $baseline, CarbonInterface $xAxisEnd, bool $flatAfterToc): array
    {
        if ($baseline === null) {
            return [];
        }

        $series = $baseline->days
            ->sortBy('plan_date')
            ->map(fn ($day): array => ['date' => $day->plan_date->toDateString(), 'percent' => (float) $day->cumulative_percent])
            ->values()
            ->all();
        if ($flatAfterToc && $xAxisEnd->gt($baseline->toc->startOfDay())) {
            $series[] = ['date' => $xAxisEnd->toDateString(), 'percent' => 100.0];
        }

        return $series;
    }

    /** @return array{kind:string,version:int,toc:string}|null */
    private function baselinePayload(?ProjectBaseline $baseline): ?array
    {
        if ($baseline === null) {
            return null;
        }

        return [
            'kind' => $baseline->kind,
            'version' => (int) $baseline->version,
            'toc' => $baseline->toc->toDateString(),
        ];
    }
}
