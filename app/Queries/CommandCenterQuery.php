<?php

namespace App\Queries;

use App\Models\Drum;
use App\Models\Material;
use App\Models\MaterialRequest;
use App\Models\MaterialSn;
use App\Models\MaterialStok;
use App\Models\Mitra;
use App\Models\SuratJalan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class CommandCenterQuery
{
    /** @return array{total: int, thc: int, mitra: int} */
    public function activeUserCounts(): array
    {
        $counts = User::query()
            ->where('aktif', true)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(CASE WHEN mitra_id IS NULL THEN 1 ELSE 0 END), 0) as thc_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN mitra_id IS NOT NULL THEN 1 ELSE 0 END), 0) as mitra_count')
            ->first();

        return [
            'total' => (int) ($counts?->total ?? 0),
            'thc' => (int) ($counts?->thc_count ?? 0),
            'mitra' => (int) ($counts?->mitra_count ?? 0),
        ];
    }

    /** @return Collection<int, Mitra> */
    public function recentMitraOnboardings(?CarbonImmutable $now = null): Collection
    {
        $cutoff = ($now ?? CarbonImmutable::now())->subDays(30);

        return Mitra::query()
            ->where('created_at', '>=', $cutoff)
            ->with('adminMitraPertama')
            ->latest('created_at')
            ->get();
    }

    /** @return Collection<int, MaterialRequest> */
    public function pendingMaterialRequests(): Collection
    {
        return MaterialRequest::query()
            ->where('status', 'diajukan')
            ->with(['mitra', 'items.material.unit'])
            ->latest()
            ->get();
    }

    /** @return Collection<int, SuratJalan> */
    public function delayedTransits(?CarbonImmutable $now = null): Collection
    {
        $cutoff = ($now ?? CarbonImmutable::now())->subDays(3);

        return SuratJalan::query()
            ->where('status', 'terbit')
            ->where('issued_at', '<', $cutoff)
            ->with(['origin', 'destination', 'items.material.unit'])
            ->latest('issued_at')
            ->get();
    }

    /** @return Collection<int, Material> */
    public function criticalStocks(): Collection
    {
        $warehouseBalance = MaterialStok::query()
            ->selectRaw('COALESCE(SUM(qty), 0)')
            ->whereColumn('material_stoks.material_id', 'materials.id')
            ->where('lokasi_tipe', 'warehouse')
            ->where('qty', '>', 0);
        $serialBalance = MaterialSn::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('material_sns.material_id', 'materials.id')
            ->where('lokasi_tipe', 'warehouse')
            ->where('status', 'tersedia');
        $drumBalance = Drum::query()
            ->selectRaw('COALESCE(SUM(sisa), 0)')
            ->whereColumn('drums.material_id', 'materials.id')
            ->where('lokasi_tipe', 'warehouse')
            ->where('sisa', '>', 0);

        return Material::query()
            ->whereNotNull('ambang_minimum')
            ->where('ambang_minimum', '>', 0)
            ->with([
                'unit',
                'stocks' => fn ($query) => $query
                    ->where('lokasi_tipe', 'warehouse')
                    ->where('qty', '>', 0)
                    ->with('warehouse'),
            ])
            ->select('materials.*')
            ->selectSub($warehouseBalance, 'warehouse_balance')
            ->selectSub($serialBalance, 'serial_balance')
            ->selectSub($drumBalance, 'drum_balance')
            ->orderBy('materials.nama')
            ->get()
            ->filter(function (Material $material): bool {
                $balance = match ($material->jenis) {
                    'ber_sn' => (float) $material->serial_balance,
                    'drum_kabel' => (float) $material->drum_balance,
                    default => (float) $material->warehouse_balance,
                };

                $material->setAttribute('actual_balance', $balance);

                return $balance <= (float) $material->ambang_minimum;
            })
            ->values();
    }
}
