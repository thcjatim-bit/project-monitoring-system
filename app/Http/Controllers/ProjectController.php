<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $project = Project::create($request->validate([
            'id_project' => ['required', 'string', 'max:255', 'unique:projects,id_project'],
            'nama' => ['required', 'string', 'max:255'],
            'mitra_id' => [Rule::requiredIf($request->user()->mitra_id === null), 'nullable', 'integer', 'exists:mitras,id'],
        ]));

        return redirect()->route('projects.index')->with('status', "Project {$project->id_project} dibuat.");
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $project->update($request->validate([
            'nama' => ['required', 'string', 'max:255'],
        ]));

        return redirect()->route('projects.index')->with('status', "Project {$project->id_project} diperbarui.");
    }

    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        return redirect()->route('projects.index')->with('status', 'Project dihapus.');
    }
}
