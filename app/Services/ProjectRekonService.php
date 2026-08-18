<?php

namespace App\Services;

use App\Models\Drum;
use App\Models\MaterialSn;
use App\Models\MaterialTransaksi;
use App\Models\Project;
use App\Models\ProjectRekon;
use App\Models\ProjectRekonItem;
use App\Models\ProjectTimeline;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProjectRekonService
{
    private const EPSILON = 0.0005;

    /** @return ProjectRekon */
    public function open(Project $project, User $actor, string $source = 'manual'): ProjectRekon
    {
        $this->ensureSource($source);
        if ($source !== 'manual') {
            throw ValidationException::withMessages(['source' => 'Rekon GO Live hanya dapat dibuka oleh lifecycle Step Project.']);
        }
        if ($source === 'manual' && $actor->mitra_id !== null) {
            throw ValidationException::withMessages(['status' => 'Rekon Material manual hanya dapat dibuka oleh user THC.']);
        }

        return DB::transaction(function () use ($project, $actor, $source): ProjectRekon {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            if (ProjectRekon::query()->where('project_id', $project->id)->where('status', 'diajukan')->exists()) {
                throw ValidationException::withMessages(['status' => 'Project sudah memiliki Draft Rekon Material.']);
            }

            return $this->openWithinTransaction($project, $actor, $source);
        });
    }

    public function openForGoLive(Project $project, User $actor): ?ProjectRekon
    {
        return DB::transaction(function () use ($project, $actor): ?ProjectRekon {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            if ($this->activeApproved($project) !== null) {
                return null;
            }
            if (ProjectRekon::query()->where('project_id', $project->id)->where('status', 'diajukan')->exists()) {
                return null;
            }

            return $this->openWithinTransaction($project, $actor, 'go_live');
        });
    }

    /** @param array<int,array{id:int,dikembalikan?:string|int|float,hilang_rusak?:string|int|float,kategori_hilang_rusak?:string|null,penanggung_jawab?:string,catatan?:string|null,keluar_gudang?:string|int|float,terpasang?:string|int|float,sisa_project?:string|int|float}> $items */
    public function updateDraft(ProjectRekon $rekon, User $actor, array $items, ?string $note = null): ProjectRekon
    {
        $this->ensureThc($actor);

        return DB::transaction(function () use ($rekon, $actor, $items, $note): ProjectRekon {
            $rekon = ProjectRekon::query()->lockForUpdate()->findOrFail($rekon->id);
            if ($rekon->status !== 'diajukan') {
                throw ValidationException::withMessages(['status' => 'Hanya Draft Rekon Material yang dapat diubah.']);
            }

            foreach ($items as $data) {
                $item = ProjectRekonItem::query()
                    ->where('project_rekon_id', $rekon->id)
                    ->lockForUpdate()
                    ->find($data['id'] ?? null);
                if ($item === null) {
                    throw ValidationException::withMessages(['items' => 'Baris Rekon Material tidak ditemukan.']);
                }

                $attributes = [];
                foreach (['keluar_gudang', 'terpasang', 'sisa_project', 'dikembalikan', 'hilang_rusak'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $attributes[$field] = $this->formatQuantity($data[$field]);
                    }
                }
                foreach (['kategori_hilang_rusak', 'penanggung_jawab', 'catatan'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $attributes[$field] = $data[$field];
                    }
                }
                $item->update($attributes);
            }

            if ($note !== null) {
                $rekon->update(['catatan' => $note]);
            }
            ProjectTimeline::recordSystem($rekon->project()->firstOrFail(), $actor, 'material_rekon_updated', [
                'project_rekon_id' => $rekon->id,
                'status' => $rekon->status,
            ]);

            return $rekon->fresh('items');
        });
    }

    public function approve(ProjectRekon $rekon, User $actor): ProjectRekon
    {
        $this->ensureThc($actor);

        return DB::transaction(function () use ($rekon, $actor): ProjectRekon {
            $rekon = ProjectRekon::query()->lockForUpdate()->findOrFail($rekon->id);
            if ($rekon->status !== 'diajukan') {
                throw ValidationException::withMessages(['status' => 'Rekon Material sudah diputuskan.']);
            }
            $project = Project::query()->lockForUpdate()->findOrFail($rekon->project_id);
            $items = ProjectRekonItem::query()->with('material')->where('project_rekon_id', $rekon->id)->lockForUpdate()->get();
            $source = $rekon->koreksi_dari_id === null ? null : ProjectRekon::query()->with('items.material')->findOrFail($rekon->koreksi_dari_id);

            if ($source !== null) {
                $active = $this->activeApproved($project);
                if ($active === null || (int) $active->id !== (int) $source->id) {
                    throw ValidationException::withMessages(['status' => 'Rekon Material yang dikoreksi bukan Rekon aktif.']);
                }
            } elseif ($this->activeApproved($project) !== null) {
                throw ValidationException::withMessages(['status' => 'Project sudah memiliki Rekon Material aktif; gunakan koreksi.']);
            }

            $ledger = $this->ledgerTotals($project);
            $oldItems = $source?->items->keyBy(fn (ProjectRekonItem $item): string => $this->itemKey($item)) ?? collect();
            $this->validateItems($project, $items, $ledger, $oldItems);
            $this->applyAccounting($rekon, $items, $oldItems, $actor);

            $rekon->update([
                'status' => 'disetujui',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);
            $project->update(['status_project' => 'selesai']);
            ProjectTimeline::recordSystem($project, $actor, 'material_rekon_approved', [
                'project_rekon_id' => $rekon->id,
                'status' => $rekon->status,
            ]);

            return $rekon->fresh(['items', 'approver']);
        });
    }

    public function reject(ProjectRekon $rekon, User $actor, ?string $note = null): ProjectRekon
    {
        $this->ensureThc($actor);

        return DB::transaction(function () use ($rekon, $actor, $note): ProjectRekon {
            $rekon = ProjectRekon::query()->lockForUpdate()->findOrFail($rekon->id);
            if ($rekon->status !== 'diajukan') {
                throw ValidationException::withMessages(['status' => 'Rekon Material sudah diputuskan.']);
            }

            $rekon->update([
                'status' => 'ditolak',
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'decision_note' => $note,
            ]);
            if ($rekon->koreksi_dari_id !== null) {
                Project::query()->whereKey($rekon->project_id)->update(['status_project' => 'selesai']);
            }
            $project = Project::query()->findOrFail($rekon->project_id);
            ProjectTimeline::recordSystem($project, $actor, 'material_rekon_rejected', [
                'project_rekon_id' => $rekon->id,
                'status' => $rekon->status,
            ]);

            return $rekon->fresh(['items', 'approver']);
        });
    }

    private function openWithinTransaction(Project $project, User $actor, string $source): ProjectRekon
    {
        $correction = $this->activeApproved($project);
        if ($source === 'go_live' && $correction !== null) {
            throw ValidationException::withMessages(['status' => 'GO Live tidak membuka Rekon kedua setelah Rekon aktif disetujui.']);
        }
        if ($correction !== null) {
            $project->update(['status_project' => 'aktif']);
        }

        $openedAt = now();
        $rekon = ProjectRekon::query()->create([
            'nomor' => $this->nextNumber($openedAt->format('ym')),
            'mitra_id' => $project->mitra_id,
            'project_id' => $project->id,
            'koreksi_dari_id' => $correction?->id,
            'source' => $source,
            'status' => 'diajukan',
            'opened_by' => $actor->id,
        ]);

        foreach ($this->draftLines($project, $correction) as $line) {
            ProjectRekonItem::query()->create($line + [
                'mitra_id' => $project->mitra_id,
                'project_rekon_id' => $rekon->id,
            ]);
        }
        ProjectTimeline::recordSystem($project, $actor, 'material_rekon_opened', [
            'project_rekon_id' => $rekon->id,
            'source' => $source,
            'correction_of_id' => $correction?->id,
        ]);

        return $rekon->load('items');
    }

    /** @return array<int,array<string,mixed>> */
    private function draftLines(Project $project, ?ProjectRekon $correction): array
    {
        $ledger = $this->ledgerTotals($project);
        $prior = $correction?->items()->get()->keyBy(fn (ProjectRekonItem $item): string => $this->itemKey($item)) ?? collect();
        $keys = collect(array_keys($ledger['outgoing']))
            ->merge(array_keys($ledger['project']))
            ->merge(array_keys($ledger['installed']))
            ->merge($prior->keys())
            ->unique()
            ->values();

        return $keys->map(function (string $key) use ($ledger, $prior): array {
            $old = $prior->get($key);
            $outgoing = $ledger['outgoing'][$key] ?? (float) ($old?->keluar_gudang ?? 0);
            $installed = $ledger['installed'][$key] ?? (float) ($old?->terpasang ?? 0);

            return [
                'warehouse_id' => $ledger['warehouses'][$key] ?? $old?->warehouse_id,
                'material_id' => $ledger['materials'][$key] ?? $old?->material_id,
                'material_sn_id' => $ledger['serials'][$key] ?? $old?->material_sn_id,
                'drum_id' => $ledger['drums'][$key] ?? $old?->drum_id,
                'keluar_gudang' => $this->formatQuantity($outgoing),
                'terpasang' => $this->formatQuantity($installed),
                'sisa_project' => $this->formatQuantity(max(0, $outgoing - $installed)),
                'dikembalikan' => $this->formatQuantity($old?->dikembalikan ?? 0),
                'hilang_rusak' => $this->formatQuantity($old?->hilang_rusak ?? 0),
                'kategori_hilang_rusak' => $old?->kategori_hilang_rusak,
                'penanggung_jawab' => $old?->penanggung_jawab ?? 'mitra',
                'catatan' => $old?->catatan,
            ];
        })->filter(fn (array $line): bool => $line['material_id'] !== null && $line['warehouse_id'] !== null)->all();
    }

    /** @return array{outgoing:array<string,float>,installed:array<string,float>,project:array<string,float>,warehouses:array<string,int>,materials:array<string,int>,serials:array<string,int|null>,drums:array<string,int|null>,identities:array<string,bool>} */
    private function ledgerTotals(Project $project): array
    {
        $totals = [
            'outgoing' => [],
            'installed' => [],
            'project' => [],
            'warehouses' => [],
            'materials' => [],
            'serials' => [],
            'drums' => [],
            'identities' => [],
        ];
        $transactions = MaterialTransaksi::query()
            ->where('project_id', $project->id)
            ->where(function ($query): void {
                $query->where('lokasi_tipe', 'project')->orWhere('lokasi_tipe', 'terpasang');
            })
            ->get();

        foreach ($transactions as $transaction) {
            $key = $this->transactionKey($transaction);
            $totals['warehouses'][$key] = (int) $transaction->warehouse_id;
            $totals['materials'][$key] = (int) $transaction->material_id;
            $totals['serials'][$key] = $transaction->material_sn_id === null ? null : (int) $transaction->material_sn_id;
            $totals['drums'][$key] = $transaction->drum_id === null ? null : (int) $transaction->drum_id;
            $totals['identities'][$key] = true;

            if ($transaction->jenis_transaksi === 'pemakaian' && $transaction->lokasi_tipe === 'project' && (float) $transaction->qty_delta > 0) {
                $totals['outgoing'][$key] = ($totals['outgoing'][$key] ?? 0) + (float) $transaction->qty_delta;
            }
            if ($transaction->jenis_transaksi === 'terpasang' && $transaction->lokasi_tipe === 'terpasang' && (float) $transaction->qty_delta > 0) {
                $totals['installed'][$key] = ($totals['installed'][$key] ?? 0) + (float) $transaction->qty_delta;
            }
            if ($transaction->lokasi_tipe === 'project') {
                $totals['project'][$key] = ($totals['project'][$key] ?? 0) + (float) $transaction->qty_delta;
            }
        }

        return $totals;
    }

    /** @param array{outgoing:array<string,float>,installed:array<string,float>,project:array<string,float>,warehouses:array<string,int>,materials:array<string,int>,serials:array<string,int|null>,drums:array<string,int|null>,identities:array<string,bool>} $ledger */
    private function validateItems(Project $project, Collection $items, array $ledger, Collection $oldItems): void
    {
        $seen = [];
        foreach ($items as $item) {
            $key = $this->itemKey($item);
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(['items' => 'Material tidak boleh memiliki lebih dari satu baris Rekon Material.']);
            }
            $seen[$key] = true;
            $old = $oldItems->get($key);
            $outgoing = $ledger['outgoing'][$key] ?? (float) ($old?->keluar_gudang ?? 0);
            $installed = $ledger['installed'][$key] ?? (float) ($old?->terpasang ?? 0);
            $projectBalance = $ledger['project'][$key] ?? 0.0;
            $oldAccounted = (float) ($old?->dikembalikan ?? 0) + (float) ($old?->hilang_rusak ?? 0);
            $available = $projectBalance + $oldAccounted;
            $sisa = (float) $item->sisa_project;
            $returned = (float) $item->dikembalikan;
            $lost = (float) $item->hilang_rusak;

            if (abs((float) $item->keluar_gudang - $outgoing) > self::EPSILON) {
                throw ValidationException::withMessages(['items' => 'Qty keluar gudang Rekon Material harus berasal dari Buku transaksi.']);
            }
            if (abs((float) $item->terpasang - $installed) > self::EPSILON) {
                throw ValidationException::withMessages(['items' => 'Qty terpasang Rekon Material harus berasal dari Buku transaksi.']);
            }
            if (abs($sisa - $available) > self::EPSILON) {
                throw ValidationException::withMessages(['items' => 'Sisa Project Rekon Material tidak sesuai saldo material.']);
            }
            if (abs((float) $item->keluar_gudang - $installed - $sisa) > self::EPSILON) {
                throw ValidationException::withMessages(['items' => 'Keluar Gudang harus sama dengan terpasang ditambah sisa Project.']);
            }
            if (abs($returned + $lost - $sisa) > self::EPSILON) {
                throw ValidationException::withMessages(['items' => 'Dikembalikan dan hilang/rusak harus menghabiskan sisa Project.']);
            }
            if ($lost > self::EPSILON && ! in_array($item->kategori_hilang_rusak, ['hilang', 'rusak', 'waste_wajar'], true)) {
                throw ValidationException::withMessages(['items' => 'Kategori hilang/rusak wajib dipilih.']);
            }
            if (! in_array($item->penanggung_jawab, ['mitra', 'thc'], true)) {
                throw ValidationException::withMessages(['items' => 'Penanggung jawab Rekon Material tidak valid.']);
            }
            if ($item->material_sn_id !== null && $item->drum_id !== null) {
                throw ValidationException::withMessages(['items' => 'Satu baris Rekon Material tidak boleh memiliki SN dan Drum sekaligus.']);
            }
            if (($item->material_sn_id !== null || $item->drum_id !== null) && abs($sisa - 1.0) > self::EPSILON && $item->material_sn_id !== null) {
                throw ValidationException::withMessages(['items' => 'Material ber-SN hanya dapat direkonsiliasi satu unit.']);
            }
            if ($item->material_sn_id !== null && $sisa > self::EPSILON
                && abs($returned - $sisa) > self::EPSILON && abs($lost - $sisa) > self::EPSILON) {
                throw ValidationException::withMessages(['items' => 'Material ber-SN harus seluruhnya dikembalikan atau dicatat sebagai hilang/rusak.']);
            }
            if ($item->drum_id !== null && $returned > self::EPSILON && $lost > self::EPSILON) {
                throw ValidationException::withMessages(['items' => 'Sisa satu Drum tidak boleh dibagi antara pengembalian dan hilang/rusak.']);
            }
        }

        foreach (array_unique(array_merge(array_keys($ledger['outgoing']), array_keys($ledger['project']), array_keys($ledger['installed']))) as $key) {
            if (! isset($seen[$key]) && abs(($ledger['outgoing'][$key] ?? 0) + ($ledger['project'][$key] ?? 0) + ($ledger['installed'][$key] ?? 0)) > self::EPSILON) {
                throw ValidationException::withMessages(['items' => 'Semua saldo material Project wajib memiliki baris Rekon Material.']);
            }
        }
    }

    private function applyAccounting(ProjectRekon $rekon, Collection $items, Collection $oldItems, User $actor): void
    {
        foreach ($items as $item) {
            $old = $oldItems->get($this->itemKey($item));
            $oldReturned = (float) ($old?->dikembalikan ?? 0);
            $newReturned = (float) $item->dikembalikan;
            $oldLost = (float) ($old?->hilang_rusak ?? 0);
            $newLost = (float) $item->hilang_rusak;

            if ($newReturned < $oldReturned - self::EPSILON) {
                $this->moveWarehouseToProject($rekon, $item, $oldReturned - $newReturned, $actor);
            }
            if ($newLost < $oldLost - self::EPSILON) {
                $this->moveLossToProject($rekon, $item, $oldLost - $newLost, $actor, $old?->kategori_hilang_rusak);
            }
            if ($oldLost > self::EPSILON && $newLost > self::EPSILON && $old?->kategori_hilang_rusak !== $item->kategori_hilang_rusak) {
                $this->moveLossToProject($rekon, $item, $newLost, $actor, $old?->kategori_hilang_rusak);
                $this->moveProjectToLoss($rekon, $item, $newLost, $actor);
            }
            if ($newReturned > $oldReturned + self::EPSILON) {
                $this->moveProjectToWarehouse($rekon, $item, $newReturned - $oldReturned, $actor);
            }
            if ($newLost > $oldLost + self::EPSILON) {
                $this->moveProjectToLoss($rekon, $item, $newLost - $oldLost, $actor);
            }
        }
    }

    private function moveProjectToWarehouse(ProjectRekon $rekon, ProjectRekonItem $item, float $qty, User $actor): void
    {
        $this->record($rekon, $item, 'project', $rekon->project_id, -$qty, 'rekon_kembali', $actor);
        $this->record($rekon, $item, 'warehouse', $item->warehouse_id, $qty, 'rekon_kembali', $actor);
        $this->setIdentityWarehouse($item);
    }

    private function moveWarehouseToProject(ProjectRekon $rekon, ProjectRekonItem $item, float $qty, User $actor): void
    {
        $this->record($rekon, $item, 'warehouse', $item->warehouse_id, -$qty, 'rekon_kembali', $actor);
        $this->record($rekon, $item, 'project', $rekon->project_id, $qty, 'rekon_kembali', $actor);
        $this->setIdentityProject($item);
    }

    private function moveProjectToLoss(ProjectRekon $rekon, ProjectRekonItem $item, float $qty, User $actor): void
    {
        $category = $item->kategori_hilang_rusak ?? 'hilang';
        $this->record($rekon, $item, 'project', $rekon->project_id, -$qty, $this->lossTransactionType($category), $actor);
        $this->setIdentityLoss($item, $category);
    }

    private function moveLossToProject(ProjectRekon $rekon, ProjectRekonItem $item, float $qty, User $actor, ?string $category): void
    {
        $this->record($rekon, $item, 'project', $rekon->project_id, $qty, $this->lossTransactionType($category ?? 'hilang'), $actor);
        $this->setIdentityProject($item);
    }

    private function record(ProjectRekon $rekon, ProjectRekonItem $item, string $locationType, int $locationId, float $qty, string $transactionType, User $actor): MaterialTransaksi
    {
        return MaterialTransaksi::query()->create([
            'warehouse_id' => $item->warehouse_id,
            'material_id' => $item->material_id,
            'material_sn_id' => $item->material_sn_id,
            'drum_id' => $item->drum_id,
            'project_id' => $rekon->project_id,
            'mitra_id' => $rekon->mitra_id,
            'project_rekon_item_id' => $item->id,
            'jenis_transaksi' => $transactionType,
            'lokasi_tipe' => $locationType,
            'lokasi_id' => $locationId,
            'qty_delta' => $this->formatDelta($qty),
            'reason' => 'Rekon Material '.$rekon->nomor,
            'actor_id' => $actor->id,
        ]);
    }

    private function setIdentityWarehouse(ProjectRekonItem $item): void
    {
        if ($item->material_sn_id !== null) {
            MaterialSn::query()->whereKey($item->material_sn_id)->update([
                'lokasi_tipe' => 'warehouse',
                'lokasi_id' => $item->warehouse_id,
                'project_id' => null,
                'status' => 'tersedia',
            ]);
        }
        if ($item->drum_id !== null) {
            Drum::query()->whereKey($item->drum_id)->update([
                'lokasi_tipe' => 'warehouse',
                'lokasi_id' => $item->warehouse_id,
                'project_id' => null,
            ]);
        }
    }

    private function setIdentityProject(ProjectRekonItem $item): void
    {
        $projectId = $item->rekon()->value('project_id');
        if ($item->material_sn_id !== null) {
            MaterialSn::query()->whereKey($item->material_sn_id)->update([
                'lokasi_tipe' => 'project',
                'lokasi_id' => $projectId,
                'project_id' => $projectId,
                'status' => 'keluar',
            ]);
        }
        if ($item->drum_id !== null) {
            Drum::query()->whereKey($item->drum_id)->update([
                'lokasi_tipe' => 'project',
                'lokasi_id' => $projectId,
                'project_id' => $projectId,
            ]);
        }
    }

    private function setIdentityLoss(ProjectRekonItem $item, string $category): void
    {
        if ($item->material_sn_id !== null) {
            MaterialSn::query()->whereKey($item->material_sn_id)->update([
                'lokasi_tipe' => $category === 'hilang' ? 'hilang' : ($category === 'rusak' ? 'rusak' : 'waste'),
                'lokasi_id' => null,
                'status' => 'hilang',
            ]);
        }
        if ($item->drum_id !== null) {
            Drum::query()->whereKey($item->drum_id)->update([
                'lokasi_tipe' => $category === 'hilang' ? 'hilang' : ($category === 'rusak' ? 'rusak' : 'waste'),
                'lokasi_id' => null,
            ]);
        }
    }

    private function activeApproved(Project $project): ?ProjectRekon
    {
        $approved = ProjectRekon::query()
            ->where('project_id', $project->id)
            ->where('status', 'disetujui')
            ->orderBy('id')
            ->get();
        if ($approved->isEmpty()) {
            return null;
        }

        $correctedIds = $approved->pluck('koreksi_dari_id')->filter()->map(fn ($id): int => (int) $id)->all();

        return $approved
            ->reject(fn (ProjectRekon $rekon): bool => in_array((int) $rekon->id, $correctedIds, true))
            ->sortByDesc('id')
            ->first();
    }

    private function transactionKey(MaterialTransaksi $transaction): string
    {
        return implode(':', [
            (int) $transaction->warehouse_id,
            (int) $transaction->material_id,
            (int) ($transaction->material_sn_id ?? 0),
            (int) ($transaction->drum_id ?? 0),
        ]);
    }

    private function itemKey(ProjectRekonItem $item): string
    {
        return implode(':', [
            (int) $item->warehouse_id,
            (int) $item->material_id,
            (int) ($item->material_sn_id ?? 0),
            (int) ($item->drum_id ?? 0),
        ]);
    }

    private function lossTransactionType(string $category): string
    {
        return match ($category) {
            'hilang' => 'rekon_hilang',
            'rusak' => 'rekon_rusak',
            'waste_wajar' => 'rekon_waste',
            default => throw ValidationException::withMessages(['items' => 'Kategori hilang/rusak tidak valid.']),
        };
    }

    private function nextNumber(string $yearMonth): string
    {
        $prefix = 'REK-'.$yearMonth.'-';
        if (DB::getDriverName() === 'pgsql') {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', [$prefix]);
        }
        DB::table('project_rekon_sequences')->insertOrIgnore([
            'prefix' => $prefix,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('project_rekon_sequences')->where('prefix', $prefix)->increment('last_number');

        return $prefix.str_pad((string) DB::table('project_rekon_sequences')->where('prefix', $prefix)->value('last_number'), 4, '0', STR_PAD_LEFT);
    }

    private function ensureSource(string $source): void
    {
        if (! in_array($source, ['manual', 'go_live'], true)) {
            throw ValidationException::withMessages(['source' => 'Sumber pembukaan Rekon Material tidak valid.']);
        }
    }

    private function ensureThc(User $actor): void
    {
        if ($actor->mitra_id !== null) {
            throw ValidationException::withMessages(['status' => 'Aksi Rekon Material ini hanya dapat dilakukan oleh user THC.']);
        }
    }

    private function formatQuantity(string|int|float $qty): string
    {
        if (! is_numeric($qty) || (float) $qty < 0) {
            throw ValidationException::withMessages(['items' => 'Qty Rekon Material harus berupa angka nol atau lebih.']);
        }

        return number_format((float) $qty, 3, '.', '');
    }

    private function formatDelta(float $qty): string
    {
        return number_format($qty, 3, '.', '');
    }
}
