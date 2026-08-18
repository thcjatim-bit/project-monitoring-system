<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const VIEW_NAMES = [
        'v_projects',
        'v_project_steps',
        'v_kurva_s',
        'v_kurva_s_series',
        'v_stok',
        'v_transaksi_material',
        'v_request_material',
        'v_rekon_material',
        'v_harga_jasa_mitra',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DO $bi_preflight$
            BEGIN
                IF pg_catalog.to_regclass('public.project_rekons') IS NULL
                   OR pg_catalog.to_regclass('public.project_rekon_items') IS NULL THEN
                    RAISE EXCEPTION 'BI views require the Rekon Material source from issue #62';
                END IF;

                IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'pms_bi_reader') THEN
                    RAISE EXCEPTION 'Role pms_bi_reader must be provisioned outside the application migration';
                END IF;
                IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'pms_bi_view_owner') THEN
                    RAISE EXCEPTION 'Role pms_bi_view_owner must be provisioned outside the application migration';
                END IF;

                IF EXISTS (
                    SELECT 1
                    FROM pg_roles
                    WHERE rolname = 'pms_bi_reader'
                      AND (rolsuper OR rolbypassrls OR rolcreatedb OR rolcreaterole OR rolreplication OR NOT rolcanlogin)
                ) THEN
                    RAISE EXCEPTION 'Role pms_bi_reader has unsafe attributes';
                END IF;

                IF EXISTS (
                    SELECT 1
                    FROM pg_roles
                    WHERE rolname = 'pms_bi_view_owner'
                      AND (rolsuper OR rolbypassrls OR rolcreatedb OR rolcreaterole OR rolreplication OR rolcanlogin)
                ) THEN
                    RAISE EXCEPTION 'Role pms_bi_view_owner has unsafe attributes';
                END IF;

                IF EXISTS (
                    SELECT 1
                    FROM pg_roles
                    WHERE rolname = 'pms_bi_reader'
                      AND (
                          NOT ('app.is_thc=off' = ANY (COALESCE(rolconfig, ARRAY[]::text[])))
                          OR NOT ('app.mitra_id=-1' = ANY (COALESCE(rolconfig, ARRAY[]::text[])))
                      )
                ) THEN
                    RAISE EXCEPTION 'Role pms_bi_reader must have fail-closed context defaults';
                END IF;

                IF EXISTS (
                    SELECT 1
                    FROM pg_auth_members AS membership
                    JOIN pg_roles AS member ON member.oid = membership.member
                    WHERE member.rolname = 'pms_bi_reader'
                ) THEN
                    RAISE EXCEPTION 'Role pms_bi_reader must not inherit or SET ROLE to another role';
                END IF;

                IF EXISTS (
                    SELECT 1
                    FROM pg_auth_members AS membership
                    JOIN pg_roles AS member ON member.oid = membership.member
                    WHERE member.rolname = 'pms_bi_view_owner'
                ) THEN
                    RAISE EXCEPTION 'Role pms_bi_view_owner must not inherit another role';
                END IF;

                IF EXISTS (
                    SELECT 1
                    FROM pg_class AS relation
                    JOIN pg_namespace AS namespace ON namespace.oid = relation.relnamespace
                    WHERE namespace.nspname = 'public'
                      AND relation.relkind IN ('r', 'p')
                      AND relation.relname = ANY (ARRAY[
                          'projects', 'project_steps', 'project_baselines', 'project_baseline_days',
                          'project_progresses', 'project_rab_jasas', 'project_variation_orders',
                          'project_variation_order_items', 'warehouses', 'material_stoks',
                          'material_transaksis', 'surat_jalans', 'surat_jalan_items',
                          'material_requests', 'material_request_items', 'project_rekons',
                          'project_rekon_items', 'pks', 'mitra_harga_jasas'
                      ]::name[])
                      AND (NOT relation.relrowsecurity OR NOT relation.relforcerowsecurity)
                ) THEN
                    RAISE EXCEPTION 'Every tenant base relation used by BI views must have ENABLE and FORCE RLS';
                END IF;

                IF NOT pg_catalog.pg_has_role(current_user, 'pms_bi_view_owner', 'SET') THEN
                    RAISE EXCEPTION 'Migration role must be allowed to SET ROLE pms_bi_view_owner';
                END IF;

                IF EXISTS (
                    SELECT 1
                    FROM pg_class AS relation
                    JOIN pg_namespace AS namespace ON namespace.oid = relation.relnamespace
                    JOIN pg_roles AS owner_role ON owner_role.oid = relation.relowner
                    WHERE namespace.nspname = 'public'
                      AND relation.relkind IN ('r', 'p')
                      AND owner_role.rolname = 'pms_bi_view_owner'
                ) THEN
                    RAISE EXCEPTION 'Role pms_bi_view_owner must not own a public base relation';
                END IF;
            END
            $bi_preflight$;

            CREATE SCHEMA IF NOT EXISTS bi;
            ALTER SCHEMA bi OWNER TO pms_bi_view_owner;
            REVOKE ALL ON SCHEMA bi FROM PUBLIC;
            GRANT USAGE ON SCHEMA bi TO pms_bi_reader;
            REVOKE CREATE ON SCHEMA bi FROM PUBLIC, pms_bi_reader;

            GRANT USAGE ON SCHEMA public TO pms_bi_view_owner;
            GRANT SELECT ON TABLE
                public.projects,
                public.mitras,
                public.project_steps,
                public.project_baselines,
                public.project_baseline_days,
                public.project_progresses,
                public.project_rab_jasas,
                public.project_variation_orders,
                public.project_variation_order_items,
                public.materials,
                public.units,
                public.warehouses,
                public.material_stoks,
                public.material_transaksis,
                public.surat_jalans,
                public.surat_jalan_items,
                public.material_requests,
                public.material_request_items,
                public.project_rekons,
                public.project_rekon_items,
                public.pks,
                public.mitra_harga_jasas,
                public.pekerjaan_jasas
            TO pms_bi_view_owner;

            REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA public FROM pms_bi_reader;
            REVOKE ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public FROM pms_bi_reader;
            REVOKE ALL PRIVILEGES ON ALL FUNCTIONS IN SCHEMA public FROM pms_bi_reader;
            REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA bi FROM PUBLIC, pms_bi_reader;
        SQL);

        foreach ($this->viewStatements() as $statement) {
            DB::unprepared($statement);
        }

        foreach (self::VIEW_NAMES as $viewName) {
            DB::unprepared("ALTER VIEW bi.{$viewName} OWNER TO pms_bi_view_owner");
        }

        DB::unprepared(<<<'SQL'
            REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA bi FROM PUBLIC, pms_bi_reader;
            GRANT SELECT ON TABLE
                bi.v_projects,
                bi.v_project_steps,
                bi.v_kurva_s,
                bi.v_kurva_s_series,
                bi.v_stok,
                bi.v_transaksi_material,
                bi.v_request_material,
                bi.v_rekon_material,
                bi.v_harga_jasa_mitra
            TO pms_bi_reader;
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_reverse(self::VIEW_NAMES) as $viewName) {
            DB::unprepared("DROP VIEW IF EXISTS bi.{$viewName}");
        }

        DB::unprepared(<<<'SQL'
            REVOKE USAGE ON SCHEMA bi FROM pms_bi_reader;
            REVOKE SELECT ON TABLE
                public.projects,
                public.mitras,
                public.project_steps,
                public.project_baselines,
                public.project_baseline_days,
                public.project_progresses,
                public.project_rab_jasas,
                public.project_variation_orders,
                public.project_variation_order_items,
                public.materials,
                public.units,
                public.warehouses,
                public.material_stoks,
                public.material_transaksis,
                public.surat_jalans,
                public.surat_jalan_items,
                public.material_requests,
                public.material_request_items,
                public.project_rekons,
                public.project_rekon_items,
                public.pks,
                public.mitra_harga_jasas,
                public.pekerjaan_jasas
            FROM pms_bi_view_owner;
            DROP SCHEMA IF EXISTS bi;
        SQL);
    }

    /** @return list<string> */
    private function viewStatements(): array
    {
        return [
            <<<'SQL'
                CREATE VIEW bi.v_projects
                WITH (security_barrier = true, security_invoker = false) AS
                WITH context AS (
                    SELECT pg_catalog.current_setting('app.is_thc', true) = 'on'
                               AND pg_catalog.current_setting('app.mitra_id', true) = '' AS allowed,
                           COALESCE(
                               NULLIF(pg_catalog.current_setting('app.reporting_as_of', true), '')::date,
                               (CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Jakarta')::date
                           ) AS reporting_as_of
                )
                SELECT p.id::bigint AS project_id,
                       p.id_project::text AS id_project,
                       p.nama::text AS project_nama,
                       p.mitra_id::bigint AS mitra_id,
                       mitra.kode::text AS mitra_kode,
                       mitra.nama::text AS mitra_nama,
                       p.status_project::text AS status_project,
                       p.toc::date AS toc,
                       original_baseline.kind::text AS original_baseline_kind,
                       original_baseline.version::integer AS original_baseline_version,
                       original_baseline.toc::date AS original_baseline_toc,
                       revised_baseline.kind::text AS revised_baseline_kind,
                       revised_baseline.version::integer AS revised_baseline_version,
                       revised_baseline.toc::date AS revised_baseline_toc,
                       COALESCE(revised_baseline.kind, original_baseline.kind)::text AS active_baseline_kind,
                       COALESCE(revised_baseline.version, original_baseline.version)::integer AS active_baseline_version,
                       COALESCE(revised_baseline.toc, original_baseline.toc)::date AS active_baseline_toc,
                       context.reporting_as_of,
                       CURRENT_TIMESTAMP AS read_at
                FROM public.projects AS p
                JOIN public.mitras AS mitra ON mitra.id = p.mitra_id
                CROSS JOIN context
                LEFT JOIN LATERAL (
                    SELECT b.kind, b.version, b.toc
                    FROM public.project_baselines AS b
                    WHERE b.project_id = p.id AND b.kind = 'original'
                    ORDER BY b.version DESC, b.id DESC
                    LIMIT 1
                ) AS original_baseline ON true
                LEFT JOIN LATERAL (
                    SELECT b.kind, b.version, b.toc
                    FROM public.project_baselines AS b
                    WHERE b.project_id = p.id AND b.kind = 'revised'
                    ORDER BY b.version DESC, b.id DESC
                    LIMIT 1
                ) AS revised_baseline ON true
                WHERE context.allowed;
                SQL,
            <<<'SQL'
                CREATE VIEW bi.v_project_steps
                WITH (security_barrier = true, security_invoker = false) AS
                WITH context AS (
                    SELECT pg_catalog.current_setting('app.is_thc', true) = 'on'
                               AND pg_catalog.current_setting('app.mitra_id', true) = '' AS allowed
                )
                SELECT step.id::bigint AS project_step_id,
                       project.id::bigint AS project_id,
                       project.id_project::text AS id_project,
                       project.nama::text AS project_nama,
                       step.mitra_id::bigint AS mitra_id,
                       step.step::text AS step_code,
                       step.urutan::integer AS step_order,
                       step.status::text AS step_status,
                       step.completed_at::timestamptz AS completed_at,
                       CURRENT_TIMESTAMP AS read_at
                FROM public.project_steps AS step
                JOIN public.projects AS project ON project.id = step.project_id
                CROSS JOIN context
                WHERE context.allowed;
                SQL,
            <<<'SQL'
                CREATE VIEW bi.v_stok
                WITH (security_barrier = true, security_invoker = false) AS
                WITH context AS (
                    SELECT pg_catalog.current_setting('app.is_thc', true) = 'on'
                               AND pg_catalog.current_setting('app.mitra_id', true) = '' AS allowed
                )
                SELECT stock.id::bigint AS stock_id,
                       stock.lokasi_tipe::text AS location_type,
                       stock.lokasi_id::bigint AS location_id,
                       COALESCE(project.id, transit_project.id)::bigint AS project_id,
                       COALESCE(project.id_project, transit_project.id_project)::text AS id_project,
                       stock.warehouse_id::bigint AS warehouse_id,
                       warehouse.kode::text AS warehouse_kode,
                       warehouse.nama::text AS warehouse_nama,
                       stock.material_id::bigint AS material_id,
                       material.kode::text AS material_kode,
                       material.nama::text AS material_nama,
                       unit.kode::text AS unit_kode,
                       unit.nama::text AS unit_nama,
                       COALESCE(stock.mitra_id, warehouse.mitra_id, project.mitra_id, transit_project.mitra_id)::bigint AS mitra_id,
                       CASE
                           WHEN stock.lokasi_tipe = 'warehouse' THEN warehouse.nama
                           WHEN stock.lokasi_tipe = 'transit' THEN COALESCE('Transit ' || surat_jalan.nomor, 'Transit')
                           WHEN stock.lokasi_tipe IN ('project', 'terpasang') THEN COALESCE(project.nama, transit_project.nama)
                           ELSE stock.lokasi_tipe
                       END::text AS location_name,
                       stock.qty::numeric(18, 3) AS qty,
                       CASE WHEN stock.lokasi_tipe = 'warehouse' THEN stock.qty ELSE 0::numeric END::numeric(18, 3) AS available_qty,
                       (stock.lokasi_tipe = 'warehouse') AS is_warehouse_available,
                       CURRENT_TIMESTAMP AS read_at
                FROM public.material_stoks AS stock
                JOIN public.materials AS material ON material.id = stock.material_id
                JOIN public.units AS unit ON unit.id = material.unit_id
                JOIN public.warehouses AS warehouse ON warehouse.id = stock.warehouse_id
                LEFT JOIN public.projects AS project
                    ON stock.lokasi_tipe IN ('project', 'terpasang')
                   AND project.id = stock.lokasi_id
                LEFT JOIN public.surat_jalans AS surat_jalan
                    ON stock.lokasi_tipe = 'transit'
                   AND surat_jalan.id = stock.lokasi_id
                LEFT JOIN public.projects AS transit_project ON transit_project.id = surat_jalan.project_id
                CROSS JOIN context
                WHERE context.allowed;
                SQL,
            <<<'SQL'
                CREATE VIEW bi.v_transaksi_material
                WITH (security_barrier = true, security_invoker = false) AS
                WITH context AS (
                    SELECT pg_catalog.current_setting('app.is_thc', true) = 'on'
                               AND pg_catalog.current_setting('app.mitra_id', true) = '' AS allowed,
                           COALESCE(
                               NULLIF(pg_catalog.current_setting('app.reporting_as_of', true), '')::date,
                               (CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Jakarta')::date
                           ) AS reporting_as_of
                )
                SELECT transaction_log.id::bigint AS material_transaction_id,
                       transaction_log.created_at::timestamptz AS event_at,
                       transaction_log.jenis_transaksi::text AS transaction_type,
                       transaction_log.material_id::bigint AS material_id,
                       material.kode::text AS material_kode,
                       material.nama::text AS material_nama,
                       unit.kode::text AS unit_kode,
                       unit.nama::text AS unit_nama,
                       transaction_log.warehouse_id::bigint AS warehouse_id,
                       warehouse.kode::text AS warehouse_kode,
                       warehouse.nama::text AS warehouse_nama,
                       transaction_log.project_id::bigint AS project_id,
                       project.id_project::text AS id_project,
                       transaction_log.surat_jalan_id::bigint AS surat_jalan_id,
                       surat_jalan.nomor::text AS surat_jalan_nomor,
                       transaction_log.lokasi_tipe::text AS location_type,
                       transaction_log.lokasi_id::bigint AS location_id,
                       transaction_log.qty_delta::numeric(18, 3) AS qty_delta,
                       transaction_log.koreksi_dari_id::bigint AS correction_transaction_id,
                       transaction_log.mitra_id::bigint AS mitra_id,
                       context.reporting_as_of,
                       CURRENT_TIMESTAMP AS read_at
                FROM public.material_transaksis AS transaction_log
                JOIN public.materials AS material ON material.id = transaction_log.material_id
                JOIN public.units AS unit ON unit.id = material.unit_id
                JOIN public.warehouses AS warehouse ON warehouse.id = transaction_log.warehouse_id
                LEFT JOIN public.projects AS project ON project.id = transaction_log.project_id
                LEFT JOIN public.surat_jalans AS surat_jalan ON surat_jalan.id = transaction_log.surat_jalan_id
                CROSS JOIN context
                WHERE context.allowed
                  AND ((transaction_log.created_at AT TIME ZONE 'UTC') AT TIME ZONE 'Asia/Jakarta')::date <= context.reporting_as_of;
                SQL,
            <<<'SQL'
                CREATE VIEW bi.v_request_material
                WITH (security_barrier = true, security_invoker = false) AS
                WITH context AS (
                    SELECT pg_catalog.current_setting('app.is_thc', true) = 'on'
                               AND pg_catalog.current_setting('app.mitra_id', true) = '' AS allowed,
                           COALESCE(
                               NULLIF(pg_catalog.current_setting('app.reporting_as_of', true), '')::date,
                               (CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Jakarta')::date
                           ) AS reporting_as_of
                ), return_quantities AS (
                    SELECT return_document.retur_dari_id AS original_surat_jalan_id,
                           return_item.material_id,
                           pg_catalog.SUM(GREATEST(return_item.qty, 0)) AS qty_diretur
                    FROM public.surat_jalans AS return_document
                    JOIN public.surat_jalan_items AS return_item
                      ON return_item.surat_jalan_id = return_document.id
                    CROSS JOIN context
                    WHERE return_document.retur_dari_id IS NOT NULL
                      AND return_document.status <> 'dibatalkan'
                      AND return_document.tanggal <= context.reporting_as_of
                    GROUP BY return_document.retur_dari_id, return_item.material_id
                ), delivery_facts AS (
                    SELECT surat_jalan.id AS surat_jalan_id,
                           surat_jalan.material_request_id,
                           surat_jalan_item.material_id,
                           surat_jalan_item.qty,
                           surat_jalan_item.qty_diterima,
                           COALESCE(return_quantities.qty_diretur, 0) AS qty_diretur,
                           (
                               (
                                   surat_jalan.received_at IS NOT NULL
                                   AND ((surat_jalan.received_at AT TIME ZONE 'UTC') AT TIME ZONE 'Asia/Jakarta')::date <= context.reporting_as_of
                               )
                               OR (
                                   surat_jalan.received_at IS NULL
                                   AND surat_jalan.status = 'diterima'
                               )
                               OR (
                                   surat_jalan.received_at IS NULL
                                   AND surat_jalan.status = 'terbit'
                                   AND surat_jalan_item.qty_diterima > 0
                                   AND ((surat_jalan_item.updated_at AT TIME ZONE 'UTC') AT TIME ZONE 'Asia/Jakarta')::date <= context.reporting_as_of
                               )
                           ) AS received_as_of
                    FROM public.surat_jalans AS surat_jalan
                    JOIN public.surat_jalan_items AS surat_jalan_item
                      ON surat_jalan_item.surat_jalan_id = surat_jalan.id
                    LEFT JOIN return_quantities
                      ON return_quantities.original_surat_jalan_id = surat_jalan.id
                     AND return_quantities.material_id = surat_jalan_item.material_id
                    CROSS JOIN context
                    WHERE surat_jalan.material_request_id IS NOT NULL
                      AND surat_jalan.status <> 'dibatalkan'
                      AND surat_jalan.retur_dari_id IS NULL
                      AND surat_jalan.tanggal <= context.reporting_as_of
                ), deliveries AS (
                    SELECT material_request_id,
                           material_id,
                           pg_catalog.SUM(GREATEST(CASE WHEN received_as_of THEN qty_diterima ELSE 0 END - qty_diretur, 0)) AS qty_diterima,
                           pg_catalog.SUM(LEAST(CASE WHEN received_as_of THEN qty_diterima ELSE 0 END, qty_diretur)) AS qty_diretur,
                           pg_catalog.SUM(CASE WHEN received_as_of THEN GREATEST(qty - qty_diterima, 0) ELSE GREATEST(qty, 0) END) AS qty_transit
                    FROM delivery_facts
                    GROUP BY material_request_id, material_id
                )
                SELECT request_item.id::bigint AS request_item_id,
                       request.id::bigint AS material_request_id,
                       request.mitra_id::bigint AS mitra_id,
                       mitra.kode::text AS mitra_kode,
                       mitra.nama::text AS mitra_nama,
                       request.project_id::bigint AS project_id,
                       project.id_project::text AS id_project,
                       project.nama::text AS project_nama,
                       request.status::text AS workflow_status,
                       request_item.material_id::bigint AS material_id,
                       material.kode::text AS material_kode,
                       material.nama::text AS material_nama,
                       unit.kode::text AS unit_kode,
                       unit.nama::text AS unit_nama,
                       request_item.qty::numeric(18, 3) AS qty_diminta,
                       COALESCE(deliveries.qty_diterima, 0)::numeric(18, 3) AS qty_diterima,
                       COALESCE(deliveries.qty_diretur, 0)::numeric(18, 3) AS qty_diretur,
                       COALESCE(deliveries.qty_transit, 0)::numeric(18, 3) AS qty_transit,
                       GREATEST(request_item.qty - COALESCE(deliveries.qty_diterima, 0), 0)::numeric(18, 3) AS qty_sisa,
                       CASE
                           WHEN COALESCE(deliveries.qty_diterima, 0) = 0 THEN 'belum_terpenuhi'
                           WHEN COALESCE(deliveries.qty_diterima, 0) >= request_item.qty THEN 'selesai'
                           ELSE 'terpenuhi_sebagian'
                       END::text AS fulfillment_status,
                       context.reporting_as_of,
                       CURRENT_TIMESTAMP AS read_at
                FROM public.material_requests AS request
                JOIN public.material_request_items AS request_item
                  ON request_item.material_request_id = request.id
                 AND request_item.mitra_id = request.mitra_id
                JOIN public.mitras AS mitra ON mitra.id = request.mitra_id
                LEFT JOIN public.projects AS project ON project.id = request.project_id
                JOIN public.materials AS material ON material.id = request_item.material_id
                JOIN public.units AS unit ON unit.id = material.unit_id
                LEFT JOIN deliveries
                  ON deliveries.material_request_id = request.id
                 AND deliveries.material_id = request_item.material_id
                CROSS JOIN context
                WHERE context.allowed;
                SQL,
            <<<'SQL'
                CREATE VIEW bi.v_harga_jasa_mitra
                WITH (security_barrier = true, security_invoker = false) AS
                WITH context AS (
                    SELECT pg_catalog.current_setting('app.is_thc', true) = 'on'
                               AND pg_catalog.current_setting('app.mitra_id', true) = '' AS allowed,
                           COALESCE(
                               NULLIF(pg_catalog.current_setting('app.reporting_as_of', true), '')::date,
                               (CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Jakarta')::date
                           ) AS reporting_as_of
                )
                SELECT price.id::bigint AS mitra_harga_jasa_id,
                       price.mitra_id::bigint AS mitra_id,
                       mitra.kode::text AS mitra_kode,
                       mitra.nama::text AS mitra_nama,
                       price.pekerjaan_jasa_id::bigint AS pekerjaan_jasa_id,
                       pekerjaan.kode::text AS pekerjaan_jasa_kode,
                       pekerjaan.nama::text AS pekerjaan_jasa_nama,
                       price.pks_id::bigint AS pks_id,
                       pks.nomor::text AS pks_nomor,
                       pks.tanggal_mulai::date AS pks_tanggal_mulai,
                       pks.tanggal_berakhir::date AS pks_tanggal_berakhir,
                       price.harga::numeric(18, 2) AS harga,
                       price.status::text AS status,
                       price.berlaku_mulai::date AS berlaku_mulai,
                       price.revisi_dari_id::bigint AS revisi_dari_id,
                       (
                           price.status = 'disetujui'
                           AND price.berlaku_mulai <= context.reporting_as_of
                           AND NOT EXISTS (
                               SELECT 1
                               FROM public.mitra_harga_jasas AS later_price
                               WHERE later_price.mitra_id = price.mitra_id
                                 AND later_price.pekerjaan_jasa_id = price.pekerjaan_jasa_id
                                 AND later_price.status = 'disetujui'
                                 AND later_price.berlaku_mulai <= context.reporting_as_of
                                 AND (
                                     later_price.berlaku_mulai > price.berlaku_mulai
                                     OR (
                                         later_price.berlaku_mulai = price.berlaku_mulai
                                         AND later_price.id > price.id
                                     )
                                 )
                           )
                       ) AS is_effective_price,
                       context.reporting_as_of,
                       CURRENT_TIMESTAMP AS read_at
                FROM public.mitra_harga_jasas AS price
                JOIN public.mitras AS mitra ON mitra.id = price.mitra_id
                JOIN public.pks AS pks ON pks.id = price.pks_id
                JOIN public.pekerjaan_jasas AS pekerjaan ON pekerjaan.id = price.pekerjaan_jasa_id
                CROSS JOIN context
                WHERE context.allowed;
                SQL,
            <<<'SQL'
                CREATE VIEW bi.v_rekon_material
                WITH (security_barrier = true, security_invoker = false) AS
                WITH context AS (
                    SELECT pg_catalog.current_setting('app.is_thc', true) = 'on'
                               AND pg_catalog.current_setting('app.mitra_id', true) = '' AS allowed,
                           COALESCE(
                               NULLIF(pg_catalog.current_setting('app.reporting_as_of', true), '')::date,
                               (CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Jakarta')::date
                           ) AS reporting_as_of
                )
                SELECT rekon.id::bigint AS project_rekon_id,
                       rekon.nomor::text AS rekon_nomor,
                       rekon.mitra_id::bigint AS mitra_id,
                       mitra.kode::text AS mitra_kode,
                       mitra.nama::text AS mitra_nama,
                       rekon.project_id::bigint AS project_id,
                       project.id_project::text AS id_project,
                       project.nama::text AS project_nama,
                       project.status_project::text AS status_project,
                       rekon.source::text AS source,
                       rekon.status::text AS status,
                       rekon.koreksi_dari_id::bigint AS correction_source_id,
                       rekon.approved_at::timestamptz AS approved_at,
                       context.reporting_as_of,
                       item.id::bigint AS project_rekon_item_id,
                       item.warehouse_id::bigint AS warehouse_id,
                       warehouse.kode::text AS warehouse_kode,
                       warehouse.nama::text AS warehouse_nama,
                       item.material_id::bigint AS material_id,
                       material.kode::text AS material_kode,
                       material.nama::text AS material_nama,
                       unit.kode::text AS unit_kode,
                       unit.nama::text AS unit_nama,
                       item.material_sn_id::bigint AS material_sn_id,
                       item.drum_id::bigint AS drum_id,
                       item.keluar_gudang::numeric(18, 3) AS keluar_gudang,
                       item.terpasang::numeric(18, 3) AS terpasang,
                       item.sisa_project::numeric(18, 3) AS sisa_project,
                       item.dikembalikan::numeric(18, 3) AS dikembalikan,
                       item.hilang_rusak::numeric(18, 3) AS hilang_rusak,
                       item.kategori_hilang_rusak::text AS kategori_hilang_rusak,
                       item.penanggung_jawab::text AS penanggung_jawab,
                       NOT EXISTS (
                           SELECT 1
                           FROM public.project_rekons AS correction
                           WHERE correction.koreksi_dari_id = rekon.id
                             AND correction.status IN ('diajukan', 'disetujui')
                             AND ((correction.created_at AT TIME ZONE 'UTC') AT TIME ZONE 'Asia/Jakarta')::date <= context.reporting_as_of
                       ) AS is_active_correction,
                       (
                           rekon.status = 'disetujui'
                           AND rekon.approved_at IS NOT NULL
                           AND ((rekon.approved_at AT TIME ZONE 'UTC') AT TIME ZONE 'Asia/Jakarta')::date <= context.reporting_as_of
                           AND NOT EXISTS (
                               SELECT 1
                               FROM public.project_rekons AS correction
                               WHERE correction.koreksi_dari_id = rekon.id
                                 AND correction.status IN ('diajukan', 'disetujui')
                                 AND ((correction.created_at AT TIME ZONE 'UTC') AT TIME ZONE 'Asia/Jakarta')::date <= context.reporting_as_of
                           )
                       ) AS is_effective_approved,
                       CURRENT_TIMESTAMP AS read_at
                FROM public.project_rekons AS rekon
                JOIN public.project_rekon_items AS item
                  ON item.project_rekon_id = rekon.id
                 AND item.mitra_id = rekon.mitra_id
                JOIN public.mitras AS mitra ON mitra.id = rekon.mitra_id
                JOIN public.projects AS project ON project.id = rekon.project_id
                JOIN public.warehouses AS warehouse ON warehouse.id = item.warehouse_id
                JOIN public.materials AS material ON material.id = item.material_id
                JOIN public.units AS unit ON unit.id = material.unit_id
                CROSS JOIN context
                WHERE context.allowed
                  AND ((rekon.created_at AT TIME ZONE 'UTC') AT TIME ZONE 'Asia/Jakarta')::date <= context.reporting_as_of;
                SQL,
            <<<'SQL'
                CREATE VIEW bi.v_kurva_s
                WITH (security_barrier = true, security_invoker = false) AS
                WITH context AS (
                    SELECT pg_catalog.current_setting('app.is_thc', true) = 'on'
                               AND pg_catalog.current_setting('app.mitra_id', true) = '' AS allowed,
                           COALESCE(
                               NULLIF(pg_catalog.current_setting('app.reporting_as_of', true), '')::date,
                               (CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Jakarta')::date
                           ) AS reporting_as_of
                ), project_facts AS (
                    SELECT project.id AS project_id,
                           project.id_project,
                           project.nama AS project_nama,
                           project.mitra_id,
                           mitra.kode AS mitra_kode,
                           mitra.nama AS mitra_nama,
                           context.reporting_as_of,
                           GREATEST(
                               0::numeric,
                               pg_catalog.ROUND(
                                   COALESCE((SELECT pg_catalog.SUM(rab.total_nilai) FROM public.project_rab_jasas AS rab WHERE rab.project_id = project.id), 0)
                                   + COALESCE((
                                       SELECT pg_catalog.SUM(
                                           CASE
                                               WHEN created_rab.id IS NULL THEN variation_item.quantity_delta * variation_item.harga_satuan
                                               ELSE 0
                                           END
                                       )
                                       FROM public.project_variation_order_items AS variation_item
                                       JOIN public.project_variation_orders AS variation_order
                                         ON variation_order.id = variation_item.project_variation_order_id
                                       LEFT JOIN public.project_rab_jasas AS created_rab
                                         ON created_rab.id = variation_item.rab_jasa_id
                                        AND created_rab.variation_order_id = variation_item.project_variation_order_id
                                       WHERE variation_order.project_id = project.id
                                         AND variation_item.status = 'applied'
                                   ), 0),
                                   2
                               )
                           )::numeric(20, 2) AS grand_total_rab_jasa,
                           COALESCE((
                               SELECT pg_catalog.SUM(progress.qty * rab.harga_satuan)
                               FROM public.project_progresses AS progress
                               JOIN public.project_rab_jasas AS rab ON rab.id = progress.project_rab_jasa_id
                               WHERE progress.project_id = project.id
                                 AND progress.status = 'verified'
                                 AND progress.actual_date <= context.reporting_as_of
                           ), 0)::numeric(20, 2) AS verified_value,
                           COALESCE((
                               SELECT pg_catalog.SUM(progress.qty * rab.harga_satuan)
                               FROM public.project_progresses AS progress
                               JOIN public.project_rab_jasas AS rab ON rab.id = progress.project_rab_jasa_id
                               WHERE progress.project_id = project.id
                                 AND progress.status = 'pending'
                                 AND progress.actual_date <= context.reporting_as_of
                           ), 0)::numeric(20, 2) AS pending_value,
                           original_baseline.kind AS original_baseline_kind,
                           original_baseline.version AS original_baseline_version,
                           original_baseline.toc AS original_baseline_toc,
                           revised_baseline.kind AS revised_baseline_kind,
                           revised_baseline.version AS revised_baseline_version,
                           revised_baseline.toc AS revised_baseline_toc,
                           COALESCE(revised_baseline.kind, original_baseline.kind) AS active_baseline_kind,
                           COALESCE(revised_baseline.version, original_baseline.version) AS active_baseline_version,
                           COALESCE(revised_baseline.toc, original_baseline.toc) AS active_baseline_toc,
                           plan_day.cumulative_percent AS active_plan_day_percent
                    FROM public.projects AS project
                    JOIN public.mitras AS mitra ON mitra.id = project.mitra_id
                    CROSS JOIN context
                    LEFT JOIN LATERAL (
                        SELECT baseline.kind, baseline.version, baseline.toc, baseline.id
                        FROM public.project_baselines AS baseline
                        WHERE baseline.project_id = project.id AND baseline.kind = 'original'
                        ORDER BY baseline.version DESC, baseline.id DESC
                        LIMIT 1
                    ) AS original_baseline ON true
                    LEFT JOIN LATERAL (
                        SELECT baseline.kind, baseline.version, baseline.toc, baseline.id
                        FROM public.project_baselines AS baseline
                        WHERE baseline.project_id = project.id AND baseline.kind = 'revised'
                        ORDER BY baseline.version DESC, baseline.id DESC
                        LIMIT 1
                    ) AS revised_baseline ON true
                    LEFT JOIN LATERAL (
                        SELECT baseline_day.cumulative_percent
                        FROM public.project_baseline_days AS baseline_day
                        WHERE baseline_day.project_baseline_id = COALESCE(revised_baseline.id, original_baseline.id)
                          AND baseline_day.plan_date <= context.reporting_as_of
                        ORDER BY baseline_day.plan_date DESC
                        LIMIT 1
                    ) AS plan_day ON true
                    WHERE context.allowed
                ), shadow_values AS (
                    SELECT project_facts.*,
                           (
                               COALESCE((
                                   SELECT pg_catalog.SUM(progress.qty * rab.harga_satuan)
                                   FROM public.project_progresses AS progress
                                   JOIN public.project_rab_jasas AS rab ON rab.id = progress.project_rab_jasa_id
                                   WHERE progress.project_id = project_facts.project_id
                                     AND progress.status = 'verified'
                                     AND progress.actual_date <= project_facts.reporting_as_of
                                     AND progress.actual_date <= COALESCE((
                                         SELECT pg_catalog.MIN(pending.actual_date)
                                         FROM public.project_progresses AS pending
                                         WHERE pending.project_id = project_facts.project_id
                                           AND pending.status = 'pending'
                                           AND pending.actual_date <= project_facts.reporting_as_of
                                     ), DATE '0001-01-01')
                               ), 0) + project_facts.pending_value
                           )::numeric(20, 2) AS pending_shadow_value
                    FROM project_facts
                ), percentages AS (
                    SELECT shadow_values.*,
                           CASE
                               WHEN grand_total_rab_jasa > 0 THEN LEAST(100::numeric, pg_catalog.ROUND(verified_value / grand_total_rab_jasa * 100, 2))
                               ELSE 0::numeric
                           END AS verified_percent,
                           CASE
                               WHEN grand_total_rab_jasa > 0 THEN LEAST(100::numeric, pg_catalog.ROUND(pending_value / grand_total_rab_jasa * 100, 2))
                               ELSE 0::numeric
                           END AS pending_percent,
                           CASE
                               WHEN grand_total_rab_jasa > 0 THEN LEAST(100::numeric, pg_catalog.ROUND(pending_shadow_value / grand_total_rab_jasa * 100, 2))
                               ELSE 0::numeric
                           END AS pending_shadow_percent,
                           CASE
                               WHEN active_baseline_toc IS NOT NULL
                                AND reporting_as_of > active_baseline_toc
                                AND revised_baseline_kind IS NULL THEN 100::numeric
                               ELSE COALESCE(active_plan_day_percent, 0)::numeric
                           END AS plan_percent,
                           (
                               active_baseline_toc IS NOT NULL
                               AND reporting_as_of > active_baseline_toc
                           ) AS overdue,
                           (
                               original_baseline_toc IS NOT NULL
                               AND revised_baseline_kind IS NULL
                               AND reporting_as_of > original_baseline_toc
                           ) AS baseline_flat_after_toc
                    FROM shadow_values
                )
                SELECT project_id::bigint,
                       id_project::text,
                       project_nama::text,
                       mitra_id::bigint,
                       mitra_kode::text,
                       mitra_nama::text,
                       reporting_as_of::date,
                       grand_total_rab_jasa::numeric(20, 2),
                       verified_value::numeric(20, 2),
                       verified_percent::numeric(8, 2),
                       pending_value::numeric(20, 2),
                       pending_percent::numeric(8, 2),
                       pending_shadow_value::numeric(20, 2),
                       pending_shadow_percent::numeric(8, 2),
                       plan_percent::numeric(8, 2),
                       CASE WHEN plan_percent > 0 THEN pg_catalog.ROUND(verified_percent / plan_percent, 4) END::numeric(12, 4) AS spi,
                       CASE
                           WHEN plan_percent = 0 THEN 'na'
                           WHEN verified_percent / plan_percent >= 1 THEN 'green'
                           WHEN verified_percent / plan_percent >= 0.9 THEN 'yellow'
                           ELSE 'red'
                       END::text AS spi_status,
                       original_baseline_kind::text,
                       original_baseline_version::integer,
                       original_baseline_toc::date,
                       revised_baseline_kind::text,
                       revised_baseline_version::integer,
                       revised_baseline_toc::date,
                       active_baseline_kind::text,
                       active_baseline_version::integer,
                       active_baseline_toc::date,
                       overdue,
                       baseline_flat_after_toc,
                       CURRENT_TIMESTAMP AS read_at
                FROM percentages;
                SQL,
            <<<'SQL'
                CREATE VIEW bi.v_kurva_s_series
                WITH (security_barrier = true, security_invoker = false) AS
                WITH context AS (
                    SELECT pg_catalog.current_setting('app.is_thc', true) = 'on'
                               AND pg_catalog.current_setting('app.mitra_id', true) = '' AS allowed,
                           COALESCE(
                               NULLIF(pg_catalog.current_setting('app.reporting_as_of', true), '')::date,
                               (CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Jakarta')::date
                           ) AS reporting_as_of
                ), projects_visible AS (
                    SELECT project.id AS project_id,
                           project.id_project,
                           project.mitra_id,
                           context.reporting_as_of,
                           original_baseline.id AS original_baseline_id,
                           original_baseline.toc AS original_baseline_toc,
                           revised_baseline.id AS revised_baseline_id,
                           revised_baseline.toc AS revised_baseline_toc
                    FROM public.projects AS project
                    CROSS JOIN context
                    LEFT JOIN LATERAL (
                        SELECT baseline.id, baseline.toc
                        FROM public.project_baselines AS baseline
                        WHERE baseline.project_id = project.id AND baseline.kind = 'original'
                        ORDER BY baseline.version DESC, baseline.id DESC
                        LIMIT 1
                    ) AS original_baseline ON true
                    LEFT JOIN LATERAL (
                        SELECT baseline.id, baseline.toc
                        FROM public.project_baselines AS baseline
                        WHERE baseline.project_id = project.id AND baseline.kind = 'revised'
                        ORDER BY baseline.version DESC, baseline.id DESC
                        LIMIT 1
                    ) AS revised_baseline ON true
                    WHERE context.allowed
                ), grand_totals AS (
                    SELECT visible.project_id,
                           visible.id_project,
                           visible.mitra_id,
                           visible.reporting_as_of,
                           GREATEST(
                               0::numeric,
                               pg_catalog.ROUND(
                                   COALESCE((SELECT pg_catalog.SUM(rab.total_nilai) FROM public.project_rab_jasas AS rab WHERE rab.project_id = visible.project_id), 0)
                                   + COALESCE((
                                       SELECT pg_catalog.SUM(
                                           CASE
                                               WHEN created_rab.id IS NULL THEN variation_item.quantity_delta * variation_item.harga_satuan
                                               ELSE 0
                                           END
                                       )
                                       FROM public.project_variation_order_items AS variation_item
                                       JOIN public.project_variation_orders AS variation_order
                                         ON variation_order.id = variation_item.project_variation_order_id
                                       LEFT JOIN public.project_rab_jasas AS created_rab
                                         ON created_rab.id = variation_item.rab_jasa_id
                                        AND created_rab.variation_order_id = variation_item.project_variation_order_id
                                       WHERE variation_order.project_id = visible.project_id
                                         AND variation_item.status = 'applied'
                                   ), 0),
                                   2
                               )
                           )::numeric(20, 2) AS grand_total_rab_jasa
                    FROM projects_visible AS visible
                ), verified_daily AS (
                    SELECT total.project_id,
                           total.id_project,
                           total.mitra_id,
                           total.reporting_as_of,
                           progress.actual_date AS series_date,
                           pg_catalog.SUM(progress.qty * rab.harga_satuan)::numeric(20, 2) AS series_value
                    FROM grand_totals AS total
                    JOIN public.project_progresses AS progress ON progress.project_id = total.project_id
                    JOIN public.project_rab_jasas AS rab ON rab.id = progress.project_rab_jasa_id
                    WHERE progress.status = 'verified' AND progress.actual_date <= total.reporting_as_of
                    GROUP BY total.project_id, total.id_project, total.mitra_id, total.reporting_as_of, progress.actual_date
                ), pending_daily AS (
                    SELECT total.project_id,
                           total.id_project,
                           total.mitra_id,
                           total.reporting_as_of,
                           progress.actual_date AS series_date,
                           pg_catalog.SUM(progress.qty * rab.harga_satuan)::numeric(20, 2) AS series_value
                    FROM grand_totals AS total
                    JOIN public.project_progresses AS progress ON progress.project_id = total.project_id
                    JOIN public.project_rab_jasas AS rab ON rab.id = progress.project_rab_jasa_id
                    WHERE progress.status = 'pending' AND progress.actual_date <= total.reporting_as_of
                    GROUP BY total.project_id, total.id_project, total.mitra_id, total.reporting_as_of, progress.actual_date
                ), verified_cumulative AS (
                    SELECT daily.*,
                           pg_catalog.SUM(daily.series_value) OVER (PARTITION BY daily.project_id ORDER BY daily.series_date) AS cumulative_value
                    FROM verified_daily AS daily
                ), pending_cumulative AS (
                    SELECT daily.*,
                           pg_catalog.SUM(daily.series_value) OVER (PARTITION BY daily.project_id ORDER BY daily.series_date) AS cumulative_value
                    FROM pending_daily AS daily
                ), original_days AS (
                    SELECT total.project_id,
                           total.id_project,
                           total.mitra_id,
                           total.reporting_as_of,
                           'original_baseline'::text AS series_kind,
                           baseline_day.plan_date AS series_date,
                           NULL::numeric AS series_value,
                           NULL::numeric AS cumulative_value,
                           baseline_day.cumulative_percent::numeric(8, 3) AS cumulative_percent
                    FROM grand_totals AS total
                    JOIN projects_visible AS visible ON visible.project_id = total.project_id
                    JOIN public.project_baseline_days AS baseline_day ON baseline_day.project_baseline_id = visible.original_baseline_id
                ), revised_days AS (
                    SELECT total.project_id,
                           total.id_project,
                           total.mitra_id,
                           total.reporting_as_of,
                           'revised_baseline'::text AS series_kind,
                           baseline_day.plan_date AS series_date,
                           NULL::numeric AS series_value,
                           NULL::numeric AS cumulative_value,
                           baseline_day.cumulative_percent::numeric(8, 3) AS cumulative_percent
                    FROM grand_totals AS total
                    JOIN projects_visible AS visible ON visible.project_id = total.project_id
                    JOIN public.project_baseline_days AS baseline_day ON baseline_day.project_baseline_id = visible.revised_baseline_id
                ), original_flat_after_toc AS (
                    SELECT total.project_id,
                           total.id_project,
                           total.mitra_id,
                           total.reporting_as_of,
                           'original_baseline'::text AS series_kind,
                           total.reporting_as_of AS series_date,
                           NULL::numeric AS series_value,
                           NULL::numeric AS cumulative_value,
                           100::numeric(8, 3) AS cumulative_percent
                    FROM grand_totals AS total
                    JOIN projects_visible AS visible ON visible.project_id = total.project_id
                    WHERE visible.revised_baseline_id IS NULL
                      AND visible.original_baseline_toc IS NOT NULL
                      AND total.reporting_as_of > visible.original_baseline_toc
                ), pending_shadow_anchor AS (
                    SELECT pending.project_id,
                           pending.id_project,
                           pending.mitra_id,
                           pending.reporting_as_of,
                           pg_catalog.MIN(pending.series_date) AS first_pending_date
                    FROM pending_daily AS pending
                    GROUP BY pending.project_id, pending.id_project, pending.mitra_id, pending.reporting_as_of
                ), pending_shadow_verified_anchor AS (
                    SELECT DISTINCT ON (anchor.project_id)
                           anchor.project_id,
                           anchor.id_project,
                           anchor.mitra_id,
                           anchor.reporting_as_of,
                           verified.series_date,
                           verified.cumulative_value
                    FROM pending_shadow_anchor AS anchor
                    JOIN verified_cumulative AS verified ON verified.project_id = anchor.project_id
                    WHERE verified.series_date < anchor.first_pending_date
                    ORDER BY anchor.project_id, verified.series_date DESC
                ), pending_shadow_anchor_series AS (
                    SELECT anchor.project_id,
                           anchor.id_project,
                           anchor.mitra_id,
                           anchor.reporting_as_of,
                           'pending_shadow'::text AS series_kind,
                           anchor.series_date,
                           NULL::numeric AS series_value,
                           anchor.cumulative_value,
                           total.grand_total_rab_jasa
                    FROM pending_shadow_verified_anchor AS anchor
                    JOIN grand_totals AS total ON total.project_id = anchor.project_id
                ), pending_shadow_daily AS (
                    SELECT pending.project_id,
                           pending.id_project,
                           pending.mitra_id,
                           pending.reporting_as_of,
                           'pending_shadow'::text AS series_kind,
                           pending.series_date,
                           pending.series_value,
                           (COALESCE(anchor.cumulative_value, 0) + pg_catalog.SUM(pending.series_value) OVER (
                               PARTITION BY pending.project_id ORDER BY pending.series_date
                           ))::numeric(20, 2) AS cumulative_value,
                           total.grand_total_rab_jasa
                    FROM pending_daily AS pending
                    JOIN grand_totals AS total ON total.project_id = pending.project_id
                    LEFT JOIN pending_shadow_verified_anchor AS anchor ON anchor.project_id = pending.project_id
                ), all_series AS (
                    SELECT project_id, id_project, mitra_id, reporting_as_of, series_kind, series_date, series_value, cumulative_value, cumulative_percent, NULL::numeric AS grand_total_rab_jasa
                    FROM original_days
                    UNION ALL
                    SELECT project_id, id_project, mitra_id, reporting_as_of, series_kind, series_date, series_value, cumulative_value, cumulative_percent, NULL::numeric
                    FROM revised_days
                    UNION ALL
                    SELECT project_id, id_project, mitra_id, reporting_as_of, series_kind, series_date, series_value, cumulative_value, cumulative_percent, NULL::numeric
                    FROM original_flat_after_toc
                    UNION ALL
                    SELECT verified.project_id, verified.id_project, verified.mitra_id, verified.reporting_as_of,
                           'verified'::text, verified.series_date, verified.series_value, verified.cumulative_value, NULL::numeric,
                           total.grand_total_rab_jasa
                    FROM verified_cumulative AS verified
                    JOIN grand_totals AS total ON total.project_id = verified.project_id
                    UNION ALL
                    SELECT pending.project_id, pending.id_project, pending.mitra_id, pending.reporting_as_of,
                           'pending'::text, pending.series_date, pending.series_value, pending.cumulative_value, NULL::numeric,
                           total.grand_total_rab_jasa
                    FROM pending_cumulative AS pending
                    JOIN grand_totals AS total ON total.project_id = pending.project_id
                    UNION ALL
                    SELECT project_id, id_project, mitra_id, reporting_as_of, series_kind, series_date, series_value, cumulative_value, NULL::numeric, grand_total_rab_jasa
                    FROM pending_shadow_anchor_series
                    UNION ALL
                    SELECT project_id, id_project, mitra_id, reporting_as_of, series_kind, series_date, series_value, cumulative_value, NULL::numeric, grand_total_rab_jasa
                    FROM pending_shadow_daily
                )
                SELECT project_id::bigint,
                       id_project::text,
                       mitra_id::bigint,
                       reporting_as_of::date,
                       series_kind::text,
                       series_date::date,
                       series_value::numeric(20, 2),
                       cumulative_value::numeric(20, 2),
                       CASE
                           WHEN cumulative_percent IS NOT NULL THEN cumulative_percent
                           WHEN grand_total_rab_jasa > 0 THEN LEAST(100::numeric, pg_catalog.ROUND(cumulative_value / grand_total_rab_jasa * 100, 2))
                           ELSE 0::numeric
                       END::numeric(8, 2) AS cumulative_percent,
                       CURRENT_TIMESTAMP AS read_at
                FROM all_series;
                SQL,
        ];
    }
};
