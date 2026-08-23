<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialSn;
use App\Models\Mitra;
use App\Models\SuratJalan;
use App\Models\SuratJalanItem;
use App\Models\Warehouse;
use App\Support\QtyTolerance;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\RefreshDatabase;
use Tests\Concerns\WarehouseFixtures;
use Tests\TestCase;

/**
 * Halaman Warehouse menyerialisasi seluruh data yang menyuapi form Terbitkan Surat Jalan
 * yang request-driven. Yang diuji di sini adalah kontraknya, bukan cara JS memakainya.
 */
class SuratJalanFormDataTest extends TestCase
{
    use RefreshDatabase;
    use WarehouseFixtures;

    public function test_gudang_tujuan_hanya_memuat_request_milik_mitra_pemiliknya(): void
    {
        $mitraA = Mitra::factory()->create();
        $mitraB = Mitra::factory()->create();
        $operator = $this->userWith(null, 'operate_warehouse');
        $origin = $this->warehouse(null, 'WH-THC-ASAL');
        $tujuanA = $this->warehouse($mitraA, 'WH-A');
        $tujuanB = $this->warehouse($mitraB, 'WH-B');
        $tujuanThc = $this->warehouse(null, 'WH-THC-TUJUAN');
        $origin->users()->attach($operator);

        $material = Material::factory()->create(['jenis' => 'biasa']);
        $disetujui = $this->materialRequest($mitraA, 'disetujui', [[$material, 10]]);
        $sebagian = $this->materialRequest($mitraA, 'terpenuhi_sebagian', [[$material, 4]]);
        $milikB = $this->materialRequest($mitraB, 'disetujui', [[$material, 3]]);
        foreach (['diajukan', 'ditolak', 'selesai', 'ditutup'] as $status) {
            $this->materialRequest($mitraA, $status, [[$material, 7]]);
        }

        $payload = $this->transferFormData($this->actingAs($operator)->get('/warehouse'));

        $this->assertSame(
            [$sebagian->id, $disetujui->id],
            array_column($payload['requests'][(string) $tujuanA->id], 'id'),
        );
        $this->assertSame([$milikB->id], array_column($payload['requests'][(string) $tujuanB->id], 'id'));
        $this->assertSame([], $payload['requests'][(string) $tujuanThc->id]);
    }

    public function test_item_request_terserialisasi_dengan_sisa_per_material(): void
    {
        $mitra = Mitra::factory()->create();
        $operator = $this->userWith(null, 'operate_warehouse');
        $origin = $this->warehouse(null, 'WH-ASAL');
        $tujuan = $this->warehouse($mitra, 'WH-TUJUAN');
        $origin->users()->attach($operator);
        $tujuan->users()->attach($operator);

        $material = Material::factory()->create(['jenis' => 'biasa']);
        $lain = Material::factory()->create(['jenis' => 'biasa']);
        $request = $this->materialRequest($mitra, 'disetujui', [[$material, 10], [$lain, 5]]);

        $this->actingAs($operator)->post('/warehouse/stock/receive', [
            'warehouse_id' => $origin->id,
            'material_id' => $material->id,
            'qty' => '10',
            'reason' => 'Penerimaan awal',
        ])->assertRedirect();
        $this->actingAs($operator)->post('/warehouse/transfers', [
            'warehouse_asal_id' => $origin->id,
            'warehouse_tujuan_id' => $tujuan->id,
            'material_request_id' => $request->id,
            'tanggal' => '2026-08-22',
            'pengirim' => 'Petugas Gudang',
            'items' => [['material_id' => $material->id, 'qty' => '4']],
        ])->assertRedirect();

        $payload = $this->transferFormData($this->actingAs($operator)->get('/warehouse'));
        $serialized = collect($payload['requests'][(string) $tujuan->id])->firstWhere('id', $request->id);
        $items = collect($serialized['items'])->keyBy('material_id');

        $this->assertSame($mitra->id, $serialized['mitra_id']);
        $this->assertSame([10.0, 4.0, 6.0], $this->quantities($items[$material->id]));
        $this->assertSame([5.0, 0.0, 5.0], $this->quantities($items[$lain->id]));
    }

