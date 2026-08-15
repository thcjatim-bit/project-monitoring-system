<?php

namespace App\Queries;

use App\Models\Material;
use App\Models\Project;
use App\Models\ProjectStep;
use Carbon\CarbonInterface;

class ProjectControlRoomQuery
{
    public function __construct(
        private ProjectCurveQuery $curveQuery,
        private ProjectMaterialReadinessQuery $materialQuery,
    ) {}

    /** @return array{project: Project, kpis: array<string, mixed>, steps: array<int, mixed>, timeline: array<int, mixed>, material: array<string, mixed>} */
    public function for(Project $project, CarbonInterface|string|null $asOf = null): array
    {
        ProjectStep::initialize($project);

        $material = $this->materialQuery->calculate($project);

        return [
            'project' => $project->loadMissing('mitra'),
            'curve' => $this->curveQuery->calculate($project, $asOf),
            'kpis' => [
                'verified_progress' => null,
                'spi' => null,
                'material_readiness' => $material['readiness_percent'],
            ],
            'steps' => ProjectStep::query()->where('project_id', $project->id)->orderBy('urutan')->get(),
            'timeline' => [],
            'material' => $material,
            'materials' => Material::query()->with('unit')->where('aktif', true)->orderBy('nama')->get(),
        ];
    }
}
