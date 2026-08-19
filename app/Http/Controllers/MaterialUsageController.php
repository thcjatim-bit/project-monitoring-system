<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\PemakaianMaterial;
use App\Models\Project;
use App\Models\Warehouse;
use App\Rules\ActiveMaterial;
use App\Services\MaterialUsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaterialUsageController extends Controller
{
    public function index(): View
    {
        return view('material-usages.index', $this->data());
    }

    public function projectIndex(Project $project): View
    {
        return view('material-usages.index', $this->data($project));
    }

    public function show(PemakaianMaterial $pemakaianMaterial): View
    {
        return view('material-usages.show', [
            'usage' => $pemakaianMaterial->load(['project', 'warehouse', 'material.unit', 'serialNumber', 'drum', 'requester', 'decider']),
        ]);
    }

    public function store(Request $request, Project $project, MaterialUsageService $service): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('mitra_id', $request->user()->mitra_id)],
            'material_id' => ['required', 'integer', new ActiveMaterial],
            'material_sn_id' => ['nullable', 'integer', 'exists:material_sns,id'],
            'drum_id' => ['nullable', 'integer', 'exists:drums,id'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'return_to_project' => ['sometimes', 'boolean'],
        ]);
        $service->submit($request->user(), $project, $data);

        if ($request->boolean('return_to_project')) {
            return redirect()->route('projects.material-usages.index', $project)->with('status', 'Pemakaian Material berhasil diajukan.');
        }

        return redirect()->route('material-usages.index')->with('status', 'Pemakaian Material berhasil diajukan.');
    }

    public function cancel(Request $request, PemakaianMaterial $pemakaianMaterial, MaterialUsageService $service): RedirectResponse
    {
        $service->cancel($pemakaianMaterial, $request->user());

        return redirect()->route('material-usages.index')->with('status', 'Pengajuan Pemakaian Material dibatalkan.');
    }

    public function approve(Request $request, PemakaianMaterial $pemakaianMaterial, MaterialUsageService $service): RedirectResponse
    {
        $note = $request->validate(['catatan' => ['nullable', 'string', 'max:2000']])['catatan'] ?? null;
        $service->decide($pemakaianMaterial, $request->user(), 'disetujui', $note);

        return redirect()->route('material-usages.index')->with('status', 'Pemakaian Material disetujui.');
    }

    public function reject(Request $request, PemakaianMaterial $pemakaianMaterial, MaterialUsageService $service): RedirectResponse
    {
        $note = $request->validate(['catatan' => ['nullable', 'string', 'max:2000']])['catatan'] ?? null;
        $service->decide($pemakaianMaterial, $request->user(), 'ditolak', $note);

        return redirect()->route('material-usages.index')->with('status', 'Pemakaian Material ditolak.');
    }

    /** @return array<string, mixed> */
    private function data(?Project $project = null): array
    {
        $user = request()->user();
        $projects = $project === null ? Project::query()->latest()->get() : collect([$project]);
        $warehouseQuery = Warehouse::query()->orderBy('nama');
        if ($user->mitra_id !== null) {
            $warehouseQuery->where('mitra_id', $user->mitra_id);
        }

        return [
            'project' => $project,
            'usages' => PemakaianMaterial::query()
                ->when($project !== null, fn ($query) => $query->where('project_id', $project->id))
                ->with(['project', 'warehouse', 'material.unit', 'requester', 'decider'])
                ->latest()
                ->get(),
            'projects' => $projects,
            'warehouses' => $warehouseQuery->get(),
            'materials' => Material::query()->with('unit')->activeWithUnit()->orderBy('nama')->get(),
        ];
    }
}
