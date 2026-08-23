<?php

namespace App\Queries;

use App\Models\Drum;
use App\Models\MaterialRequest;
use App\Models\MaterialSn;
use App\Models\Project;
use App\Models\SuratJalanItem;
use App\Models\Warehouse;
use App\Support\QtyTolerance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Kontrak data yang diserialisasi ke halaman Warehouse supaya form Terbitkan Surat Jalan
 * berjalan request-driven tanpa endpoint JSON baru dan tanpa muat ulang halaman.
 *
 * Semua penyaringan berjalan di klien, jadi payload membawa sendiri kunci penyaringnya:
 * Request Material dikelompokkan per gudang tujuan, Project membawa `mitra_id`, dan identitas
 * dikelompokkan per (gudang asal x material).
 */
class SuratJalanFormQuery
{
    /**
     * @param  Collection<int, Warehouse>  $originWarehouses  gudang yang ditugaskan kepada user
     * @param  Collection<int, Warehouse>  $destinationWarehouses  gudang tujuan yang boleh dipilih
     * @param  int|null  $selectedOriginId  gudang asal hasil `old()`; null berarti muat pertama
     * @param  int|null  $selectedDestinationId  gudang tujuan hasil `old()`; null berarti muat pertama
     * @return array{
     *     warehouse_mitra: array<string, int|null>,
     *     initial_origin_id: int|null,
     *     initial_destination_id: int|null,
     *     initial_mitra_id: int|null,
     *     qty_tolerance: float,
     *     terminal_request_id: int|null,
     *     requests: array<string, list<array<string, mixed>>>,
     *     projects: list<array<string, mixed>>,
     *     identities: array<string, array<string, list<array<string, mixed>>>>,
     * }
     */
    public function forOperator(
        Collection $originWarehouses,
        Collection $destinationWarehouses,
        ?int $selectedOriginId,
        ?int $selectedDestinationId,
        ?int $selectedRequestId = null,
    ): array {
        $warehouseMitra = $originWarehouses->concat($destinationWarehouses)
            ->keyBy('id')
            ->mapWithKeys(fn (Warehouse $warehouse, int|string $id): array => [
                (string) $id => $warehouse->mitra_id === null ? null : (int) $warehouse->mitra_id,
            ])
            ->all();
        $initialOriginId = $this->initialWarehouseId($originWarehouses, $selectedOriginId);
        $initialDestinationId = $this->initialWarehouseId($destinationWarehouses, $selectedDestinationId);

        return [
            'warehouse_mitra' => $warehouseMitra,
            'initial_origin_id' => $initialOriginId,
            'initial_destination_id' => $initialDestinationId,
            'initial_mitra_id' => $this->effectiveMitra($warehouseMitra, $initialOriginId, $initialDestinationId),
            'qty_tolerance' => QtyTolerance::VALUE,
            'terminal_request_id' => $this->terminalRequestId(
                $selectedRequestId,
                $this->effectiveMitra($warehouseMitra, $initialOriginId, $initialDestinationId),
            ),
            'requests' => $this->requestsPerDestination($destinationWarehouses),
            'projects' => $this->activeProjects($originWarehouses, $destinationWarehouses),
            'identities' => $this->identitiesPerOrigin($originWarehouses),
        ];
    }

    private function terminalRequestId(?int $requestId, ?int $mitraId): ?int
    {
        if ($requestId === null || $mitraId === null) {
            return null;
        }

        $terminalRequestId = MaterialRequest::query()
            ->whereKey($requestId)
            ->where('mitra_id', $mitraId)
            ->whereIn('status', MaterialRequest::TERMINAL_STATUSES)
            ->value('id');

        return $terminalRequestId === null ? null : (int) $terminalRequestId;
    }

    /**
     * Pilihan awal sebuah dropdown gudang: yang dipulihkan `old()` bila masih ada di daftarnya,
     * kalau tidak yang pertama menurut urutan render.
     *
     * @param  Collection<int, Warehouse>  $warehouses
     */
    private function initialWarehouseId(Collection $warehouses, ?int $selectedId): ?int
    {
        $selected = $selectedId === null ? null : $warehouses->firstWhere('id', $selectedId);
        $initial = $selected ?? $warehouses->first();

        return $initial === null ? null : (int) $initial->id;
    }

