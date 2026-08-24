<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Mitra;
use App\Models\Project;
use App\Models\ProjectStep;
use App\Models\ProjectTimeline;
use App\Models\User;
use App\Support\TenantDatabaseContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ProjectStepTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_has_exactly_eleven_steps_and_can_move_forward_or_backward(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra);
        $user = $this->userWithPermissions($mitra->id, 'read_project', 'read_project_timeline', 'update_project_step');

        $this->asThc(function () use ($project): void {
            $this->assertSame(11, ProjectStep::query()->where('project_id', $project->id)->count());
            $this->assertSame('design', ProjectStep::query()->where('project_id', $project->id)->where('status', 'active')->value('step'));
        });

        $response = $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('action="'.route('projects.step.update', $project).'"', false);
        foreach (['Design', 'Survey', 'DRM', 'SPK', 'Pengadaan Material', 'Delivery Material', 'MOS', 'Deployment', 'Test Comm', 'ATP', 'GO Live'] as $label) {
            $response->assertSee($label);
        }

        $this->actingAs($user)
            ->patch(route('projects.step.update', $project), ['step' => 'deployment', 'status' => 'active'])
            ->assertRedirect(route('projects.show', $project));

        $this->asThc(function () use ($project): void {
            $this->assertDatabaseHas('project_steps', ['project_id' => $project->id, 'step' => 'deployment', 'status' => 'active']);
            $this->assertDatabaseHas('project_steps', ['project_id' => $project->id, 'step' => 'survey', 'status' => 'completed']);
            $this->assertNotNull(ProjectStep::query()->where('project_id', $project->id)->where('step', 'survey')->value('completed_at'));
            $this->assertNull(ProjectStep::query()->where('project_id', $project->id)->where('step', 'deployment')->value('completed_at'));
            $this->assertDatabaseHas('project_timelines', ['project_id' => $project->id, 'event_key' => 'step_changed']);
        });

        $this->actingAs($user)
            ->patch(route('projects.step.update', $project), ['step' => 'survey', 'status' => 'active'])
            ->assertRedirect(route('projects.show', $project));

        $this->asThc(function () use ($project): void {
            $this->assertDatabaseHas('project_steps', ['project_id' => $project->id, 'step' => 'survey', 'status' => 'active']);
            $this->assertDatabaseHas('project_steps', ['project_id' => $project->id, 'step' => 'deployment', 'status' => 'pending']);
            $this->assertNull(ProjectStep::query()->where('project_id', $project->id)->where('step', 'survey')->value('completed_at'));
            $this->assertNull(ProjectStep::query()->where('project_id', $project->id)->where('step', 'deployment')->value('completed_at'));
        });

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Step Changed');
    }

    public function test_completing_a_step_records_actual_date_and_activates_the_next_step(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra);
        $user = $this->userWithPermissions($mitra->id, 'read_project', 'read_project_timeline', 'update_project_step');
        $completedAt = Carbon::create(2026, 8, 17, 10, 15, 0);
        Carbon::setTestNow($completedAt);

        try {
            $this->actingAs($user)
                ->patch(route('projects.step.update', $project), ['step' => 'survey', 'status' => 'completed'])
                ->assertRedirect(route('projects.show', $project));
        } finally {
            Carbon::setTestNow();
        }

        $this->asThc(function () use ($project, $user, $completedAt): void {
            $steps = ProjectStep::query()
                ->where('project_id', $project->id)
                ->orderBy('urutan')
                ->get()
                ->keyBy('step');

            $this->assertSame('completed', $steps['design']->status);
            $this->assertSame('completed', $steps['survey']->status);
            $this->assertSame('active', $steps['drm']->status);
            $this->assertSame('pending', $steps['spk']->status);
            $this->assertSame($completedAt->toDateTimeString(), $steps['design']->completed_at->toDateTimeString());
            $this->assertSame($completedAt->toDateTimeString(), $steps['survey']->completed_at->toDateTimeString());
            $this->assertSame($user->id, $steps['design']->completed_by);
            $this->assertSame($user->id, $steps['survey']->completed_by);
            $this->assertNull($steps['drm']->completed_at);
            $this->assertNull($steps['spk']->completed_at);

            $timeline = ProjectTimeline::query()
                ->where('project_id', $project->id)
                ->where('event_key', 'step_changed')
                ->latest('id')
                ->firstOrFail();
            $this->assertSame(['from' => 'design', 'to' => 'survey', 'status' => 'completed'], $timeline->metadata);
        });

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Step Changed');
    }

    public function test_step_without_update_permission_cannot_be_changed_directly(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra);
        $user = $this->userWithPermissions($mitra->id, 'read_project');

        $this->actingAs($user)
            ->patch(route('projects.step.update', $project), ['step' => 'survey', 'status' => 'active'])
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertDontSee('action="'.route('projects.step.update', $project).'"', false);
    }

    public function test_mitra_cannot_change_another_mitras_step(): void
    {
        $mitraA = Mitra::factory()->create();
        $mitraB = Mitra::factory()->create();
        $projectB = $this->projectFor($mitraB);
        $userA = $this->userWithPermissions($mitraA->id, 'read_project', 'update_project_step');

        $this->actingAs($userA)
            ->patch(route('projects.step.update', $projectB), ['step' => 'survey', 'status' => 'active'])
            ->assertNotFound();
    }

    public function test_mitra_raw_query_cannot_read_another_mitras_steps(): void
    {
        $mitraA = Mitra::factory()->create();
        $mitraB = Mitra::factory()->create();
        $projectA = $this->projectFor($mitraA);
        $projectB = $this->projectFor($mitraB);
        $userA = $this->userWithPermissions($mitraA->id, 'read_project');

        $this->actingAs($userA)->get(route('projects.index'))->assertOk();

        $visibleProjectIds = DB::table('project_steps')
            ->whereIn('project_id', [$projectA->id, $projectB->id])
            ->pluck('project_id')
            ->unique()
            ->all();

        $this->assertSame([$projectA->id], $visibleProjectIds);
    }

    private function projectFor(Mitra $mitra): Project
    {
        return $this->asThc(fn (): Project => Project::create([
            'id_project' => 'PRJ-2608-'.fake()->unique()->numerify('####'),
            'nama' => 'Project Step',
            'mitra_id' => $mitra->id,
        ]));
    }

    private function userWithPermissions(?int $mitraId, string ...$permissions): User
    {
        $group = Grup::factory()->create();
        $group->izins()->attach(collect($permissions)->map(
            fn (string $permission) => Izin::query()->firstOrCreate(
                ['kode' => $permission],
                ['nama' => $permission],
            )->id,
        )->all());

        return User::factory()->create(['mitra_id' => $mitraId, 'grup_id' => $group->id]);
    }

    private function asThc(\Closure $callback): mixed
    {
        app(TenantDatabaseContext::class)->set(null, true);

        try {
            return $callback();
        } finally {
            app(TenantDatabaseContext::class)->set(null, false);
        }
    }
}
