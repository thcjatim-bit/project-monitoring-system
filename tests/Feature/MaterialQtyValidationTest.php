<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialRequest;
use App\Models\Mitra;
use App\Models\ProjectRekon;
use App\Models\ProjectRekonItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\RefreshDatabase;
use Tests\Concerns\WarehouseFixtures;
use Tests\TestCase;

class MaterialQtyValidationTest extends TestCase
{
    use RefreshDatabase;
    use WarehouseFixtures;

    public function test_material_request_rejects_fractional_qty_for_every_material_type(): void
    {
        $mitra = Mitra::factory()->create();
        $user = $this->userWith($mitra, 'create_material_request', 'read_material_request');

        foreach (['biasa', 'ber_sn', 'drum_kabel'] as $jenis) {
            $material = Material::factory()->create(['jenis' => $jenis]);

            $response = $this->actingAs($user)->post(route('material-requests.store'), [
                'items' => [['material_id' => $material->id, 'qty' => '2.5']],
            ]);

            $this->assertWholeQtyError($response, 'items.0.qty', $this->reasonFor($jenis));
        }

        $this->assertSame(0, MaterialRequest::query()->count());
    }

    public function test_warehouse_receive_and_issue_reject_fractional_qty_for_biasa_and_drum_kabel(): void
    {
        $warehouse = $this->warehouse(null, 'WH-QTY-VALIDATION');
        $user = $this->userWith(null, 'operate_warehouse');
        $warehouse->users()->attach($user);

        foreach (['biasa', 'drum_kabel'] as $jenis) {
            $material = Material::factory()->create(['jenis' => $jenis]);
            $wholeIdentity = $jenis === 'drum_kabel' ? ['drum_id' => 'DRM-WHOLE-'.$material->id] : [];
            $fractionalIdentity = $jenis === 'drum_kabel' ? ['drum_id' => 'DRM-FRACTION-'.$material->id] : [];

            $this->actingAs($user)->post(route('warehouse.stock.receive'), [
                'warehouse_id' => $warehouse->id,
                'material_id' => $material->id,
                'qty' => '10',
                'reason' => 'Stok awal',
            ] + $wholeIdentity)->assertRedirect();

            $response = $this->actingAs($user)->post(route('warehouse.stock.receive'), [
                'warehouse_id' => $warehouse->id,
                'material_id' => $material->id,
                'qty' => '2.5',
                'reason' => 'Qty pecahan tidak boleh masuk',
            ] + $fractionalIdentity);

            $this->assertWholeQtyError($response, 'qty', $this->reasonFor($jenis));

            $response = $this->actingAs($user)->post(route('warehouse.stock.issue'), [
                'warehouse_id' => $warehouse->id,
                'material_id' => $material->id,
                'qty' => '2.5',
                'reason' => 'Qty pecahan tidak boleh keluar',
            ] + $wholeIdentity);

            $this->assertWholeQtyError($response, 'qty', $this->reasonFor($jenis));
        }
    }

    public function test_drum_split_rejects_fractional_cut_length(): void
    {
        $warehouse = $this->warehouse(null, 'WH-DRUM-QTY');
        $material = Material::factory()->create(['jenis' => 'drum_kabel']);
        $user = $this->userWith(null, 'operate_warehouse');
        $warehouse->users()->attach($user);

        $this->actingAs($user)->post(route('warehouse.stock.receive'), [
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'drum_id' => 'DRM-SPLIT-QTY',
            'qty' => '100',
            'reason' => 'Penerimaan drum',
        ])->assertRedirect();

        $response = $this->actingAs($user)->post(route('warehouse.stock.drum-split'), [
            'warehouse_id' => $warehouse->id,
            'drum_id' => 'DRM-SPLIT-QTY',
            'qty' => '2.5',
            'reason' => 'Potongan pecahan tidak boleh',
        ]);

        $this->assertWholeQtyError($response, 'qty', 'meter');
        $this->assertSame(1, DB::table('drums')->where('drum_id', 'DRM-SPLIT-QTY')->count());
    }

