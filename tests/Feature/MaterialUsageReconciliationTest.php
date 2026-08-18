<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Material;
use App\Models\Mitra;
use App\Models\Project;
use App\Models\ProjectRekon;
use App\Models\ProjectRekonItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Queries\ProjectRekonQuery;
use App\Services\MaterialInventoryService;
use App\Services\MaterialUsageService;
use App\Services\ProjectMaterialInstallationService;
use App\Services\ProjectRekonService;
use App\Services\ProjectStepService;
use App\Support\TenantDatabaseContext;
use Closure;
use Illuminate\Database\QueryException;
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
            ->assertRedirect(route('material-usages.index'))
            ->assertSessionDoesntHaveErrors();

        $usageId = $this->asThc(fn (): int => (int) DB::table('pemakaian_materials')->value('id'));
        $this->asThc(function () use ($usageId, $mitra, $project, $warehouse, $material): void {
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
        });

        $this->actingAs($thc)
            ->patch(route('material-usages.approve', $usageId))
            ->assertRedirect();

        $this->asThc(function () use ($usageId, $thc, $warehouse, $material, $project): void {
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
        });
    }

    public function test_manual_rekon_prefills_project_balance_and_approval_returns_material_and_closes_the_project(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->asThc(fn (): Project => Project::query()->create([
            'id_project' => 'PRJ-2608-'.fake()->unique()->numerify('####'),
            'nama' => 'Project Rekon Material',
            'mitra_id' => $mitra->id,
        ]));
        $warehouse = $this->asThc(fn (): Warehouse => Warehouse::factory()->create(['mitra_id' => $mitra->id]));
        $material = Material::factory()->create();
        $thc = $this->userWithPermissions(null, 'operate_warehouse', 'approve_material_usage', 'create_material_rekon', 'approve_material_rekon');
        $mitraUser = $this->userWithPermissions($mitra->id, 'create_material_usage');

        $this->asThc(fn (): mixed => app(MaterialInventoryService::class)->receive(
            $thc,
            $warehouse,
            $material->id,
            '10',
            'Penerimaan awal',
        ));
        $usage = $this->asMitra($mitra->id, fn () => app(MaterialUsageService::class)->submit($mitraUser, $project, [
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'qty' => '6',
        ]));
        $this->asThc(fn (): mixed => app(MaterialUsageService::class)->decide($usage, $thc, 'disetujui'));

        $rekon = $this->asThc(fn () => app(ProjectRekonService::class)->open($project, $thc, 'manual'));

        $this->asThc(function () use ($rekon, $project, $material, $warehouse): void {
            $this->assertSame('diajukan', $rekon->status);
            $this->assertStringStartsWith('REK-2608-', $rekon->nomor);
            $this->assertDatabaseHas('project_rekon_items', [
                'project_rekon_id' => $rekon->id,
                'material_id' => $material->id,
                'warehouse_id' => $warehouse->id,
                'keluar_gudang' => '6.000',
                'terpasang' => '0.000',
                'sisa_project' => '6.000',
                'dikembalikan' => '0.000',
                'hilang_rusak' => '0.000',
            ]);
            $this->assertDatabaseHas('projects', [
                'id' => $project->id,
                'status_project' => 'aktif',
            ]);
        });

        $item = $this->asThc(fn (): ProjectRekonItem => ProjectRekonItem::query()->where('project_rekon_id', $rekon->id)->firstOrFail());
        $this->asThc(fn (): bool => $item->update(['dikembalikan' => '6']));
        $this->asThc(fn (): mixed => app(ProjectRekonService::class)->approve($rekon, $thc));

        $this->asThc(function () use ($rekon, $project, $warehouse, $material, $thc, $item): void {
            $this->assertDatabaseHas('project_rekons', [
                'id' => $rekon->id,
                'status' => 'disetujui',
                'approved_by' => $thc->id,
            ]);
            $this->assertDatabaseHas('projects', [
                'id' => $project->id,
                'status_project' => 'selesai',
            ]);
            $this->assertDatabaseHas('material_stoks', [
                'warehouse_id' => $warehouse->id,
                'material_id' => $material->id,
                'lokasi_tipe' => 'warehouse',
                'lokasi_id' => $warehouse->id,
                'qty' => '10.000',
            ]);
            $this->assertDatabaseHas('material_stoks', [
                'warehouse_id' => $warehouse->id,
                'material_id' => $material->id,
                'lokasi_tipe' => 'project',
                'lokasi_id' => $project->id,
                'qty' => '0.000',
            ]);
            $this->assertDatabaseCount('material_transaksis', 5);
            $this->assertDatabaseHas('material_transaksis', [
                'project_rekon_item_id' => $item->id,
                'jenis_transaksi' => 'rekon_kembali',
                'lokasi_tipe' => 'project',
                'qty_delta' => '-6.000',
            ]);
        });
    }

    public function test_cancellation_and_rejection_leave_the_stock_book_unchanged(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->asThc(fn (): Project => Project::query()->create([
            'id_project' => 'PRJ-2608-'.fake()->unique()->numerify('####'),
            'nama' => 'Project Penolakan Pemakaian Material',
            'mitra_id' => $mitra->id,
        ]));
        $warehouse = $this->asThc(fn (): Warehouse => Warehouse::factory()->create(['mitra_id' => $mitra->id]));
        $material = Material::factory()->create();
        $thc = $this->userWithPermissions(null, 'operate_warehouse', 'approve_material_usage');
        $mitraUser = $this->userWithPermissions($mitra->id, 'create_material_usage');

        $this->asThc(fn (): mixed => app(MaterialInventoryService::class)->receive($thc, $warehouse, $material->id, '10', 'Penerimaan awal'));
        $cancelled = $this->asMitra($mitra->id, fn () => app(MaterialUsageService::class)->submit($mitraUser, $project, [
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'qty' => '2',
        ]));
        $this->asMitra($mitra->id, fn () => app(MaterialUsageService::class)->cancel($cancelled, $mitraUser));

        $rejected = $this->asMitra($mitra->id, fn () => app(MaterialUsageService::class)->submit($mitraUser, $project, [
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'qty' => '3',
        ]));
        $this->asThc(fn () => app(MaterialUsageService::class)->decide($rejected, $thc, 'ditolak', 'Tidak sesuai kebutuhan project'));

        $this->asThc(function () use ($cancelled, $rejected, $warehouse, $material): void {
            $this->assertDatabaseHas('pemakaian_materials', ['id' => $cancelled->id, 'status' => 'dibatalkan']);
            $this->assertDatabaseHas('pemakaian_materials', ['id' => $rejected->id, 'status' => 'ditolak']);
            $this->assertDatabaseCount('material_transaksis', 1);
            $this->assertDatabaseHas('material_stoks', [
                'warehouse_id' => $warehouse->id,
                'material_id' => $material->id,
                'lokasi_tipe' => 'warehouse',
                'lokasi_id' => $warehouse->id,
                'qty' => '10.000',
            ]);
        });
    }

    public function test_rekon_correction_reverses_old_accounting_and_preserves_history(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->asThc(fn (): Project => Project::query()->create([
            'id_project' => 'PRJ-2608-'.fake()->unique()->numerify('####'),
            'nama' => 'Project Koreksi Rekon Material',
            'mitra_id' => $mitra->id,
        ]));
        $warehouse = $this->asThc(fn (): Warehouse => Warehouse::factory()->create(['mitra_id' => $mitra->id]));
        $material = Material::factory()->create();
        $thc = $this->userWithPermissions(null, 'operate_warehouse', 'approve_material_usage', 'create_material_rekon', 'approve_material_rekon');
        $mitraUser = $this->userWithPermissions($mitra->id, 'create_material_usage');

        $this->asThc(fn (): mixed => app(MaterialInventoryService::class)->receive($thc, $warehouse, $material->id, '10', 'Penerimaan awal'));
        $usage = $this->asMitra($mitra->id, fn () => app(MaterialUsageService::class)->submit($mitraUser, $project, [
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'qty' => '6',
        ]));
        $this->asThc(fn (): mixed => app(MaterialUsageService::class)->decide($usage, $thc, 'disetujui'));

        $first = $this->asThc(fn () => app(ProjectRekonService::class)->open($project, $thc));
        $firstItem = $this->asThc(fn (): ProjectRekonItem => ProjectRekonItem::query()->where('project_rekon_id', $first->id)->firstOrFail());
        $this->asThc(fn (): bool => $firstItem->update(['dikembalikan' => '6']));
        $this->asThc(fn (): mixed => app(ProjectRekonService::class)->approve($first, $thc));

        $second = $this->asThc(fn () => app(ProjectRekonService::class)->open($project, $thc));
        $secondItem = $this->asThc(fn (): ProjectRekonItem => ProjectRekonItem::query()->where('project_rekon_id', $second->id)->firstOrFail());
        $this->asThc(fn (): bool => $secondItem->update([
            'dikembalikan' => '4',
            'hilang_rusak' => '2',
            'kategori_hilang_rusak' => 'waste_wajar',
        ]));
        $this->asThc(fn (): mixed => app(ProjectRekonService::class)->approve($second, $thc));

        $this->asThc(function () use ($first, $second, $secondItem, $project, $warehouse, $material): void {
            $this->assertDatabaseHas('project_rekons', ['id' => $first->id, 'status' => 'disetujui']);
            $this->assertDatabaseHas('project_rekons', ['id' => $second->id, 'status' => 'disetujui', 'koreksi_dari_id' => $first->id]);
            $this->assertDatabaseHas('projects', ['id' => $project->id, 'status_project' => 'selesai']);
            $this->assertDatabaseHas('material_stoks', [
                'warehouse_id' => $warehouse->id,
                'material_id' => $material->id,
                'lokasi_tipe' => 'warehouse',
                'lokasi_id' => $warehouse->id,
                'qty' => '8.000',
            ]);
            $this->assertDatabaseHas('material_stoks', [
                'warehouse_id' => $warehouse->id,
                'material_id' => $material->id,
                'lokasi_tipe' => 'project',
                'lokasi_id' => $project->id,
                'qty' => '0.000',
            ]);
            $this->assertDatabaseHas('material_transaksis', [
                'project_rekon_item_id' => $secondItem->id,
                'jenis_transaksi' => 'rekon_waste',
                'lokasi_tipe' => 'project',
                'qty_delta' => '-2.000',
            ]);
            $this->assertDatabaseCount('project_rekons', 2);
        });

        $readModel = $this->asThc(fn (): array => app(ProjectRekonQuery::class)->forProject($project));
        $this->assertSame($second->id, $readModel['active_rekon']['id']);
        $this->assertCount(2, $readModel['rekons']);
        $this->assertSame(4.0, $readModel['active_rekon']['items'][0]['dikembalikan']);
        $this->assertArrayNotHasKey('approved_by', $readModel['active_rekon']);
        $this->assertArrayNotHasKey('catatan', $readModel['active_rekon']['items'][0]);
    }

    public function test_go_live_opens_one_rekon_and_does_not_duplicate_after_approval(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->asThc(fn (): Project => Project::query()->create([
            'id_project' => 'PRJ-2608-'.fake()->unique()->numerify('####'),
            'nama' => 'Project GO Live Rekon Material',
            'mitra_id' => $mitra->id,
        ]));
        $thc = $this->userWithPermissions(null, 'create_material_rekon', 'approve_material_rekon', 'update_project_step');

        $this->asThc(fn (): mixed => app(ProjectStepService::class)->move($project, $thc, 'go_live', 'completed'));

        $this->asThc(function () use ($project): void {
            $this->assertDatabaseHas('project_rekons', [
                'project_id' => $project->id,
                'source' => 'go_live',
                'status' => 'diajukan',
            ]);
            $this->assertDatabaseCount('project_rekons', 1);
        });
        $rekon = $this->asThc(fn (): ProjectRekon => ProjectRekon::query()->where('project_id', $project->id)->firstOrFail());
        $this->asThc(fn (): mixed => app(ProjectRekonService::class)->approve($rekon, $thc));
        $this->asThc(fn (): ?object => app(ProjectRekonService::class)->openForGoLive($project, $thc));

        $this->asThc(function () use ($project): void {
            $this->assertDatabaseHas('project_rekons', ['project_id' => $project->id, 'source' => 'go_live', 'status' => 'disetujui']);
            $this->assertDatabaseHas('projects', ['id' => $project->id, 'status_project' => 'selesai']);
            $this->assertDatabaseCount('project_rekons', 1);
        });
    }

    public function test_installation_writes_project_to_installed_and_rekon_reads_the_installed_quantity(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->asThc(fn (): Project => Project::query()->create([
            'id_project' => 'PRJ-2608-'.fake()->unique()->numerify('####'),
            'nama' => 'Project Material Terpasang',
            'mitra_id' => $mitra->id,
        ]));
        $warehouse = $this->asThc(fn (): Warehouse => Warehouse::factory()->create(['mitra_id' => $mitra->id]));
        $material = Material::factory()->create();
        $thc = $this->userWithPermissions(null, 'operate_warehouse', 'approve_material_usage', 'create_material_rekon');
        $mitraUser = $this->userWithPermissions($mitra->id, 'create_material_usage', 'report_project_progress');

        $this->asThc(fn (): mixed => app(MaterialInventoryService::class)->receive($thc, $warehouse, $material->id, '10', 'Penerimaan awal'));
        $usage = $this->asMitra($mitra->id, fn () => app(MaterialUsageService::class)->submit($mitraUser, $project, [
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'qty' => '6',
        ]));
        $this->asThc(fn (): mixed => app(MaterialUsageService::class)->decide($usage, $thc, 'disetujui'));
        $this->asMitra($mitra->id, fn (): array => app(ProjectMaterialInstallationService::class)->record($mitraUser, $project, [
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'qty' => '2',
        ]));

        $rekon = $this->asThc(fn () => app(ProjectRekonService::class)->open($project, $thc));
        $this->asThc(function () use ($project, $warehouse, $material, $rekon): void {
            $this->assertDatabaseCount('material_transaksis', 5);
            $this->assertDatabaseHas('material_stoks', [
                'warehouse_id' => $warehouse->id,
                'material_id' => $material->id,
                'lokasi_tipe' => 'project',
                'lokasi_id' => $project->id,
                'qty' => '4.000',
            ]);
            $this->assertDatabaseHas('material_stoks', [
                'warehouse_id' => $warehouse->id,
                'material_id' => $material->id,
                'lokasi_tipe' => 'terpasang',
                'lokasi_id' => $project->id,
                'qty' => '2.000',
            ]);
            $this->assertDatabaseHas('project_rekon_items', [
                'project_rekon_id' => $rekon->id,
                'keluar_gudang' => '6.000',
                'terpasang' => '2.000',
                'sisa_project' => '4.000',
            ]);
        });
    }

    public function test_mitra_cannot_read_or_reassign_another_tenants_usage_and_rekon_sources(): void
    {
        $mitraA = Mitra::factory()->create();
        $mitraB = Mitra::factory()->create();
        $projectA = $this->asThc(fn (): Project => Project::query()->create([
            'id_project' => 'PRJ-2608-'.fake()->unique()->numerify('####'),
            'nama' => 'Project Tenant A',
            'mitra_id' => $mitraA->id,
        ]));
        $projectB = $this->asThc(fn (): Project => Project::query()->create([
            'id_project' => 'PRJ-2608-'.fake()->unique()->numerify('####'),
            'nama' => 'Project Tenant B',
            'mitra_id' => $mitraB->id,
        ]));
        $warehouseA = $this->asThc(fn (): Warehouse => Warehouse::factory()->create(['mitra_id' => $mitraA->id]));
        $warehouseB = $this->asThc(fn (): Warehouse => Warehouse::factory()->create(['mitra_id' => $mitraB->id]));
        $material = Material::factory()->create();
        $thc = $this->userWithPermissions(null, 'create_material_rekon');
        $userA = $this->userWithPermissions($mitraA->id, 'create_material_usage');
        $userB = $this->userWithPermissions($mitraB->id, 'create_material_usage');

        $usageA = $this->asMitra($mitraA->id, fn () => app(MaterialUsageService::class)->submit($userA, $projectA, [
            'warehouse_id' => $warehouseA->id,
            'material_id' => $material->id,
            'qty' => '1',
        ]));
        $usageB = $this->asMitra($mitraB->id, fn () => app(MaterialUsageService::class)->submit($userB, $projectB, [
            'warehouse_id' => $warehouseB->id,
            'material_id' => $material->id,
            'qty' => '1',
        ]));
        $rekonA = $this->asThc(fn () => app(ProjectRekonService::class)->open($projectA, $thc));
        $rekonB = $this->asThc(fn () => app(ProjectRekonService::class)->open($projectB, $thc));

        $this->asMitra($mitraA->id, function () use ($usageA, $usageB, $rekonA, $rekonB, $mitraB): void {
            $this->assertSame([(int) $usageA->id], DB::table('pemakaian_materials')->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all());
            $this->assertSame([(int) $rekonA->id], DB::table('project_rekons')->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all());

            try {
                DB::table('pemakaian_materials')->where('id', $usageA->id)->update(['mitra_id' => $mitraB->id]);
                $this->fail('Expected tenant RLS to reject a cross-tenant usage update.');
            } catch (QueryException $exception) {
                $this->assertSame('42501', (string) $exception->getCode());
            }

            try {
                DB::table('project_rekons')->where('id', $rekonA->id)->update(['mitra_id' => $mitraB->id]);
                $this->fail('Expected tenant RLS to reject a cross-tenant Rekon update.');
            } catch (QueryException $exception) {
                $this->assertSame('42501', (string) $exception->getCode());
            }

            $this->assertFalse(DB::table('pemakaian_materials')->where('id', $usageB->id)->exists());
            $this->assertFalse(DB::table('project_rekons')->where('id', $rekonB->id)->exists());
        });
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

    private function asMitra(int $mitraId, Closure $callback): mixed
    {
        app(TenantDatabaseContext::class)->set($mitraId, false);

        try {
            return $callback();
        } finally {
            app(TenantDatabaseContext::class)->set(null, false);
        }
    }
}
