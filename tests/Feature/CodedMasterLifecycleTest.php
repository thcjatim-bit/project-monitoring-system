<?php

namespace Tests\Feature;

use App\Enums\MasterKind;
use App\Models\Grup;
use App\Models\Izin;
use App\Models\Material;
use App\Models\Mitra;
use App\Models\PekerjaanJasa;
use App\Models\Pop;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CodedMasterLifecycle;
use App\Support\TenantDatabaseContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class CodedMasterLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_thc_creates_a_coded_master_through_one_lifecycle(): void
    {
        CarbonImmutable::setTestNow('2026-08-21 10:00:00 Asia/Jakarta');

        try {
            $actor = $this->thcWithPermission('manage_master_data');

            $unit = app(CodedMasterLifecycle::class)->create($actor, MasterKind::Unit, [
                'kode' => '',
                'nama' => 'Meter',
            ]);

            $this->assertInstanceOf(Unit::class, $unit);
            $this->assertSame('UNT-2608-0001', $unit->kode);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_each_master_kind_uses_the_same_automatic_code_lifecycle(): void
    {
        CarbonImmutable::setTestNow('2026-08-21 10:00:00 Asia/Jakarta');

        try {
            $actor = $this->thcWithPermission('manage_master_data', 'manage_materials', 'manage_warehouses');
            $unit = Unit::query()->create(['kode' => 'PCS', 'nama' => 'Pieces']);
            $mitra = Mitra::factory()->create();
            $lifecycle = app(CodedMasterLifecycle::class);

            $records = $this->asThc(fn (): array => [
                $lifecycle->create($actor, MasterKind::Material, ['nama' => 'Kabel', 'unit_id' => $unit->id, 'jenis' => 'biasa']),
                $lifecycle->create($actor, MasterKind::Pop, ['nama' => 'PoP Surabaya']),
                $lifecycle->create($actor, MasterKind::PekerjaanJasa, ['nama' => 'Tarik Kabel']),
                $lifecycle->create($actor, MasterKind::Warehouse, ['nama' => 'Gudang Mitra', 'mitra_id' => $mitra->id]),
            ]);

            $this->assertContainsOnlyInstancesOf(Material::class, [$records[0]]);
            $this->assertInstanceOf(Pop::class, $records[1]);
            $this->assertInstanceOf(PekerjaanJasa::class, $records[2]);
            $this->assertInstanceOf(Warehouse::class, $records[3]);
            $this->assertSame(
                ['MAT-2608-0001', 'POP-2608-0001', 'JAS-2608-0001', 'WH-2608-0001'],
                array_map(fn ($record): string => $record->kode, $records),
            );
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_lifecycle_normalizes_updates_and_deactivates_manual_codes(): void
    {
        $actor = $this->thcWithPermission('manage_master_data');
        $lifecycle = app(CodedMasterLifecycle::class);
        $pop = $lifecycle->create($actor, MasterKind::Pop, ['kode' => ' pop-lama ', 'nama' => 'PoP Lama']);

        $updated = $lifecycle->update($actor, $pop, ['kode' => ' pop-baru ', 'nama' => 'PoP Baru']);
        $lifecycle->deactivate($actor, $updated);

        $this->assertSame('POP-BARU', $updated->kode);
        $this->assertFalse($updated->fresh()->aktif);
    }

    public function test_lifecycle_rejects_a_mitra_actor_even_with_a_management_permission(): void
    {
        $actor = $this->thcWithPermission('manage_master_data');
        $actor->update(['mitra_id' => Mitra::factory()->create()->id]);

        $this->expectException(HttpException::class);

        app(CodedMasterLifecycle::class)->create($actor->fresh(), MasterKind::Unit, [
            'kode' => 'M',
            'nama' => 'Meter',
        ]);
    }

    public function test_lifecycle_uses_jakarta_month_boundaries_and_independent_monthly_sequences(): void
    {
        $actor = $this->thcWithPermission('manage_master_data');
        $lifecycle = app(CodedMasterLifecycle::class);

        try {
            CarbonImmutable::setTestNow('2026-08-31 16:59:00 UTC');
            $august = $lifecycle->create($actor, MasterKind::Unit, ['nama' => 'Unit Agustus']);
            $augustNext = $lifecycle->create($actor, MasterKind::Unit, ['nama' => 'Unit Agustus Berikutnya']);
            CarbonImmutable::setTestNow('2026-08-31 17:01:00 UTC');
            $september = $lifecycle->create($actor, MasterKind::Unit, ['nama' => 'Unit September']);

            $this->assertSame(
                ['UNT-2608-0001', 'UNT-2608-0002', 'UNT-2609-0001'],
                [$august->kode, $augustNext->kode, $september->kode],
            );
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_lifecycle_skips_an_existing_code_when_issuing_automatically(): void
    {
        CarbonImmutable::setTestNow('2026-08-21 10:00:00 Asia/Jakarta');

        try {
            DB::table('units')->insert([
                'kode' => 'UNT-2608-0001',
                'nama' => 'Unit Legacy',
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $actor = $this->thcWithPermission('manage_master_data');

            $unit = app(CodedMasterLifecycle::class)->create($actor, MasterKind::Unit, ['nama' => 'Unit Baru']);

            $this->assertSame('UNT-2608-0002', $unit->kode);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_lifecycle_rejects_issuance_after_sequence_9999(): void
    {
        CarbonImmutable::setTestNow('2026-08-21 10:00:00 Asia/Jakarta');
        DB::table('master_code_sequences')->insert([
            'entity' => MasterKind::Warehouse->value,
            'bulan' => '2608',
            'nomor_berikutnya' => 10000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $actor = $this->thcWithPermission('manage_warehouses');

        try {
            $this->expectException(\OverflowException::class);
            app(CodedMasterLifecycle::class)->create($actor, MasterKind::Warehouse, ['nama' => 'Gudang Penuh']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_lifecycle_rolls_back_the_issued_ledger_when_record_creation_fails(): void
    {
        CarbonImmutable::setTestNow('2026-08-21 10:00:00 Asia/Jakarta');
        $event = 'eloquent.creating: '.Unit::class;
        Event::listen($event, static function (): never {
            throw new \RuntimeException('simulated record failure');
        });

        try {
            app(CodedMasterLifecycle::class)->create(
                $this->thcWithPermission('manage_master_data'),
                MasterKind::Unit,
                ['nama' => 'Unit Gagal'],
            );
            $this->fail('Lifecycle seharusnya meneruskan kegagalan penyimpanan record.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('simulated record failure', $exception->getMessage());
        } finally {
            Event::forget($event);
            CarbonImmutable::setTestNow();
        }

        $this->assertDatabaseMissing('master_code_issued', ['entity' => 'unit', 'kode' => 'UNT-2608-0001']);
        $this->assertDatabaseMissing('units', ['nama' => 'Unit Gagal']);
    }

    public function test_lifecycle_rejects_a_manual_code_that_uses_the_automatic_pattern(): void
    {
        $this->assertValidationError(function (): void {
            app(CodedMasterLifecycle::class)->create(
                $this->thcWithPermission('manage_master_data'),
                MasterKind::Unit,
                ['kode' => 'UNT-2608-0042', 'nama' => 'Unit Menyamar'],
            );
        }, 'kode');
    }

    public function test_lifecycle_rejects_duplicate_manual_codes(): void
    {
        $actor = $this->thcWithPermission('manage_master_data');
        $lifecycle = app(CodedMasterLifecycle::class);
        $lifecycle->create($actor, MasterKind::Pop, ['kode' => 'POP-MANUAL', 'nama' => 'PoP Pertama']);

        $this->assertValidationError(
            fn () => $lifecycle->create($actor, MasterKind::Pop, ['kode' => ' pop-manual ', 'nama' => 'PoP Kedua']),
            'kode',
        );
    }

    public function test_lifecycle_keeps_automatically_issued_codes_immutable(): void
    {
        $actor = $this->thcWithPermission('manage_master_data');
        $lifecycle = app(CodedMasterLifecycle::class);
        $unit = $lifecycle->create($actor, MasterKind::Unit, ['nama' => 'Unit Otomatis']);

        $this->assertValidationError(
            fn () => $lifecycle->update($actor, $unit, ['kode' => 'UNIT-BARU', 'nama' => 'Unit Otomatis']),
            'kode',
        );
    }

    public function test_lifecycle_requires_an_active_unit_for_material(): void
    {
        $unit = Unit::query()->create(['kode' => 'UNIT-LAMA', 'nama' => 'Unit Lama', 'aktif' => false]);
        $actor = $this->thcWithPermission('manage_materials');

        $this->assertValidationError(
            fn () => $this->asThc(fn () => app(CodedMasterLifecycle::class)->create($actor, MasterKind::Material, [
                'nama' => 'Material Baru',
                'unit_id' => $unit->id,
                'jenis' => 'biasa',
            ])),
            'unit_id',
        );
    }

    public function test_lifecycle_requires_an_active_mitra_for_warehouse(): void
    {
        $mitra = Mitra::factory()->create(['aktif' => false]);
        $actor = $this->thcWithPermission('manage_warehouses');

        $this->assertValidationError(
            fn () => $this->asThc(fn () => app(CodedMasterLifecycle::class)->create($actor, MasterKind::Warehouse, [
                'nama' => 'Gudang Mitra Lama',
                'mitra_id' => $mitra->id,
            ])),
            'mitra_id',
        );
    }

    public function test_material_lifecycle_requires_the_exact_material_permission(): void
    {
        $this->expectException(HttpException::class);

        app(CodedMasterLifecycle::class)->create(
            $this->thcWithPermission('manage_master_data'),
            MasterKind::Material,
            ['nama' => 'Material Tanpa Izin'],
        );
    }

    public function test_warehouse_lifecycle_requires_the_exact_warehouse_permission(): void
    {
        $this->expectException(HttpException::class);

        app(CodedMasterLifecycle::class)->create(
            $this->thcWithPermission('manage_master_data'),
            MasterKind::Warehouse,
            ['nama' => 'Gudang Tanpa Izin'],
        );
    }

    private function thcWithPermission(string ...$permissions): User
    {
        $group = Grup::factory()->create();
        foreach ($permissions as $permission) {
            $group->izins()->attach(Izin::factory()->create(['kode' => $permission]));
        }

        return User::factory()->create(['mitra_id' => null, 'grup_id' => $group->id]);
    }

    private function assertValidationError(\Closure $operation, string $field): void
    {
        try {
            $operation();
            $this->fail("ValidationException untuk {$field} tidak dilempar.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
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
}
