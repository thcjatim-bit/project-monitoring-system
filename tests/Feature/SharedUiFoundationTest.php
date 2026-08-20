<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Material;
use App\Models\Mitra;
use App\Models\Unit;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class SharedUiFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_pages_share_the_management_page_contract(): void
    {
        $admin = User::factory()->create([
            'grup_id' => $this->groupWith('read_master_data', 'manage_master_data', 'manage_materials')->id,
        ]);
        $unit = Unit::query()->create(['kode' => 'M', 'nama' => 'Meter']);
        Material::query()->create([
            'kode' => 'MAT-001',
            'nama' => 'Kabel FO',
            'unit_id' => $unit->id,
            'jenis' => 'biasa',
        ]);

        foreach (['/admin/master/units', '/admin/materials'] as $url) {
            $this->actingAs($admin)
                ->get($url)
                ->assertOk()
                ->assertSee('ui-page__header', false)
                ->assertSee('ui-panel', false)
                ->assertSee('ui-table', false)
                ->assertSee('data-ui-search', false)
                ->assertSee('ui-badge', false);
        }
    }

    public function test_shared_master_pages_keep_mitra_users_read_only(): void
    {
        $mitra = Mitra::factory()->create();
        $viewer = User::factory()->create([
            'mitra_id' => $mitra->id,
            'grup_id' => $this->groupWith('read_master_data')->id,
        ]);
        Unit::query()->create(['kode' => 'M', 'nama' => 'Meter']);
        Material::query()->create([
            'kode' => 'MAT-001',
            'nama' => 'Kabel FO',
            'unit_id' => Unit::query()->value('id'),
            'jenis' => 'biasa',
        ]);

        foreach (['/admin/master/units', '/admin/materials'] as $url) {
            $this->actingAs($viewer)
                ->get($url)
                ->assertOk()
                ->assertSee('Read-only', false)
                ->assertDontSee('name="kode"', false)
                ->assertDontSee('ui-button--danger', false);
        }
    }

    public function test_command_center_exposes_compact_activity_column_contract(): void
    {
        $admin = User::factory()->create([
            'grup_id' => $this->groupWith('read_dashboard', 'manage_users')->id,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('command-center__layout', false)
            ->assertSee('command-center__main', false)
            ->assertSee('command-center__activity-column', false)
            ->assertSee('id="activity-feed-panel"', false)
            ->assertSee('command-center__activity-list', false);
    }

    public function test_management_and_project_pages_reuse_the_shared_page_header(): void
    {
        $admin = User::factory()->create([
            'grup_id' => $this->groupWith('manage_users', 'manage_mitras', 'read_project', 'create_project')->id,
        ]);

        foreach (['/admin/users', '/admin/mitras', '/projects', '/projects/buat'] as $url) {
            $this->actingAs($admin)
                ->get($url)
                ->assertOk()
                ->assertSee('ui-page__header', false)
                ->assertSee('ui-page', false);
        }

        foreach (['/admin/users', '/admin/mitras', '/projects/buat'] as $url) {
            $this->actingAs($admin)->get($url)->assertSee('ui-panel', false);
        }
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
