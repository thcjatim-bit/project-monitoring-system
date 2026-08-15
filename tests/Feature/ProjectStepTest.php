<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Mitra;
use App\Models\Project;
use App\Models\ProjectStep;
use App\Models\User;
use App\Support\TenantDatabaseContext;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ProjectStepTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_has_exactly_eleven_steps_and_can_move_forward_or_backward(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra);
        $user = $this->userWithPermissions($mitra->id, 'read_project', 'update_project_step');

        $this->asThc(function () use ($project): void {
            $this->assertSame(11, ProjectStep::query()->where('project_id', $project->id)->count());
            $this->assertSame('design', ProjectStep::query()->where('project_id', $project->id)->where('status', 'active')->value('step'));
        });

        $this->actingAs($user)
            ->patch(route('projects.step.update', $project), ['step' => 'deployment', 'status' => 'active'])
            ->assertRedirect(route('projects.show', $project));

        $this->asThc(function () use ($project): void {
            $this->assertDatabaseHas('project_steps', ['project_id' => $project->id, 'step' => 'deployment', 'status' => 'active']);
            $this->assertDatabaseHas('project_steps', ['project_id' => $project->id, 'step' => 'survey', 'status' => 'completed']);
            $this->assertDatabaseHas('project_timelines', ['project_id' => $project->id, 'event_key' => 'step_changed']);
        });

        $this->actingAs($user)
            ->patch(route('projects.step.update', $project), ['step' => 'survey', 'status' => 'active'])
            ->assertRedirect(route('projects.show', $project));

        $this->asThc(function () use ($project): void {
            $this->assertDatabaseHas('project_steps', ['project_id' => $project->id, 'step' => 'survey', 'status' => 'active']);
            $this->assertDatabaseHas('project_steps', ['project_id' => $project->id, 'step' => 'deployment', 'status' => 'pending']);
        });
    }

    public function test_step_without_update_permission_cannot_be_changed_directly(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra);
        $user = $this->userWithPermissions($mitra->id, 'read_project');

        $this->actingAs($user)
            ->patch(route('projects.step.update', $project), ['step' => 'survey', 'status' => 'active'])
            ->assertForbidden();
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
