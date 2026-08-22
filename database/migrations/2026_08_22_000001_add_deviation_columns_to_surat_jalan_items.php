<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_jalan_items', function (Blueprint $table): void {
            $table->text('catatan')->nullable()->after('qty_diretur');
            $table->string('jenis_penyimpangan')->nullable()->after('catatan');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE surat_jalan_items
                ADD CONSTRAINT surat_jalan_items_deviation_valid
                CHECK (jenis_penyimpangan IS NULL OR jenis_penyimpangan IN ('material_asing', 'qty_melebihi'));
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('ALTER TABLE surat_jalan_items DROP CONSTRAINT IF EXISTS surat_jalan_items_deviation_valid;');
        }

        Schema::table('surat_jalan_items', function (Blueprint $table): void {
            $table->dropColumn(['catatan', 'jenis_penyimpangan']);
        });
    }
};
