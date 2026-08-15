<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mitra_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id');
            $table->string('step');
            $table->unsignedSmallInteger('urutan');
            $table->string('status')->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'step']);
            $table->unique(['id', 'mitra_id']);
            $table->index(['mitra_id', 'project_id', 'urutan']);
            $table->foreign(['project_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('projects')
                ->cascadeOnDelete();
        });

        $steps = [
            ['step' => 'design', 'urutan' => 1],
            ['step' => 'survey', 'urutan' => 2],
            ['step' => 'drm', 'urutan' => 3],
            ['step' => 'spk', 'urutan' => 4],
            ['step' => 'pengadaan_material', 'urutan' => 5],
            ['step' => 'delivery_material', 'urutan' => 6],
            ['step' => 'mos', 'urutan' => 7],
            ['step' => 'deployment', 'urutan' => 8],
            ['step' => 'test_comm', 'urutan' => 9],
            ['step' => 'atp', 'urutan' => 10],
            ['step' => 'go_live', 'urutan' => 11],
        ];
        foreach (DB::table('projects')->select('id', 'mitra_id')->get() as $project) {
            foreach ($steps as $index => $step) {
                DB::table('project_steps')->insert([
                    'mitra_id' => $project->mitra_id,
                    'project_id' => $project->id,
                    'step' => $step['step'],
                    'urutan' => $step['urutan'],
                    'status' => $index === 0 ? 'active' : 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE project_steps
                ADD CONSTRAINT project_steps_status_valid
                CHECK (status IN ('pending', 'active', 'completed'));
            ALTER TABLE project_steps ENABLE ROW LEVEL SECURITY;
            ALTER TABLE project_steps FORCE ROW LEVEL SECURITY;
            CREATE POLICY project_step_tenant_isolation ON project_steps
                USING (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint)
                WITH CHECK (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint);
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS project_step_tenant_isolation ON project_steps');
        }

        Schema::dropIfExists('project_steps');
    }
};
