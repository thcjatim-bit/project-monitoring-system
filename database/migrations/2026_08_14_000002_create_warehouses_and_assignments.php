<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->foreignId('mitra_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('user_warehouses', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'warehouse_id']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE warehouses ENABLE ROW LEVEL SECURITY;
                ALTER TABLE warehouses FORCE ROW LEVEL SECURITY;
                CREATE POLICY tenant_isolation ON warehouses
                    USING (
                        current_setting('app.is_thc', true) = 'on'
                        OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                    )
                    WITH CHECK (
                        current_setting('app.is_thc', true) = 'on'
                        OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
                    );
                SQL);
        }

        Schema::create('whatsapp_session_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('session')->unique();
            $table->string('status');
            $table->jsonb('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_session_statuses');
        Schema::dropIfExists('user_warehouses');
        Schema::dropIfExists('warehouses');
    }
};
