<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Material;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\MaterialSn;
use App\Models\Mitra;
use App\Models\Project;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\TenantDatabaseContext;
use Closure;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman Warehouse menyerialisasi seluruh data yang menyuapi form Terbitkan Surat Jalan
 * yang request-driven. Yang diuji di sini adalah kontraknya, bukan cara JS memakainya.
 */
class SuratJalanFormDataTest extends TestCase
{
    use RefreshDatabase;

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
        $disetujui = $this->request($mitraA, 'disetujui', $material, 10);
        $sebagian = $this->request($mitraA, 'terpenuhi_sebagian', $material, 4);
        $milikB = $this->request($mitraB, 'disetujui', $material, 3);
        foreach (['diajukan', 'ditolak', 'selesai', 'ditutup'] as $status) {
            $this->request($mitraA, $status, $material, 7);
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
        $request = $this->request($mitra, 'disetujui', $material, 10, $lain, 5);

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

    public function test_user_mitra_tidak_melihat_form_penerbitan_maupun_datanya(): void
    {
        $mitra = Mitra::factory()->create();
        $operator = $this->userWith($mitra, 'operate_warehouse');
        $gudang = $this->warehouse($mitra, 'WH-MITRA');
        $gudang->users()->attach($operator);
        $this->request($mitra, 'disetujui', Material::factory()->create(['jenis' => 'biasa']), 10);

        $this->actingAs($operator)
            ->get('/warehouse')
            ->assertOk()
            ->assertSee('Penerimaan stok')
            ->assertSee('Pengeluaran stok')
            ->assertSee('Split drum')
            ->assertSee($gudang->kode)
            ->assertDontSee('Terbitkan Surat Jalan')
            ->assertDontSee('data-transfer-form-data', false);
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

    private function request(
        Mitra $mitra,
        string $status,
        Material $material,
        float $qty,
        ?Material $second = null,
        ?float $secondQty = null,
    ): MaterialRequest {
        $requester = $this->userWith($mitra, 'create_material_request');

        return $this->asThc(function () use ($mitra, $status, $material, $qty, $second, $secondQty, $requester): MaterialRequest {
            $request = MaterialRequest::query()->create([
                'mitra_id' => $mitra->id,
                'requested_by' => $requester->id,
                'status' => $status,
            ]);
            $lines = [[$material, $qty]];
            if ($second !== null) {
                $lines[] = [$second, $secondQty];
            }
            foreach ($lines as [$lineMaterial, $lineQty]) {
                MaterialRequestItem::query()->create([
                    'material_request_id' => $request->id,
                    'mitra_id' => $mitra->id,
                    'material_id' => $lineMaterial->id,
                    'qty' => $lineQty,
                ]);
            }

            return $request;
        });
    }

    private function project(Mitra $mitra, string $idProject, string $status): Project
    {
        return $this->asThc(fn (): Project => Project::query()->create([
            'id_project' => $idProject,
            'nama' => 'Project '.$idProject,
            'mitra_id' => $mitra->id,
            'status_project' => $status,
        ]));
    }

    private function warehouse(?Mitra $mitra, string $kode): Warehouse
    {
        return $this->asThc(fn (): Warehouse => Warehouse::factory()->create([
            'mitra_id' => $mitra?->id,
            'kode' => $kode,
            'aktif' => true,
        ]));
    }

    private function userWith(?Mitra $mitra, string ...$permissions): User
    {
        $group = Grup::factory()->create();
        $group->izins()->attach(collect($permissions)->map(
            fn (string $permission) => Izin::query()->firstOrCreate(['kode' => $permission], ['nama' => $permission])->id,
        )->all());

        return User::factory()->create(['mitra_id' => $mitra?->id, 'grup_id' => $group->id]);
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
