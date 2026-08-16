<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectProgress;
use App\Models\ProjectRabJasa;
use App\Models\ProjectTimeline;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectProgressService
{
    public function __construct(private ProjectPlanningService $planning) {}

    public function submit(Project $project, User $actor, int $rabJasaId, string $actualDate, string|int|float $qty): ProjectProgress
    {
        if (! is_numeric($qty) || (float) $qty <= 0) {
            throw ValidationException::withMessages(['qty' => 'Qty progres harus lebih besar dari nol.']);
        }

        try {
            $date = CarbonImmutable::parse($actualDate)->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['actual_date' => 'Tanggal aktual pekerjaan tidak valid.']);
        }

        return DB::transaction(function () use ($project, $actor, $rabJasaId, $date, $qty): ProjectProgress {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            $rab = ProjectRabJasa::query()
                ->where('project_id', $project->id)
                ->lockForUpdate()
                ->find($rabJasaId);
            if ($rab === null) {
                throw ValidationException::withMessages(['project_rab_jasa_id' => 'Baris RAB Jasa tidak ditemukan pada Project ini.']);
            }

            $reported = (float) ProjectProgress::query()
                ->where('project_rab_jasa_id', $rab->id)
                ->whereIn('status', ['pending', 'verified'])
                ->sum('qty');
            $remaining = $this->planning->currentRabQuantity($rab) - $reported;
            if ((float) $qty > $remaining + 0.0005) {
                throw ValidationException::withMessages(['qty' => 'Qty progres melebihi sisa RAB Jasa.']);
            }

            $progress = ProjectProgress::query()->create([
                'mitra_id' => $project->mitra_id,
                'project_id' => $project->id,
                'project_rab_jasa_id' => $rab->id,
                'reported_by' => $actor->id,
                'actual_date' => $date,
                'qty' => number_format((float) $qty, 3, '.', ''),
                'status' => 'pending',
            ]);
            ProjectTimeline::recordSystem($project, $actor, 'progress_submitted', [
                'progress_id' => $progress->id,
                'status' => 'pending',
            ]);

            return $progress;
        });
    }

    public function verify(Project $project, ProjectProgress $progress, User $actor, bool $approved, ?string $note = null): ProjectProgress
    {
        return DB::transaction(function () use ($project, $progress, $actor, $approved, $note): ProjectProgress {
            $progress = ProjectProgress::query()
                ->where('project_id', $project->id)
                ->lockForUpdate()
                ->findOrFail($progress->id);
            if ($progress->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Progres sudah diputuskan.']);
            }

            if ($approved) {
                $rab = ProjectRabJasa::query()->lockForUpdate()->findOrFail($progress->project_rab_jasa_id);
                $otherReported = (float) ProjectProgress::query()
                    ->where('project_rab_jasa_id', $rab->id)
                    ->where('id', '!=', $progress->id)
                    ->whereIn('status', ['pending', 'verified'])
                    ->sum('qty');
                if ((float) $progress->qty + $otherReported > $this->planning->currentRabQuantity($rab) + 0.0005) {
                    throw ValidationException::withMessages(['status' => 'Progres tidak dapat diverifikasi karena melebihi sisa RAB Jasa.']);
                }
            }

            $status = $approved ? 'verified' : 'rejected';
            $progress->update([
                'status' => $status,
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'verification_note' => $note,
            ]);
            ProjectTimeline::recordSystem($project, $actor, $approved ? 'progress_verified' : 'progress_rejected', [
                'progress_id' => $progress->id,
                'status' => $status,
            ]);

            return $progress->fresh();
        });
    }
}
