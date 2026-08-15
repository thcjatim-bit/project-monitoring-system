<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_sns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->string('serial_number')->unique();
            $table->string('lokasi_tipe')->default('warehouse');
            $table->unsignedBigInteger('lokasi_id')->nullable();
            $table->string('status')->default('tersedia');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['material_id', 'status']);
            $table->index(['lokasi_tipe', 'lokasi_id']);
        });

        Schema::create('drums', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->string('drum_id')->unique();
            $table->decimal('panjang_awal', 18, 3);
            $table->decimal('sisa', 18, 3);
            $table->foreignId('induk_drum_id')->nullable()->constrained('drums')->restrictOnDelete();
            $table->string('lokasi_tipe')->default('warehouse');
            $table->unsignedBigInteger('lokasi_id')->nullable();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['material_id', 'lokasi_tipe', 'lokasi_id']);
        });

        Schema::table('material_stoks', function (Blueprint $table): void {
            $table->string('lokasi_tipe')->default('warehouse')->after('material_id');
            $table->unsignedBigInteger('lokasi_id')->nullable()->after('lokasi_tipe');
            $table->index(['material_id', 'lokasi_tipe', 'lokasi_id']);
        });

        Schema::table('material_transaksis', function (Blueprint $table): void {
            $table->string('jenis_transaksi')->default('stok')->after('material_id');
            $table->string('lokasi_tipe')->default('warehouse')->after('jenis_transaksi');
            $table->unsignedBigInteger('lokasi_id')->nullable()->after('lokasi_tipe');
            $table->foreignId('material_sn_id')->nullable()->after('qty_delta')->constrained('material_sns')->restrictOnDelete();
            $table->foreignId('drum_id')->nullable()->after('material_sn_id')->constrained('drums')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->after('drum_id')->constrained()->nullOnDelete();
            $table->foreignId('mitra_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            $table->text('catatan')->nullable()->after('reason');
            $table->index(['material_id', 'material_sn_id']);
            $table->index(['material_id', 'drum_id']);
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            GRANT SELECT ON material_stoks TO pms_app;
            GRANT SELECT, INSERT, UPDATE ON material_sns, drums TO pms_app;
            GRANT USAGE, SELECT, UPDATE ON SEQUENCE material_sns_id_seq, drums_id_seq TO pms_app;

            CREATE OR REPLACE FUNCTION apply_material_transaction() RETURNS trigger AS $fn$
            DECLARE next_qty numeric(18,3);
            BEGIN
                IF NEW.qty_delta >= 0 THEN
                    INSERT INTO material_stoks (warehouse_id, material_id, lokasi_tipe, lokasi_id, qty, created_at, updated_at)
                    VALUES (NEW.warehouse_id, NEW.material_id, 'warehouse', NEW.warehouse_id, NEW.qty_delta, NOW(), NOW())
                    ON CONFLICT (warehouse_id, material_id)
                    DO UPDATE SET qty = material_stoks.qty + EXCLUDED.qty, updated_at = NOW()
                    RETURNING qty INTO next_qty;
                ELSE
                    UPDATE material_stoks
                    SET qty = qty + NEW.qty_delta, updated_at = NOW()
                    WHERE warehouse_id = NEW.warehouse_id AND material_id = NEW.material_id
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

            ALTER TABLE material_sns
                ADD CONSTRAINT material_sns_status_valid CHECK (status IN ('tersedia', 'keluar'));
            ALTER TABLE material_sns
                ADD CONSTRAINT material_sns_location_valid CHECK (lokasi_tipe IN ('warehouse', 'project', 'terpasang', 'transit'));
            ALTER TABLE drums
                ADD CONSTRAINT drums_length_valid CHECK (panjang_awal > 0 AND sisa >= 0 AND sisa <= panjang_awal);
            ALTER TABLE drums
                ADD CONSTRAINT drums_location_valid CHECK (lokasi_tipe IN ('warehouse', 'project', 'terpasang', 'transit'));

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

            DROP TRIGGER IF EXISTS material_transaction_identity ON material_transaksis;
            CREATE TRIGGER material_transaction_identity
                BEFORE INSERT ON material_transaksis
                FOR EACH ROW EXECUTE FUNCTION validate_material_transaction_identity();

            ALTER TABLE material_sns ENABLE ROW LEVEL SECURITY;
            ALTER TABLE material_sns FORCE ROW LEVEL SECURITY;
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

            ALTER TABLE drums ENABLE ROW LEVEL SECURITY;
            ALTER TABLE drums FORCE ROW LEVEL SECURITY;
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

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS material_transaction_identity ON material_transaksis;
                DROP FUNCTION IF EXISTS validate_material_transaction_identity();
                DROP POLICY IF EXISTS material_sn_tenant_isolation ON material_sns;
                DROP POLICY IF EXISTS drum_tenant_isolation ON drums;
            SQL);
        }

        Schema::table('material_transaksis', function (Blueprint $table): void {
            $table->dropForeign(['material_sn_id']);
            $table->dropForeign(['drum_id']);
            $table->dropForeign(['project_id']);
            $table->dropForeign(['mitra_id']);
            $table->dropIndex(['material_id', 'material_sn_id']);
            $table->dropIndex(['material_id', 'drum_id']);
            $table->dropColumn(['jenis_transaksi', 'lokasi_tipe', 'lokasi_id', 'material_sn_id', 'drum_id', 'project_id', 'mitra_id', 'catatan']);
        });
        Schema::table('material_stoks', function (Blueprint $table): void {
            $table->dropIndex(['material_id', 'lokasi_tipe', 'lokasi_id']);
            $table->dropColumn(['lokasi_tipe', 'lokasi_id']);
        });
        Schema::dropIfExists('drums');
        Schema::dropIfExists('material_sns');
    }
};
