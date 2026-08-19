<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_code_sequences', function (Blueprint $table): void {
            $table->string('bulan', 4)->primary();
            $table->unsignedInteger('nomor_berikutnya');
            $table->timestamps();
        });

        Schema::create('project_code_issued', function (Blueprint $table): void {
            $table->string('kode')->primary();
            $table->timestamps();
        });

        $legacyCodes = DB::table('projects')->pluck('id_project')
            ->filter(fn (mixed $code): bool => is_string($code) && preg_match('/^PRJ-\d{4}-\d{4}$/', $code) === 1)
            ->map(fn (string $code): array => ['kode' => $code, 'created_at' => now(), 'updated_at' => now()])
            ->values()
            ->all();

        foreach (array_chunk($legacyCodes, 500) as $chunk) {
            DB::table('project_code_issued')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_code_issued');
        Schema::dropIfExists('project_code_sequences');
    }
};
