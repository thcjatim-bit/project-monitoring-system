<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('izins')->insertOrIgnore([
            'kode' => 'read_project_progress',
            'nama' => 'Melihat Progres Project',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $groupId = DB::table('grups')->where('preset', 'admin_thc')->value('id');
        $permissionId = DB::table('izins')->where('kode', 'read_project_progress')->value('id');

        if ($groupId === null || $permissionId === null) {
            return;
        }

        DB::table('grup_izin')->insertOrIgnore([
            'grup_id' => $groupId,
            'izin_id' => $permissionId,
        ]);
    }

    public function down(): void
    {
        $groupId = DB::table('grups')->where('preset', 'admin_thc')->value('id');
        $permissionId = DB::table('izins')->where('kode', 'read_project_progress')->value('id');

        if ($groupId === null || $permissionId === null) {
            return;
        }

        DB::table('grup_izin')
            ->where('grup_id', $groupId)
            ->where('izin_id', $permissionId)
            ->delete();
    }
};
