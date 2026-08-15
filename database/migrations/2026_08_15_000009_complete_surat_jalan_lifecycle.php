<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_jalans', function (Blueprint $table): void {
            $table->foreignId('retur_dari_id')->nullable()->after('mitra_id')->constrained('surat_jalans')->nullOnDelete();
            $table->string('transit_resolution')->nullable()->after('status');
            $table->foreignId('resolved_by')->nullable()->after('received_by')->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable()->after('received_at');
        });

        Schema::table('surat_jalan_items', function (Blueprint $table): void {
            $table->decimal('qty_diterima', 18, 3)->default(0)->after('qty');
            $table->decimal('qty_diretur', 18, 3)->default(0)->after('qty_diterima');
        });

        Schema::table('material_transaksis', function (Blueprint $table): void {
            $table->foreignId('koreksi_dari_id')->nullable()->after('surat_jalan_id')
                ->constrained('material_transaksis')->nullOnDelete();
            $table->index('koreksi_dari_id');
        });

        DB::table('surat_jalan_items')
            ->whereIn('surat_jalan_id', DB::table('surat_jalans')->where('status', 'diterima')->pluck('id'))
            ->update(['qty_diterima' => DB::raw('qty')]);

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE material_sns DROP CONSTRAINT IF EXISTS material_sns_status_valid;
            ALTER TABLE material_sns
                ADD CONSTRAINT material_sns_status_valid CHECK (status IN ('tersedia', 'keluar', 'hilang'));
            ALTER TABLE surat_jalan_items
                ADD CONSTRAINT surat_jalan_items_received_valid CHECK (qty_diterima >= 0 AND qty_diterima <= qty);
            ALTER TABLE surat_jalan_items
                ADD CONSTRAINT surat_jalan_items_returned_valid CHECK (qty_diretur >= 0 AND qty_diretur <= qty_diterima);

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
                    'hilang_dalam_perjalanan', 'koreksi'
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
                IF NEW.drum_id IS NOT NULL AND NEW.jenis_transaksi IN (
                    'drum_issue', 'drum_split', 'hilang_dalam_perjalanan'
                ) AND NEW.qty_delta < 0 THEN
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

            GRANT SELECT, INSERT ON material_transaksis TO pms_app;
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION apply_drum_transaction() RETURNS trigger AS $fn$
                BEGIN
                    IF NEW.drum_id IS NOT NULL AND NEW.jenis_transaksi IN ('drum_issue', 'drum_split') AND NEW.qty_delta < 0 THEN
                        UPDATE drums SET sisa = sisa + NEW.qty_delta, updated_at = NOW() WHERE id = NEW.drum_id;
                    END IF;
                    RETURN NEW;
                END;
                $fn$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = public, pg_temp;

                ALTER TABLE material_sns DROP CONSTRAINT IF EXISTS material_sns_status_valid;
                ALTER TABLE material_sns
                    ADD CONSTRAINT material_sns_status_valid CHECK (status IN ('tersedia', 'keluar'));
                ALTER TABLE surat_jalan_items DROP CONSTRAINT IF EXISTS surat_jalan_items_received_valid;
                ALTER TABLE surat_jalan_items DROP CONSTRAINT IF EXISTS surat_jalan_items_returned_valid;
            SQL);
        }

        Schema::table('material_transaksis', function (Blueprint $table): void {
            $table->dropForeign(['koreksi_dari_id']);
            $table->dropIndex(['koreksi_dari_id']);
            $table->dropColumn('koreksi_dari_id');
        });
        Schema::table('surat_jalan_items', function (Blueprint $table): void {
            $table->dropColumn(['qty_diterima', 'qty_diretur']);
        });
        Schema::table('surat_jalans', function (Blueprint $table): void {
            $table->dropForeign(['retur_dari_id']);
            $table->dropForeign(['resolved_by']);
            $table->dropColumn(['retur_dari_id', 'transit_resolution', 'resolved_by', 'resolved_at']);
        });
    }
};
