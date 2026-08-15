<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_rab_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mitra_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id');
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 18, 3);
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->unique(['id', 'mitra_id']);
            $table->index(['mitra_id', 'project_id', 'material_id']);
            $table->foreign(['project_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('projects')
                ->cascadeOnDelete();
        });

        Schema::table('surat_jalans', function (Blueprint $table): void {
            $table->unsignedBigInteger('project_id')->nullable()->after('material_request_id');
            $table->index(['mitra_id', 'project_id']);
            $table->foreign(['project_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('projects')
                ->nullOnDelete();
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE project_rab_materials
                ADD CONSTRAINT project_rab_materials_qty_positive CHECK (qty > 0);

            ALTER TABLE project_rab_materials ENABLE ROW LEVEL SECURITY;
            ALTER TABLE project_rab_materials FORCE ROW LEVEL SECURITY;
            CREATE POLICY project_rab_material_tenant_isolation ON project_rab_materials
                USING (
                    current_setting('app.is_thc', true) = 'on'
                    OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                )
                WITH CHECK (
                    current_setting('app.is_thc', true) = 'on'
                    OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                );

            GRANT SELECT, INSERT, UPDATE ON project_rab_materials TO pms_app;
            GRANT USAGE, SELECT, UPDATE ON SEQUENCE project_rab_materials_id_seq TO pms_app;
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP POLICY IF EXISTS project_rab_material_tenant_isolation ON project_rab_materials');
        }

        Schema::table('surat_jalans', function (Blueprint $table): void {
            $table->dropForeign(['project_id', 'mitra_id']);
            $table->dropIndex(['mitra_id', 'project_id']);
            $table->dropColumn('project_id');
        });
        Schema::dropIfExists('project_rab_materials');
    }
};
