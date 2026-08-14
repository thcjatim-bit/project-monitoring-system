<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION apply_material_transaction() RETURNS trigger AS $fn$
            DECLARE next_qty numeric(18,3);
            BEGIN
                IF NEW.qty_delta >= 0 THEN
                    INSERT INTO material_stoks (warehouse_id, material_id, qty, created_at, updated_at)
                    VALUES (NEW.warehouse_id, NEW.material_id, NEW.qty_delta, NOW(), NOW())
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
        SQL);
    }

    public function down(): void
    {
        // The previous trigger implementation is intentionally not restored:
        // it rejects valid decrements before the conflict update can run.
    }
};
