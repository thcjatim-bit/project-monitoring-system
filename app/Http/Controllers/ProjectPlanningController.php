<?php

namespace App\Http\Controllers;

use App\Models\MitraHargaJasa;
use App\Models\Project;
use App\Models\ProjectBaseline;
use App\Models\ProjectRabJasa;
use App\Models\ProjectVariationOrder;
use App\Services\ProjectPlanningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectPlanningController extends Controller
{
    public function index(Project $project): View
    {
        $viewer = request()->user();
        $canManage = $viewer->mitra_id === null && $viewer->hasIzin('manage_project_plan');

        return view('projects.planning', [
            'project' => $project->loadMissing('mitra'),
            'rabJasas' => ProjectRabJasa::query()
                ->where('project_id', $project->id)
                ->with('pekerjaanJasa')
                ->orderBy('id')
                ->get(),
            'prices' => $canManage
                ? MitraHargaJasa::query()
                    ->where('mitra_id', $project->mitra_id)
                    ->where('status', 'disetujui')
                    ->whereDate('berlaku_mulai', '<=', today())
                    ->with('pekerjaanJasa')
                    ->orderBy('pekerjaan_jasa_id')
                    ->get()
                : collect(),
            'baselines' => ProjectBaseline::query()
                ->where('project_id', $project->id)
                ->with('days')
                ->orderByDesc('version')
                ->get(),
            'variationOrders' => ProjectVariationOrder::query()
                ->where('project_id', $project->id)
                ->with(['items.rabJasa.pekerjaanJasa', 'items.hargaJasaMitra.pekerjaanJasa'])
                ->orderByDesc('id')
                ->get(),
            'canManage' => $canManage,
        ]);
    }

    public function storeRabJasa(Request $request, Project $project, ProjectPlanningService $service): RedirectResponse
    {
        $data = $request->validate([
            'harga_jasa_id' => ['required', 'integer'],
            'qty' => ['required', 'numeric', 'gt:0'],
        ]);
        $service->addRabJasa($project, $request->user(), (int) $data['harga_jasa_id'], $data['qty']);

        return redirect()->route('projects.show', $project)->with('status', 'Baris RAB Jasa ditambahkan.');
    }

    public function updatePlan(Request $request, Project $project, ProjectPlanningService $service): RedirectResponse
    {
        $data = $request->validate([
            'toc' => ['required', 'date'],
            'plan' => ['required', 'array', 'min:1'],
            'plan.*.date' => ['required', 'date'],
            'plan.*.percent' => ['required', 'numeric', 'between:0,100'],
        ]);
        $service->savePlan($project, $request->user(), $data['toc'], $data['plan']);

        return redirect()->route('projects.show', $project)->with('status', 'Baseline Project disimpan.');
    }

    public function storeVariationOrder(Request $request, Project $project, ProjectPlanningService $service): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.rab_jasa_id' => ['nullable', 'integer'],
            'items.*.harga_jasa_id' => ['nullable', 'integer'],
            'items.*.quantity_delta' => ['required', 'numeric', 'not_in:0'],
        ]);
        $service->createVariationOrder($project, $request->user(), $data['reason'], $data['items']);

        return redirect()->route('projects.show', $project)->with('status', 'Variation Order dibuat dan menunggu persetujuan.');
    }

    public function approveVariationOrder(Request $request, Project $project, ProjectVariationOrder $variationOrder, ProjectPlanningService $service): RedirectResponse
    {
        $service->approveVariationOrder($project, $variationOrder, $request->user());

        return redirect()->route('projects.show', $project)->with('status', 'Variation Order disetujui.');
    }
}
