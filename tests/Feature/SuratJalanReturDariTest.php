<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Mitra;
use App\Models\Project;
use App\Models\ProjectTimeline;
use App\Models\SuratJalan;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Concerns\RefreshDatabase;
use Tests\Concerns\WarehouseFixtures;
use Tests\TestCase;

/**
 * Kontrak #167: konsep glosarium **Retur** -- "Surat Jalan baru arah sebaliknya yang menunjuk
 * Surat Jalan asal" -- dieja `retur` di lapisan mana pun, jadi relasi yang menunjuknya bernama
 * `returDari()`, bukan `returnedFrom()`. Aturannya dibekukan di ADR-0027.
 *
 * Kolomnya sudah patuh sejak awal (`retur_dari_id`), jadi tiket ini tidak menyentuh migrasi.
 * Yang tersisa adalah nama di kode -- dan nama relasi Eloquent punya dua pemakai yang gagal
 * dengan cara berbeda: pemanggilan properti (`->returDari`) dan **string** eager-load
 * (`with('returDari')`). String tidak diperiksa siapa pun sampai baris itu dijalankan, jadi
 * keduanya dijaga terpisah di sini.
 */
class SuratJalanReturDariTest extends TestCase
{
    use RefreshDatabase;
    use WarehouseFixtures;

    /**
     * Seam pertama: relasi sebagai antarmuka publik model.
     *
     * Kunci asing ikut ditagih karena rename relasi yang salah menunjuk kolom lain adalah
     * kegagalan yang paling mahal di tiket ini -- ia lolos setiap assertion yang hanya
     * memeriksa "metodenya ada".
     */
    public function test_relasi_retur_dari_membaca_kolom_retur_dari_id(): void
    {
        $relasi = (new SuratJalan)->returDari();

        $this->assertInstanceOf(BelongsTo::class, $relasi);
        $this->assertSame('retur_dari_id', $relasi->getForeignKeyName());
        $this->assertInstanceOf(SuratJalan::class, $relasi->getRelated());
    }

    /**
     * Seam kedua: halaman detail Surat Jalan yang dirender.
     *
     * `SuratJalanController::show()` memuat relasi ini lewat **string**. String yang basah
     * setelah rename tidak ditangkap PHPStan maupun IDE; ia baru meledak saat halaman dibuka.
     * Karena itu yang ditagih bukan sekadar `assertOk()`, melainkan relasi yang benar-benar
     * termuat dengan ejaan glosariumnya -- `assertOk()` sendiri akan tetap hijau seandainya
     * eager-load-nya dibuang diam-diam.
     */
    public function test_halaman_detail_retur_memuat_relasi_retur_dari(): void
    {
        [$asalId, $petugas, $thc] = $this->terbitkanDanTerimaSuratJalan();
        $returId = $this->returkan($asalId, $thc);

        $response = $this->actingAs($petugas)->get(route('warehouse.transfers.show', $returId))->assertOk();

        $suratJalan = $response->viewData('suratJalan');
        $this->assertTrue(
            $suratJalan->relationLoaded('returDari'),
            'Halaman detail harus memuat relasi retur dengan ejaan glosariumnya (`returDari`).',
        );
        $this->assertSame($asalId, $suratJalan->returDari?->id);
    }

    /**
     * Seam ketiga: kunci payload event linimasa.
     *
     * Kunci `metadata` tidak dibaca siapa pun -- linimasa merender labelnya dari `event_key` --
     * jadi ejaannya tidak akan pernah membuat test lain merah. Justru karena itu ia perlu
     * penjaga sendiri: ADR-0027 menyebut kunci payload secara eksplisit, dan tanpa assertion
     * ini ejaan Inggrisnya bisa kembali tanpa terasa.
     */
    public function test_event_linimasa_retur_mengeja_kunci_retur_dari_id(): void
    {
        [$asalId, , $thc, $project] = $this->terbitkanDanTerimaSuratJalan();
        $returId = $this->returkan($asalId, $thc);

        $event = ProjectTimeline::query()
            ->where('project_id', $project->id)
            ->where('event_key', 'surat_jalan_returned')
            ->sole();

        $this->assertSame($asalId, $event->metadata['retur_dari_id'] ?? null);
        $this->assertArrayNotHasKey('returned_from_id', $event->metadata);
        $this->assertSame($returId, $event->metadata['surat_jalan_id'] ?? null);
    }

    /** @return array{int, User, User, Project} */
    private function terbitkanDanTerimaSuratJalan(): array
    {
        $mitra = Mitra::factory()->create();
        $project = $this->project($mitra, 'PRJ-2608-0167');
        $asal = $this->warehouse($mitra, 'WH-RETUR-ASAL');
        $tujuan = $this->warehouse($mitra, 'WH-RETUR-TUJUAN');
        $material = Material::factory()->create(['jenis' => 'biasa']);
        $petugas = $this->userWith($mitra, 'operate_warehouse');
        $thc = $this->userWith(null, 'operate_warehouse');
        $asal->users()->attach([$petugas->id, $thc->id]);
        $tujuan->users()->attach([$petugas->id, $thc->id]);

        $this->actingAs($petugas)->post('/warehouse/stock/receive', [
            'warehouse_id' => $asal->id,
            'material_id' => $material->id,
            'qty' => '10',
            'reason' => 'Penerimaan awal',
        ])->assertRedirect();

        $this->actingAs($petugas)->post('/warehouse/transfers', [
            'warehouse_asal_id' => $asal->id,
            'warehouse_tujuan_id' => $tujuan->id,
            'tanggal' => '2026-08-15',
            'pengirim' => 'Petugas Gudang',
            'project_id' => $project->id,
            'items' => [['material_id' => $material->id, 'qty' => '4']],
        ])->assertRedirect();

        $suratJalanId = (int) SuratJalan::query()->value('id');
        $this->actingAs($petugas)->post(route('warehouse.transfers.receive', $suratJalanId))->assertRedirect();

        return [$suratJalanId, $petugas, $thc, $project];
    }

    private function returkan(int $asalId, User $thc): int
    {
        $this->actingAs($thc)->post(route('warehouse.transfers.return', $asalId), [
            'tanggal' => '2026-08-16',
            'pengirim' => 'Petugas THC',
        ])->assertRedirect();

        return (int) SuratJalan::query()->where('retur_dari_id', $asalId)->value('id');
    }
}
