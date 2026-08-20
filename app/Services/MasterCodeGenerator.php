<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class MasterCodeGenerator
{
    private const DEFINITIONS = [
        'material' => ['table' => 'materials', 'prefix' => 'MAT'],
        'unit' => ['table' => 'units', 'prefix' => 'UNT'],
        'pop' => ['table' => 'pops', 'prefix' => 'POP'],
        'pekerjaan_jasa' => ['table' => 'pekerjaan_jasas', 'prefix' => 'JAS'],
        'warehouse' => ['table' => 'warehouses', 'prefix' => 'WH'],
    ];

    public function normalize(?string $code): ?string
    {
        $normalized = strtoupper(trim((string) $code));

        return $normalized === '' ? null : $normalized;
    }

    public function isAutomaticCode(string $entity, string $code): bool
    {
        $definition = $this->definition($entity);

        return preg_match('/^'.preg_quote($definition['prefix'], '/').'-(\\d{2})(0[1-9]|1[0-2])-\\d{4}$/', $code) === 1;
    }

    public function wasIssued(string $entity, string $code): bool
    {
        $this->definition($entity);

        return DB::table('master_code_issued')
            ->where('entity', $entity)
            ->where('kode', $code)
            ->exists();
    }

    public function generate(string $entity, DateTimeInterface $at): string
    {
        $definition = $this->definition($entity);
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Penerbitan kode master harus berada dalam transaksi database.');
        }

        $yearMonth = CarbonImmutable::instance($at)->setTimezone('Asia/Jakarta')->format('ym');
        $this->lockMonth($entity, $yearMonth);
        $prefix = $definition['prefix'].'-'.$yearMonth.'-';

        $maxExisting = (int) DB::table($definition['table'])
            ->where('kode', 'like', $prefix.'%')
            ->lockForUpdate()
            ->pluck('kode')
            ->map(fn (string $code): int => preg_match('/^'.preg_quote($prefix, '/').'([0-9]{4})$/', $code, $matches) === 1 ? (int) $matches[1] : 0)
            ->max();
        $maxIssued = (int) DB::table('master_code_issued')
            ->where('entity', $entity)
            ->where('kode', 'like', $prefix.'%')
            ->lockForUpdate()
            ->pluck('kode')
            ->map(fn (string $code): int => preg_match('/^'.preg_quote($prefix, '/').'([0-9]{4})$/', $code, $matches) === 1 ? (int) $matches[1] : 0)
            ->max();

        $sequence = DB::table('master_code_sequences')
            ->where('entity', $entity)
            ->where('bulan', $yearMonth)
            ->lockForUpdate()
            ->first();
        $next = max($maxExisting + 1, $maxIssued + 1, (int) ($sequence?->nomor_berikutnya ?? 1));

        while ($next <= 9999) {
            $code = $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            if (! $this->wasIssued($entity, $code)
                && ! DB::table($definition['table'])->where('kode', $code)->exists()) {
                break;
            }
            $next++;
        }

        if ($next > 9999) {
            throw new \OverflowException("Urutan kode {$entity} untuk bulan ini sudah penuh.");
        }

        $attributes = ['nomor_berikutnya' => $next + 1, 'updated_at' => now()];
        if ($sequence === null) {
            DB::table('master_code_sequences')->insert([
                'entity' => $entity,
                'bulan' => $yearMonth,
                ...$attributes,
                'created_at' => now(),
            ]);
        } else {
            DB::table('master_code_sequences')
                ->where('id', $sequence->id)
                ->update($attributes);
        }

        DB::table('master_code_issued')->insert([
            'entity' => $entity,
            'kode' => $code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $code;
    }

    private function definition(string $entity): array
    {
        if (! isset(self::DEFINITIONS[$entity])) {
            throw new \InvalidArgumentException("Entitas kode master tidak dikenal: {$entity}.");
        }

        return self::DEFINITIONS[$entity];
    }

    private function lockMonth(string $entity, string $yearMonth): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ["master-code:{$entity}:{$yearMonth}"]);
        }
    }
}
