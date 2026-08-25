<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialRequest;
use App\Models\Mitra;
use App\Models\SuratJalan;
use App\Models\SuratJalanItem;
use App\Services\SuratJalanService;
use App\Support\QtyTolerance;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\Concerns\RefreshDatabase;
use Tests\Concerns\WarehouseFixtures;
use Tests\TestCase;

/**
 * Sisi PHP dari kontrak lintas bahasa yang dijanjikan ADR-0026. Kasusnya tidak ditulis di sini
 * melainkan dibaca dari tests/fixtures/klasifikasi-penyimpangan.json, berkas yang sama yang
 * dibaca tests/JavaScript/warehouse-material-form.test.js atas `markDeviations()`. Klasifikator
 * klien memang kembar dengan yang di server dan itu disengaja; berkas itu yang membuat keduanya
 * tidak bisa menyimpang diam-diam.
 *
 * Yang dipanggil di sini adalah klasifikatornya langsung, bukan endpoint penerbitan: sebagian
 * kasus batas berada di bawah presisi kolom `qty` (3 desimal), jadi hanya terjangkau sebelum
 * baris tersimpan. Jalur HTTP-nya diuji terpisah di SuratJalanDeviationTest.
 */
class SuratJalanDeviationContractTest extends TestCase
{
    use RefreshDatabase;
    use WarehouseFixtures;

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function kasusKlasifikasi(): iterable
    {
        foreach (self::fixture()['kasus'] as $kasus) {
            yield $kasus['nama'] => [$kasus];
        }
    }

    /** @param array<string, mixed> $kasus */
    #[DataProvider('kasusKlasifikasi')]
    public function test_klasifikator_server_mengikuti_fixture_kontrak(array $kasus): void
    {
        $mitra = Mitra::factory()->create();
        $materials = $this->materialsFor($kasus);
        $request = $this->materialRequest($mitra, 'disetujui', array_map(
            fn (array $line): array => [$materials[$line['material_id']], $line['diminta']],
            $kasus['request'],
        ));
        $this->recordSentQuantities($mitra, $request, $kasus, $materials);

        $deviations = $this->classify($request, array_map(fn (array $baris): array => [
            'material_id' => $materials[$baris['material_id']]->id,
            'qty' => $this->qty($kasus, $baris),
        ], $kasus['baris']));

        $this->assertSame($this->expected($kasus, $materials), $deviations);
    }

    public function test_fixture_kontrak_menyebut_setiap_jenis_penyimpangan(): void
    {
        $jenis = [];
        foreach (self::fixture()['kasus'] as $kasus) {
            $jenis = array_merge($jenis, array_values($kasus['klasifikasi']));
        }

        foreach ([null, 'material_asing', 'qty_melebihi'] as $harus) {
            $this->assertContains($harus, $jenis, 'fixture kontrak harus memuat kasus '.($harus ?? 'patuh'));
        }
    }

    public function test_ambang_klasifikasi_tidak_diketik_ulang_di_klasifikator(): void
    {
        $source = (string) file_get_contents(base_path('app/Services/SuratJalanService.php'));
        $start = (int) strpos($source, 'private function classifyRequestDeviations');
        $body = substr($source, $start, (int) strpos($source, "\n    }", $start) - $start);

        $this->assertStringContainsString('QtyTolerance::VALUE', $body);
        $this->assertStringNotContainsString(
            (string) QtyTolerance::VALUE,
            $body,
            'klasifikator harus memakai konstantanya, bukan mengetik ulang angkanya',
        );
    }

