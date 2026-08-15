<?php

namespace App\Queries;

use App\Models\Project;

class ProjectControlRoomQuery
{
    /** @return array{project: Project, kpis: array<string, mixed>, steps: array<int, mixed>, timeline: array<int, mixed>, material: array<string, mixed>} */
    public function for(Project $project): array
    {
        return [
            'project' => $project->loadMissing('mitra'),
            'kpis' => [
                'verified_progress' => null,
                'spi' => null,
                'material_readiness' => null,
            ],
            'steps' => [],
            'timeline' => [],
            'material' => [
                'required' => null,
                'delivered' => null,
                'available' => null,
                'state' => 'empty',
            ],
        ];
    }
}
