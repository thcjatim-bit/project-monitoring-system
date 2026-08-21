<?php

namespace App\Http\Controllers;

use App\Models\MitraHargaJasa;
use App\Models\Project;
use App\Models\ProjectBaseline;
use App\Models\ProjectBaselineProposal;
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
        $canManage = ($viewer->mitra_id === null && $viewer->hasIzin('manage_project_plan'))
            || ($viewer->mitra_id !== null && $viewer->hasIzin('manage_mitra_project'));
        $canApproveVariationOrder = $viewer->mitra_id === null && $viewer->hasIzin('manage_project_plan');
        $canApproveBaselineProposal = $canApproveVariationOrder;
        $rabJasas = ProjectRabJasa::query()
            ->where('project_id', $project->id)
            ->with('pekerjaanJasa')
            ->orderBy('id')
            ->get();
        $prices = $canManage
            ? MitraHargaJasa::query()
                ->where('mitra_id', $project->mitra_id)
                ->where('status', 'disetujui')
                ->whereDate('berlaku_mulai', '<=', today())
                ->with('pekerjaanJasa')
                ->orderBy('pekerjaan_jasa_id')
                ->get()
            : collect();
        $baselines = ProjectBaseline::query()
            ->where('project_id', $project->id)
            ->with('days')
            ->orderByDesc('version')
            ->get();

        return view('projects.planning', [
            'project' => $project->loadMissing('mitra'),
            'rabJasas' => $rabJasas,
            'prices' => $prices,
            'priceOptions' => $prices->map(fn (MitraHargaJasa $price): array => [
                'value' => (string) $price->id,
                'label' => ($price->pekerjaanJasa?->nama ?? 'Pekerjaan Jasa').' · Rp '.number_format((float) $price->harga, 2, ',', '.'),
                'search' => ($price->pekerjaanJasa?->kode ?? '').' '.($price->pekerjaanJasa?->nama ?? ''),
            ]),
            'rabOptions' => $rabJasas->map(fn (ProjectRabJasa $rab): array => [
                'value' => (string) $rab->id,
                'label' => ($rab->pekerjaanJasa?->nama ?? 'Pekerjaan Jasa').' · qty '.number_format((float) $rab->qty, 3, '.', ''),
                'search' => ($rab->pekerjaanJasa?->kode ?? '').' '.($rab->pekerjaanJasa?->nama ?? ''),
            ]),
            'baselines' => $baselines,
            'baselineProposals' => ProjectBaselineProposal::query()
                ->where('project_id', $project->id)
                ->with('days')
                ->orderByDesc('id')
                ->get(),
            'variationOrders' => ProjectVariationOrder::query()
                ->where('project_id', $project->id)
                ->with(['items.rabJasa.pekerjaanJasa', 'items.hargaJasaMitra.pekerjaanJasa'])
                ->orderByDesc('id')
                ->get(),
            'canManage' => $canManage,
            'rabFrozen' => $baselines->isNotEmpty(),
            'canAddInitialRab' => $canManage && $baselines->isEmpty(),
            'canApproveVariationOrder' => $canApproveVariationOrder,
            'canApproveBaselineProposal' => $canApproveBaselineProposal,
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

        $message = $request->user()->mitra_id === null
            ? 'Baseline Project disimpan.'
            : 'Usulan Baseline Project diajukan untuk persetujuan THC.';

        return redirect()->route('projects.show', $project)->with('status', $message);
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

    public function approveBaselineProposal(Request $request, Project $project, ProjectBaselineProposal $proposal, ProjectPlanningService $service): RedirectResponse
    {
        $service->approveBaselineProposal($project, $proposal, $request->user());

        return redirect()->route('projects.show', $project)->with('status', 'Usulan Baseline Project disetujui.');
    }
}
