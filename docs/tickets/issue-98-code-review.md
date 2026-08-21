# Issue #98 — Tindak lanjut code review map QC #87

**Status**: Terbuka

**Issue**: [#98 Tindak lanjuti temuan code review map QC #87](https://github.com/thcjatim-bit/project-monitoring-system/issues/98)

**Parent**: [#87 Review dan penyelesaian QC Produksi 2026-08-20 di main](https://github.com/thcjatim-bit/project-monitoring-system/issues/87)

## Rentang review

Review dua sumbu (Standards dan Spec) dilakukan terhadap 27 commit pada rentang:

```text
f451c2b20e9b9f7a6dd433269a4760b3e310a429...a43803a9ba5f2589fcf077d694d57e7190e39b79
```

Fixed point adalah commit terakhir sebelum map #87 dibuat. Perubahan lokal yang belum
di-commit tidak termasuk dalam review.

## Temuan Spec

1. RAB yang sudah beku masih dapat ditambah langsung melalui
   `ProjectPlanningService::addRabJasa()` dan form Project Planning tanpa melewati
   Variation Order.
2. Project create belum memakai `x-ui.searchable-select` sesuai keputusan #90 dan
   masih memiliki field pencarian serta native select terpisah.
3. Regression test JavaScript searchable select baru menguji filtering; interaksi
   open/click, keyboard, reset, hidden value, dan outside click belum diuji.
4. Fondasi UI yang diputuskan pada #90 belum lengkap, sedangkan workspace Project
   Planning masih menggunakan CSS dan komponen lokal.

## Temuan Standards

1. Aturan lifecycle kode master terduplikasi di `AdminController` dan
   `MasterDataController`.
2. Alur publikasi baseline terduplikasi antara simpan langsung THC dan approval
   proposal Mitra di `ProjectPlanningService`.

## Acceptance criteria

- Tambahkan regression test yang gagal sebelum perbaikan RAB freeze. Setelah
  baseline ada, perubahan RAB hanya dapat dilakukan melalui Variation Order yang
  sah.
- Migrasikan Project create ke `x-ui.searchable-select` tanpa field pencarian
  ganda dan tanpa memperluas opsi authorized.
- Tambahkan test interaksi searchable select untuk mouse, keyboard, open/close,
  reset, hidden form value, dan outside click.
- Lengkapi seam UI yang disepakati pada #90 dan migrasikan Project Planning agar
  memakai fondasi bersama tanpa perubahan permission atau domain yang tidak
  diminta.
- Sentralisasikan lifecycle kode master dan publikasi baseline agar hanya ada satu
  implementasi aturan masing-masing.
- Pertahankan RLS, authorization server-side, snapshot harga, append-only
  timeline/ledger, dan batas tenant.
- Jalankan focused tests, test JavaScript dan build, PostgreSQL
  integration/security tests, serta full Laravel suite di `pms-dev` sampai hijau.
- Lakukan code review ulang dan dokumentasikan bukti verifikasi sebelum issue
  ditutup.
