# PostgreSQL BI read surface

Status: implemented by migration `2026_08_18_000002_create_bi_views_and_roles.php`.

This surface is for the internal THC BI consumer on the LAN. It is separate
from the REST API and never opens, forwards, or publishes PostgreSQL port 5432.
The application migration does not create production credentials: the database
operator provisions the roles, and the migration fails closed if they are
missing or unsafe.

## Security boundary

- Schema: `bi`.
- Login role: `pms_bi_reader` (`LOGIN`, `NOSUPERUSER`, `NOBYPASSRLS`,
  `NOCREATEDB`, `NOCREATEROLE`, `NOREPLICATION`, no memberships). Its role
  defaults are `app.is_thc=off` and `app.mitra_id=-1`, so a connection
  without an explicit checkout context fails closed.
- View owner: `pms_bi_view_owner` (`NOLOGIN`, `NOSUPERUSER`, `NOBYPASSRLS`),
  never supplied to a BI tool and never an owner of a `public` base table.
- The reader receives `USAGE` on `bi` and explicit `SELECT` only on the nine
  views below. It receives no raw-table, sequence, explicit function, DML,
  `TRIGGER`, or `REFERENCES` privilege.
- Views use `security_invoker = false` and `security_barrier = true`. The
  owner has minimum `SELECT` on the source relations so RLS remains evaluated
  for the non-owner, non-bypass role.
- Only the migration/deployer role may be granted `SET ROLE` to
  `pms_bi_view_owner`; the BI reader must never receive that membership. The
  testing bootstrap grants this narrowly to `pms_migrator`.

Every BI connection must overwrite and verify its session context before any
query, including after pool reuse:

```sql
SELECT set_config('app.is_thc', 'on', false);
SELECT set_config('app.mitra_id', '', false);
SELECT current_user,
       current_setting('app.is_thc', true) AS is_thc,
       current_setting('app.mitra_id', true) AS mitra_id;
```

The pool adapter must discard the connection unless the verification result is
exactly `pms_bi_reader`, `on`, and the empty string. This overwrite-and-verify
step is required again whenever a pooled connection is checked out.

The views require THC context and an empty Mitra context. Missing or invalid
context returns zero rows. GUC values are only a fail-closed query context;
role membership, effective privileges, ownership, RLS, and the LAN boundary
are the authorization controls.

`app.reporting_as_of` is optional. When present it is an inclusive Asia/Jakarta
business date; when absent, the current Asia/Jakarta business date is used.
`read_at` is a separate `timestamptz` read timestamp and is present, non-null,
on every view.

## Stable view contract

All IDs are `bigint`; business identifiers and labels are explicit text
columns. No view uses `SELECT *`.

