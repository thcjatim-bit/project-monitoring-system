<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Material;
use App\Models\Mitra;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\TenantDatabaseContext;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class MaterialInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_officer_can_receive_and_issue_material_through_audited_transactions(): void
    {
        $mitra = Mitra::factory()->create();
        $warehouse = $this->asThc(fn () => Warehouse::factory()->create(['mitra_id' => $mitra->id]));
        $material = Material::factory()->create(['jenis' => 'biasa']);
        $user = $this->userWith('operate_warehouse', $mitra);
        $warehouse->users()->attach($user);

        $this->actingAs($user)
            ->post('/warehouse/stock/receive', [
                'warehouse_id' => $warehouse->id,
                'material_id' => $material->id,
                'qty' => '10',
                'reason' => 'Penerimaan awal',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post('/warehouse/stock/issue', [
                'warehouse_id' => $warehouse->id,
                'material_id' => $material->id,
                'qty' => '3',
                'reason' => 'Pemakaian lapangan',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('material_stoks', [
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'qty' => '7.000',
        ]);
        $this->assertDatabaseCount('material_transaksis', 2);
    }

    public function test_issue_is_rejected_when_it_would_make_stock_negative(): void
    {
        $mitra = Mitra::factory()->create();
        $warehouse = $this->asThc(fn () => Warehouse::factory()->create(['mitra_id' => $mitra->id]));
        $material = Material::factory()->create();
        $user = $this->userWith('operate_warehouse', $mitra);
        $warehouse->users()->attach($user);

        $this->actingAs($user)
            ->post('/warehouse/stock/issue', [
                'warehouse_id' => $warehouse->id,
                'material_id' => $material->id,
                'qty' => '1',
                'reason' => 'Tidak boleh',
            ])
            ->assertSessionHasErrors('qty');

        $this->assertDatabaseCount('material_transaksis', 0);
    }

    public function test_material_with_inactive_unit_cannot_be_used_for_new_stock_operations(): void
    {
        $mitra = Mitra::factory()->create();
        $warehouse = $this->asThc(fn () => Warehouse::factory()->create(['mitra_id' => $mitra->id]));
        $unit = Unit::query()->create(['kode' => 'M', 'nama' => 'Meter', 'aktif' => false]);
        $material = Material::factory()->create(['unit_id' => $unit->id]);
        $user = $this->userWith('operate_warehouse', $mitra);
        $warehouse->users()->attach($user);

        $this->actingAs($user)
            ->post('/warehouse/stock/receive', [
                'warehouse_id' => $warehouse->id,
                'material_id' => $material->id,
                'qty' => '10',
                'reason' => 'Tidak boleh',
            ])
            ->assertSessionHasErrors('material_id');

        $this->assertDatabaseCount('material_transaksis', 0);
    }

    public function test_stock_operations_accept_only_material_biasa(): void
    {
        $mitra = Mitra::factory()->create();
        $warehouse = $this->asThc(fn () => Warehouse::factory()->create(['mitra_id' => $mitra->id]));
        $user = $this->userWith('operate_warehouse', $mitra);
        $warehouse->users()->attach($user);

        foreach (['ber_sn', 'drum_kabel'] as $jenis) {
            $material = Material::factory()->create(['jenis' => $jenis]);

            $this->actingAs($user)
                ->post('/warehouse/stock/receive', [
                    'warehouse_id' => $warehouse->id,
                    'material_id' => $material->id,
                    'qty' => '10',
                    'reason' => 'Belum didukung',
                ])
                ->assertSessionHasErrors('material_id');

            $this->actingAs($user)
                ->post('/warehouse/stock/issue', [
                    'warehouse_id' => $warehouse->id,
                    'material_id' => $material->id,
                    'qty' => '1',
                    'reason' => 'Belum didukung',
                ])
                ->assertSessionHasErrors('material_id');
        }

        $this->assertDatabaseCount('material_transaksis', 0);
    }

    public function test_database_trigger_rejects_a_transaction_that_would_make_stock_negative(): void
    {
        $mitra = Mitra::factory()->create();
        $warehouse = $this->asThc(fn () => Warehouse::factory()->create(['mitra_id' => $mitra->id]));
        $material = Material::factory()->create(['jenis' => 'biasa']);
        $actor = $this->userWith('operate_warehouse', $mitra);

        $this->expectException(QueryException::class);

        $this->asThc(fn () => DB::table('material_transaksis')->insert([
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'qty_delta' => '-1',
            'reason' => 'Tidak boleh',
            'actor_id' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    private function userWith(string $permission, Mitra $mitra): User
    {
        $group = Grup::factory()->create();
        $group->izins()->attach(Izin::factory()->create(['kode' => $permission]));

        return User::factory()->create(['mitra_id' => $mitra->id, 'grup_id' => $group->id]);
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
