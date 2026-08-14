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
sudo cp deploy/systemd/pms-*.service deploy/systemd/pms-*.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now pms-queue.service pms-schedule.timer
```

Gunakan `APP_ENV=production` dan `APP_DEBUG=false` pada `.env` produksi. PHP-FPM dan Nginx tetap dikelola oleh unit paket sistem masing-masing.

## Menjalankan pengujian

Pengujian selalu memakai PostgreSQL, bukan SQLite, agar slice berikutnya dapat membuktikan RLS, trigger, dan grant database. Buat database `project_monitoring_system_testing` dan berikan akses kepada role aplikasi non-superuser tanpa `BYPASSRLS` (mis. `pms_app`). Konfigurasi koneksi (`DB_HOST`, `DB_PORT`, `DB_USERNAME`, dan `DB_PASSWORD`) berasal dari environment atau `.env.testing`; `phpunit.xml` mengunci hanya nama database testing agar test tidak dapat memakai database produksi.

Pada deployment, jalankan migration dengan environment role `pms_migrator`, lalu jalankan aplikasi dengan role `pms_app`; role aplikasi tidak boleh superuser atau memiliki `BYPASSRLS`.

```bash
php artisan test
```
