<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['units', 'pops', 'pekerjaan_jasas'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->string('kode')->unique();
                $table->string('nama');
                $table->boolean('aktif')->default(true);
                $table->timestamps();
                $table->index('aktif');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pekerjaan_jasas');
        Schema::dropIfExists('pops');
        Schema::dropIfExists('units');
    }
};