    public function test_sisa_mengabaikan_surat_jalan_request_dengan_mitra_tidak_cocok(): void
    {
        $mitra = Mitra::factory()->create();
        $operator = $this->userWith(null, 'operate_warehouse');
        $origin = $this->warehouse(null, 'WH-ASAL');
        $tujuan = $this->warehouse($mitra, 'WH-TUJUAN');
        $origin->users()->attach($operator);
        $tujuan->users()->attach($operator);

        $material = Material::factory()->create(['jenis' => 'biasa']);
        $request = $this->materialRequest($mitra, 'disetujui', [[$material, 10]]);
        $issuer = $this->userWith(null, 'operate_warehouse');

        // `mitra_id` nullable pada Surat Jalan untuk arah THC ke THC. FK komposit tetap
        // mengizinkan row ini dengan Request Material Mitra, tetapi bukan pemenuhan request
        // yang sah dan harus diabaikan sama seperti klasifikator sisi server.
        $this->asThc(function () use ($origin, $tujuan, $request, $issuer, $material): void {
            $suratJalan = SuratJalan::query()->create([
                'nomor' => 'SJ-FORM-INVALID-TENANT-'.$request->id,
                'tanggal' => '2026-08-22',
                'warehouse_asal_id' => $origin->id,
                'warehouse_tujuan_id' => $tujuan->id,
                'mitra_id' => null,
                'material_request_id' => $request->id,
                'issued_by' => $issuer->id,
                'issued_at' => now(),
                'status' => 'terbit',
                'pengirim' => 'Petugas Gudang',
            ]);

            SuratJalanItem::query()->create([
                'surat_jalan_id' => $suratJalan->id,
                'mitra_id' => null,
                'material_id' => $material->id,
                'qty' => '4',
            ]);
        });

        $payload = $this->transferFormData($this->actingAs($operator)->get('/warehouse'));
        $serialized = collect($payload['requests'][(string) $tujuan->id])->firstWhere('id', $request->id);

        $this->assertSame([10.0, 0.0, 10.0], $this->quantities($serialized['items'][0]));
    }

    public function test_daftar_project_hanya_project_aktif_milik_mitra_surat_jalan(): void
    {
        $mitra = Mitra::factory()->create();
        $tanpaGudang = Mitra::factory()->create();
        $operator = $this->userWith(null, 'operate_warehouse');
        $origin = $this->warehouse(null, 'WH-ASAL');
        $this->warehouse($mitra, 'WH-TUJUAN');
        $origin->users()->attach($operator);

        $aktif = $this->project($mitra, 'PRJ-2608-0001', 'aktif');
        $this->project($mitra, 'PRJ-2608-0002', 'selesai');
        $this->project($tanpaGudang, 'PRJ-2608-0003', 'aktif');

        $payload = $this->transferFormData($this->actingAs($operator)->get('/warehouse'));

        $this->assertSame([$aktif->id], array_column($payload['projects'], 'id'));
        $this->assertSame('PRJ-2608-0001', $payload['projects'][0]['id_project']);
        $this->assertSame($mitra->id, $payload['projects'][0]['mitra_id']);
    }

