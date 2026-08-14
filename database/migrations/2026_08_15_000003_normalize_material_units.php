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
            $table->foreignId('unit_id')->nullable()->after('nama')->constrained('units')->restrictOnDelete();
        });

        foreach (DB::table('materials')->select('unit')->distinct()->pluck('unit') as $unitName) {
            $unitId = DB::table('units')->where('kode', $unitName)->value('id');
            if ($unitId === null) {
                $unitId = DB::table('units')->insertGetId(['kode' => $unitName, 'nama' => $unitName, 'aktif' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('materials')->where('unit', $unitName)->update(['unit_id' => $unitId]);
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE materials ALTER COLUMN unit_id SET NOT NULL');
        }

        Schema::table('materials', function (Blueprint $table): void {
            $table->dropColumn('unit');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table): void {
            $table->string('unit')->nullable()->after('nama');
        });
        DB::table('materials')->orderBy('id')->each(function (object $material): void {
            DB::table('materials')->where('id', $material->id)->update(['unit' => DB::table('units')->where('id', $material->unit_id)->value('kode') ?? 'pcs']);
        });
        Schema::table('materials', function (Blueprint $table): void {
            $table->dropForeign(['unit_id']);
            $table->dropColumn('unit_id');
        });
    }
};
