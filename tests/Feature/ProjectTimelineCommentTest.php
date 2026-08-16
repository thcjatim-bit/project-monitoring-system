<?php

namespace Tests\Feature;

use App\Contracts\WahaClient;
use App\Models\Grup;
use App\Models\Izin;
use App\Models\Mitra;
use App\Models\Project;
use App\Models\ProjectTimeline;
use App\Models\User;
use App\Support\TenantDatabaseContext;
use Closure;
use Mockery;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ProjectTimelineCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_and_internal_comments_have_different_visibility_and_edits_are_marked(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra);
        $mitraUser = $this->userWith($mitra, 'read_project', 'read_project_timeline', 'create_project_comment', 'edit_project_comment');
        $thc = $this->userWith(null, 'read_project', 'read_project_timeline', 'create_project_comment', 'edit_project_comment');

        $this->actingAs($mitraUser)
            ->post(route('projects.comments.store', $project), ['body' => 'Update untuk Mitra'])
            ->assertRedirect(route('projects.show', $project));
        $regular = ProjectTimeline::query()->where('type', 'comment')->firstOrFail();

        $this->actingAs($thc)
            ->post(route('projects.comments.store', $project), [
                'body' => 'Catatan koordinasi THC',
                'internal' => true,
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->actingAs($mitraUser)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Update untuk Mitra')
            ->assertDontSee('Catatan koordinasi THC');

        $this->actingAs($thc)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Update untuk Mitra')
            ->assertSee('Catatan koordinasi THC');

        $this->actingAs($mitraUser)
            ->patch(route('projects.comments.update', [$project, $regular->id]), ['body' => 'Update yang diedit'])
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('project_timelines', [
            'id' => $regular->id,
            'body' => 'Update yang diedit',
        ]);
        $this->assertNotNull(ProjectTimeline::query()->findOrFail($regular->id)->edited_at);
        $this->assertFalse(route('projects.show', $project) === route('projects.comments.update', [$project, $regular->id]));
    }

    public function test_mention_creates_in_app_notification_and_calls_existing_waha_seam(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra);
        $actor = $this->userWith($mitra, 'read_project', 'read_project_timeline', 'create_project_comment', 'mention_project_user');
        $target = $this->userWith(null, 'read_project', 'read_project_timeline', 'create_project_comment');
        $target->update(['no_wa' => '628123456789']);
        $waha = Mockery::mock(WahaClient::class);
        $waha->shouldReceive('sendText')->once()->with('628123456789', Mockery::type('string'));
        $this->app->instance(WahaClient::class, $waha);

        $this->actingAs($actor)
            ->post(route('projects.comments.store', $project), [
                'body' => 'Mohon cek @THC',
                'mentions' => [$target->id],
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('project_notifications', [
            'project_id' => $project->id,
            'user_id' => $target->id,
            'type' => 'project_mention',
        ]);
        $this->assertDatabaseHas('project_timeline_mentions', [
            'project_id' => $project->id,
            'mentioned_user_id' => $target->id,
            'notification_status' => 'sent',
        ]);
    }

    public function test_internal_comment_requires_thc_and_tenant_cannot_read_another_projects_timeline(): void
    {
        $mitraA = Mitra::factory()->create();
        $mitraB = Mitra::factory()->create();
        $projectB = $this->projectFor($mitraB);
        $userA = $this->userWith($mitraA, 'read_project', 'read_project_timeline', 'create_project_comment');

        $this->actingAs($userA)
            ->post(route('projects.comments.store', $projectB), ['body' => 'Tidak boleh', 'internal' => true])
            ->assertNotFound();

        $projectA = $this->projectFor($mitraA);
        $this->actingAs($userA)
            ->from(route('projects.show', $projectA))
            ->post(route('projects.comments.store', $projectA), ['body' => 'Internal Mitra', 'internal' => true])
            ->assertRedirect(route('projects.show', $projectA))
            ->assertSessionHasErrors('internal');
    }

    private function projectFor(Mitra $mitra): Project
    {
        return $this->asThc(fn (): Project => Project::query()->create([
            'id_project' => 'PRJ-TIMELINE-'.fake()->unique()->numerify('####'),
            'nama' => 'Project Timeline',
            'mitra_id' => $mitra->id,
        ]));
    }

    private function userWith(?Mitra $mitra, string ...$permissions): User
    {
        return User::factory()->create([
            'mitra_id' => $mitra?->id,
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
