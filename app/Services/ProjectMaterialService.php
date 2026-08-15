<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectRabMaterial;
use App\Models\ProjectTimeline;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProjectMaterialService
{
    /** @param array{material_id:int,qty:string|int|float,catatan?:string|null} $data */
    public function addRequirement(Project $project, User $actor, array $data): ProjectRabMaterial
    {
        return DB::transaction(function () use ($project, $actor, $data): ProjectRabMaterial {
            $requirement = ProjectRabMaterial::query()->create([
                'mitra_id' => $project->mitra_id,
                'project_id' => $project->id,
                'material_id' => $data['material_id'],
                'qty' => $data['qty'],
                'catatan' => $data['catatan'] ?? null,
            ]);

            ProjectTimeline::recordSystem($project, $actor, 'rab_material_added', [
                'project_rab_material_id' => $requirement->id,
                'material_id' => $requirement->material_id,
                'qty' => $requirement->qty,
            ]);

            return $requirement->load('material.unit');
        });
    }
}
