<?php

namespace Tests\Feature;

use App\Contracts\WahaClient;
use App\Models\Grup;
use App\Models\Izin;
use App\Models\Mitra;
use App\Models\MitraHargaJasa;
use App\Models\PekerjaanJasa;
use App\Models\Pks;
use App\Models\Project;
use App\Models\ProjectBaseline;
use App\Models\ProjectBaselineProposal;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\TenantDatabaseContext;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class AdminMitraWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_mitra_user_workspace_is_scoped_to_the_authenticated_mitra(): void
    {
        $mitraA = Mitra::factory()->create(['nama' => 'Mitra A']);
        $mitraB = Mitra::factory()->create(['nama' => 'Mitra B']);
        $admin = User::factory()->create([
            'mitra_id' => $mitraA->id,
            'grup_id' => $this->groupWith('manage_mitra_users')->id,
        ]);
        $ownUser = User::factory()->create(['mitra_id' => $mitraA->id, 'name' => 'User Mitra A']);
        User::factory()->create(['mitra_id' => $mitraB->id, 'name' => 'User Mitra B']);

        $this->actingAs($admin)
            ->get(route('admin.users'))
            ->assertOk()
            ->assertSee($ownUser->name)
            ->assertDontSee('User Mitra B')
            ->assertSee('Kelola User Mitra');
    }

    public function test_admin_mitra_creates_a_user_in_its_tenant_even_when_payload_names_another_mitra(): void
    {
        $mitraA = Mitra::factory()->create();
        $mitraB = Mitra::factory()->create();
        $role = $this->groupWith('read_project');
        $admin = User::factory()->create([
            'mitra_id' => $mitraA->id,
            'grup_id' => $this->groupWith('manage_mitra_users')->id,
        ]);
        $this->app->instance(WahaClient::class, new class implements WahaClient
        {
            public function sendText(string $to, string $text): void {}

            public function sessionStatus(string $session): array
            {
                return [];
            }

            public function restart(string $session): void {}
        });

        $this->actingAs($admin)
            ->post(route('admin.users.create'), [
                'name' => 'User Baru',
                'email' => 'user.baru@example.com',
                'no_wa' => '628123456789',
                'mitra_id' => $mitraB->id,
                'grup_id' => $role->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'user.baru@example.com',
            'mitra_id' => $mitraA->id,
            'grup_id' => $role->id,
        ]);
        $this->assertDatabaseMissing('users', [
            'email' => 'user.baru@example.com',
            'mitra_id' => $mitraB->id,
        ]);
    }

    public function test_admin_mitra_cannot_select_a_group_with_an_unlisted_capability(): void
    {
        $mitra = Mitra::factory()->create();
        $admin = User::factory()->create([
            'mitra_id' => $mitra->id,
            'grup_id' => $this->groupWith('manage_mitra_users')->id,
        ]);
        $sensitiveGroup = Grup::factory()->create(['nama' => 'Grup Sensitif Baru']);
        $sensitiveGroup->izins()->attach(Izin::factory()->create(['kode' => 'new_sensitive_capability']));

        $this->actingAs($admin)
            ->get(route('admin.users'))
            ->assertOk()
            ->assertDontSee('Grup Sensitif Baru');
    }

    public function test_admin_mitra_cannot_edit_another_tenants_user_by_direct_url(): void
    {
        $mitraA = Mitra::factory()->create();
        $mitraB = Mitra::factory()->create();
        $admin = User::factory()->create([
            'mitra_id' => $mitraA->id,
            'grup_id' => $this->groupWith('manage_mitra_users')->id,
        ]);
        $target = User::factory()->create(['mitra_id' => $mitraB->id, 'name' => 'Tetap Sama']);

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $target), [
                'name' => 'Perubahan Lintas Tenant',
                'email' => 'cross-tenant@example.com',
                'no_wa' => '628123456789',
                'mitra_id' => $mitraA->id,
                'grup_id' => $admin->grup_id,
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Tetap Sama', 'mitra_id' => $mitraB->id]);
    }

    public function test_admin_mitra_navigation_exposes_only_authorized_tenant_workspace_entries(): void
    {
        $mitra = Mitra::factory()->create();
        $admin = User::factory()->create([
            'mitra_id' => $mitra->id,
            'grup_id' => $this->groupWith('read_dashboard', 'manage_mitra_users', 'operate_warehouse')->id,
        ]);

        $this->actingAs($admin)
            ->get(route('mitra.dashboard'))
            ->assertOk()
            ->assertSee('Kelola User Mitra')
            ->assertSee(route('admin.users'), false)
            ->assertSee('Warehouse')
            ->assertSee(route('warehouse.index'), false);
    }

    public function test_admin_mitra_dashboard_uses_real_kpi_cards_and_shared_quantity_formatting(): void
    {
        $mitra = Mitra::factory()->create();
        $admin = User::factory()->create([
            'mitra_id' => $mitra->id,
            'grup_id' => $this->groupWith('read_dashboard', 'read_project', 'read_master_data', 'manage_mitra_users')->id,
        ]);
        $this->asThc(function () use ($mitra): void {
            Warehouse::factory()->create(['mitra_id' => $mitra->id, 'aktif' => true]);
            Warehouse::factory()->create(['mitra_id' => Mitra::factory()->create()->id, 'aktif' => true]);
        });

        $this->actingAs($admin)
            ->get(route('mitra.dashboard'))
            ->assertOk()
            ->assertSee('data-dashboard-kpis', false)
            ->assertSee('Project aktif')
            ->assertSee('Project selesai')
            ->assertSeeInOrder(['Warehouse aktif', '1'])
            ->assertSee('User aktif')
            ->assertSee('ui-badge', false);
    }

    public function test_admin_mitra_can_assign_own_users_to_own_warehouses_only(): void
    {
        $mitraA = Mitra::factory()->create();
        $mitraB = Mitra::factory()->create();
        $admin = User::factory()->create([
            'mitra_id' => $mitraA->id,
            'grup_id' => $this->groupWith('manage_mitra_warehouse')->id,
        ]);
        $user = User::factory()->create(['mitra_id' => $mitraA->id]);
        [$warehouseA, $warehouseB] = $this->asThc(fn (): array => [
            Warehouse::factory()->create(['mitra_id' => $mitraA->id]),
            Warehouse::factory()->create(['mitra_id' => $mitraB->id]),
        ]);

        $this->actingAs($admin)
            ->post(route('mitra.warehouses.assign', $warehouseA), ['user_id' => $user->id])
            ->assertRedirect();

        $this->assertDatabaseHas('user_warehouses', ['user_id' => $user->id, 'warehouse_id' => $warehouseA->id]);

        $this->actingAs($admin)
            ->post(route('mitra.warehouses.assign', $warehouseB), ['user_id' => $user->id])
            ->assertNotFound();
        $this->assertDatabaseMissing('user_warehouses', ['user_id' => $user->id, 'warehouse_id' => $warehouseB->id]);
    }

    public function test_admin_mitra_submits_price_for_its_active_pks_without_approving_it(): void
    {
        $mitra = Mitra::factory()->create();
        $admin = User::factory()->create([
            'mitra_id' => $mitra->id,
            'grup_id' => $this->groupWith('manage_mitra_prices')->id,
        ]);
        [$pks, $job] = $this->asThc(fn (): array => [
            Pks::create(['mitra_id' => $mitra->id, 'nomor' => 'PKS-ADMIN-MITRA', 'tanggal_mulai' => '2026-01-01', 'tanggal_berakhir' => '2026-12-31']),
            PekerjaanJasa::create(['kode' => 'JASA-ADMIN-MITRA', 'nama' => 'Penarikan Kabel', 'aktif' => true]),
        ]);

        $this->actingAs($admin)
            ->post(route('mitra.prices.store'), [
                'pks_id' => $pks->id,
                'pekerjaan_jasa_id' => $job->id,
                'harga' => '125000',
                'berlaku_mulai' => '2026-09-01',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('mitra_harga_jasas', [
            'mitra_id' => $mitra->id,
            'pks_id' => $pks->id,
            'pekerjaan_jasa_id' => $job->id,
            'harga' => '125000.00',
            'status' => 'diajukan',
            'diajukan_oleh' => $admin->id,
        ]);
    }

    public function test_only_thc_can_approve_a_submitted_mitra_price(): void
    {
        $mitra = Mitra::factory()->create();
        [$pks, $job, $price] = $this->asThc(function () use ($mitra): array {
            $pks = Pks::create(['mitra_id' => $mitra->id, 'nomor' => 'PKS-APPROVAL', 'tanggal_mulai' => '2026-01-01', 'tanggal_berakhir' => '2026-12-31']);
            $job = PekerjaanJasa::create(['kode' => 'JASA-APPROVAL', 'nama' => 'Terminasi ODP', 'aktif' => true]);
            $price = MitraHargaJasa::create([
                'mitra_id' => $mitra->id,
                'pks_id' => $pks->id,
                'pekerjaan_jasa_id' => $job->id,
                'harga' => '80000',
                'status' => 'diajukan',
                'berlaku_mulai' => '2026-09-01',
            ]);

            return [$pks, $job, $price];
        });
        $thc = User::factory()->create(['mitra_id' => null, 'grup_id' => $this->groupWith('approve_mitra_price')->id]);
        $mitraUser = User::factory()->create(['mitra_id' => $mitra->id, 'grup_id' => $this->groupWith('manage_mitra_prices')->id]);

        $this->actingAs($mitraUser)
            ->patch(route('admin.prices.approve', $price))
            ->assertForbidden();

        $this->actingAs($thc)
            ->patch(route('admin.prices.approve', $price))
            ->assertRedirect();

        $this->assertDatabaseHas('mitra_harga_jasas', ['id' => $price->id, 'status' => 'disetujui', 'diputuskan_oleh' => $thc->id]);
    }

    public function test_admin_mitra_can_add_rab_jasa_to_its_project_using_its_approved_price_snapshot(): void
    {
        $mitra = Mitra::factory()->create();
        [$project, $price] = $this->asThc(function () use ($mitra): array {
            $project = Project::create(['id_project' => 'PRJ-ADMIN-0001', 'nama' => 'Project Mitra', 'mitra_id' => $mitra->id]);
            $pks = Pks::create(['mitra_id' => $mitra->id, 'nomor' => 'PKS-RAB-ADMIN', 'tanggal_mulai' => '2026-01-01', 'tanggal_berakhir' => '2026-12-31']);
            $job = PekerjaanJasa::create(['kode' => 'JASA-RAB-ADMIN', 'nama' => 'Instalasi Kabel', 'aktif' => true]);
            $price = MitraHargaJasa::create([
                'mitra_id' => $mitra->id,
                'pks_id' => $pks->id,
                'pekerjaan_jasa_id' => $job->id,
                'harga' => '125000',
                'status' => 'disetujui',
                'berlaku_mulai' => '2026-01-01',
            ]);

            return [$project, $price];
        });
        $admin = User::factory()->create([
            'mitra_id' => $mitra->id,
            'grup_id' => $this->groupWith('manage_mitra_project')->id,
        ]);

        $this->actingAs($admin)
            ->post(route('projects.rab-jasa.store', $project), ['harga_jasa_id' => $price->id, 'qty' => '4'])
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('project_rab_jasas', [
            'project_id' => $project->id,
            'mitra_id' => $mitra->id,
            'harga_jasa_mitra_id' => $price->id,
            'harga_satuan' => '125000.00',
            'dibuat_oleh' => $admin->id,
        ]);
    }

    public function test_admin_mitra_submits_baseline_proposals_that_only_thc_can_approve(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->asThc(fn (): Project => Project::create([
            'id_project' => 'PRJ-ADMIN-PLAN-0001',
            'nama' => 'Project Planning Mitra',
            'mitra_id' => $mitra->id,
        ]));
        $admin = User::factory()->create([
            'mitra_id' => $mitra->id,
            'grup_id' => $this->groupWith('manage_mitra_project')->id,
        ]);

        $this->actingAs($admin)
            ->put(route('projects.plan.update', $project), [
                'toc' => '2026-09-30',
                'plan' => [
                    ['date' => '2026-09-01', 'percent' => 50],
                    ['date' => '2026-09-30', 'percent' => 100],
                ],
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseCount('project_baselines', 0);
        $this->assertDatabaseHas('project_baseline_proposals', [
            'project_id' => $project->id,
            'status' => 'diajukan',
            'toc' => '2026-09-30',
        ]);
        $this->assertNull($project->fresh()->toc);

        $proposal = ProjectBaselineProposal::query()->firstOrFail();
        $thc = User::factory()->create([
            'mitra_id' => null,
            'grup_id' => $this->groupWith('manage_project_plan')->id,
        ]);

        $this->actingAs($thc)
            ->patch(route('projects.baseline-proposals.approve', [$project, $proposal]))
            ->assertRedirect(route('projects.show', $project));

        $this->assertSame(1, ProjectBaseline::query()->where('project_id', $project->id)->where('kind', 'original')->count());
        $this->assertSame('2026-09-30', $project->fresh()->toc->toDateString());

        $this->actingAs($admin)
            ->put(route('projects.plan.update', $project), [
                'toc' => '2026-10-15',
                'plan' => [
                    ['date' => '2026-09-15', 'percent' => 50],
                    ['date' => '2026-10-15', 'percent' => 100],
                ],
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertSame(1, ProjectBaseline::query()->where('project_id', $project->id)->where('kind', 'original')->count());
        $this->assertSame(1, ProjectBaselineProposal::query()->where('project_id', $project->id)->where('status', 'diajukan')->count());
        $this->assertSame('2026-09-30', $project->fresh()->toc->toDateString());

        $secondProposal = ProjectBaselineProposal::query()->where('status', 'diajukan')->firstOrFail();
        $this->actingAs($thc)
            ->patch(route('projects.baseline-proposals.approve', [$project, $secondProposal]))
            ->assertRedirect(route('projects.show', $project));

        $this->assertSame(1, ProjectBaseline::query()->where('project_id', $project->id)->where('kind', 'revised')->count());
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'toc' => '2026-10-15']);
    }

    public function test_admin_mitra_deactivates_users_instead_of_deleting_tenant_history(): void
    {
        $mitra = Mitra::factory()->create();
        $admin = User::factory()->create([
            'mitra_id' => $mitra->id,
            'grup_id' => $this->groupWith('manage_mitra_users')->id,
        ]);
        $user = User::factory()->create(['mitra_id' => $mitra->id, 'aktif' => true]);

        $this->actingAs($admin)
            ->delete(route('admin.users.delete', $user))
            ->assertRedirect()
            ->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'aktif' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.users.toggle', $user))
            ->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'aktif' => false]);
    }

    private function groupWith(string ...$permissions): Grup
    {
        $group = Grup::factory()->create();

        foreach ($permissions as $permission) {
            $group->izins()->attach(Izin::factory()->create(['kode' => $permission]));
        }

        return $group;
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