    public function test_identitas_hanya_dari_gudang_yang_ditugaskan_kepada_user(): void
    {
        $mitraLain = Mitra::factory()->create();
        $operator = $this->userWith(null, 'operate_warehouse');
        $origin = $this->warehouse(null, 'WH-ASAL');
        $gudangMitraLain = $this->warehouse($mitraLain, 'WH-LAIN');
        $origin->users()->attach($operator);

        $berSn = Material::factory()->create(['jenis' => 'ber_sn']);
        $drumKabel = Material::factory()->create(['jenis' => 'drum_kabel']);
        $this->actingAs($operator)->post('/warehouse/stock/receive', [
            'warehouse_id' => $origin->id,
            'material_id' => $berSn->id,
            'qty' => '1',
            'serial_number' => 'SN-ASAL-1',
            'reason' => 'Penerimaan awal',
        ])->assertRedirect();
        $this->actingAs($operator)->post('/warehouse/stock/receive', [
            'warehouse_id' => $origin->id,
            'material_id' => $drumKabel->id,
            'qty' => '250',
            'drum_id' => 'DRM-ASAL-1',
            'reason' => 'Penerimaan awal',
        ])->assertRedirect();
        $this->asThc(fn (): MaterialSn => MaterialSn::query()->create([
            'material_id' => $berSn->id,
            'mitra_id' => $mitraLain->id,
            'serial_number' => 'SN-MITRA-LAIN',
            'lokasi_tipe' => 'warehouse',
            'lokasi_id' => $gudangMitraLain->id,
            'status' => 'tersedia',
        ]));

        $response = $this->actingAs($operator)->get('/warehouse');
        $payload = $this->transferFormData($response);

        $this->assertSame([$origin->id], array_keys($payload['identities']));
        $this->assertSame([
            ['type' => 'sn', 'value' => 'SN-ASAL-1', 'sisa' => null],
        ], $payload['identities'][$origin->id][$berSn->id]);
        $this->assertSame('drum', $payload['identities'][$origin->id][$drumKabel->id][0]['type']);
        $this->assertSame('DRM-ASAL-1', $payload['identities'][$origin->id][$drumKabel->id][0]['value']);
        $this->assertSame(250.0, (float) $payload['identities'][$origin->id][$drumKabel->id][0]['sisa']);
        $response->assertDontSee('SN-MITRA-LAIN');
    }

    public function test_form_surat_jalan_merender_pemilih_identitas_yang_terbatas_material_dan_gudang_asal(): void
    {
        $operator = $this->userWith(null, 'operate_warehouse');
        $origin = $this->warehouse(null, 'WH-ASAL', 'A Gudang Asal');
        $otherOrigin = $this->warehouse(null, 'WH-ASAL-LAIN', 'B Gudang Asal');
        $destination = $this->warehouse(null, 'WH-TUJUAN', 'C Gudang Tujuan');
        $origin->users()->attach($operator);
        $otherOrigin->users()->attach($operator);

        $serialised = Material::factory()->create(['jenis' => 'ber_sn']);
        $drumCable = Material::factory()->create(['jenis' => 'drum_kabel']);

        foreach ([[$origin, 'SN-ASAL-1'], [$otherOrigin, 'SN-ASAL-LAIN']] as [$warehouse, $serialNumber]) {
            $this->actingAs($operator)->post('/warehouse/stock/receive', [
                'warehouse_id' => $warehouse->id,
                'material_id' => $serialised->id,
                'qty' => '1',
                'serial_number' => $serialNumber,
                'reason' => 'Penerimaan awal',
            ])->assertRedirect();
        }
        $this->actingAs($operator)->post('/warehouse/stock/receive', [
            'warehouse_id' => $origin->id,
            'material_id' => $drumCable->id,
            'qty' => '250',
            'drum_id' => 'DRM-ASAL-1',
            'reason' => 'Penerimaan awal',
        ])->assertRedirect();

        $response = $this->actingAs($operator)
            ->withSession(['_old_input' => [
                'warehouse_asal_id' => (string) $origin->id,
                'warehouse_tujuan_id' => (string) $destination->id,
                'items' => [
                    ['material_id' => $serialised->id, 'qty' => '1', 'serial_number' => 'SN-ASAL-1'],
                    ['material_id' => $drumCable->id, 'qty' => '25', 'drum_id' => 'DRM-ASAL-1'],
                ],
            ]])
            ->get('/warehouse')
            ->assertOk();

        $response->assertSee('data-identity-select', false)
            ->assertSee('data-ui-select-native', false)
            ->assertDontSee('<input name="items[0][serial_number]" maxlength="255">', false)
            ->assertDontSee('<input name="items[1][drum_id]" maxlength="255">', false);

        $this->assertSame(['SN-ASAL-1'], $this->identityOptionValues($response, 'serial_number'));
        $this->assertSame(['DRM-ASAL-1'], $this->identityOptionValues($response, 'drum_id'));
    }

