<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table): void {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('unit');
            $table->string('jenis')->default('biasa');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->index(['aktif', 'jenis']);
        });

        Schema::create('material_stoks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 18, 3)->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'material_id']);
        });

        Schema::create('material_transaksis', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->decimal('qty_delta', 18, 3);
            $table->string('reason');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['warehouse_id', 'material_id', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE material_stoks ADD CONSTRAINT material_stoks_qty_nonnegative CHECK (qty >= 0);
                ALTER TABLE material_transaksis ADD CONSTRAINT material_transaksis_qty_nonzero CHECK (qty_delta <> 0);

                CREATE OR REPLACE FUNCTION apply_material_transaction() RETURNS trigger AS $fn$
                DECLARE next_qty numeric(18,3);
                BEGIN
                    INSERT INTO material_stoks (warehouse_id, material_id, qty, created_at, updated_at)
                    VALUES (NEW.warehouse_id, NEW.material_id, NEW.qty_delta, NOW(), NOW())
                    ON CONFLICT (warehouse_id, material_id)
                    DO UPDATE SET qty = material_stoks.qty + EXCLUDED.qty, updated_at = NOW()
                    RETURNING qty INTO next_qty;
                    IF next_qty < 0 THEN
                        RAISE EXCEPTION 'Saldo material tidak mencukupi';
                    END IF;
                    RETURN NEW;
                END;
                $fn$ LANGUAGE plpgsql;

                CREATE TRIGGER material_transaction_balance
                AFTER INSERT ON material_transaksis
                FOR EACH ROW EXECUTE FUNCTION apply_material_transaction();

                CREATE OR REPLACE FUNCTION prevent_material_transaction_mutation() RETURNS trigger AS $fn$
                BEGIN
                    RAISE EXCEPTION 'Buku transaksi bersifat append-only';
                END;
                $fn$ LANGUAGE plpgsql;

                CREATE TRIGGER material_transaction_no_update
                BEFORE UPDATE OR DELETE ON material_transaksis
                FOR EACH ROW EXECUTE FUNCTION prevent_material_transaction_mutation();

                ALTER TABLE material_stoks ENABLE ROW LEVEL SECURITY;
                ALTER TABLE material_stoks FORCE ROW LEVEL SECURITY;
                CREATE POLICY warehouse_stock_tenant_isolation ON material_stoks
                    USING (current_setting('app.is_thc', true) = 'on' OR warehouse_id IN (
                        SELECT id FROM warehouses WHERE mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                    ))
                    WITH CHECK (current_setting('app.is_thc', true) = 'on' OR warehouse_id IN (
                        SELECT id FROM warehouses WHERE mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                    ));
                ALTER TABLE material_transaksis ENABLE ROW LEVEL SECURITY;
                ALTER TABLE material_transaksis FORCE ROW LEVEL SECURITY;
                CREATE POLICY material_transaction_tenant_isolation ON material_transaksis
                    USING (current_setting('app.is_thc', true) = 'on' OR warehouse_id IN (
                        SELECT id FROM warehouses WHERE mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                    ))
                    WITH CHECK (current_setting('app.is_thc', true) = 'on' OR warehouse_id IN (
                        SELECT id FROM warehouses WHERE mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                    ));
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('material_transaksis');
        Schema::dropIfExists('material_stoks');
        Schema::dropIfExists('materials');
    }
};
