<?php

namespace Tests\Feature;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class PostgresBiViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('The pdo_pgsql extension is required for PostgreSQL BI integration tests.');
        }

        parent::setUp();
    }

    public function test_bi_publishes_the_allowlisted_views_with_security_options(): void
    {
        $views = DB::select(<<<'SQL'
            SELECT c.relname,
                   COALESCE('security_barrier=true' = ANY (c.reloptions), false) AS security_barrier,
                   COALESCE('security_invoker=false' = ANY (c.reloptions), false) AS security_invoker_disabled
            FROM pg_class AS c
            JOIN pg_namespace AS n ON n.oid = c.relnamespace
            WHERE n.nspname = 'bi' AND c.relkind = 'v'
            ORDER BY c.relname
        SQL);

        $this->assertSame([
            'v_harga_jasa_mitra',
            'v_kurva_s',
            'v_kurva_s_series',
            'v_project_steps',
            'v_projects',
            'v_rekon_material',
            'v_request_material',
            'v_stok',
            'v_transaksi_material',
        ], array_map(static fn (object $view): string => $view->relname, $views));

        foreach ($views as $view) {
            $this->assertTrue((bool) $view->security_barrier, $view->relname);
            $this->assertTrue((bool) $view->security_invoker_disabled, $view->relname);
        }
    }

    public function test_bi_view_columns_are_explicit_and_stable(): void
    {
        $expected = [
            'v_projects' => [
                'project_id', 'id_project', 'project_nama', 'mitra_id', 'mitra_kode', 'mitra_nama',
                'status_project', 'toc', 'original_baseline_kind', 'original_baseline_version',
                'original_baseline_toc', 'revised_baseline_kind', 'revised_baseline_version',
                'revised_baseline_toc', 'active_baseline_kind', 'active_baseline_version',
                'active_baseline_toc', 'reporting_as_of', 'read_at',
            ],
            'v_project_steps' => [
                'project_step_id', 'project_id', 'id_project', 'project_nama', 'mitra_id', 'step_code',
                'step_order', 'step_status', 'completed_at', 'read_at',
            ],
            'v_kurva_s' => [
                'project_id', 'id_project', 'project_nama', 'mitra_id', 'mitra_kode', 'mitra_nama',
                'reporting_as_of', 'grand_total_rab_jasa', 'verified_value', 'verified_percent',
                'pending_value', 'pending_percent', 'pending_shadow_value', 'pending_shadow_percent',
                'plan_percent', 'spi', 'spi_status', 'original_baseline_kind', 'original_baseline_version',
                'original_baseline_toc', 'revised_baseline_kind', 'revised_baseline_version',
                'revised_baseline_toc', 'active_baseline_kind', 'active_baseline_version',
                'active_baseline_toc', 'overdue', 'baseline_flat_after_toc', 'read_at',
            ],
            'v_kurva_s_series' => [
                'project_id', 'id_project', 'mitra_id', 'reporting_as_of', 'series_kind', 'series_date',
                'series_value', 'cumulative_value', 'cumulative_percent', 'read_at',
            ],
            'v_stok' => [
                'stock_id', 'location_type', 'location_id', 'project_id', 'id_project', 'warehouse_id',
                'warehouse_kode', 'warehouse_nama', 'material_id', 'material_kode', 'material_nama',
                'unit_kode', 'unit_nama', 'mitra_id', 'location_name', 'qty', 'available_qty',
                'is_warehouse_available', 'read_at',
            ],
            'v_transaksi_material' => [
                'material_transaction_id', 'event_at', 'transaction_type', 'material_id', 'material_kode',
                'material_nama', 'unit_kode', 'unit_nama', 'warehouse_id', 'warehouse_kode',
                'warehouse_nama', 'project_id', 'id_project', 'surat_jalan_id', 'surat_jalan_nomor',
                'location_type', 'location_id', 'qty_delta', 'correction_transaction_id', 'mitra_id',
                'reporting_as_of', 'read_at',
            ],
            'v_request_material' => [
                'request_item_id', 'material_request_id', 'mitra_id', 'mitra_kode', 'mitra_nama',
                'project_id', 'id_project', 'project_nama', 'workflow_status', 'material_id',
                'material_kode', 'material_nama', 'unit_kode', 'unit_nama', 'qty_diminta', 'qty_diterima',
                'qty_diretur', 'qty_transit', 'qty_sisa', 'fulfillment_status', 'reporting_as_of', 'read_at',
            ],
            'v_rekon_material' => [
                'project_rekon_id', 'rekon_nomor', 'mitra_id', 'mitra_kode', 'mitra_nama', 'project_id',
                'id_project', 'project_nama', 'status_project', 'source', 'status', 'correction_source_id',
                'approved_at', 'reporting_as_of', 'project_rekon_item_id', 'warehouse_id', 'warehouse_kode',
                'warehouse_nama', 'material_id', 'material_kode', 'material_nama', 'unit_kode', 'unit_nama',
                'material_sn_id', 'drum_id', 'keluar_gudang', 'terpasang', 'sisa_project', 'dikembalikan',
                'hilang_rusak', 'kategori_hilang_rusak', 'penanggung_jawab', 'is_active_correction',
                'is_effective_approved', 'read_at',
            ],
            'v_harga_jasa_mitra' => [
                'mitra_harga_jasa_id', 'mitra_id', 'mitra_kode', 'mitra_nama', 'pekerjaan_jasa_id',
                'pekerjaan_jasa_kode', 'pekerjaan_jasa_nama', 'pks_id', 'pks_nomor', 'pks_tanggal_mulai',
                'pks_tanggal_berakhir', 'harga', 'status', 'berlaku_mulai', 'revisi_dari_id',
                'is_effective_price', 'reporting_as_of', 'read_at',
            ],
        ];

        $columns = DB::connection('migrator')->select(<<<'SQL'
            SELECT table_name, column_name
            FROM information_schema.columns
            WHERE table_schema = 'bi'
            ORDER BY table_name, ordinal_position
        SQL);
        $actual = [];
        foreach ($columns as $column) {
            $actual[$column->table_name][] = $column->column_name;
        }

        ksort($expected);
        $this->assertSame($expected, $actual);
        $readAt = DB::connection('migrator')->select(<<<'SQL'
            SELECT table_name, data_type
            FROM information_schema.columns
            WHERE table_schema = 'bi' AND column_name = 'read_at'
            ORDER BY table_name
        SQL);
        $this->assertCount(9, $readAt);
        foreach ($readAt as $column) {
            $this->assertSame('timestamp with time zone', $column->data_type, $column->table_name);
        }
    }

    public function test_bi_reader_has_only_effective_select_on_the_curated_views(): void
    {
        $role = DB::selectOne(<<<'SQL'
            SELECT rolsuper, rolbypassrls, rolcreatedb, rolcreaterole, rolreplication, rolcanlogin
            FROM pg_roles
            WHERE rolname = 'pms_bi_reader'
        SQL);
        $this->assertFalse($role->rolsuper);
        $this->assertFalse($role->rolbypassrls);
        $this->assertFalse($role->rolcreatedb);
        $this->assertFalse($role->rolcreaterole);
        $this->assertFalse($role->rolreplication);
        $this->assertTrue($role->rolcanlogin);

        $memberships = DB::selectOne(<<<'SQL'
            SELECT EXISTS (
                SELECT 1
                FROM pg_auth_members AS membership
                JOIN pg_roles AS member ON member.oid = membership.member
                WHERE member.rolname = 'pms_bi_reader'
            ) AS has_membership
        SQL);
        $this->assertFalse($memberships->has_membership);

        $owner = DB::selectOne(<<<'SQL'
            SELECT rolsuper, rolbypassrls, rolcanlogin
            FROM pg_roles
            WHERE rolname = 'pms_bi_view_owner'
        SQL);
        $this->assertFalse($owner->rolsuper);
        $this->assertFalse($owner->rolbypassrls);
        $this->assertFalse($owner->rolcanlogin);

        $owner_memberships = DB::selectOne(<<<'SQL'
            SELECT EXISTS (
                SELECT 1
                FROM pg_auth_members AS membership
                JOIN pg_roles AS member ON member.oid = membership.member
                WHERE member.rolname = 'pms_bi_view_owner'
            ) AS has_membership
        SQL);
        $this->assertFalse($owner_memberships->has_membership);

        $owner_base_relations = DB::selectOne(<<<'SQL'
            SELECT EXISTS (
                SELECT 1
                FROM pg_class AS relation
                JOIN pg_namespace AS namespace ON namespace.oid = relation.relnamespace
                JOIN pg_roles AS owner_role ON owner_role.oid = relation.relowner
                WHERE namespace.nspname = 'public'
                  AND relation.relkind IN ('r', 'p')
                  AND owner_role.rolname = 'pms_bi_view_owner'
            ) AS owns_base_relation
        SQL);
        $this->assertFalse($owner_base_relations->owns_base_relation);

        $tenant_relations = DB::select(<<<'SQL'
            SELECT c.relname, c.relrowsecurity, c.relforcerowsecurity
            FROM pg_class AS c
            JOIN pg_namespace AS n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public'
              AND c.relkind IN ('r', 'p')
              AND c.relname IN (
                  'projects', 'project_steps', 'project_baselines', 'project_baseline_days',
                  'project_progresses', 'project_rab_jasas', 'project_variation_orders',
                  'project_variation_order_items', 'warehouses', 'material_stoks',
                  'material_transaksis', 'surat_jalans', 'surat_jalan_items',
                  'material_requests', 'material_request_items', 'project_rekons',
                  'project_rekon_items', 'pks', 'mitra_harga_jasas'
              )
            ORDER BY c.relname
        SQL);
        $this->assertSame([
            'material_request_items', 'material_requests', 'material_stoks', 'material_transaksis',
            'mitra_harga_jasas', 'pks', 'project_baseline_days', 'project_baselines',
            'project_progresses', 'project_rab_jasas', 'project_rekon_items', 'project_rekons',
            'project_steps', 'project_variation_order_items', 'project_variation_orders', 'projects',
            'surat_jalan_items', 'surat_jalans', 'warehouses',
        ], array_map(static fn (object $relation): string => $relation->relname, $tenant_relations));
        foreach ($tenant_relations as $relation) {
            $this->assertTrue((bool) $relation->relrowsecurity, $relation->relname);
            $this->assertTrue((bool) $relation->relforcerowsecurity, $relation->relname);
        }

        $schema_privileges = DB::selectOne(<<<'SQL'
            SELECT has_schema_privilege('pms_bi_reader', 'bi', 'USAGE') AS can_usage,
                   has_schema_privilege('pms_bi_reader', 'bi', 'CREATE') AS can_create
        SQL);
        $this->assertTrue($schema_privileges->can_usage);
        $this->assertFalse($schema_privileges->can_create);

        $raw_privilege = DB::selectOne(<<<'SQL'
            SELECT has_table_privilege('pms_bi_reader', 'public.material_transaksis', 'SELECT') AS can_select,
                   has_table_privilege('pms_bi_reader', 'public.material_transaksis', 'INSERT') AS can_insert,
                   has_table_privilege('pms_bi_reader', 'public.material_transaksis', 'UPDATE') AS can_update,
                   has_table_privilege('pms_bi_reader', 'public.material_transaksis', 'DELETE') AS can_delete,
                   has_table_privilege('pms_bi_reader', 'public.material_transaksis', 'TRUNCATE') AS can_truncate,
                   has_table_privilege('pms_bi_reader', 'public.material_transaksis', 'REFERENCES') AS can_reference,
                   has_table_privilege('pms_bi_reader', 'public.material_transaksis', 'TRIGGER') AS can_trigger
        SQL);
        foreach (get_object_vars($raw_privilege) as $privilege) {
            $this->assertFalse($privilege);
        }

        $view_privileges = DB::select(<<<'SQL'
            SELECT c.relname,
                   has_table_privilege('pms_bi_reader', c.oid, 'SELECT') AS can_select,
                   has_table_privilege('pms_bi_reader', c.oid, 'INSERT') AS can_insert,
                   has_table_privilege('pms_bi_reader', c.oid, 'UPDATE') AS can_update,
                   has_table_privilege('pms_bi_reader', c.oid, 'DELETE') AS can_delete,
                   has_table_privilege('pms_bi_reader', c.oid, 'TRUNCATE') AS can_truncate,
                   has_table_privilege('pms_bi_reader', c.oid, 'REFERENCES') AS can_reference,
                   has_table_privilege('pms_bi_reader', c.oid, 'TRIGGER') AS can_trigger
            FROM pg_class AS c
            JOIN pg_namespace AS n ON n.oid = c.relnamespace
            WHERE n.nspname = 'bi' AND c.relkind = 'v'
            ORDER BY c.relname
        SQL);
        foreach ($view_privileges as $view) {
            $this->assertTrue($view->can_select, $view->relname);
            foreach (['can_insert', 'can_update', 'can_delete', 'can_truncate', 'can_reference', 'can_trigger'] as $privilege) {
                $this->assertFalse($view->{$privilege}, $view->relname.' '.$privilege);
            }
        }

        $raw_tables_with_access = DB::select(<<<'SQL'
            SELECT c.relname
            FROM pg_class AS c
            JOIN pg_namespace AS n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public'
              AND c.relkind IN ('r', 'p')
              AND (
                  has_table_privilege('pms_bi_reader', c.oid, 'SELECT')
                  OR has_table_privilege('pms_bi_reader', c.oid, 'INSERT')
                  OR has_table_privilege('pms_bi_reader', c.oid, 'UPDATE')
                  OR has_table_privilege('pms_bi_reader', c.oid, 'DELETE')
                  OR has_table_privilege('pms_bi_reader', c.oid, 'TRUNCATE')
                  OR has_table_privilege('pms_bi_reader', c.oid, 'REFERENCES')
                  OR has_table_privilege('pms_bi_reader', c.oid, 'TRIGGER')
              )
            ORDER BY c.relname
        SQL);
        $this->assertSame([], $raw_tables_with_access);
    }

    public function test_bi_reader_is_thc_scoped_and_missing_context_returns_no_rows(): void
    {
        $writer = DB::connection('migrator');
        $writer->select("select set_config('app.is_thc', 'on', false)");
        $writer->select("select set_config('app.mitra_id', '', false)");

        $mitraA = $writer->table('mitras')->insertGetId([
            'kode' => 'BI-A',
            'nama' => 'Mitra BI A',
            'aktif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $mitraB = $writer->table('mitras')->insertGetId([
            'kode' => 'BI-B',
            'nama' => 'Mitra BI B',
            'aktif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $writer->table('projects')->insert([
            [
                'id_project' => 'PRJ-BI-0001',
                'nama' => 'Project BI A',
                'mitra_id' => $mitraA,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id_project' => 'PRJ-BI-0002',
                'nama' => 'Project BI B',
                'mitra_id' => $mitraB,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $bi = DB::connection('bi_testing');
        $bi->statement('reset app.is_thc');
        $bi->statement('reset app.mitra_id');
        $this->assertCount(0, $bi->table('bi.v_projects')->get());

        $bi->select("select set_config('app.is_thc', 'on', false)");
        $bi->statement('reset app.mitra_id');
        $this->assertCount(0, $bi->table('bi.v_projects')->get());

        $bi->select("select set_config('app.mitra_id', '', false)");
        $bi->select("select set_config('app.reporting_as_of', '2026-08-18', false)");

        $identity = $bi->selectOne(<<<'SQL'
            SELECT current_user,
                   current_setting('app.is_thc', true) AS is_thc,
                   current_setting('app.mitra_id', true) AS mitra_id
        SQL);
        $this->assertSame('pms_bi_reader', $identity->current_user);
        $this->assertSame('on', $identity->is_thc);
        $this->assertSame('', $identity->mitra_id);

        $projects = $bi->table('bi.v_projects')->orderBy('id_project')->get();
        $this->assertSame(['PRJ-BI-0001', 'PRJ-BI-0002'], $projects->pluck('id_project')->all());
        $this->assertNotNull($projects->first()->read_at);
        $this->assertSame('2026-08-18', (string) $projects->first()->reporting_as_of);

        $bi->select("select set_config('app.is_thc', 'off', false)");
        $this->assertCount(0, $bi->table('bi.v_projects')->get());

        $bi->select("select set_config('app.is_thc', 'on', false)");
        $bi->select("select set_config('app.mitra_id', '999999', false)");
        $this->assertCount(0, $bi->table('bi.v_projects')->get());
    }

    public function test_bi_reader_cannot_query_the_raw_material_book_or_sensitive_columns(): void
    {
        $columns = DB::select(<<<'SQL'
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = 'bi'
              AND table_name IN (
                  'v_projects', 'v_project_steps', 'v_kurva_s', 'v_kurva_s_series', 'v_stok',
                  'v_transaksi_material', 'v_request_material', 'v_rekon_material', 'v_harga_jasa_mitra'
              )
              AND column_name IN (
                  'password', 'password_hash', 'body', 'metadata', 'catatan', 'decision_note',
                  'actor_id', 'requested_by', 'decided_by', 'approved_by', 'opened_by',
                  'diajukan_oleh', 'diputuskan_oleh', 'lampiran_path', 'reason'
              )
        SQL);
        $this->assertSame([], $columns);

        $bi = DB::connection('bi_testing');
        $bi->select("select set_config('app.mitra_id', '', false)");
        $bi->select("select set_config('app.is_thc', 'on', false)");

        try {
            $bi->select('select count(*) from public.material_transaksis');
            $this->fail('The BI reader must not have raw material-book access.');
        } catch (QueryException $exception) {
            $this->assertSame('42501', (string) $exception->getCode());
        }

        foreach ([
            ['insert into public.material_transaksis (id) values (0)', ['42501']],
            ['update public.material_transaksis set reason = reason where false', ['42501']],
            ['delete from bi.v_projects where false', ['42501', '55000']],
        ] as $statement) {
            try {
                $bi->statement($statement[0]);
                $this->fail('The BI reader must not mutate raw tables or curated views.');
            } catch (QueryException $exception) {
                $this->assertContains((string) $exception->getCode(), $statement[1], $statement[0]);
            }
        }
    }

    public function test_inventory_and_request_views_keep_their_domain_grain(): void
    {
        $fixture = $this->createInventoryFixture();

        $historical = $this->biReader('2026-08-18')
            ->table('bi.v_request_material')
            ->where('material_request_id', $fixture['request_id'])
            ->first();
        $this->assertNotNull($historical);
        $this->assertSame('0.000', (string) $historical->qty_diterima);
        $this->assertSame('0.000', (string) $historical->qty_diretur);
        $this->assertSame('5.000', (string) $historical->qty_transit);
        $this->assertSame('5.000', (string) $historical->qty_sisa);

        $bi = $this->biReader('2026-08-19');

        $stocks = $bi->table('bi.v_stok')
            ->where('material_id', $fixture['material_id'])
            ->orderBy('location_type')
            ->get();

        $this->assertSame(['project', 'terpasang', 'transit', 'warehouse'], $stocks->pluck('location_type')->all());
        $this->assertSame('7.000', (string) $stocks->firstWhere('location_type', 'warehouse')->qty);
        $this->assertSame('7.000', (string) $stocks->firstWhere('location_type', 'warehouse')->available_qty);
        $this->assertSame('0.000', (string) $stocks->firstWhere('location_type', 'transit')->available_qty);
        $this->assertSame('0.000', (string) $stocks->firstWhere('location_type', 'project')->available_qty);
        $this->assertSame('0.000', (string) $stocks->firstWhere('location_type', 'terpasang')->available_qty);

        $request = $bi->table('bi.v_request_material')
            ->where('material_request_id', $fixture['request_id'])
            ->first();

        $this->assertNotNull($request);
        $this->assertSame('5.000', (string) $request->qty_diminta);
        $this->assertSame('3.000', (string) $request->qty_diterima);
        $this->assertSame('1.000', (string) $request->qty_diretur);
        $this->assertSame('1.000', (string) $request->qty_transit);
        $this->assertSame('2.000', (string) $request->qty_sisa);
        $this->assertSame('terpenuhi_sebagian', $request->fulfillment_status);
        $this->assertSame('2026-08-19', (string) $request->reporting_as_of);

        $writer = DB::connection('migrator');
        $writer->select("select set_config('app.is_thc', 'on', false)");
        $writer->table('material_transaksis')->insert([
            'warehouse_id' => $fixture['warehouse_id'],
            'material_id' => $fixture['material_id'],
            'jenis_transaksi' => 'receive',
            'lokasi_tipe' => 'warehouse',
            'lokasi_id' => $fixture['warehouse_id'],
            'qty_delta' => '1.000',
            'material_sn_id' => null,
            'drum_id' => null,
            'project_id' => $fixture['project_id'],
            'mitra_id' => $fixture['mitra_id'],
            'surat_jalan_id' => null,
            'koreksi_dari_id' => null,
            'pemakaian_material_id' => null,
            'project_rekon_item_id' => null,
            'reason' => 'BI internal reason must not be projected',
            'catatan' => 'BI internal note must not be projected',
            'actor_id' => $fixture['actor_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $transaction = $bi->table('bi.v_transaksi_material')
            ->where('material_id', $fixture['material_id'])
            ->latest('material_transaction_id')
            ->first();
        $this->assertNotNull($transaction);
        $this->assertSame('receive', $transaction->transaction_type);
        $this->assertSame('1.000', (string) $transaction->qty_delta);
        $this->assertSame('2026-08-19', (string) $transaction->reporting_as_of);
        $this->assertNotNull($transaction->event_at);
        $this->assertObjectNotHasProperty('reason', $transaction);
        $this->assertObjectNotHasProperty('catatan', $transaction);
        $this->assertObjectNotHasProperty('actor_id', $transaction);
    }

    public function test_curve_rekon_and_price_views_follow_the_shared_semantic_contract(): void
    {
        $fixture = $this->createInventoryFixture();
        $writer = DB::connection('migrator');
        $writer->select("select set_config('app.is_thc', 'on', false)");
        $timestamp = now();
        $suffix = str_replace('.', '', uniqid('', true));

        $pekerjaanId = (int) $writer->table('pekerjaan_jasas')->insertGetId([
            'kode' => 'JOB-'.$suffix,
            'nama' => 'Pekerjaan BI '.$suffix,
            'aktif' => true,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $pksId = (int) $writer->table('pks')->insertGetId([
            'mitra_id' => $fixture['mitra_id'],
            'nomor' => 'PKS-BI-'.$suffix,
            'tanggal_mulai' => '2026-01-01',
            'tanggal_berakhir' => '2026-12-31',
            'lampiran_path' => 'private/attachment-'.$suffix.'.pdf',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $hargaId = (int) $writer->table('mitra_harga_jasas')->insertGetId([
            'mitra_id' => $fixture['mitra_id'],
            'pks_id' => $pksId,
            'pekerjaan_jasa_id' => $pekerjaanId,
            'harga' => '100.00',
            'status' => 'disetujui',
            'berlaku_mulai' => '2026-01-01',
            'revisi_dari_id' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $rabId = (int) $writer->table('project_rab_jasas')->insertGetId([
            'mitra_id' => $fixture['mitra_id'],
            'project_id' => $fixture['project_id'],
            'pekerjaan_jasa_id' => $pekerjaanId,
            'harga_jasa_mitra_id' => $hargaId,
            'variation_order_id' => null,
            'qty' => '10.000',
            'harga_satuan' => '100.00',
            'total_nilai' => '1000.00',
            'dibuat_oleh' => $fixture['actor_id'],
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $baselineId = (int) $writer->table('project_baselines')->insertGetId([
            'mitra_id' => $fixture['mitra_id'],
            'project_id' => $fixture['project_id'],
            'kind' => 'original',
            'version' => 1,
            'toc' => '2026-08-20',
            'supersedes_id' => null,
            'dibuat_oleh' => $fixture['actor_id'],
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $writer->table('project_baseline_days')->insert([
            ['mitra_id' => $fixture['mitra_id'], 'project_baseline_id' => $baselineId, 'plan_date' => '2026-08-18', 'cumulative_percent' => '60.000', 'created_at' => $timestamp, 'updated_at' => $timestamp],
        ]);
        $writer->table('project_progresses')->insert([
            [
                'mitra_id' => $fixture['mitra_id'],
                'project_id' => $fixture['project_id'],
                'project_rab_jasa_id' => $rabId,
                'reported_by' => $fixture['actor_id'],
                'actual_date' => '2026-08-18',
                'qty' => '2.000',
                'status' => 'verified',
                'verified_by' => $fixture['actor_id'],
                'verified_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'mitra_id' => $fixture['mitra_id'],
                'project_id' => $fixture['project_id'],
                'project_rab_jasa_id' => $rabId,
                'reported_by' => $fixture['actor_id'],
                'actual_date' => '2026-08-19',
                'qty' => '1.000',
                'status' => 'pending',
                'verified_by' => null,
                'verified_at' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);

        $firstRekonId = (int) $writer->table('project_rekons')->insertGetId([
            'nomor' => 'REK-BI-1-'.$suffix,
            'mitra_id' => $fixture['mitra_id'],
            'project_id' => $fixture['project_id'],
            'koreksi_dari_id' => null,
            'source' => 'manual',
            'status' => 'diajukan',
            'opened_by' => $fixture['actor_id'],
            'approved_by' => $fixture['actor_id'],
            'approved_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $writer->table('project_rekon_items')->insert([
            'mitra_id' => $fixture['mitra_id'],
            'project_rekon_id' => $firstRekonId,
            'warehouse_id' => $fixture['warehouse_id'],
            'material_id' => $fixture['material_id'],
            'keluar_gudang' => '6.000',
            'terpasang' => '2.000',
            'sisa_project' => '4.000',
            'dikembalikan' => '3.000',
            'hilang_rusak' => '1.000',
            'kategori_hilang_rusak' => 'waste_wajar',
            'penanggung_jawab' => 'mitra',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $writer->table('project_rekons')->where('id', $firstRekonId)->update([
            'status' => 'disetujui',
        ]);
        $secondRekonId = (int) $writer->table('project_rekons')->insertGetId([
            'nomor' => 'REK-BI-2-'.$suffix,
            'mitra_id' => $fixture['mitra_id'],
            'project_id' => $fixture['project_id'],
            'koreksi_dari_id' => $firstRekonId,
            'source' => 'manual',
            'status' => 'diajukan',
            'opened_by' => $fixture['actor_id'],
            'approved_by' => $fixture['actor_id'],
            'approved_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $writer->table('project_rekon_items')->insert([
            'mitra_id' => $fixture['mitra_id'],
            'project_rekon_id' => $secondRekonId,
            'warehouse_id' => $fixture['warehouse_id'],
            'material_id' => $fixture['material_id'],
            'keluar_gudang' => '6.000',
            'terpasang' => '2.000',
            'sisa_project' => '4.000',
            'dikembalikan' => '4.000',
            'hilang_rusak' => '0.000',
            'kategori_hilang_rusak' => null,
            'penanggung_jawab' => 'mitra',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $writer->table('project_rekons')->where('id', $secondRekonId)->update([
            'status' => 'disetujui',
        ]);

        $bi = $this->biReader('2026-08-19');
        $curve = $bi->table('bi.v_kurva_s')->where('project_id', $fixture['project_id'])->first();
        $this->assertNotNull($curve);
        $this->assertSame('1000.00', (string) $curve->grand_total_rab_jasa);
        $this->assertSame('200.00', (string) $curve->verified_value);
        $this->assertSame('20.00', (string) $curve->verified_percent);
        $this->assertSame('100.00', (string) $curve->pending_value);
        $this->assertSame('10.00', (string) $curve->pending_percent);
        $this->assertSame('30.00', (string) $curve->pending_shadow_percent);
        $this->assertSame('60.00', (string) $curve->plan_percent);
        $this->assertSame('0.3333', (string) $curve->spi);
        $this->assertSame('red', $curve->spi_status);

        $series = $bi->table('bi.v_kurva_s_series')
            ->where('project_id', $fixture['project_id'])
            ->orderBy('series_kind')
            ->orderBy('series_date')
            ->get();
        $this->assertSame([
            'original_baseline',
            'pending',
            'pending_shadow',
            'verified',
        ], $series->pluck('series_kind')->unique()->values()->all());
        $this->assertSame('20.00', (string) $series->firstWhere('series_kind', 'verified')->cumulative_percent);
        $pendingShadowSeries = $series->where('series_kind', 'pending_shadow')->values();
        $this->assertCount(2, $pendingShadowSeries);
        $this->assertSame('20.00', (string) $pendingShadowSeries->first()->cumulative_percent);
        $this->assertSame('30.00', (string) $pendingShadowSeries->last()->cumulative_percent);

        $rekons = $bi->table('bi.v_rekon_material')
            ->where('project_id', $fixture['project_id'])
            ->orderBy('project_rekon_id')
            ->get();
        $this->assertCount(2, $rekons);
        $this->assertFalse((bool) $rekons[0]->is_active_correction);
        $this->assertFalse((bool) $rekons[0]->is_effective_approved);
        $this->assertTrue((bool) $rekons[1]->is_active_correction);
        $this->assertTrue((bool) $rekons[1]->is_effective_approved);
        $this->assertSame('4.000', (string) $rekons[1]->dikembalikan);

        $pendingRekonId = (int) $writer->table('project_rekons')->insertGetId([
            'nomor' => 'REK-BI-PENDING-'.$suffix,
            'mitra_id' => $fixture['mitra_id'],
            'project_id' => $fixture['project_id'],
            'koreksi_dari_id' => $secondRekonId,
            'source' => 'manual',
            'status' => 'diajukan',
            'opened_by' => $fixture['actor_id'],
            'approved_by' => null,
            'approved_at' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $writer->table('project_rekon_items')->insert([
            'mitra_id' => $fixture['mitra_id'],
            'project_rekon_id' => $pendingRekonId,
            'warehouse_id' => $fixture['warehouse_id'],
            'material_id' => $fixture['material_id'],
            'keluar_gudang' => '6.000',
            'terpasang' => '2.000',
            'sisa_project' => '4.000',
            'dikembalikan' => '4.000',
            'hilang_rusak' => '0.000',
            'kategori_hilang_rusak' => null,
            'penanggung_jawab' => 'mitra',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $rekonsWithPending = $bi->table('bi.v_rekon_material')
            ->where('project_id', $fixture['project_id'])
            ->orderBy('project_rekon_id')
            ->get();
        $this->assertCount(3, $rekonsWithPending);
        $this->assertFalse((bool) $rekonsWithPending[1]->is_active_correction);
        $this->assertFalse((bool) $rekonsWithPending[1]->is_effective_approved);
        $this->assertTrue((bool) $rekonsWithPending[2]->is_active_correction);
        $this->assertFalse((bool) $rekonsWithPending[2]->is_effective_approved);

        $price = $bi->table('bi.v_harga_jasa_mitra')
            ->where('mitra_harga_jasa_id', $hargaId)
            ->first();
        $this->assertNotNull($price);
        $this->assertTrue((bool) $price->is_effective_price);
        $this->assertSame('100.00', (string) $price->harga);
        $this->assertObjectNotHasProperty('lampiran_path', $price);
    }

    private function biReader(string $reportingAsOf): Connection
    {
        $bi = DB::connection('bi_testing');
        $bi->select("select set_config('app.mitra_id', '', false)");
        $bi->select("select set_config('app.is_thc', 'on', false)");
        $bi->select('select set_config(?, ?, false)', ['app.reporting_as_of', $reportingAsOf]);

        return $bi;
    }

    /** @return array{actor_id:int,material_id:int,mitra_id:int,project_id:int,request_id:int,warehouse_id:int} */
    private function createInventoryFixture(): array
    {
        $writer = DB::connection('migrator');
        $writer->select("select set_config('app.is_thc', 'on', false)");
        $writer->select("select set_config('app.mitra_id', '', false)");

        $suffix = str_replace('.', '', uniqid('', true));
        $timestamp = now();
        $mitraId = (int) $writer->table('mitras')->insertGetId([
            'kode' => 'BI-'.$suffix,
            'nama' => 'Mitra BI '.$suffix,
            'aktif' => true,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $projectId = (int) $writer->table('projects')->insertGetId([
            'id_project' => 'PRJ-'.$suffix,
            'nama' => 'Project BI '.$suffix,
            'mitra_id' => $mitraId,
            'status_project' => 'aktif',
            'toc' => '2026-08-20',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $unitId = (int) $writer->table('units')->insertGetId([
            'kode' => 'U-'.$suffix,
            'nama' => 'Unit BI '.$suffix,
            'aktif' => true,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $materialId = (int) $writer->table('materials')->insertGetId([
            'kode' => 'MAT-'.$suffix,
            'nama' => 'Material BI '.$suffix,
            'unit_id' => $unitId,
            'jenis' => 'biasa',
            'aktif' => true,
            'ambang_minimum' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $warehouseId = (int) $writer->table('warehouses')->insertGetId([
            'kode' => 'WH-'.$suffix,
            'nama' => 'Warehouse BI '.$suffix,
            'mitra_id' => $mitraId,
            'aktif' => true,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $destinationWarehouseId = (int) $writer->table('warehouses')->insertGetId([
            'kode' => 'WH-D-'.$suffix,
            'nama' => 'Warehouse Tujuan BI '.$suffix,
            'mitra_id' => $mitraId,
            'aktif' => true,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $actorId = (int) $writer->table('users')->insertGetId([
            'name' => 'BI Fixture Actor '.$suffix,
            'email' => 'bi-'.$suffix.'@example.test',
            'password' => 'not-a-bi-output-secret',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $writer->table('material_stoks')->insert([
            ['warehouse_id' => $warehouseId, 'material_id' => $materialId, 'mitra_id' => $mitraId, 'lokasi_tipe' => 'warehouse', 'lokasi_id' => $warehouseId, 'qty' => '7.000', 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['warehouse_id' => $warehouseId, 'material_id' => $materialId, 'mitra_id' => $mitraId, 'lokasi_tipe' => 'transit', 'lokasi_id' => 900001, 'qty' => '2.000', 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['warehouse_id' => $warehouseId, 'material_id' => $materialId, 'mitra_id' => $mitraId, 'lokasi_tipe' => 'project', 'lokasi_id' => $projectId, 'qty' => '3.000', 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['warehouse_id' => $warehouseId, 'material_id' => $materialId, 'mitra_id' => $mitraId, 'lokasi_tipe' => 'terpasang', 'lokasi_id' => $projectId, 'qty' => '1.000', 'created_at' => $timestamp, 'updated_at' => $timestamp],
        ]);

        $requestId = (int) $writer->table('material_requests')->insertGetId([
            'mitra_id' => $mitraId,
            'project_id' => $projectId,
            'requested_by' => $actorId,
            'status' => 'disetujui',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $writer->table('material_request_items')->insert([
            'material_request_id' => $requestId,
            'mitra_id' => $mitraId,
            'material_id' => $materialId,
            'qty' => '5.000',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $receivedShipmentId = (int) $writer->table('surat_jalans')->insertGetId([
            'nomor' => 'SJ-BI-R-'.$suffix,
            'tanggal' => '2026-08-18',
            'warehouse_asal_id' => $warehouseId,
            'warehouse_tujuan_id' => $destinationWarehouseId,
            'mitra_id' => $mitraId,
            'material_request_id' => $requestId,
            'project_id' => $projectId,
            'issued_by' => $actorId,
            'issued_at' => $timestamp,
            'status' => 'diterima',
            'pengirim' => 'BI Fixture',
            'received_by' => $actorId,
            'received_at' => '2026-08-19 10:00:00',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $writer->table('surat_jalan_items')->insert([
            'surat_jalan_id' => $receivedShipmentId,
            'mitra_id' => $mitraId,
            'material_id' => $materialId,
            'qty' => '4.000',
            'qty_diterima' => '4.000',
            'qty_diretur' => '1.000',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $returnShipmentId = (int) $writer->table('surat_jalans')->insertGetId([
            'nomor' => 'SJ-BI-RET-'.$suffix,
            'tanggal' => '2026-08-19',
            'warehouse_asal_id' => $destinationWarehouseId,
            'warehouse_tujuan_id' => $warehouseId,
            'mitra_id' => $mitraId,
            'material_request_id' => $requestId,
            'project_id' => $projectId,
            'retur_dari_id' => $receivedShipmentId,
            'issued_by' => $actorId,
            'issued_at' => $timestamp,
            'status' => 'terbit',
            'pengirim' => 'BI Fixture Return',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $writer->table('surat_jalan_items')->insert([
            'surat_jalan_id' => $returnShipmentId,
            'mitra_id' => $mitraId,
            'material_id' => $materialId,
            'qty' => '1.000',
            'qty_diterima' => '0.000',
            'qty_diretur' => '0.000',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $transitShipmentId = (int) $writer->table('surat_jalans')->insertGetId([
            'nomor' => 'SJ-BI-T-'.$suffix,
            'tanggal' => '2026-08-18',
            'warehouse_asal_id' => $warehouseId,
            'warehouse_tujuan_id' => $destinationWarehouseId,
            'mitra_id' => $mitraId,
            'material_request_id' => $requestId,
            'project_id' => $projectId,
            'issued_by' => $actorId,
            'issued_at' => $timestamp,
            'status' => 'terbit',
            'pengirim' => 'BI Fixture',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $writer->table('surat_jalan_items')->insert([
            'surat_jalan_id' => $transitShipmentId,
            'mitra_id' => $mitraId,
            'material_id' => $materialId,
            'qty' => '1.000',
            'qty_diterima' => '0.000',
            'qty_diretur' => '0.000',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return [
            'actor_id' => $actorId,
            'material_id' => $materialId,
            'mitra_id' => $mitraId,
            'project_id' => $projectId,
            'request_id' => $requestId,
            'warehouse_id' => $warehouseId,
        ];
    }
}
