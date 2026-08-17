<?php

namespace Tests\Feature;

use App\Contracts\PhotoSyncPort;
use App\Models\Mitra;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\ProjectStep;
use App\Models\User;
use App\Services\ProjectPhotoSyncService;
use App\Support\TenantDatabaseContext;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ProjectPhotoSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_destination_uses_project_step_capture_date_and_stable_stored_filename(): void
    {
        $photo = new ProjectPhoto([
            'stored_path' => 'project-photos/PRJ-SYNC-0001/deployment/2026-08-12/evidence-uuid.jpg',
            'capture_date' => '2026-08-12',
        ]);
        $photo->setRelation('project', new Project(['id_project' => 'PRJ-SYNC-0001']));
        $photo->setRelation('step', new ProjectStep(['step' => 'deployment']));

        $service = new ProjectPhotoSyncService(Mockery::mock(PhotoSyncPort::class));

        $this->assertSame(
            'PRJ-SYNC-0001/deployment/2026-08-12/evidence-uuid.jpg',
            $service->destinationFor($photo),
        );
    }

    public function test_pending_photo_is_copied_and_marked_synced(): void
    {
        Storage::fake('local');
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra, 'PRJ-SYNC-0001');
        $path = 'project-photos/PRJ-SYNC-0001/deployment/2026-08-12/evidence-uuid.jpg';
        Storage::disk('local')->put($path, 'jpeg evidence');
        $photo = $this->photoFor($project, $path, '2026-08-12');

        $port = Mockery::mock(PhotoSyncPort::class);
        $port->shouldReceive('copy')
            ->once()
            ->with(Mockery::type('string'), 'PRJ-SYNC-0001/deployment/2026-08-12/evidence-uuid.jpg')
            ->andReturn('https://drive.example/evidence-uuid.jpg');
        $this->app->instance(PhotoSyncPort::class, $port);

        $summary = $this->asThc(fn (): array => app(ProjectPhotoSyncService::class)->syncPending());

        $this->assertSame(['discovered' => 1, 'synced' => 1, 'failed' => 0], $summary);
        $this->asThc(function () use ($photo): void {
            $photo->refresh();
            $this->assertSame('synced', $photo->sync_status);
            $this->assertSame('https://drive.example/evidence-uuid.jpg', $photo->drive_url);
            $this->assertNotNull($photo->synced_at);
            $this->assertNull($photo->sync_error);
        });
        Storage::disk('local')->assertExists($path);
    }

    public function test_rclone_failure_marks_photo_failed_without_deleting_local_evidence(): void
    {
        Storage::fake('local');
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra, 'PRJ-SYNC-0002');
        $path = 'project-photos/PRJ-SYNC-0002/survey/2026-08-13/evidence-uuid.jpg';
        Storage::disk('local')->put($path, 'jpeg evidence');
        $photo = $this->photoFor($project, $path, '2026-08-13', 'survey');

        $port = Mockery::mock(PhotoSyncPort::class);
        $port->shouldReceive('copy')->once()->andThrow(new RuntimeException('Drive unavailable'));
        $this->app->instance(PhotoSyncPort::class, $port);

        $summary = $this->asThc(fn (): array => app(ProjectPhotoSyncService::class)->syncPending());

        $this->assertSame(['discovered' => 1, 'synced' => 0, 'failed' => 1], $summary);
        $this->asThc(function () use ($photo): void {
            $photo->refresh();
            $this->assertSame('failed', $photo->sync_status);
            $this->assertStringContainsString('Drive unavailable', $photo->sync_error);
        });
        Storage::disk('local')->assertExists($path);
    }

    public function test_failed_photo_can_retry_and_synced_photo_is_not_copied_again(): void
    {
        Storage::fake('local');
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra, 'PRJ-SYNC-0003');
        $path = 'project-photos/PRJ-SYNC-0003/atp/2026-08-14/evidence-uuid.jpg';
        Storage::disk('local')->put($path, 'jpeg evidence');
        $photo = $this->photoFor($project, $path, '2026-08-14', 'atp');

        $attempts = 0;
        $port = Mockery::mock(PhotoSyncPort::class);
        $port->shouldReceive('copy')->twice()->andReturnUsing(function () use (&$attempts): string {
            $attempts++;

            if ($attempts === 1) {
                throw new RuntimeException('temporary outage');
            }

            return 'https://drive.example/evidence-uuid.jpg';
        });
        $this->app->instance(PhotoSyncPort::class, $port);

        $first = $this->asThc(fn (): array => app(ProjectPhotoSyncService::class)->syncPending());
        $second = $this->asThc(fn (): array => app(ProjectPhotoSyncService::class)->syncPending());
        $third = $this->asThc(fn (): array => app(ProjectPhotoSyncService::class)->syncPending());

        $this->assertSame(['discovered' => 1, 'synced' => 0, 'failed' => 1], $first);
        $this->assertSame(['discovered' => 1, 'synced' => 1, 'failed' => 0], $second);
        $this->assertSame(['discovered' => 0, 'synced' => 0, 'failed' => 0], $third);
        $this->assertSame(2, $attempts);
        $this->asThc(function () use ($photo): void {
            $photo->refresh();
            $this->assertSame('synced', $photo->sync_status);
            $this->assertSame('https://drive.example/evidence-uuid.jpg', $photo->drive_url);
            $this->assertNull($photo->sync_error);
        });
    }

    private function projectFor(Mitra $mitra, string $idProject): Project
    {
        return $this->asThc(fn (): Project => Project::query()->create([
            'id_project' => $idProject,
            'nama' => 'Project Photo Sync',
            'mitra_id' => $mitra->id,
        ]));
    }

    private function photoFor(Project $project, string $path, string $date, string $stepKey = 'deployment'): ProjectPhoto
    {
        return $this->asThc(function () use ($project, $path, $date, $stepKey): ProjectPhoto {
            $step = $project->steps()->where('step', $stepKey)->firstOrFail();
            $uploader = User::factory()->create(['mitra_id' => $project->mitra_id]);

            return ProjectPhoto::query()->create([
                'mitra_id' => $project->mitra_id,
                'project_id' => $project->id,
                'project_step_id' => $step->id,
                'uploaded_by' => $uploader->id,
                'original_name' => basename($path),
                'stored_path' => $path,
                'mime_type' => 'image/jpeg',
                'original_size' => 12,
                'width' => 1920,
                'height' => 1080,
                'capture_date' => CarbonImmutable::parse($date),
                'sync_status' => 'pending',
            ]);
        });
    }

    private function asThc(Closure $callback): mixed
    {
        app(TenantDatabaseContext::class)->set(null, true);

        try {
            return $callback();
        } finally {
            app(TenantDatabaseContext::class)->set(null, false);
        }
    }
}
