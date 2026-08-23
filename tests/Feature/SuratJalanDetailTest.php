<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialRequest;
use App\Models\Mitra;
use App\Models\Project;
use App\Models\SuratJalan;
use App\Models\SuratJalanItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\View\View as ViewInstance;
use Tests\Concerns\RefreshDatabase;
use Tests\Concerns\WarehouseFixtures;
use Tests\TestCase;

class SuratJalanDetailTest extends TestCase
{
    use RefreshDatabase;
    use WarehouseFixtures;

    public function test_detail_shows_provenance_notes_and_stored_deviation_badges(): void
    {
        $mitra = Mitra::factory()->create();
        $user = $this->userWith($mitra, 'operate_warehouse');
        $origin = $this->warehouse($mitra, 'WH-DETAIL-ASAL');
        $destination = $this->warehouse($mitra, 'WH-DETAIL-TUJUAN');
        $origin->users()->attach($user);
        $destination->users()->attach($user);

        $project = $this->asThc(fn (): Project => Project::query()->create([
            'id_project' => 'PRJ-2608-0120',
            'nama' => 'Fiberisasi Detail',
            'mitra_id' => $mitra->id,
            'status_project' => 'aktif',
        ]));
        $requested = Material::factory()->create(['kode' => 'MAT-DIMINTA', 'nama' => 'Material diminta']);
        $foreign = Material::factory()->create(['kode' => 'MAT-ASING', 'nama' => 'Material asing']);
        $excess = Material::factory()->create(['kode' => 'MAT-LEBIH', 'nama' => 'Material berlebih']);
        $request = $this->materialRequest($mitra, 'disetujui', [[$requested, 4]], $project);
        $suratJalan = $this->createTransfer($mitra, $user, $origin, $destination, $request, $project, [
            [
                'material' => $requested,
                'qty' => 4,
                'catatan' => 'Catatan baris patuh',
            ],
            [
                'material' => $foreign,
                'qty' => 1,
                'catatan' => 'Catatan material asing',
                'jenis_penyimpangan' => 'material_asing',
            ],
            [
                'material' => $excess,
                'qty' => 8,
                'catatan' => 'Catatan qty berlebih',
                'jenis_penyimpangan' => 'qty_melebihi',
            ],
        ]);

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });
        $provenanceWasEagerLoaded = false;
        View::composer('warehouse.transfer-show', function (ViewInstance $view) use (&$provenanceWasEagerLoaded): void {
            $transfer = $view->getData()['suratJalan'];
            $provenanceWasEagerLoaded = $transfer->relationLoaded('materialRequest')
                && $transfer->relationLoaded('project');
        });

        $response = $this->actingAs($user)
            ->get(route('warehouse.transfers.show', $suratJalan))
            ->assertOk()
            ->assertSee('Status dokumen')
            ->assertSee('Project')
            ->assertSee($project->id_project)
            ->assertSee($project->nama)
            ->assertSee('Request Material #'.$request->id)
            ->assertSee('href="'.route('material-requests.show', $request).'"', false)
            ->assertSee('Catatan baris patuh')
            ->assertSee('Catatan material asing')
            ->assertSee('Catatan qty berlebih');

        $this->assertSame(1, substr_count($response->getContent(), 'Material di luar request'));
        $this->assertSame(1, substr_count($response->getContent(), 'Qty melebihi sisa'));
        $this->assertTrue($provenanceWasEagerLoaded);
        $this->assertSame(1, count(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'material_requests'))));
        $this->assertSame(1, count(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'projects'))));
    }

    public function test_detail_omits_empty_provenance_rows_and_badges_for_a_compliant_line(): void
    {
        $mitra = Mitra::factory()->create();
        $user = $this->userWith($mitra, 'operate_warehouse');
        $origin = $this->warehouse($mitra, 'WH-EMPTY-ASAL');
        $destination = $this->warehouse($mitra, 'WH-EMPTY-TUJUAN');
        $origin->users()->attach($user);
        $destination->users()->attach($user);
        $material = Material::factory()->create(['kode' => 'MAT-COMPLIANT', 'nama' => 'Material patuh']);
        $suratJalan = $this->createTransfer($mitra, $user, $origin, $destination, null, null, [[
            'material' => $material,
            'qty' => 2,
        ]]);

        $this->actingAs($user)
            ->get(route('warehouse.transfers.show', $suratJalan))
            ->assertOk()
            ->assertSee('Material patuh')
            ->assertDontSee('Project:')
            ->assertDontSee('Request Material')
            ->assertDontSee('Material di luar request')
            ->assertDontSee('Qty melebihi sisa');
    }

    public function test_detail_keeps_the_first_badge_frozen_after_a_later_surat_jalan_is_issued(): void
    {
        $mitra = Mitra::factory()->create();
        $user = $this->userWith($mitra, 'operate_warehouse');
        $origin = $this->warehouse($mitra, 'WH-FROZEN-ASAL');
        $destination = $this->warehouse($mitra, 'WH-FROZEN-TUJUAN');
        $origin->users()->attach($user);
        $destination->users()->attach($user);
        $project = $this->project($mitra, 'PRJ-2608-0121');
        $material = Material::factory()->create(['kode' => 'MAT-FROZEN', 'nama' => 'Material frozen']);
        $request = $this->materialRequest($mitra, 'terpenuhi_sebagian', [[$material, 4]], $project);

        $first = $this->createTransfer($mitra, $user, $origin, $destination, $request, $project, [[
            'material' => $material,
            'qty' => 4,
            'catatan' => 'Pengiriman pertama',
        ]]);
        $this->createTransfer($mitra, $user, $origin, $destination, $request, $project, [[
            'material' => $material,
            'qty' => 2,
            'catatan' => 'Pengiriman tambahan',
            'jenis_penyimpangan' => 'qty_melebihi',
        ]]);

        $this->actingAs($user)
            ->get(route('warehouse.transfers.show', $first))
            ->assertOk()
            ->assertSee('Request Material #'.$request->id)
            ->assertSee('Pengiriman pertama')
            ->assertDontSee('Qty melebihi sisa');
    }

    public function test_admin_mitra_cannot_reach_a_surat_jalan_owned_by_another_mitra(): void
    {
        $mitraA = Mitra::factory()->create();
        $mitraB = Mitra::factory()->create();
        $userA = $this->userWith($mitraA, 'operate_warehouse');
        $originA = $this->warehouse($mitraA, 'WH-TENANT-A-ASAL');
        $destinationA = $this->warehouse($mitraA, 'WH-TENANT-A-TUJUAN');
        $originA->users()->attach($userA);
        $destinationA->users()->attach($userA);
        $originB = $this->warehouse($mitraB, 'WH-TENANT-B-ASAL');
        $destinationB = $this->warehouse($mitraB, 'WH-TENANT-B-TUJUAN');
        $material = Material::factory()->create(['kode' => 'MAT-TENANT', 'nama' => 'Material tenant']);

        $own = $this->createTransfer($mitraA, $userA, $originA, $destinationA, null, null, [[
            'material' => $material,
            'qty' => 1,
        ]]);
        $other = $this->createTransfer($mitraB, $userA, $originB, $destinationB, null, null, [[
            'material' => $material,
            'qty' => 1,
        ]]);

        $this->actingAs($userA)
            ->get(route('warehouse.transfers.show', $own))
            ->assertOk();

        $this->actingAs($userA)
            ->get(route('warehouse.transfers.show', $other))
            ->assertNotFound();
    }

    /**
     * @param  list<array{material:Material,qty:string|int|float,catatan?:string|null,jenis_penyimpangan?:string|null}>  $items
     */
    private function createTransfer(
        Mitra $mitra,
        User $issuer,
        Warehouse $origin,
        Warehouse $destination,
        ?MaterialRequest $request,
        ?Project $project,
        array $items,
    ): SuratJalan {
        return $this->asThc(function () use ($mitra, $issuer, $origin, $destination, $request, $project, $items): SuratJalan {
            $suratJalan = SuratJalan::query()->create([
                'nomor' => 'SJ-DETAIL-'.Str::uuid(),
                'tanggal' => '2026-08-24',
                'warehouse_asal_id' => $origin->id,
                'warehouse_tujuan_id' => $destination->id,
                'mitra_id' => $mitra->id,
                'material_request_id' => $request?->id,
                'project_id' => $project?->id,
                'issued_by' => $issuer->id,
                'issued_at' => now(),
                'status' => 'terbit',
                'pengirim' => 'Petugas Detail',
            ]);

            foreach ($items as $item) {
                SuratJalanItem::query()->create([
                    'surat_jalan_id' => $suratJalan->id,
                    'mitra_id' => $mitra->id,
                    'material_id' => $item['material']->id,
                    'qty' => $item['qty'],
                    'qty_diterima' => 0,
                    'qty_diretur' => 0,
                    'catatan' => $item['catatan'] ?? null,
                    'jenis_penyimpangan' => $item['jenis_penyimpangan'] ?? null,
                ]);
            }

            return $suratJalan;
        });
    }
}
