<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Material;
use App\Models\MaterialRequest;
use App\Models\MaterialTransaksi;
use App\Models\Mitra;
use App\Models\SuratJalan;
use App\Models\SuratJalanItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\MaterialInventoryService;
use App\Support\TenantDatabaseContext;
use Carbon\CarbonImmutable;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class CommandCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_thc_with_read_dashboard_can_open_the_command_center(): void
    {
        $thc = $this->userWithPermissions(null, 'read_dashboard');

        $this->actingAs($thc)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Command Center');
    }

    public function test_mitra_with_read_dashboard_is_forbidden_from_the_command_center(): void
    {
        $mitra = Mitra::factory()->create();
        $user = $this->userWithPermissions($mitra->id, 'read_dashboard', 'operate_warehouse', 'read_master_data');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertForbidden();
    }

    public function test_thc_without_read_dashboard_is_forbidden_even_by_direct_url(): void
    {
        $thc = $this->userWithPermissions(null);

        $this->actingAs($thc)
            ->get('/dashboard')
            ->assertForbidden();
    }

    public function test_command_center_shows_actual_active_user_capacity_by_tenant_type(): void
    {
        $actor = $this->userWithPermissions(null, 'read_dashboard', 'manage_users');
        $this->userWithPermissions(null);
        $this->userWithPermissions(Mitra::factory()->create()->id);
        $this->userWithPermissions(Mitra::factory()->create()->id);
        User::factory()->create(['aktif' => false, 'mitra_id' => null]);
        User::factory()->create(['aktif' => false, 'mitra_id' => Mitra::factory()->create()->id]);

        $this->actingAs($actor)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('User aktif')
            ->assertSee('User THC aktif')
            ->assertSee('User Mitra aktif')
            ->assertSee('<strong>4</strong>', false)
            ->assertSee('<strong>2</strong>', false)
            ->assertSee('href="'.route('admin.users').'"', false);
    }

    public function test_command_center_shows_mitra_created_within_the_exact_30_day_boundary_and_first_admin_context(): void
    {
        $now = CarbonImmutable::parse('2026-08-20 12:00:00');
        CarbonImmutable::setTestNow($now);

        try {
            $outside = Mitra::factory()->create([
                'nama' => 'Mitra Di Luar Rentang',
                'created_at' => $now->subDays(30)->subSecond(),
            ]);
            $boundary = Mitra::factory()->create([
                'nama' => 'Mitra Tepat Batas',
                'created_at' => $now->subDays(30),
            ]);
            $recent = Mitra::factory()->create([
                'nama' => 'Mitra Terbaru',
                'created_at' => $now->subDays(2),
            ]);
            User::factory()->create([
                'name' => 'Admin Mitra Terbaru',
                'email' => 'admin.terbaru@example.com',
                'mitra_id' => $recent->id,
                'created_at' => $now->subDays(2)->addMinute(),
            ]);
            User::factory()->create([
                'name' => 'Admin Kedua',
                'email' => 'admin.kedua@example.com',
                'mitra_id' => $recent->id,
                'created_at' => $now->subDay(),
            ]);
            $actor = $this->userWithPermissions(null, 'read_dashboard', 'manage_mitras');

            $this->actingAs($actor)
                ->get('/dashboard')
                ->assertOk()
                ->assertSee('Onboarding Mitra terbaru')
                ->assertSee('Mitra Tepat Batas')
                ->assertSee('Mitra Terbaru')
                ->assertSee('Admin Mitra Terbaru')
                ->assertSee('admin.terbaru@example.com')
                ->assertSee($recent->created_at->format('d M Y H:i'))
                ->assertSee('href="'.route('admin.mitras').'#mitra-'.$boundary->id.'"', false)
                ->assertDontSee($outside->nama);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_command_center_panels_follow_user_and_mitra_management_permissions(): void
    {
        $userManager = $this->userWithPermissions(null, 'read_dashboard', 'manage_users');

        $this->actingAs($userManager)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('User aktif')
            ->assertDontSee('Onboarding Mitra terbaru');

        $mitraManager = $this->userWithPermissions(null, 'read_dashboard', 'manage_mitras');

        $this->actingAs($mitraManager)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('User aktif')
            ->assertSee('Onboarding Mitra terbaru');

        $viewer = $this->userWithPermissions(null, 'read_dashboard');

        $this->actingAs($viewer)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('User aktif')
            ->assertDontSee('Onboarding Mitra terbaru');
    }

    public function test_mitra_cannot_access_command_center_or_user_and_mitra_sources_by_direct_url(): void
    {
        $mitra = Mitra::factory()->create();
        $user = $this->userWithPermissions($mitra->id, 'read_dashboard', 'manage_users', 'manage_mitras');

        $this->actingAs($user)->get('/dashboard')->assertForbidden();
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->get('/admin/mitras')->assertForbidden();
    }

    public function test_command_center_uses_read_only_links_for_active_users_and_recent_mitras(): void
    {
        $mitra = Mitra::factory()->create(['nama' => 'Mitra Link']);
        $firstAdmin = User::factory()->create(['mitra_id' => $mitra->id, 'name' => 'Admin Link']);
        $actor = $this->userWithPermissions(null, 'read_dashboard', 'manage_users', 'manage_mitras');

        $this->actingAs($actor)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('href="'.route('admin.users').'"', false)
            ->assertSee('href="'.route('admin.mitras').'#mitra-'.$mitra->id.'"', false)
            ->assertDontSee('action="'.route('admin.users.create').'"', false)
            ->assertDontSee('action="'.route('admin.mitras.create').'"', false)
            ->assertSee('Admin Link');

        $this->assertDatabaseHas('users', ['id' => $firstAdmin->id, 'aktif' => true]);
    }

    public function test_command_center_shows_only_submitted_requests_requiring_thc_decision(): void
    {
        $mitra = Mitra::factory()->create();
        $material = Material::factory()->create();
        $mitraUser = $this->userWithPermissions($mitra->id, 'read_material_request', 'create_material_request');

        $this->actingAs($mitraUser)->post('/material-requests', [
            'items' => [['material_id' => $material->id, 'qty' => 4]],
        ])->assertRedirect('/material-requests');
        $submitted = MaterialRequest::query()->firstOrFail();

        $this->actingAs($mitraUser)->post('/material-requests', [
            'items' => [['material_id' => $material->id, 'qty' => 2]],
        ])->assertRedirect('/material-requests');
        $approved = MaterialRequest::query()->latest('id')->firstOrFail();
        $this->asThc(fn () => $approved->update(['status' => 'disetujui']));

        $thc = $this->userWithPermissions(null, 'read_dashboard', 'read_material_request');

        $this->actingAs($thc)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('<strong>1</strong>', false)
            ->assertSee('Request Material menunggu keputusan')
            ->assertSee('Request Material #'.$submitted->id)
            ->assertDontSee('Request Material #'.$approved->id);
    }

    public function test_command_center_navigation_only_contains_modules_allowed_by_permissions(): void
    {
        $thc = $this->userWithPermissions(null, 'read_dashboard', 'read_material_request');

        $this->actingAs($thc)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('href="'.route('material-requests.index').'"', false)
            ->assertDontSee('href="'.route('projects.index').'"', false)
            ->assertDontSee('href="'.route('admin.users').'"', false)
            ->assertDontSee('href="'.route('admin.warehouses').'"', false);
    }

    public function test_command_center_hides_request_material_panel_without_read_permission(): void
    {
        $thc = $this->userWithPermissions(null, 'read_dashboard');

        $this->actingAs($thc)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Request Material yang membutuhkan keputusan')
            ->assertDontSee('Request Material menunggu keputusan');
    }

    public function test_command_center_marks_only_transit_older_than_three_days_as_delayed(): void
    {
        $now = CarbonImmutable::parse('2026-08-20 12:00:00');
        CarbonImmutable::setTestNow($now);

        try {
            $mitra = Mitra::factory()->create(['nama' => 'Mitra Transit']);
            [$origin, $destination] = $this->warehousesFor($mitra);
            $material = Material::factory()->create(['nama' => 'Kabel FO']);
            $thc = $this->userWithPermissions(null, 'read_dashboard', 'operate_warehouse');
            $older = $this->createIssuedTransfer($origin, $destination, $material, '2026-08-17 11:59:59', 'SJ-OLD');
            $boundary = $this->createIssuedTransfer($origin, $destination, $material, '2026-08-17 12:00:00', 'SJ-BOUNDARY');

            $this->actingAs($thc)
                ->get('/dashboard')
                ->assertOk()
                ->assertSee('Transit terlambat')
                ->assertSee($older->nomor)
                ->assertSee($origin->nama.' → '.$destination->nama)
                ->assertSee('Lebih dari 3 hari')
                ->assertSee('command-center__item-status--danger', false)
                ->assertSee('href="'.route('warehouse.transfers.print', $older).'"', false)
                ->assertDontSee($boundary->nomor);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_thc_can_set_and_change_a_material_minimum_threshold_through_master_data(): void
    {
        $material = Material::factory()->create();
        $thc = $this->userWithPermissions(null, 'manage_materials');

        $this->actingAs($thc)
            ->patch('/admin/materials/'.$material->id, [
                'kode' => $material->kode,
                'nama' => $material->nama,
                'unit_id' => $material->unit_id,
                'jenis' => $material->jenis,
                'ambang_minimum' => '10',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('materials', ['id' => $material->id, 'ambang_minimum' => '10.000']);

        $this->actingAs($thc)
            ->patch('/admin/materials/'.$material->id, [
                'kode' => $material->kode,
                'nama' => $material->nama,
                'unit_id' => $material->unit_id,
                'jenis' => $material->jenis,
                'ambang_minimum' => '4',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('materials', ['id' => $material->id, 'ambang_minimum' => '4.000']);
    }

    public function test_command_center_uses_warehouse_balance_for_all_material_types_and_excludes_transit(): void
    {
        $warehouse = $this->asThc(fn () => Warehouse::factory()->create(['nama' => 'Warehouse THC']));
        $ordinary = Material::factory()->create(['nama' => 'Material Biasa', 'ambang_minimum' => '10']);
        $serialised = Material::factory()->create(['nama' => 'Material Ber-SN', 'jenis' => 'ber_sn', 'ambang_minimum' => '2']);
        $drum = Material::factory()->create(['nama' => 'Material Drum', 'jenis' => 'drum_kabel', 'ambang_minimum' => '100']);
        $actor = $this->userWithPermissions(null, 'operate_warehouse', 'read_dashboard', 'read_master_data');

        $this->asThc(function () use ($actor, $warehouse, $ordinary, $serialised, $drum): void {
            $service = app(MaterialInventoryService::class);
            $service->receive($actor, $warehouse, $ordinary->id, '5', 'Stok awal');
            $service->receive($actor, $warehouse, $serialised->id, '1', 'Stok awal', 'SN-COMMAND-001');
            $service->receive($actor, $warehouse, $drum->id, '100', 'Stok awal', null, 'DRM-COMMAND-001');

            MaterialTransaksi::query()->create([
                'warehouse_id' => $warehouse->id,
                'material_id' => $ordinary->id,
                'jenis_transaksi' => 'transfer',
                'lokasi_tipe' => 'transit',
                'lokasi_id' => 999,
                'qty_delta' => '10',
                'mitra_id' => null,
                'reason' => 'Transit belum tiba',
                'actor_id' => $actor->id,
            ]);
        });

        $this->actingAs($actor)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Stok kritis')
            ->assertSee('Material Biasa')
            ->assertSee('Material Ber-SN')
            ->assertSee('Material Drum')
            ->assertSee('5.000')
            ->assertSee('1.000')
            ->assertSee('100.000')
            ->assertSee('Warehouse THC')
            ->assertSee('href="'.route('admin.materials').'#material-'.$ordinary->id.'"', false);

        $this->actingAs($actor)
            ->get('/admin/materials')
            ->assertOk()
            ->assertSee('Saldo Warehouse')
            ->assertSee('Warehouse THC')
            ->assertSee('5.000');
    }

    public function test_command_center_hides_inventory_panels_without_their_source_permissions(): void
    {
        $thc = $this->userWithPermissions(null, 'read_dashboard');

        $this->actingAs($thc)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Transit terlambat')
            ->assertDontSee('Stok kritis');
    }

    public function test_command_center_inventory_panels_follow_their_source_permissions(): void
    {
        $transitReader = $this->userWithPermissions(null, 'read_dashboard', 'operate_warehouse');

        $this->actingAs($transitReader)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Transit terlambat')
            ->assertDontSee('Stok kritis');

        $stockReader = $this->userWithPermissions(null, 'read_dashboard', 'read_master_data');

        $this->actingAs($stockReader)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Transit terlambat')
            ->assertSee('Stok kritis');
    }

    public function test_command_center_uses_read_only_queue_and_detail_links(): void
    {
        [$submitted] = $this->createSubmittedRequest();
        $thc = $this->userWithPermissions(null, 'read_dashboard', 'read_material_request');

        $this->actingAs($thc)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('href="'.route('material-requests.index').'"', false)
            ->assertSee('href="'.route('material-requests.show', $submitted).'"', false)
            ->assertDontSee('Setujui')
            ->assertDontSee('Tolak')
            ->assertDontSee('method="POST" action="'.route('material-requests.approve', $submitted).'"', false);
    }

    public function test_mitra_cannot_open_another_mitras_request_detail(): void
    {
        [$requestA, $userA] = $this->createSubmittedRequest();
        [$requestB] = $this->createSubmittedRequest();

        $this->actingAs($userA)
            ->get('/material-requests/'.$requestB->id)
            ->assertNotFound();

        $this->assertNotSame($requestA->mitra_id, $requestB->mitra_id);
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

        return User::factory()->create([
            'mitra_id' => $mitraId,
            'grup_id' => $group->id,
        ]);
    }

    private function asThc(\Closure $callback): mixed
    {
        app(TenantDatabaseContext::class)->set(null, true);

        try {
            return $callback();
        } finally {
            app(TenantDatabaseContext::class)->set(null, false);
        }
    }

    /** @return array{Warehouse, Warehouse} */
    private function warehousesFor(Mitra $mitra): array
    {
        return $this->asThc(fn (): array => [
            Warehouse::factory()->create(['mitra_id' => $mitra->id, 'nama' => 'Warehouse Asal']),
            Warehouse::factory()->create(['mitra_id' => $mitra->id, 'nama' => 'Warehouse Tujuan']),
        ]);
    }

    private function createIssuedTransfer(
        Warehouse $origin,
        Warehouse $destination,
        Material $material,
        string $issuedAt,
        string $number,
    ): SuratJalan {
        return $this->asThc(function () use ($origin, $destination, $material, $issuedAt, $number): SuratJalan {
            $suratJalan = SuratJalan::query()->create([
                'nomor' => $number,
                'tanggal' => substr($issuedAt, 0, 10),
                'warehouse_asal_id' => $origin->id,
                'warehouse_tujuan_id' => $destination->id,
                'mitra_id' => $origin->mitra_id,
                'issued_by' => User::query()->whereNull('mitra_id')->firstOrFail()->id,
                'issued_at' => $issuedAt,
                'status' => 'terbit',
                'pengirim' => 'Petugas Gudang',
            ]);
            SuratJalanItem::query()->create([
                'surat_jalan_id' => $suratJalan->id,
                'mitra_id' => $origin->mitra_id,
                'material_id' => $material->id,
                'qty' => 1,
            ]);

            return $suratJalan;
        });
    }

    /** @return array{MaterialRequest, User} */
    private function createSubmittedRequest(): array
    {
        $mitra = Mitra::factory()->create();
        $material = Material::factory()->create();
        $mitraUser = $this->userWithPermissions($mitra->id, 'read_material_request', 'create_material_request');

        $this->actingAs($mitraUser)->post('/material-requests', [
            'items' => [['material_id' => $material->id, 'qty' => 4]],
        ])->assertRedirect('/material-requests');

        return [MaterialRequest::query()->firstOrFail(), $mitraUser];
    }
}