| View | Grain | Stable columns |
| --- | --- | --- |
| `bi.v_projects` | Project | `project_id`, `id_project`, `project_nama`, `mitra_id`, `mitra_kode`, `mitra_nama`, `status_project`, `toc`, `original_baseline_kind`, `original_baseline_version`, `original_baseline_toc`, `revised_baseline_kind`, `revised_baseline_version`, `revised_baseline_toc`, `active_baseline_kind`, `active_baseline_version`, `active_baseline_toc`, `reporting_as_of`, `read_at` |
| `bi.v_project_steps` | Project x Step | `project_step_id`, `project_id`, `id_project`, `project_nama`, `mitra_id`, `step_code`, `step_order`, `step_status`, `completed_at`, `read_at` |
| `bi.v_kurva_s` | Project x reporting date summary | `project_id`, `id_project`, `project_nama`, `mitra_id`, `mitra_kode`, `mitra_nama`, `reporting_as_of`, `grand_total_rab_jasa`, `verified_value`, `verified_percent`, `pending_value`, `pending_percent`, `pending_shadow_value`, `pending_shadow_percent`, `plan_percent`, `spi`, `spi_status`, `original_baseline_kind`, `original_baseline_version`, `original_baseline_toc`, `revised_baseline_kind`, `revised_baseline_version`, `revised_baseline_toc`, `active_baseline_kind`, `active_baseline_version`, `active_baseline_toc`, `overdue`, `baseline_flat_after_toc`, `read_at` |
| `bi.v_kurva_s_series` | Project x reporting date x series kind x date | `project_id`, `id_project`, `mitra_id`, `reporting_as_of`, `series_kind`, `series_date`, `series_value`, `cumulative_value`, `cumulative_percent`, `read_at` |
| `bi.v_stok` | Location x Material | `stock_id`, `location_type`, `location_id`, `project_id`, `id_project`, `warehouse_id`, `warehouse_kode`, `warehouse_nama`, `material_id`, `material_kode`, `material_nama`, `unit_kode`, `unit_nama`, `mitra_id`, `location_name`, `qty`, `available_qty`, `is_warehouse_available`, `read_at` |
| `bi.v_transaksi_material` | Immutable material event | `material_transaction_id`, `event_at`, `transaction_type`, `material_id`, `material_kode`, `material_nama`, `unit_kode`, `unit_nama`, `warehouse_id`, `warehouse_kode`, `warehouse_nama`, `project_id`, `id_project`, `surat_jalan_id`, `surat_jalan_nomor`, `location_type`, `location_id`, `qty_delta`, `correction_transaction_id`, `mitra_id`, `reporting_as_of`, `read_at` |
| `bi.v_request_material` | Request x item | `request_item_id`, `material_request_id`, `mitra_id`, `mitra_kode`, `mitra_nama`, `project_id`, `id_project`, `project_nama`, `workflow_status`, `material_id`, `material_kode`, `material_nama`, `unit_kode`, `unit_nama`, `qty_diminta`, `qty_diterima`, `qty_diretur`, `qty_transit`, `qty_sisa`, `fulfillment_status`, `reporting_as_of`, `read_at` |
| `bi.v_rekon_material` | Rekon x item | `project_rekon_id`, `rekon_nomor`, `mitra_id`, `mitra_kode`, `mitra_nama`, `project_id`, `id_project`, `project_nama`, `status_project`, `source`, `status`, `correction_source_id`, `approved_at`, `reporting_as_of`, `project_rekon_item_id`, `warehouse_id`, `warehouse_kode`, `warehouse_nama`, `material_id`, `material_kode`, `material_nama`, `unit_kode`, `unit_nama`, `material_sn_id`, `drum_id`, `keluar_gudang`, `terpasang`, `sisa_project`, `dikembalikan`, `hilang_rusak`, `kategori_hilang_rusak`, `penanggung_jawab`, `is_active_correction`, `is_effective_approved`, `read_at` |
| `bi.v_harga_jasa_mitra` | Mitra x Pekerjaan Jasa x price version | `mitra_harga_jasa_id`, `mitra_id`, `mitra_kode`, `mitra_nama`, `pekerjaan_jasa_id`, `pekerjaan_jasa_kode`, `pekerjaan_jasa_nama`, `pks_id`, `pks_nomor`, `pks_tanggal_mulai`, `pks_tanggal_berakhir`, `harga`, `status`, `berlaku_mulai`, `revisi_dari_id`, `is_effective_price`, `reporting_as_of`, `read_at` |

`v_stok` reads `material_stoks`; only `location_type = warehouse` contributes
to `available_qty`. Transit, Project, and terpasang balances remain visible
but are never warehouse-available. `v_request_material` reports received
quantity net of `qty_diretur`, exposes returned quantity separately, excludes
cancelled/return Surat Jalan rows as new deliveries, and counts active outbound
quantity as Transit.

Kurva S uses the canonical domain formulas: base RAB plus applied Variation
Order deltas without double-counting VO-created RAB lines; verified and pending
value through `reporting_as_of`; clamped two-decimal percentages; Revised
Baseline before Original; 100% after an overdue Original without Revised; and
SPI rounded to four decimals with `na`, `green`, `yellow`, and `red` thresholds.
The series is cumulative and `pending_shadow` starts at the last verified point
before pending work. Rekon exposes all history; a pending or approved correction
is the chain endpoint, a rejected correction does not replace the prior source,
and `is_effective_approved` is true only for an approved endpoint.

## Exclusions and evolution

The views exclude raw `material_transaksis`, internal comments/free notes,
password hashes, actor/user identity fields, PKS `lampiran_path`, and binary
Foto Pekerjaan. They read live source rows transactionally; `read_at` does not
claim a historical knowledge snapshot.

Additive nullable columns are backward-compatible. Renames, deletions, type
changes, required fields, or semantic changes require a new `_v2` view while
the existing contract remains available during consumer migration.

## Verification

`scripts/bootstrap-testing.sh` provisions disposable testing roles and the
dedicated `project_monitoring_system_testing` database, then runs the complete
migration set. The PostgreSQL security and semantic checks are in
`tests/Feature/PostgresBiViewTest.php`; run them on `pms-dev` with:

```sh
bash scripts/bootstrap-testing.sh
php artisan test tests/Feature/PostgresBiViewTest.php
```

The test suite audits effective privileges and memberships, view options and
columns, RLS/context behavior including pooled-connection reuse, positive
curated reads, sensitive-column exclusion, and raw-table/DML denial.
