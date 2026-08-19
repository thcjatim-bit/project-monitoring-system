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

class ApplicationShellNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_thc_shell_lists_each_existing_production_landing_page_without_prototype_or_api_links(): void
    {
        $thc = $this->userWithPermissions(
            null,
            'read_dashboard',
            'read_project',
            'manage_mitras',
            'manage_users',
            'read_master_data',
            'manage_warehouses',
            'operate_warehouse',
            'read_material_request',
            'read_material_usage',
            'read_material_rekon',
        );

        $this->actingAs($thc)
            ->get('/admin/materials')
            ->assertOk()
            ->assertSee('PMS · THC')
            ->assertSee('Command Center')
            ->assertSee('Project')
            ->assertSee('Master Data')
            ->assertSee('Mitra')
            ->assertSee('User')
            ->assertSee('Material')
            ->assertSee('Unit')
            ->assertSee('PoP')
            ->assertSee('Pekerjaan Jasa')
            ->assertSee('Penugasan Warehouse')
            ->assertSee('Surat Jalan &amp; Transit', false)
            ->assertSee('Request Material')
            ->assertSee('Pemakaian Material')
            ->assertSee('href="'.route('admin.master.index', ['entity' => 'units']).'"', false)
            ->assertSee('href="'.route('admin.master.index', ['entity' => 'pops']).'"', false)
            ->assertSee('href="'.route('admin.master.index', ['entity' => 'pekerjaan-jasa']).'"', false)
            ->assertDontSee('/prototype/')
            ->assertDontSee('/api/v1');
    }

    public function test_shell_filters_landing_pages_by_permission_and_direct_url_remains_forbidden(): void
    {
        $thc = $this->userWithPermissions(null, 'read_master_data');

        $this->actingAs($thc)
            ->get('/admin/materials')
            ->assertOk()
            ->assertSee('Material')
            ->assertSee('Unit')
            ->assertSee('PoP')
            ->assertSee('Pekerjaan Jasa')
            ->assertDontSee('href="'.route('dashboard').'"', false)
            ->assertDontSee('href="'.route('projects.index').'"', false)
            ->assertDontSee('href="'.route('admin.mitras').'"', false)
            ->assertDontSee('href="'.route('admin.users').'"', false)
            ->assertDontSee('href="'.route('admin.warehouses').'"', false)
            ->assertDontSee('href="'.route('warehouse.transit').'"', false)
            ->assertDontSee('href="'.route('material-requests.index').'"', false)
            ->assertDontSee('href="'.route('material-usages.index').'"', false);

        $this->actingAs($thc)
            ->get('/projects')
            ->assertForbidden();

        $unauthorized = $this->userWithPermissions(null);
        foreach (['units', 'pops', 'pekerjaan-jasa'] as $entity) {
            $this->actingAs($unauthorized)
                ->get(route('admin.master.index', ['entity' => $entity]))
                ->assertForbidden();
        }

        $this->actingAs($this->userWithPermissions(null, 'read_master_data'))
            ->get(route('admin.master.index', ['entity' => 'units']))
            ->assertOk()
            ->assertSee('aria-current="page"', false);
    }

    public function test_mitra_landing_uses_mitra_persona_and_only_shows_its_production_workflows(): void
    {
        $mitra = Mitra::factory()->create();
        $user = $this->userWithPermissions(
            $mitra->id,
            'read_project',
            'read_material_request',
            'read_material_usage',
            'read_material_rekon',
        );

        $this->actingAs($user)
            ->get('/projects')
            ->assertOk()
            ->assertSee('User Mitra')
            ->assertSee('Cakupan data milik Mitra')
            ->assertSee('Project')
            ->assertSee('Request Material')
            ->assertSee('Pemakaian Material')
            ->assertDontSee('Command Center')
            ->assertDontSee('Mitra &amp; User', false)
            ->assertDontSee('Penugasan Warehouse');
    }

    public function test_nested_workflow_user_keeps_a_safe_shell_home_fallback(): void
    {
        $project = $this->asThc(fn (): Project => Project::query()->create([
            'id_project' => 'PRJ-2608-0001',
            'nama' => 'Project fallback shell',
            'mitra_id' => Mitra::factory()->create()->id,
            'status_project' => 'aktif',
            'toc' => '2026-09-01',
        ]));
        $thc = $this->userWithPermissions(null, 'read_material_rekon');
        $rekonUrl = route('projects.rekons.index', $project);

        $this->actingAs($thc)
            ->get($rekonUrl)
            ->assertOk()
            ->assertSee('class="app-shell__brand" href="'.$rekonUrl.'"', false)
            ->assertSee('Project');
    }

    public function test_project_control_room_links_to_rekon_material_when_allowed(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->asThc(fn (): Project => Project::query()->create([
            'id_project' => 'PRJ-2608-0002',
            'nama' => 'Project Rekon discoverability',
            'mitra_id' => $mitra->id,
            'status_project' => 'aktif',
            'toc' => '2026-09-01',
        ]));
        $user = $this->userWithPermissions($mitra->id, 'read_project', 'read_material_rekon');

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('href="'.route('projects.rekons.index', $project).'"', false)
            ->assertSee('Rekon Material');
    }

    private function userWithPermissions(?int $mitraId, string ...$permissions): User
    {
        $group = Grup::factory()->create();
        $group->izins()->attach(collect($permissions)->map(
            fn (string $permission): int => Izin::query()->firstOrCreate(
                ['kode' => $permission],
                ['nama' => $permission],
            )->id,
        ));

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
