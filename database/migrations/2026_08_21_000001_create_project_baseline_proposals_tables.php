<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_baseline_proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mitra_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id');
            $table->string('status')->default('diajukan');
            $table->date('toc');
            $table->foreignId('diajukan_oleh')->constrained('users')->restrictOnDelete();
            $table->foreignId('diputuskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diputuskan_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'mitra_id']);
            $table->index(['mitra_id', 'project_id', 'status']);
            $table->foreign(['project_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('projects')
                ->cascadeOnDelete();
        });

        Schema::create('project_baseline_proposal_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mitra_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_baseline_proposal_id');
            $table->date('plan_date');
            $table->decimal('cumulative_percent', 8, 3);
            $table->timestamps();
            $table->index(['mitra_id', 'project_baseline_proposal_id', 'plan_date']);
            $table->unique(['project_baseline_proposal_id', 'plan_date']);
            $table->foreign(['project_baseline_proposal_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('project_baseline_proposals')
                ->cascadeOnDelete();
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE project_baseline_proposals
                ADD CONSTRAINT project_baseline_proposals_status_valid
                CHECK (status IN ('diajukan', 'disetujui', 'ditolak'));
            ALTER TABLE project_baseline_proposal_days
                ADD CONSTRAINT project_baseline_proposal_days_percent_valid
                CHECK (cumulative_percent >= 0 AND cumulative_percent <= 100);

            ALTER TABLE project_baseline_proposals ENABLE ROW LEVEL SECURITY;
            ALTER TABLE project_baseline_proposals FORCE ROW LEVEL SECURITY;
            CREATE POLICY project_baseline_proposal_tenant_isolation ON project_baseline_proposals
                USING (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint)
                WITH CHECK (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint);
            ALTER TABLE project_baseline_proposal_days ENABLE ROW LEVEL SECURITY;
            ALTER TABLE project_baseline_proposal_days FORCE ROW LEVEL SECURITY;
            CREATE POLICY project_baseline_proposal_day_tenant_isolation ON project_baseline_proposal_days
                USING (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint)
                WITH CHECK (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint);
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP POLICY IF EXISTS project_baseline_proposal_day_tenant_isolation ON project_baseline_proposal_days;
                DROP POLICY IF EXISTS project_baseline_proposal_tenant_isolation ON project_baseline_proposals;
            SQL);
        }

        Schema::dropIfExists('project_baseline_proposal_days');
        Schema::dropIfExists('project_baseline_proposals');
    }
};
