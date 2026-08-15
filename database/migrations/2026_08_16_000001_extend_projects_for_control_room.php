<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('status')->default('aktif')->after('nama');
            $table->date('toc')->nullable()->after('status');
            $table->index(['mitra_id', 'status']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_status_valid CHECK (status IN ('aktif', 'selesai'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE projects DROP CONSTRAINT IF EXISTS projects_status_valid');
        }

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex(['mitra_id', 'status']);
            $table->dropColumn(['status', 'toc']);
        });
    }
};
