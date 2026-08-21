<?php

namespace Tests\Feature;

use App\Models\Drum;
use App\Models\Grup;
use App\Models\Izin;
use App\Models\Material;
use App\Models\MaterialSn;
use App\Models\Mitra;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\TenantDatabaseContext;
use Closure;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class SuratJalanTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_petugas_gudang_can_issue_a_direct_transfer_into_transit(): void
    {
        $mitra = Mitra::factory()->create();
        [$origin, $destination] = $this->warehousesFor($mitra);
        $material = Material::factory()->create(['jenis' => 'biasa']);
        $user = $this->userWithWarehousePermission($mitra);
        $origin->users()->attach($user);
        $destination->users()->attach($user);

        $this->actingAs($user)->post('/warehouse/stock/receive', [
            'warehouse_id' => $origin->id,
            'material_id' => $material->id,
            'qty' => '10',
            'reason' => 'Penerimaan awal',
        ])->assertRedirect();

        $response = $this->actingAs($user)->post('/warehouse/transfers', [
            'warehouse_asal_id' => $origin->id,
            'warehouse_tujuan_id' => $destination->id,
            'tanggal' => '2026-08-15',
            'pengirim' => 'Petugas Gudang',
            'sopir' => 'Budi',
            'plat_nomor' => 'L 1234 THC',
            'items' => [
                ['material_id' => $material->id, 'qty' => '4'],
            ],
        ]);

        $response->assertRedirect();

        $suratJalanId = DB::table('surat_jalans')->value('id');
        $this->assertNotNull($suratJalanId);
        $this->assertDatabaseHas('surat_jalans', [
            'id' => $suratJalanId,
            'status' => 'terbit',
            'warehouse_asal_id' => $origin->id,
            'warehouse_tujuan_id' => $destination->id,
        ]);
        $this->assertDatabaseHas('surat_jalan_items', [
            'surat_jalan_id' => $suratJalanId,
            'material_id' => $material->id,
            'qty' => '4.000',
        ]);
        $this->assertDatabaseHas('material_stoks', [
            'warehouse_id' => $origin->id,
            'material_id' => $material->id,
            'lokasi_tipe' => 'warehouse',
            'qty' => '6.000',
        ]);
        $this->assertDatabaseHas('material_stoks', [
            'warehouse_id' => $origin->id,
            'material_id' => $material->id,
            'lokasi_tipe' => 'transit',
            'lokasi_id' => $suratJalanId,
            'qty' => '4.000',
        ]);
        $this->assertDatabaseCount('material_transaksis', 3);
    }

    public function test_receiving_petugas_moves_the_verified_transfer_from_transit_to_destination(): void
    {
        [$origin, $destination, $material, $user] = $this->issueOrdinaryTransfer();
        $suratJalanId = DB::table('surat_jalans')->value('id');

        $this->actingAs($user)
            ->post("/warehouse/transfers/{$suratJalanId}/receive")
            ->assertRedirect();

        $this->assertDatabaseHas('surat_jalans', [
            'id' => $suratJalanId,
            'status' => 'diterima',
            'received_by' => $user->id,
        ]);
        $this->assertDatabaseHas('material_stoks', [
            'warehouse_id' => $origin->id,
            'material_id' => $material->id,
            'lokasi_tipe' => 'warehouse',
            'qty' => '6.000',
        ]);
        $this->assertDatabaseHas('material_stoks', [
            'warehouse_id' => $origin->id,
            'material_id' => $material->id,
            'lokasi_tipe' => 'transit',
            'lokasi_id' => $suratJalanId,
            'qty' => '0.000',
        ]);
        $this->assertDatabaseHas('material_stoks', [
            'warehouse_id' => $destination->id,
            'material_id' => $material->id,
            'lokasi_tipe' => 'warehouse',
            'qty' => '4.000',
        ]);
        $this->assertDatabaseCount('material_transaksis', 5);
    }

    public function test_transit_report_is_separate_from_warehouse_stock_and_print_contains_delivery_contract(): void
    {
        [$origin, $destination, $material, $user] = $this->issueOrdinaryTransfer();
        $suratJalanId = DB::table('surat_jalans')->value('id');
        $suratJalanNumber = DB::table('surat_jalans')->value('nomor');

        $this->actingAs($user)
            ->get('/warehouse/transit')
            ->assertOk()
            ->assertSee($suratJalanNumber)
            ->assertSee('4')
            ->assertSee($origin->kode.' — '.$origin->nama)
            ->assertSee($destination->kode.' — '.$destination->nama)
            ->assertSee('Dalam Transit')
            ->assertSee('ui-badge--info', false)
            ->assertSee('Detail')
            ->assertSee('Cetak')
            ->assertDontSee('SJ #'.$suratJalanId);

        $this->actingAs($user)
            ->get("/warehouse/transfers/{$suratJalanId}/print")
            ->assertOk()
            ->assertSee('SURAT JALAN')
            ->assertSee($suratJalanNumber)
            ->assertSee($origin->kode)
            ->assertSee($destination->kode)
            ->assertSee('Tanda tangan penerima')
            ->assertSee('QR')
            ->assertSee('<td>4</td>', false);
    }

    public function test_transit_status_is_calculated_per_item_for_multi_item_transfers(): void
    {
        $mitra = Mitra::factory()->create();
        [$origin, $destination] = $this->warehousesFor($mitra);
        $first = Material::factory()->create(['jenis' => 'biasa']);
        $second = Material::factory()->create(['jenis' => 'biasa']);
        $user = $this->userWithWarehousePermission($mitra);
        $origin->users()->attach($user);
        $destination->users()->attach($user);

        foreach ([$first, $second] as $material) {
            $this->actingAs($user)->post('/warehouse/stock/receive', [
                'warehouse_id' => $origin->id,
                'material_id' => $material->id,
                'qty' => '10',
                'reason' => 'Penerimaan awal',
            ])->assertRedirect();
        }

        $this->actingAs($user)->post('/warehouse/transfers', [
            'warehouse_asal_id' => $origin->id,
            'warehouse_tujuan_id' => $destination->id,
            'tanggal' => '2026-08-15',
            'pengirim' => 'Petugas Gudang',
            'items' => [
                ['material_id' => $first->id, 'qty' => '4'],
                ['material_id' => $second->id, 'qty' => '5'],
            ],
        ])->assertRedirect();

        $suratJalanId = DB::table('surat_jalans')->value('id');
        $firstItemId = DB::table('surat_jalan_items')->where('material_id', $first->id)->value('id');

        $this->actingAs($user)->post("/warehouse/transfers/{$suratJalanId}/receive", [
            'items' => [['surat_jalan_item_id' => $firstItemId, 'qty' => '1']],
        ])->assertRedirect();

        $response = $this->actingAs($user)->get('/warehouse/transit')->assertOk();
        $response->assertSee('Sebagian diterima')->assertSee('Dalam Transit');
        $this->assertSame(1, substr_count($response->getContent(), 'Sebagian diterima'));
        $this->assertSame(1, substr_count($response->getContent(), 'Dalam Transit'));
    }

    public function test_read_transit_grants_own_transfer_detail_and_print_without_dashboard_permission(): void
    {
        [$origin] = $this->issueOrdinaryTransfer();
        $suratJalanId = DB::table('surat_jalans')->value('id');
        $reader = $this->userWithPermission(Mitra::query()->findOrFail($origin->mitra_id), 'read_transit');
        $withoutTransit = User::factory()->create(['mitra_id' => $origin->mitra_id]);

        $this->actingAs($reader)
            ->get(route('warehouse.transfers.show', $suratJalanId))
            ->assertOk();

        $this->actingAs($reader)
            ->get(route('warehouse.transfers.print', $suratJalanId))
            ->assertOk();

        $this->actingAs($withoutTransit)
            ->get(route('warehouse.transfers.show', $suratJalanId))
            ->assertForbidden();

        $this->actingAs($withoutTransit)
            ->get(route('warehouse.transfers.print', $suratJalanId))
            ->assertForbidden();

        $otherMitra = Mitra::factory()->create();
        $otherReader = $this->userWithPermission($otherMitra, 'read_transit');

        $this->actingAs($otherReader)
            ->get(route('warehouse.transfers.show', $suratJalanId))
            ->assertNotFound();

    }

    public function test_direct_transfer_moves_serial_number_and_drum_identities_without_consuming_them(): void
    {
        $mitra = Mitra::factory()->create();
        [$origin, $destination] = $this->warehousesFor($mitra);
        $user = $this->userWithWarehousePermission($mitra);
        $origin->users()->attach($user);
        $destination->users()->attach($user);

        $serialMaterial = Material::factory()->create(['jenis' => 'ber_sn']);
        $drumMaterial = Material::factory()->create(['jenis' => 'drum_kabel']);

        $this->actingAs($user)->post('/warehouse/stock/receive', [
            'warehouse_id' => $origin->id,
            'material_id' => $serialMaterial->id,
            'serial_number' => 'SN-TRANSFER-001',
            'qty' => '1',
            'reason' => 'Penerimaan SN',
        ])->assertRedirect();
        $this->actingAs($user)->post('/warehouse/stock/receive', [
            'warehouse_id' => $origin->id,
            'material_id' => $drumMaterial->id,
            'drum_id' => 'DRM-TRANSFER-001',
            'qty' => '200',
            'reason' => 'Penerimaan drum',
        ])->assertRedirect();

        $this->actingAs($user)->post('/warehouse/transfers', [
            'warehouse_asal_id' => $origin->id,
            'warehouse_tujuan_id' => $destination->id,
            'tanggal' => '2026-08-15',
            'pengirim' => 'Petugas Gudang',
            'items' => [
                ['material_id' => $serialMaterial->id, 'qty' => '1', 'serial_number' => 'SN-TRANSFER-001'],
                ['material_id' => $drumMaterial->id, 'qty' => '200', 'drum_id' => 'DRM-TRANSFER-001'],
            ],
        ])->assertRedirect();

        $serial = MaterialSn::query()->where('serial_number', 'SN-TRANSFER-001')->firstOrFail();
        $drum = Drum::query()->where('drum_id', 'DRM-TRANSFER-001')->firstOrFail();
        $this->assertSame('tersedia', $serial->status);
        $this->assertSame('transit', $serial->lokasi_tipe);
        $this->assertSame('200.000', $drum->sisa);
        $this->assertSame('transit', $drum->lokasi_tipe);

        $suratJalanId = DB::table('surat_jalans')->value('id');
        $this->actingAs($user)->post("/warehouse/transfers/{$suratJalanId}/receive")->assertRedirect();

        $this->assertDatabaseHas('material_sns', [
            'id' => $serial->id,
            'status' => 'tersedia',
            'lokasi_tipe' => 'warehouse',
            'lokasi_id' => $destination->id,
        ]);
        $this->assertDatabaseHas('drums', [
            'id' => $drum->id,
            'sisa' => '200.000',
            'lokasi_tipe' => 'warehouse',
            'lokasi_id' => $destination->id,
        ]);
    }

    public function test_receiving_part_of_a_transfer_leaves_the_residual_in_transit_until_it_is_resolved_as_lost(): void
    {
        [$origin, $destination, $material, $user] = $this->issueOrdinaryTransfer();
        $suratJalanId = DB::table('surat_jalans')->value('id');
        $itemId = DB::table('surat_jalan_items')->value('id');
        $thc = $this->thcUserWithWarehousePermission();
        $origin->users()->attach($thc);
        $destination->users()->attach($thc);

        $this->actingAs($user)
            ->post("/warehouse/transfers/{$suratJalanId}/receive", [
                'items' => [['surat_jalan_item_id' => $itemId, 'qty' => '2']],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('surat_jalans', ['id' => $suratJalanId, 'status' => 'terbit']);
        $this->assertDatabaseHas('surat_jalan_items', [
            'id' => $itemId,
            'qty_diterima' => '2.000',
        ]);
        $this->assertDatabaseHas('material_stoks', [
            'warehouse_id' => $origin->id,
            'material_id' => $material->id,
            'lokasi_tipe' => 'transit',
            'lokasi_id' => $suratJalanId,
            'qty' => '2.000',
        ]);

        $this->actingAs($thc)
            ->post("/warehouse/transfers/{$suratJalanId}/resolve", ['resolution' => 'hilang_dalam_perjalanan'])
            ->assertRedirect();

        $this->assertDatabaseHas('surat_jalans', [
            'id' => $suratJalanId,
            'status' => 'diterima',
            'transit_resolution' => 'hilang_dalam_perjalanan',
            'resolved_by' => $thc->id,
        ]);
        $this->assertDatabaseHas('material_stoks', [
            'warehouse_id' => $origin->id,
            'material_id' => $material->id,
            'lokasi_tipe' => 'transit',
            'lokasi_id' => $suratJalanId,
            'qty' => '0.000',
        ]);
        $this->assertDatabaseHas('material_transaksis', [
            'surat_jalan_id' => $suratJalanId,
            'jenis_transaksi' => 'hilang_dalam_perjalanan',
            'qty_delta' => '-2.000',
            'actor_id' => $thc->id,
        ]);
    }

    public function test_thc_can_resolve_a_partial_transit_by_returning_the_residual_to_origin(): void
    {
        [$origin, $destination, $material, $user] = $this->issueOrdinaryTransfer();
        $suratJalanId = DB::table('surat_jalans')->value('id');
        $itemId = DB::table('surat_jalan_items')->value('id');
        $thc = $this->thcUserWithWarehousePermission();
        $origin->users()->attach($thc);
        $destination->users()->attach($thc);

        $this->actingAs($user)->post("/warehouse/transfers/{$suratJalanId}/receive", [
            'items' => [['surat_jalan_item_id' => $itemId, 'qty' => '2']],
        ])->assertRedirect();

        $this->actingAs($thc)->post("/warehouse/transfers/{$suratJalanId}/resolve", [
            'resolution' => 'kembali_ke_asal',
        ])->assertRedirect();

        $this->assertDatabaseHas('surat_jalans', [
            'id' => $suratJalanId,
            'status' => 'diterima',
            'transit_resolution' => 'kembali_ke_asal',
            'resolved_by' => $thc->id,
        ]);
        $this->assertDatabaseHas('material_stoks', [
            'warehouse_id' => $origin->id,
            'material_id' => $material->id,
            'lokasi_tipe' => 'warehouse',
            'lokasi_id' => $origin->id,
            'qty' => '8.000',
        ]);
        $this->assertDatabaseHas('material_transaksis', [
            'surat_jalan_id' => $suratJalanId,
            'lokasi_tipe' => 'warehouse',
            'qty_delta' => '2.000',
            'actor_id' => $thc->id,
        ]);
    }

    public function test_thc_can_cancel_an_unreceived_transfer(): void
    {
        [$origin, $destination, $material, $user] = $this->issueOrdinaryTransfer();
        $suratJalanId = DB::table('surat_jalans')->value('id');
        $thc = $this->thcUserWithWarehousePermission();
        $origin->users()->attach($thc);
        $destination->users()->attach($thc);

        $this->actingAs($thc)
            ->post("/warehouse/transfers/{$suratJalanId}/cancel")
            ->assertRedirect();

        $this->assertDatabaseHas('surat_jalans', ['id' => $suratJalanId, 'status' => 'dibatalkan']);
        $this->assertDatabaseHas('material_stoks', [
            'warehouse_id' => $origin->id,
            'material_id' => $material->id,
            'lokasi_tipe' => 'warehouse',
            'lokasi_id' => $origin->id,
            'qty' => '10.000',
        ]);

    }

    public function test_thc_cannot_cancel_a_transfer_after_receipt(): void
    {
        [$origin, $destination, , $user] = $this->issueOrdinaryTransfer();
        $suratJalanId = DB::table('surat_jalans')->value('id');
        $thc = $this->thcUserWithWarehousePermission();
        $origin->users()->attach($thc);
        $destination->users()->attach($thc);

        $this->actingAs($user)->post("/warehouse/transfers/{$suratJalanId}/receive")->assertRedirect();
        $this->actingAs($thc)
            ->post("/warehouse/transfers/{$suratJalanId}/cancel")
            ->assertSessionHasErrors('status');
    }

    public function test_a_received_transfer_returns_through_a_new_reverse_surat_jalan(): void
    {
        [$origin, $destination, $material, $user] = $this->issueOrdinaryTransfer();
        $suratJalanId = DB::table('surat_jalans')->value('id');
        $thc = $this->thcUserWithWarehousePermission();
        $origin->users()->attach($thc);
        $destination->users()->attach($thc);

        $this->actingAs($user)->post("/warehouse/transfers/{$suratJalanId}/receive")->assertRedirect();

        $response = $this->actingAs($thc)->post("/warehouse/transfers/{$suratJalanId}/return", [
            'tanggal' => '2026-08-15',
            'pengirim' => 'Petugas THC',
        ]);

        $response->assertRedirect();
        $returnId = DB::table('surat_jalans')->where('retur_dari_id', $suratJalanId)->value('id');
        $this->assertNotNull($returnId);
        $this->assertDatabaseHas('surat_jalans', [
            'id' => $returnId,
            'status' => 'terbit',
            'warehouse_asal_id' => $destination->id,
            'warehouse_tujuan_id' => $origin->id,
            'retur_dari_id' => $suratJalanId,
        ]);
        $this->assertDatabaseHas('material_stoks', [
            'warehouse_id' => $destination->id,
            'material_id' => $material->id,
            'lokasi_tipe' => 'transit',
            'lokasi_id' => $returnId,
            'qty' => '4.000',
        ]);
    }

    public function test_thc_correction_appends_reversal_and_corrected_rows_without_mutating_the_original(): void
    {
        [$origin, $destination, $material, $user] = $this->issueOrdinaryTransfer();
        $suratJalanId = DB::table('surat_jalans')->value('id');
        $thc = $this->thcUserWithWarehousePermission();
        $origin->users()->attach($thc);
        $destination->users()->attach($thc);

        $this->actingAs($user)->post("/warehouse/transfers/{$suratJalanId}/receive")->assertRedirect();
        $original = DB::table('material_transaksis')
            ->where('surat_jalan_id', $suratJalanId)
            ->where('lokasi_tipe', 'warehouse')
            ->where('qty_delta', '4.000')
            ->first();

        $this->actingAs($thc)
            ->post("/warehouse/material-transactions/{$original->id}/correct", [
                'qty_delta' => '3',
                'reason' => 'Koreksi jumlah diterima',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('material_transaksis', [
            'id' => $original->id,
            'qty_delta' => '4.000',
            'jenis_transaksi' => 'receipt',
        ]);
        $this->assertDatabaseHas('material_transaksis', [
            'koreksi_dari_id' => $original->id,
            'qty_delta' => '-4.000',
            'jenis_transaksi' => 'koreksi',
            'reason' => 'Koreksi jumlah diterima',
            'actor_id' => $thc->id,
        ]);
        $this->assertDatabaseHas('material_transaksis', [
            'koreksi_dari_id' => $original->id,
            'qty_delta' => '3.000',
            'jenis_transaksi' => 'koreksi',
            'reason' => 'Koreksi jumlah diterima',
            'actor_id' => $thc->id,
        ]);
        $this->assertDatabaseHas('material_stoks', [
            'warehouse_id' => $destination->id,
            'material_id' => $material->id,
            'lokasi_tipe' => 'warehouse',
            'lokasi_id' => $destination->id,
            'qty' => '3.000',
        ]);
    }

    public function test_mitra_cannot_use_thc_only_surat_jalan_correction_endpoints(): void
    {
        [$origin, $destination, , $user] = $this->issueOrdinaryTransfer();
        $suratJalanId = DB::table('surat_jalans')->value('id');

        $this->actingAs($user)
            ->post("/warehouse/transfers/{$suratJalanId}/cancel")
            ->assertForbidden();
    }

    /** @return array{Warehouse, Warehouse, Material, User} */
    private function issueOrdinaryTransfer(): array
    {
        $mitra = Mitra::factory()->create();
        [$origin, $destination] = $this->warehousesFor($mitra);
        $material = Material::factory()->create(['jenis' => 'biasa']);
        $user = $this->userWithWarehousePermission($mitra);
        $origin->users()->attach($user);
        $destination->users()->attach($user);

        $this->actingAs($user)->post('/warehouse/stock/receive', [
            'warehouse_id' => $origin->id,
            'material_id' => $material->id,
            'qty' => '10',
            'reason' => 'Penerimaan awal',
        ])->assertRedirect();

        $this->actingAs($user)->post('/warehouse/transfers', [
            'warehouse_asal_id' => $origin->id,
            'warehouse_tujuan_id' => $destination->id,
            'tanggal' => '2026-08-15',
            'pengirim' => 'Petugas Gudang',
            'sopir' => 'Budi',
            'plat_nomor' => 'L 1234 THC',
            'items' => [['material_id' => $material->id, 'qty' => '4']],
        ])->assertRedirect();

        return [$origin, $destination, $material, $user];
    }

    /** @return array{Warehouse, Warehouse} */
    private function warehousesFor(Mitra $mitra): array
    {
        return $this->asThc(fn (): array => [
            Warehouse::factory()->create(['mitra_id' => $mitra->id]),
            Warehouse::factory()->create(['mitra_id' => $mitra->id]),
        ]);
    }

    private function userWithWarehousePermission(Mitra $mitra): User
    {
        $group = Grup::factory()->create();
        $group->izins()->attach(Izin::query()->firstOrCreate(
            ['kode' => 'operate_warehouse'],
            ['nama' => 'Operate warehouse'],
        ));

        return User::factory()->create(['mitra_id' => $mitra->id, 'grup_id' => $group->id]);
    }

    private function userWithPermission(Mitra $mitra, string $permission): User
    {
        $group = Grup::factory()->create();
        $group->izins()->attach(Izin::query()->firstOrCreate(
            ['kode' => $permission],
            ['nama' => $permission],
        ));

        return User::factory()->create(['mitra_id' => $mitra->id, 'grup_id' => $group->id]);
    }

    private function thcUserWithWarehousePermission(): User
    {
        $group = Grup::factory()->create();
        $group->izins()->attach(Izin::query()->firstOrCreate(
            ['kode' => 'operate_warehouse'],
            ['nama' => 'Operate warehouse'],
        ));

        return User::factory()->create(['mitra_id' => null, 'grup_id' => $group->id]);
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
