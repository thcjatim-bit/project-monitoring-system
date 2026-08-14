# Riset: Deploy Satu Perintah, Rollback, dan Backup ke Google Drive

Riset untuk issue #13. Konteks yang sudah terkunci: Ubuntu 22.04.5 LTS, 4 core, RAM 4,8 GB, disk 109 GB (sisa sekitar 63 GB), aplikasi Laravel 12 + PHP 8.3 + PostgreSQL 16 berjalan native melalui systemd, aset frontend sudah dibangun developer, dan WAHA NOWEB menjadi satu-satunya komponen Docker. Pemilik nyaman menyalin-tempel satu perintah, tetapi tidak merawat sistem melalui log.

## Ringkasan keputusan yang disarankan

1. Pertahankan deploy native yang sudah dipilih. Gunakan satu skrip root-owned: `sudo /opt/pms/deploy.sh`. Docker Compose penuh tidak memberi keuntungan yang sebanding karena stack native sudah dikunci dan berjalan pada satu server kecil.
2. Deploy memakai direktori release + symlink `current`, bukan `git pull` langsung ke direktori aktif. Skrip membuat `pg_dump` sebelum migrasi, menyiapkan release baru, menjalankan migrasi, memindahkan symlink secara atomik, me-reload service, lalu menguji `/up`.
3. Rollback aplikasi harus satu perintah: `sudo /opt/pms/rollback.sh`. Rollback cepat hanya aman bila migrasi dibuat kompatibel ke belakang. Pemulihan dump database adalah prosedur darurat terpisah karena menghapus semua transaksi sejak dump dibuat.
4. Pisahkan dua kebutuhan Google Drive:
   - foto untuk dibaca mitra: `rclone copy`, bukan `sync`, agar hilangnya file lokal tidak ikut menghapus salinan Drive;
   - backup pemulihan bencana: restic melalui backend rclone, karena snapshot terenkripsi, punya retensi, pemeriksaan integritas, dan restore yang jelas.
5. Backup database harian memakai `pg_dump -Fc`; backup foto/restic berjalan harian. Jalankan uji restore otomatis ke database sementara setiap minggu dan uji restore lengkap secara manual tiap tiga bulan.
6. Monitoring minimum: health route Laravel `/up`, pemeriksaan terjadwal dari luar server dengan dead-man switch, serta notifikasi kegagalan systemd/backup. Pemilik menerima pesan, bukan diminta membaca log.
7. RAM 4,8 GB cukup untuk Laravel + PostgreSQL pada sekitar 10 user jika dikonfigurasi konservatif, tetapi server sudah memakai swap sebelum sistem dipasang. Upgrade ke 8 GB sebelum WAHA diaktifkan tetap menjadi pilihan aman. Ukur penggunaan nyata setelah setiap gelombang; jangan mengandalkan angka perkiraan sebagai kapasitas terjamin.
8. Jangan menambah panel administrasi server buatan sendiri. Halaman admin aplikasi cukup menampilkan status read-only dan tindakan domain terbatas, misalnya restart sesi WAHA. Restart PHP/PostgreSQL/deploy tetap melalui skrip root-owned dan SSH. Panel server publik menambah permukaan serangan dan kredensial berprivilege tinggi.

## 1. Bentuk deploy satu perintah

### Pilihan yang dibandingkan

**Docker Compose seluruh stack** lazim untuk membungkus web, worker, database, dan reverse proxy dalam satu perintah. Namun, keputusan proyek telah menetapkan Laravel/PHP/PostgreSQL native systemd dan hanya WAHA di Docker. Memindahkan semuanya ke Compose sekarang berarti mengganti keputusan stack, menambah pengelolaan volume/database container, serta tidak menghilangkan kebutuhan migrasi dan rollback.

**Skrip pembungkus native systemd** paling kecil perubahannya dan memenuhi kebutuhan pemilik. Antarmuka operasionalnya cukup:

```bash
sudo /opt/pms/deploy.sh
```

