<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->char('key_hash', 64)->unique();
            $table->foreignId('mitra_id')->nullable()->constrained()->nullOnDelete();
            $table->json('permissions');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('grace_until')->nullable();
            $table->uuid('rotated_from_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['mitra_id', 'expires_at', 'revoked_at']);
        });

        Schema::table('api_keys', function (Blueprint $table): void {
            $table->foreign('rotated_from_id')
                ->references('id')
                ->on('api_keys')
                ->nullOnDelete();
        });

        Schema::create('api_key_audits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('api_key_id')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event');
            $table->string('request_id')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('api_key_id')
                ->references('id')
                ->on('api_keys')
                ->nullOnDelete();
            $table->index(['api_key_id', 'created_at']);
            $table->index(['event', 'created_at']);
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            GRANT SELECT, INSERT, UPDATE ON api_keys TO pms_app;
            GRANT SELECT, INSERT ON api_key_audits TO pms_app;
            REVOKE DELETE ON api_keys FROM pms_app;
            REVOKE UPDATE, DELETE ON api_key_audits FROM pms_app;

            CREATE OR REPLACE FUNCTION prevent_api_key_delete() RETURNS trigger AS $fn$
            BEGIN
                RAISE EXCEPTION 'API Key bersifat nonaktifkan, bukan hapus';
            END;
            $fn$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = public, pg_temp;

            CREATE TRIGGER api_key_no_delete
                BEFORE DELETE ON api_keys
                FOR EACH ROW EXECUTE FUNCTION prevent_api_key_delete();

            CREATE OR REPLACE FUNCTION prevent_api_key_audit_mutation() RETURNS trigger AS $fn$
            BEGIN
                RAISE EXCEPTION 'Audit API Key bersifat append-only';
            END;
            $fn$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = public, pg_temp;

            CREATE TRIGGER api_key_audit_no_mutation
                BEFORE UPDATE OR DELETE ON api_key_audits
                FOR EACH ROW EXECUTE FUNCTION prevent_api_key_audit_mutation();
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS api_key_audit_no_mutation ON api_key_audits;
                DROP TRIGGER IF EXISTS api_key_no_delete ON api_keys;
                DROP FUNCTION IF EXISTS prevent_api_key_audit_mutation();
                DROP FUNCTION IF EXISTS prevent_api_key_delete();
            SQL);
        }

        Schema::dropIfExists('api_key_audits');
        Schema::dropIfExists('api_keys');
    }
};
