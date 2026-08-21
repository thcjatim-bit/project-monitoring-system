<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Material;
use App\Models\MaterialRequest;
use App\Models\MaterialTransaksi;
use App\Models\Mitra;
use App\Models\Project;
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

            $response = $this->actingAs($actor)
                ->get('/dashboard')
                ->assertOk()
                ->assertSee('Onboarding Mitra terbaru')
                ->assertSee('Mitra Tepat Batas')
                ->assertSee('Mitra Terbaru')
                ->assertSee('Admin Mitra Terbaru')
                ->assertSee('admin.terbaru@example.com')
                ->assertSee($recent->created_at->format('d M Y H:i'))
                ->assertSee('href="'.route('admin.mitras').'#mitra-'.$boundary->id.'"', false);

            $this->assertPanelDoesNotContain($response->getContent(), 'recent-mitra-onboarding-panel', $outside->nama);
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

        $response = $this->actingAs($thc)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('<strong>1</strong>', false)
            ->assertSee('Request Material menunggu keputusan')
            ->assertSee('Request Material #'.$submitted->id)
            ->assertSee('Tanpa Project');

        $this->assertPanelDoesNotContain($response->getContent(), 'material-request-panel', 'Request Material #'.$approved->id);
    }

    public function test_command_center_pending_request_includes_its_project_context(): void
    {
        $mitra = Mitra::factory()->create();
        $material = Material::factory()->create();
        $mitraUser = $this->userWithPermissions($mitra->id, 'read_material_request', 'create_material_request');
        $project = $this->asThc(fn (): Project => Project::query()->create([
            'id_project' => 'PRJ-2608-0093',
            'nama' => 'Project Command Center',
            'mitra_id' => $mitra->id,
            'status_project' => 'aktif',
        ]));

        $this->actingAs($mitraUser)->post('/material-requests', [
            'project_id' => $project->id,
            'items' => [['material_id' => $material->id, 'qty' => 4]],
        ])->assertRedirect('/material-requests');

        $thc = $this->userWithPermissions(null, 'read_dashboard', 'read_material_request');

        $this->actingAs($thc)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('PRJ-2608-0093 — Project Command Center')
            ->assertDontSee('href="'.route('projects.show', $project).'"', false);
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
            $older = $this->createIssuedTransfer($origin, $destination, $material, '2026-08-16 23:59:59', 'SJ-OLD');
            $boundary = $this->createIssuedTransfer($origin, $destination, $material, '2026-08-17 00:00:00', 'SJ-BOUNDARY');

            $response = $this->actingAs($thc)
                ->get('/dashboard')
                ->assertOk()
                ->assertSee('Transit terlambat')
                ->assertSee($older->nomor)
                ->assertSee($origin->nama.' → '.$destination->nama)
                ->assertSee('Umur Transit 4 hari')
                ->assertSee('Lebih dari 3 hari')
                ->assertSee('command-center__item-status--danger', false)
                ->assertSee('href="'.route('warehouse.transfers.print', $older).'"', false);

            $this->assertPanelDoesNotContain($response->getContent(), 'delayed-transit-panel', $boundary->nomor);
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

    public function test_command_center_shows_readiness_for_active_thc_and_mitra_warehouses(): void
    {
        $now = CarbonImmutable::parse('2026-08-20 12:00:00');
        CarbonImmutable::setTestNow($now);

        try {
            $mitra = Mitra::factory()->create(['nama' => 'Mitra Warehouse']);
            $otherMitra = Mitra::factory()->create(['nama' => 'Mitra Warehouse Lain']);
            [$ready, $attention, $transitHub, $other, $inactive] = $this->asThc(fn (): array => [
                Warehouse::factory()->create(['nama' => 'Warehouse Siap THC']),
                Warehouse::factory()->create(['nama' => 'Warehouse Perlu Perhatian', 'mitra_id' => $mitra->id]),
                Warehouse::factory()->create(['nama' => 'Warehouse Transit Hub']),
                Warehouse::factory()->create(['nama' => 'Warehouse Mitra Lain', 'mitra_id' => $otherMitra->id]),
                Warehouse::factory()->create(['nama' => 'Warehouse Nonaktif', 'aktif' => false]),
            ]);
            $ordinary = Material::factory()->create(['nama' => 'Material Biasa Readiness', 'ambang_minimum' => '10']);
            $serialised = Material::factory()->create(['nama' => 'Material SN Readiness', 'jenis' => 'ber_sn', 'ambang_minimum' => '2']);
            $drum = Material::factory()->create(['nama' => 'Material Drum Readiness', 'jenis' => 'drum_kabel', 'ambang_minimum' => '100']);
            $actor = $this->userWithPermissions(
                null,
                'read_dashboard',
                'manage_warehouses',
                'read_master_data',
                'operate_warehouse',
            );
            $inactiveOfficer = User::factory()->create(['aktif' => false]);
            $ready->users()->attach($actor);
            $attention->users()->attach($inactiveOfficer);

            $this->asThc(function () use ($actor, $ready, $attention, $ordinary, $serialised, $drum): void {
                $inventory = app(MaterialInventoryService::class);
                $inventory->receive($actor, $ready, $ordinary->id, '20', 'Stok readiness');
                $inventory->receive($actor, $ready, $serialised->id, '1', 'Stok readiness', 'SN-READINESS-1');
                $inventory->receive($actor, $ready, $serialised->id, '1', 'Stok readiness', 'SN-READINESS-1B');
                $inventory->receive($actor, $ready, $serialised->id, '1', 'Stok readiness', 'SN-READINESS-1C');
                $inventory->receive($actor, $ready, $drum->id, '101', 'Stok readiness', null, 'DRM-READINESS-1');
                $inventory->receive($actor, $attention, $ordinary->id, '5', 'Stok readiness');
                $inventory->receive($actor, $attention, $serialised->id, '1', 'Stok readiness', 'SN-READINESS-2');
                $inventory->receive($actor, $attention, $drum->id, '50', 'Stok readiness', null, 'DRM-READINESS-2');
            });
            $this->createIssuedTransfer($attention, $transitHub, $ordinary, $now->subDay()->format('Y-m-d H:i:s'), 'SJ-READINESS-ACTIVE');
            $this->createIssuedTransfer($attention, $transitHub, $ordinary, $now->subDays(4)->format('Y-m-d H:i:s'), 'SJ-READINESS-DELAYED');

            $response = $this->actingAs($actor)
                ->get('/dashboard')
                ->assertOk()
                ->assertSee('Kesiapan Warehouse')
                ->assertSee('Warehouse Siap THC')
                ->assertSee('Kepemilikan: THC')
                ->assertSee('Warehouse Perlu Perhatian')
                ->assertSee('Kepemilikan: Mitra Warehouse')
                ->assertSee('Warehouse Mitra Lain')
                ->assertSee('Petugas Gudang aktif: 1')
                ->assertSee('Petugas Gudang aktif: 0')
                ->assertSee('Material kritis: 0')
                ->assertSee('Material kritis: 3')
                ->assertSee('Transit aktif: 2 · Terlambat: 1')
                ->assertSee('Siap')
                ->assertSee('Perlu perhatian')
                ->assertSee('href="'.route('admin.warehouses').'#warehouse-'.$ready->id.'"', false)
                ->assertSee('href="'.route('admin.materials').'"', false)
                ->assertSee('href="'.route('warehouse.transit').'"', false);

            $this->assertPanelDoesNotContain($response->getContent(), 'warehouse-readiness-panel', $inactive->nama);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_command_center_gates_warehouse_readiness_facts_and_source_links_by_permission(): void
    {
        $warehouse = $this->asThc(fn () => Warehouse::factory()->create(['nama' => 'Warehouse Permission']));

        $warehouseManager = $this->userWithPermissions(null, 'read_dashboard', 'manage_warehouses');
        $this->actingAs($warehouseManager)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Kesiapan Warehouse')
            ->assertSee('Petugas Gudang aktif: 0')
            ->assertDontSee('Material kritis:')
            ->assertDontSee('Transit aktif:')
            ->assertSee('href="'.route('admin.warehouses').'#warehouse-'.$warehouse->id.'"', false)
            ->assertSee('Status kesiapan memerlukan izin sumber Warehouse, stok, dan Transit.');

        $stockReader = $this->userWithPermissions(null, 'read_dashboard', 'read_master_data');
        $this->actingAs($stockReader)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Kesiapan Warehouse')
            ->assertSee('Material kritis: 0')
            ->assertDontSee('Petugas Gudang aktif:')
            ->assertDontSee('Transit aktif:')
            ->assertDontSee('href="'.route('admin.warehouses').'"', false);

        $transitReader = $this->userWithPermissions(null, 'read_dashboard', 'operate_warehouse');
        $this->actingAs($transitReader)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Kesiapan Warehouse')
            ->assertSee('Transit aktif: 0 · Terlambat: 0')
            ->assertDontSee('Petugas Gudang aktif:')
            ->assertDontSee('Material kritis:')
            ->assertDontSee('href="'.route('admin.warehouses').'"', false);

        $viewer = $this->userWithPermissions(null, 'read_dashboard');
        $this->actingAs($viewer)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Kesiapan Warehouse');
    }

    public function test_mitra_cannot_access_warehouse_readiness_or_warehouse_source_by_direct_url(): void
    {
        $mitra = Mitra::factory()->create();
        $user = $this->userWithPermissions($mitra->id, 'read_dashboard', 'manage_warehouses', 'read_master_data', 'operate_warehouse');

        $this->actingAs($user)->get('/dashboard')->assertForbidden();
        $this->actingAs($user)->get('/admin/warehouses')->assertForbidden();
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

    public function test_command_center_merges_operational_activity_sources_in_chronological_order(): void
    {
        $now = CarbonImmutable::parse('2026-08-20 12:00:00');
        CarbonImmutable::setTestNow($now);

        try {
            $mitra = Mitra::factory()->create([
                'nama' => 'Mitra Feed',
                'created_at' => $now->subHours(4),
                'updated_at' => $now->subHours(4),
            ]);
            $actor = $this->userWithPermissions(
                null,
                'read_dashboard',
                'read_material_request',
                'operate_warehouse',
                'manage_users',
                'manage_mitras',
                'read_master_data',
                'manage_warehouses',
            );
            $request = $this->asThc(fn () => MaterialRequest::query()->create([
                'mitra_id' => $mitra->id,
                'requested_by' => $actor->id,
                'status' => 'disetujui',
                'decided_by' => $actor->id,
                'decided_at' => $now->subHours(5),
                'created_at' => $now->subHours(6),
                'updated_at' => $now->subHours(5),
            ]));
            $material = Material::factory()->create([
                'nama' => 'Material Feed',
                'created_at' => $now->subHour(),
                'updated_at' => $now->subHour(),
            ]);
            [$origin, $destination] = $this->warehousesFor($mitra);
            $suratJalan = $this->createIssuedTransfer(
                $origin,
                $destination,
                $material,
                $now->subHours(3)->format('Y-m-d H:i:s'),
                'SJ-FEED',
            );
            $user = User::factory()->create([
                'name' => 'User Feed',
                'email' => 'user.feed@example.com',
                'mitra_id' => $mitra->id,
                'created_at' => $now->subHours(2),
                'updated_at' => $now->subHours(2),
            ]);

            $response = $this->actingAs($actor)->get('/dashboard');

            $response
                ->assertOk()
                ->assertSee('Aktivitas lintas operasional')
                ->assertSee('Request Material #'.$request->id)
                ->assertSee('SJ-FEED')
                ->assertSee('User Feed')
                ->assertSee('Mitra Feed')
                ->assertSee('Material Feed')
                ->assertSee('Disetujui THC')
                ->assertSee($now->subHours(5)->format('d M Y H:i'))
                ->assertSee($now->subHours(3)->format('d M Y H:i'))
                ->assertSee($now->subHours(2)->format('d M Y H:i'))
                ->assertSee($now->subHours(4)->format('d M Y H:i'))
                ->assertSee($now->subHour()->format('d M Y H:i'))
                ->assertSee('href="'.route('material-requests.show', $request).'"', false)
                ->assertSee('href="'.route('warehouse.transfers.print', $suratJalan).'"', false)
                ->assertSee('href="'.route('admin.users').'"', false)
                ->assertSee('href="'.route('admin.mitras').'"', false)
                ->assertSee('href="'.route('admin.materials').'"', false);

            $html = $response->getContent();
            $this->assertLessThan(strpos($html, 'User Feed'), strpos($html, 'Material Feed'));
            $this->assertLessThan(strpos($html, 'Mitra Feed'), strpos($html, 'User Feed'));
            $this->assertLessThan(strpos($html, 'SJ-FEED'), strpos($html, 'Mitra Feed'));
            $this->assertLessThan(strpos($html, 'Request Material #'.$request->id), strpos($html, 'SJ-FEED'));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_command_center_activity_feed_is_permission_gated_and_has_empty_loading_states(): void
    {
        $viewer = $this->userWithPermissions(null, 'read_dashboard');

        $this->actingAs($viewer)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Aktivitas lintas operasional')
            ->assertSee('Memuat aktivitas lintas operasional')
            ->assertSee('Belum ada aktivitas lintas operasional yang dapat ditampilkan.')
            ->assertDontSee('Request Material #')
            ->assertDontSee('User Feed')
            ->assertDontSee('Mitra Feed');

        $requestReader = $this->userWithPermissions(null, 'read_dashboard', 'read_material_request');

        $this->actingAs($requestReader)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Aktivitas lintas operasional')
            ->assertSee('Belum ada aktivitas lintas operasional yang dapat ditampilkan.')
            ->assertDontSee('href="'.route('admin.users').'"', false)
            ->assertDontSee('href="'.route('admin.mitras').'"', false);
    }

    public function test_mitra_cannot_receive_cross_operational_activity_feed(): void
    {
        $mitra = Mitra::factory()->create();
        $user = $this->userWithPermissions($mitra->id, 'read_dashboard', 'read_material_request', 'operate_warehouse');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertForbidden();
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

    private function assertPanelDoesNotContain(string $html, string $panelId, string $needle): void
    {
        $panelStart = strpos($html, 'id="'.$panelId.'"');
        $this->assertNotFalse($panelStart, "Panel {$panelId} tidak ditemukan.");

        $panelEnd = strpos($html, '</section>', $panelStart);
        $this->assertNotFalse($panelEnd, "Penutup panel {$panelId} tidak ditemukan.");

        $panelHtml = substr($html, $panelStart, $panelEnd - $panelStart);
        $this->assertStringNotContainsString($needle, $panelHtml);
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