Skrip harus dimiliki `root`, tidak writable oleh user deploy, memakai `set -Eeuo pipefail`, lock agar dua deploy tidak bersamaan, dan berhenti pada kesalahan pertama. Rahasia `.env`, kredensial rclone, dan password restic berada di luar checkout/release.

### Release directory, bukan `git pull` in-place

Struktur sederhana:

```text
/opt/pms/
├── current -> releases/20260814-<commit>
├── releases/
├── shared/.env
├── shared/storage/
├── backups/pre-deploy/
├── deploy.sh
└── rollback.sh
```

Setiap deploy membuat release baru. `current` baru diganti setelah dependency, link storage, dan pemeriksaan awal berhasil. Release sebelumnya tetap tersedia untuk rollback cepat. Simpan dua atau tiga release terakhir saja.

Urutan minimum `deploy.sh`:

1. Ambil lock dan catat commit/release aktif.
2. Unduh commit/tag yang hendak dipasang ke direktori release baru. Lebih aman deploy commit/tag eksplisit daripada branch yang dapat berubah di tengah proses.
3. Pasang dependency PHP produksi dari lock file (`composer install --no-dev ...`), atau gunakan artifact release yang dependency-nya sudah dibangun developer.
4. Hubungkan `.env` dan `storage` shared; pastikan permission Laravel benar.
5. Jalankan pemeriksaan konfigurasi dan boot aplikasi pada release baru.
6. Buat backup pra-deploy `pg_dump -Fc` dan pastikan file tidak kosong serta `pg_restore --list` berhasil.
7. Aktifkan maintenance mode untuk bagian perubahan yang tidak kompatibel, lalu jalankan `php artisan migrate --force`.
8. Jalankan `php artisan optimize`; dokumentasi Laravel merekomendasikan `optimize` sebagai bagian deploy. Setelah release berubah, jalankan `php artisan reload` atau restart/reload worker yang dikelola process monitor/systemd agar memakai kode baru.
9. Ganti symlink `current` secara atomik, reload PHP-FPM/worker, matikan maintenance mode, lalu panggil `https://<domain>/up`.
10. Jika health check gagal, kembalikan symlink ke release sebelumnya, reload service, dan kirim notifikasi. Jangan otomatis memulihkan database dump karena itu dapat membuang transaksi pengguna yang masuk setelah backup.

