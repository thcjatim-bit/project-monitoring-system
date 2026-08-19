<?php

namespace App\Services;

use App\Exceptions\SafeDeletionException;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class UserDeletionService
{
    /** @var list<array{table: string, column: string}> */
    private const HISTORY_REFERENCES = [
        ['table' => 'material_transaksis', 'column' => 'actor_id'],
        ['table' => 'material_requests', 'column' => 'requested_by'],
        ['table' => 'material_requests', 'column' => 'decided_by'],
        ['table' => 'surat_jalans', 'column' => 'issued_by'],
        ['table' => 'surat_jalans', 'column' => 'received_by'],
        ['table' => 'surat_jalans', 'column' => 'resolved_by'],
        ['table' => 'project_timelines', 'column' => 'actor_id'],
        ['table' => 'project_progresses', 'column' => 'reported_by'],
        ['table' => 'project_progresses', 'column' => 'verified_by'],
        ['table' => 'project_steps', 'column' => 'completed_by'],
        ['table' => 'project_photos', 'column' => 'uploaded_by'],
        ['table' => 'project_timeline_mentions', 'column' => 'mentioned_user_id'],
        ['table' => 'pemakaian_materials', 'column' => 'requested_by'],
        ['table' => 'pemakaian_materials', 'column' => 'decided_by'],
        ['table' => 'project_rekons', 'column' => 'opened_by'],
        ['table' => 'project_rekons', 'column' => 'approved_by'],
        ['table' => 'api_keys', 'column' => 'created_by'],
        ['table' => 'api_key_audits', 'column' => 'actor_id'],
        ['table' => 'mitra_harga_jasas', 'column' => 'diajukan_oleh'],
        ['table' => 'mitra_harga_jasas', 'column' => 'diputuskan_oleh'],
        ['table' => 'project_variation_orders', 'column' => 'dibuat_oleh'],
        ['table' => 'project_variation_orders', 'column' => 'disetujui_oleh'],
        ['table' => 'project_rab_jasas', 'column' => 'dibuat_oleh'],
        ['table' => 'project_baselines', 'column' => 'dibuat_oleh'],
    ];

    public function delete(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw new SafeDeletionException('User tidak dapat menghapus dirinya sendiri. Nonaktifkan User bila akses harus dihentikan.');
        }

        DB::transaction(function () use ($user): void {
            $activeThcUsers = User::query()
                ->select('id')
                ->whereNull('mitra_id')
                ->where('aktif', true)
                ->lockForUpdate()
                ->get();
            if ($user->mitra_id === null && $user->aktif && $activeThcUsers->count() <= 1) {
                throw new SafeDeletionException('User THC aktif terakhir tidak dapat dihapus. Nonaktifkan User lain atau buat pengganti terlebih dahulu.');
            }

            foreach ($this->referencingRows($user->getKey()) as $reference) {
                throw new SafeDeletionException(sprintf(
                    'User tidak dapat dihapus karena memiliki histori pada %s. Nonaktifkan User sebagai gantinya.',
                    $reference,
                ));
            }

            try {
                $user->delete();
            } catch (QueryException $exception) {
                throw new SafeDeletionException('User tidak dapat dihapus karena memiliki histori yang direferensikan. Nonaktifkan User sebagai gantinya.', 0, $exception);
            }
        });
    }

    /** @return list<string> */
    private function referencingRows(int $id): array
    {
        $references = [];
        foreach (self::HISTORY_REFERENCES as $reference) {
            if (DB::table($reference['table'])->where($reference['column'], $id)->exists()) {
                $references[] = $reference['table'].'.'.$reference['column'];
            }
        }

        return $references;
    }
}
