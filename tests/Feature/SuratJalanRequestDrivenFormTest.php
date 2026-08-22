<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialRequest;
use App\Models\Mitra;
use App\Models\SuratJalan;
use App\Models\User;
use App\Models\Warehouse;
use Tests\Concerns\RefreshDatabase;
use Tests\Concerns\WarehouseFixtures;
use Tests\TestCase;

/**
 * Form Terbitkan Surat Jalan yang request-driven diuji pada dua permukaan yang bertahan tanpa
 * browser: apa yang dirender halaman, dan apa yang diterima server saat form dikirim. Prefill
 * dan reset di klien diuji terpisah di tests/JavaScript/warehouse-material-form.test.js.
 */
class SuratJalanRequestDrivenFormTest extends TestCase
{
    use RefreshDatabase;
    use WarehouseFixtures;

    public function test_dropdown_request_dan_project_terender_untuk_gudang_tujuan_terpilih(): void
    {
        $mitra = Mitra::factory()->create();
        $mitraLain = Mitra::factory()->create();
        $operator = $this->userWith(null, 'operate_warehouse');
        // Gudang tujuan terpilih saat muat pertama adalah yang pertama menurut nama.
        $origin = $this->warehouse(null, 'WH-ASAL', 'A Gudang THC');
        $tujuan = $this->warehouse($mitra, 'WH-TUJUAN', 'B Gudang Mitra');
        $this->warehouse($mitraLain, 'WH-LAIN', 'C Gudang Mitra Lain');
        $origin->users()->attach($operator);

        $lengkap = Material::factory()->create(['jenis' => 'biasa']);
        $belum = Material::factory()->create(['jenis' => 'biasa']);
        $request = $this->materialRequest($mitra, 'disetujui', [[$lengkap, 10], [$belum, 5]]);
        $requestMitraLain = $this->materialRequest($mitraLain, 'disetujui', [[$belum, 3]]);
        $this->project($mitra, 'PRJ-2608-0001');
        $this->project($mitraLain, 'PRJ-2608-0002');
        $this->terbitkan($operator, $origin, $tujuan, [[$lengkap, '10']], $request);

        $response = $this->actingAs($operator)->get('/warehouse')->assertOk();

        $response->assertSee('— Tanpa Request Material —');
        $response->assertSee(sprintf(
            '#%d — %s · 2 item, 1 belum lengkap',
            $request->id,
            $request->created_at->format('d M Y'),
        ));
        $response->assertDontSee('#'.$requestMitraLain->id.' — '.$requestMitraLain->created_at->format('d M Y'));
        $response->assertSee('<option value="">— Tanpa Project —</option>', false);
        $response->assertSee('PRJ-2608-0001 — Project PRJ-2608-0001');
        $response->assertDontSee('PRJ-2608-0002 — Project PRJ-2608-0002');
    }

    public function test_mitra_efektif_null_merender_select_project_disabled_dengan_opsi_penjelas(): void
    {
        $operator = $this->userWith(null, 'operate_warehouse');
        $origin = $this->warehouse(null, 'WH-ASAL', 'A Gudang THC');
        $this->warehouse(null, 'WH-TUJUAN', 'B Gudang THC');
        $origin->users()->attach($operator);

        $response = $this->actingAs($operator)->get('/warehouse')->assertOk();

        $this->assertMatchesRegularExpression(
            '/<select name="project_id"[^>]*\bdisabled\b[^>]*><option value="">Gudang THC ke gudang THC — tanpa Project<\/option>/',
            $response->getContent(),
        );
        $response->assertDontSee('<option value="">— Tanpa Project —</option>', false);
    }

    public function test_baris_item_membawa_asal_usul_sebagai_hidden_input(): void
    {
        $operator = $this->userWith(null, 'operate_warehouse');
        $origin = $this->warehouse(null, 'WH-ASAL', 'A Gudang THC');
        $this->warehouse(null, 'WH-TUJUAN', 'B Gudang THC');
        $origin->users()->attach($operator);

        $this->actingAs($operator)
            ->get('/warehouse')
            ->assertOk()
            ->assertSee('<input type="hidden" name="items[0][asal]" value="manual" data-row-origin>', false);
    }

