<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Rules\ActiveMaterial;
use App\Rules\WholeMaterialQty;
use App\Services\ProjectMaterialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectMaterialController extends Controller
{
    public function store(Request $request, Project $project, ProjectMaterialService $service): RedirectResponse
    {
        $data = $request->validate([
            'material_id' => ['required', 'integer', new ActiveMaterial],
            'qty' => ['required', 'numeric', 'gt:0', new WholeMaterialQty],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->addRequirement($project, $request->user(), $data);

        return redirect()->route('projects.show', $project)->with('status', 'Kebutuhan RAB Material ditambahkan.');
    }
}
