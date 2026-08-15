<?php

namespace Tests\Feature;

use App\Models\User;
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
            ->whereIn('c.relname', ['drums', 'material_request_items', 'material_requests', 'material_sns', 'material_stoks', 'material_transaksis', 'projects', 'warehouses'])
            ->where('c.relkind', 'r')
            ->select(['c.relname', 'c.relrowsecurity', 'c.relforcerowsecurity'])
            ->orderBy('c.relname')
            ->get()
            ->keyBy('relname');

        $this->assertSame(
            ['drums', 'material_request_items', 'material_requests', 'material_sns', 'material_stoks', 'material_transaksis', 'projects', 'warehouses'],
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
}
