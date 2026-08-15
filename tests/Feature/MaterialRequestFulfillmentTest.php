<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Material;
use App\Models\MaterialRequest;
use App\Models\Mitra;
use App\Models\SuratJalan;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\TenantDatabaseContext;
use Closure;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class MaterialRequestFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unapproved_request_cannot_be_referenced_by_a_surat_jalan(): void
    {
        $mitra = Mitra::factory()->create();
        $material = Material::factory()->create(['jenis' => 'biasa']);
        $user = $this->userWith($mitra, 'create_material_request', 'read_material_request', 'operate_warehouse');
        [$origin, $destination] = $this->warehousesFor($mitra);
        $origin->users()->attach($user);
        $destination->users()->attach($user);

        $this->actingAs($user)->post('/material-requests', [
            'items' => [['material_id' => $material->id, 'qty' => 4]],
        ])->assertRedirect('/material-requests');
        $request = MaterialRequest::query()->firstOrFail();

        $this->actingAs($user)->post('/warehouse/stock/receive', [
            'warehouse_id' => $origin->id,
            'material_id' => $material->id,
            'qty' => 4,
            'reason' => 'Penerimaan awal',
        ])->assertRedirect();

        $this->actingAs($user)
            ->post('/warehouse/transfers', [
                'warehouse_asal_id' => $origin->id,
                'warehouse_tujuan_id' => $destination->id,
                'material_request_id' => $request->id,
                'tanggal' => '2026-08-15',
                'pengirim' => 'Petugas Gudang',
                'items' => [['material_id' => $material->id, 'qty' => 4]],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount('surat_jalans', 0);
    }

    public function test_an_approved_request_can_be_fulfilled_by_multiple_surat_jalan_and_status_uses_received_qty(): void
    {
        $mitra = Mitra::factory()->create();
        $material = Material::factory()->create(['jenis' => 'biasa']);
        $user = $this->userWith($mitra, 'create_material_request', 'read_material_request', 'operate_warehouse');
        $thc = $this->userWith(null, 'approve_material_request');
        [$origin, $destination] = $this->warehousesFor($mitra);
        $origin->users()->attach($user);
        $destination->users()->attach($user);

        $this->actingAs($user)->post('/material-requests', [
            'items' => [['material_id' => $material->id, 'qty' => 6]],
        ])->assertRedirect('/material-requests');
        $request = MaterialRequest::query()->firstOrFail();

        $this->actingAs($thc)
            ->patch("/material-requests/{$request->id}/approve")
            ->assertRedirect('/material-requests');

        $this->actingAs($user)->post('/warehouse/stock/receive', [
            'warehouse_id' => $origin->id,
            'material_id' => $material->id,
            'qty' => 6,
            'reason' => 'Penerimaan awal',
        ])->assertRedirect();

        $first = $this->issueForRequest($user, $origin, $destination, $request, $material, 2);
        $this->actingAs($user)
            ->post("/warehouse/transfers/{$first->id}/receive")
            ->assertRedirect();
        $this->assertSame('terpenuhi_sebagian', $request->fresh()->status);

        $second = $this->issueForRequest($user, $origin, $destination, $request->fresh(), $material, 4);
        $this->actingAs($user)
            ->post("/warehouse/transfers/{$second->id}/receive")
            ->assertRedirect();

        $this->assertSame('selesai', $request->fresh()->status);
        $this->assertDatabaseCount('surat_jalans', 2);
        $this->assertDatabaseHas('surat_jalans', [
            'id' => $first->id,
            'material_request_id' => $request->id,
        ]);
        $this->assertDatabaseHas('surat_jalans', [
            'id' => $second->id,
            'material_request_id' => $request->id,
        ]);
    }

    public function test_a_cancelled_surat_jalan_does_not_consume_request_quantity(): void
    {
        $mitra = Mitra::factory()->create();
        $material = Material::factory()->create(['jenis' => 'biasa']);
        $user = $this->userWith($mitra, 'create_material_request', 'read_material_request', 'operate_warehouse');
        $thc = $this->userWith(null, 'approve_material_request', 'operate_warehouse');
        [$origin, $destination] = $this->warehousesFor($mitra);
        $origin->users()->attach($user);
        $destination->users()->attach($user);
        $origin->users()->attach($thc);

        $this->actingAs($user)->post('/material-requests', [
            'items' => [['material_id' => $material->id, 'qty' => 4]],
        ])->assertRedirect('/material-requests');
        $request = MaterialRequest::query()->firstOrFail();
        $this->actingAs($thc)
            ->patch("/material-requests/{$request->id}/approve")
            ->assertRedirect('/material-requests');
        $this->actingAs($user)->post('/warehouse/stock/receive', [
            'warehouse_id' => $origin->id,
            'material_id' => $material->id,
            'qty' => 4,
            'reason' => 'Penerimaan awal',
        ])->assertRedirect();

        $cancelled = $this->issueForRequest($user, $origin, $destination, $request, $material, 4);
        $this->actingAs($thc)
            ->post("/warehouse/transfers/{$cancelled->id}/cancel")
            ->assertRedirect();

        $replacement = $this->issueForRequest($user, $origin, $destination, $request, $material, 4);
        $this->actingAs($user)
            ->post("/warehouse/transfers/{$replacement->id}/receive")
            ->assertRedirect();

        $this->assertSame('selesai', $request->fresh()->status);
    }

    private function issueForRequest(User $user, Warehouse $origin, Warehouse $destination, MaterialRequest $request, Material $material, int $qty): SuratJalan
    {
        $this->actingAs($user)
            ->post('/warehouse/transfers', [
                'warehouse_asal_id' => $origin->id,
                'warehouse_tujuan_id' => $destination->id,
                'material_request_id' => $request->id,
                'tanggal' => '2026-08-15',
                'pengirim' => 'Petugas Gudang',
                'items' => [['material_id' => $material->id, 'qty' => $qty]],
            ])
            ->assertRedirect();

        return SuratJalan::query()->latest('id')->firstOrFail();
    }

    /** @return array{Warehouse, Warehouse} */
    private function warehousesFor(Mitra $mitra): array
    {
        return $this->asThc(fn (): array => [
            Warehouse::factory()->create(['mitra_id' => $mitra->id]),
            Warehouse::factory()->create(['mitra_id' => $mitra->id]),
        ]);
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
