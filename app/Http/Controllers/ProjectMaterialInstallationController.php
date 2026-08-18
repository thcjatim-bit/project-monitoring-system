<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectMaterialInstallationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectMaterialInstallationController extends Controller
{
    public function store(Request $request, Project $project, ProjectMaterialInstallationService $service): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('mitra_id', $request->user()->mitra_id)],
            'material_id' => ['required', 'integer', 'exists:materials,id'],
            'material_sn_id' => ['nullable', 'integer', 'exists:material_sns,id'],
            'drum_id' => ['nullable', 'integer', 'exists:drums,id'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);
        $service->record($request->user(), $project, $data);

        return redirect()->route('projects.show', $project)->with('status', 'Material terpasang berhasil dicatat.');
    }
}
