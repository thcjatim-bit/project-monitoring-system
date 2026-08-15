<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_jalans', function (Blueprint $table): void {
            $table->id();
            $table->string('nomor')->unique();
            $table->date('tanggal');
            $table->foreignId('warehouse_asal_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('warehouse_tujuan_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('mitra_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at');
            $table->string('status')->default('terbit');
            $table->string('pengirim');
            $table->string('sopir')->nullable();
            $table->string('plat_nomor')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->index(['mitra_id', 'status']);
        });

        Schema::create('surat_jalan_sequences', function (Blueprint $table): void {
            $table->string('prefix')->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('surat_jalan_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('surat_jalan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mitra_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_sn_id')->nullable()->constrained('material_sns')->restrictOnDelete();
            $table->foreignId('drum_id')->nullable()->constrained('drums')->restrictOnDelete();
            $table->decimal('qty', 18, 3);
            $table->timestamps();
            $table->index(['mitra_id', 'surat_jalan_id']);
        });

        Schema::table('material_stoks', function (Blueprint $table): void {
            $table->foreignId('mitra_id')->nullable()->after('material_id')->constrained()->nullOnDelete();
        });

        Schema::table('material_sns', function (Blueprint $table): void {
            $table->foreignId('mitra_id')->nullable()->after('material_id')->constrained()->nullOnDelete();
        });

        Schema::table('drums', function (Blueprint $table): void {
            $table->foreignId('mitra_id')->nullable()->after('material_id')->constrained()->nullOnDelete();
        });

        Schema::table('material_transaksis', function (Blueprint $table): void {
            $table->foreignId('surat_jalan_id')->nullable()->after('mitra_id')->constrained()->nullOnDelete();
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            UPDATE material_stoks
            SET lokasi_id = COALESCE(lokasi_id, warehouse_id)
            WHERE lokasi_tipe = 'warehouse';

            UPDATE material_stoks s
            SET mitra_id = w.mitra_id
            FROM warehouses w
            WHERE s.warehouse_id = w.id AND s.mitra_id IS NULL AND s.lokasi_tipe = 'warehouse';

            UPDATE material_sns s
            SET mitra_id = w.mitra_id
            FROM warehouses w
            WHERE s.lokasi_tipe = 'warehouse' AND s.lokasi_id = w.id;

            UPDATE drums d
            SET mitra_id = w.mitra_id
            FROM warehouses w
            WHERE d.lokasi_tipe = 'warehouse' AND d.lokasi_id = w.id;

            ALTER TABLE material_stoks DROP CONSTRAINT material_stoks_warehouse_id_material_id_unique;
            CREATE UNIQUE INDEX material_stoks_location_unique
                ON material_stoks (warehouse_id, material_id, lokasi_tipe, lokasi_id);

            ALTER TABLE surat_jalans
                ADD CONSTRAINT surat_jalans_status_valid
                CHECK (status IN ('terbit', 'diterima', 'dibatalkan'));
            ALTER TABLE surat_jalans
                ADD CONSTRAINT surat_jalans_distinct_warehouses
                CHECK (warehouse_asal_id <> warehouse_tujuan_id);
            ALTER TABLE surat_jalan_items
                ADD CONSTRAINT surat_jalan_items_qty_positive CHECK (qty > 0);

            CREATE OR REPLACE FUNCTION apply_material_transaction() RETURNS trigger AS $fn$
            DECLARE next_qty numeric(18,3);
            DECLARE stock_mitra_id bigint;
            BEGIN
                IF NEW.lokasi_id IS NULL THEN
                    NEW.lokasi_id = NEW.warehouse_id;
                END IF;

                stock_mitra_id := NEW.mitra_id;
                IF NEW.lokasi_tipe = 'warehouse' THEN
                    SELECT mitra_id INTO stock_mitra_id FROM warehouses WHERE id = NEW.warehouse_id;
                END IF;

                IF NEW.qty_delta >= 0 THEN
                    INSERT INTO material_stoks (warehouse_id, material_id, mitra_id, lokasi_tipe, lokasi_id, qty, created_at, updated_at)
                    VALUES (NEW.warehouse_id, NEW.material_id, stock_mitra_id, NEW.lokasi_tipe, NEW.lokasi_id, NEW.qty_delta, NOW(), NOW())
                    ON CONFLICT (warehouse_id, material_id, lokasi_tipe, lokasi_id)
                    DO UPDATE SET qty = material_stoks.qty + EXCLUDED.qty,
                                  mitra_id = COALESCE(material_stoks.mitra_id, EXCLUDED.mitra_id),
                                  updated_at = NOW()
                    RETURNING qty INTO next_qty;
                ELSE
                    UPDATE material_stoks
                    SET qty = qty + NEW.qty_delta, updated_at = NOW()
                    WHERE warehouse_id = NEW.warehouse_id
                      AND material_id = NEW.material_id
                      AND lokasi_tipe = NEW.lokasi_tipe
                      AND lokasi_id = NEW.lokasi_id
                    RETURNING qty INTO next_qty;

                    IF NOT FOUND THEN
                        RAISE EXCEPTION 'Saldo material tidak mencukupi';
                    END IF;
                END IF;

                IF next_qty < 0 THEN
                    RAISE EXCEPTION 'Saldo material tidak mencukupi';
                END IF;

                RETURN NEW;
            END;
            $fn$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = public, pg_temp;

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
                    'drum_receive', 'drum_issue', 'drum_split', 'transfer', 'receipt'
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

            DROP POLICY IF EXISTS warehouse_stock_tenant_isolation ON material_stoks;
            CREATE POLICY warehouse_stock_tenant_isolation ON material_stoks
                USING (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint)
                WITH CHECK (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint);

            DROP POLICY IF EXISTS material_transaction_tenant_isolation ON material_transaksis;
            CREATE POLICY material_transaction_tenant_isolation ON material_transaksis
                USING (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint)
                WITH CHECK (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint);

            DROP POLICY IF EXISTS material_sn_tenant_isolation ON material_sns;
            CREATE POLICY material_sn_tenant_isolation ON material_sns
                USING (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint)
                WITH CHECK (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint);

            DROP POLICY IF EXISTS drum_tenant_isolation ON drums;
            CREATE POLICY drum_tenant_isolation ON drums
                USING (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint)
                WITH CHECK (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint);

            ALTER TABLE surat_jalans ENABLE ROW LEVEL SECURITY;
            ALTER TABLE surat_jalans FORCE ROW LEVEL SECURITY;
            CREATE POLICY surat_jalan_tenant_isolation ON surat_jalans
                USING (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint)
                WITH CHECK (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint);

            ALTER TABLE surat_jalan_items ENABLE ROW LEVEL SECURITY;
            ALTER TABLE surat_jalan_items FORCE ROW LEVEL SECURITY;
            CREATE POLICY surat_jalan_item_tenant_isolation ON surat_jalan_items
                USING (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint)
                WITH CHECK (current_setting('app.is_thc', true) = 'on' OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint);

            GRANT SELECT, INSERT, UPDATE ON surat_jalans, surat_jalan_items TO pms_app;
            GRANT USAGE, SELECT, UPDATE ON SEQUENCE surat_jalans_id_seq, surat_jalan_items_id_seq TO pms_app;
            GRANT SELECT, INSERT, UPDATE ON surat_jalan_sequences TO pms_app;
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP POLICY IF EXISTS surat_jalan_item_tenant_isolation ON surat_jalan_items;
                DROP POLICY IF EXISTS surat_jalan_tenant_isolation ON surat_jalans;
                DROP INDEX IF EXISTS material_stoks_location_unique;
                CREATE UNIQUE INDEX material_stoks_warehouse_id_material_id_unique
                    ON material_stoks (warehouse_id, material_id);
            SQL);
        }

        Schema::table('material_transaksis', function (Blueprint $table): void {
            $table->dropForeign(['surat_jalan_id']);
            $table->dropColumn('surat_jalan_id');
        });
        Schema::table('drums', function (Blueprint $table): void {
            $table->dropForeign(['mitra_id']);
            $table->dropColumn('mitra_id');
        });
        Schema::table('material_sns', function (Blueprint $table): void {
            $table->dropForeign(['mitra_id']);
            $table->dropColumn('mitra_id');
        });
        Schema::table('material_stoks', function (Blueprint $table): void {
            $table->dropForeign(['mitra_id']);
            $table->dropColumn('mitra_id');
        });
        Schema::dropIfExists('surat_jalan_items');
        Schema::dropIfExists('surat_jalan_sequences');
        Schema::dropIfExists('surat_jalans');
    }
};
