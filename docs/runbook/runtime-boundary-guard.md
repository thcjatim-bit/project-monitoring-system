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

## Terpasang di pipeline

`/usr/local/sbin/pms-deploy` memanggil guard dua kali dalam satu deploy:

- **preflight**, sebelum checkout commit baru — menolak deploy kalau runtime yang
  sedang berjalan sudah melanggar boundary;
- **gate sesudah deploy**, setelah `php artisan optimize` dan restart service —
  menolak kalau cached config yang baru dibangun bukan milik production.

Keduanya lewat `production_boundary_guard()`, yang menjalankan
`scripts/verify-runtime-boundary.sh production` dari checkout terpasang sebagai
user deploy. Kegagalan di preflight membatalkan deploy sebelum apa pun berubah.

Konsekuensi yang perlu diketahui: karena preflight membaca script dari checkout
yang **sedang terpasang**, perbaikan pada guard baru berlaku setelah satu deploy
berhasil. Guard tidak bisa memperbaiki dirinya sendiri lewat deploy yang sedang
diblokir olehnya.

## Pemulihan: cached config production hilang

**Konteks tiket**: [Preflight deploy buntu sendiri ketika cached config production hilang (#122)](https://github.com/thcjatim-bit/project-monitoring-system/issues/122)

Preflight mensyaratkan config **CACHED**, sementara satu-satunya jalur yang
membangun cache itu (`php artisan optimize`) berada di dalam deploy, sesudah
preflight. Kalau `bootstrap/cache/config.php` hilang, deploy terkunci dan tidak
bisa membuka kuncinya sendiri.

Penyebab yang sudah terjadi: `composer install` dijalankan langsung di checkout
production (22 Agustus 2026). `post-autoload-dump` menulis ulang `packages.php`
dan `services.php` lalu menghapus `config.php`, tanpa membangunnya kembali.

Pemulihannya:

```bash
ssh pms-prod "sudo -n /usr/local/sbin/pms-deploy recover-config-cache"
```

Perintah itu membangun ulang cache dari `.env` production, lalu menjalankan guard
penuh untuk membuktikan hasilnya sah. Sesudah itu deploy normal bisa dijalankan.

Dua sifatnya yang disengaja:

- **Menolak kalau cache sudah ada.** Cache yang ada tapi salah adalah insiden #65,
  dan membangun ulang di atasnya akan menimpa bukti sebelum ada yang membacanya.
  Periksa dulu dengan guard; perintah ini hanya untuk cache yang benar-benar hilang.
- **Fail-closed.** Kalau guard menolak cache yang baru dibangun, cache itu dihapus
  lagi dan production ditinggalkan tanpa cache. Artifact yang tidak bisa
  dipertanggungjawabkan lebih buruk daripada tidak ada artifact sama sekali.

Aplikasi tetap melayani request selama tidak ada cache — Laravel membaca `.env`
per request. Yang terhenti adalah kemampuan deploy, bukan layanannya.

Guard sendiri tidak berubah sama sekali oleh #122: ia tetap assertion murni,
tidak pernah membangun cache. Yang membangun adalah `pms-deploy`, dan hanya
ketika operator memintanya secara eksplisit.

## Temuan operasional terbuka

- `public/storage` tidak ter-link di production, sehingga URL disk publik tidak terlayani. Relevan untuk Foto Pekerjaan (ADR-0012); di luar cakupan #65.
- Role `pms_prod_migrator` belum ada di cluster production; provisioning-nya tetap follow-up operasi sesuai #63.
- Role `pms_app` ada di cluster production, penamaan yang sama dengan role runtime testing. Perlu ditinjau saat separasi credential dikerjakan.
