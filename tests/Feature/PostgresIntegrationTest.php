<?php

namespace Tests\Feature;

use App\Models\Mitra;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\User;
use App\Support\TenantDatabaseContext;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class PostgresIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('The pdo_pgsql extension is required for PostgreSQL integration tests.');
        }

        parent::setUp();
    }

    public function test_a_user_is_persisted_in_postgresql(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
        ]);
    }

    public function test_testing_runtime_uses_the_dedicated_database_and_restricted_application_role(): void
    {
        $identity = DB::selectOne('select current_database() as database, current_user as user');
        $role = DB::selectOne(<<<'SQL'
            select rolsuper, rolbypassrls, rolcreatedb, rolcreaterole,
                   rolreplication, rolcanlogin
            from pg_roles
            where rolname = current_user
        SQL);
        $book_privileges = DB::selectOne(<<<'SQL'
            select has_table_privilege(current_user, 'public.material_transaksis', 'INSERT') as can_insert,
                   has_table_privilege(current_user, 'public.material_transaksis', 'UPDATE') as can_update,
                   has_table_privilege(current_user, 'public.material_transaksis', 'DELETE') as can_delete,
                   has_table_privilege(current_user, 'public.material_stoks', 'INSERT') as can_insert_stock,
                   has_table_privilege(current_user, 'public.material_stoks', 'UPDATE') as can_update_stock
        SQL);

        $this->assertSame('project_monitoring_system_testing', $identity->database);
        $this->assertSame('pms_app', $identity->user);
        $this->assertFalse($role->rolsuper);
        $this->assertFalse($role->rolbypassrls);
        $this->assertFalse($role->rolcreatedb);
        $this->assertFalse($role->rolcreaterole);
        $this->assertFalse($role->rolreplication);
        $this->assertTrue($role->rolcanlogin);
        $this->assertTrue($book_privileges->can_insert);
        $this->assertFalse($book_privileges->can_update);
        $this->assertFalse($book_privileges->can_delete);
        $this->assertFalse($book_privileges->can_insert_stock);
        $this->assertFalse($book_privileges->can_update_stock);
    }

    public function test_relevant_tenant_tables_have_forced_rls_and_expected_policy(): void
    {
        $tables = DB::table('pg_class as c')
            ->join('pg_namespace as n', 'n.oid', '=', 'c.relnamespace')
            ->where('n.nspname', 'public')
            ->whereIn('c.relname', ['drums', 'material_request_items', 'material_requests', 'material_sns', 'material_stoks', 'material_transaksis', 'pemakaian_materials', 'project_rekon_items', 'project_rekons', 'projects', 'warehouses'])
            ->where('c.relkind', 'r')
            ->select(['c.relname', 'c.relrowsecurity', 'c.relforcerowsecurity'])
            ->orderBy('c.relname')
            ->get()
            ->keyBy('relname');

        $this->assertSame(
            ['drums', 'material_request_items', 'material_requests', 'material_sns', 'material_stoks', 'material_transaksis', 'pemakaian_materials', 'project_rekon_items', 'project_rekons', 'projects', 'warehouses'],
            $tables->keys()->all()
        );

        foreach ($tables as $table) {
            $this->assertTrue($table->relrowsecurity);
            $this->assertTrue($table->relforcerowsecurity);
        }

        $policies = DB::table('pg_policies')
            ->where('schemaname', 'public')
            ->whereIn('tablename', $tables->keys())
            ->pluck('policyname', 'tablename')
            ->sortKeys()
            ->all();

        $this->assertSame([
            'drums' => 'drum_tenant_isolation',
            'material_request_items' => 'material_request_item_tenant_isolation',
            'material_requests' => 'material_request_tenant_isolation',
            'material_sns' => 'material_sn_tenant_isolation',
            'material_stoks' => 'warehouse_stock_tenant_isolation',
            'material_transaksis' => 'material_transaction_tenant_isolation',
            'pemakaian_materials' => 'pemakaian_material_tenant_isolation',
            'project_rekon_items' => 'project_rekon_item_tenant_isolation',
            'project_rekons' => 'project_rekon_tenant_isolation',
            'projects' => 'tenant_isolation',
            'warehouses' => 'tenant_isolation',
        ], $policies);
    }

    public function test_surat_jalan_and_transit_tables_preserve_rls_and_stock_cache_privileges(): void
    {
        $tables = DB::table('pg_class as c')
            ->join('pg_namespace as n', 'n.oid', '=', 'c.relnamespace')
            ->where('n.nspname', 'public')
            ->whereIn('c.relname', ['material_stoks', 'material_transaksis', 'surat_jalans', 'surat_jalan_items', 'material_sns', 'drums'])
            ->where('c.relkind', 'r')
            ->select(['c.relname', 'c.relrowsecurity', 'c.relforcerowsecurity'])
            ->orderBy('c.relname')
            ->get()
            ->keyBy('relname');

        $this->assertSame(
            ['drums', 'material_sns', 'material_stoks', 'material_transaksis', 'surat_jalan_items', 'surat_jalans'],
            $tables->keys()->all()
        );
        foreach ($tables as $table) {
            $this->assertTrue($table->relrowsecurity);
            $this->assertTrue($table->relforcerowsecurity);
        }

        $policies = DB::table('pg_policies')
            ->where('schemaname', 'public')
            ->whereIn('tablename', $tables->keys())
            ->pluck('policyname', 'tablename')
            ->sortKeys()
            ->all();

        $this->assertSame([
            'drums' => 'drum_tenant_isolation',
            'material_sns' => 'material_sn_tenant_isolation',
            'material_stoks' => 'warehouse_stock_tenant_isolation',
            'material_transaksis' => 'material_transaction_tenant_isolation',
            'surat_jalan_items' => 'surat_jalan_item_tenant_isolation',
            'surat_jalans' => 'surat_jalan_tenant_isolation',
        ], $policies);

        $privileges = DB::selectOne(<<<'SQL'
            select has_table_privilege(current_user, 'public.material_stoks', 'SELECT') as can_read_stock,
                   has_table_privilege(current_user, 'public.material_stoks', 'UPDATE') as can_update_stock,
                   has_table_privilege(current_user, 'public.surat_jalans', 'INSERT') as can_insert_surat_jalan
        SQL);

        $this->assertTrue($privileges->can_read_stock);
        $this->assertFalse($privileges->can_update_stock);
        $this->assertTrue($privileges->can_insert_surat_jalan);
    }

    public function test_project_control_room_tables_have_forced_rls_and_expected_policies(): void
    {
        $expected = [
            'mitra_harga_jasas' => 'mitra_harga_jasa_tenant_isolation',
            'pks' => 'pks_tenant_isolation',
            'project_baseline_days' => 'project_baseline_day_tenant_isolation',
            'project_baselines' => 'project_baseline_tenant_isolation',
            'project_notifications' => 'project_notification_tenant_isolation',
            'project_photos' => 'project_photo_tenant_isolation',
            'project_progresses' => 'project_progress_tenant_isolation',
            'project_rab_jasas' => 'project_rab_jasa_tenant_isolation',
            'project_rab_materials' => 'project_rab_material_tenant_isolation',
            'project_steps' => 'project_step_tenant_isolation',
            'project_timeline_mentions' => 'project_timeline_mention_tenant_isolation',
            'project_timelines' => 'project_timeline_tenant_isolation',
            'project_variation_order_items' => 'project_variation_order_item_tenant_isolation',
            'project_variation_orders' => 'project_variation_order_tenant_isolation',
        ];
        ksort($expected);

        $tables = DB::table('pg_class as c')
            ->join('pg_namespace as n', 'n.oid', '=', 'c.relnamespace')
            ->where('n.nspname', 'public')
            ->whereIn('c.relname', array_keys($expected))
            ->where('c.relkind', 'r')
            ->select(['c.relname', 'c.relrowsecurity', 'c.relforcerowsecurity'])
            ->orderBy('c.relname')
            ->get()
            ->keyBy('relname');

        $this->assertSame(array_keys($expected), $tables->keys()->all());
        foreach ($tables as $table) {
            $this->assertTrue($table->relrowsecurity);
            $this->assertTrue($table->relforcerowsecurity);
        }

        $this->assertSame(
            $expected,
            DB::table('pg_policies')
                ->where('schemaname', 'public')
                ->whereIn('tablename', array_keys($expected))
                ->pluck('policyname', 'tablename')
                ->sortKeys()
                ->all(),
        );
    }

    public function test_project_timeline_is_append_only_for_the_application_role(): void
    {
        $privileges = DB::selectOne(<<<'SQL'
            select has_table_privilege(current_user, 'public.project_timelines', 'DELETE') as can_delete_timeline
        SQL);

        $this->assertFalse($privileges->can_delete_timeline);
    }

    public function test_material_usage_and_reconciliation_sources_have_no_application_delete_path(): void
    {
        $privileges = DB::selectOne(<<<'SQL'
            select has_table_privilege(current_user, 'public.pemakaian_materials', 'INSERT') as can_insert_usage,
                   has_table_privilege(current_user, 'public.pemakaian_materials', 'UPDATE') as can_update_usage,
                   has_table_privilege(current_user, 'public.pemakaian_materials', 'DELETE') as can_delete_usage,
                   has_table_privilege(current_user, 'public.project_rekons', 'INSERT') as can_insert_rekon,
                   has_table_privilege(current_user, 'public.project_rekons', 'UPDATE') as can_update_rekon,
                   has_table_privilege(current_user, 'public.project_rekons', 'DELETE') as can_delete_rekon,
                   has_table_privilege(current_user, 'public.project_rekon_items', 'DELETE') as can_delete_rekon_item
        SQL);

        $this->assertTrue($privileges->can_insert_usage);
        $this->assertTrue($privileges->can_update_usage);
        $this->assertFalse($privileges->can_delete_usage);
        $this->assertTrue($privileges->can_insert_rekon);
        $this->assertTrue($privileges->can_update_rekon);
        $this->assertFalse($privileges->can_delete_rekon);
        $this->assertFalse($privileges->can_delete_rekon_item);
    }

    public function test_api_key_security_tables_are_non_tenant_and_append_only_where_required(): void
    {
        $tables = DB::table('pg_class as c')
            ->join('pg_namespace as n', 'n.oid', '=', 'c.relnamespace')
            ->where('n.nspname', 'public')
            ->whereIn('c.relname', ['api_keys', 'api_key_audits'])
            ->where('c.relkind', 'r')
            ->select(['c.relname', 'c.relrowsecurity', 'c.relforcerowsecurity'])
            ->orderBy('c.relname')
            ->get()
            ->keyBy('relname');

        $this->assertSame(['api_key_audits', 'api_keys'], $tables->keys()->all());
        foreach ($tables as $table) {
            $this->assertFalse($table->relrowsecurity);
            $this->assertFalse($table->relforcerowsecurity);
        }

        $privileges = DB::selectOne(<<<'SQL'
            select has_table_privilege(current_user, 'public.api_keys', 'SELECT') as can_read_keys,
                   has_table_privilege(current_user, 'public.api_keys', 'INSERT') as can_insert_keys,
                   has_table_privilege(current_user, 'public.api_keys', 'UPDATE') as can_update_keys,
                   has_table_privilege(current_user, 'public.api_keys', 'DELETE') as can_delete_keys,
                   has_table_privilege(current_user, 'public.api_key_audits', 'INSERT') as can_insert_audits,
                   has_table_privilege(current_user, 'public.api_key_audits', 'UPDATE') as can_update_audits,
                   has_table_privilege(current_user, 'public.api_key_audits', 'DELETE') as can_delete_audits
        SQL);

        $this->assertTrue($privileges->can_read_keys);
        $this->assertTrue($privileges->can_insert_keys);
        $this->assertTrue($privileges->can_update_keys);
        $this->assertFalse($privileges->can_delete_keys);
        $this->assertTrue($privileges->can_insert_audits);
        $this->assertFalse($privileges->can_update_audits);
        $this->assertFalse($privileges->can_delete_audits);
    }

    public function test_mitra_raw_query_cannot_read_or_write_another_mitras_project_photo(): void
    {
        $mitraA = Mitra::factory()->create();
        $mitraB = Mitra::factory()->create();
        $tenantContext = app(TenantDatabaseContext::class);

        $tenantContext->set(null, true);

        try {
            $uploader = User::factory()->create();
            $projectA = Project::query()->create([
                'id_project' => 'PRJ-PHOTO-RLS-A',
                'nama' => 'Project Photo Mitra A',
                'mitra_id' => $mitraA->id,
            ]);
            $projectB = Project::query()->create([
                'id_project' => 'PRJ-PHOTO-RLS-B',
                'nama' => 'Project Photo Mitra B',
                'mitra_id' => $mitraB->id,
            ]);
            $stepA = $projectA->steps()->where('step', 'survey')->firstOrFail();
            $stepB = $projectB->steps()->where('step', 'survey')->firstOrFail();
            $photoA = ProjectPhoto::query()->create([
                'mitra_id' => $mitraA->id,
                'project_id' => $projectA->id,
                'project_step_id' => $stepA->id,
                'uploaded_by' => $uploader->id,
                'original_name' => 'mitra-a.jpg',
                'stored_path' => 'project-photos/PRJ-PHOTO-RLS-A/survey/2026-08-17/mitra-a.jpg',
                'mime_type' => 'image/jpeg',
                'original_size' => 1024,
            ]);
            $photoB = ProjectPhoto::query()->create([
                'mitra_id' => $mitraB->id,
                'project_id' => $projectB->id,
                'project_step_id' => $stepB->id,
                'uploaded_by' => $uploader->id,
                'original_name' => 'mitra-b.jpg',
                'stored_path' => 'project-photos/PRJ-PHOTO-RLS-B/survey/2026-08-17/mitra-b.jpg',
                'mime_type' => 'image/jpeg',
                'original_size' => 1024,
            ]);

            $tenantContext->set($mitraA->id, false);

            $visiblePhotoIds = DB::table('project_photos')
                ->pluck('id')
                ->map(static fn (int|string $id): int => (int) $id)
                ->all();

            $this->assertSame([$photoA->id], $visiblePhotoIds);
            $this->assertFalse(DB::table('project_photos')->where('id', $photoB->id)->exists());

            $this->assertProjectPhotoRlsViolation(fn () => DB::table('project_photos')->insert([
                'mitra_id' => $mitraB->id,
                'project_id' => $projectB->id,
                'project_step_id' => $stepB->id,
                'uploaded_by' => $uploader->id,
                'original_name' => 'cross-tenant-insert.jpg',
                'stored_path' => 'project-photos/PRJ-PHOTO-RLS-B/survey/2026-08-17/cross-tenant-insert.jpg',
                'mime_type' => 'image/jpeg',
                'original_size' => 1024,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            $this->assertProjectPhotoRlsViolation(fn () => DB::table('project_photos')
                ->where('id', $photoA->id)
                ->update(['mitra_id' => $mitraB->id]));
        } finally {
            $tenantContext->set(null, false);
        }
    }

    private function assertProjectPhotoRlsViolation(Closure $operation): void
    {
        try {
            DB::transaction($operation);
            $this->fail('Expected project_photos RLS to reject the cross-tenant write.');
        } catch (QueryException $exception) {
            $this->assertSame('42501', (string) $exception->getCode());
            $this->assertStringContainsString(
                'row-level security policy for table "project_photos"',
                $exception->getMessage(),
            );
        }
    }
}
