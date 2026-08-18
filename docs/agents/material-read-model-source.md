# Sumber Read Model Material Project

Issue #62 owns the domain mutations and the source read seam for Pemakaian Material and Rekon Material. REST resources and PostgreSQL BI views remain consumers owned by their own tickets.

## Canonical sources

| Projection | Grain | Source | Read seam |
| --- | --- | --- | --- |
| Pemakaian Material | one usage request | `pemakaian_materials` and linked `material_transaksis` | `PemakaianMaterial` and `MaterialUsageService` |
| Rekon Material | one Rekon and one Rekon item | `project_rekons`, `project_rekon_items`, and linked `material_transaksis` | `ProjectRekonQuery` |
| Project material balance | location x material | `material_stoks`, with `material_transaksis` as the immutable audit book | `ProjectRekonService` ledger |

`ProjectRekonQuery::forProject()` returns the project status, all Rekon history, and the effective active approved Rekon. `activeForProject()` returns only that effective approved source. Both methods apply the model tenant scope and omit actor identities, decision notes, and free-form internal notes.

## Source fields

Each Rekon source contains `id`, `nomor`, `source` (`manual` or `go_live`), `status`, `koreksi_dari_id`, `approved_at`, and item rows. An item contains the warehouse/material identity, optional SN or Drum identity, `keluar_gudang`, `terpasang`, `sisa_project`, `dikembalikan`, `hilang_rusak`, loss category, and responsible party.

`keluar_gudang` is derived from approved `pemakaian` transactions into the Project. `terpasang` is derived from approved `terpasang` movements. `sisa_project` is the current Project balance plus the previous active Rekon accounting when a correction is opened. Return and loss quantities are represented by append-only `rekon_kembali`, `rekon_hilang`, `rekon_rusak`, or `rekon_waste` transactions after THC approval.

Pending or rejected usage never contributes to the ledger. The active approved correction is the terminal approved Rekon in the `koreksi_dari_id` chain; older approved Rekons remain queryable history.

## Security and ownership

The three source tables have tenant columns and forced PostgreSQL RLS. `pms_app` can insert/update lifecycle rows but cannot delete them; `material_transaksis` remains insert-only for the application and cache changes are trigger-owned. Mitra identity rows are visible only while they belong to that Mitra's warehouse or Project. Consumers must use the query seam or a later curated adapter, never raw-table grants.
