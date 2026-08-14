<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_master_data_route_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/master/units')->assertForbidden();
    }

    private function groupWith(string $permission): Grup
    {
        $group = Grup::factory()->create();
        $group->izins()->attach(Izin::factory()->create(['kode' => $permission]));

        return $group;
    }
}