    /**
     * Mitra Surat Jalan ditentukan gudang asal, dan jatuh ke gudang tujuan bila asal milik THC.
     * Aturannya dirakit di sini saja dan hasilnya diserahkan lewat payload: render awal oleh
     * Blade dan skrip halaman membacanya, bukan menghitungnya ulang masing-masing.
     *
     * @param  array<string, int|null>  $warehouseMitra
     */
    private function effectiveMitra(array $warehouseMitra, ?int $originId, ?int $destinationId): ?int
    {
        return $warehouseMitra[(string) $originId] ?? $warehouseMitra[(string) $destinationId] ?? null;
    }

    /**
     * "Request yang ditujukan ke suatu gudang" berarti request milik Mitra pemilik gudang itu;
     * `material_requests` tidak punya kolom gudang tujuan. Gudang THC tidak punya Mitra pemilik,
     * jadi daftarnya kosong.
     *
     * @param  Collection<int, Warehouse>  $destinationWarehouses
     * @return array<string, list<array<string, mixed>>>
     */
    private function requestsPerDestination(Collection $destinationWarehouses): array
    {
        $mitraIds = $destinationWarehouses->pluck('mitra_id')->filter()->unique()->values();
        $requests = $mitraIds->isEmpty()
            ? collect()
            : MaterialRequest::query()
                ->with('items.material')
                ->whereIn('mitra_id', $mitraIds)
                ->whereIn('status', MaterialRequest::FULFILLABLE_STATUSES)
                ->orderByDesc('id')
                ->get();
        $sent = $this->sentQuantities($requests->pluck('id')->map(fn ($id): int => (int) $id)->all());

        $serialized = $requests->map(function (MaterialRequest $request) use ($sent): array {
            $items = $request->items
                ->groupBy('material_id')
                ->map(function (Collection $lines, int|string $materialId) use ($sent, $request): array {
                    $diminta = (float) $lines->sum('qty');
                    $terkirim = (float) ($sent[(int) $request->id][(int) $materialId] ?? 0.0);

                    return [
                        'material_id' => (int) $materialId,
                        'jenis' => $lines->first()->material->jenis,
                        'diminta' => $this->quantity($diminta),
                        'terkirim' => $this->quantity($terkirim),
                        'sisa' => $this->quantity(max($diminta - $terkirim, 0.0)),
                    ];
                })
                ->values()
                ->all();

            return [
                'id' => (int) $request->id,
                'mitra_id' => (int) $request->mitra_id,
                'project_id' => $request->project_id === null ? null : (int) $request->project_id,
                'tanggal' => $request->created_at?->toDateString(),
                'status' => $request->status,
                'label' => $this->requestLabel($request, $items),
                'items' => $items,
            ];
        })->groupBy('mitra_id');

        return $destinationWarehouses
            ->mapWithKeys(fn (Warehouse $warehouse): array => [
                (string) $warehouse->id => $warehouse->mitra_id === null
                    ? []
                    : $serialized->get((int) $warehouse->mitra_id, collect())->values()->all(),
            ])
            ->all();
    }

    /**
     * Sisa diukur dengan basis yang sama seperti klasifikator penyimpangan di
     * `SuratJalanService::classifyRequestDeviations()`, supaya yang terlihat operator
     * sama dengan yang dinilai server.
     *
     * @param  list<int>  $requestIds
     * @return array<int, array<int, float>>
     */
    private function sentQuantities(array $requestIds): array
    {
        if ($requestIds === []) {
            return [];
        }

        return SuratJalanItem::query()
            ->join('surat_jalans', 'surat_jalans.id', '=', 'surat_jalan_items.surat_jalan_id')
            ->whereIn('surat_jalans.material_request_id', $requestIds)
            ->where('surat_jalans.status', '!=', 'dibatalkan')
            ->groupBy('surat_jalans.material_request_id', 'surat_jalan_items.material_id')
            ->select(
                'surat_jalans.material_request_id',
                'surat_jalan_items.material_id',
                DB::raw(SuratJalanItem::SENT_QUANTITY.' as qty_sent'),
            )
            ->get()
            ->groupBy('material_request_id')
            ->map(fn (Collection $rows): array => $rows
                ->mapWithKeys(fn ($row): array => [(int) $row->material_id => (float) $row->qty_sent])
                ->all())
            ->all();
    }

