<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Penjaga regresi untuk ADR-0027 di jalur Surat Jalan: konsep glosarium **Gudang asal** dan
 * **Gudang tujuan** dieja `asal`/`tujuan` di lapisan mana pun, jadi padanan Inggrisnya tidak
 * boleh muncul lagi pada berkas yang sudah diseragamkan (#165).
 *
 * Penjaganya sengaja **berdaftar berkas**, bukan repo-wide ber-allowlist: kata `origin` memikul
 * tiga arti yang tidak berhubungan di repo ini, dan merawat allowlist untuk sinkronisasi foto
 * (`rclone`) serta `getRawOriginal()` lebih mahal daripada masalah yang dicegah. Harganya
 * tercatat di ADR-0027: berkas jalur Surat Jalan yang baru harus didaftarkan di sini secara
 * manual, dan kelalaian itu senyap.
 *
 * Berkas test ikut terdaftar. Alasannya sama dengan alasan variabel lokal ikut diseragamkan --
 * grep tidak bisa membedakan variabel lokal dari nama publik, dan `$origin` yang tertinggal di
 * test adalah yang paling mungkin disalin saat test Surat Jalan berikutnya ditulis.
 */
class GlosariumAsalTujuanGuardTest extends TestCase
{
    /**
     * Cakupan #165 AC 6. Berkas ini sendiri tidak terdaftar: ia wajib menyebut ejaan yang
     * dilarangnya.
     *
     * @var list<string>
     */
    private const BERKAS_JALUR_SURAT_JALAN = [
        'app/Http/Controllers/MaterialInventoryController.php',
        'app/Http/Controllers/SuratJalanController.php',
        'app/Models/SuratJalan.php',
        'app/Queries/CommandCenterQuery.php',
        'app/Queries/MitraDashboardQuery.php',
        'app/Queries/PortfolioDecisionQueueQuery.php',
        'app/Queries/SuratJalanFormQuery.php',
        'app/Services/SuratJalanService.php',
        'resources/js/warehouse-material-form.js',
        'resources/views/dashboard.blade.php',
        'resources/views/mitra/dashboard.blade.php',
        'resources/views/warehouse/index.blade.php',
        'resources/views/warehouse/surat-jalan-print.blade.php',
        'resources/views/warehouse/transfer-show.blade.php',
        'resources/views/warehouse/transfers.blade.php',
        'resources/views/warehouse/transit.blade.php',
        'tests/Feature/CommandCenterTest.php',
        'tests/Feature/MaterialOperationalUiTest.php',
        'tests/Feature/MaterialQtyValidationTest.php',
        'tests/Feature/MaterialRequestFulfillmentTest.php',
        'tests/Feature/PortfolioCockpitTest.php',
        'tests/Feature/ProjectMaterialReadinessTest.php',
        'tests/Feature/SuratJalanDetailTest.php',
        'tests/Feature/SuratJalanDeviationContractTest.php',
        'tests/Feature/SuratJalanDeviationTest.php',
        'tests/Feature/SuratJalanFormDataTest.php',
        'tests/Feature/SuratJalanPrintTest.php',
        'tests/Feature/SuratJalanRequestDrivenFormTest.php',
        'tests/Feature/SuratJalanTransferTest.php',
        'tests/JavaScript/warehouse-material-form.test.js',
    ];

    /**
     * `origin` tanpa diikuti `al`, supaya `original_baseline`, `originalId`, dan
     * `getRawOriginal()` tidak ikut terjaring -- ketiganya menunjuk konsep di luar glosarium
     * `asal`, dan sebagian memang hidup di berkas yang terdaftar di atas.
     *
     * Lookahead ini punya satu korban yang diterima sadar: `$original` di
     * `SuratJalanService::createReturn()` justru **menunjuk** konsep glosarium (Surat Jalan asal
     * yang diretur), tapi lolos karena grep tidak bisa memisahkannya dari ketiga nama di atas.
     * Ia ditagih di #167, bukan di sini; lihat ADR-0027.
     */
    private const EJAAN_INGGRIS = '/origin(?!al)|destination/i';

    private const CONTOH_DILAPORKAN = 20;

    public function test_daftar_berkas_penjaga_menunjuk_berkas_yang_masih_ada(): void
    {
        $hilang = array_values(array_filter(
            self::BERKAS_JALUR_SURAT_JALAN,
            fn (string $berkas): bool => ! is_file(base_path($berkas)),
        ));

        $this->assertSame([], $hilang, implode("\n", [
            'Berkas berikut terdaftar di penjaga glosarium tapi tidak ada lagi.',
            'Perbarui daftarnya bersama commit yang memindahkannya, bukan sesudahnya:',
            ...$hilang,
        ]));
    }

    public function test_jalur_surat_jalan_tidak_mengeja_gudang_asal_dan_tujuan_dalam_bahasa_inggris(): void
    {
        $temuan = [];

        foreach (self::BERKAS_JALUR_SURAT_JALAN as $berkas) {
            foreach (file(base_path($berkas), FILE_IGNORE_NEW_LINES) as $index => $baris) {
                if (preg_match(self::EJAAN_INGGRIS, $baris) === 1) {
                    $temuan[] = sprintf('%s:%d: %s', $berkas, $index + 1, trim($baris));
                }
            }
        }

        // Dilaporkan sebagai jumlah, bukan sebagai diff dua array: sebelum rename #165 dikerjakan
        // temuannya ratusan baris, dan menumpahkan semuanya ke output test membakar konteks sesi
        // yang justru sedang mengerjakan rename itu. Contoh yang dicetak dibatasi.
        $this->assertSame(0, count($temuan), implode("\n", [
            'Konsep glosarium Gudang asal dan Gudang tujuan dieja `asal`/`tujuan` di lapisan mana',
            'pun -- relasi Eloquent, kunci payload, atribut `data-*`, variabel lokal. Lihat',
            'ADR-0027 dan #165.',
            sprintf(
                '%d kemunculan memakai ejaan Inggrisnya, %d pertama:',
                count($temuan),
                min(count($temuan), self::CONTOH_DILAPORKAN),
            ),
            ...array_slice($temuan, 0, self::CONTOH_DILAPORKAN),
        ]));
    }
}
