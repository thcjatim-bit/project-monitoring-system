<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Material;
use App\Models\Mitra;
use App\Models\MitraHargaJasa;
use App\Models\PekerjaanJasa;
use App\Models\Pks;
use App\Models\Project;
use App\Models\ProjectRekon;
use App\Models\PemakaianMaterial;
use App\Models\User;
use App\Models\Warehouse;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ProjectWorkflowUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_control_room_links_to_each_project_workflow_and_shows_permission_aware_installation_entry(): void
    {
        $mitra = Mitra::factory()->create();
        $user = $this->userWithPermissions($mitra->id, 'read_project', 'report_project_progress', 'read_material_usage', 'create_material_usage', 'read_material_rekon');
        $project = $this->projectFor($mitra);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee(route('projects.planning.index', $project), false)
            ->assertSee(route('projects.material-usages.index', $project), false)
            ->assertSee(route('projects.rekons.index', $project), false)
            ->assertSee('Material Installation')
            ->assertSee('Belum ada saldo Material Project yang dapat dipasang.');
    }

    public function test_thc_can_open_planning_workspace_with_rab_baseline_and_variation_order_workflows(): void
    {
        $mitra = Mitra::factory()->create();
        $thc = $this->userWithPermissions(null, 'read_project', 'manage_project_plan');
        $project = $this->projectFor($mitra);
        $job = $this->asThc(fn (): PekerjaanJasa => PekerjaanJasa::create(['kode' => 'JASA-UI-81', 'nama' => 'Penarikan Kabel UI', 'aktif' => true]));
        $pks = $this->asThc(fn (): Pks => Pks::create([
            'mitra_id' => $mitra->id,
            'nomor' => 'PKS-UI-81',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_berakhir' => '2026-12-31',
        ]));
        $price = $this->asThc(fn (): MitraHargaJasa => MitraHargaJasa::create([
            'mitra_id' => $mitra->id,
            'pks_id' => $pks->id,
            'pekerjaan_jasa_id' => $job->id,
            'harga' => '125000.00',
            'status' => 'disetujui',
            'berlaku_mulai' => '2026-01-01',
        ]));

        $this->actingAs($thc)
            ->get(route('projects.planning.index', $project))
            ->assertOk()
            ->assertSee('Workspace Perencanaan Project')
            ->assertSee('RAB Jasa')
            ->assertSee($job->nama)
            ->assertSee('Baseline / TOC')
            ->assertSee('Variation Order')
            ->assertSee(route('projects.rab-jasa.store', $project), false)
            ->assertSee(route('projects.plan.update', $project), false)
            ->assertSee(route('projects.variation-orders.store', $project), false)
            ->assertSee('value="'.$price->id.'"', false);
    }

    public function test_mitra_can_open_project_material_usage_list_and_each_usage_has_a_detail_link(): void
    {
        $mitra = Mitra::factory()->create();
        $user = $this->userWithPermissions($mitra->id, 'read_project', 'read_material_usage', 'create_material_usage');
        $project = $this->projectFor($mitra);
        $warehouse = $this->asThc(fn (): Warehouse => Warehouse::factory()->create(['mitra_id' => $mitra->id]));
        $material = Material::factory()->create();
        $usage = $this->asThc(fn (): PemakaianMaterial => PemakaianMaterial::create([
            'mitra_id' => $mitra->id,
            'project_id' => $project->id,
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'requested_by' => $user->id,
            'qty' => '2.000',
            'status' => 'diajukan',
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $this->actingAs($user)
            ->get(route('projects.material-usages.index', $project))
            ->assertOk()
            ->assertSee('Pemakaian Material')
            ->assertSee('Daftar pengajuan untuk '.$project->id_project)
            ->assertSee(route('material-usages.show', $usage), false)
            ->assertSee('Pending / diajukan');
    }

    public function test_rekon_workspace_has_project_back_link_and_detail_route(): void
    {
        $mitra = Mitra::factory()->create();
        $user = $this->userWithPermissions($mitra->id, 'read_project', 'read_material_rekon');
        $project = $this->projectFor($mitra);
        $rekon = $this->asThc(fn (): ProjectRekon => ProjectRekon::create([
            'mitra_id' => $mitra->id,
            'project_id' => $project->id,
            'nomor' => 'REK-UI-81',
            'source' => 'manual',
            'status' => 'diajukan',
        ]));

        $this->actingAs($user)
            ->get(route('projects.rekons.index', $project))
            ->assertOk()
            ->assertSee(route('projects.show', $project), false)
            ->assertSee(route('project-rekons.show', $rekon), false);

        $this->actingAs($user)
            ->get(route('project-rekons.show', $rekon))
            ->assertOk()
            ->assertSee('Detail Rekon Material')
            ->assertSee($rekon->nomor);
    }

    private function projectFor(Mitra $mitra): Project
    {
        return $this->asThc(fn (): Project => Project::create([
            'id_project' => 'PRJ-UI-81-'.fake()->unique()->numerify('####'),
            'nama' => 'Project Workflow UI',
            'mitra_id' => $mitra->id,
        ]));
    }

    private function userWithPermissions(?int $mitraId, string ...$permissions): User
    {
        $group = Grup::factory()->create();
        foreach ($permissions as $permission) {
            $group->izins()->attach(Izin::query()->firstOrCreate(['kode' => $permission], ['nama' => $permission]));
        }

        return User::factory()->create(['mitra_id' => $mitraId, 'grup_id' => $group->id]);
    }

    private function asThc(\Closure $callback): mixed
    {
        app(\App\Support\TenantDatabaseContext::class)->set(null, true);

        try {
            return $callback();
        } finally {
            app(\App\Support\TenantDatabaseContext::class)->set(null, false);
        }
    }
}
