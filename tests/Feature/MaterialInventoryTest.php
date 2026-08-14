<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Material;
use App\Models\Mitra;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_officer_can_receive_and_issue_material_through_audited_transactions(): void
    {
        $mitra = Mitra::factory()->create();
        $warehouse = Warehouse::factory()->create(['mitra_id' => $mitra->id]);
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
        $warehouse = Warehouse::factory()->create(['mitra_id' => $mitra->id]);
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

    private function userWith(string $permission, Mitra $mitra): User
    {
        $group = Grup::factory()->create();
        $group->izins()->attach(Izin::factory()->create(['kode' => $permission]));

        return User::factory()->create(['mitra_id' => $mitra->id, 'grup_id' => $group->id]);
    }
}
