<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_jalans', function (Blueprint $table): void {
            $table->unsignedBigInteger('material_request_id')->nullable()->after('mitra_id');
            $table->index(['mitra_id', 'material_request_id']);
            $table->foreign(['material_request_id', 'mitra_id'])
                ->references(['id', 'mitra_id'])
                ->on('material_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('surat_jalans', function (Blueprint $table): void {
            $table->dropForeign(['material_request_id', 'mitra_id']);
            $table->dropIndex(['mitra_id', 'material_request_id']);
            $table->dropColumn('material_request_id');
        });
    }
};
