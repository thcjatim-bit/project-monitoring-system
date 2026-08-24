# Research audit: GitHub issue #159

Date: 2026-08-24  
Repository: `thcjatim-bit/project-monitoring-system`  
Issue: [#159 — Baseline Laravel: klasifikasikan 13 failure yang bukan regresi qty](https://github.com/thcjatim-bit/project-monitoring-system/issues/159)

## Conclusion

The previously reported 13 Laravel failures are not reproducible in a safe,
exact-SHA setup. After rebuilding the approved disposable testing database and
installing Composer dependencies independently in each worktree:

| Revision | Exact SHA | Result |
| --- | --- | --- |
| Base | `f60ed5761b7fda013a0511359653256871270738` | **400 passed, 2,526 assertions** |
| Implementation plus follow-up | `031bfb2a7650ae34967921ec9903e7c83abc9e3a` | **411 passed, 2,602 assertions** |

Therefore the current remaining Laravel failure count is zero on both
revisions, and no failure is a #133 regression. The historical counts recorded
on #133 (`385 passed / 15 failed` on base and `398 passed / 13 failed` on the
implementation) came from an invalid verification setup, not from a stable
feature-baseline difference.

The key setup problems were observable in the earlier temporary worktrees:

- `/tmp/pms-issue159-base/vendor` was a symlink to
  `/var/www/project-monitoring-system/vendor`, so its Composer autoload was
  shared with the dirty authoritative checkout rather than generated for the
  exact worktree.
- Multiple Laravel test processes reused the single testing database. The
  captured output included PostgreSQL deadlocks and schema errors such as
  `column "project_id" of relation "surat_jalans" does not exist`.
- The authoritative `/var/www/project-monitoring-system` checkout was dirty at
  `6fd549902d82cf825e97257391ea75e5837337bb`; it was not changed during this
  research.

Those facts make the earlier 13-failure comparison unsuitable for creating
feature follow-up tickets. The correct disposition is testing-environment
repair and rerun, which is now green.

## Historical 13-failure set

These are the 13 failures named by the earlier #133 release evidence. They are
listed for traceability, but each passed on both exact revisions in the clean
rerun. “Touched surface” means a path-level overlap with #133; “behavior
overlap” distinguishes that from the actual quantity policy changed by #133.

| Historical failing test | Earlier base / implementation | Clean base / implementation | Touched surface | #133 regression? | Disposition |
| --- | --- | --- | --- | --- | --- |
| `PortfolioCockpitTest::test_mitra_scope_keeps_other_tenants_out_of_the_portfolio_kpis` | fail / fail | pass / pass | None; no Portfolio Cockpit path in the #133 diff | No | Environment-drift symptom; no feature ticket from this evidence |
| `PortfolioCockpitTest::test_portfolio_activity_renders_surat_jalan_deviation_metadata` | fail / fail | pass / pass | None; no Portfolio Cockpit/activity path in the #133 diff | No | Environment-drift symptom; no feature ticket from this evidence |
| `ProjectControlRoomTest::test_linimasa_gabungan_merender_baris_menyimpang_surat_jalan_sebagai_kalimat_terbaca` | fail / fail | pass / pass | `resources/views/projects/show.blade.php` only; #133 changes qty inputs, not timeline rendering | No | Environment-drift symptom; no feature ticket from this evidence |
| `ProjectControlRoomTest::test_linimasa_gabungan_mempertahankan_label_mentah_untuk_event_lain` | fail / fail | pass / pass | `resources/views/projects/show.blade.php` only; no timeline behavior change | No | Environment-drift symptom; no feature ticket from this evidence |
| `ProjectStepTest::test_project_has_exactly_eleven_steps_and_can_move_forward_or_backward` | fail / fail | pass / pass | None; no Project Step path in the #133 diff | No | Environment-drift symptom; no feature ticket from this evidence |
| `ProjectStepTest::test_completing_a_step_records_actual_date_and_activates_the_next_step` | fail / fail | pass / pass | None; no Project Step path in the #133 diff | No | Environment-drift symptom; no feature ticket from this evidence |
| `SuratJalanFormDataTest::test_sisa_mengabaikan_surat_jalan_request_dengan_mitra_tidak_cocok` | fail / fail | pass / pass | `SuratJalanController.php`, `warehouse/index.blade.php`, and warehouse JS overlap only for qty policy | No | Environment-drift symptom; request-remainder behavior is not #133 behavior |
| `SuratJalanPrintTest::test_print_shows_request_project_and_all_item_notes` | fail / fail | pass / pass | `warehouse/transfer-show.blade.php` changes receive/return/correction qty inputs, not print output | No | Environment-drift symptom; no print regression |
| `SuratJalanPrintTest::test_print_omits_project_when_request_has_no_project` | fail / fail | pass / pass | Same limited transfer-form overlap | No | Environment-drift symptom; no print regression |
| `SuratJalanPrintTest::test_print_of_a_return_omits_inherited_request_and_project_rows` | fail / fail | pass / pass | Same limited transfer-form overlap | No | Environment-drift symptom; no print regression |
| `SuratJalanRequestDrivenFormTest::test_request_yang_belum_terminal_tidak_diubah_menjadi_kiriman_langsung_dan_asal_dipertahankan` | fail / fail | pass / pass | `warehouse/index.blade.php` and `warehouse-material-form.js` overlap only for qty defaults | No | Environment-drift symptom; request-origin preservation is not #133 behavior |
| `SuratJalanRequestDrivenFormTest::test_request_yang_tidak_lagi_tersedia_tetap_terpilih_setelah_validasi_gagal` | fail / fail | pass / pass | Same limited qty-form overlap | No | Environment-drift symptom; request-origin preservation is not #133 behavior |
| `SuratJalanRequestDrivenFormTest::test_request_terminal_milik_mitra_lain_tidak_diubah_menjadi_kiriman_langsung` | fail / fail | pass / pass | Same limited qty-form overlap | No | Environment-drift symptom; request-origin preservation is not #133 behavior |

