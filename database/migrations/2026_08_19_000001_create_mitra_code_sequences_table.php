<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mitra_code_sequences', function (Blueprint $table): void {
            $table->string('bulan', 4)->primary();
            $table->unsignedInteger('nomor_berikutnya');
            $table->timestamps();
        });
        Schema::create('mitra_code_issued', function (Blueprint $table): void {
            $table->string('kode')->primary();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mitra_code_issued');
        Schema::dropIfExists('mitra_code_sequences');
    }
};
