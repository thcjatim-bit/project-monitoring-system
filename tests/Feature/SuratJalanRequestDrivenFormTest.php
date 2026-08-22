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
        // Gudang tujuan terpilih saat muat pertama adalah yang pertama menurut nama di antara
        // seluruh gudang aktif -- gudang asal sendiri ikut jadi kandidat tujuan, jadi namanya
        // sengaja diurutkan paling belakang supaya tujuan terpilih adalah gudang mitra.
        $origin = $this->warehouse(null, 'WH-ASAL', 'Z Gudang THC');
        $tujuan = $this->warehouse($mitra, 'WH-TUJUAN', 'A Gudang Mitra');
        $this->warehouse($mitraLain, 'WH-LAIN', 'B Gudang Mitra Lain');
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
        // Tanpa Material ber-Unit aktif, panel Terbitkan Surat Jalan tidak merender form sama sekali.
        Material::factory()->create(['jenis' => 'biasa']);

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
        Material::factory()->create(['jenis' => 'biasa']);

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

    /**
     * Baris pertama form dinonaktifkan begitu prefill mengambil alih, jadi browser tidak mengirim
     * `items[0]` sama sekali dan penomoran item mulai dari 1. Bentuk payload itu harus diterima
     * apa adanya, bukan hanya bentuk yang indeksnya rapat dari nol.
     */
    public function test_payload_prefill_tanpa_item_indeks_nol_diterima(): void
    {
        $mitra = Mitra::factory()->create();
        $operator = $this->userWith(null, 'operate_warehouse');
        $origin = $this->warehouse(null, 'WH-ASAL', 'Z Gudang THC');
        $tujuan = $this->warehouse($mitra, 'WH-TUJUAN', 'A Gudang Mitra');
        $origin->users()->attach($operator);
        $material = Material::factory()->create(['jenis' => 'biasa']);
        $request = $this->materialRequest($mitra, 'disetujui', [[$material, 10]]);
        $this->terimaStok($operator, $origin, $material, '10');

        $this->actingAs($operator)->post('/warehouse/transfers', [
            'warehouse_asal_id' => $origin->id,
            'warehouse_tujuan_id' => $tujuan->id,
            'material_request_id' => $request->id,
            'tanggal' => '2026-08-22',
            'pengirim' => 'Petugas Gudang',
            'items' => [1 => ['material_id' => $material->id, 'qty' => '10', 'asal' => 'request']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $suratJalan = SuratJalan::query()->latest('id')->firstOrFail();
        $this->assertSame($request->id, $suratJalan->material_request_id);
        $this->assertCount(1, $suratJalan->items()->get());
    }

    /**
     * Asal-usul baris tidak punya konsumen di server: klasifikasi dihitung ulang dari data
     * request, bukan dari hidden input. Field tanpa konsumen tidak boleh menolak submit yang
     * isinya sah, jadi nilai apa pun -- termasuk yang tidak dikenal atau kosong -- lewat, dan
     * sisa payload tetap tersimpan utuh.
     */
    public function test_asal_usul_baris_apa_pun_diterima_dan_payload_tersimpan_utuh(): void
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
            'items' => [
                ['material_id' => $material->id, 'qty' => '4', 'asal' => 'entah'],
                ['material_id' => $material->id, 'qty' => '3', 'asal' => ''],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $suratJalan = SuratJalan::query()->latest('id')->firstOrFail();
        $items = $suratJalan->items()->get();
        $this->assertCount(2, $items);
        $this->assertEqualsCanonicalizing(['4', '3'], $items->map(fn ($item): string => (string) (int) $item->qty)->all());
    }

    /**
     * Catatan baris menyimpang ditegakkan server, dan baris kedua dan seterusnya hanya ada di
     * klien. Tanpa render ulang dari `old()`, satu penolakan berarti mengetik ulang seluruh
     * Surat Jalan -- dan baris yang ditolak kembali tanpa penandaan maupun panduan catatannya.
     */
    public function test_penolakan_mengembalikan_setiap_baris_yang_sudah_diketik(): void
    {
        $mitra = Mitra::factory()->create();
        $operator = $this->userWith(null, 'operate_warehouse');
        $origin = $this->warehouse(null, 'WH-ASAL', 'Z Gudang THC');
        $tujuan = $this->warehouse($mitra, 'WH-TUJUAN', 'A Gudang Mitra');
        $origin->users()->attach($operator);
        $diminta = Material::factory()->create(['jenis' => 'biasa']);
        $asing = Material::factory()->create(['jenis' => 'biasa']);
        $project = $this->project($mitra, 'PRJ-2608-0001');
        $request = $this->materialRequest($mitra, 'disetujui', [[$diminta, 4]], $project);
        $this->terimaStok($operator, $origin, $diminta, '4');
        $this->terimaStok($operator, $origin, $asing, '5');

        $this->actingAs($operator)->from('/warehouse')->post('/warehouse/transfers', [
            'warehouse_asal_id' => $origin->id,
            'warehouse_tujuan_id' => $tujuan->id,
            'material_request_id' => $request->id,
            'project_id' => $project->id,
            'tanggal' => '2026-08-22',
            'pengirim' => 'Petugas Gudang',
            'items' => [
                ['material_id' => $diminta->id, 'qty' => '4', 'asal' => 'request', 'catatan' => 'Sesuai permintaan'],
                ['material_id' => $asing->id, 'qty' => '5', 'asal' => 'manual', 'catatan' => ''],
            ],
        ])
            ->assertRedirect('/warehouse')
            ->assertSessionHasErrors('items');

        $html = $this->actingAs($operator)->get('/warehouse')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/name="items\[0\]\[catatan\]"[^>]*value="Sesuai permintaan"/',
            $html,
            'catatan yang sudah diketik harus kembali',
        );
        $this->assertMatchesRegularExpression(
            '/name="items\[1\]\[catatan\]"/',
            $html,
            'baris kedua hanya ada di klien, jadi tanpa render ulang catatannya tidak punya tempat',
        );
        $this->assertMatchesRegularExpression(
            '/name="items\[1\]\[qty\]"[^>]*value="5"/',
            $html,
            'catatan yang kembali tanpa barisnya tidak berarti apa-apa',
        );
        // Baris prefill kembali dalam bentuk prefill, bukan sebagai baris ketikan biasa.
        $this->assertMatchesRegularExpression(
            '/<select name="items\[0\]\[material_id\]"[^>]*\bdisabled\b/',
            $html,
            'material baris prefill tidak boleh jadi bisa diubah hanya karena POST ditolak',
        );
        $this->assertStringContainsString(
            '<input type="hidden" name="items[0][material_id]" value="'.$diminta->id.'">',
            $html,
            'select yang disabled tidak terkirim, jadi materialnya dibawa hidden input',
        );
        $this->assertStringContainsString(
            '<input type="hidden" name="project_id" value="'.$project->id.'" data-project-lock>',
            $html,
            'request ber-Project mengunci Projectnya; kuncinya ikut dipulihkan',
        );
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
