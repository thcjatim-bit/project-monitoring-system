<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Material;
use App\Models\Mitra;
use App\Models\PekerjaanJasa;
use App\Models\Pop;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\AccessControlSeeder;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class MasterDataManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_thc_can_create_and_deactivate_a_shared_master_record(): void
    {
        $admin = User::factory()->create(['grup_id' => $this->groupWith('manage_master_data')->id]);

        $this->actingAs($admin)->post('/admin/master/units', [
            'kode' => 'M',
            'nama' => 'Meter',
        ])->assertRedirect();

        $unit = Unit::query()->firstOrFail();
        $this->assertTrue($unit->aktif);

        $this->actingAs($admin)
            ->patch("/admin/master/units/{$unit->id}/deactivate")
            ->assertRedirect();

        $this->assertDatabaseHas('units', ['id' => $unit->id, 'aktif' => false]);
    }

    public function test_authorized_thc_can_update_each_shared_master(): void
    {
        $admin = User::factory()->create(['grup_id' => $this->groupWith('manage_master_data')->id]);

        foreach ([
            ['units', Unit::class],
            ['pops', Pop::class],
            ['pekerjaan-jasa', PekerjaanJasa::class],
        ] as [$route, $model]) {
            $record = $model::query()->create(['kode' => strtoupper(fake()->unique()->lexify('???')), 'nama' => 'Lama']);

            $this->actingAs($admin)
                ->patch("/admin/master/{$route}/{$record->id}", ['kode' => $record->kode, 'nama' => 'Baru'])
                ->assertRedirect();

            $this->assertDatabaseHas($record->getTable(), ['id' => $record->id, 'nama' => 'Baru']);
        }
    }

    public function test_material_requires_an_active_unit_relationship(): void
    {
        $admin = User::factory()->create(['grup_id' => $this->groupWith('manage_materials')->id]);
        $unit = Unit::query()->create(['kode' => 'M', 'nama' => 'Meter']);

        $this->actingAs($admin)->post('/admin/materials', [
            'kode' => 'MAT-001', 'nama' => 'Kabel', 'unit_id' => $unit->id, 'jenis' => 'biasa',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $material = Material::query()->firstOrFail();
        $this->assertTrue($material->unit()->exists());
        $this->assertSame($unit->id, $material->unit_id);

        $unit->update(['aktif' => false]);
        $this->actingAs($admin)->post('/admin/materials', [
            'kode' => 'MAT-002', 'nama' => 'Kabel 2', 'unit_id' => $unit->id, 'jenis' => 'biasa',
        ])->assertSessionHasErrors('unit_id');
    }

    public function test_warehouse_can_be_created_for_an_active_mitra_and_deactivated(): void
    {
        $admin = User::factory()->create(['grup_id' => $this->groupWith('manage_warehouses')->id]);
        $mitra = Mitra::factory()->create();

        $this->actingAs($admin)->post('/admin/warehouses', [
            'kode' => 'GDG-001', 'nama' => 'Gudang Mitra', 'mitra_id' => $mitra->id,
        ])->assertRedirect();

        $warehouse = Warehouse::query()->firstOrFail();
        $this->assertSame($mitra->id, $warehouse->mitra_id);

        $this->actingAs($admin)
            ->patch("/admin/warehouses/{$warehouse->id}/deactivate")
            ->assertRedirect();

        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id, 'aktif' => false]);
    }

    public function test_master_data_route_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/master/units')->assertForbidden();
    }

    public function test_seeded_thc_admin_can_open_every_master_data_page(): void
    {
        $this->seed(AccessControlSeeder::class);
        $admin = User::factory()->create([
            'grup_id' => Grup::query()->where('preset', 'admin_thc')->value('id'),
            'mitra_id' => null,
        ]);

        foreach (['/admin/master/units', '/admin/master/pops', '/admin/master/pekerjaan-jasa', '/admin/materials', '/admin/warehouses'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_mitra_can_read_shared_master_data_but_cannot_manage_it(): void
    {
        $mitraUser = User::factory()->create([
            'grup_id' => $this->groupWith('read_dashboard', 'read_master_data', 'manage_master_data', 'manage_materials', 'manage_warehouses')->id,
            'mitra_id' => Mitra::factory()->create()->id,
        ]);

        foreach (['/admin/master/units', '/admin/materials'] as $url) {
            $this->actingAs($mitraUser)->get($url)->assertOk();
        }

        $this->actingAs($mitraUser)->get('/admin/warehouses')->assertForbidden();

        $this->actingAs($mitraUser)->post('/admin/master/units', [
            'kode' => 'M',
            'nama' => 'Meter',
        ])->assertForbidden();

        $this->actingAs($mitraUser)->get('/dashboard')
            ->assertOk()
            ->assertSee(route('admin.master.index', 'units'), false)
            ->assertSee(route('admin.materials'), false)
            ->assertDontSee(route('admin.warehouses'), false);
    }

    public function test_material_can_keep_its_historical_inactive_unit_when_updated(): void
    {
        $admin = User::factory()->create(['grup_id' => $this->groupWith('manage_materials')->id]);
        $unit = Unit::query()->create(['kode' => 'M', 'nama' => 'Meter', 'aktif' => false]);
        $material = Material::factory()->create(['unit_id' => $unit->id, 'nama' => 'Nama lama']);

        $this->actingAs($admin)->patch("/admin/materials/{$material->id}", [
            'kode' => $material->kode,
            'nama' => 'Nama baru',
            'unit_id' => $unit->id,
            'jenis' => $material->jenis,
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('materials', [
            'id' => $material->id,
            'nama' => 'Nama baru',
            'unit_id' => $unit->id,
        ]);
    }

    public function test_material_edit_offers_active_unit_replacement_without_inactive_option(): void
    {
        $admin = User::factory()->create(['grup_id' => $this->groupWith('read_master_data', 'manage_materials')->id]);
        $inactiveUnit = Unit::query()->create(['kode' => 'M-LAMA', 'nama' => 'Meter lama', 'aktif' => false]);
        $activeUnit = Unit::query()->create(['kode' => 'M-BARU', 'nama' => 'Meter baru', 'aktif' => true]);
        $material = Material::factory()->create(['unit_id' => $inactiveUnit->id]);

        $this->actingAs($admin)
            ->get('/admin/materials')
            ->assertOk()
            ->assertSee('value="'.$activeUnit->id.'"', false)
            ->assertDontSee('value="'.$inactiveUnit->id.'"', false);
    }

    private function groupWith(string ...$permissions): Grup
    {
        $group = Grup::factory()->create();
        foreach ($permissions as $permission) {
            $group->izins()->attach(Izin::factory()->create(['kode' => $permission]));
        }

        return $group;
    }
}
