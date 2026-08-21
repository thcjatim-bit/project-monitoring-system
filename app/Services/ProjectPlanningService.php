<?php

namespace App\Services;

use App\Models\MitraHargaJasa;
use App\Models\Project;
use App\Models\ProjectBaseline;
use App\Models\ProjectBaselineDay;
use App\Models\ProjectBaselineProposal;
use App\Models\ProjectBaselineProposalDay;
use App\Models\ProjectRabJasa;
use App\Models\ProjectTimeline;
use App\Models\ProjectVariationOrder;
use App\Models\ProjectVariationOrderItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectPlanningService
{
    public function __construct(private MitraPriceBook $priceBook) {}

    public function addRabJasa(Project $project, User $actor, int $hargaJasaId, string|int|float $qty): ProjectRabJasa
    {
        $this->assertProjectAccess($project, $actor);
        $quantity = $this->positiveQuantity($qty);

        return DB::transaction(function () use ($project, $actor, $hargaJasaId, $quantity): ProjectRabJasa {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            $price = $this->priceBook->effectiveFor($project, $hargaJasaId, CarbonImmutable::today());

            $unitPrice = (string) $price->harga;

            return ProjectRabJasa::query()->create([
                'mitra_id' => $project->mitra_id,
                'project_id' => $project->id,
                'pekerjaan_jasa_id' => $price->pekerjaan_jasa_id,
                'harga_jasa_mitra_id' => $price->id,
                'qty' => $quantity,
                'harga_satuan' => $unitPrice,
                'total_nilai' => $this->money((float) $quantity * (float) $unitPrice),
                'dibuat_oleh' => $actor->id,
            ]);
        });
    }

    /** @param array<int, array{date:string,percent:string|int|float}> $planDays */
    public function savePlan(Project $project, User $actor, string $toc, array $planDays): ProjectBaseline|ProjectBaselineProposal
    {
        $this->assertProjectAccess($project, $actor);
        if ($planDays === []) {
            throw ValidationException::withMessages(['plan' => 'Baseline membutuhkan minimal satu rencana harian.']);
        }

        $tocDate = $this->date($toc, 'toc');
        $normalizedDays = collect($planDays)
            ->map(function (array $day): array {
                $date = $this->date((string) ($day['date'] ?? ''), 'plan');
                $percent = (float) ($day['percent'] ?? -1);
                if ($percent < 0 || $percent > 100) {
                    throw ValidationException::withMessages(['plan' => 'Persen baseline harus berada di antara 0 dan 100.']);
                }

                return ['date' => $date, 'percent' => $percent];
            })
            ->sortBy('date')
            ->values();

        if ((float) $normalizedDays->last()['percent'] !== 100.0) {
            throw ValidationException::withMessages(['plan' => 'Hari terakhir baseline harus mencapai 100%.']);
        }

        if ($actor->mitra_id !== null) {
            return DB::transaction(function () use ($project, $actor, $tocDate, $normalizedDays): ProjectBaselineProposal {
                $project = Project::query()->lockForUpdate()->findOrFail($project->id);
                $proposal = ProjectBaselineProposal::query()->create([
                    'mitra_id' => $project->mitra_id,
                    'project_id' => $project->id,
                    'status' => 'diajukan',
                    'toc' => $tocDate,
                    'diajukan_oleh' => $actor->id,
                ]);

                foreach ($normalizedDays as $day) {
                    ProjectBaselineProposalDay::query()->create([
                        'mitra_id' => $project->mitra_id,
                        'project_baseline_proposal_id' => $proposal->id,
                        'plan_date' => $day['date'],
                        'cumulative_percent' => number_format($day['percent'], 3, '.', ''),
                    ]);
                }

                ProjectTimeline::recordSystem($project, $actor, 'baseline_proposal_submitted', [
                    'baseline_proposal_id' => $proposal->id,
                    'toc' => $tocDate,
                ]);

                return $proposal->load('days');
            });
        }

        return DB::transaction(function () use ($project, $actor, $tocDate, $normalizedDays): ProjectBaseline {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            $previous = ProjectBaseline::query()
                ->where('project_id', $project->id)
                ->orderByDesc('version')
                ->lockForUpdate()
                ->first();
            $version = ($previous?->version ?? 0) + 1;

            $baseline = ProjectBaseline::query()->create([
                'mitra_id' => $project->mitra_id,
                'project_id' => $project->id,
                'kind' => $previous === null ? 'original' : 'revised',
                'version' => $version,
                'toc' => $tocDate,
                'supersedes_id' => $previous?->id,
                'dibuat_oleh' => $actor->id,
            ]);

            foreach ($normalizedDays as $day) {
                ProjectBaselineDay::query()->create([
                    'mitra_id' => $project->mitra_id,
                    'project_baseline_id' => $baseline->id,
                    'plan_date' => $day['date'],
                    'cumulative_percent' => number_format($day['percent'], 3, '.', ''),
                ]);
            }

            $project->update(['toc' => $tocDate]);
            ProjectTimeline::recordSystem($project, $actor, 'toc_changed', [
                'toc' => $tocDate,
                'baseline_id' => $baseline->id,
                'baseline_kind' => $baseline->kind,
            ]);

            return $baseline->load('days');
        });
    }

    public function approveBaselineProposal(Project $project, ProjectBaselineProposal $proposal, User $actor): ProjectBaseline
    {
        abort_unless($actor->mitra_id === null && $actor->hasIzin('manage_project_plan'), 403);

        return DB::transaction(function () use ($project, $proposal, $actor): ProjectBaseline {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            $proposal = ProjectBaselineProposal::query()
                ->where('project_id', $project->id)
                ->with('days')
                ->lockForUpdate()
                ->findOrFail($proposal->id);
            if ($proposal->status !== 'diajukan') {
                throw ValidationException::withMessages(['status' => 'Usulan Baseline sudah diputuskan.']);
            }

            $previous = ProjectBaseline::query()
                ->where('project_id', $project->id)
                ->orderByDesc('version')
                ->lockForUpdate()
                ->first();
            $baseline = ProjectBaseline::query()->create([
                'mitra_id' => $project->mitra_id,
                'project_id' => $project->id,
                'kind' => $previous === null ? 'original' : 'revised',
                'version' => ($previous?->version ?? 0) + 1,
                'toc' => $proposal->toc,
                'supersedes_id' => $previous?->id,
                'dibuat_oleh' => $actor->id,
            ]);

            foreach ($proposal->days as $day) {
                ProjectBaselineDay::query()->create([
                    'mitra_id' => $project->mitra_id,
                    'project_baseline_id' => $baseline->id,
                    'plan_date' => $day->plan_date,
                    'cumulative_percent' => $day->cumulative_percent,
                ]);
            }

            $proposal->update([
                'status' => 'disetujui',
                'diputuskan_oleh' => $actor->id,
                'diputuskan_at' => now(),
            ]);
            $project->update(['toc' => $proposal->toc]);
            ProjectTimeline::recordSystem($project, $actor, 'baseline_proposal_approved', [
                'baseline_proposal_id' => $proposal->id,
                'baseline_id' => $baseline->id,
                'baseline_kind' => $baseline->kind,
            ]);

            return $baseline->load('days');
        });
    }

    /** @param array<int, array{rab_jasa_id?:int|null,harga_jasa_id?:int|null,quantity_delta:string|int|float}> $items */
    public function createVariationOrder(Project $project, User $actor, string $reason, array $items): ProjectVariationOrder
    {
        $this->assertProjectAccess($project, $actor);
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Variation Order membutuhkan minimal satu baris.']);
        }

        return DB::transaction(function () use ($project, $actor, $reason, $items): ProjectVariationOrder {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            $number = 'VO-'.$project->id.'-'.(ProjectVariationOrder::query()->where('project_id', $project->id)->count() + 1);
            $variation = ProjectVariationOrder::query()->create([
                'mitra_id' => $project->mitra_id,
                'project_id' => $project->id,
                'nomor' => $number,
                'status' => 'draft',
                'alasan' => $reason,
                'dibuat_oleh' => $actor->id,
            ]);

            foreach ($items as $item) {
                $delta = (float) ($item['quantity_delta'] ?? 0);
                if (abs($delta) < 0.0005) {
                    throw ValidationException::withMessages(['items' => 'Perubahan qty Variation Order tidak boleh nol.']);
                }

                $rab = null;
                $price = null;
                if (! empty($item['rab_jasa_id'])) {
                    $rab = ProjectRabJasa::query()
                        ->where('project_id', $project->id)
                        ->find($item['rab_jasa_id']);
                    if ($rab === null) {
                        throw ValidationException::withMessages(['items' => 'Baris RAB Jasa tidak ditemukan pada Project ini.']);
                    }
                    $price = $rab->hargaJasaMitra;
                    if ($delta < 0 && $this->currentRabQuantity($rab) + $delta < -0.0005) {
                        throw ValidationException::withMessages(['items' => 'Pengurangan Variation Order melebihi qty RAB Jasa yang tersedia.']);
                    }
                } elseif ($delta > 0 && ! empty($item['harga_jasa_id'])) {
                    try {
                        $price = $this->priceBook->effectiveFor($project, (int) $item['harga_jasa_id'], CarbonImmutable::today());
                    } catch (ValidationException) {
                        throw ValidationException::withMessages(['items' => 'Penambahan RAB hanya boleh memakai Harga Jasa Mitra yang disetujui dan berlaku.']);
                    }
                } else {
                    throw ValidationException::withMessages(['items' => 'Baris Variation Order harus menunjuk RAB atau Harga Jasa Mitra baru.']);
                }

                ProjectVariationOrderItem::query()->create([
                    'mitra_id' => $project->mitra_id,
                    'project_variation_order_id' => $variation->id,
                    'rab_jasa_id' => $rab?->id,
                    'pekerjaan_jasa_id' => $rab?->pekerjaan_jasa_id ?? $price?->pekerjaan_jasa_id,
                    'harga_jasa_mitra_id' => $price?->id,
                    'quantity_delta' => number_format($delta, 3, '.', ''),
                    'harga_satuan' => $rab?->harga_satuan ?? $price?->harga,
                    'status' => 'pending',
                ]);
            }

            ProjectTimeline::recordSystem($project, $actor, 'variation_order_created', [
                'variation_order_id' => $variation->id,
            ]);

            return $variation->load('items');
        });
    }

    public function approveVariationOrder(Project $project, ProjectVariationOrder $variation, User $actor): ProjectVariationOrder
    {
        abort_unless($actor->mitra_id === null && $actor->hasIzin('manage_project_plan'), 403);

        return DB::transaction(function () use ($project, $variation, $actor): ProjectVariationOrder {
            $variation = ProjectVariationOrder::query()
                ->where('project_id', $project->id)
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($variation->id);
            if ($variation->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Variation Order sudah diputuskan.']);
            }

            foreach ($variation->items as $item) {
                if ((float) $item->quantity_delta > 0 && $item->rab_jasa_id === null) {
                    $rab = ProjectRabJasa::query()->create([
                        'mitra_id' => $project->mitra_id,
                        'project_id' => $project->id,
                        'pekerjaan_jasa_id' => $item->pekerjaan_jasa_id,
                        'harga_jasa_mitra_id' => $item->harga_jasa_mitra_id,
                        'variation_order_id' => $variation->id,
                        'qty' => $item->quantity_delta,
                        'harga_satuan' => $item->harga_satuan,
                        'total_nilai' => $this->money((float) $item->quantity_delta * (float) $item->harga_satuan),
                        'dibuat_oleh' => $actor->id,
                    ]);
                    $item->update(['rab_jasa_id' => $rab->id]);
                }
                $item->update(['status' => 'applied']);
            }

            $variation->update([
                'status' => 'approved',
                'disetujui_oleh' => $actor->id,
                'disetujui_at' => now(),
            ]);
            ProjectTimeline::recordSystem($project, $actor, 'variation_order_approved', [
                'variation_order_id' => $variation->id,
            ]);

            return $variation->fresh('items');
        });
    }

    public function currentRabQuantity(ProjectRabJasa $rab): float
    {
        $adjustments = ProjectVariationOrderItem::query()
            ->where('rab_jasa_id', $rab->id)
            ->where('status', 'applied')
            ->when($rab->variation_order_id !== null, fn ($query) => $query->where('project_variation_order_id', '!=', $rab->variation_order_id))
            ->sum('quantity_delta');

        return (float) $rab->qty + (float) $adjustments;
    }

    private function positiveQuantity(string|int|float $qty): string
    {
        if (! is_numeric($qty) || (float) $qty <= 0) {
            throw ValidationException::withMessages(['qty' => 'Qty harus lebih besar dari nol.']);
        }

        return number_format((float) $qty, 3, '.', '');
    }

    private function date(string $value, string $field): string
    {
        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => 'Tanggal tidak valid.']);
        }
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function assertProjectAccess(Project $project, User $actor): void
    {
        $permission = $actor->mitra_id === null ? 'manage_project_plan' : 'manage_mitra_project';
        abort_unless($actor->hasIzin($permission), 403);
        if ($actor->mitra_id !== null) {
            abort_unless((int) $project->mitra_id === (int) $actor->mitra_id, 404);
        }
    }
}
