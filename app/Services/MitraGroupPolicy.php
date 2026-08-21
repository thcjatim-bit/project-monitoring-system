<?php

namespace App\Services;

use App\Models\Grup;
use Illuminate\Database\Eloquent\Builder;

class MitraGroupPolicy
{
    /** @var list<string> Capabilities that may be delegated inside one Mitra tenant. */
    private const MITRA_CAPABILITIES = [
        'read_dashboard',
        'read_project',
        'read_project_progress',
        'report_project_progress',
        'update_project_step',
        'read_project_material',
        'upload_project_photo',
        'read_project_timeline',
        'create_project_comment',
        'edit_project_comment',
        'mention_project_user',
        'read_master_data',
        'read_material_request',
        'create_material_request',
        'read_material_usage',
        'create_material_usage',
        'read_material_rekon',
        'read_transit',
        'operate_warehouse',
        'manage_mitra_users',
        'manage_mitra_warehouse',
        'manage_mitra_project',
        'manage_mitra_prices',
    ];

    public function assignableToMitra(): Builder
    {
        return Grup::query()
            ->where(fn (Builder $query) => $query->whereNull('preset')->orWhere('preset', '!=', 'admin_mitra'))
            ->whereDoesntHave('izins', fn (Builder $query) => $query->whereNotIn('kode', self::MITRA_CAPABILITIES));
    }

    public function assertAssignableToMitra(int $groupId): void
    {
        abort_unless(
            $this->assignableToMitra()->whereKey($groupId)->exists(),
            422,
            'Grup tersebut memiliki capability yang tidak dapat didelegasikan kepada User Mitra.',
        );
    }
}