Laravel menyediakan health route `/up`: status 200 bila aplikasi dapat boot dan 500 bila tidak. Event `DiagnosingHealth` dapat ditambah untuk memeriksa database. `APP_DEBUG` wajib `false` di produksi. Sumber: [Laravel 12 Deployment — optimization, reload, debug, health route](https://laravel.com/docs/12.x/deployment).

### Migrasi database tanpa campur tangan

Gunakan:

```bash
php artisan migrate --force
```

`--force` diperlukan agar migrasi produksi berjalan tanpa prompt konfirmasi. Rollback Artisan memang tersedia (`migrate:rollback`), tetapi bukan strategi rollback produksi yang cukup: kode lama dapat rusak bila migrasi sudah menghapus atau mengubah kolom, dan method `down()` tidak mengembalikan data yang sudah hilang. Sumber: [Laravel 12 Database Migrations](https://laravel.com/docs/12.x/migrations).

Pola aman adalah **expand/contract**:

1. Release A menambah tabel/kolom/index baru tanpa menghapus bentuk lama.
2. Kode Release B dapat membaca bentuk lama dan baru; data dipindahkan/backfill bila perlu.
3. Setelah setidaknya satu release stabil, Release C berhenti memakai bentuk lama.
4. Penghapusan kolom lama dilakukan pada deploy terpisah setelah masa rollback berakhir.

Dengan pola ini, rollback aplikasi biasanya hanya memindahkan symlink. Maintenance mode tetap dibutuhkan untuk migrasi yang benar-benar tidak kompatibel; jangan menganggap semua perubahan schema zero-downtime.

## 2. Rollback yang dapat dipahami pemilik

Sediakan dua tingkat pemulihan yang namanya tidak ambigu.

### A. Rollback aplikasi cepat

```bash
sudo /opt/pms/rollback.sh
```

Tindakan skrip:

1. pilih release sebelumnya yang tercatat;
2. pastikan release dan `.env` valid;
3. pindahkan symlink `current` kembali;
4. reload PHP-FPM dan worker;
5. cek `/up` serta koneksi database;
6. kirim hasil berhasil/gagal.

Targetnya hitungan detik-menit dan tidak menyentuh data. Ini hanya dijamin bila migrasi mengikuti expand/contract.

### B. Pemulihan database darurat

Pemulihan dump bukan tombol rollback biasa. Ia memerlukan maintenance mode, konfirmasi eksplisit, backup kondisi terkini, lalu restore ke database baru/sementara lebih dahulu. Alasannya: mengembalikan dump pra-deploy akan menghilangkan seluruh transaksi setelah waktu dump.

`pg_dump` menghasilkan backup konsisten ketika database tetap dipakai dan tidak memblokir pembaca/penulis. Format custom `-Fc` terkompresi secara default dan dipulihkan dengan `pg_restore`. `pg_dump` hanya mencadangkan satu database; role/tablespace cluster perlu `pg_dumpall --globals-only` bila diperlukan. Sumber: [PostgreSQL 16 pg_dump](https://www.postgresql.org/docs/16/app-pgdump.html).

Contoh backup:

```bash
pg_dump --format=custom --file=/opt/pms/backups/pre-deploy/pms.dump pms
pg_restore --list /opt/pms/backups/pre-deploy/pms.dump >/dev/null
```

Contoh validasi restore ke database terpisah:

```bash
createdb --template=template0 pms_restore_test
pg_restore --dbname=pms_restore_test --single-transaction --exit-on-error pms.dump
psql --dbname=pms_restore_test --command='ANALYZE;'
```

`--single-transaction` memastikan seluruh restore berhasil atau tidak ada perubahan yang dikomit dan mengimplikasikan `--exit-on-error`. Secara default, `pg_restore` justru dapat melanjutkan setelah SQL error, sehingga otomasi harus memakai opsi tersebut. Sumber: [PostgreSQL 16 pg_restore](https://www.postgresql.org/docs/16/app-pgrestore.html).

## 3. Backup database dan foto ke Google Drive

### Mengapa rclone saja belum cukup untuk backup

`rclone copy` menyalin file baru/berubah dan tidak menghapus file yang hanya ada di tujuan. `rclone sync` membuat tujuan sama dengan sumber, termasuk menghapus file tujuan yang tidak ada di sumber. Karena foto lokal akan dihapus setelah masa retensi, `sync` dapat ikut menghapus salinan Drive; untuk arsip foto gunakan `copy`. Sumber: [rclone copy](https://rclone.org/commands/rclone_copy/).

`rclone check` membandingkan ukuran dan hash MD5/SHA1 bila tersedia; `--download` dapat membandingkan isi saat hash tidak tersedia. Perintah ini tidak memodifikasi sumber atau tujuan, tetapi keberhasilan check belum membuktikan aplikasi/database dapat dipulihkan. Sumber: [rclone check](https://rclone.org/commands/rclone_check/).

### Rekomendasi dua jalur

#### Jalur 1: foto yang harus dibuka mitra

Gunakan `rclone copy` per jam sesuai ADR-0012:

```bash
rclone copy /srv/pms/storage/app/photos drive-publik:FolderMaster \
  --checksum --log-file=/var/log/pms-photo-copy.log
```

Setelah copy sukses, lakukan `rclone check --one-way` untuk batch terkait. Aplikasi harus menyimpan status bahwa tiap file sudah tersalin sebelum proses retensi boleh menghapus file lokal. Jangan menjadikan keluaran logger rclone sebagai satu-satunya bukti karena log menjelaskan operasi saat proses berjalan; cek exit code dan lakukan verifikasi tujuan.

#### Jalur 2: backup pemulihan bencana

Gunakan restic dengan backend rclone:

```bash
restic -r rclone:gdrive-backup:pms backup \
  /opt/pms/backups/daily/pms.dump \
  /srv/pms/storage/app/photos
```

Restic mendukung repository `rclone:<remote>:<path>` dan menangani start/stop proses rclone. Backup dienkripsi sebelum dikirim. Sumber: [restic — preparing a repository, rclone backend](https://restic.readthedocs.io/en/stable/030_preparing_a_new_repo.html).

Retensi awal yang sederhana:

```bash
restic -r rclone:gdrive-backup:pms forget \
  --keep-daily 14 --keep-weekly 8 --keep-monthly 12 --prune
```

`forget` menghapus referensi snapshot; `prune` baru mengambil kembali ruang yang tidak lagi direferensikan. Uji kebijakan dengan `--dry-run` sebelum mengaktifkan penghapusan terjadwal. Sumber: [restic — removing backup snapshots](https://restic.readthedocs.io/en/stable/060_forget.html).

### Autentikasi Google Drive

Dua opsi rclone yang relevan:

- **OAuth user khusus backup**: token disimpan dalam konfigurasi rclone dan dipakai untuk akses Drive user tersebut. Buat OAuth client sendiri; dokumentasi rclone menyatakan shared client ID rclone akan berhenti bekerja selama 2026 dan aplikasi OAuth berstatus Testing dapat meminta otorisasi ulang mingguan.
- **Service account**: tidak memerlukan browser/login interaktif berkala. Folder atau Shared Drive harus dibagikan kepada alamat service account, atau admin Google Workspace memakai domain-wide delegation/impersonation. File JSON adalah secret dan harus hanya dapat dibaca root/user backup.

Sumber: [rclone Google Drive](https://rclone.org/drive/).

Untuk proyek ini, service account cocok dengan preferensi “tidak login ulang”, tetapi kapasitas harus diverifikasi pada folder/Shared Drive tujuan menggunakan `rclone about`. Jangan berasumsi service account otomatis mendapat storage “unlimited”. Jika Drive organisasi tidak memberi service account ruang/keanggotaan yang tepat, gunakan user Google Workspace khusus backup dengan OAuth client produksi milik sendiri.

Pisahkan remote/folder foto publik dari repository restic privat. Repository restic tidak perlu dan tidak boleh dibuat `Anyone with the link`.

### Jadwal dan bukti bahwa backup dapat dipulihkan

Gunakan systemd timer, bukan cron yang tersebar, agar status dan exit code terpusat. Jadwal minimum:

- tiap jam: `rclone copy` foto publik;
- tiap malam: `pg_dump -Fc`, lalu snapshot restic database + foto;
- tiap minggu: restore dump terbaru ke database sementara, jalankan migration/status dan query invariant aplikasi, lalu hapus database sementara;
- tiap minggu: `restic check`;
- per minggu bergilir: `restic check --read-data-subset=1/4` sampai `4/4` agar seluruh data dibaca dalam empat minggu;
- tiap tiga bulan: simulasi pemulihan lengkap ke direktori/database terpisah dan buka sampel foto dari hasil restore.

`restic check` biasa memeriksa struktur repository tetapi tidak membaca semua pack. `check --read-data` membaca seluruh data; subset pecahan deterministik dapat membagi beban. Restore harus menuju direktori terpisah agar tidak menimpa produksi. Sumber: [restic — working with repositories](https://restic.readthedocs.io/en/stable/045_working_with_repos.html).

Backup dianggap sehat hanya bila empat syarat terpenuhi:

1. job selesai dengan exit code 0;
2. snapshot baru terlihat dan usianya tidak melewati ambang;
3. pemeriksaan integritas selesai;
4. restore uji berhasil, database dapat dibuka, invariant penting lolos, dan sampel foto dapat dibaca.

## 4. Monitoring dan notifikasi tanpa membaca log

Minimum yang cukup:

1. **Aplikasi**: monitor eksternal memanggil `https://<domain>/up` setiap 5 menit. Listener health Laravel juga memeriksa query database ringan.
2. **Backup dan timer**: setiap job memanggil URL dead-man switch ketika mulai, berhasil, atau gagal. Jika ping sukses tidak datang sesuai jadwal + grace period, layanan menandai job down dan mengirim email/webhook. Konsep ini didokumentasikan oleh [Healthchecks.io](https://healthchecks.io/docs/).
3. **Service lokal**: unit systemd memakai `Restart=on-failure` untuk worker yang aman direstart. Kegagalan berulang memicu unit notifikasi/skrip yang mengirim webhook atau mencatat status untuk banner admin.
4. **Kapasitas**: alarm disk saat sisa <10 GB mengikuti ADR-0012; tambah alarm memory pressure/swap dan usia backup terakhir.
5. **Kanal**: email atau webhook eksternal menjadi kanal utama karena tetap bekerja ketika WAHA/server aplikasi mati. WhatsApp dan banner dashboard hanya kanal tambahan.

Pemilik cukup menerima pesan seperti:

```text
PMS GAGAL: backup database harian belum berhasil sejak 02:00.
Jangan deploy. Hubungi pengelola dan buka panduan Pemulihan Backup.
```

Jangan hanya memberi “server error”; pesan harus menyebut komponen, waktu sukses terakhir, tingkat dampak, dan tindakan pertama.

## 5. RAM realistis pada server 4,8 GB

Tidak ada angka resmi tunggal yang dapat menjamin konsumsi Laravel/PostgreSQL/WAHA karena hasil bergantung pada jumlah worker PHP-FPM, `memory_limit`, query, ukuran cache PostgreSQL, queue concurrency, dan pola pesan. Angka berikut adalah **anggaran awal**, bukan benchmark:

| Komponen | Anggaran awal | Pengendali utama |
|---|---:|---|
| OS + systemd + Nginx | 500–900 MB | service lain, filesystem cache |
| PostgreSQL 16 | 500 MB–1,2 GB | `shared_buffers`, koneksi, `work_mem`, query paralel |
| PHP-FPM + Laravel + queue | 600 MB–1,5 GB | jumlah child/worker dan memory per request |
| WAHA NOWEB Docker, 1 sesi | 200–500 MB untuk budget operasional | traffic, runtime Node, pertumbuhan sesi |
| Cadangan/headroom | minimal 1 GB | deploy, dump/restore, lonjakan request, page cache |

WAHA mendokumentasikan bahwa NOWEB tidak menjalankan Chromium sehingga memakai CPU dan RAM lebih rendah dibanding WEBJS/WPP berbasis Puppeteer/Chrome; dokumentasi tidak memberi angka MB yang dijamin. Sumber: [WAHA Engines](https://waha.devlike.pro/docs/how-to/engines/).

Docker menyediakan `docker stats` untuk melihat penggunaan dan limit memory container. Compose `mem_limit` dapat memberi batas keras, misalnya budget awal 512–768 MB untuk WAHA yang kemudian disesuaikan berdasarkan pengukuran. Sumber: [Docker container stats](https://docs.docker.com/reference/cli/docker/container/stats/) dan [Compose service memory limits](https://docs.docker.com/reference/compose-file/services/#mem_limit).

Kesimpulan untuk server ini:

- Laravel + PostgreSQL untuk sekitar 10 user mungkin cukup dalam 4,8 GB bila jumlah worker kecil dan konfigurasi konservatif.
- Server sudah memakai swap sekitar 803 MB sebelum aplikasi dipasang. Ini mengurangi margin dan menunjukkan angka total RAM bukan ruang kosong nyata.
- Upgrade ke **8 GB sebelum WAHA aktif** tetap rekomendasi yang aman. Jika upgrade belum bisa dilakukan, aktifkan WAHA terakhir, tetapkan limit container, mulai dengan satu queue worker, dan ukur minimal satu minggu.
- Ukur memakai `free`, `systemd-cgtop`, `docker stats`, PostgreSQL activity, serta peak RSS PHP-FPM. Upgrade wajib bila swap terus bertambah, terjadi OOM kill, latency meningkat saat backup, atau free+reclaimable memory tidak menyisakan headroom saat beban puncak.

## 6. Apakah perlu panel web untuk restart/status?

### Yang layak dibuat di aplikasi

Halaman admin read-only yang menampilkan:

- versi/commit aktif;
- status `/up` dan koneksi database;
- waktu backup dan restore-test terakhir;
- waktu sinkronisasi foto terakhir;
- kapasitas disk;
- status sesi WAHA serta aksi terbatas “Restart sesi WAHA” dan tampilkan QR bila sesi putus.

Aksi harus memakai endpoint/domain API yang sempit, izin aplikasi khusus, audit log, CSRF protection, dan timeout. API key WAHA tidak pernah dikirim ke browser.

### Yang tidak layak dibuat

Jangan membuat panel generik yang dapat menjalankan shell, `sudo`, restart PostgreSQL/PHP, mengedit `.env`, atau menjalankan deploy. Agar fitur itu bekerja, proses web harus memperoleh privilege OS tinggi; kompromi akun admin atau bug aplikasi lalu berubah menjadi kompromi seluruh server.

Cockpit atau panel server sejenis juga tidak diperlukan untuk pemilik saat ini. Jika kelak dipasang untuk administrator teknis, batasi melalui VPN/IP allowlist, HTTPS, MFA bila tersedia, dan jangan mengekspos port panel langsung ke internet. Untuk kebutuhan sekarang, SSH + dua skrip root-owned lebih kecil permukaan serangannya.

## 7. Checklist implementasi setelah riset

Ini bukan bagian yang dikerjakan oleh tiket riset, tetapi menjadi acceptance checklist untuk effort pembangunan/deployment:

- [ ] `deploy.sh` root-owned, lock, backup pra-migrasi, release directory, atomic symlink, health check, notifikasi.
- [ ] `rollback.sh` hanya rollback aplikasi; prosedur restore database diberi nama dan konfirmasi berbeda.
- [ ] Semua migrasi produksi mengikuti expand/contract atau mendokumentasikan bahwa rollback kode tidak tersedia.
- [ ] Foto memakai `rclone copy`, bukan `sync`, dan status per-file tersimpan sebelum retensi lokal.
- [ ] Backup DR memakai restic repository privat melalui rclone; password dan kredensial tidak berada di Git.
- [ ] systemd timer untuk dump, backup, check, dan restore-test; missed/failed job mengirim alarm eksternal.
- [ ] Uji restore database mingguan dan simulasi penuh triwulanan menghasilkan bukti waktu serta hasil.
- [ ] Upgrade RAM 8 GB sebelum WAHA, atau bukti pengukuran satu minggu menunjukkan headroom aman.
- [ ] UI status bersifat read-only kecuali operasi WAHA yang sempit; tidak ada shell/sudo dari aplikasi web.

## Sumber primer

- [Laravel 12 — Deployment](https://laravel.com/docs/12.x/deployment)
- [Laravel 12 — Database Migrations](https://laravel.com/docs/12.x/migrations)
- [PostgreSQL 16 — pg_dump](https://www.postgresql.org/docs/16/app-pgdump.html)
- [PostgreSQL 16 — pg_restore](https://www.postgresql.org/docs/16/app-pgrestore.html)
- [rclone — Google Drive](https://rclone.org/drive/)
- [rclone — copy](https://rclone.org/commands/rclone_copy/)
- [rclone — check](https://rclone.org/commands/rclone_check/)
- [restic — Preparing a New Repository](https://restic.readthedocs.io/en/stable/030_preparing_a_new_repo.html)
- [restic — Working with Repositories](https://restic.readthedocs.io/en/stable/045_working_with_repos.html)
- [restic — Removing Backup Snapshots](https://restic.readthedocs.io/en/stable/060_forget.html)
- [WAHA — Engines](https://waha.devlike.pro/docs/how-to/engines/)
- [Docker — container stats](https://docs.docker.com/reference/cli/docker/container/stats/)
- [Docker Compose — service memory limits](https://docs.docker.com/reference/compose-file/services/#mem_limit)
- [Healthchecks.io — Documentation](https://healthchecks.io/docs/)
