<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mitra_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('diajukan');
            $table->text('catatan')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
            $table->index(['mitra_id', 'status']);
            $table->unique(['id', 'mitra_id']);
        });

        Schema::create('material_request_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('material_request_id');
            $table->foreignId('mitra_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 18, 3);
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->index(['mitra_id', 'material_request_id']);
            $table->foreign(['material_request_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('material_requests')
                ->cascadeOnDelete();
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE material_requests
                ADD CONSTRAINT material_requests_status_valid
                CHECK (status IN ('diajukan', 'disetujui', 'ditolak', 'terpenuhi_sebagian', 'selesai', 'ditutup', 'dibatalkan'));
            ALTER TABLE material_request_items
                ADD CONSTRAINT material_request_items_qty_positive CHECK (qty > 0);

            ALTER TABLE material_requests ENABLE ROW LEVEL SECURITY;
            ALTER TABLE material_requests FORCE ROW LEVEL SECURITY;
            CREATE POLICY material_request_tenant_isolation ON material_requests
                USING (
                    current_setting('app.is_thc', true) = 'on'
                    OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                )
                WITH CHECK (
                    current_setting('app.is_thc', true) = 'on'
                    OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                );

            ALTER TABLE material_request_items ENABLE ROW LEVEL SECURITY;
            ALTER TABLE material_request_items FORCE ROW LEVEL SECURITY;
            CREATE POLICY material_request_item_tenant_isolation ON material_request_items
                USING (
                    current_setting('app.is_thc', true) = 'on'
                    OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                )
                WITH CHECK (
                    current_setting('app.is_thc', true) = 'on'
                    OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                );

            GRANT SELECT, INSERT, UPDATE ON material_requests, material_request_items TO pms_app;
            GRANT USAGE, SELECT, UPDATE ON SEQUENCE material_requests_id_seq, material_request_items_id_seq TO pms_app;
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP POLICY IF EXISTS material_request_item_tenant_isolation ON material_request_items;
                DROP POLICY IF EXISTS material_request_tenant_isolation ON material_requests;
            SQL);
        }

        Schema::dropIfExists('material_request_items');
        Schema::dropIfExists('material_requests');
    }
};
