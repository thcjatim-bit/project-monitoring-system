<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Services\MasterCodeGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class MasterCodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_a_separate_monthly_sequence_for_each_master_entity(): void
    {
        $generator = app(MasterCodeGenerator::class);
        $august = CarbonImmutable::parse('2026-08-20 10:00:00', 'Asia/Jakarta');
        $september = CarbonImmutable::parse('2026-09-01 00:01:00', 'Asia/Jakarta');

        $codes = DB::transaction(function () use ($generator, $august, $september): array {
            return [
                $generator->generate('material', $august),
                $generator->generate('material', $august),
                $generator->generate('unit', $august),
                $generator->generate('material', $september),
            ];
        });

        $this->assertSame([
            'MAT-2608-0001',
            'MAT-2608-0002',
            'UNT-2608-0001',
            'MAT-2609-0001',
        ], $codes);
    }

    public function test_it_skips_existing_manual_codes_and_remembers_issued_codes(): void
    {
        $unit = Unit::query()->create(['kode' => 'PCS', 'nama' => 'Pieces']);
        DB::table('materials')->insert([
            'kode' => 'MAT-2608-0001',
            'nama' => 'Material Legacy',
            'unit_id' => $unit->id,
            'jenis' => 'biasa',
            'aktif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $generator = app(MasterCodeGenerator::class);
        $code = DB::transaction(fn (): string => $generator->generate(
            'material',
            CarbonImmutable::parse('2026-08-20 10:00:00', 'Asia/Jakarta'),
        ));

        $this->assertSame('MAT-2608-0002', $code);
        $this->assertDatabaseHas('master_code_issued', [
            'entity' => 'material',
            'kode' => 'MAT-2608-0002',
        ]);
    }

    public function test_a_failed_transaction_does_not_consume_a_code(): void
    {
        $generator = app(MasterCodeGenerator::class);
        $date = CarbonImmutable::parse('2026-08-20 10:00:00', 'Asia/Jakarta');

        try {
            DB::transaction(function () use ($generator, $date): void {
                $generator->generate('unit', $date);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
            // Expected: the code was never successfully issued.
        }

        $code = DB::transaction(fn (): string => $generator->generate('unit', $date));

        $this->assertSame('UNT-2608-0001', $code);
    }

    public function test_it_throws_when_the_monthly_sequence_is_full(): void
    {
        DB::table('master_code_sequences')->insert([
            'entity' => 'warehouse',
            'bulan' => '2608',
            'nomor_berikutnya' => 10000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\OverflowException::class);

        DB::transaction(fn (): string => app(MasterCodeGenerator::class)->generate(
            'warehouse',
            CarbonImmutable::parse('2026-08-20 10:00:00', 'Asia/Jakarta'),
        ));
    }
}
