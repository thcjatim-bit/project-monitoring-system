<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Material;
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
            ->assertSee($suratJalanId)
            ->assertSee('4.000');

        $this->actingAs($user)
            ->get("/warehouse/transfers/{$suratJalanId}/print")
            ->assertOk()
            ->assertSee('SURAT JALAN')
            ->assertSee($suratJalanNumber)
            ->assertSee($origin->kode)
            ->assertSee($destination->kode)
            ->assertSee('Tanda tangan penerima')
            ->assertSee('QR');
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
        $group->izins()->attach(Izin::factory()->create(['kode' => 'operate_warehouse']));

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
