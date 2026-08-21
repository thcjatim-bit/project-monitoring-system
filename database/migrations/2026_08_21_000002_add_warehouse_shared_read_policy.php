<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Gudang THC punya mitra_id NULL, jadi tenant_isolation menyembunyikannya dari
        // Mitra dan tiap Surat Jalan THC -> Mitra kehilangan relasi origin-nya. ADR-0005
        // mewajibkan Mitra melihat gudang asal; ADR-0023 membuka bacanya lewat policy
        // permissive terpisah yang hanya berlaku untuk SELECT, sehingga tenant_isolation
        // tetap menjadi satu-satunya jalur INSERT, UPDATE, dan DELETE.
        DB::unprepared(<<<'SQL'
            CREATE POLICY warehouse_shared_read ON warehouses
                FOR SELECT
                USING (mitra_id IS NULL);
            SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP POLICY IF EXISTS warehouse_shared_read ON warehouses;');
    }
};
