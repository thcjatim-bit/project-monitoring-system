<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Mitra;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\User;
use App\Support\TenantDatabaseContext;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ProjectPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_upload_jpeg_photos_for_a_project_step(): void
    {
        Storage::fake('local');
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra);
        $user = $this->userWith($mitra, 'read_project', 'upload_project_photo');

        $this->actingAs($user)
            ->post(route('projects.photos.store', $project), [
                'step' => 'survey',
                'photos' => [UploadedFile::fake()->image('survey.jpg', 800, 600)->size(120)],
            ])
            ->assertRedirect(route('projects.show', $project));

        $photo = ProjectPhoto::query()->firstOrFail();
        $this->assertSame($project->id, $photo->project_id);
        $this->assertSame($mitra->id, $photo->mitra_id);
        $this->assertSame('survey', $photo->step->step);
        $this->assertSame('pending', $photo->sync_status);
        Storage::disk('local')->assertExists($photo->stored_path);

        $this->actingAs($user)
            ->get(route('projects.photos.show', [$project, $photo->id]))
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_upload_rejects_non_jpeg_oversized_and_more_than_ten_photos(): void
    {
        Storage::fake('local');
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra);
        $user = $this->userWith($mitra, 'read_project', 'upload_project_photo');

        $this->actingAs($user)
            ->from(route('projects.show', $project))
            ->post(route('projects.photos.store', $project), [
                'step' => 'survey',
                'photos' => [UploadedFile::fake()->create('survey.png', 100, 'image/png')],
            ])
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHasErrors('photos.0');

        $this->actingAs($user)
            ->from(route('projects.show', $project))
            ->post(route('projects.photos.store', $project), [
                'step' => 'survey',
                'photos' => [UploadedFile::fake()->image('large.jpg')->size(5121)],
            ])
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHasErrors('photos.0');

        $photos = collect(range(1, 11))->map(fn (int $number) => UploadedFile::fake()->image("photo-{$number}.jpg"))->all();
        $this->actingAs($user)
            ->from(route('projects.show', $project))
            ->post(route('projects.photos.store', $project), ['step' => 'survey', 'photos' => $photos])
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHasErrors('photos');

        $this->assertDatabaseCount('project_photos', 0);
    }

    public function test_mitra_cannot_read_or_upload_another_mitras_project_photo(): void
    {
        Storage::fake('local');
        $mitraA = Mitra::factory()->create();
        $mitraB = Mitra::factory()->create();
        $projectB = $this->projectFor($mitraB);
        $userA = $this->userWith($mitraA, 'read_project', 'upload_project_photo');

        $this->actingAs($userA)
            ->get(route('projects.show', $projectB))
            ->assertNotFound();

        $this->actingAs($userA)
            ->post(route('projects.photos.store', $projectB), [
                'step' => 'survey',
                'photos' => [UploadedFile::fake()->image('survey.jpg')],
            ])
            ->assertNotFound();
    }

    public function test_upload_permission_is_required(): void
    {
        Storage::fake('local');
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra);
        $user = $this->userWith($mitra, 'read_project');

        $this->actingAs($user)
            ->post(route('projects.photos.store', $project), [
                'step' => 'survey',
                'photos' => [UploadedFile::fake()->image('survey.jpg')],
            ])
            ->assertForbidden();
    }

    private function projectFor(Mitra $mitra): Project
    {
        return $this->asThc(fn (): Project => Project::query()->create([
            'id_project' => 'PRJ-PHOTO-'.fake()->unique()->numerify('####'),
            'nama' => 'Project Photo',
            'mitra_id' => $mitra->id,
        ]));
    }

    private function userWith(Mitra $mitra, string ...$permissions): User
    {
        return User::factory()->create([
            'mitra_id' => $mitra->id,
            'grup_id' => $this->groupWith(...$permissions)->id,
        ]);
    }

    private function groupWith(string ...$permissions): Grup
    {
        $group = Grup::factory()->create();
        $group->izins()->attach(collect($permissions)->map(
            fn (string $permission) => Izin::query()->firstOrCreate(['kode' => $permission], ['nama' => $permission])->id,
        )->all());

        return $group;
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
