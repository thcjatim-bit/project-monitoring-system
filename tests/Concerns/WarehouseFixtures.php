<?php

namespace Tests\Concerns;

use App\Models\Grup;
use App\Models\Izin;
use App\Models\Material;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\Mitra;
use App\Models\Project;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\TenantDatabaseContext;
use Closure;

/**
 * Fixture bersama untuk test yang menyentuh form Terbitkan Surat Jalan: gudang, user,
 * Project, dan Request Material. Ditulis sebagai THC karena data lintas mitra ini
 * dibuat sebelum ada user mitra yang bertindak.
 */
trait WarehouseFixtures
{
    protected function warehouse(?Mitra $mitra, string $kode, ?string $nama = null): Warehouse
    {
        // `mitra_id` selalu diteruskan apa adanya: NULL di sini berarti "gudang milik THC"
        // (ADR-0023), keputusan pemanggil yang tidak boleh diserahkan ke WarehouseFactory.
        // Hanya `nama` yang boleh disaring, karena itu memang minta default factory.
        $attributes = ['mitra_id' => $mitra?->id, 'kode' => $kode, 'aktif' => true];

        if ($nama !== null) {
            $attributes['nama'] = $nama;
        }

        return $this->asThc(fn (): Warehouse => Warehouse::factory()->create($attributes));
    }

    protected function userWith(?Mitra $mitra, string ...$permissions): User
    {
        $group = Grup::factory()->create();
        $group->izins()->attach(collect($permissions)->map(
            fn (string $permission) => Izin::query()->firstOrCreate(['kode' => $permission], ['nama' => $permission])->id,
        )->all());

        return User::factory()->create(['mitra_id' => $mitra?->id, 'grup_id' => $group->id]);
    }

    protected function project(Mitra $mitra, string $idProject, string $status = 'aktif'): Project
    {
        return $this->asThc(fn (): Project => Project::query()->create([
            'id_project' => $idProject,
            'nama' => 'Project '.$idProject,
            'mitra_id' => $mitra->id,
            'status_project' => $status,
        ]));
    }

    /** @param  list<array{0: Material, 1: float|int}>  $lines */
    protected function materialRequest(Mitra $mitra, string $status, array $lines, ?Project $project = null): MaterialRequest
    {
        $requester = $this->userWith($mitra, 'create_material_request');

        return $this->asThc(function () use ($mitra, $status, $lines, $project, $requester): MaterialRequest {
            $request = MaterialRequest::query()->create([
                'mitra_id' => $mitra->id,
                'requested_by' => $requester->id,
                'project_id' => $project?->id,
                'status' => $status,
            ]);
            foreach ($lines as [$material, $qty]) {
                MaterialRequestItem::query()->create([
                    'material_request_id' => $request->id,
                    'mitra_id' => $mitra->id,
                    'material_id' => $material->id,
                    'qty' => $qty,
                ]);
            }

            return $request;
        });
    }

    protected function asThc(Closure $callback): mixed
    {
        app(TenantDatabaseContext::class)->set(null, true);

        try {
            return $callback();
        } finally {
            app(TenantDatabaseContext::class)->set(null, false);
        }
    }
}
