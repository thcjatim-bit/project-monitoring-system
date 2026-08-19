<?php

namespace App\Services;

use App\Exceptions\SafeDeletionException;
use App\Models\Mitra;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class MitraDeletionService
{
    public function delete(Mitra $mitra): void
    {
        DB::transaction(function () use ($mitra): void {
            if ($mitra->users()->exists()) {
                throw new SafeDeletionException('Mitra tidak dapat dihapus karena masih memiliki User. Nonaktifkan Mitra sebagai gantinya.');
            }

            $references = $this->referencingRows($mitra->getKey());
            if ($references !== []) {
                throw new SafeDeletionException(sprintf(
                    'Mitra tidak dapat dihapus karena masih memiliki data pada %s. Nonaktifkan Mitra sebagai gantinya.',
                    implode(', ', $references),
                ));
            }

            try {
                $mitra->delete();
            } catch (QueryException $exception) {
                throw new SafeDeletionException('Mitra tidak dapat dihapus karena masih memiliki data yang direferensikan. Nonaktifkan Mitra sebagai gantinya.', 0, $exception);
            }
        });
    }

    /** @return list<string> */
    private function referencingRows(int $id): array
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return [];
        }

        $constraints = DB::select(<<<'SQL'
            select distinct kcu.table_name, kcu.column_name
            from information_schema.table_constraints tc
            join information_schema.key_column_usage kcu
              on tc.constraint_name = kcu.constraint_name
             and tc.table_schema = kcu.table_schema
            join information_schema.constraint_column_usage ccu
              on ccu.constraint_name = tc.constraint_name
             and ccu.table_schema = tc.table_schema
            where tc.constraint_type = 'FOREIGN KEY'
              and tc.table_schema = 'public'
              and ccu.table_name = 'mitras'
        SQL, []);

        $references = [];
        foreach ($constraints as $constraint) {
            if (DB::table($constraint->table_name)->where($constraint->column_name, $id)->exists()) {
                $references[] = $constraint->table_name.'.'.$constraint->column_name;
            }
        }

        return $references;
    }
}
