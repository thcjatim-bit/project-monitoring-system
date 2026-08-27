<?php

namespace App\Queries;

use App\Models\Drum;
use App\Models\Material;
use App\Models\MaterialRequest;
use App\Models\MaterialSn;
use App\Models\MaterialStok;
use App\Models\Mitra;
use App\Models\PekerjaanJasa;
use App\Models\Pop;
use App\Models\SuratJalan;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

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

    /** @return EloquentCollection<int, Mitra> */
    public function recentMitraOnboardings(?CarbonImmutable $now = null): EloquentCollection
    {
        $cutoff = ($now ?? CarbonImmutable::now())->subDays(30);

        return Mitra::query()
            ->where('created_at', '>=', $cutoff)
            ->with('adminMitraPertama')
            ->latest('created_at')
            ->get();
    }

    /** @return EloquentCollection<int, MaterialRequest> */
    public function pendingMaterialRequests(): EloquentCollection
    {
        return MaterialRequest::query()
            ->where('status', 'diajukan')
            ->with(['mitra', 'project', 'items.material.unit'])
            ->latest()
            ->get();
    }

    /** @return EloquentCollection<int, SuratJalan> */
    public function delayedTransits(?CarbonImmutable $now = null): EloquentCollection
    {
        $cutoff = ($now ?? CarbonImmutable::now())->startOfDay()->subDays(3);

        return SuratJalan::query()
            ->where('status', 'terbit')
            ->where('issued_at', '<', $cutoff)
            ->with(['asal', 'tujuan', 'items.material.unit'])
            ->latest('issued_at')
            ->get();
    }

    /** @return EloquentCollection<int, Material> */
    public function criticalStocks(): EloquentCollection
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

    /**
     * Build the read-only Warehouse readiness facts that the actor is allowed to see.
     *
     * @return EloquentCollection<int, Warehouse>
     */
    public function warehouseReadiness(User $actor, ?CarbonImmutable $now = null): EloquentCollection
    {
        $canManageWarehouses = $actor->hasIzin('manage_warehouses');
        $canReadMasterData = $actor->hasIzin('read_master_data');
        $canOperateWarehouse = $actor->hasIzin('operate_warehouse');

        if (! $canManageWarehouses && ! $canReadMasterData && ! $canOperateWarehouse) {
            return new EloquentCollection;
        }

        $warehouses = Warehouse::query()
            ->where('aktif', true)
            ->with('mitra')
            ->when($canManageWarehouses, fn ($query) => $query->withCount([
                'users as active_petugas_count' => fn ($users) => $users->where('users.aktif', true),
            ]))
            ->orderBy('nama')
            ->orderBy('id')
            ->get();

        if ($warehouses->isEmpty()) {
            return $warehouses;
        }

        $warehouseIds = $warehouses->modelKeys();
        $materials = $canReadMasterData
            ? Material::query()
                ->whereNotNull('ambang_minimum')
                ->where('ambang_minimum', '>', 0)
                ->get(['id', 'jenis', 'ambang_minimum'])
            : new EloquentCollection;

        $ordinaryBalances = $canReadMasterData
            ? MaterialStok::query()
                ->whereIn('warehouse_id', $warehouseIds)
                ->where('lokasi_tipe', 'warehouse')
                ->get(['warehouse_id', 'material_id', 'qty'])
                ->groupBy('warehouse_id')
                ->map(fn (EloquentCollection|Collection $stocks): Collection => $stocks
                    ->groupBy('material_id')
                    ->map(fn (EloquentCollection|Collection $rows): float => (float) $rows->sum('qty')))
            : collect();
        $serialBalances = $canReadMasterData
            ? MaterialSn::query()
                ->whereIn('lokasi_id', $warehouseIds)
                ->where('lokasi_tipe', 'warehouse')
                ->where('status', 'tersedia')
                ->get(['lokasi_id', 'material_id'])
                ->groupBy('lokasi_id')
                ->map(fn (EloquentCollection|Collection $serials): Collection => $serials
                    ->countBy('material_id')
                    ->map(fn (int $count): float => (float) $count))
            : collect();
        $drumBalances = $canReadMasterData
            ? Drum::query()
                ->whereIn('lokasi_id', $warehouseIds)
                ->where('lokasi_tipe', 'warehouse')
                ->where('sisa', '>', 0)
                ->get(['lokasi_id', 'material_id', 'sisa'])
                ->groupBy('lokasi_id')
                ->map(fn (EloquentCollection|Collection $drums): Collection => $drums
                    ->groupBy('material_id')
                    ->map(fn (EloquentCollection|Collection $rows): float => (float) $rows->sum('sisa')))
            : collect();

        $transits = $canOperateWarehouse
            ? SuratJalan::query()
                ->where('status', 'terbit')
                ->where(function ($query) use ($warehouseIds): void {
                    $query
                        ->whereIn('warehouse_asal_id', $warehouseIds)
                        ->orWhereIn('warehouse_tujuan_id', $warehouseIds);
                })
                ->get(['warehouse_asal_id', 'warehouse_tujuan_id', 'issued_at'])
            : collect();
        $transitCounts = collect();
        $cutoff = ($now ?? CarbonImmutable::now())->startOfDay()->subDays(3);

        foreach ($transits as $transit) {
            foreach (array_unique([(int) $transit->warehouse_asal_id, (int) $transit->warehouse_tujuan_id]) as $warehouseId) {
                $counts = $transitCounts->get($warehouseId, ['active' => 0, 'delayed' => 0]);
                $counts['active']++;
                if ($transit->issued_at?->lt($cutoff)) {
                    $counts['delayed']++;
                }
                $transitCounts->put($warehouseId, $counts);
            }
        }

        foreach ($warehouses as $warehouse) {
            if ($canReadMasterData) {
                $ordinary = $ordinaryBalances->get($warehouse->id, collect());
                $serial = $serialBalances->get($warehouse->id, collect());
                $drum = $drumBalances->get($warehouse->id, collect());
                $criticalCount = $materials->filter(function (Material $material) use ($ordinary, $serial, $drum): bool {
                    $balance = match ($material->jenis) {
                        'ber_sn' => (float) $serial->get($material->id, 0),
                        'drum_kabel' => (float) $drum->get($material->id, 0),
                        default => (float) $ordinary->get($material->id, 0),
                    };

                    return $balance <= (float) $material->ambang_minimum;
                })->count();
                $warehouse->setAttribute('critical_material_count', $criticalCount);
            }

            if ($canOperateWarehouse) {
                $warehouse->setAttribute('active_transit_count', $transitCounts->get($warehouse->id, ['active' => 0, 'delayed' => 0])['active']);
                $warehouse->setAttribute('delayed_transit_count', $transitCounts->get($warehouse->id, ['active' => 0, 'delayed' => 0])['delayed']);
            }

            if ($canManageWarehouses && $canReadMasterData && $canOperateWarehouse) {
                $warehouse->setAttribute(
                    'readiness_status',
                    $warehouse->active_petugas_count >= 1
                        && $warehouse->critical_material_count === 0
                        && $warehouse->delayed_transit_count === 0
                        ? 'Siap'
                        : 'Perlu perhatian',
                );
            }
        }

        return $warehouses;
    }

    /**
     * Build a read-only navigation feed from timestamps already persisted by each domain.
     *
     * @return Collection<int, array{source: string, entity: string, title: string, description: string, status: string, occurred_at: CarbonInterface, url: string, id: int, sort_key: string}>
     */
    public function activityFeed(User $actor, int $limit = 20): Collection
    {
        $activities = collect();

        if ($actor->hasIzin('read_material_request')) {
            MaterialRequest::query()
                ->with(['mitra', 'project'])
                ->latest('updated_at')
                ->limit($limit)
                ->get()
                ->each(fn (MaterialRequest $request) => $activities->push($this->materialRequestActivity($request)));
        }

        if ($actor->hasIzin('operate_warehouse')) {
            SuratJalan::query()
                ->with(['asal', 'tujuan', 'mitra'])
                ->latest('updated_at')
                ->limit($limit)
                ->get()
                ->each(fn (SuratJalan $suratJalan) => $activities->push($this->suratJalanActivity($suratJalan)));
        }

        if ($actor->hasIzin('manage_users')) {
            User::query()
                ->with('mitra')
                ->latest('updated_at')
                ->limit($limit)
                ->get()
                ->each(fn (User $user) => $activities->push($this->userActivity($user)));
        }

        if ($actor->hasIzin('manage_mitras')) {
            Mitra::query()
                ->latest('updated_at')
                ->limit($limit)
                ->get()
                ->each(fn (Mitra $mitra) => $activities->push($this->mitraActivity($mitra)));
        }

        if ($actor->hasIzin('read_master_data')) {
            $this->appendMasterDataActivities($activities, Material::class, 'Material', fn () => route('admin.materials'), $limit);
            $this->appendMasterDataActivities($activities, Unit::class, 'Unit', fn () => route('admin.master.index', 'units'), $limit);
            $this->appendMasterDataActivities($activities, Pop::class, 'PoP', fn () => route('admin.master.index', 'pops'), $limit);
            $this->appendMasterDataActivities($activities, PekerjaanJasa::class, 'Pekerjaan Jasa', fn () => route('admin.master.index', 'pekerjaan-jasa'), $limit);
        }

        if ($actor->hasIzin('manage_warehouses')) {
            $this->appendMasterDataActivities($activities, Warehouse::class, 'Warehouse', fn () => route('admin.warehouses'), $limit);
        }

        return $activities
            ->sortByDesc('sort_key')
            ->take($limit)
            ->values();
    }

    /** @return array{source: string, entity: string, title: string, description: string, status: string, occurred_at: CarbonInterface, url: string, id: int, sort_key: string} */
    private function materialRequestActivity(MaterialRequest $request): array
    {
        $occurredAt = match ($request->status) {
            'diajukan' => $request->created_at,
            'disetujui', 'ditolak', 'ditutup' => $request->decided_at ?? $request->updated_at,
            default => $request->updated_at,
        } ?? $request->created_at ?? CarbonImmutable::now();

        $projectDescription = $request->project === null
            ? 'Tanpa Project'
            : $request->project->id_project.' — '.$request->project->nama;
        $description = $projectDescription.' · '.($request->mitra?->nama ?? 'Mitra tidak tersedia');

        return $this->activity(
            source: 'Request Material',
            entity: 'Request Material',
            title: 'Request Material #'.$request->id,
            description: $description,
            status: $this->materialRequestStatus($request->status),
            occurredAt: $occurredAt,
            url: route('material-requests.show', $request),
            id: (int) $request->id,
        );
    }

    /** @return array{source: string, entity: string, title: string, description: string, status: string, occurred_at: CarbonInterface, url: string, id: int, sort_key: string} */
    private function suratJalanActivity(SuratJalan $suratJalan): array
    {
        $occurredAt = match ($suratJalan->status) {
            'terbit' => $suratJalan->issued_at,
            'diterima' => $suratJalan->received_at ?? $suratJalan->updated_at,
            default => $suratJalan->updated_at,
        } ?? $suratJalan->issued_at ?? CarbonImmutable::now();
        $asal = $suratJalan->namaAsal();
        $tujuan = $suratJalan->namaTujuan();

        return $this->activity(
            source: 'Surat Jalan',
            entity: 'Surat Jalan',
            title: $suratJalan->nomor,
            description: $asal.' → '.$tujuan,
            status: $this->suratJalanStatus($suratJalan->status),
            occurredAt: $occurredAt,
            url: route('warehouse.transfers.print', $suratJalan),
            id: (int) $suratJalan->id,
        );
    }

    /** @return array{source: string, entity: string, title: string, description: string, status: string, occurred_at: CarbonInterface, url: string, id: int, sort_key: string} */
    private function userActivity(User $user): array
    {
        $created = $this->wasCreated($user);

        return $this->activity(
            source: 'User',
            entity: 'User',
            title: 'User: '.$user->name,
            description: $user->email.($user->mitra?->nama ? ' · '.$user->mitra->nama : ' · THC'),
            status: $created ? 'Dibuat' : 'Diperbarui',
            occurredAt: $user->updated_at ?? $user->created_at ?? CarbonImmutable::now(),
            url: route('admin.users'),
            id: (int) $user->id,
        );
    }

    /** @return array{source: string, entity: string, title: string, description: string, status: string, occurred_at: CarbonInterface, url: string, id: int, sort_key: string} */
    private function mitraActivity(Mitra $mitra): array
    {
        return $this->activity(
            source: 'Mitra',
            entity: 'Mitra',
            title: 'Mitra: '.$mitra->nama,
            description: $mitra->kode,
            status: $this->wasCreated($mitra) ? 'Dibuat' : 'Diperbarui',
            occurredAt: $mitra->updated_at ?? $mitra->created_at ?? CarbonImmutable::now(),
            url: route('admin.mitras'),
            id: (int) $mitra->id,
        );
    }

    private function appendMasterDataActivities(Collection $activities, string $modelClass, string $label, callable $url, int $limit): void
    {
        $modelClass::query()
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->each(function ($record) use ($activities, $label, $url): void {
                $activities->push($this->activity(
                    source: 'Master Data',
                    entity: $label,
                    title: $label.': '.$record->nama,
                    description: $record->kode,
                    status: $this->wasCreated($record) ? 'Dibuat' : 'Diperbarui',
                    occurredAt: $record->updated_at ?? $record->created_at ?? CarbonImmutable::now(),
                    url: $url(),
                    id: (int) $record->id,
                ));
            });
    }

    private function wasCreated(object $record): bool
    {
        return $record->created_at !== null
            && $record->updated_at !== null
            && $record->created_at->equalTo($record->updated_at);
    }

    private function materialRequestStatus(string $status): string
    {
        return [
            'diajukan' => 'Diajukan',
            'disetujui' => 'Disetujui THC',
            'ditolak' => 'Ditolak THC',
            'terpenuhi_sebagian' => 'Terpenuhi sebagian',
            'selesai' => 'Selesai',
            'ditutup' => 'Ditutup THC',
            'dibatalkan' => 'Dibatalkan',
        ][$status] ?? $status;
    }

    private function suratJalanStatus(string $status): string
    {
        return [
            'terbit' => 'Terbit',
            'diterima' => 'Diterima',
            'dibatalkan' => 'Dibatalkan',
        ][$status] ?? $status;
    }

    /** @return array{source: string, entity: string, title: string, description: string, status: string, occurred_at: CarbonInterface, url: string, id: int, sort_key: string} */
    private function activity(
        string $source,
        string $entity,
        string $title,
        string $description,
        string $status,
        CarbonInterface $occurredAt,
        string $url,
        int $id,
    ): array {
        return [
            'source' => $source,
            'entity' => $entity,
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'occurred_at' => $occurredAt,
            'url' => $url,
            'id' => $id,
            'sort_key' => $occurredAt->format('YmdHis.u').sprintf('-%s-%020d', $source, $id),
        ];
    }
}
