<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\PemakaianMaterial;
use App\Models\Project;
use App\Services\MaterialUsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaterialUsageController extends Controller
{
    public function index(): View
    {
        return view('material-usages.index', [
            'usages' => PemakaianMaterial::query()->with(['project', 'warehouse', 'material.unit', 'requester', 'decider'])->latest()->get(),
            'projects' => Project::query()->latest()->get(),
            'materials' => Material::query()->with('unit')->where('aktif', true)->whereHas('unit', fn ($query) => $query->where('aktif', true))->orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request, Project $project, MaterialUsageService $service): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('mitra_id', $request->user()->mitra_id)],
            'material_id' => ['required', 'integer', Rule::exists('materials', 'id')->where('aktif', true)],
            'material_sn_id' => ['nullable', 'integer', 'exists:material_sns,id'],
            'drum_id' => ['nullable', 'integer', 'exists:drums,id'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);
        $service->submit($request->user(), $project, $data);

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
}
