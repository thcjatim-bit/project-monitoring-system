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

    public function test_identity_material_operations_require_the_matching_identity_field(): void
    {
        $mitra = Mitra::factory()->create();
        $warehouse = $this->asThc(fn () => Warehouse::factory()->create(['mitra_id' => $mitra->id]));
        $user = $this->userWith('operate_warehouse', $mitra);
        $warehouse->users()->attach($user);

        foreach (['ber_sn', 'drum_kabel'] as $jenis) {
            $material = Material::factory()->create(['jenis' => $jenis]);
            $field = $jenis === 'ber_sn' ? 'serial_number' : 'drum_id';

            $this->actingAs($user)
                ->post('/warehouse/stock/receive', [
                    'warehouse_id' => $warehouse->id,
                    'material_id' => $material->id,
                    'qty' => '10',
                    'reason' => 'Belum didukung',
                ])
                ->assertSessionHasErrors($field);

            $this->actingAs($user)
                ->post('/warehouse/stock/issue', [
                    'warehouse_id' => $warehouse->id,
                    'material_id' => $material->id,
                    'qty' => '1',
                    'reason' => 'Belum didukung',
                ])
                ->assertSessionHasErrors($field);
        }

        $this->assertDatabaseCount('material_transaksis', 0);
    }

    public function test_drum_split_creates_a_traceable_child_and_conserves_remaining_meters(): void
    {
        $mitra = Mitra::factory()->create();
        $warehouse = $this->asThc(fn () => Warehouse::factory()->create(['mitra_id' => $mitra->id]));
        $material = Material::factory()->create(['jenis' => 'drum_kabel']);
        $user = $this->userWith('operate_warehouse', $mitra);
        $warehouse->users()->attach($user);

        $this->actingAs($user)
            ->post('/warehouse/stock/receive', [
                'warehouse_id' => $warehouse->id,
                'material_id' => $material->id,
                'drum_id' => 'DRM-00042',
                'qty' => '2000',
                'reason' => 'Penerimaan drum',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post('/warehouse/stock/drum-split', [
                'warehouse_id' => $warehouse->id,
                'drum_id' => 'DRM-00042',
                'qty' => '300',
                'reason' => 'Potong kabel',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('drums', [
            'drum_id' => 'DRM-00042',
            'panjang_awal' => '2000.000',
            'sisa' => '1700.000',
        ]);
        $this->assertDatabaseHas('drums', [
            'drum_id' => 'DRM-00042-1',
            'panjang_awal' => '300.000',
            'sisa' => '300.000',
        ]);
        $this->assertDatabaseHas('material_transaksis', ['drum_id' => DB::table('drums')->where('drum_id', 'DRM-00042')->value('id'), 'qty_delta' => '-300.000']);
        $this->assertDatabaseHas('material_transaksis', ['drum_id' => DB::table('drums')->where('drum_id', 'DRM-00042-1')->value('id'), 'qty_delta' => '300.000']);
        $this->assertDatabaseHas('material_stoks', ['warehouse_id' => $warehouse->id, 'material_id' => $material->id, 'qty' => '2000.000']);
    }

    public function test_drum_split_is_rejected_when_the_parent_has_insufficient_remaining_meters(): void
    {
        $mitra = Mitra::factory()->create();
        $warehouse = $this->asThc(fn () => Warehouse::factory()->create(['mitra_id' => $mitra->id]));
        $material = Material::factory()->create(['jenis' => 'drum_kabel']);
        $user = $this->userWith('operate_warehouse', $mitra);
        $warehouse->users()->attach($user);

        $this->actingAs($user)->post('/warehouse/stock/receive', [
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'drum_id' => 'DRM-00043',
            'qty' => '100',
            'reason' => 'Penerimaan drum',
        ]);

        $this->actingAs($user)
            ->post('/warehouse/stock/drum-split', [
                'warehouse_id' => $warehouse->id,
                'drum_id' => 'DRM-00043',
                'qty' => '101',
                'reason' => 'Terlalu panjang',
            ])
            ->assertSessionHasErrors('qty');

        $this->assertDatabaseCount('drums', 1);
        $this->assertDatabaseCount('material_transaksis', 1);
    }

    public function test_warehouse_officer_can_receive_and_issue_serialised_material_by_serial_number(): void
    {
        $mitra = Mitra::factory()->create();
        $warehouse = $this->asThc(fn () => Warehouse::factory()->create(['mitra_id' => $mitra->id]));
        $material = Material::factory()->create(['jenis' => 'ber_sn']);
        $user = $this->userWith('operate_warehouse', $mitra);
        $warehouse->users()->attach($user);

        $this->actingAs($user)
            ->post('/warehouse/stock/receive', [
                'warehouse_id' => $warehouse->id,
                'material_id' => $material->id,
                'serial_number' => 'SN-001',
                'qty' => '1',
                'reason' => 'Penerimaan SN',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post('/warehouse/stock/issue', [
                'warehouse_id' => $warehouse->id,
                'material_id' => $material->id,
                'serial_number' => 'SN-001',
                'qty' => '1',
                'reason' => 'Pengeluaran SN',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('material_sns', [
            'material_id' => $material->id,
            'serial_number' => 'SN-001',
            'status' => 'keluar',
        ]);
        $this->assertDatabaseHas('material_transaksis', [
            'material_id' => $material->id,
            'material_sn_id' => DB::table('material_sns')->where('serial_number', 'SN-001')->value('id'),
            'qty_delta' => '-1.000',
        ]);
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

    public function test_database_trigger_rejects_a_serialised_transaction_without_its_serial_number(): void
    {
        $mitra = Mitra::factory()->create();
        $warehouse = $this->asThc(fn () => Warehouse::factory()->create(['mitra_id' => $mitra->id]));
        $material = Material::factory()->create(['jenis' => 'ber_sn']);
        $actor = $this->userWith('operate_warehouse', $mitra);

        $this->expectException(QueryException::class);

        $this->asThc(fn () => DB::table('material_transaksis')->insert([
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'qty_delta' => '1',
            'reason' => 'Tidak boleh tanpa SN',
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
