<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectStepService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectStepController extends Controller
{
    public function update(Request $request, Project $project, ProjectStepService $service): RedirectResponse
    {
        $data = $request->validate([
            'step' => ['required', 'string'],
            'status' => ['sometimes', 'string', 'in:active,completed'],
        ]);
        $service->move($project, $request->user(), $data['step'], $data['status'] ?? 'active');

        return redirect()->route('projects.show', $project)->with('status', 'Step Project diperbarui.');
    }
}
