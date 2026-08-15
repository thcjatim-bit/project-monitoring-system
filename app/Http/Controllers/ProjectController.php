<?php

namespace App\Http\Controllers;

use App\Queries\ProjectControlRoomQuery;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(): View
    {
        return view('projects.index', [
            'projects' => Project::query()->latest()->get(),
            'user' => request()->user(),
        ]);
    }

    public function create(): View
    {
        return view('projects.create', ['user' => request()->user()]);
    }

    public function show(Project $project, ProjectControlRoomQuery $query): View
    {
        return view('projects.show', $query->for($project, request()->query('as_of'), request()->user()));
    }

    public function store(Request $request): RedirectResponse
    {
        $project = Project::create($request->validate([
            'id_project' => ['required', 'string', 'max:255', 'unique:projects,id_project'],
            'nama' => ['required', 'string', 'max:255'],
            'mitra_id' => [Rule::requiredIf($request->user()->mitra_id === null), 'nullable', 'integer', 'exists:mitras,id'],
        ]));

        return redirect()->route('projects.index')->with('status', "Project {$project->id_project} dibuat.");
    }

    public function update(Request $request, int $projectId): RedirectResponse
    {
        $project = Project::query()->findOrFail($projectId);
        $project->update($request->validate([
            'nama' => ['required', 'string', 'max:255'],
        ]));

        return redirect()->route('projects.index')->with('status', "Project {$project->id_project} diperbarui.");
    }

    public function destroy(int $projectId): RedirectResponse
    {
        $project = Project::query()->findOrFail($projectId);
        $project->delete();

        return redirect()->route('projects.index')->with('status', 'Project dihapus.');
    }
}
