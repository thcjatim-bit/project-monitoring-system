<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\MaterialRequest;
use App\Models\Project;
use App\Services\MaterialRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaterialRequestController extends Controller
{
    public function index(): View
    {
        return view('material-requests.index', [
            'requests' => MaterialRequest::query()->with(['items.material.unit', 'requester', 'decider'])->latest()->get(),
            'materials' => Material::query()->with('unit')->where('aktif', true)->whereHas('unit', fn ($query) => $query->where('aktif', true))->orderBy('nama')->get(),
            'projects' => Project::query()->latest()->get(),
        ]);
    }

    public function show(MaterialRequest $materialRequest): View
    {
        return view('material-requests.show', [
            'materialRequest' => $materialRequest->load(['items.material.unit', 'mitra', 'project', 'requester', 'decider']),
        ]);
    }

    public function store(Request $request, MaterialRequestService $service): RedirectResponse
    {
        abort_unless($request->user()->mitra_id !== null, 403);

        $data = $request->validate([
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('mitra_id', $request->user()->mitra_id)],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'integer', Rule::exists('materials', 'id')->where('aktif', true)],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.catatan' => ['nullable', 'string', 'max:1000'],
        ]);
        $service->submit($request->user(), $data);

        return redirect()->route('material-requests.index')->with('status', 'Request Material berhasil diajukan.');
    }

    public function approve(Request $request, MaterialRequest $materialRequest, MaterialRequestService $service): RedirectResponse
    {
        $service->decide($materialRequest, $request->user(), 'disetujui', $request->validate(['catatan' => ['nullable', 'string', 'max:2000']])['catatan'] ?? null);

        return redirect()->route('material-requests.index')->with('status', 'Request Material disetujui.');
    }

    public function reject(Request $request, MaterialRequest $materialRequest, MaterialRequestService $service): RedirectResponse
    {
        $service->decide($materialRequest, $request->user(), 'ditolak', $request->validate(['catatan' => ['nullable', 'string', 'max:2000']])['catatan'] ?? null);

        return redirect()->route('material-requests.index')->with('status', 'Request Material ditolak.');
    }
}
