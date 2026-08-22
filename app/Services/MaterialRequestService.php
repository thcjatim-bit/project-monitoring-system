<?php

namespace App\Services;

use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaterialRequestService
{
    /** @param array{project_id?: int|null, catatan?: string|null, items: array<int, array{material_id: int, qty: string|int|float, catatan?: string|null}>} $data */
    public function submit(User $actor, array $data): MaterialRequest
    {
        if ($actor->mitra_id === null) {
            throw ValidationException::withMessages(['mitra_id' => 'Hanya User Mitra yang dapat mengajukan Request Material.']);
        }

        return DB::transaction(function () use ($actor, $data): MaterialRequest {
            $request = MaterialRequest::query()->create([
                'mitra_id' => $actor->mitra_id,
                'project_id' => $data['project_id'] ?? null,
                'requested_by' => $actor->id,
                'status' => 'diajukan',
                'catatan' => $data['catatan'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                MaterialRequestItem::query()->create([
                    'material_request_id' => $request->id,
                    'mitra_id' => $actor->mitra_id,
                    'material_id' => $item['material_id'],
                    'qty' => $item['qty'],
                    'catatan' => $item['catatan'] ?? null,
                ]);
            }

            return $request->load('items');
        });
    }

    public function close(MaterialRequest $request, User $actor, string $note): MaterialRequest
    {
        if ($actor->mitra_id !== null) {
            throw ValidationException::withMessages(['status' => 'Hanya THC yang dapat menutup Request Material.']);
        }

        return DB::transaction(function () use ($request, $actor, $note): MaterialRequest {
            $request = MaterialRequest::query()->lockForUpdate()->findOrFail($request->id);
            if (! in_array($request->status, ['disetujui', 'terpenuhi_sebagian'], true)) {
                throw ValidationException::withMessages(['status' => 'Hanya Request Material yang disetujui atau terpenuhi sebagian dapat ditutup.']);
            }

            $request->update([
                'status' => 'ditutup',
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);

            return $request->fresh(['items', 'decider']);
        });
    }

    public function decide(MaterialRequest $request, User $actor, string $status, ?string $note): MaterialRequest
    {
        if (! in_array($status, ['disetujui', 'ditolak'], true)) {
            throw new \InvalidArgumentException('Keputusan Request Material tidak valid.');
        }

        return DB::transaction(function () use ($request, $actor, $status, $note): MaterialRequest {
            $request = MaterialRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($request->status !== 'diajukan') {
                throw ValidationException::withMessages(['status' => 'Request Material sudah memiliki keputusan.']);
            }

            $request->update([
                'status' => $status,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);

            return $request->fresh(['items', 'decider']);
        });
    }
}
