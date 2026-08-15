<?php

namespace App\Queries;

use App\Models\Project;
use App\Models\ProjectTimeline;
use App\Models\User;
use Illuminate\Support\Collection;

class ProjectTimelineQuery
{
    public function for(Project $project, User $viewer, int $limit = 100): Collection
    {
        return ProjectTimeline::query()
            ->with(['actor', 'mentions.user'])
            ->where('project_id', $project->id)
            ->when($viewer->mitra_id !== null, fn ($query) => $query->where('type', '!=', 'internal_note'))
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    public function mentionableUsers(Project $project): Collection
    {
        return User::query()
            ->with('grup')
            ->where('aktif', true)
            ->where(function ($query) use ($project): void {
                $query->whereNull('mitra_id')->orWhere('mitra_id', $project->mitra_id);
            })
            ->whereHas('grup.izins', fn ($query) => $query->where('kode', 'read_project'))
            ->orderBy('name')
            ->get();
    }
}
