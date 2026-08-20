<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ENTITIES = [
        'material' => ['table' => 'materials', 'prefix' => 'MAT'],
        'unit' => ['table' => 'units', 'prefix' => 'UNT'],
        'pop' => ['table' => 'pops', 'prefix' => 'POP'],
        'pekerjaan_jasa' => ['table' => 'pekerjaan_jasas', 'prefix' => 'JAS'],
        'warehouse' => ['table' => 'warehouses', 'prefix' => 'WH'],
    ];

    public function up(): void
    {
        Schema::create('master_code_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('entity', 32);
            $table->char('bulan', 4);
            $table->unsignedSmallInteger('nomor_berikutnya');
            $table->timestamps();
            $table->unique(['entity', 'bulan']);
        });

        Schema::create('master_code_issued', function (Blueprint $table): void {
            $table->id();
            $table->string('entity', 32);
            $table->string('kode');
            $table->timestamps();
            $table->unique(['entity', 'kode']);
        });

        foreach (self::ENTITIES as $entity => $definition) {
            $this->normalizeAndRegisterExistingCodes($entity, $definition['table'], $definition['prefix']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('master_code_issued');
        Schema::dropIfExists('master_code_sequences');
    }

    private function normalizeAndRegisterExistingCodes(string $entity, string $table, string $prefix): void
    {
        $seen = [];
        $maximums = [];
        $pattern = '/^'.preg_quote($prefix, '/').'-(\\d{2})(0[1-9]|1[0-2])-(\\d{4})$/';

        foreach (DB::table($table)->orderBy('id')->get(['id', 'kode']) as $record) {
            $code = strtoupper(trim((string) $record->kode));
            if ($code === '') {
                throw new RuntimeException("Kode kosong ditemukan pada {$table}#{$record->id}.");
            }
            if (isset($seen[$code])) {
                throw new RuntimeException("Collision kode {$code} ditemukan pada {$table}.");
            }
            $seen[$code] = true;

            if ($code !== $record->kode) {
                DB::table($table)->where('id', $record->id)->update(['kode' => $code]);
            }

            if (preg_match($pattern, $code, $matches) === 1) {
                DB::table('master_code_issued')->insert([
                    'entity' => $entity,
                    'kode' => $code,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $month = $matches[1].$matches[2];
                $maximums[$month] = max($maximums[$month] ?? 0, (int) $matches[3]);
            }
        }

        foreach ($maximums as $month => $maximum) {
            DB::table('master_code_sequences')->insert([
                'entity' => $entity,
                'bulan' => $month,
                'nomor_berikutnya' => $maximum + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
