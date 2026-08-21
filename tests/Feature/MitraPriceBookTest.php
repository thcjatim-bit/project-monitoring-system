<?php

namespace Tests\Feature;

use App\Models\Mitra;
use App\Models\MitraHargaJasa;
use App\Models\Grup;
use App\Models\Izin;
use App\Models\PekerjaanJasa;
use App\Models\Pks;
use App\Models\Project;
use App\Models\User;
use App\Services\MitraPriceBook;
use App\Support\TenantDatabaseContext;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class MitraPriceBookTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_book_rechecks_manage_prices_permission_at_its_public_seam(): void
    {
        $actor = User::factory()->create(['mitra_id' => Mitra::factory()->create()->id]);

        $this->expectException(HttpException::class);

        app(MitraPriceBook::class)->priceBookFor($actor);
    }

    public function test_price_book_resolves_an_approved_price_for_the_requested_project_date(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->asThc(fn (): Project => Project::create([
            'id_project' => 'PRJ-2608-9501',
            'nama' => 'Project Harga Jasa',
            'mitra_id' => $mitra->id,
        ]));
        $job = $this->asThc(fn (): PekerjaanJasa => PekerjaanJasa::create([
            'kode' => 'JASA-PRICE-BOOK',
            'nama' => 'Penarikan Kabel',
            'aktif' => true,
        ]));
        $pks = $this->asThc(fn (): Pks => Pks::create([
            'mitra_id' => $mitra->id,
            'nomor' => 'PKS-PRICE-BOOK',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_berakhir' => '2026-12-31',
        ]));
        $price = $this->asThc(fn (): MitraHargaJasa => MitraHargaJasa::create([
            'mitra_id' => $mitra->id,
            'pks_id' => $pks->id,
            'pekerjaan_jasa_id' => $job->id,
            'harga' => '150000.00',
            'status' => 'disetujui',
            'berlaku_mulai' => '2026-09-01',
        ]));

        $snapshot = $this->asThc(fn (): MitraHargaJasa => app(MitraPriceBook::class)->effectiveFor(
            $project,
            $price->id,
            CarbonImmutable::parse('2026-09-15'),
        ));

        $this->assertSame($price->id, $snapshot->id);
        $this->assertSame('150000.00', $snapshot->harga);
    }

    public function test_revision_must_reference_an_approved_predecessor(): void
    {
        $mitra = Mitra::factory()->create();
        $actor = User::factory()->create([
            'mitra_id' => $mitra->id,
            'grup_id' => $this->groupWith('manage_mitra_prices')->id,
        ]);
        $job = $this->asThc(fn (): PekerjaanJasa => PekerjaanJasa::create([
            'kode' => 'JASA-REVISION-STATUS',
            'nama' => 'Pekerjaan Revisi',
            'aktif' => true,
        ]));
        $pks = $this->asThc(fn (): Pks => Pks::create([
            'mitra_id' => $mitra->id,
            'nomor' => 'PKS-REVISION-STATUS',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_berakhir' => '2026-12-31',
        ]));
        $pending = $this->asThc(fn (): MitraHargaJasa => MitraHargaJasa::create([
            'mitra_id' => $mitra->id,
            'pks_id' => $pks->id,
            'pekerjaan_jasa_id' => $job->id,
            'harga' => '100000.00',
            'status' => 'diajukan',
            'berlaku_mulai' => '2026-01-01',
        ]));

        $this->expectException(ValidationException::class);
        app(TenantDatabaseContext::class)->set($mitra->id, false);

        try {
            app(MitraPriceBook::class)->submit($actor, [
                'pks_id' => $pks->id,
                'pekerjaan_jasa_id' => $job->id,
                'harga' => '110000.00',
                'berlaku_mulai' => '2026-02-01',
                'revisi_dari_id' => $pending->id,
            ]);
        } finally {
            app(TenantDatabaseContext::class)->set(null, false);
        }
    }

    private function asThc(\Closure $callback): mixed
    {
        app(TenantDatabaseContext::class)->set(null, true);

        try {
            return $callback();
        } finally {
            app(TenantDatabaseContext::class)->set(null, false);
        }
    }

    private function groupWith(string ...$permissions): Grup
    {
        $group = Grup::factory()->create();
        foreach ($permissions as $permission) {
            $group->izins()->attach(Izin::factory()->create(['kode' => $permission]));
        }

        return $group;
    }
}
