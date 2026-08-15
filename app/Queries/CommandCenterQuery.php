<?php

namespace App\Queries;

use App\Models\MaterialRequest;
use Illuminate\Database\Eloquent\Collection;

class CommandCenterQuery
{
    /** @return Collection<int, MaterialRequest> */
    public function pendingMaterialRequests(): Collection
    {
        return MaterialRequest::query()
            ->where('status', 'diajukan')
            ->with(['mitra', 'items.material.unit'])
            ->latest()
            ->get();
    }
}