    public function test_menerbitkan_surat_jalan_tanpa_memilih_request_tetap_bisa(): void
    {
        $mitra = Mitra::factory()->create();
        $operator = $this->userWith(null, 'operate_warehouse');
        $origin = $this->warehouse(null, 'WH-ASAL', 'A Gudang THC');
        $tujuan = $this->warehouse($mitra, 'WH-TUJUAN', 'B Gudang Mitra');
        $origin->users()->attach($operator);
        $material = Material::factory()->create(['jenis' => 'biasa']);
        $this->terimaStok($operator, $origin, $material, '10');

        $this->actingAs($operator)->post('/warehouse/transfers', [
            'warehouse_asal_id' => $origin->id,
            'warehouse_tujuan_id' => $tujuan->id,
            'material_request_id' => '',
            'project_id' => '',
            'tanggal' => '2026-08-22',
            'pengirim' => 'Petugas Gudang',
            'items' => [['material_id' => $material->id, 'qty' => '4', 'asal' => 'manual']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $suratJalan = SuratJalan::query()->latest('id')->firstOrFail();
        $this->assertNull($suratJalan->material_request_id);
        $this->assertNull($suratJalan->project_id);
    }

    public function test_request_dan_project_yang_dikirim_form_tersimpan_utuh(): void
    {
        $mitra = Mitra::factory()->create();
        $operator = $this->userWith(null, 'operate_warehouse');
        $origin = $this->warehouse(null, 'WH-ASAL', 'A Gudang THC');
        $tujuan = $this->warehouse($mitra, 'WH-TUJUAN', 'B Gudang Mitra');
        $origin->users()->attach($operator);
        $material = Material::factory()->create(['jenis' => 'biasa']);
        $project = $this->project($mitra, 'PRJ-2608-0001');
        $request = $this->materialRequest($mitra, 'disetujui', [[$material, 10]], $project);

        $this->terbitkan($operator, $origin, $tujuan, [[$material, '10']], $request, $project->id);

        $suratJalan = SuratJalan::query()->latest('id')->firstOrFail();
        $this->assertSame($request->id, $suratJalan->material_request_id);
        $this->assertSame($project->id, $suratJalan->project_id);
        $this->assertSame($mitra->id, $suratJalan->mitra_id);
    }

    public function test_asal_usul_baris_yang_tidak_dikenal_ditolak(): void
    {
        $mitra = Mitra::factory()->create();
        $operator = $this->userWith(null, 'operate_warehouse');
        $origin = $this->warehouse(null, 'WH-ASAL', 'A Gudang THC');
        $tujuan = $this->warehouse($mitra, 'WH-TUJUAN', 'B Gudang Mitra');
        $origin->users()->attach($operator);
        $material = Material::factory()->create(['jenis' => 'biasa']);
        $this->terimaStok($operator, $origin, $material, '10');

        $this->actingAs($operator)->post('/warehouse/transfers', [
            'warehouse_asal_id' => $origin->id,
            'warehouse_tujuan_id' => $tujuan->id,
            'tanggal' => '2026-08-22',
            'pengirim' => 'Petugas Gudang',
            'items' => [['material_id' => $material->id, 'qty' => '4', 'asal' => 'entah']],
        ])->assertSessionHasErrors('items.0.asal');

        $this->assertSame(0, SuratJalan::query()->count());
    }

    private function terimaStok(User $operator, Warehouse $warehouse, Material $material, string $qty): void
    {
        $this->actingAs($operator)->post('/warehouse/stock/receive', [
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'qty' => $qty,
            'reason' => 'Penerimaan awal',
        ])->assertRedirect();
    }

    /** @param  list<array{0: Material, 1: string}>  $lines */
    private function terbitkan(
        User $operator,
        Warehouse $origin,
        Warehouse $tujuan,
        array $lines,
        ?MaterialRequest $request = null,
        ?int $projectId = null,
    ): void {
        foreach ($lines as [$material, $qty]) {
            $this->terimaStok($operator, $origin, $material, $qty);
        }

        $this->actingAs($operator)->post('/warehouse/transfers', [
            'warehouse_asal_id' => $origin->id,
            'warehouse_tujuan_id' => $tujuan->id,
            'material_request_id' => $request?->id,
            'project_id' => $projectId,
            'tanggal' => '2026-08-22',
            'pengirim' => 'Petugas Gudang',
            'items' => array_map(
                fn (array $line): array => ['material_id' => $line[0]->id, 'qty' => $line[1], 'asal' => 'request'],
                $lines,
            ),
        ])->assertRedirect()->assertSessionHasNoErrors();
    }
}
