<?php

namespace App\Services;

use App\Contracts\WahaClient;
use App\Models\Project;
use App\Models\ProjectNotification;
use App\Models\ProjectTimeline;
use App\Models\ProjectTimelineMention;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProjectCommentService
{
    public function __construct(private WahaClient $waha) {}

    /** @param array<int, int|string> $mentionIds */
    public function create(Project $project, User $actor, string $body, bool $internal, array $mentionIds = []): ProjectTimeline
    {
        if ($internal && $actor->mitra_id !== null) {
            throw ValidationException::withMessages(['internal' => 'Komentar Internal hanya dapat dibuat oleh user THC.']);
        }

        return DB::transaction(function () use ($project, $actor, $body, $internal, $mentionIds): ProjectTimeline {
            $targets = $this->mentionTargets($project, $mentionIds, $internal);
            $timeline = ProjectTimeline::query()->create([
                'mitra_id' => $project->mitra_id,
                'project_id' => $project->id,
                'actor_id' => $actor->id,
                'type' => $internal ? 'internal_note' : 'comment',
                'event_key' => $internal ? 'internal_note_created' : 'comment_created',
                'body' => $body,
                'metadata' => ['mention_count' => $targets->count()],
            ]);

            $this->createMentionNotifications($project, $actor, $timeline, $targets);

            return $timeline->load('mentions.user');
        });
    }

    public function edit(Project $project, ProjectTimeline $timeline, User $actor, string $body): ProjectTimeline
    {
        return DB::transaction(function () use ($project, $timeline, $actor, $body): ProjectTimeline {
            $timeline = ProjectTimeline::query()
                ->where('project_id', $project->id)
                ->lockForUpdate()
                ->findOrFail($timeline->id);
            if (! in_array($timeline->type, ['comment', 'internal_note'], true)) {
                throw ValidationException::withMessages(['body' => 'Log sistem tidak dapat diedit sebagai komentar.']);
            }
            if ($timeline->type === 'internal_note' && $actor->mitra_id !== null) {
                throw ValidationException::withMessages(['body' => 'Komentar Internal hanya dapat diedit oleh user THC.']);
            }
            if ($timeline->actor_id !== $actor->id && $actor->mitra_id !== null) {
                throw ValidationException::withMessages(['body' => 'Komentar hanya dapat diedit oleh pembuatnya.']);
            }

            $timeline->update(['body' => $body, 'edited_at' => now()]);

            return $timeline->fresh('mentions.user');
        });
    }

    /** @param array<int, int|string> $mentionIds */
    private function mentionTargets(Project $project, array $mentionIds, bool $internal)
    {
        $ids = collect($mentionIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        $targets = User::query()->whereIn('id', $ids)->where('aktif', true)->get();
        $valid = $targets->filter(function (User $target) use ($project, $internal): bool {
            if ($target->mitra_id !== null && $target->mitra_id !== $project->mitra_id) {
                return false;
            }
            if ($internal && $target->mitra_id !== null) {
                return false;
            }

            return $target->hasIzin('read_project');
        })->values();
        if ($valid->count() !== $ids->count()) {
            throw ValidationException::withMessages(['mentions' => 'User mention tidak memiliki akses ke Project ini.']);
        }

        return $valid;
    }

    private function createMentionNotifications(Project $project, User $actor, ProjectTimeline $timeline, $targets): void
    {
        foreach ($targets as $target) {
            $mention = ProjectTimelineMention::query()->create([
                'mitra_id' => $project->mitra_id,
                'project_id' => $project->id,
                'timeline_id' => $timeline->id,
                'mentioned_user_id' => $target->id,
                'notification_status' => 'pending',
            ]);
            ProjectNotification::query()->create([
                'mitra_id' => $project->mitra_id,
                'project_id' => $project->id,
                'timeline_id' => $timeline->id,
                'user_id' => $target->id,
                'type' => 'project_mention',
                'body' => $actor->name.' menyebut Anda di '.$project->id_project.': '.$timeline->body,
            ]);

            if ($target->no_wa === null || $target->no_wa === '') {
                continue;
            }

            try {
                $this->waha->sendText($target->no_wa, Str::limit($actor->name.' menyebut Anda di '.$project->id_project.': '.$timeline->body, 500).' '.route('projects.show', $project));
                $mention->update(['notification_status' => 'sent', 'notified_at' => now()]);
            } catch (Throwable $exception) {
                $mention->update([
                    'notification_status' => 'failed',
                    'notification_error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
