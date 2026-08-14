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
            GRANT USAGE ON SCHEMA public TO pms_app;
            GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO pms_app;
            GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO pms_app;
            GRANT EXECUTE ON ALL FUNCTIONS IN SCHEMA public TO pms_app;

            REVOKE UPDATE, DELETE ON material_transaksis FROM pms_app;
            REVOKE INSERT, UPDATE, DELETE ON material_stoks FROM pms_app;

            ALTER DEFAULT PRIVILEGES IN SCHEMA public
                GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO pms_app;
            ALTER DEFAULT PRIVILEGES IN SCHEMA public
                GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO pms_app;
            ALTER DEFAULT PRIVILEGES IN SCHEMA public
                GRANT EXECUTE ON FUNCTIONS TO pms_app;
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            REVOKE ALL ON ALL TABLES IN SCHEMA public FROM pms_app;
            REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM pms_app;
            REVOKE ALL ON ALL FUNCTIONS IN SCHEMA public FROM pms_app;
            REVOKE USAGE ON SCHEMA public FROM pms_app;
        SQL);
    }
};
