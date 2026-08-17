# Project Monitoring System

Fondasi aplikasi web THC untuk memantau Project, Mitra, Material, dan Gudang.

## Stack

- PHP 8.3+
- Laravel 12
- Livewire 3
- PostgreSQL 16

## Menjalankan aplikasi

Pastikan ekstensi PHP `pdo_pgsql` aktif dan PostgreSQL tersedia. Salin `.env.example` menjadi `.env`, lalu isi kredensial database aplikasi. Role aplikasi harus merupakan role non-superuser tanpa `BYPASSRLS`.

```bash
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

## Produksi

Produksi berjalan native di Ubuntu melalui Nginx + PHP 8.3 FPM dan PostgreSQL 16; `php artisan serve` hanya untuk pengembangan. Unit systemd untuk queue worker dan scheduler ada di `deploy/systemd/`. Setelah checkout aplikasi berada di `/opt/pms/current`, `.env` dan `storage` disediakan sebagai shared state, lalu pasang dan aktifkan unitnya:

```bash
sudo -n /usr/local/sbin/pms-install php-laravel
```

Gunakan `APP_ENV=production` dan `APP_DEBUG=false` pada `.env` produksi. PHP-FPM dan Nginx tetap dikelola oleh unit paket sistem masing-masing.

Sinkronisasi Foto Pekerjaan memerlukan `rclone` dan konfigurasi remote Google Drive yang tersedia untuk user service `pms` di luar checkout. Isi `PHOTO_SYNC_DISK`, `RCLONE_BINARY`, dan `PHOTO_SYNC_REMOTE_ROOT` pada `.env` bersama; pastikan `pms-schedule.timer` aktif agar `photos:sync` berjalan tiap jam. Jangan menyimpan file Service Account, token, atau kredensial rclone di repository.

## Menjalankan pengujian

Pengujian selalu memakai PostgreSQL, bukan SQLite, agar slice berikutnya dapat membuktikan RLS, trigger, dan grant database. Buat database `project_monitoring_system_testing` dan berikan akses kepada role aplikasi non-superuser tanpa `BYPASSRLS` (mis. `pms_app`). Konfigurasi koneksi (`DB_HOST`, `DB_PORT`, `DB_USERNAME`, dan `DB_PASSWORD`) berasal dari environment atau `.env.testing`; `phpunit.xml` mengunci hanya nama database testing agar test tidak dapat memakai database produksi.

Pada deployment, jalankan migration dengan environment role `pms_migrator`, lalu jalankan aplikasi dengan role `pms_app`; role aplikasi tidak boleh superuser atau memiliki `BYPASSRLS`. Jangan menjalankan migration memakai kredensial `pms_app`.

Seeder menyediakan akun demo `thc@example.com` dan `mitra@example.com` dengan password awal `password`. Ganti `PMS_DEMO_PASSWORD` sebelum menjalankan seeder di lingkungan bersama, atau hapus akun demo setelah verifikasi.

```bash
php artisan test
```
