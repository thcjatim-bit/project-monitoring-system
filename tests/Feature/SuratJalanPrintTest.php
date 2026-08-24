<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Material;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\Mitra;
use App\Models\Project;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\TenantDatabaseContext;
use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewInstance;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class SuratJalanPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_print_shows_request_project_and_all_item_notes(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->asThc(fn (): Project => Project::query()->create([
            'id_project' => 'PRJ-2608-0119',
            'nama' => 'Fiberisasi Sidoarjo',
            'mitra_id' => $mitra->id,
            'status_project' => 'aktif',
        ]));
        [$origin, $destination] = $this->warehousesFor($mitra);
        $user = $this->userWithWarehousePermission($mitra);
        $origin->users()->attach($user);
        $destination->users()->attach($user);

        $requested = Material::factory()->create(['jenis' => 'biasa']);
        $foreign = Material::factory()->create(['jenis' => 'biasa']);
        $this->receiveStock($user, $origin, $requested, 10);
        $this->receiveStock($user, $origin, $foreign, 10);

        $request = $this->asThc(fn (): MaterialRequest => MaterialRequest::query()->create([
            'mitra_id' => $mitra->id,
            'project_id' => $project->id,
            'requested_by' => $user->id,
            'status' => 'disetujui',
        ]));
        $this->asThc(function () use ($request, $mitra, $requested): void {
            MaterialRequestItem::query()->create([
                'material_request_id' => $request->id,
                'mitra_id' => $mitra->id,
                'material_id' => $requested->id,
                'qty' => '4',
            ]);
        });

        $this->actingAs($user)
            ->post('/warehouse/transfers', [
                'warehouse_asal_id' => $origin->id,
                'warehouse_tujuan_id' => $destination->id,
                'material_request_id' => $request->id,
                'project_id' => $project->id,
                'tanggal' => '2026-08-15',
                'pengirim' => 'Petugas Gudang',
                'items' => [
                    ['material_id' => $requested->id, 'qty' => '4', 'catatan' => 'Catatan baris patuh'],
                    ['material_id' => $foreign->id, 'qty' => '1', 'catatan' => 'Catatan material asing'],
                ],
            ])
            ->assertRedirect();

        $suratJalanId = DB::table('surat_jalans')->latest('id')->value('id');
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });
        $provenanceWasEagerLoaded = false;
        View::composer('warehouse.surat-jalan-print', function (ViewInstance $view) use (&$provenanceWasEagerLoaded): void {
            $suratJalan = $view->getData()['suratJalan'];
            $provenanceWasEagerLoaded = $suratJalan->relationLoaded('materialRequest')
                && $suratJalan->relationLoaded('project');
        });

        $this->actingAs($user)
            ->get(route('warehouse.transfers.print', $suratJalanId))
            ->assertOk()
            ->assertSee('Request Material #'.$request->id)
            ->assertSee($project->id_project.' — '.$project->nama)
            ->assertSee('Catatan baris patuh')
            ->assertSee('Catatan material asing');

        $this->assertTrue($provenanceWasEagerLoaded);
        $this->assertSame(1, count(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'material_requests'))));
        $this->assertSame(1, count(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'projects'))));
    }

    public function test_print_omits_request_and_project_when_transfer_has_no_request_or_project(): void
    {
        [, , , $user, $suratJalanId] = $this->issueDirectTransfer();

        $this->actingAs($user)
            ->get(route('warehouse.transfers.print', $suratJalanId))
            ->assertOk()
            ->assertDontSee('Request Material')
            ->assertDontSee('Project:');
    }

    public function test_print_omits_project_when_request_has_no_project(): void
    {
        $mitra = Mitra::factory()->create();
        [$origin, $destination] = $this->warehousesFor($mitra);
        $user = $this->userWithWarehousePermission($mitra);
        $origin->users()->attach($user);
        $destination->users()->attach($user);
        $material = Material::factory()->create(['jenis' => 'biasa']);
        $this->receiveStock($user, $origin, $material, 10);

        $request = $this->asThc(fn (): MaterialRequest => MaterialRequest::query()->create([
            'mitra_id' => $mitra->id,
            'project_id' => null,
            'requested_by' => $user->id,
            'status' => 'disetujui',
        ]));
        $this->asThc(function () use ($request, $mitra, $material): void {
            MaterialRequestItem::query()->create([
                'material_request_id' => $request->id,
                'mitra_id' => $mitra->id,
                'material_id' => $material->id,
                'qty' => '4',
            ]);
        });

        $this->actingAs($user)
            ->post('/warehouse/transfers', [
                'warehouse_asal_id' => $origin->id,
                'warehouse_tujuan_id' => $destination->id,
                'material_request_id' => $request->id,
                'tanggal' => '2026-08-15',
                'pengirim' => 'Petugas Gudang',
                'items' => [['material_id' => $material->id, 'qty' => '4']],
            ])
            ->assertRedirect();

        $suratJalanId = (int) DB::table('surat_jalans')->latest('id')->value('id');

        $this->actingAs($user)
            ->get(route('warehouse.transfers.print', $suratJalanId))
            ->assertOk()
            ->assertSee('Request Material #'.$request->id)
            ->assertDontSee('Project:');
    }

    public function test_print_of_a_return_without_origin_context_omits_empty_request_and_project_rows(): void
    {
        [$origin, $destination, , $user, $suratJalanId] = $this->issueDirectTransfer();
        $this->actingAs($user)
            ->post(route('warehouse.transfers.receive', $suratJalanId))
            ->assertRedirect();

        $thc = $this->thcUserWithWarehousePermission();
        $origin->users()->attach($thc);
        $destination->users()->attach($thc);

        $this->actingAs($thc)
            ->post(route('warehouse.transfers.return', $suratJalanId), [
                'tanggal' => '2026-08-16',
                'pengirim' => 'Petugas THC',
            ])
            ->assertRedirect();

        $returnId = DB::table('surat_jalans')->where('retur_dari_id', $suratJalanId)->value('id');

        $this->actingAs($user)
            ->get(route('warehouse.transfers.print', $returnId))
            ->assertOk()
            ->assertDontSee('Request Material')
            ->assertDontSee('Project:');
    }

    public function test_print_of_a_return_omits_inherited_request_and_project_rows(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->asThc(fn (): Project => Project::query()->create([
            'id_project' => 'PRJ-2608-0122',
            'nama' => 'Project Retur',
            'mitra_id' => $mitra->id,
            'status_project' => 'aktif',
        ]));
        [$origin, $destination] = $this->warehousesFor($mitra);
        $user = $this->userWithWarehousePermission($mitra);
        $thc = $this->thcUserWithWarehousePermission();
        $origin->users()->attach($user);
        $destination->users()->attach($user);
        $origin->users()->attach($thc);
        $destination->users()->attach($thc);
        $material = Material::factory()->create(['jenis' => 'biasa']);
        $this->receiveStock($user, $origin, $material, 10);

        $request = $this->asThc(fn (): MaterialRequest => MaterialRequest::query()->create([
            'mitra_id' => $mitra->id,
            'project_id' => $project->id,
            'requested_by' => $user->id,
            'status' => 'disetujui',
        ]));
        $this->asThc(function () use ($request, $mitra, $material): void {
            MaterialRequestItem::query()->create([
                'material_request_id' => $request->id,
                'mitra_id' => $mitra->id,
                'material_id' => $material->id,
                'qty' => '4',
            ]);
        });

        $this->actingAs($user)
            ->post('/warehouse/transfers', [
                'warehouse_asal_id' => $origin->id,
                'warehouse_tujuan_id' => $destination->id,
                'material_request_id' => $request->id,
                'project_id' => $project->id,
                'tanggal' => '2026-08-15',
                'pengirim' => 'Petugas Gudang',
                'items' => [['material_id' => $material->id, 'qty' => '4']],
            ])
            ->assertRedirect();

        $originalId = (int) DB::table('surat_jalans')->latest('id')->value('id');
        $this->actingAs($user)
            ->post(route('warehouse.transfers.receive', $originalId))
            ->assertRedirect();

        $this->actingAs($thc)
            ->post(route('warehouse.transfers.return', $originalId), [
                'tanggal' => '2026-08-16',
                'pengirim' => 'Petugas THC',
            ])
            ->assertRedirect();

        $returnId = (int) DB::table('surat_jalans')->where('retur_dari_id', $originalId)->value('id');

        $this->assertDatabaseHas('surat_jalans', [
            'id' => $returnId,
            'material_request_id' => null,
            'project_id' => $project->id,
        ]);

        $this->actingAs($user)
            ->get(route('warehouse.transfers.print', $returnId))
            ->assertOk()
            ->assertDontSee('Request Material')
            ->assertDontSee('Project:');
    }

    /** @return array{Warehouse, Warehouse, Material, User, int} */
    private function issueDirectTransfer(): array
    {
        $mitra = Mitra::factory()->create();
        [$origin, $destination] = $this->warehousesFor($mitra);
        $material = Material::factory()->create(['jenis' => 'biasa']);
        $user = $this->userWithWarehousePermission($mitra);
        $origin->users()->attach($user);
        $destination->users()->attach($user);
        $this->receiveStock($user, $origin, $material, 10);

        $this->actingAs($user)
            ->post('/warehouse/transfers', [
                'warehouse_asal_id' => $origin->id,
                'warehouse_tujuan_id' => $destination->id,
                'tanggal' => '2026-08-15',
                'pengirim' => 'Petugas Gudang',
                'items' => [['material_id' => $material->id, 'qty' => '4']],
            ])
            ->assertRedirect();

        $suratJalanId = (int) DB::table('surat_jalans')->latest('id')->value('id');

        return [$origin, $destination, $material, $user, $suratJalanId];
    }

    private function receiveStock(User $user, Warehouse $origin, Material $material, int $qty): void
    {
        $this->actingAs($user)
            ->post('/warehouse/stock/receive', [
                'warehouse_id' => $origin->id,
                'material_id' => $material->id,
                'qty' => $qty,
                'reason' => 'Penerimaan awal',
            ])
            ->assertRedirect();
    }

    /** @return array{Warehouse, Warehouse} */
    private function warehousesFor(Mitra $mitra): array
    {
        return $this->asThc(fn (): array => [
            Warehouse::factory()->create(['mitra_id' => $mitra->id]),
            Warehouse::factory()->create(['mitra_id' => $mitra->id]),
        ]);
    }

    private function userWithWarehousePermission(Mitra $mitra): User
    {
        $group = Grup::factory()->create();
        $group->izins()->attach(Izin::query()->firstOrCreate(
            ['kode' => 'operate_warehouse'],
            ['nama' => 'Operate warehouse'],
        ));

        return User::factory()->create(['mitra_id' => $mitra->id, 'grup_id' => $group->id]);
    }

    private function thcUserWithWarehousePermission(): User
    {
        $group = Grup::factory()->create();
        $group->izins()->attach(Izin::query()->firstOrCreate(
            ['kode' => 'operate_warehouse'],
            ['nama' => 'Operate warehouse'],
        ));

        return User::factory()->create(['mitra_id' => null, 'grup_id' => $group->id]);
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