    /**
     * Project terikat pada Mitra Surat Jalan (`origin.mitra_id ?? destination.mitra_id`), bukan
     * pada gudang tujuan; itu yang membuat arah mitra ke THC tetap bisa menyebut Project.
     *
     * @param  Collection<int, Warehouse>  $originWarehouses
     * @param  Collection<int, Warehouse>  $destinationWarehouses
     * @return list<array<string, mixed>>
     */
    private function activeProjects(Collection $originWarehouses, Collection $destinationWarehouses): array
    {
        $mitraIds = $originWarehouses->concat($destinationWarehouses)
            ->pluck('mitra_id')
            ->filter()
            ->unique()
            ->values();

        if ($mitraIds->isEmpty()) {
            return [];
        }

        return Project::query()
            ->where('status_project', 'aktif')
            ->whereIn('mitra_id', $mitraIds)
            ->orderByDesc('id_project')
            ->get(['id', 'id_project', 'nama', 'mitra_id'])
            ->map(fn (Project $project): array => [
                'id' => (int) $project->id,
                'id_project' => $project->id_project,
                'nama' => $project->nama,
                'mitra_id' => (int) $project->mitra_id,
                'label' => $project->id_project.' — '.$project->nama,
            ])
            ->all();
    }

    /**
     * Label opsi dirakit di sini, bukan di Blade dan di JS masing-masing: render awal oleh
     * server dan render ulang oleh klien harus mustahil menyimpang satu sama lain.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function requestLabel(MaterialRequest $request, array $items): string
    {
        $belumLengkap = count(array_filter($items, fn (array $item): bool => $item['sisa'] > 0));

        return sprintf(
            '#%d — %s · %d item, %d belum lengkap',
            (int) $request->id,
            $request->created_at?->format('d M Y') ?? '-',
            count($items),
            $belumLengkap,
        );
    }

    /**
     * Identitas yang ditawarkan harus identitas yang benar-benar bisa dikirim: syaratnya sama
     * dengan yang diperiksa `SuratJalanService::createItem()` di gudang asal.
     *
     * @param  Collection<int, Warehouse>  $originWarehouses
     * @return array<string, array<string, list<array<string, mixed>>>>
     */
    private function identitiesPerOrigin(Collection $originWarehouses): array
    {
        $warehouseIds = $originWarehouses->modelKeys();
        if ($warehouseIds === []) {
            return [];
        }

        $serials = MaterialSn::query()
            ->where('status', 'tersedia')
            ->where('lokasi_tipe', 'warehouse')
            ->whereIn('lokasi_id', $warehouseIds)
            ->orderBy('serial_number')
            ->get(['material_id', 'lokasi_id', 'serial_number'])
            ->map(fn (MaterialSn $serial): array => [
                'warehouse_id' => (int) $serial->lokasi_id,
                'material_id' => (int) $serial->material_id,
                'type' => 'sn',
                'value' => $serial->serial_number,
                'sisa' => null,
            ]);
        $drums = Drum::query()
            ->where('lokasi_tipe', 'warehouse')
            ->whereIn('lokasi_id', $warehouseIds)
            ->where('sisa', '>', 0)
            ->orderBy('drum_id')
            ->get(['material_id', 'lokasi_id', 'drum_id', 'sisa'])
            ->map(fn (Drum $drum): array => [
                'warehouse_id' => (int) $drum->lokasi_id,
                'material_id' => (int) $drum->material_id,
                'type' => 'drum',
                'value' => $drum->drum_id,
                'sisa' => $this->quantity((float) $drum->sisa),
            ]);
        $grouped = $serials->concat($drums)->groupBy('warehouse_id');

        return collect($warehouseIds)
            ->mapWithKeys(fn (int $warehouseId): array => [
                (string) $warehouseId => $grouped->get($warehouseId, collect())
                    ->groupBy('material_id')
                    ->mapWithKeys(fn (Collection $identities, int|string $materialId): array => [
                        (string) $materialId => $identities
                            ->map(fn (array $identity): array => [
                                'type' => $identity['type'],
                                'value' => $identity['value'],
                                'sisa' => $identity['sisa'],
                            ])
                            ->values()
                            ->all(),
                    ])
                    ->all(),
            ])
            ->all();
    }

    private function quantity(float $qty): float
    {
        return round($qty, 3);
    }
}
