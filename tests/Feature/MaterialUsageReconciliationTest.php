<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Material;
use App\Models\Mitra;
use App\Models\Project;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\MaterialInventoryService;
use App\Support\TenantDatabaseContext;
use Closure;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class MaterialUsageReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_material_usage_does_not_touch_the_book_until_thc_approves_it(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->asThc(fn (): Project => Project::query()->create([
            'id_project' => 'PRJ-2608-'.fake()->unique()->numerify('####'),
            'nama' => 'Project Pemakaian Material',
            'mitra_id' => $mitra->id,
        ]));
        $warehouse = $this->asThc(fn (): Warehouse => Warehouse::factory()->create(['mitra_id' => $mitra->id]));
        $material = Material::factory()->create();
        $thc = $this->userWithPermissions(null, 'operate_warehouse', 'approve_material_usage');
        $mitraUser = $this->userWithPermissions($mitra->id, 'create_material_usage');

        $this->asThc(fn (): mixed => app(MaterialInventoryService::class)->receive(
            $thc,
            $warehouse,
            $material->id,
            '10',
            'Penerimaan awal',
        ));

        $this->actingAs($mitraUser)
            ->post(route('projects.material-usages.store', $project), [
                'warehouse_id' => $warehouse->id,
                'material_id' => $material->id,
                'qty' => '4',
                'catatan' => 'Pemakaian untuk deployment',
            ])
            ->assertRedirect();

        $usageId = $this->asThc(fn (): int => (int) DB::table('pemakaian_materials')->value('id'));
        $this->assertDatabaseHas('pemakaian_materials', [
            'id' => $usageId,
            'status' => 'diajukan',
            'mitra_id' => $mitra->id,
            'project_id' => $project->id,
            'qty' => '4.000',
        ]);
        $this->assertDatabaseCount('material_transaksis', 1);
        $this->assertDatabaseHas('material_stoks', [
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'lokasi_tipe' => 'warehouse',
            'lokasi_id' => $warehouse->id,
            'qty' => '10.000',
        ]);

        $this->actingAs($thc)
            ->patch(route('material-usages.approve', $usageId))
            ->assertRedirect();

        $this->assertDatabaseHas('pemakaian_materials', [
            'id' => $usageId,
            'status' => 'disetujui',
            'decided_by' => $thc->id,
        ]);
        $this->assertDatabaseCount('material_transaksis', 3);
        $this->assertDatabaseHas('material_stoks', [
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'lokasi_tipe' => 'warehouse',
            'lokasi_id' => $warehouse->id,
            'qty' => '6.000',
        ]);
        $this->assertDatabaseHas('material_stoks', [
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'lokasi_tipe' => 'project',
            'lokasi_id' => $project->id,
            'qty' => '4.000',
        ]);
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
