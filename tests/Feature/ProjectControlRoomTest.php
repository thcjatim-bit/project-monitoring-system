<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Mitra;
use App\Models\Project;
use App\Models\User;
use App\Support\TenantDatabaseContext;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ProjectControlRoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_read_project_can_open_control_room_from_project_list(): void
    {
        $mitra = Mitra::factory()->create(['nama' => 'Mitra Nusantara']);
        $user = $this->userWithPermissions($mitra->id, 'read_project');
        $project = $this->asThc(fn (): Project => Project::create([
            'id_project' => 'PRJ-2608-0040',
            'nama' => 'Instalasi Site Utama',
            'mitra_id' => $mitra->id,
            'status_project' => 'selesai',
            'toc' => '2026-09-30',
        ]));

        $this->actingAs($user)
            ->get('/projects')
            ->assertOk()
            ->assertSee(route('projects.show', $project), false);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Project Control Room')
            ->assertSee('PRJ-2608-0040')
            ->assertSee('Instalasi Site Utama')
            ->assertSee('Mitra Nusantara')
            ->assertSee('Selesai')
            ->assertSee('30 Sep 2026');

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertSee('href="'.route('projects.index').'"', false)
            ->assertDontSee('href="'.route('dashboard').'"', false)
            ->assertSee('data-control-room-state="loading"', false)
            ->assertSee('Memuat Control Room', false)
            ->assertSee('data-control-room-state="error"', false)
            ->assertSee('Gagal memuat Control Room', false);
    }

    public function test_user_without_read_project_cannot_open_control_room_directly(): void
    {
        $mitra = Mitra::factory()->create();
        $user = User::factory()->create(['mitra_id' => $mitra->id, 'grup_id' => Grup::factory()->create()->id]);
        $project = $this->asThc(fn (): Project => Project::create([
            'id_project' => 'PRJ-2608-0041',
            'nama' => 'Project Tertutup',
            'mitra_id' => $mitra->id,
        ]));

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertForbidden();
    }

    public function test_thc_with_read_project_can_open_control_room_from_project_list(): void
    {
        $thc = $this->userWithPermissions(null, 'read_project');
        $project = $this->asThc(fn (): Project => Project::create([
            'id_project' => 'PRJ-2608-0043',
            'nama' => 'Project THC',
            'mitra_id' => Mitra::factory()->create()->id,
        ]));

        $this->actingAs($thc)
            ->get('/projects')
            ->assertOk()
            ->assertSee(route('projects.show', $project), false)
            ->assertDontSee('href="'.route('dashboard').'"', false);

        $this->actingAs($thc)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee($project->id_project)
            ->assertSee($project->nama);
    }

    public function test_control_room_renders_contextual_error_without_leaking_other_projects(): void
    {
        $mitraA = Mitra::factory()->create();
        $mitraB = Mitra::factory()->create();
        $userA = $this->userWithPermissions($mitraA->id, 'read_project');
        $projectA = $this->asThc(fn (): Project => Project::create([
            'id_project' => 'PRJ-2608-0044',
            'nama' => 'Project Dengan Error',
            'mitra_id' => $mitraA->id,
        ]));
        $this->asThc(fn (): Project => Project::create([
            'id_project' => 'PRJ-2608-0045',
            'nama' => 'Project Tenant Lain',
            'mitra_id' => $mitraB->id,
        ]));

        $this->actingAs($userA)
            ->get(route('projects.show', $projectA).'?as_of=not-a-date')
            ->assertOk()
            ->assertSee($projectA->id_project)
            ->assertSee('Gagal memuat Control Room', false)
            ->assertSee('data-control-room-state="error"', false)
            ->assertDontSee('Project Tenant Lain');
    }

    public function test_mitra_cannot_open_another_mitras_control_room(): void
    {
        $mitraA = Mitra::factory()->create();
        $mitraB = Mitra::factory()->create();
        $userA = $this->userWithPermissions($mitraA->id, 'read_project');
        $projectB = $this->asThc(fn (): Project => Project::create([
            'id_project' => 'PRJ-2608-0042',
            'nama' => 'Project Mitra B',
            'mitra_id' => $mitraB->id,
        ]));

        $this->actingAs($userA)
            ->get(route('projects.show', $projectB))
            ->assertNotFound();
    }

    private function userWithPermissions(?int $mitraId, string ...$permissions): User
    {
        $group = Grup::factory()->create();
        $group->izins()->attach(collect($permissions)->map(
            fn (string $permission) => Izin::factory()->create(['kode' => $permission])->id,
        )->all());

        return User::factory()->create([
            'mitra_id' => $mitraId,
            'grup_id' => $group->id,
        ]);
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
