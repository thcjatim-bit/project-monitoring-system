<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pemakaian_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mitra_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('project_id');
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_sn_id')->nullable()->constrained('material_sns')->restrictOnDelete();
            $table->foreignId('drum_id')->nullable()->constrained('drums')->restrictOnDelete();
            $table->decimal('qty', 18, 3);
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('diajukan');
            $table->text('catatan')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
            $table->unique(['id', 'mitra_id']);
            $table->index(['mitra_id', 'project_id', 'status']);
            $table->foreign(['project_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('projects')
                ->restrictOnDelete();
        });

        Schema::create('project_rekon_sequences', function (Blueprint $table): void {
            $table->string('prefix')->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('project_rekons', function (Blueprint $table): void {
            $table->id();
            $table->string('nomor')->unique();
            $table->foreignId('mitra_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('koreksi_dari_id')->nullable();
            $table->string('source')->default('manual');
            $table->string('status')->default('diajukan');
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('catatan')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
            $table->unique(['id', 'mitra_id']);
            $table->index(['mitra_id', 'project_id', 'status']);
            $table->foreign(['project_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('projects')
                ->restrictOnDelete();
            $table->foreign(['koreksi_dari_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('project_rekons')
                ->nullOnDelete();
        });

        Schema::create('project_rekon_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mitra_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('project_rekon_id');
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_sn_id')->nullable()->constrained('material_sns')->restrictOnDelete();
            $table->foreignId('drum_id')->nullable()->constrained('drums')->restrictOnDelete();
            $table->decimal('keluar_gudang', 18, 3)->default(0);
            $table->decimal('terpasang', 18, 3)->default(0);
            $table->decimal('sisa_project', 18, 3)->default(0);
            $table->decimal('dikembalikan', 18, 3)->default(0);
            $table->decimal('hilang_rusak', 18, 3)->default(0);
            $table->string('kategori_hilang_rusak')->nullable();
            $table->string('penanggung_jawab')->default('mitra');
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->index(['mitra_id', 'project_rekon_id']);
            $table->foreign(['project_rekon_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('project_rekons')
                ->cascadeOnDelete();
        });

        Schema::table('material_transaksis', function (Blueprint $table): void {
            $table->foreignId('pemakaian_material_id')->nullable()->after('surat_jalan_id')
                ->constrained('pemakaian_materials')->nullOnDelete();
            $table->foreignId('project_rekon_item_id')->nullable()->after('pemakaian_material_id')
                ->constrained('project_rekon_items')->nullOnDelete();
            $table->index('pemakaian_material_id');
            $table->index('project_rekon_item_id');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE pemakaian_materials
                ADD CONSTRAINT pemakaian_materials_status_valid
                CHECK (status IN ('diajukan', 'disetujui', 'ditolak', 'dibatalkan'));
            ALTER TABLE pemakaian_materials
                ADD CONSTRAINT pemakaian_materials_qty_positive CHECK (qty > 0);
            ALTER TABLE pemakaian_materials
                ADD CONSTRAINT pemakaian_materials_identity_exclusive
                CHECK (NOT (material_sn_id IS NOT NULL AND drum_id IS NOT NULL));

            ALTER TABLE project_rekons
                ADD CONSTRAINT project_rekons_source_valid CHECK (source IN ('manual', 'go_live'));
            ALTER TABLE project_rekons
                ADD CONSTRAINT project_rekons_status_valid
                CHECK (status IN ('diajukan', 'disetujui', 'ditolak'));
            ALTER TABLE project_rekons
                ADD CONSTRAINT project_rekons_not_self_correction
                CHECK (koreksi_dari_id IS NULL OR koreksi_dari_id <> id);

            ALTER TABLE project_rekon_items
                ADD CONSTRAINT project_rekon_items_quantities_nonnegative CHECK (
                    keluar_gudang >= 0 AND terpasang >= 0 AND sisa_project >= 0
                    AND dikembalikan >= 0 AND hilang_rusak >= 0
                );
            ALTER TABLE project_rekon_items
                ADD CONSTRAINT project_rekon_items_identity_exclusive
                CHECK (NOT (material_sn_id IS NOT NULL AND drum_id IS NOT NULL));
            ALTER TABLE project_rekon_items
                ADD CONSTRAINT project_rekon_items_loss_category_valid CHECK (
                    hilang_rusak = 0 OR kategori_hilang_rusak IN ('hilang', 'rusak', 'waste_wajar')
                );
            ALTER TABLE project_rekon_items
                ADD CONSTRAINT project_rekon_items_responsible_valid
                CHECK (penanggung_jawab IN ('mitra', 'thc'));

            ALTER TABLE material_sns DROP CONSTRAINT IF EXISTS material_sns_location_valid;
            ALTER TABLE material_sns
                ADD CONSTRAINT material_sns_location_valid
                CHECK (lokasi_tipe IN ('warehouse', 'project', 'terpasang', 'transit', 'hilang', 'rusak', 'waste'));
            ALTER TABLE drums DROP CONSTRAINT IF EXISTS drums_location_valid;
            ALTER TABLE drums
                ADD CONSTRAINT drums_location_valid
                CHECK (lokasi_tipe IN ('warehouse', 'project', 'terpasang', 'transit', 'hilang', 'rusak', 'waste'));

            DROP POLICY IF EXISTS material_sn_tenant_isolation ON material_sns;
            CREATE POLICY material_sn_tenant_isolation ON material_sns
                USING (
                    current_setting('app.is_thc', true) = 'on'
                    OR (
                        lokasi_tipe = 'warehouse' AND lokasi_id IN (
                            SELECT id FROM warehouses
                            WHERE mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                        )
                    )
                    OR project_id IN (
                        SELECT id FROM projects
                        WHERE mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                    )
                )
                WITH CHECK (
                    current_setting('app.is_thc', true) = 'on'
                    OR (
                        lokasi_tipe = 'warehouse' AND lokasi_id IN (
                            SELECT id FROM warehouses
                            WHERE mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                        )
                    )
                    OR project_id IN (
                        SELECT id FROM projects
                        WHERE mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                    )
                );

            DROP POLICY IF EXISTS drum_tenant_isolation ON drums;
            CREATE POLICY drum_tenant_isolation ON drums
                USING (
                    current_setting('app.is_thc', true) = 'on'
                    OR (
                        lokasi_tipe = 'warehouse' AND lokasi_id IN (
                            SELECT id FROM warehouses
                            WHERE mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                        )
                    )
                    OR project_id IN (
                        SELECT id FROM projects
                        WHERE mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                    )
                )
                WITH CHECK (
                    current_setting('app.is_thc', true) = 'on'
                    OR (
                        lokasi_tipe = 'warehouse' AND lokasi_id IN (
                            SELECT id FROM warehouses
                            WHERE mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                        )
                    )
                    OR project_id IN (
                        SELECT id FROM projects
                        WHERE mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                    )
                );

            ALTER TABLE pemakaian_materials ENABLE ROW LEVEL SECURITY;
            ALTER TABLE pemakaian_materials FORCE ROW LEVEL SECURITY;
            CREATE POLICY pemakaian_material_tenant_isolation ON pemakaian_materials
                USING (
                    current_setting('app.is_thc', true) = 'on'
                    OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                )
                WITH CHECK (
                    current_setting('app.is_thc', true) = 'on'
                    OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                );

            ALTER TABLE project_rekons ENABLE ROW LEVEL SECURITY;
            ALTER TABLE project_rekons FORCE ROW LEVEL SECURITY;
            CREATE POLICY project_rekon_tenant_isolation ON project_rekons
                USING (
                    current_setting('app.is_thc', true) = 'on'
                    OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                )
                WITH CHECK (
                    current_setting('app.is_thc', true) = 'on'
                    OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                );

            ALTER TABLE project_rekon_items ENABLE ROW LEVEL SECURITY;
            ALTER TABLE project_rekon_items FORCE ROW LEVEL SECURITY;
            CREATE POLICY project_rekon_item_tenant_isolation ON project_rekon_items
                USING (
                    current_setting('app.is_thc', true) = 'on'
                    OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                )
                WITH CHECK (
                    current_setting('app.is_thc', true) = 'on'
                    OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                );

            GRANT SELECT, INSERT, UPDATE ON pemakaian_materials, project_rekons, project_rekon_items TO pms_app;
            GRANT USAGE, SELECT, UPDATE ON SEQUENCE pemakaian_materials_id_seq, project_rekons_id_seq, project_rekon_items_id_seq TO pms_app;
            GRANT SELECT, INSERT, UPDATE ON project_rekon_sequences TO pms_app;
            REVOKE DELETE ON pemakaian_materials, project_rekons, project_rekon_items, project_rekon_sequences FROM pms_app;

            CREATE OR REPLACE FUNCTION validate_material_transaction_identity() RETURNS trigger AS $fn$
            DECLARE material_type text;
            BEGIN
                SELECT jenis INTO material_type FROM materials WHERE id = NEW.material_id;
                IF material_type IS NULL THEN
                    RAISE EXCEPTION 'Material tidak ditemukan';
                END IF;

                IF material_type = 'biasa' AND (NEW.material_sn_id IS NOT NULL OR NEW.drum_id IS NOT NULL) THEN
                    RAISE EXCEPTION 'Material biasa tidak boleh memiliki identitas SN atau drum';
                END IF;

                IF material_type = 'ber_sn' AND (
                    NEW.material_sn_id IS NULL OR NEW.drum_id IS NOT NULL OR abs(NEW.qty_delta) <> 1
                ) THEN
                    RAISE EXCEPTION 'Material ber-SN wajib memiliki satu identitas SN dengan qty 1';
                END IF;

                IF material_type = 'drum_kabel' AND (NEW.drum_id IS NULL OR NEW.material_sn_id IS NOT NULL) THEN
                    RAISE EXCEPTION 'Material drum kabel wajib memiliki identitas drum';
                END IF;

                IF material_type = 'drum_kabel' AND NEW.jenis_transaksi NOT IN (
                    'drum_receive', 'drum_issue', 'drum_split', 'transfer', 'receipt',
                    'hilang_dalam_perjalanan', 'koreksi', 'pemakaian', 'terpasang',
                    'rekon_kembali', 'rekon_hilang', 'rekon_rusak', 'rekon_waste'
                ) THEN
                    RAISE EXCEPTION 'Transaksi drum kabel memiliki jenis transaksi yang tidak valid';
                END IF;

                IF NEW.material_sn_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM material_sns WHERE id = NEW.material_sn_id AND material_id = NEW.material_id
                ) THEN
                    RAISE EXCEPTION 'Identitas SN tidak cocok dengan material';
                END IF;

                IF NEW.drum_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM drums WHERE id = NEW.drum_id AND material_id = NEW.material_id
                ) THEN
                    RAISE EXCEPTION 'Identitas drum tidak cocok dengan material';
                END IF;

                IF NEW.lokasi_id IS NULL THEN
                    NEW.lokasi_id = NEW.warehouse_id;
                END IF;

                RETURN NEW;
            END;
            $fn$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = public, pg_temp;

            CREATE OR REPLACE FUNCTION apply_drum_transaction() RETURNS trigger AS $fn$
            BEGIN
                IF NEW.drum_id IS NOT NULL AND (
                    (NEW.jenis_transaksi IN ('drum_issue', 'drum_split', 'hilang_dalam_perjalanan', 'terpasang') AND NEW.qty_delta < 0)
                    OR NEW.jenis_transaksi IN ('rekon_hilang', 'rekon_rusak', 'rekon_waste')
                ) THEN
                    UPDATE drums
                    SET sisa = sisa + NEW.qty_delta, updated_at = NOW()
                    WHERE id = NEW.drum_id;
                    IF NOT FOUND THEN
                        RAISE EXCEPTION 'Drum tidak ditemukan';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $fn$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = public, pg_temp;
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP POLICY IF EXISTS project_rekon_item_tenant_isolation ON project_rekon_items;
                DROP POLICY IF EXISTS project_rekon_tenant_isolation ON project_rekons;
                DROP POLICY IF EXISTS pemakaian_material_tenant_isolation ON pemakaian_materials;
                ALTER TABLE material_sns DROP CONSTRAINT IF EXISTS material_sns_location_valid;
                ALTER TABLE material_sns
                    ADD CONSTRAINT material_sns_location_valid CHECK (lokasi_tipe IN ('warehouse', 'project', 'terpasang', 'transit'));
                ALTER TABLE drums DROP CONSTRAINT IF EXISTS drums_location_valid;
                ALTER TABLE drums
                    ADD CONSTRAINT drums_location_valid CHECK (lokasi_tipe IN ('warehouse', 'project', 'terpasang', 'transit'));
                DROP POLICY IF EXISTS material_sn_tenant_isolation ON material_sns;
                CREATE POLICY material_sn_tenant_isolation ON material_sns
                    USING (current_setting('app.is_thc', true) = 'on' OR (
                        lokasi_tipe = 'warehouse' AND lokasi_id IN (
                            SELECT id FROM warehouses WHERE mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                        )
                    ))
                    WITH CHECK (current_setting('app.is_thc', true) = 'on' OR (
                        lokasi_tipe = 'warehouse' AND lokasi_id IN (
                            SELECT id FROM warehouses WHERE mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                        )
                    ));
                DROP POLICY IF EXISTS drum_tenant_isolation ON drums;
                CREATE POLICY drum_tenant_isolation ON drums
                    USING (current_setting('app.is_thc', true) = 'on' OR (
                        lokasi_tipe = 'warehouse' AND lokasi_id IN (
                            SELECT id FROM warehouses WHERE mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                        )
                    ))
                    WITH CHECK (current_setting('app.is_thc', true) = 'on' OR (
                        lokasi_tipe = 'warehouse' AND lokasi_id IN (
                            SELECT id FROM warehouses WHERE mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                        )
                    ));
            SQL);
        }

        Schema::table('material_transaksis', function (Blueprint $table): void {
            $table->dropForeign(['pemakaian_material_id']);
            $table->dropForeign(['project_rekon_item_id']);
            $table->dropIndex(['pemakaian_material_id']);
            $table->dropIndex(['project_rekon_item_id']);
            $table->dropColumn(['pemakaian_material_id', 'project_rekon_item_id']);
        });
        Schema::dropIfExists('project_rekon_items');
        Schema::dropIfExists('project_rekons');
        Schema::dropIfExists('project_rekon_sequences');
        Schema::dropIfExists('pemakaian_materials');
    }
};
