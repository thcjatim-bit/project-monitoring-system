<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectStep;
use App\Models\ProjectTimeline;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectStepService
{
    public function move(Project $project, User $actor, string $stepKey, string $status = 'active'): ProjectStep
    {
        if (! isset(ProjectStep::STEPS[$stepKey])) {
            throw ValidationException::withMessages(['step' => 'Step Project tidak valid.']);
        }
        if (! in_array($status, ['active', 'completed'], true)) {
            throw ValidationException::withMessages(['status' => 'Status Step tidak valid.']);
        }

        return DB::transaction(function () use ($project, $actor, $stepKey, $status): ProjectStep {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            ProjectStep::initialize($project);
            $steps = ProjectStep::query()
                ->where('project_id', $project->id)
                ->orderBy('urutan')
                ->lockForUpdate()
                ->get();
            $target = $steps->firstWhere('step', $stepKey);
            if ($target === null) {
                throw ValidationException::withMessages(['step' => 'Step Project tidak ditemukan.']);
            }
            $previous = $steps->firstWhere('status', 'active')?->step;
            $now = now();

            foreach ($steps as $item) {
                if ($item->urutan < $target->urutan) {
                    $item->update(['status' => 'completed']);
                } elseif ($item->urutan > $target->urutan) {
                    $item->update(['status' => 'pending']);
                } else {
                    if ($status === 'completed') {
                        $item->update([
                            'status' => 'completed',
                            'completed_at' => $item->completed_at ?? $now,
                            'completed_by' => $item->completed_by ?? $actor->id,
                        ]);
                    } else {
                        $item->update(['status' => 'active']);
                    }
                }
            }

            if ($status === 'completed') {
                $next = $steps->first(fn (ProjectStep $item): bool => $item->urutan > $target->urutan);
                if ($next !== null) {
                    $next->update(['status' => 'active']);
                }
            }

            ProjectTimeline::recordSystem($project, $actor, 'step_changed', [
                'from' => $previous,
                'to' => $stepKey,
                'status' => $status,
            ]);

            return $target->fresh();
        });
    }
}