## Base-only quantity failures from the invalid run

The earlier `15 failed` base count also included these two old assertions:

- `MaterialRequestTest::test_mitra_can_submit_a_request_for_its_own_material_need`
- `MaterialRequestTest::test_fractional_qty_names_the_row_that_is_wrong_not_the_whole_request`

They are not failures in the clean base rerun. On the implementation they are
represented by the updated whole-quantity contract and also pass. The clean
comparison therefore provides no evidence of a #133 regression or a valid
base-only failure.

## Exact commands and primary evidence

The local source comparison was:

```text
git diff --name-status f60ed5761b7fda013a0511359653256871270738..031bfb2a7650ae34967921ec9903e7c83abc9e3a
```

The #133 range contains 22 changed paths: the shared `WholeMaterialQty` rule,
six PHP controllers, the Warehouse/Request Material quantity forms and
JavaScript, generated frontend assets, and the #133 quantity tests. It does not
contain Portfolio Cockpit queries, Project Step code, timeline renderers,
print templates, or Request Material origin-preservation logic.

For each exact SHA, the following was run on `pms-dev` in a separate disposable
worktree with its own Composer autoload and a copied, ignored `.env.testing`:

```sh
cd /tmp/pms-issue159-research-base
composer install --no-interaction --prefer-dist --no-progress --no-scripts
bash scripts/bootstrap-testing.sh
APP_ENV=testing php artisan test --no-coverage

cd /tmp/pms-issue159-research-impl
composer install --no-interaction --prefer-dist --no-progress --no-scripts
bash scripts/bootstrap-testing.sh
APP_ENV=testing php artisan test --no-coverage
```

The bootstrap output verified the disposable database
`project_monitoring_system_testing` and roles `pms_app` / `pms_migrator` with
RLS enabled. No production database, source checkout, or production service
was changed.

Exact worktree evidence:

```text
/tmp/pms-issue159-research-base  f60ed5761b7fda013a0511359653256871270738
/tmp/pms-issue159-research-impl  031bfb2a7650ae34967921ec9903e7c83abc9e3a
```

Primary source links:

- [Issue #133 implementation and prior verification evidence](https://github.com/thcjatim-bit/project-monitoring-system/issues/133)
- [Issue #159 question](https://github.com/thcjatim-bit/project-monitoring-system/issues/159)
- [ADR-0025 — Qty material bilangan bulat](../adr/0025-qty-material-bilangan-bulat.md)
- [Exact #133 diff](https://github.com/thcjatim-bit/project-monitoring-system/compare/f60ed5761b7fda013a0511359653256871270738...031bfb2a7650ae34967921ec9903e7c83abc9e3a)

## Release-gate recommendation

Do not open unrelated feature tickets from the historical 13-failure list.
Record the safe rerun as the durable baseline result: both exact revisions are
green, and #133 introduces no Laravel regression. The remaining release-gate
work is outside this ticket (for example the separate JavaScript runner ticket
and authoritative-checkout ticket).