    public function test_warehouse_mitra_menyertakan_gudang_asal_dan_tujuan(): void
    {
        $mitra = Mitra::factory()->create();
        $operator = $this->userWith(null, 'operate_warehouse');
        $origin = $this->warehouse(null, 'WH-ASAL');
        $tujuan = $this->warehouse($mitra, 'WH-TUJUAN');
        $origin->users()->attach($operator);

        $payload = $this->transferFormData($this->actingAs($operator)->get('/warehouse'));

        $this->assertNull($payload['warehouse_mitra'][(string) $origin->id]);
        $this->assertSame($mitra->id, $payload['warehouse_mitra'][(string) $tujuan->id]);
    }

    /**
     * Mitra efektif awal punya satu sumber: `SuratJalanFormQuery`. Yang dijaga di sini adalah
     * sisi servernya — nilai di payload dan dropdown yang dirender di atasnya. Bahwa skrip halaman
     * membaca nilai itu alih-alih menghitungnya sendiri dijaga di sisi JS
     * (tests/JavaScript/warehouse-material-form.test.js), supaya aturannya tidak diketik ulang
     * di test hanya untuk membuktikan bahwa ia tidak diketik ulang di produksi.
     */
    public function test_mitra_efektif_awal_dilayani_payload_pada_arah_mitra_ke_thc(): void
    {
        $mitra = Mitra::factory()->create();
        $operator = $this->userWith(null, 'operate_warehouse');
        // Gudang asal terpilih adalah satu-satunya gudang yang ditugaskan; gudang tujuan terpilih
        // adalah yang pertama menurut nama di antara seluruh gudang aktif.
        $asal = $this->warehouse($mitra, 'WH-MITRA', 'Z Gudang Mitra');
        $tujuan = $this->warehouse(null, 'WH-THC', 'A Gudang THC');
        $asal->users()->attach($operator);
        $this->project($mitra, 'PRJ-2608-0001');
        // Form Terbitkan Surat Jalan hanya dirender bila ada Material aktif; tanpa itu yang diuji
        // di bawah bukan render awalnya melainkan pesan kosongnya.
        Material::factory()->create(['jenis' => 'biasa']);

        $response = $this->actingAs($operator)->get('/warehouse');
        $payload = $this->transferFormData($response);

        $this->assertSame($asal->id, $payload['initial_origin_id']);
        $this->assertSame($tujuan->id, $payload['initial_destination_id']);
        $this->assertSame($mitra->id, $payload['initial_mitra_id'], 'asal milik Mitra menentukan Mitra Surat Jalan');
        $this->assertRenderedSelection($response, $asal, $tujuan);
        $response->assertSee('PRJ-2608-0001 — Project PRJ-2608-0001');
    }

    public function test_mitra_efektif_awal_dilayani_payload_pada_arah_thc_ke_mitra(): void
    {
        $mitra = Mitra::factory()->create();
        $operator = $this->userWith(null, 'operate_warehouse');
        $asal = $this->warehouse(null, 'WH-THC', 'Z Gudang THC');
        $tujuan = $this->warehouse($mitra, 'WH-MITRA', 'A Gudang Mitra');
        $asal->users()->attach($operator);
        $this->project($mitra, 'PRJ-2608-0002');
        Material::factory()->create(['jenis' => 'biasa']);

        $response = $this->actingAs($operator)->get('/warehouse');
        $payload = $this->transferFormData($response);

        $this->assertSame($asal->id, $payload['initial_origin_id']);
        $this->assertSame($tujuan->id, $payload['initial_destination_id']);
        $this->assertSame($mitra->id, $payload['initial_mitra_id'], 'asal milik THC jatuh ke Mitra gudang tujuan');
        $this->assertRenderedSelection($response, $asal, $tujuan);
        $response->assertSee('PRJ-2608-0002 — Project PRJ-2608-0002');
    }

