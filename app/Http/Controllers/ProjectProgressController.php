<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectProgress;
use App\Services\ProjectProgressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectProgressController extends Controller
{
    public function store(Request $request, Project $project, ProjectProgressService $service): RedirectResponse
    {
        $data = $request->validate([
            'project_rab_jasa_id' => ['required', 'integer'],
            'actual_date' => ['required', 'date'],
            'qty' => ['required', 'numeric', 'gt:0'],
        ]);
        $service->submit($project, $request->user(), (int) $data['project_rab_jasa_id'], $data['actual_date'], $data['qty']);

        return redirect()->route('projects.show', $project)->with('status', 'Progres jasa diajukan untuk verifikasi THC.');
    }

    public function verify(Request $request, Project $project, ProjectProgress $progress, ProjectProgressService $service): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $service->verify($project, $progress, $request->user(), true, $data['note'] ?? null);

        return redirect()->route('projects.show', $project)->with('status', 'Progres jasa diverifikasi.');
    }

    public function reject(Request $request, Project $project, ProjectProgress $progress, ProjectProgressService $service): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        $service->verify($project, $progress, $request->user(), false, $data['note']);

        return redirect()->route('projects.show', $project)->with('status', 'Progres jasa ditolak.');
    }
}
