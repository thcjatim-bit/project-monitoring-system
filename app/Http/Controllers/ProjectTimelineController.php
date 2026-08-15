<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectTimeline;
use App\Queries\ProjectControlRoomQuery;
use App\Services\ProjectCommentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectTimelineController extends Controller
{
    public function index(Request $request, Project $project, ProjectControlRoomQuery $query): \Illuminate\View\View
    {
        return view('projects.show', $query->for($project, $request->query('as_of'), $request->user()));
    }

    public function store(Request $request, Project $project, ProjectCommentService $service): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'internal' => ['sometimes', 'boolean'],
            'mentions' => ['sometimes', 'array', 'max:20'],
            'mentions.*' => ['integer'],
        ]);
        if (! empty($data['mentions'])) {
            abort_unless($request->user()->hasIzin('mention_project_user'), 403);
        }

        $service->create($project, $request->user(), $data['body'], (bool) ($data['internal'] ?? false), $data['mentions'] ?? []);

        return redirect()->route('projects.show', $project)->with('status', 'Komentar Project ditambahkan.');
    }

    public function update(Request $request, Project $project, int $timeline, ProjectCommentService $service): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $service->edit($project, ProjectTimeline::query()->findOrFail($timeline), $request->user(), $data['body']);

        return redirect()->route('projects.show', $project)->with('status', 'Komentar Project diperbarui dan ditandai edited.');
    }
}
