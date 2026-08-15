<?php

namespace App\Queries;

use App\Models\Material;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\ProjectStep;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ProjectControlRoomQuery
{
    public function __construct(
        private ProjectCurveQuery $curveQuery,
        private ProjectMaterialReadinessQuery $materialQuery,
        private ProjectTimelineQuery $timelineQuery,
    ) {}

    /** @return array{project: Project, kpis: array<string, mixed>, steps: array<int, mixed>, timeline: array<int, mixed>, material: array<string, mixed>} */
    public function for(Project $project, CarbonInterface|string|null $asOf = null, ?User $viewer = null): array
    {
        ProjectStep::initialize($project);

        $canReadMaterial = $viewer === null
            || $viewer->hasIzin('read_project_material')
            || $viewer->hasIzin('manage_project_material');
        $material = $canReadMaterial
            ? $this->materialQuery->calculate($project)
            : [
                'required' => 0.0,
                'delivered' => 0.0,
                'transit' => 0.0,
                'available' => 0.0,
                'readiness_percent' => null,
                'state' => 'forbidden',
                'items' => [],
            ];
        $timeline = $viewer?->hasIzin('read_project_timeline')
            ? $this->timelineQuery->for($project, $viewer)
            : new Collection;

        return [
            'project' => $project->loadMissing('mitra'),
            'curve' => $this->curveQuery->calculate($project, $asOf),
            'kpis' => [
                'verified_progress' => null,
                'spi' => null,
                'material_readiness' => $material['readiness_percent'],
            ],
            'steps' => ProjectStep::query()->where('project_id', $project->id)->orderBy('urutan')->get(),
            'timeline' => $timeline,
            'material' => $material,
            'materials' => $viewer?->hasIzin('manage_project_material') || $viewer === null
                ? Material::query()->with('unit')->where('aktif', true)->orderBy('nama')->get()
                : new Collection,
            'photos' => ProjectPhoto::query()->with(['step', 'uploader'])->where('project_id', $project->id)->latest()->get(),
            'mentionableUsers' => $viewer?->hasIzin('mention_project_user')
                ? $this->timelineQuery->mentionableUsers($project)
                : new Collection,
        ];
    }
}
