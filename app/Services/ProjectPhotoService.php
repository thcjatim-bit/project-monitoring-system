<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\ProjectStep;
use App\Models\ProjectTimeline;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProjectPhotoService
{
    /** @param array<int, UploadedFile> $files */
    public function upload(Project $project, User $actor, string $stepKey, array $files): array
    {
        $step = ProjectStep::query()
            ->where('project_id', $project->id)
            ->where('step', $stepKey)
            ->first();
        if ($step === null) {
            throw ValidationException::withMessages(['step' => 'Step Project tidak ditemukan.']);
        }

        $storedPaths = [];

        try {
            return DB::transaction(function () use ($project, $actor, $step, $files, &$storedPaths): array {
                $photos = [];
                $date = CarbonImmutable::now()->toDateString();
                $directory = 'project-photos/'.$project->id_project.'/'.$step->step.'/'.$date;

                foreach ($files as $file) {
                    $path = $file->storeAs($directory, str()->uuid().'.jpg', 'local');
                    $storedPaths[] = $path;
                    $dimensions = @getimagesize($file->getRealPath());
                    $photo = ProjectPhoto::query()->create([
                        'mitra_id' => $project->mitra_id,
                        'project_id' => $project->id,
                        'project_step_id' => $step->id,
                        'uploaded_by' => $actor->id,
                        'original_name' => $file->getClientOriginalName(),
                        'stored_path' => $path,
                        'mime_type' => 'image/jpeg',
                        'original_size' => $file->getSize(),
                        'width' => $dimensions[0] ?? null,
                        'height' => $dimensions[1] ?? null,
                        'capture_date' => $date,
                        'sync_status' => 'pending',
                    ]);
                    $photos[] = $photo;
                }

                ProjectTimeline::recordSystem($project, $actor, 'photo_uploaded', [
                    'project_step_id' => $step->id,
                    'photo_ids' => collect($photos)->pluck('id')->all(),
                ]);

                return $photos;
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }
    }

    public function markSyncFailed(ProjectPhoto $photo, string $reason): ProjectPhoto
    {
        $photo->update([
            'sync_status' => 'failed',
            'sync_error' => $reason,
        ]);

        return $photo->fresh();
    }
}
