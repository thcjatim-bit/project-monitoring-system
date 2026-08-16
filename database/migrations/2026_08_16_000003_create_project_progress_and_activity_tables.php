<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_timelines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mitra_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('system_log');
            $table->string('event_key')->nullable();
            $table->text('body')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'mitra_id']);
            $table->index(['mitra_id', 'project_id', 'created_at']);
            $table->foreign(['project_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('projects')
                ->cascadeOnDelete();
        });

        Schema::create('project_progresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mitra_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id');
            $table->foreignId('project_rab_jasa_id');
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->date('actual_date');
            $table->decimal('qty', 18, 3);
            $table->string('status')->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_note')->nullable();
            $table->timestamps();
            $table->unique(['id', 'mitra_id']);
            $table->index(['mitra_id', 'project_id', 'status', 'actual_date']);
            $table->foreign(['project_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('projects')
                ->cascadeOnDelete();
            $table->foreign(['project_rab_jasa_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('project_rab_jasas')
                ->restrictOnDelete();
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE project_timelines
                ADD CONSTRAINT project_timelines_type_valid
                CHECK (type IN ('system_log', 'comment', 'internal_note'));
            ALTER TABLE project_progresses
                ADD CONSTRAINT project_progresses_status_valid
                CHECK (status IN ('pending', 'verified', 'rejected'));
            ALTER TABLE project_progresses
                ADD CONSTRAINT project_progresses_qty_positive CHECK (qty > 0);

            ALTER TABLE project_timelines ENABLE ROW LEVEL SECURITY;
            ALTER TABLE project_timelines FORCE ROW LEVEL SECURITY;
            CREATE POLICY project_timeline_tenant_isolation ON project_timelines
                USING (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint)
                WITH CHECK (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint);
            ALTER TABLE project_progresses ENABLE ROW LEVEL SECURITY;
            ALTER TABLE project_progresses FORCE ROW LEVEL SECURITY;
            CREATE POLICY project_progress_tenant_isolation ON project_progresses
                USING (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint)
                WITH CHECK (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint);
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP POLICY IF EXISTS project_progress_tenant_isolation ON project_progresses;
                DROP POLICY IF EXISTS project_timeline_tenant_isolation ON project_timelines;
            SQL);
        }

        Schema::dropIfExists('project_progresses');
        Schema::dropIfExists('project_timelines');
    }
};
