<?php

namespace App\Services;

use App\Contracts\PhotoSyncPort;
use App\Models\ProjectPhoto;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProjectPhotoSyncService
{
    public function __construct(private readonly PhotoSyncPort $photoSync) {}

    public function destinationFor(ProjectPhoto $photo): string
    {
        $projectId = $photo->project?->id_project;
        $step = $photo->step?->step;
        $captureDate = $photo->capture_date ?? $photo->created_at;

        if (! is_string($projectId) || $projectId === '') {
            throw new RuntimeException('Project photo is missing its project ID.');
        }

        if (! is_string($step) || $step === '') {
            throw new RuntimeException('Project photo is missing its project step.');
        }

        if ($captureDate instanceof CarbonInterface || $captureDate instanceof DateTimeInterface) {
            $captureDate = $captureDate->format('Y-m-d');
        }

        if (! is_string($captureDate) || $captureDate === '') {
            throw new RuntimeException('Project photo is missing its capture date.');
        }

        $filename = basename((string) $photo->stored_path);

        if ($filename === '' || $filename === '.' || $filename === DIRECTORY_SEPARATOR) {
            throw new RuntimeException('Project photo is missing its stored filename.');
        }

        return implode('/', [
            trim($projectId, '/'),
            trim($step, '/'),
            $captureDate,
            $filename,
        ]);
    }

    /**
     * @return array{discovered: int, synced: int, failed: int}
     */
    public function syncPending(): array
    {
        $photos = ProjectPhoto::query()
            ->with(['project', 'step'])
            ->whereIn('sync_status', ['pending', 'failed'])
            ->orderBy('id')
            ->get();

        $summary = [
            'discovered' => $photos->count(),
            'synced' => 0,
            'failed' => 0,
        ];
        $disk = Storage::disk((string) config('photo_sync.disk', 'local'));

        foreach ($photos as $photo) {
            try {
                if (! $disk->exists($photo->stored_path)) {
                    throw new RuntimeException('Local photo evidence is missing: '.$photo->stored_path);
                }

                $driveUrl = $this->photoSync->copy(
                    $disk->path($photo->stored_path),
                    $this->destinationFor($photo),
                );

                $photo->forceFill([
                    'sync_status' => 'synced',
                    'synced_at' => now(),
                    'sync_error' => null,
                    'drive_url' => $driveUrl,
                ])->save();

                $summary['synced']++;
            } catch (Throwable $exception) {
                $photo->forceFill([
                    'sync_status' => 'failed',
                    'sync_error' => $this->errorMessage($exception),
                ])->save();

                $summary['failed']++;
            }
        }

        return $summary;
    }

    private function errorMessage(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        return $message !== '' ? $message : $exception::class;
    }
}
