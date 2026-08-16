<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_timeline_mentions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mitra_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id');
            $table->foreignId('timeline_id');
            $table->foreignId('mentioned_user_id')->constrained('users')->restrictOnDelete();
            $table->string('notification_status')->default('pending');
            $table->timestamp('notified_at')->nullable();
            $table->text('notification_error')->nullable();
            $table->timestamps();
            $table->unique(['timeline_id', 'mentioned_user_id', 'mitra_id']);
            $table->unique(['id', 'mitra_id']);
            $table->index(['mitra_id', 'project_id', 'mentioned_user_id']);
            $table->foreign(['project_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('projects')
                ->cascadeOnDelete();
            $table->foreign(['timeline_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('project_timelines')
                ->cascadeOnDelete();
        });

        Schema::create('project_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mitra_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id');
            $table->foreignId('timeline_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'mitra_id']);
            $table->index(['mitra_id', 'user_id', 'read_at']);
            $table->foreign(['project_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('projects')
                ->cascadeOnDelete();
            $table->foreign(['timeline_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('project_timelines')
                ->cascadeOnDelete();
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE project_timeline_mentions
                ADD CONSTRAINT project_timeline_mentions_status_valid
                CHECK (notification_status IN ('pending', 'sent', 'failed'));

            ALTER TABLE project_timeline_mentions ENABLE ROW LEVEL SECURITY;
            ALTER TABLE project_timeline_mentions FORCE ROW LEVEL SECURITY;
            CREATE POLICY project_timeline_mention_tenant_isolation ON project_timeline_mentions
                USING (
                    current_setting('app.is_thc', true) = 'on'
                    OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                )
                WITH CHECK (
                    current_setting('app.is_thc', true) = 'on'
                    OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                );

            ALTER TABLE project_notifications ENABLE ROW LEVEL SECURITY;
            ALTER TABLE project_notifications FORCE ROW LEVEL SECURITY;
            CREATE POLICY project_notification_tenant_isolation ON project_notifications
                USING (
                    current_setting('app.is_thc', true) = 'on'
                    OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                )
                WITH CHECK (
                    current_setting('app.is_thc', true) = 'on'
                    OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                );

            GRANT SELECT, INSERT, UPDATE ON project_timeline_mentions, project_notifications TO pms_app;
            GRANT USAGE, SELECT, UPDATE ON SEQUENCE project_timeline_mentions_id_seq, project_notifications_id_seq TO pms_app;
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP POLICY IF EXISTS project_notification_tenant_isolation ON project_notifications;
                DROP POLICY IF EXISTS project_timeline_mention_tenant_isolation ON project_timeline_mentions;
            SQL);
        }

        Schema::dropIfExists('project_notifications');
        Schema::dropIfExists('project_timeline_mentions');
    }
};
