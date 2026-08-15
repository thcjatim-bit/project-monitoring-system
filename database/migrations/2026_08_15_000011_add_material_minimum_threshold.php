<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table): void {
            $table->decimal('ambang_minimum', 18, 3)->nullable()->after('jenis');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE materials ADD CONSTRAINT materials_minimum_threshold_nonnegative CHECK (ambang_minimum IS NULL OR ambang_minimum >= 0)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE materials DROP CONSTRAINT IF EXISTS materials_minimum_threshold_nonnegative');
        }

        Schema::table('materials', function (Blueprint $table): void {
            $table->dropColumn('ambang_minimum');
        });
    }
};
