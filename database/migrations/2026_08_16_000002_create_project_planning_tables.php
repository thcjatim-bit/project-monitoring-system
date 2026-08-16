<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mitra_id')->constrained()->restrictOnDelete();
            $table->string('nomor')->unique();
            $table->date('tanggal_mulai');
            $table->date('tanggal_berakhir')->nullable();
            $table->string('lampiran_path')->nullable();
            $table->timestamps();
            $table->unique(['id', 'mitra_id']);
            $table->index(['mitra_id', 'tanggal_mulai', 'tanggal_berakhir']);
        });

        Schema::create('mitra_harga_jasas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mitra_id')->constrained()->restrictOnDelete();
            $table->foreignId('pks_id');
            $table->foreignId('pekerjaan_jasa_id')->constrained()->restrictOnDelete();
            $table->decimal('harga', 18, 2);
            $table->string('status')->default('diajukan');
            $table->date('berlaku_mulai');
            $table->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('diputuskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diputuskan_at')->nullable();
            $table->foreignId('revisi_dari_id')->nullable()->constrained('mitra_harga_jasas')->nullOnDelete();
            $table->timestamps();
            $table->unique(['id', 'mitra_id']);
            $table->index(['mitra_id', 'status', 'berlaku_mulai']);
            $table->foreign(['pks_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('pks')
                ->restrictOnDelete();
        });

        Schema::create('project_variation_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mitra_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id');
            $table->string('nomor')->unique();
            $table->string('status')->default('draft');
            $table->text('alasan');
            $table->foreignId('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'mitra_id']);
            $table->index(['mitra_id', 'project_id', 'status']);
            $table->foreign(['project_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('projects')
                ->cascadeOnDelete();
        });

        Schema::create('project_rab_jasas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mitra_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id');
            $table->foreignId('pekerjaan_jasa_id')->constrained()->restrictOnDelete();
            $table->foreignId('harga_jasa_mitra_id')->constrained('mitra_harga_jasas')->restrictOnDelete();
            $table->foreignId('variation_order_id')->nullable()->constrained('project_variation_orders')->nullOnDelete();
            $table->decimal('qty', 18, 3);
            $table->decimal('harga_satuan', 18, 2);
            $table->decimal('total_nilai', 20, 2);
            $table->foreignId('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['id', 'mitra_id']);
            $table->index(['mitra_id', 'project_id']);
            $table->foreign(['project_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('projects')
                ->cascadeOnDelete();
            $table->foreign(['harga_jasa_mitra_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('mitra_harga_jasas')
                ->restrictOnDelete();
        });

        Schema::create('project_variation_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mitra_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_variation_order_id');
            $table->foreignId('rab_jasa_id')->nullable();
            $table->foreignId('pekerjaan_jasa_id')->constrained()->restrictOnDelete();
            $table->foreignId('harga_jasa_mitra_id')->nullable()->constrained('mitra_harga_jasas')->nullOnDelete();
            $table->decimal('quantity_delta', 18, 3);
            $table->decimal('harga_satuan', 18, 2);
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->index(['mitra_id', 'project_variation_order_id']);
            $table->foreign(['project_variation_order_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('project_variation_orders')
                ->cascadeOnDelete();
            $table->foreign(['rab_jasa_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('project_rab_jasas')
                ->restrictOnDelete();
        });

        Schema::create('project_baselines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mitra_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id');
            $table->string('kind');
            $table->unsignedInteger('version')->default(1);
            $table->date('toc');
            $table->foreignId('supersedes_id')->nullable()->constrained('project_baselines')->nullOnDelete();
            $table->foreignId('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['id', 'mitra_id']);
            $table->index(['mitra_id', 'project_id', 'kind', 'version']);
            $table->foreign(['project_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('projects')
                ->cascadeOnDelete();
        });

        Schema::create('project_baseline_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mitra_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_baseline_id');
            $table->date('plan_date');
            $table->decimal('cumulative_percent', 8, 3);
            $table->timestamps();
            $table->index(['mitra_id', 'project_baseline_id', 'plan_date']);
            $table->unique(['project_baseline_id', 'plan_date']);
            $table->foreign(['project_baseline_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('project_baselines')
                ->cascadeOnDelete();
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE mitra_harga_jasas
                ADD CONSTRAINT mitra_harga_jasas_status_valid
                CHECK (status IN ('diajukan', 'disetujui', 'ditolak'));
            ALTER TABLE mitra_harga_jasas
                ADD CONSTRAINT mitra_harga_jasas_harga_positive CHECK (harga > 0);
            ALTER TABLE project_variation_orders
                ADD CONSTRAINT project_variation_orders_status_valid
                CHECK (status IN ('draft', 'approved', 'rejected'));
            ALTER TABLE project_rab_jasas
                ADD CONSTRAINT project_rab_jasas_qty_positive CHECK (qty > 0);
            ALTER TABLE project_rab_jasas
                ADD CONSTRAINT project_rab_jasas_total_nonnegative CHECK (total_nilai >= 0);
            ALTER TABLE project_variation_order_items
                ADD CONSTRAINT project_variation_order_items_delta_nonzero CHECK (quantity_delta <> 0);
            ALTER TABLE project_variation_order_items
                ADD CONSTRAINT project_variation_order_items_status_valid
                CHECK (status IN ('pending', 'applied', 'rejected'));
            ALTER TABLE project_baselines
                ADD CONSTRAINT project_baselines_kind_valid CHECK (kind IN ('original', 'revised'));
            ALTER TABLE project_baseline_days
                ADD CONSTRAINT project_baseline_days_percent_valid CHECK (cumulative_percent >= 0 AND cumulative_percent <= 100);

            ALTER TABLE pks ENABLE ROW LEVEL SECURITY;
            ALTER TABLE pks FORCE ROW LEVEL SECURITY;
            CREATE POLICY pks_tenant_isolation ON pks
                USING (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint)
                WITH CHECK (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint);
            ALTER TABLE mitra_harga_jasas ENABLE ROW LEVEL SECURITY;
            ALTER TABLE mitra_harga_jasas FORCE ROW LEVEL SECURITY;
            CREATE POLICY mitra_harga_jasa_tenant_isolation ON mitra_harga_jasas
                USING (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint)
                WITH CHECK (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint);
            ALTER TABLE project_variation_orders ENABLE ROW LEVEL SECURITY;
            ALTER TABLE project_variation_orders FORCE ROW LEVEL SECURITY;
            CREATE POLICY project_variation_order_tenant_isolation ON project_variation_orders
                USING (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint)
                WITH CHECK (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint);
            ALTER TABLE project_rab_jasas ENABLE ROW LEVEL SECURITY;
            ALTER TABLE project_rab_jasas FORCE ROW LEVEL SECURITY;
            CREATE POLICY project_rab_jasa_tenant_isolation ON project_rab_jasas
                USING (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint)
                WITH CHECK (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint);
            ALTER TABLE project_variation_order_items ENABLE ROW LEVEL SECURITY;
            ALTER TABLE project_variation_order_items FORCE ROW LEVEL SECURITY;
            CREATE POLICY project_variation_order_item_tenant_isolation ON project_variation_order_items
                USING (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint)
                WITH CHECK (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint);
            ALTER TABLE project_baselines ENABLE ROW LEVEL SECURITY;
            ALTER TABLE project_baselines FORCE ROW LEVEL SECURITY;
            CREATE POLICY project_baseline_tenant_isolation ON project_baselines
                USING (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint)
                WITH CHECK (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint);
            ALTER TABLE project_baseline_days ENABLE ROW LEVEL SECURITY;
            ALTER TABLE project_baseline_days FORCE ROW LEVEL SECURITY;
            CREATE POLICY project_baseline_day_tenant_isolation ON project_baseline_days
                USING (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint)
                WITH CHECK (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint);
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP POLICY IF EXISTS project_baseline_day_tenant_isolation ON project_baseline_days;
                DROP POLICY IF EXISTS project_baseline_tenant_isolation ON project_baselines;
                DROP POLICY IF EXISTS project_variation_order_item_tenant_isolation ON project_variation_order_items;
                DROP POLICY IF EXISTS project_rab_jasa_tenant_isolation ON project_rab_jasas;
                DROP POLICY IF EXISTS project_variation_order_tenant_isolation ON project_variation_orders;
                DROP POLICY IF EXISTS mitra_harga_jasa_tenant_isolation ON mitra_harga_jasas;
                DROP POLICY IF EXISTS pks_tenant_isolation ON pks;
            SQL);
        }

        Schema::dropIfExists('project_baseline_days');
        Schema::dropIfExists('project_baselines');
        Schema::dropIfExists('project_variation_order_items');
        Schema::dropIfExists('project_rab_jasas');
        Schema::dropIfExists('project_variation_orders');
        Schema::dropIfExists('mitra_harga_jasas');
        Schema::dropIfExists('pks');
    }
};
