<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MitraCodeGenerator
{
    public function wasIssued(string $code): bool
    {
        if (! preg_match('/^MTR-(\d{4})-(\d{4})$/', $code, $matches)) {
            return false;
        }

        return DB::table('mitra_code_issued')->where('kode', $code)->exists();
    }

    public function generate(string $yearMonth): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['mitra-code:'.$yearMonth]);
        }

        $prefix = 'MTR-'.$yearMonth.'-';
        $maxExisting = (int) DB::table('mitras')
            ->where('kode', 'like', $prefix.'%')
            ->lockForUpdate()
            ->pluck('kode')
            ->map(fn (string $kode): int => preg_match('/^'.preg_quote($prefix, '/').'([0-9]{4})$/', $kode, $matches) ? (int) $matches[1] : 0)
            ->max();

        $sequence = DB::table('mitra_code_sequences')
            ->where('bulan', $yearMonth)
            ->lockForUpdate()
            ->first();
        $next = max($maxExisting + 1, (int) ($sequence?->nomor_berikutnya ?? 1));
        if ($next > 9999) {
            throw new \OverflowException('Urutan Kode Mitra untuk bulan ini sudah penuh.');
        }

        if ($sequence === null) {
            DB::table('mitra_code_sequences')->insert(['bulan' => $yearMonth, 'nomor_berikutnya' => $next + 1]);
        } else {
            DB::table('mitra_code_sequences')->where('bulan', $yearMonth)->update(['nomor_berikutnya' => $next + 1]);
        }

        $code = $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        DB::table('mitra_code_issued')->insert(['kode' => $code, 'created_at' => now(), 'updated_at' => now()]);

        return $code;
    }
}
