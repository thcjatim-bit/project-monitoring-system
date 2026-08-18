# Runbook — Guard boundary runtime pms-dev / pms-prod

**Konteks tiket**: [Pulihkan dan buktikan runtime production memakai config dan database production (#65)](https://github.com/thcjatim-bit/project-monitoring-system/issues/65)
**Keputusan yang diterapkan**: [Tentukan boundary runtime pms-dev/pms-prod dan guard cache konfigurasi production (#63)](https://github.com/thcjatim-bit/project-monitoring-system/issues/63)

## Masalah yang dijaga

`pms-dev` dan `pms-prod` masih memakai host, user SSH, dan checkout yang sama
(`/var/www/project-monitoring-system`). Checkout itu berisi `.env` (production)
dan `.env.testing` (testing) sekaligus. Karena Laravel mengabaikan `.env` ketika
`bootstrap/cache/config.php` ada, satu perintah `config:cache` yang dijalankan
dengan environment testing di checkout tersebut membuat **runtime production
melayani database testing**, tanpa mengubah `.env` sama sekali.

Itu insiden yang dipulihkan pada #65: `.env` production benar, tetapi cached
config dibangun dari environment testing.

Bahayanya berlaku dua arah. Selama cached config production terpasang di
checkout bersama, `APP_ENV=testing php artisan …` juga **resolve ke database
production** — sehingga menjalankan test suite di checkout itu dapat merusak
data production. Verifikasi ini dibuktikan read-only pada #65.

Untuk host yang sedang authoritative, path tersebut sama dengan `APP_DIR` pada
`/usr/local/sbin/pms-deploy` dan working directory service production. Jika
deployment dipindahkan ke root lain, operator wajib mengisi
`PMS_BOUNDARY_APP_DIR` dengan root yang sama sebelum menjalankan guard; jangan
mengandalkan default lama dari template deployment.

Separasi checkout/service/cache per environment adalah remediasi sebenarnya dan
tetap menjadi pekerjaan platform/deployment operator sesuai #63. Guard di sini
adalah jaring pengaman fail-closed, bukan pengganti separasi tersebut.

## Cara memakai guard

```bash
scripts/verify-runtime-boundary.sh production
scripts/verify-runtime-boundary.sh testing
```

Guard bersifat **assertion**: tidak pernah menulis konfigurasi, tidak pernah
membangun ulang cache, dan tidak pernah mencetak nilai secret. Setiap mismatch,
input yang hilang, atau profile yang tidak dikenal menghasilkan exit non-nol
(`1` untuk pelanggaran boundary, `64` untuk pemakaian salah), sehingga aman
dipakai sebagai preflight dan sebagai gate sesudah deploy.

Override untuk test dan checkout non-default:

| Variabel | Arti |
| --- | --- |
| `PMS_BOUNDARY_APP_DIR` | checkout yang diperiksa |
| `PMS_BOUNDARY_SKIP_RUNTIME` | `1` = hanya periksa cached config, lewati identitas database live dan service |

## Yang diperiksa

Per environment, guard membandingkan terhadap allowlist yang ditulis di dalam
script — bukan menyimpulkan identitas yang diharapkan dari runtime yang sedang
diaudit:

- checkout ada dan berbentuk aplikasi Laravel;
- exact SHA dan worktree bersih — checkout yang tidak dapat diperiksa (bukan repo git, `git` tidak tersedia, HEAD tidak resolve, atau `status` ditolak karena dubious ownership) dihitung **gagal**, bukan dilewati;
- config **CACHED** (runtime production tanpa cache tidak punya artifact yang bisa diaudit);
- `app.env`, `app.debug`, `app.url`;
- `database.default` — koneksi yang diperiksa diambil dari sini, bukan `pgsql` yang dipatok, agar runtime yang `DB_CONNECTION`-nya menunjuk ke tempat lain tidak lolos atas dasar koneksi yang tidak pernah dibuka;
- koneksi default → `database`, `host`, `port`, `username`;
- tidak ada identifier environment lain pada field identitas di atas;
- identitas database **live** (`db:show`, yang tidak memuat password) cocok dengan allowlist;
- `pms-queue.service`, `pms-schedule.timer`, `nginx`, `php8.3-fpm` aktif.

Pemeriksaan kebocoran identifier sengaja hanya melihat field identitas, bukan
seluruh array cached config: default framework yang tidak dipakai
(`app.frontend_url`, host `beanstalkd`) sah-sah saja menyebut `localhost` dan
akan membuat pemindaian seluruh blob merah permanen.

## Test

```bash
bash scripts/verify-runtime-boundary.test.sh
```

18 check. Suite membangun fixture repo git sekali pakai berisi
`bootstrap/cache/config.php` sintetis dan menegaskan exit status guard —
termasuk kasus insiden #65 (cached config testing di production), setiap field
identitas secara independen, penolakan lintas profile, koneksi default bukan
`pgsql`, checkout bukan repo git, worktree kotor, config yang tidak ter-cache,
profile tak dikenal, checkout hilang, dan bahwa output tidak pernah memuat nilai
password. Suite ini offline: tidak menyentuh server, database, maupun service.

## Belum terpasang di pipeline

Guard **belum** dipanggil oleh `/usr/local/sbin/pms-deploy`. Saat ini
`pms-deploy` hanya memverifikasi worktree bersih dan asal commit; tidak ada
assertion bahwa cached config hasil `php artisan optimize` benar-benar milik
production, dan `pms-deploy status` tidak gagal saat terjadi mismatch.

Pemasangannya adalah perubahan infrastruktur di luar profile `pms-install` yang
disetujui, sehingga menjadi milik platform/deployment operator sesuai #63 —
bukan diubah manual oleh worker. Hook yang disarankan: jalankan
`verify-runtime-boundary.sh production` (a) sebagai preflight sebelum switch,
dan (b) setelah `php artisan optimize` serta reload service, dengan kegagalan
membatalkan deploy sebelum switch.

## Temuan operasional terbuka

- `public/storage` tidak ter-link di production, sehingga URL disk publik tidak terlayani. Relevan untuk Foto Pekerjaan (ADR-0012); di luar cakupan #65.
- Role `pms_prod_migrator` belum ada di cluster production; provisioning-nya tetap follow-up operasi sesuai #63.
- Role `pms_app` ada di cluster production, penamaan yang sama dengan role runtime testing. Perlu ditinjau saat separasi credential dikerjakan.
