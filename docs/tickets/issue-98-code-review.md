# Issue #98 — Tindak lanjut code review map QC #87

**Status**: Selesai

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

## Hasil implementasi

- RAB langsung dibekukan secara atomik setelah Baseline pertama terbit;
  perubahan sesudahnya tetap melalui Variation Order.
- Project create dan Project Planning memakai fondasi UI bersama serta
  searchable select dengan fallback native sebelum JavaScript aktif.
- Lifecycle create, update, dan Nonaktif untuk seluruh master berkode
  dipusatkan di `CodedMasterLifecycle`, termasuk ledger penerbitan kode,
  permission eksak, dan validasi reference aktif.
- Publikasi Baseline langsung dan hasil approval proposal memakai satu jalur
  penulisan Baseline, dengan event Timeline sesuai provenance masing-masing.

## Review ulang

Review dilakukan terhadap working tree issue #98 dengan fixed point
`e1c6ac93c1b88ce8d48b96ff4b7789605bdddc3c`.

- **Standards**: tidak ada temuan tersisa.
- **Spec**: tidak ada temuan acceptance tersisa dan tidak ada scope creep.

Temuan selama review ulang tentang atomic ordering, fallback native, coverage
race/lifecycle, pemisahan seam concurrency, provenance publikasi Baseline, dan
regression proposal sudah diperbaiki sebelum verifikasi final.

## Bukti verifikasi

- `npm run test:js`: 8 passed.
- `npm run build`: berhasil; asset produksi `app-C97x3UFV.js` diterbitkan.
- Pint untuk seluruh file PHP yang berubah: passed.
- Focused lifecycle: 15 passed, 23 assertions.
- Focused Project Planning dan UI: 19 passed, 80 assertions.
- PostgreSQL concurrency: 2 passed, 17 assertions.
- PostgreSQL integration/security termasuk RLS dan append-only: passed sebagai
  bagian dari suite penuh.
- Full Laravel suite di `pms-dev`: 314 passed, 1.938 assertions.
