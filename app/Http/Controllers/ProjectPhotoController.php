<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\ProjectStep;
use App\Services\ProjectPhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProjectPhotoController extends Controller
{
    public function store(Request $request, Project $project, ProjectPhotoService $service): RedirectResponse
    {
        $data = $request->validate([
            'step' => ['required', 'string', Rule::in(array_keys(ProjectStep::STEPS))],
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => ['required', 'file', 'mimes:jpg,jpeg', 'mimetypes:image/jpeg', 'max:5120'],
        ]);

        $service->upload($project, $request->user(), $data['step'], $data['photos']);

        return redirect()->route('projects.show', $project)->with('status', 'Foto Pekerjaan berhasil diunggah.');
    }

    public function show(Project $project, int $photo): Response|RedirectResponse
    {
        $projectPhoto = ProjectPhoto::query()
            ->where('project_id', $project->id)
            ->findOrFail($photo);

        if (! Storage::disk('local')->exists($projectPhoto->stored_path)) {
            if ($projectPhoto->drive_url !== null) {
                return redirect()->away($projectPhoto->drive_url);
            }

            abort(404);
        }

        return response()->file(Storage::disk('local')->path($projectPhoto->stored_path), [
            'Content-Type' => $projectPhoto->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($projectPhoto->original_name).'"',
        ]);
    }
}
