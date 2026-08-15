<?php

namespace App\Queries;

use App\Models\Project;
use App\Models\ProjectStep;
use Carbon\CarbonInterface;

class ProjectControlRoomQuery
{
    public function __construct(private ProjectCurveQuery $curveQuery) {}

    /** @return array{project: Project, kpis: array<string, mixed>, steps: array<int, mixed>, timeline: array<int, mixed>, material: array<string, mixed>} */
    public function for(Project $project, CarbonInterface|string|null $asOf = null): array
    {
        ProjectStep::initialize($project);

        return [
            'project' => $project->loadMissing('mitra'),
            'curve' => $this->curveQuery->calculate($project, $asOf),
            'kpis' => [
                'verified_progress' => null,
                'spi' => null,
                'material_readiness' => null,
            ],
            'steps' => ProjectStep::query()->where('project_id', $project->id)->orderBy('urutan')->get(),
            'timeline' => [],
            'material' => [
                'required' => null,
                'delivered' => null,
                'available' => null,
                'state' => 'empty',
            ],
        ];
    }
}
