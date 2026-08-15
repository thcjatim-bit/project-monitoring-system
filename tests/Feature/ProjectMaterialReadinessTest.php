<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Material;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\Mitra;
use App\Models\Project;
use App\Models\ProjectRabMaterial;
use App\Models\SuratJalan;
use App\Models\SuratJalanItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\TenantDatabaseContext;
use Closure;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ProjectMaterialReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_control_room_calculates_readiness_from_received_material_and_excludes_transit(): void
    {
        $mitra = Mitra::factory()->create();
        $material = Material::factory()->create();
        $project = $this->projectFor($mitra);
        $thc = $this->userWith(null, 'read_project', 'read_project_material', 'manage_project_material');

        $this->actingAs($thc)
            ->post(route('projects.rab-material.store', $project), [
                'material_id' => $material->id,
                'qty' => '10',
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->asThc(function () use ($mitra, $material, $project, $thc): void {
            $origin = Warehouse::factory()->create(['mitra_id' => $mitra->id]);
            $destination = Warehouse::factory()->create(['mitra_id' => $mitra->id]);
            $request = MaterialRequest::query()->create([
                'mitra_id' => $mitra->id,
                'project_id' => $project->id,
                'requested_by' => $thc->id,
                'status' => 'disetujui',
            ]);
            MaterialRequestItem::query()->create([
                'material_request_id' => $request->id,
                'mitra_id' => $mitra->id,
                'material_id' => $material->id,
                'qty' => 10,
            ]);
            $suratJalan = SuratJalan::query()->create([
                'nomor' => 'SJ-TEST-READINESS',
                'tanggal' => '2026-08-16',
                'warehouse_asal_id' => $origin->id,
                'warehouse_tujuan_id' => $destination->id,
                'mitra_id' => $mitra->id,
                'project_id' => $project->id,
                'material_request_id' => $request->id,
                'issued_by' => $thc->id,
                'issued_at' => now(),
                'status' => 'terbit',
                'pengirim' => 'THC',
            ]);
            SuratJalanItem::query()->create([
                'surat_jalan_id' => $suratJalan->id,
                'mitra_id' => $mitra->id,
                'material_id' => $material->id,
                'qty' => 10,
                'qty_diterima' => 4,
                'qty_diretur' => 1,
            ]);
        });

        $this->actingAs($thc)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('30.00%')
            ->assertSee('Transit')
            ->assertSee('Request Material');
    }

    public function test_control_room_exposes_an_empty_state_when_rab_material_is_not_defined(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra);
        $user = $this->userWith($mitra, 'read_project', 'read_project_material');

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Kebutuhan RAB Material belum disusun');
    }

    public function test_mitra_cannot_open_another_mitras_project_material_panel_or_create_requirement(): void
    {
        $mitraA = Mitra::factory()->create();
        $mitraB = Mitra::factory()->create();
        $projectB = $this->projectFor($mitraB);
        $userA = $this->userWith($mitraA, 'read_project', 'manage_project_material');
        $material = Material::factory()->create();

        $this->actingAs($userA)
            ->get(route('projects.show', $projectB))
            ->assertNotFound();

        $this->actingAs($userA)
            ->post(route('projects.rab-material.store', $projectB), [
                'material_id' => $material->id,
                'qty' => 3,
            ])
            ->assertNotFound();

        $this->asThc(fn (): bool => ! ProjectRabMaterial::query()->exists());
    }

    public function test_material_panel_is_hidden_without_material_read_permission(): void
    {
        $mitra = Mitra::factory()->create();
        $material = Material::factory()->create();
        $project = $this->projectFor($mitra);
        $this->asThc(fn (): ProjectRabMaterial => ProjectRabMaterial::query()->create([
            'mitra_id' => $mitra->id,
            'project_id' => $project->id,
            'material_id' => $material->id,
            'qty' => 5,
        ]));
        $user = $this->userWith($mitra, 'read_project');

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Terbatas')
            ->assertDontSee($material->nama);
    }

    private function projectFor(Mitra $mitra): Project
    {
        return $this->asThc(fn (): Project => Project::query()->create([
            'id_project' => 'PRJ-READINESS-'.fake()->unique()->numerify('####'),
            'nama' => 'Project Material Readiness',
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
