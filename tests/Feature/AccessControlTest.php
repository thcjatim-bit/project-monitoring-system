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

        $this->assertSame([$mitraA->id], DB::table('projects')->pluck('mitra_id')->all());
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
