<?php

namespace App\Services;

use App\Models\MitraHargaJasa;
use App\Models\PekerjaanJasa;
use App\Models\Pks;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MitraPriceBook
{
    /** @return Collection<int, MitraHargaJasa> */
    public function priceBookFor(User $actor): Collection
    {
        $this->assertManageAccess($actor);

        return MitraHargaJasa::query()
            ->with('pekerjaanJasa', 'pks')
            ->where('mitra_id', $actor->mitra_id)
            ->latest()
            ->get();
    }

    /** @return array{jobs: Collection<int, PekerjaanJasa>, pks: Collection<int, Pks>} */
    public function catalogFor(User $actor): array
    {
        $this->assertManageAccess($actor);

        return [
            'jobs' => PekerjaanJasa::query()->where('aktif', true)->orderBy('nama')->get(),
            'pks' => Pks::query()
                ->whereDate('tanggal_mulai', '<=', today())
                ->where(fn ($query) => $query->whereNull('tanggal_berakhir')->orWhereDate('tanggal_berakhir', '>=', today()))
                ->where('mitra_id', $actor->mitra_id)
                ->orderByDesc('tanggal_mulai')
                ->get(),
        ];
    }

    /** @param array{pks_id:int,pekerjaan_jasa_id:int,harga:string|int|float,berlaku_mulai:string,revisi_dari_id?:int|null} $data */
    public function submit(User $actor, array $data): MitraHargaJasa
    {
        $mitraId = $actor->mitra_id;
        $this->assertManageAccess($actor);
        if (! is_numeric($data['harga']) || (float) $data['harga'] <= 0) {
            throw ValidationException::withMessages(['harga' => 'Harga harus lebih besar dari nol.']);
        }

        return DB::transaction(function () use ($actor, $data, $mitraId): MitraHargaJasa {
            $pks = Pks::query()
                ->whereKey($data['pks_id'])
                ->where('mitra_id', $mitraId)
                ->whereDate('tanggal_mulai', '<=', $data['berlaku_mulai'])
                ->where(fn ($query) => $query
                    ->whereNull('tanggal_berakhir')
                    ->orWhereDate('tanggal_berakhir', '>=', $data['berlaku_mulai']))
                ->first();
            if ($pks === null) {
                throw ValidationException::withMessages(['pks_id' => 'PKS tidak aktif untuk tanggal berlaku tersebut.']);
            }

            if (! PekerjaanJasa::query()->whereKey($data['pekerjaan_jasa_id'])->where('aktif', true)->exists()) {
                throw ValidationException::withMessages(['pekerjaan_jasa_id' => 'Pekerjaan Jasa tidak aktif.']);
            }

            $revision = null;
            if (! empty($data['revisi_dari_id'])) {
                $revision = MitraHargaJasa::query()
                    ->whereKey($data['revisi_dari_id'])
                    ->where('mitra_id', $mitraId)
                    ->where('status', 'disetujui')
                    ->first();
                if ($revision === null) {
                    throw ValidationException::withMessages(['revisi_dari_id' => 'Harga Jasa Mitra asal revisi tidak ditemukan.']);
                }
                if ((int) $revision->pekerjaan_jasa_id !== (int) $data['pekerjaan_jasa_id']) {
                    throw ValidationException::withMessages(['revisi_dari_id' => 'Revisi harus memakai Pekerjaan Jasa yang sama.']);
                }
            }

            return MitraHargaJasa::query()->create([
                'mitra_id' => $mitraId,
                'pks_id' => $pks->id,
                'pekerjaan_jasa_id' => $data['pekerjaan_jasa_id'],
                'harga' => $data['harga'],
                'status' => 'diajukan',
                'berlaku_mulai' => $data['berlaku_mulai'],
                'diajukan_oleh' => $actor->id,
                'revisi_dari_id' => $revision?->id,
            ]);
        });
    }

    public function decide(User $actor, MitraHargaJasa $price, string $decision): MitraHargaJasa
    {
        abort_unless($actor->mitra_id === null && $actor->hasIzin('approve_mitra_price'), 403);
        if (! in_array($decision, ['disetujui', 'ditolak'], true)) {
            throw ValidationException::withMessages(['status' => 'Keputusan Harga Jasa Mitra tidak valid.']);
        }

        return DB::transaction(function () use ($actor, $price, $decision): MitraHargaJasa {
            $locked = MitraHargaJasa::query()->lockForUpdate()->find($price->id);
            if ($locked === null) {
                throw (new ModelNotFoundException())->setModel(MitraHargaJasa::class, [$price->id]);
            }
            if ($locked->status !== 'diajukan') {
                throw ValidationException::withMessages(['status' => 'Harga Jasa Mitra sudah diputuskan.']);
            }

            $locked->update([
                'status' => $decision,
                'diputuskan_oleh' => $actor->id,
                'diputuskan_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    public function effectiveFor(Project $project, int $priceId, \DateTimeInterface $at): MitraHargaJasa
    {
        $date = CarbonImmutable::instance($at)->toDateString();
        $price = MitraHargaJasa::query()
            ->whereKey($priceId)
            ->where('mitra_id', $project->mitra_id)
            ->where('status', 'disetujui')
            ->whereDate('berlaku_mulai', '<=', $date)
            ->whereHas('pekerjaanJasa', fn ($query) => $query->where('aktif', true))
            ->whereHas('pks', fn ($query) => $query
                ->where('mitra_id', $project->mitra_id)
                ->whereDate('tanggal_mulai', '<=', $date)
                ->where(fn ($query) => $query
                    ->whereNull('tanggal_berakhir')
                    ->orWhereDate('tanggal_berakhir', '>=', $date)))
            ->first();

        if ($price === null) {
            throw ValidationException::withMessages([
                'harga_jasa_id' => 'Harga Jasa Mitra belum disetujui atau belum berlaku untuk Project ini.',
            ]);
        }

        return $price;
    }

    private function assertManageAccess(User $actor): void
    {
        abort_unless($actor->mitra_id !== null && $actor->hasIzin('manage_mitra_prices'), 403);
    }
}