    public function test_surat_jalan_issue_rejects_fractional_qty_before_creating_a_transfer(): void
    {
        $origin = $this->warehouse(null, 'WH-ASAL-QTY');
        $destination = $this->warehouse(null, 'WH-TUJUAN-QTY');
        $material = Material::factory()->create(['jenis' => 'biasa']);
        $user = $this->userWith(null, 'operate_warehouse');
        $origin->users()->attach($user);
        $destination->users()->attach($user);

        $this->actingAs($user)->post(route('warehouse.stock.receive'), [
            'warehouse_id' => $origin->id,
            'material_id' => $material->id,
            'qty' => '10',
            'reason' => 'Stok awal',
        ])->assertRedirect();

        $response = $this->actingAs($user)->post(route('warehouse.transfers.issue'), [
            'warehouse_asal_id' => $origin->id,
            'warehouse_tujuan_id' => $destination->id,
            'tanggal' => '2026-08-24',
            'pengirim' => 'Petugas Gudang',
            'items' => [['material_id' => $material->id, 'qty' => '2.5']],
        ]);

        $this->assertWholeQtyError($response, 'items.0.qty', 'unit');
        $this->assertSame(0, DB::table('surat_jalans')->count());
    }

    public function test_surat_jalan_receive_rejects_fractional_qty(): void
    {
        [$suratJalanId, $itemId, $user] = $this->issuedTransfer();

        $response = $this->actingAs($user)->post(route('warehouse.transfers.receive', $suratJalanId), [
            'items' => [['surat_jalan_item_id' => $itemId, 'qty' => '1.5']],
        ]);

        $this->assertWholeQtyError($response, 'items.0.qty', 'unit');
    }

    public function test_surat_jalan_return_rejects_fractional_qty(): void
    {
        [$suratJalanId, $itemId, $user] = $this->issuedTransfer();

        $this->actingAs($user)->post(route('warehouse.transfers.receive', $suratJalanId), [
            'items' => [['surat_jalan_item_id' => $itemId, 'qty' => '4']],
        ])->assertRedirect();

        $response = $this->actingAs($user)->post(route('warehouse.transfers.return', $suratJalanId), [
            'tanggal' => '2026-08-24',
            'pengirim' => 'Petugas Gudang',
            'items' => [['surat_jalan_item_id' => $itemId, 'qty' => '1.5']],
        ]);

        $this->assertWholeQtyError($response, 'items.0.qty', 'unit');
    }

    public function test_surat_jalan_correction_rejects_fractional_qty_delta(): void
    {
        [$suratJalanId, $itemId, $user] = $this->issuedTransfer();

        $this->actingAs($user)->post(route('warehouse.transfers.receive', $suratJalanId), [
            'items' => [['surat_jalan_item_id' => $itemId, 'qty' => '4']],
        ])->assertRedirect();
        $transactionId = DB::table('material_transaksis')
            ->where('surat_jalan_id', $suratJalanId)
            ->where('jenis_transaksi', 'receipt')
            ->whereNull('koreksi_dari_id')
            ->value('id');

        $response = $this->actingAs($user)->post(route('warehouse.material-transactions.correct', $transactionId), [
            'qty_delta' => '1.5',
            'reason' => 'Koreksi pecahan tidak boleh',
        ]);

        $this->assertWholeQtyError($response, 'qty_delta', 'unit');
    }

    public function test_material_usage_rejects_fractional_qty(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->project($mitra, 'PRJ-2608-QTY1');
        $warehouse = $this->warehouse($mitra, 'WH-USAGE-QTY');
        $material = Material::factory()->create(['jenis' => 'biasa']);
        $user = $this->userWith($mitra, 'create_material_usage');

        $response = $this->actingAs($user)->post(route('projects.material-usages.store', $project), [
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'qty' => '1.5',
        ]);

        $this->assertWholeQtyError($response, 'qty', 'unit');
        $this->assertSame(0, DB::table('pemakaian_materials')->count());
    }

    public function test_project_rekon_rejects_fractional_qty_in_each_material_quantity_field(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->project($mitra, 'PRJ-2608-QTY2');
        $warehouse = $this->warehouse($mitra, 'WH-REKON-QTY');
        $material = Material::factory()->create(['jenis' => 'biasa']);
        $thc = $this->userWith(null, 'read_project', 'read_material_rekon', 'edit_material_rekon');
        $rekon = $this->asThc(fn (): ProjectRekon => ProjectRekon::query()->create([
            'nomor' => 'REK-2608-QTY2',
            'mitra_id' => $mitra->id,
            'project_id' => $project->id,
            'source' => 'manual',
            'status' => 'diajukan',
            'opened_by' => $thc->id,
        ]));
        $item = $this->asThc(fn (): ProjectRekonItem => ProjectRekonItem::query()->create([
            'mitra_id' => $mitra->id,
            'project_rekon_id' => $rekon->id,
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'keluar_gudang' => '6',
            'terpasang' => '0',
            'sisa_project' => '6',
            'dikembalikan' => '0',
            'hilang_rusak' => '0',
            'penanggung_jawab' => 'mitra',
        ]));

        foreach (['keluar_gudang', 'terpasang', 'sisa_project', 'dikembalikan', 'hilang_rusak'] as $field) {
            $row = [
                'id' => $item->id,
                'keluar_gudang' => '6',
                'terpasang' => '0',
                'sisa_project' => '6',
                'dikembalikan' => '0',
                'hilang_rusak' => '0',
                'penanggung_jawab' => 'mitra',
            ];
            $row[$field] = '1.5';

            $response = $this->actingAs($thc)->patch(route('project-rekons.update', $rekon), [
                'items' => [$item->id => $row],
            ]);

            $this->assertWholeQtyError($response, 'items.'.$item->id.'.'.$field, 'unit');
        }
    }

