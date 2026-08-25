<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Material;
use App\Models\MaterialRequest;
use App\Models\Mitra;
use App\Models\Project;
use App\Models\ProjectTimeline;
use App\Models\SuratJalan;
use App\Models\SuratJalanItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\TenantDatabaseContext;
use Closure;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Jalur HTTP penerbitan Surat Jalan bagi baris menyimpang: apa yang tersimpan, apa yang ditolak,
 * siapa yang boleh melihatnya. Kasus klasifikasinya sendiri tidak ditulis di sini — tempatnya
 * tests/fixtures/klasifikasi-penyimpangan.json, yang dibaca SuratJalanDeviationContractTest
 * dan sisi JS-nya sekaligus (ADR-0026).
 */
class SuratJalanDeviationTest extends TestCase
{
    use RefreshDatabase;

    public function test_material_outside_the_request_is_issued_and_marked_material_asing(): void
    {
        [$user, $asal, $tujuan, $requested, $request] = $this->approvedRequest(4);
        $substitute = $this->stockedMaterial($user, $asal, 5);

        $this->issue($user, $asal, $tujuan, $request, [
            ['material_id' => $requested->id, 'qty' => 4],
            ['material_id' => $substitute->id, 'qty' => 5, 'catatan' => 'Substitusi, stok material asli habis'],
        ])->assertRedirect();

        $this->assertNull($this->itemFor($requested)->jenis_penyimpangan);
        $this->assertSame('material_asing', $this->itemFor($substitute)->jenis_penyimpangan);
        $this->assertSame('Substitusi, stok material asli habis', $this->itemFor($substitute)->catatan);
    }

    public function test_quantity_above_the_remainder_is_issued_and_marked_qty_melebihi(): void
    {
        [$user, $asal, $tujuan, $material, $request] = $this->approvedRequest(4, 10);

        $this->issue($user, $asal, $tujuan, $request, [
            ['material_id' => $material->id, 'qty' => 6, 'catatan' => 'Titipan tambahan ikut kendaraan'],
        ])->assertRedirect();

        $this->assertSame('qty_melebihi', $this->itemFor($material)->jenis_penyimpangan);
    }

    public function test_quantity_below_the_remainder_is_not_a_deviation(): void
    {
        [$user, $asal, $tujuan, $material, $request] = $this->approvedRequest(4);

        $this->issue($user, $asal, $tujuan, $request, [
            ['material_id' => $material->id, 'qty' => 3],
        ])->assertRedirect();

        $this->assertNull($this->itemFor($material)->jenis_penyimpangan);
        $this->assertNull($this->itemFor($material)->catatan);
    }

