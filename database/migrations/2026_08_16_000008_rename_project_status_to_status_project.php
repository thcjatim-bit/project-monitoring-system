<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropStatusConstraintAndIndex('status');

        Schema::table('projects', function (Blueprint $table): void {
            $table->renameColumn('status', 'status_project');
        });

        $this->addStatusConstraintAndIndex('status_project');
    }

    public function down(): void
    {
        $this->dropStatusConstraintAndIndex('status_project');

        Schema::table('projects', function (Blueprint $table): void {
            $table->renameColumn('status_project', 'status');
        });

        $this->addStatusConstraintAndIndex('status');
    }

    private function dropStatusConstraintAndIndex(string $column): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE projects DROP CONSTRAINT IF EXISTS projects_status_valid');
            DB::statement('ALTER TABLE projects DROP CONSTRAINT IF EXISTS projects_status_project_valid');
            DB::statement("DROP INDEX IF EXISTS projects_mitra_id_{$column}_index");

            return;
        }

        Schema::table('projects', function (Blueprint $table) use ($column): void {
            $table->dropIndex(['mitra_id', $column]);
        });
    }

    private function addStatusConstraintAndIndex(string $column): void
    {
        Schema::table('projects', function (Blueprint $table) use ($column): void {
            $table->index(['mitra_id', $column]);
        });

        if (DB::getDriverName() === 'pgsql') {
            $constraint = $column === 'status_project' ? 'projects_status_project_valid' : 'projects_status_valid';
            DB::statement("ALTER TABLE projects ADD CONSTRAINT {$constraint} CHECK ({$column} IN ('aktif', 'selesai'))");
        }
    }
};
