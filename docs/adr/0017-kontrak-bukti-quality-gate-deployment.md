# ADR-0017 — Kontrak bukti quality gate sebelum deployment

**Status**: Diterima — 2026-08-14  
**Konteks tiket**: [Tetapkan required quality-gate evidence sebelum production deploy](https://github.com/thcjatim-bit/project-monitoring-system/issues/34)

## Konteks

Proses `/implement` sudah menetapkan urutan verifikasi development sampai production, tetapi belum menetapkan bukti minimum yang membuat keputusan deploy dapat diaudit dan diulang. Production `deploythc.web.id` harus menerima hanya exact commit yang telah lolos verifikasi; production bukan target test.

## Keputusan

1. Setiap tiket implementasi wajib membuktikan local checks, verifikasi `pms-dev`, PostgreSQL integration tests, full suite, Pint/lint/static checks, code review, clean commit, push, exact SHA, deployment status, dan smoke test `deploythc.web.id`. Tes atau bukti tambahan yang khusus untuk tiket juga wajib dicatat.
2. Bukti dicatat pada tiket implementasi dengan format: command/check, environment, result, output ringkas atau link artifact, timestamp, dan commit SHA. Implementer mengumpulkan bukti; reviewer/release authority memeriksa kelengkapannya.
3. Pemeriksaan standar lokal adalah `composer validate --strict`, `php artisan test`, `vendor/bin/pint --test`, dan `npm run build`. Build frontend wajib bila aset atau konfigurasi frontend berubah; selain itu dicatat sebagai `N/A` dengan alasan.
4. Bukti `pms-dev` mencakup runtime Laravel/PHP, status migrasi bila relevan, full suite dengan database testing khusus dan role `pms_app`, Pint/lint/static checks, PostgreSQL/RLS integration tests, serta health service/infrastructure yang relevan.
5. Full SHA harus sama pada commit terverifikasi, upstream, status deployment `pms-prod`, dan pemeriksaan runtime setelah deploy.
6. Sebelum deploy, semua bukti wajib sudah ada kecuali status deployment dan smoke test yang ditambahkan segera sesudahnya. Smoke test ditentukan sebelum implementasi dan mencakup satu workflow relevan yang berhasil serta satu batas authorization/failure yang relevan, menggunakan jalur read-only atau data aman.
7. Gate yang berlaku tidak boleh dilewati tanpa persetujuan eksplisit repository owner/release authority pada tiket, berisi alasan, risiko, pemeriksaan pengganti, dan kondisi evaluasi ulang. Kegagalan deployment atau smoke test membuat tiket belum selesai dan ditangani sesuai prosedur status, log, health check, rollback, atau forward-fix.
8. Bukti besar tetap berupa artifact/link; tiket menjadi indeks permanen. Documentation-only change boleh melewati code-specific checks yang benar-benar tidak relevan, tetapi bila ikut dideploy tetap wajib clean commit, exact-SHA verification, deployment status, dan smoke test.

## Konsekuensi

- Setiap deploy memiliki audit trail yang menghubungkan perubahan, hasil review, exact SHA, dan kesehatan production.
- Tiket implementasi memerlukan disiplin pencatatan dan definisi smoke test sebelum pekerjaan dimulai.
- Exception menjadi keputusan terukur dan terlihat, bukan asumsi diam-diam.