    public function test_gudang_awal_mengikuti_old_input_setelah_penerbitan_ditolak(): void
    {
        $mitra = Mitra::factory()->create();
        $operator = $this->userWith(null, 'operate_warehouse');
        $thc = $this->warehouse(null, 'WH-THC', 'A Gudang THC');
        $milikMitra = $this->warehouse($mitra, 'WH-MITRA', 'Z Gudang Mitra');
        $thc->users()->attach($operator);
        $milikMitra->users()->attach($operator);

        $payload = $this->transferFormData(
            $this->actingAs($operator)
                ->withSession(['_old_input' => [
                    'warehouse_asal_id' => (string) $milikMitra->id,
                    'warehouse_tujuan_id' => (string) $thc->id,
                ]])
                ->get('/warehouse'),
        );

        $this->assertSame($milikMitra->id, $payload['initial_origin_id']);
        $this->assertSame($thc->id, $payload['initial_destination_id']);
        $this->assertSame($mitra->id, $payload['initial_mitra_id']);
    }

    public function test_toleransi_klasifikasi_dibawa_payload_dari_konstanta_aplikasi(): void
    {
        $mitra = Mitra::factory()->create();
        $operator = $this->userWith(null, 'operate_warehouse');
        $origin = $this->warehouse(null, 'WH-ASAL');
        $this->warehouse($mitra, 'WH-TUJUAN');
        $origin->users()->attach($operator);

        $payload = $this->transferFormData($this->actingAs($operator)->get('/warehouse'));

        $this->assertSame(QtyTolerance::VALUE, $payload['qty_tolerance']);
    }

    public function test_user_mitra_tidak_melihat_form_penerbitan_maupun_datanya(): void
    {
        $mitra = Mitra::factory()->create();
        $operator = $this->userWith($mitra, 'operate_warehouse');
        $gudang = $this->warehouse($mitra, 'WH-MITRA');
        $gudang->users()->attach($operator);
        $this->materialRequest($mitra, 'disetujui', [[Material::factory()->create(['jenis' => 'biasa']), 10]]);

        $this->actingAs($operator)
            ->get('/warehouse')
            ->assertOk()
            ->assertSee('Penerimaan stok')
            ->assertSee('Pengeluaran stok')
            ->assertSee('Split drum')
            ->assertSee($gudang->kode)
            ->assertDontSee('Terbitkan Surat Jalan')
            // Yang tidak boleh bocor adalah payload-nya. Skrip form request-driven dirender di
            // setiap halaman dan menyebut selector ini apa adanya, jadi tag payload yang diperiksa.
            ->assertDontSee('<script type="application/json" data-transfer-form-data>', false);
    }

    private function assertRenderedSelection(TestResponse $response, Warehouse $asal, Warehouse $tujuan): void
    {
        $response->assertSee(sprintf('<option value="%d" selected>%s', $asal->id, $asal->kode), false);
        $response->assertSee(sprintf('<option value="%d" selected>%s', $tujuan->id, $tujuan->kode), false);
    }

    /** @return list<string> */
    private function identityOptionValues(TestResponse $response, string $identity): array
    {
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $options = $xpath->query(sprintf(
            '//label[@data-identity="%s"]//div[@data-identity-select]//button[@data-ui-select-option]',
            $identity,
        ));

        return collect($options === false ? [] : iterator_to_array($options))
            ->map(fn (\DOMElement $option): string => $option->getAttribute('data-value'))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{float, float, float}
     */
    private function quantities(array $item): array
    {
        return [(float) $item['diminta'], (float) $item['terkirim'], (float) $item['sisa']];
    }

    /** @return array<string, mixed> */
    private function transferFormData(TestResponse $response): array
    {
        $response->assertOk();
        $matched = preg_match(
            '/<script type="application\/json" data-transfer-form-data>(.*?)<\/script>/s',
            $response->getContent(),
            $matches,
        );
        $this->assertSame(1, $matched, 'Halaman Warehouse tidak menyerialisasi data form Surat Jalan.');

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }
}
