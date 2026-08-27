<?php

namespace Tests\Feature;

use App\Models\SuratJalan;
use App\Models\Warehouse;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Kontrak untuk #170: nama gudang asal dan gudang tujuan satu Surat Jalan, berikut kalimat
 * penggantinya bila relasinya tidak ter-resolve, dijawab oleh model -- bukan disusun ulang di
 * setiap Query yang kebetulan butuh.
 *
 * Seam-nya model, bukan Query, karena `SuratJalan` yang memiliki kedua relasi. Selama kedua
 * Query menjangkau ke dalamnya sekadar untuk sebuah label, teks yang sampai ke mata pengguna
 * hidup di dua tempat dan mengubahnya di satu sisi lolos review.
 *
 * Test ini sengaja tidak menyentuh database: `setRelation()` adalah antarmuka publik Eloquent,
 * dan yang diuji di sini adalah keputusan model atas relasi yang sudah ada -- bukan cara relasi
 * itu dimuat.
 */
class SuratJalanNamaAsalTujuanTest extends TestCase
{
    public function test_nama_asal_reads_the_resolved_warehouse_name(): void
    {
        $suratJalan = new SuratJalan;
        $suratJalan->setRelation('asal', new Warehouse(['nama' => 'Gudang Surabaya']));

        $this->assertSame('Gudang Surabaya', $suratJalan->namaAsal());
    }

    public function test_nama_asal_falls_back_when_the_warehouse_does_not_resolve(): void
    {
        $suratJalan = new SuratJalan;
        $suratJalan->setRelation('asal', null);

        $this->assertSame('Warehouse asal tidak tersedia', $suratJalan->namaAsal());
    }

    public function test_nama_tujuan_reads_the_resolved_warehouse_name(): void
    {
        $suratJalan = new SuratJalan;
        $suratJalan->setRelation('tujuan', new Warehouse(['nama' => 'Gudang Malang']));

        $this->assertSame('Gudang Malang', $suratJalan->namaTujuan());
    }

    public function test_nama_tujuan_falls_back_when_the_warehouse_does_not_resolve(): void
    {
        $suratJalan = new SuratJalan;
        $suratJalan->setRelation('tujuan', null);

        $this->assertSame('Warehouse tujuan tidak tersedia', $suratJalan->namaTujuan());
    }

    /**
     * Kalimat pengganti asal dan tujuan tidak boleh tertukar. Keduanya sekarang diketik
     * berdampingan di dua berkas, dan menukar keduanya adalah salah ketik yang lolos setiap
     * assertion yang hanya memeriksa "ada teksnya".
     */
    public function test_the_two_fallback_sentences_are_not_swapped(): void
    {
        $suratJalan = new SuratJalan;
        $suratJalan->setRelation('asal', null);
        $suratJalan->setRelation('tujuan', null);

        $this->assertNotSame($suratJalan->namaAsal(), $suratJalan->namaTujuan());
    }

    /**
     * Penjaga yang menagih sifatnya, bukan bentuk implementasinya: kalimat pengganti itu adalah
     * teks yang sampai ke mata pengguna, dan selama ia hidup di lebih dari satu berkas,
     * mengubahnya di satu sisi saja akan lolos review tanpa satu test pun menangkapnya.
     *
     * Yang dijaga jumlah tempatnya, bukan siapa pemanggilnya -- jadi penjaga ini tetap berlaku
     * kalau nanti Blade ikut memakai jalur yang sama, dan tidak ikut merah kalau accessor-nya
     * dinamai ulang.
     */
    public function test_each_fallback_sentence_is_typed_in_exactly_one_production_file(): void
    {
        foreach (['Warehouse asal tidak tersedia', 'Warehouse tujuan tidak tersedia'] as $kalimat) {
            $this->assertSame(
                ['app/Models/SuratJalan.php'],
                $this->produksiYangMenyebut($kalimat),
                'Kalimat pengganti "'.$kalimat.'" harus diketik satu kali saja, di model yang memiliki relasinya.',
            );
        }
    }

    /** @return list<string> */
    private function produksiYangMenyebut(string $kalimat): array
    {
        $akar = dirname(__DIR__, 2);
        $ditemukan = [];

        foreach (['app', 'resources'] as $direktori) {
            $berkas = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($akar.'/'.$direktori, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($berkas as $berkasSatuan) {
                if (! $berkasSatuan->isFile()) {
                    continue;
                }

                if (str_contains((string) file_get_contents($berkasSatuan->getPathname()), $kalimat)) {
                    $ditemukan[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($berkasSatuan->getPathname(), strlen($akar) + 1));
                }
            }
        }

        sort($ditemukan);

        return $ditemukan;
    }
}
