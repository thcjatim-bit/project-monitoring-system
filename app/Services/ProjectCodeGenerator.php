<?php

namespace App\Services;

use App\Exceptions\ProjectCodeAlreadyIssuedException;
use Illuminate\Support\Facades\DB;

class ProjectCodeGenerator
{
    public function isAutomaticCode(string $code): bool
    {
        return $this->yearMonthFor($code) !== null;
    }

    public function wasIssued(string $code): bool
    {
        return DB::table('project_code_issued')->where('kode', $code)->exists();
    }

    public function reserveManual(string $code): void
    {
        $yearMonth = $this->yearMonthFor($code);
        if ($yearMonth === null) {
            return;
        }

        DB::transaction(function () use ($code, $yearMonth): void {
            $this->lockMonth($yearMonth);

            if ($this->wasIssued($code)) {
                throw new ProjectCodeAlreadyIssuedException('ID Project otomatis tersebut sudah pernah diterbitkan.');
            }

            DB::table('project_code_issued')->insert([
                'kode' => $code,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function generate(string $yearMonth): string
    {
        return DB::transaction(function () use ($yearMonth): string {
            $this->lockMonth($yearMonth);

            $prefix = 'PRJ-'.$yearMonth.'-';
            $maxExisting = (int) DB::table('projects')
                ->where('id_project', 'like', $prefix.'%')
                ->lockForUpdate()
                ->pluck('id_project')
                ->map(fn (string $code): int => preg_match('/^'.preg_quote($prefix, '/').'([0-9]{4})$/', $code, $matches) ? (int) $matches[1] : 0)
                ->max();
            $maxIssued = (int) DB::table('project_code_issued')
                ->where('kode', 'like', $prefix.'%')
                ->pluck('kode')
                ->map(fn (string $code): int => preg_match('/^'.preg_quote($prefix, '/').'([0-9]{4})$/', $code, $matches) ? (int) $matches[1] : 0)
                ->max();

            $sequence = DB::table('project_code_sequences')
                ->where('bulan', $yearMonth)
                ->lockForUpdate()
                ->first();
            $next = max($maxExisting + 1, $maxIssued + 1, (int) ($sequence?->nomor_berikutnya ?? 1));

            while ($next <= 9999) {
                $code = $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
                if (! DB::table('project_code_issued')->where('kode', $code)->exists()
                    && ! DB::table('projects')->where('id_project', $code)->exists()) {
                    break;
                }
                $next++;
            }

            if ($next > 9999) {
                throw new \OverflowException('Urutan ID Project untuk bulan ini sudah penuh.');
            }

            if ($sequence === null) {
                DB::table('project_code_sequences')->insert([
                    'bulan' => $yearMonth,
                    'nomor_berikutnya' => $next + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('project_code_sequences')->where('bulan', $yearMonth)->update([
                    'nomor_berikutnya' => $next + 1,
                    'updated_at' => now(),
                ]);
            }

            DB::table('project_code_issued')->insert([
                'kode' => $code,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $code;
        });
    }

    private function yearMonthFor(string $code): ?string
    {
        return preg_match('/^PRJ-(\d{2})(0[1-9]|1[0-2])-\d{4}$/', $code, $matches) === 1
            ? $matches[1].$matches[2]
            : null;
    }

    private function lockMonth(string $yearMonth): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['project-code:'.$yearMonth]);
        }
    }
}