    /** @return array<string, mixed> */
    private static function fixture(): array
    {
        return json_decode(
            (string) file_get_contents(dirname(__DIR__).'/fixtures/klasifikasi-penyimpangan.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @param  array<string, mixed>  $kasus
     * @return array<int, Material>
     */
    private function materialsFor(array $kasus): array
    {
        $ids = array_values(array_unique(array_merge(
            array_column($kasus['request'], 'material_id'),
            array_column($kasus['baris'], 'material_id'),
        )));
        sort($ids);

        return array_combine($ids, array_map(
            fn (): Material => Material::factory()->create(['jenis' => 'biasa']),
            $ids,
        ));
    }

    /**
     * Fixture menyatakan "sudah terkirim" sebagai angka; di server angka itu lahir dari Surat Jalan
     * sebelumnya, jadi baris nyatanya yang dibuat — kalau tidak, basis sisa yang diuji bukan basis
     * yang dipakai produksi.
     *
     * @param  array<string, mixed>  $kasus
     * @param  array<int, Material>  $materials
     */
    private function recordSentQuantities(Mitra $mitra, MaterialRequest $request, array $kasus, array $materials): void
    {
        $terkirim = array_filter($kasus['request'], fn (array $line): bool => (float) $line['terkirim'] > 0);
        if ($terkirim === []) {
            return;
        }

        $asal = $this->warehouse(null, 'WH-ASAL');
        $tujuan = $this->warehouse($mitra, 'WH-TUJUAN');
        $issuer = $this->userWith(null, 'operate_warehouse');

        $this->asThc(function () use ($mitra, $request, $terkirim, $materials, $asal, $tujuan, $issuer): void {
            $suratJalan = SuratJalan::query()->create([
                'nomor' => 'SJ-KONTRAK-'.$request->id,
                'tanggal' => '2026-08-20',
                'warehouse_asal_id' => $asal->id,
                'warehouse_tujuan_id' => $tujuan->id,
                'mitra_id' => $mitra->id,
                'material_request_id' => $request->id,
                'issued_by' => $issuer->id,
                'issued_at' => now(),
                'status' => 'terbit',
                'pengirim' => 'Petugas Gudang',
            ]);
            foreach ($terkirim as $line) {
                SuratJalanItem::query()->create([
                    'surat_jalan_id' => $suratJalan->id,
                    'mitra_id' => $mitra->id,
                    'material_id' => $materials[$line['material_id']]->id,
                    'qty' => $line['terkirim'],
                ]);
            }
        });
    }

    /**
     * Kasus batas menyebut toleransi sebagai faktor, bukan angka: sisinya masing-masing yang
     * mengalikannya dengan ambang aplikasinya sendiri.
     *
     * @param  array<string, mixed>  $kasus
     * @param  array<string, mixed>  $baris
     */
    private function qty(array $kasus, array $baris): float
    {
        if (array_key_exists('qty', $baris)) {
            return (float) $baris['qty'];
        }

        return $this->sisa($kasus, $baris['material_id'])
            + (float) $baris['qty_sisa_plus_toleransi'] * QtyTolerance::VALUE;
    }

    /** @param array<string, mixed> $kasus */
    private function sisa(array $kasus, int $materialId): float
    {
        foreach ($kasus['request'] as $line) {
            if ($line['material_id'] === $materialId) {
                return (float) $line['diminta'] - (float) $line['terkirim'];
            }
        }

        throw new LogicException('Kasus batas toleransi hanya berlaku pada material yang ada di request.');
    }

    /**
     * @param  array<string, mixed>  $kasus
     * @param  array<int, Material>  $materials
     * @return array<int, string>
     */
    private function expected(array $kasus, array $materials): array
    {
        $expected = [];
        foreach ($kasus['klasifikasi'] as $materialId => $jenis) {
            if ($jenis !== null) {
                $expected[(int) $materials[(int) $materialId]->id] = $jenis;
            }
        }
        ksort($expected);

        return $expected;
    }

    /**
     * @param  list<array{material_id:int,qty:float}>  $items
     * @return array<int, string>
     */
    private function classify(MaterialRequest $request, array $items): array
    {
        return $this->asThc(function () use ($request, $items): array {
            $fresh = MaterialRequest::query()->with('items')->findOrFail($request->id);
            $deviations = (new ReflectionMethod(SuratJalanService::class, 'classifyRequestDeviations'))
                ->invoke(app(SuratJalanService::class), $fresh, $items);
            ksort($deviations);

            return $deviations;
        });
    }
}
