<?php

namespace App\Queries;

use App\Models\Drum;
use App\Models\Material;
use App\Models\MaterialSn;
use App\Models\MaterialStok;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\ProjectProgress;
use App\Models\ProjectRabJasa;
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

    /** @return array<string, mixed> */
    public function for(Project $project, CarbonInterface|string|null $asOf = null, ?User $viewer = null): array
    {

        $canReadMaterial = $viewer === null
            || $viewer->hasIzin('read_project_material')
            || $viewer->hasIzin('manage_project_material');
        $canReadProgress = $viewer === null || $viewer->hasIzin('read_project_progress');
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
        $progresses = $canReadProgress
            ? ProjectProgress::query()
                ->where('project_id', $project->id)
                ->with(['rabJasa.pekerjaanJasa', 'reporter', 'verifier'])
                ->latest('actual_date')
                ->latest('id')
                ->get()
            : new Collection;
        $rabJasas = $canReadProgress
            ? ProjectRabJasa::query()
                ->where('project_id', $project->id)
                ->with('pekerjaanJasa')
                ->orderBy('id')
                ->get()
            : new Collection;
        $canReportInstallation = $viewer !== null
            && $viewer->mitra_id !== null
            && (int) $viewer->mitra_id === (int) $project->mitra_id
            && $viewer->hasIzin('report_project_progress');
        $installationStocks = $canReportInstallation
            ? MaterialStok::query()
                ->where('lokasi_tipe', 'project')
                ->where('lokasi_id', $project->id)
                ->where('qty', '>', 0)
                ->with(['warehouse', 'material.unit'])
                ->orderBy('material_id')
                ->get()
            : new Collection;
        $installationSerials = $canReportInstallation
            ? MaterialSn::query()
                ->where('lokasi_tipe', 'project')
                ->where('lokasi_id', $project->id)
                ->with('material')
                ->orderBy('serial_number')
                ->get()
            : new Collection;
        $installationDrums = $canReportInstallation
            ? Drum::query()
                ->where('lokasi_tipe', 'project')
                ->where('lokasi_id', $project->id)
                ->where('sisa', '>', 0)
                ->with('material')
                ->orderBy('drum_id')
                ->get()
            : new Collection;

        return [
            'project' => $project->loadMissing('mitra'),
            'curve' => $this->curveQuery->calculate($project, $asOf, $canReadProgress),
            'kpis' => [
                'verified_progress' => null,
                'spi' => null,
                'material_readiness' => $material['readiness_percent'],
            ],
            'steps' => $project->steps,
            'timeline' => $timeline,
            'progresses' => $progresses,
            'rabJasas' => $rabJasas,
            'canReportInstallation' => $canReportInstallation,
            'installationStocks' => $installationStocks,
            'installationSerials' => $installationSerials,
            'installationDrums' => $installationDrums,
            'canReadProgress' => $canReadProgress,
            'material' => $material,
            'materials' => $viewer?->hasIzin('manage_project_material') || $viewer === null
                ? Material::query()->with('unit')->activeWithUnit()->orderBy('nama')->get()
                : new Collection,
            'photos' => ProjectPhoto::query()->with(['step', 'uploader'])->where('project_id', $project->id)->latest()->get(),
            'mentionableUsers' => $viewer?->hasIzin('mention_project_user')
                ? $this->timelineQuery->mentionableUsers($project)
                : new Collection,
            'controlRoomError' => false,
        ];
    }

    /** @return array<string, mixed> */
    public function errorState(Project $project): array
    {
        $today = now()->toDateString();

        return [
            'project' => $project->loadMissing('mitra'),
            'curve' => [
                'as_of' => $today,
                'grand_total_rab_jasa' => 0.0,
                'verified_percent' => 0.0,
                'pending_percent' => 0.0,
                'plan_percent' => 0.0,
                'spi_label' => 'N/A',
                'spi_status' => 'na',
                'verified_series' => [],
                'pending_series' => [],
                'pending_shadow_series' => [],
                'baseline_series' => [],
                'original_baseline_series' => [],
                'revised_baseline_series' => [],
                'original_baseline' => null,
                'revised_baseline' => null,
                'active_baseline_kind' => null,
                'overdue' => false,
                'baseline_flat_after_toc' => false,
                'x_axis_end' => $today,
            ],
            'kpis' => [
                'verified_progress' => null,
                'spi' => null,
                'material_readiness' => null,
            ],
            'steps' => new Collection,
            'timeline' => new Collection,
            'progresses' => new Collection,
            'rabJasas' => new Collection,
            'canReportInstallation' => false,
            'installationStocks' => new Collection,
            'installationSerials' => new Collection,
            'installationDrums' => new Collection,
            'canReadProgress' => false,
            'material' => [
                'required' => 0.0,
                'delivered' => 0.0,
                'transit' => 0.0,
                'available' => 0.0,
                'readiness_percent' => null,
                'state' => 'error',
                'items' => [],
            ],
            'materials' => new Collection,
            'photos' => new Collection,
            'mentionableUsers' => new Collection,
            'controlRoomError' => true,
        ];
    }
}
