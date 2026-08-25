<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Material;
use App\Models\Mitra;
use App\Models\Project;
use App\Models\SuratJalan;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\TenantDatabaseContext;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class MaterialOperationalUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_material_form_explains_required_unit_and_renders_validation_errors(): void
    {
        $admin = $this->userWithPermissions(null, 'read_master_data', 'manage_materials', 'manage_master_data');
        Unit::query()->update(['aktif' => false]);

        $this->actingAs($admin)
            ->get('/admin/materials')
            ->assertOk()
            ->assertSee('Unit/Satuan aktif wajib tersedia')
            ->assertSee(route('admin.master.index', 'units'), false)
            ->assertSee('Belum ada Unit/Satuan aktif');

        $activeUnit = Unit::query()->create(['kode' => 'M', 'nama' => 'Meter', 'aktif' => true]);

        $this->actingAs($admin)
            ->post('/admin/materials', [
                'kode' => '',
                'nama' => '',
                'unit_id' => $activeUnit->id,
                'jenis' => 'biasa',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['nama'])
            ->assertSessionDoesntHaveErrors(['kode']);

        $this->actingAs($admin)
            ->get('/admin/materials')
            ->assertOk()
            ->assertSee('The nama field is required.');
    }

    public function test_project_material_dropdown_only_offers_materials_with_active_units(): void
    {
        $mitra = Mitra::factory()->create();
        $user = $this->userWithPermissions(null, 'read_project', 'read_project_material', 'manage_project_material');
        $project = $this->asThc(fn (): Project => Project::query()->create([
            'id_project' => 'PRJ-2608-8501',
            'nama' => 'Project Uji Material',
            'mitra_id' => $mitra->id,
        ]));
        $activeUnit = Unit::query()->create(['kode' => 'M-AKTIF', 'nama' => 'Meter aktif', 'aktif' => true]);
        $inactiveUnit = Unit::query()->create(['kode' => 'M-LAMA', 'nama' => 'Meter lama', 'aktif' => false]);
        $activeMaterial = Material::factory()->create(['kode' => 'MAT-AKTIF', 'nama' => 'Material aktif', 'unit_id' => $activeUnit->id]);
        $inactiveMaterial = Material::factory()->create(['kode' => 'MAT-LAMA', 'nama' => 'Material unit lama', 'unit_id' => $inactiveUnit->id]);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('value="'.$activeMaterial->id.'"', false)
            ->assertDontSee('value="'.$inactiveMaterial->id.'"', false);

        $this->actingAs($user)
            ->post(route('projects.rab-material.store', $project), [
                'material_id' => $inactiveMaterial->id,
                'qty' => '2',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('material_id');
    }

    public function test_assigned_operator_can_open_operational_warehouse_hub(): void
    {
        $mitra = Mitra::factory()->create();
        // Petugas THC: form Terbitkan Surat Jalan hanya ada untuk User THC.
        $user = $this->userWithPermissions(null, 'operate_warehouse', 'read_master_data');
        $warehouse = $this->asThc(fn (): Warehouse => Warehouse::factory()->create(['mitra_id' => $mitra->id]));
        $tujuan = $this->asThc(fn (): Warehouse => Warehouse::factory()->create(['mitra_id' => $mitra->id, 'kode' => 'WH-TUJUAN']));
        $warehouse->users()->attach($user);
        $material = Material::factory()->create(['kode' => 'MAT-HUB', 'nama' => 'Material Hub']);

        $this->actingAs($user)
            ->get('/warehouse')
            ->assertOk()
            ->assertSee('Operasional Material')
            ->assertSee('Penerimaan stok')
            ->assertSee('Pengeluaran stok')
            ->assertSee('Split drum')
            ->assertSee('Terbitkan Surat Jalan')
            ->assertSee($warehouse->nama)
            ->assertSee($tujuan->kode)
            ->assertSee('MAT-HUB')
            ->assertSee('data-submit-loading', false);
    }

    public function test_operasional_material_memformat_qty_dan_menampilkan_surat_jalan_masuk(): void
    {
        $mitra = Mitra::factory()->create();
        $user = $this->userWithPermissions($mitra->id, 'operate_warehouse');
        [$asal, $tujuan] = $this->asThc(fn (): array => [
            Warehouse::factory()->create(['mitra_id' => $mitra->id, 'kode' => 'WH-ASAL']),
            Warehouse::factory()->create(['mitra_id' => $mitra->id, 'kode' => 'WH-TUJUAN']),
        ]);
        $asal->users()->attach($user);
        $tujuan->users()->attach($user);
        $material = Material::factory()->create(['kode' => 'MAT-QTY', 'nama' => 'Material Qty']);

        $this->actingAs($user)->post(route('warehouse.stock.receive'), [
            'warehouse_id' => $asal->id,
            'material_id' => $material->id,
            'qty' => '2312279',
            'reason' => 'Stok awal',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('warehouse.transfers.issue'), [
            'warehouse_asal_id' => $asal->id,
            'warehouse_tujuan_id' => $tujuan->id,
            'tanggal' => '2026-08-20',
            'pengirim' => 'Petugas Gudang',
            'items' => [['material_id' => $material->id, 'qty' => '4']],
        ])->assertRedirect();

        $suratJalan = SuratJalan::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('warehouse.index'))
            ->assertOk()
            ->assertSee('2.312.275')
            ->assertSee('2.312.279')
            ->assertDontSee('2312275.000')
            ->assertDontSee('2312279.000')
            ->assertSee('Pengiriman masuk')
            ->assertSee($suratJalan->nomor)
            ->assertSee($asal->nama)
            ->assertSee($tujuan->nama)
            ->assertSee('Terima Pengiriman')
            ->assertSee(route('warehouse.transfers.show', $suratJalan), false);
    }

    public function test_action_cetak_dan_penerbitan_surat_jalan_membuka_tab_baru_dengan_aman(): void
    {
        $mitra = Mitra::factory()->create();
        // Petugas THC: form Terbitkan Surat Jalan hanya ada untuk User THC.
        $user = $this->userWithPermissions(null, 'operate_warehouse');
        [$asal, $tujuan] = $this->asThc(fn (): array => [
            Warehouse::factory()->create(['mitra_id' => $mitra->id]),
            Warehouse::factory()->create(['mitra_id' => $mitra->id]),
        ]);
        $asal->users()->attach($user);
        $tujuan->users()->attach($user);
        $material = Material::factory()->create();

        $this->actingAs($user)->post(route('warehouse.stock.receive'), [
            'warehouse_id' => $asal->id,
            'material_id' => $material->id,
            'qty' => '5',
            'reason' => 'Stok awal',
        ])->assertRedirect();
        $this->actingAs($user)->post(route('warehouse.transfers.issue'), [
            'warehouse_asal_id' => $asal->id,
            'warehouse_tujuan_id' => $tujuan->id,
            'tanggal' => '2026-08-20',
            'pengirim' => 'Petugas Gudang',
            'items' => [['material_id' => $material->id, 'qty' => '2']],
        ])->assertRedirect();
        $suratJalan = SuratJalan::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('warehouse.index'))
            ->assertSee('target="_blank"', false)
            ->assertSee('data-transfer-form', false);

        $this->actingAs($user)
            ->get(route('warehouse.transit'))
            ->assertSee('target="_blank" rel="noopener noreferrer"', false);

        $this->actingAs($user)
            ->get(route('warehouse.transfers.index'))
            ->assertSee('target="_blank" rel="noopener noreferrer"', false);

        $this->actingAs($user)
            ->get(route('warehouse.transfers.show', $suratJalan))
            ->assertSee('target="_blank" rel="noopener noreferrer"', false)
            ->assertSee('name="items[0][qty]"', false)
            ->assertSee('value="2"', false);
    }

    public function test_transfer_detail_exposes_receive_cancel_return_and_correction_actions(): void
    {
        $mitra = Mitra::factory()->create();
        $user = $this->userWithPermissions(null, 'operate_warehouse');
        [$asal, $tujuan] = $this->asThc(fn (): array => [
            Warehouse::factory()->create(['mitra_id' => $mitra->id]),
            Warehouse::factory()->create(['mitra_id' => $mitra->id]),
        ]);
        $asal->users()->attach($user);
        $tujuan->users()->attach($user);
        $material = Material::factory()->create(['kode' => 'MAT-TRANSFER', 'nama' => 'Material Transfer']);

        $this->actingAs($user)->post('/warehouse/stock/receive', [
            'warehouse_id' => $asal->id,
            'material_id' => $material->id,
            'qty' => '5',
            'reason' => 'Stok awal',
        ])->assertRedirect();
        $this->actingAs($user)->post('/warehouse/transfers', [
            'warehouse_asal_id' => $asal->id,
            'warehouse_tujuan_id' => $tujuan->id,
            'tanggal' => '2026-08-19',
            'pengirim' => 'Petugas',
            'items' => [['material_id' => $material->id, 'qty' => '2']],
        ])->assertRedirect();
        $transferId = (int) DB::table('surat_jalans')->value('id');

        $this->actingAs($user)
            ->get(route('warehouse.transfers.show', $transferId))
            ->assertOk()
            ->assertSee('Terima Surat Jalan')
            ->assertSee('Batalkan Surat Jalan')
            ->assertSee('Koreksi Buku Transaksi')
            ->assertSee('Material Transfer');

        $tujuan->users()->detach($user);

        $this->actingAs($user)
            ->get(route('warehouse.transfers.show', $transferId))
            ->assertOk()
            ->assertDontSee('Koreksi Buku Transaksi');

        $this->actingAs($user)
            ->post(route('warehouse.transfers.receive', $transferId))
            ->assertForbidden();

        $unassigned = $this->userWithPermissions(null, 'operate_warehouse');

        $this->actingAs($unassigned)
            ->get(route('warehouse.transfers.show', $transferId))
            ->assertForbidden();
    }

    private function userWithPermissions(?int $mitraId, string ...$permissions): User
    {
        $group = Grup::factory()->create();
        foreach ($permissions as $permission) {
            $group->izins()->attach(Izin::query()->firstOrCreate(['kode' => $permission], ['nama' => $permission]));
        }

        return User::factory()->create(['mitra_id' => $mitraId, 'grup_id' => $group->id]);
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

    /**
     * Kait yang dipakai `warehouse-material-form.js` untuk menemukan kedua select gudang. Sebelum
     * #165 tidak ada satu pun test PHP yang menyebut atribut ini: penjaga glosarium hanya bisa
     * membuktikan ejaan Inggrisnya hilang, bukan bahwa atributnya masih ada, jadi rename yang
     * keliru menghapusnya akan lolos. Literal yang sama dipakai fixture DOM di
     * tests/JavaScript/warehouse-material-form.test.js -- keduanya harus bergerak bersama.
     */
    public function test_form_terbitkan_surat_jalan_menandai_select_gudang_dengan_asal_dan_tujuan(): void
    {
        $mitra = Mitra::factory()->create();
        $user = $this->userWithPermissions(null, 'operate_warehouse', 'read_master_data');
        $asal = $this->asThc(fn (): Warehouse => Warehouse::factory()->create(['mitra_id' => $mitra->id]));
        $asal->users()->attach($user);
        // Form Terbitkan Surat Jalan hanya dirender bila ada Material dengan Unit/Satuan aktif.
        Material::factory()->create();

        $this->actingAs($user)
            ->get(route('warehouse.index'))
            ->assertOk()
            ->assertSee('data-asal-select', false)
            ->assertSee('data-tujuan-select', false);
    }
}
