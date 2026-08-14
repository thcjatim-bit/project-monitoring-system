<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mitras', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('grups', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->string('preset')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('izins', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('grup_izin', function (Blueprint $table) {
            $table->foreignId('grup_id')->constrained()->cascadeOnDelete();
            $table->foreignId('izin_id')->constrained('izins')->cascadeOnDelete();
            $table->primary(['grup_id', 'izin_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('no_wa')->nullable()->after('email');
            $table->foreignId('mitra_id')->nullable()->after('no_wa')->constrained()->nullOnDelete();
            $table->foreignId('grup_id')->nullable()->after('mitra_id')->constrained()->nullOnDelete();
            $table->boolean('aktif')->default(true)->after('password');
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('id_project')->unique();
            $table->string('nama');
            $table->foreignId('mitra_id')->constrained();
            $table->timestamps();
            $table->index(['mitra_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE projects ENABLE ROW LEVEL SECURITY;
                ALTER TABLE projects FORCE ROW LEVEL SECURITY;
                CREATE POLICY tenant_isolation ON projects
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
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grup_id');
            $table->dropConstrainedForeignId('mitra_id');
            $table->dropColumn(['no_wa', 'aktif']);
        });

        Schema::dropIfExists('grup_izin');
        Schema::dropIfExists('izins');
        Schema::dropIfExists('grups');
        Schema::dropIfExists('mitras');
    }
};
