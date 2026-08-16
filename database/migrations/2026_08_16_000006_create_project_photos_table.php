<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mitra_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id');
            $table->foreignId('project_step_id');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('original_size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->date('capture_date')->nullable();
            $table->string('sync_status')->default('pending');
            $table->timestamp('synced_at')->nullable();
            $table->text('sync_error')->nullable();
            $table->text('drive_url')->nullable();
            $table->timestamps();
            $table->unique(['id', 'mitra_id']);
            $table->index(['mitra_id', 'project_id', 'project_step_id', 'created_at']);
            $table->foreign(['project_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('projects')
                ->cascadeOnDelete();
            $table->foreign(['project_step_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('project_steps')
                ->cascadeOnDelete();
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE project_photos
                ADD CONSTRAINT project_photos_size_valid CHECK (original_size > 0 AND original_size <= 5242880);
            ALTER TABLE project_photos
                ADD CONSTRAINT project_photos_sync_status_valid CHECK (sync_status IN ('pending', 'synced', 'failed'));
            ALTER TABLE project_photos
                ADD CONSTRAINT project_photos_jpeg_valid CHECK (lower(mime_type) = 'image/jpeg');

            ALTER TABLE project_photos ENABLE ROW LEVEL SECURITY;
            ALTER TABLE project_photos FORCE ROW LEVEL SECURITY;
            CREATE POLICY project_photo_tenant_isolation ON project_photos
                USING (
                    current_setting('app.is_thc', true) = 'on'
                    OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                )
                WITH CHECK (
                    current_setting('app.is_thc', true) = 'on'
                    OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                );

            GRANT SELECT, INSERT, UPDATE ON project_photos TO pms_app;
            GRANT USAGE, SELECT, UPDATE ON SEQUENCE project_photos_id_seq TO pms_app;
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP POLICY IF EXISTS project_photo_tenant_isolation ON project_photos');
        }

        Schema::dropIfExists('project_photos');
    }
};
