<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectRekon;
use App\Queries\ProjectRekonQuery;
use App\Services\ProjectRekonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectRekonController extends Controller
{
    public function index(Project $project, ProjectRekonQuery $query): View
    {
        return view('project-rekons.index', [
            'project' => $project,
            'readModel' => $query->forProject($project),
            'rekons' => ProjectRekon::query()
                ->where('project_id', $project->id)
                ->with('items.material.unit')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function show(ProjectRekon $projectRekon): View
    {
        return view('project-rekons.show', [
            'rekon' => $projectRekon->load(['project', 'items.material.unit', 'items.warehouse']),
        ]);
    }

    public function store(Request $request, Project $project, ProjectRekonService $service): RedirectResponse
    {
        $note = $request->validate(['catatan' => ['nullable', 'string', 'max:2000']])['catatan'] ?? null;
        $rekon = $service->open($project, $request->user(), 'manual');
        if ($note !== null) {
            $service->updateDraft($rekon, $request->user(), [], $note);
        }

        return redirect()->route('projects.rekons.index', $project)->with('status', 'Rekon Material berhasil dibuka.');
    }

    public function update(Request $request, ProjectRekon $projectRekon, ProjectRekonService $service): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['present', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.keluar_gudang' => ['required', 'numeric', 'min:0'],
            'items.*.terpasang' => ['required', 'numeric', 'min:0'],
            'items.*.sisa_project' => ['required', 'numeric', 'min:0'],
            'items.*.dikembalikan' => ['required', 'numeric', 'min:0'],
            'items.*.hilang_rusak' => ['required', 'numeric', 'min:0'],
            'items.*.kategori_hilang_rusak' => ['nullable', 'string', 'in:hilang,rusak,waste_wajar'],
            'items.*.penanggung_jawab' => ['required', 'string', 'in:mitra,thc'],
            'items.*.catatan' => ['nullable', 'string', 'max:2000'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);
        $service->updateDraft($projectRekon, $request->user(), $data['items'], $data['catatan'] ?? null);

        return redirect()->route('projects.rekons.index', $projectRekon->project_id)->with('status', 'Draft Rekon Material diperbarui.');
    }

    public function approve(Request $request, ProjectRekon $projectRekon, ProjectRekonService $service): RedirectResponse
    {
        $service->approve($projectRekon, $request->user());

        return redirect()->route('projects.rekons.index', $projectRekon->project_id)->with('status', 'Rekon Material disetujui.');
    }

    public function reject(Request $request, ProjectRekon $projectRekon, ProjectRekonService $service): RedirectResponse
    {
        $note = $request->validate(['catatan' => ['nullable', 'string', 'max:2000']])['catatan'] ?? null;
        $service->reject($projectRekon, $request->user(), $note);

        return redirect()->route('projects.rekons.index', $projectRekon->project_id)->with('status', 'Rekon Material ditolak.');
    }
}