    public function test_a_deviating_line_without_a_note_rejects_the_whole_issuance(): void
    {
        [$user, $asal, $tujuan, $requested, $request] = $this->approvedRequest(4);
        $substitute = $this->stockedMaterial($user, $asal, 5);

        $this->issue($user, $asal, $tujuan, $request, [
            ['material_id' => $requested->id, 'qty' => 4, 'catatan' => 'Sesuai permintaan'],
            ['material_id' => $substitute->id, 'qty' => 5],
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('surat_jalans', 0);
        $this->assertDatabaseCount('surat_jalan_items', 0);
    }

    public function test_a_quantity_above_the_remainder_without_a_note_rejects_the_whole_issuance(): void
    {
        [$user, $asal, $tujuan, $material, $request] = $this->approvedRequest(4, 10);

        $this->issue($user, $asal, $tujuan, $request, [
            ['material_id' => $material->id, 'qty' => 6],
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('surat_jalans', 0);
        $this->assertDatabaseCount('surat_jalan_items', 0);
    }

    public function test_a_compliant_line_needs_no_note(): void
    {
        [$user, $asal, $tujuan, $material, $request] = $this->approvedRequest(4);

        $this->issue($user, $asal, $tujuan, $request, [
            ['material_id' => $material->id, 'qty' => 4],
        ])->assertRedirect();

        $this->assertDatabaseCount('surat_jalans', 1);
        $this->assertNull($this->itemFor($material)->jenis_penyimpangan);
        $this->assertNull($this->itemFor($material)->catatan);
    }

    public function test_deviation_is_grouped_per_material_across_several_lines(): void
    {
        [$user, $asal, $tujuan, $material, $request] = $this->approvedRequest(4, 10);

        $this->issue($user, $asal, $tujuan, $request, [
            ['material_id' => $material->id, 'qty' => 3, 'catatan' => 'Bagian pertama'],
            ['material_id' => $material->id, 'qty' => 3, 'catatan' => 'Titipan tambahan'],
        ])->assertRedirect();

        $items = SuratJalanItem::query()->where('material_id', $material->id)->orderBy('id')->get();
        $this->assertSame(['qty_melebihi', 'qty_melebihi'], $items->pluck('jenis_penyimpangan')->all());
    }

    public function test_asal_warehouse_balance_still_blocks_a_quantity_the_request_would_allow(): void
    {
        [$user, $asal, $tujuan, $material, $request] = $this->approvedRequest(10, 4);

        $this->issue($user, $asal, $tujuan, $request, [
            ['material_id' => $material->id, 'qty' => 10],
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('surat_jalans', 0);
    }

    public function test_classification_is_frozen_when_a_later_surat_jalan_consumes_the_remainder(): void
    {
        [$user, $asal, $tujuan, $material, $request] = $this->approvedRequest(4, 10);

        $this->issue($user, $asal, $tujuan, $request, [
            ['material_id' => $material->id, 'qty' => 4],
        ])->assertRedirect();
        $first = SuratJalan::query()->latest('id')->firstOrFail();

        $this->issue($user, $asal, $tujuan, $request->fresh(), [
            ['material_id' => $material->id, 'qty' => 2, 'catatan' => 'Titipan tambahan'],
        ])->assertRedirect();

        $firstItem = SuratJalanItem::query()->where('surat_jalan_id', $first->id)->firstOrFail();
        $this->assertNull($firstItem->jenis_penyimpangan);
        $this->assertSame('qty_melebihi', $this->itemFor($material)->jenis_penyimpangan);
    }

    public function test_a_surat_jalan_without_a_request_never_has_a_deviating_line(): void
    {
        $mitra = Mitra::factory()->create();
        [$asal, $tujuan] = $this->warehousesFor($mitra);
        $user = $this->userWith($mitra, 'operate_warehouse');
        $asal->users()->attach($user);
        $tujuan->users()->attach($user);
        $material = $this->stockedMaterial($user, $asal, 7);

        $this->actingAs($user)->post('/warehouse/transfers', [
            'warehouse_asal_id' => $asal->id,
            'warehouse_tujuan_id' => $tujuan->id,
            'tanggal' => '2026-08-22',
            'pengirim' => 'Petugas Gudang',
            'items' => [['material_id' => $material->id, 'qty' => 7]],
        ])->assertRedirect();

        $this->assertNull($this->itemFor($material)->jenis_penyimpangan);
    }

    public function test_request_status_still_counts_a_mix_of_compliant_and_foreign_lines(): void
    {
        [$user, $asal, $tujuan, $requested, $request] = $this->approvedRequest(4);
        $substitute = $this->stockedMaterial($user, $asal, 5);

        $this->issue($user, $asal, $tujuan, $request, [
            ['material_id' => $requested->id, 'qty' => 4],
            ['material_id' => $substitute->id, 'qty' => 5, 'catatan' => 'Titipan'],
        ])->assertRedirect();
        $suratJalan = SuratJalan::query()->latest('id')->firstOrFail();

        $this->actingAs($user)->post("/warehouse/transfers/{$suratJalan->id}/receive")->assertRedirect();

        $this->assertSame('selesai', $request->fresh()->status);
    }

    public function test_project_deviation_records_timeline_event_with_material_categories(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra);
        [$user, $asal, $tujuan, $requested, $request] = $this->approvedRequest(4, 10, $project);
        $substitute = $this->stockedMaterial($user, $asal, 5);

        $this->issue($user, $asal, $tujuan, $request, [
            ['material_id' => $requested->id, 'qty' => 5, 'catatan' => 'Titipan tambahan'],
            ['material_id' => $substitute->id, 'qty' => 5, 'catatan' => 'Substitusi material'],
        ])->assertRedirect();

        $timeline = ProjectTimeline::query()
            ->where('project_id', $project->id)
            ->where('event_key', 'surat_jalan_deviation')
            ->firstOrFail();

        $this->assertSame([$substitute->nama], $timeline->metadata['material_asing']);
        $this->assertSame([$requested->nama], $timeline->metadata['qty_melebihi']);
    }

    public function test_deviation_on_a_request_without_a_project_has_no_timeline_event_but_keeps_the_line_note(): void
    {
        [$user, $asal, $tujuan, $requested, $request] = $this->approvedRequest(4, 10);
        $substitute = $this->stockedMaterial($user, $asal, 5);

        $this->issue($user, $asal, $tujuan, $request, [
            ['material_id' => $requested->id, 'qty' => 4],
            ['material_id' => $substitute->id, 'qty' => 5, 'catatan' => 'Substitusi material'],
        ])->assertRedirect();

        $this->assertDatabaseMissing('project_timelines', ['event_key' => 'surat_jalan_deviation']);
        $this->assertSame('Substitusi material', $this->itemFor($substitute)->catatan);
    }

    public function test_compliant_issuance_for_a_project_does_not_record_a_deviation_event(): void
    {
        $mitra = Mitra::factory()->create();
        $project = $this->projectFor($mitra);
        [$user, $asal, $tujuan, $material, $request] = $this->approvedRequest(4, null, $project);

        $this->issue($user, $asal, $tujuan, $request, [
            ['material_id' => $material->id, 'qty' => 4],
        ])->assertRedirect();

        $this->assertDatabaseMissing('project_timelines', [
            'project_id' => $project->id,
            'event_key' => 'surat_jalan_deviation',
        ]);
    }

    public function test_admin_mitra_still_cannot_reach_the_issuing_endpoint(): void
    {
        [$user, $asal, $tujuan, $material, $request] = $this->approvedRequest(4);
        $adminMitra = $this->userWith($asal->mitra, 'manage_mitra_users');

        $this->actingAs($adminMitra)->post('/warehouse/transfers', [
            'warehouse_asal_id' => $asal->id,
            'warehouse_tujuan_id' => $tujuan->id,
            'material_request_id' => $request->id,
            'tanggal' => '2026-08-22',
            'pengirim' => 'Admin Mitra',
            'items' => [['material_id' => $material->id, 'qty' => 4, 'catatan' => 'Coba tembus']],
        ])->assertForbidden();

        $this->assertDatabaseCount('surat_jalans', 0);
    }

    /** @param array<int,array<string,mixed>> $items */
    private function issue(User $user, Warehouse $asal, Warehouse $tujuan, MaterialRequest $request, array $items): TestResponse
    {
        return $this->actingAs($user)->post('/warehouse/transfers', [
            'warehouse_asal_id' => $asal->id,
            'warehouse_tujuan_id' => $tujuan->id,
            'material_request_id' => $request->id,
            'tanggal' => '2026-08-22',
            'pengirim' => 'Petugas Gudang',
            'items' => $items,
        ]);
    }

    /** @return array{User, Warehouse, Warehouse, Material, MaterialRequest} */
    private function approvedRequest(int $requestedQty, ?int $stockQty = null, ?Project $project = null): array
    {
        $mitra = $project?->mitra ?? Mitra::factory()->create();
        [$asal, $tujuan] = $this->warehousesFor($mitra);
        $user = $this->userWith($mitra, 'create_material_request', 'read_material_request', 'operate_warehouse');
        $thc = $this->userWith(null, 'approve_material_request');
        $asal->users()->attach($user);
        $tujuan->users()->attach($user);
        $material = Material::factory()->create(['jenis' => 'biasa']);

        $this->actingAs($user)->post('/material-requests', [
            'project_id' => $project?->id,
            'items' => [['material_id' => $material->id, 'qty' => $requestedQty]],
        ])->assertRedirect('/material-requests');
        $request = MaterialRequest::query()->firstOrFail();
        $this->actingAs($thc)->patch("/material-requests/{$request->id}/approve")->assertRedirect();

        $this->receiveStock($user, $asal, $material, $stockQty ?? $requestedQty);

        return [$user, $asal, $tujuan, $material, $request];
    }

    private function projectFor(Mitra $mitra): Project
    {
        return $this->asThc(fn (): Project => Project::query()->create([
            'id_project' => 'PRJ-2608-'.fake()->unique()->numerify('####'),
            'nama' => 'Project Deviation',
            'mitra_id' => $mitra->id,
            'status_project' => 'aktif',
        ]));
    }

    private function stockedMaterial(User $user, Warehouse $asal, int $qty): Material
    {
        $material = Material::factory()->create(['jenis' => 'biasa']);
        $this->receiveStock($user, $asal, $material, $qty);

        return $material;
    }

    private function receiveStock(User $user, Warehouse $asal, Material $material, int $qty): void
    {
        $this->actingAs($user)->post('/warehouse/stock/receive', [
            'warehouse_id' => $asal->id,
            'material_id' => $material->id,
            'qty' => $qty,
            'reason' => 'Penerimaan awal',
        ])->assertRedirect();
    }

    private function itemFor(Material $material): SuratJalanItem
    {
        return SuratJalanItem::query()->where('material_id', $material->id)->latest('id')->firstOrFail();
    }

    /** @return array{Warehouse, Warehouse} */
    private function warehousesFor(Mitra $mitra): array
    {
        return $this->asThc(fn (): array => [
            Warehouse::factory()->create(['mitra_id' => $mitra->id]),
            Warehouse::factory()->create(['mitra_id' => $mitra->id]),
        ]);
    }

    private function userWith(?Mitra $mitra, string ...$permissions): User
    {
        return User::factory()->create([
            'mitra_id' => $mitra?->id,
            'grup_id' => $this->groupWith(...$permissions)->id,
        ]);
    }

    private function groupWith(string ...$permissions): Grup
    {
        $group = Grup::factory()->create();
        $group->izins()->attach(collect($permissions)->map(
            fn (string $permission) => Izin::query()->firstOrCreate(['kode' => $permission], ['nama' => $permission])->id,
        )->all());

        return $group;
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