    public function test_material_usage_form_advertises_whole_qty(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->project($mitra, 'PRJ-2608-QTY3');
        $this->warehouse($mitra, 'WH-USAGE-FORM');
        Material::factory()->create(['jenis' => 'biasa']);
        $user = $this->userWith($mitra, 'read_material_usage', 'create_material_usage');

        $this->actingAs($user)
            ->get(route('material-usages.index'))
            ->assertOk()
            ->assertSee('name="qty" type="number" min="1" step="1" required', false)
            ->assertDontSee('name="qty" type="number" min="0.001" step="0.001"', false);
    }

    public function test_project_rekon_form_advertises_whole_qty(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->project($mitra, 'PRJ-2608-QTY4');
        $warehouse = $this->warehouse($mitra, 'WH-REKON-FORM');
        $material = Material::factory()->create(['jenis' => 'biasa']);
        $thc = $this->userWith(null, 'read_project', 'read_material_rekon', 'edit_material_rekon');
        $rekon = $this->asThc(fn (): ProjectRekon => ProjectRekon::query()->create([
            'nomor' => 'REK-2608-QTY4',
            'mitra_id' => $mitra->id,
            'project_id' => $project->id,
            'source' => 'manual',
            'status' => 'diajukan',
            'opened_by' => $thc->id,
        ]));
        $this->asThc(fn () => ProjectRekonItem::query()->create([
            'mitra_id' => $mitra->id,
            'project_rekon_id' => $rekon->id,
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'keluar_gudang' => '6',
            'terpasang' => '0',
            'sisa_project' => '6',
            'dikembalikan' => '0',
            'hilang_rusak' => '0',
            'penanggung_jawab' => 'mitra',
        ]));

        $this->actingAs($thc)
            ->get(route('projects.rekons.index', $project))
            ->assertOk()
            ->assertSee('type="number" step="1" min="0"', false)
            ->assertDontSee('step="0.001"', false);
    }

    /** @return array{0:int,1:int,2:User} */
    private function issuedTransfer(): array
    {
        $origin = $this->warehouse(null, 'WH-TRANSFER-QTY-ASAL');
        $destination = $this->warehouse(null, 'WH-TRANSFER-QTY-TUJUAN');
        $material = Material::factory()->create(['jenis' => 'biasa']);
        $user = $this->userWith(null, 'operate_warehouse');
        $origin->users()->attach($user);
        $destination->users()->attach($user);

        $this->actingAs($user)->post(route('warehouse.stock.receive'), [
            'warehouse_id' => $origin->id,
            'material_id' => $material->id,
            'qty' => '10',
            'reason' => 'Stok awal',
        ])->assertRedirect();
        $this->actingAs($user)->post(route('warehouse.transfers.issue'), [
            'warehouse_asal_id' => $origin->id,
            'warehouse_tujuan_id' => $destination->id,
            'tanggal' => '2026-08-24',
            'pengirim' => 'Petugas Gudang',
            'items' => [['material_id' => $material->id, 'qty' => '4']],
        ])->assertRedirect();

        $suratJalanId = (int) DB::table('surat_jalans')->latest('id')->value('id');
        $itemId = (int) DB::table('surat_jalan_items')->where('surat_jalan_id', $suratJalanId)->value('id');

        return [$suratJalanId, $itemId, $user];
    }

    private function assertWholeQtyError(TestResponse $response, string $field, string $reason): void
    {
        $response
            ->assertSessionHasErrors($field)
            ->assertSessionHas('errors', static function ($errors) use ($field, $reason): bool {
                $message = strtolower((string) $errors->first($field));

                return str_contains($message, 'bilangan bulat') && str_contains($message, strtolower($reason));
            });
    }

    private function reasonFor(string $jenis): string
    {
        return match ($jenis) {
            'biasa' => 'unit',
            'ber_sn' => 'serial number',
            'drum_kabel' => 'meter',
        };
    }
}
