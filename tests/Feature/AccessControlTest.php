<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Mitra;
use App\Models\Project;
use App\Models\User;
use App\Support\TenantDatabaseContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_only_sees_menus_allowed_by_their_grup(): void
    {
        $grup = Grup::factory()->create();
        $grup->izins()->attach([
            Izin::factory()->create(['kode' => 'read_dashboard'])->id,
            Izin::factory()->create(['kode' => 'read_project'])->id,
        ]);
        $user = User::factory()->create(['grup_id' => $grup->id]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Project');
    }

    public function test_active_user_can_sign_in_with_their_credentials(): void
    {
        $user = User::factory()->create(['password' => 'rahasia-benar']);

        $this->post('/masuk', ['email' => $user->email, 'password' => 'rahasia-benar'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_sign_in(): void
    {
        $user = User::factory()->create(['aktif' => false, 'password' => 'rahasia-benar']);

        $this->from('/masuk')
            ->post('/masuk', ['email' => $user->email, 'password' => 'rahasia-benar'])
            ->assertRedirect('/masuk')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_direct_route_access_without_required_izin_aksi_is_forbidden(): void
    {
        $user = User::factory()->create(['grup_id' => Grup::factory()->create()->id]);

        $this->actingAs($user)
            ->get('/projects')
            ->assertForbidden();
    }

    public function test_direct_project_creation_without_required_izin_aksi_is_forbidden(): void
    {
        $mitra = Mitra::factory()->create();
        $user = User::factory()->create(['grup_id' => Grup::factory()->create()->id]);

        $this->actingAs($user)
            ->post('/projects', ['id_project' => 'PRJ-2608-0008', 'nama' => 'Project tanpa izin', 'mitra_id' => $mitra->id])
            ->assertForbidden();
    }

    public function test_dashboard_hides_project_menu_without_read_project_izin_aksi(): void
    {
        $grup = Grup::factory()->create();
        $grup->izins()->attach(Izin::factory()->create(['kode' => 'read_dashboard']));
        $user = User::factory()->create(['grup_id' => $grup->id]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('href="'.route('projects.index').'"', false);
    }

    public function test_user_with_create_project_izin_aksi_can_create_project_for_mitra(): void
    {
        $mitra = Mitra::factory()->create();
        $grup = Grup::factory()->create();
        $grup->izins()->attach(Izin::factory()->create(['kode' => 'create_project']));
        $user = User::factory()->create(['grup_id' => $grup->id]);

        $this->actingAs($user)
            ->post('/projects', ['id_project' => 'PRJ-2608-0010', 'nama' => 'Project berizin', 'mitra_id' => $mitra->id])
            ->assertRedirect('/projects');

        $this->assertDatabaseHas('projects', ['id_project' => 'PRJ-2608-0010', 'mitra_id' => $mitra->id]);
    }

    public function test_project_action_controls_follow_izin_aksi(): void
    {
        $readProject = Izin::factory()->create(['kode' => 'read_project']);
        $readOnlyGrup = Grup::factory()->create();
        $readOnlyGrup->izins()->attach($readProject);
        $readOnlyUser = User::factory()->create(['grup_id' => $readOnlyGrup->id]);

        $this->actingAs($readOnlyUser)
            ->get('/projects')
            ->assertOk()
            ->assertDontSee('Tambah Project');

        $createGrup = Grup::factory()->create();
        $createGrup->izins()->attach([
            $readProject->id,
            Izin::factory()->create(['kode' => 'create_project'])->id,
        ]);
        $createUser = User::factory()->create(['grup_id' => $createGrup->id]);

        $this->actingAs($createUser)
            ->get('/projects')
            ->assertOk()
            ->assertSee('Tambah Project');
    }

    public function test_project_list_and_actions_are_limited_by_izin_aksi(): void
    {
        $mitra = Mitra::factory()->create();
        $grup = Grup::factory()->create();
        $grup->izins()->attach(Izin::factory()->create(['kode' => 'read_project']));
        $user = User::factory()->create(['mitra_id' => $mitra->id, 'grup_id' => $grup->id]);
        $project = $this->asThc(fn () => Project::create([
            'id_project' => 'PRJ-2608-0013',
            'nama' => 'Project terbatas',
            'mitra_id' => $mitra->id,
        ]));

        $this->actingAs($user)
            ->get('/projects')
            ->assertOk()
            ->assertSee('Project terbatas')
            ->assertDontSee('Simpan perubahan')
            ->assertDontSee('Hapus');

        $this->actingAs($user)
            ->patch("/projects/{$project->id}", ['nama' => 'Tidak boleh'])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete("/projects/{$project->id}")
            ->assertForbidden();
    }

    public function test_unauthorized_project_route_returns_403_even_for_another_mitras_project(): void
    {
        [$mitraA, $mitraB, $userA] = $this->tenantFixtures();
        $projectB = $this->asThc(fn () => Project::create([
            'id_project' => 'PRJ-2608-0014',
            'nama' => 'Project Mitra B',
            'mitra_id' => $mitraB->id,
        ]));

        $this->actingAs($userA)
            ->patch("/projects/{$projectB->id}", ['nama' => 'Tidak boleh'])
            ->assertForbidden();
    }

    public function test_direct_dashboard_access_without_required_izin_aksi_is_forbidden(): void
    {
        $user = User::factory()->create(['grup_id' => Grup::factory()->create()->id]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertForbidden();
    }

    public function test_postgresql_integration_uses_restricted_application_role(): void
    {
        $role = DB::selectOne(<<<'SQL'
            select current_user as name,
                   case when not rolsuper and not rolbypassrls then 'yes' else 'no' end as restricted
            from pg_roles
            where rolname = current_user
            SQL);

        $this->assertSame('pms_app', $role->name);
        $this->assertSame('yes', $role->restricted);
    }

    public function test_mitra_raw_query_cannot_read_another_mitras_project(): void
    {
        [$mitraA, $mitraB, $userA] = $this->tenantFixtures();

        $this->asThc(function () use ($mitraA, $mitraB): void {
            Project::create(['id_project' => 'PRJ-2608-0001', 'nama' => 'Mitra A', 'mitra_id' => $mitraA->id]);
            Project::create(['id_project' => 'PRJ-2608-0002', 'nama' => 'Mitra B', 'mitra_id' => $mitraB->id]);
        });

        $this->actingAs($userA)->get('/projects')->assertOk();

        $visibleProjectIds = DB::table('projects')->pluck('id_project')->all();

        $this->assertSame(['PRJ-2608-0001'], $visibleProjectIds);
        $this->assertSame([$mitraA->id], DB::table('projects')->pluck('mitra_id')->all());
    }

    public function test_raw_query_without_tenant_context_is_denied_by_default(): void
    {
        $mitra = Mitra::factory()->create();

        $this->asThc(fn () => Project::create([
            'id_project' => 'PRJ-2608-0011',
            'nama' => 'Project tanpa konteks',
            'mitra_id' => $mitra->id,
        ]));

        app(TenantDatabaseContext::class)->set(null, false);

        $this->assertSame([], DB::table('projects')->get()->all());
    }

    public function test_mitra_raw_query_cannot_insert_a_project_for_another_mitra(): void
    {
        [$mitraA, $mitraB, $userA] = $this->tenantFixtures();

        $this->actingAs($userA)->get('/projects')->assertOk();

        $this->expectException(QueryException::class);

        DB::table('projects')->insert([
            'id_project' => 'PRJ-2608-0003',
            'nama' => 'Tidak diizinkan',
            'mitra_id' => $mitraB->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_mitra_raw_query_cannot_move_its_project_to_another_mitra(): void
    {
        [$mitraA, $mitraB, $userA] = $this->tenantFixtures();

        $project = $this->asThc(fn () => Project::create([
            'id_project' => 'PRJ-2608-0004',
            'nama' => 'Mitra A',
            'mitra_id' => $mitraA->id,
        ]));

        $this->actingAs($userA)->get('/projects')->assertOk();

        $this->expectException(QueryException::class);

        DB::table('projects')->where('id', $project->id)->update(['mitra_id' => $mitraB->id]);
    }

    public function test_mitra_raw_query_cannot_delete_another_mitras_project(): void
    {
        [$mitraA, $mitraB, $userA] = $this->tenantFixtures();

        $projectB = $this->asThc(fn () => Project::create([
            'id_project' => 'PRJ-2608-0009',
            'nama' => 'Project Mitra B',
            'mitra_id' => $mitraB->id,
        ]));

        $this->actingAs($userA)->get('/projects')->assertOk();

        $this->assertSame(0, DB::table('projects')->where('id', $projectB->id)->delete());
        $this->asThc(fn () => $this->assertDatabaseHas('projects', ['id' => $projectB->id]));
    }

    public function test_mitra_eloquent_project_query_and_creation_use_active_mitra(): void
    {
        [$mitraA, $mitraB, $userA] = $this->tenantFixtures();

        $this->asThc(function () use ($mitraA, $mitraB): void {
            Project::create(['id_project' => 'PRJ-2608-0005', 'nama' => 'Project Mitra A', 'mitra_id' => $mitraA->id]);
            Project::create(['id_project' => 'PRJ-2608-0006', 'nama' => 'Project Mitra B', 'mitra_id' => $mitraB->id]);
        });

        $this->actingAs($userA)->get('/projects')->assertOk();

        $project = Project::create(['id_project' => 'PRJ-2608-0007', 'nama' => 'Project baru']);

        $this->assertSame([$mitraA->id], Project::query()->pluck('mitra_id')->unique()->all());
        $this->assertSame($mitraA->id, $project->mitra_id);
    }

    public function test_mitra_with_izin_aksi_can_update_and_delete_its_own_project(): void
    {
        [$mitraA, $mitraB, $userA] = $this->tenantFixtures();
        $userA->grup->izins()->attach([
            Izin::factory()->create(['kode' => 'update_project'])->id,
            Izin::factory()->create(['kode' => 'delete_project'])->id,
        ]);

        $project = $this->asThc(fn () => Project::create([
            'id_project' => 'PRJ-2608-0012',
            'nama' => 'Project Mitra A',
            'mitra_id' => $mitraA->id,
        ]));

        $this->actingAs($userA)
            ->patch("/projects/{$project->id}", ['nama' => 'Project diperbarui'])
            ->assertRedirect('/projects');

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'nama' => 'Project diperbarui']);

        $this->actingAs($userA)
            ->delete("/projects/{$project->id}")
            ->assertRedirect('/projects');

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    /** @return array{Mitra, Mitra, User} */
    private function tenantFixtures(): array
    {
        $mitraA = Mitra::factory()->create();
        $mitraB = Mitra::factory()->create();
        $grup = Grup::factory()->create();
        $grup->izins()->attach([
            Izin::factory()->create(['kode' => 'read_dashboard'])->id,
            Izin::factory()->create(['kode' => 'read_project'])->id,
        ]);
        $userA = User::factory()->create(['mitra_id' => $mitraA->id, 'grup_id' => $grup->id]);

        return [$mitraA, $mitraB, $userA];
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
