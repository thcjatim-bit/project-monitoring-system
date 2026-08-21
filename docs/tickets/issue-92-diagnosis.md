# Diagnosis #92 — Presentation Warehouse, formatter Qty, Surat Jalan, dan Transit

Status: selesai — diagnosis, implementasi, dan verifikasi
Tanggal: 2026-08-21
Issue: https://github.com/thcjatim-bit/project-monitoring-system/issues/92
QC: QC-0012, QC-0013, QC-0014

## Ringkasan

Issue #92 bukan satu bug tunggal. Reproduksi terisolasi menunjukkan dua gejala utama yang deterministik:

1. Qty pada halaman Warehouse ditampilkan sebagai angka penyimpanan dengan tiga digit desimal dan tanpa grouping, misalnya `2312279.000`.
2. Halaman Transit menampilkan `SJ #<database id>` alih-alih nomor Surat Jalan resmi.

Selain itu, alur penerbitan Surat Jalan masih melakukan redirect pada tab aktif, dan halaman Operasional Material belum menampilkan pengiriman masuk yang terbuka untuk Warehouse tujuan.

## Reproduksi

Feedback loop menggunakan feature test sementara pada salinan terisolasi source saat ini di `pms-dev`. Test menjalankan alur nyata:

```text
buat user + Warehouse
→ catat penerimaan stok
→ terbitkan Surat Jalan ke Warehouse tujuan
→ render /warehouse dan /warehouse/transit
→ assert format Qty dan nomor Surat Jalan resmi
```

Hasil:

- Repro quantity minimal: satu penerimaan dan satu render `/warehouse` sudah gagal.
- Repro Transit minimal: satu penerimaan, satu transfer, dan satu render `/warehouse/transit` sudah gagal.
- Kedua repro gagal konsisten pada dua pengulangan.
- Output yang mengandung token sesi/CSRF tidak disalin ke dokumentasi.

Focused identity test tetap lulus:

```text
SuratJalanTransferTest::test_direct_transfer_moves_serial_number_and_drum_identities_without_consuming_them
OK (1 test, 10 assertions)
```

## Temuan dan akar masalah

### 1. Formatter Qty belum memiliki satu seam tampilan bersama

`resources/views/warehouse/index.blade.php` memakai `number_format(..., 3, '.', '')` untuk Saldo Warehouse, sementara Aktivitas Buku Transaksi menampilkan `qty_delta` mentah. Halaman detail Surat Jalan dan Transit juga menampilkan nilai mentah atau formatter tiga desimal.

Bukti utama:

- `resources/views/warehouse/index.blade.php:49` — saldo tetap `2312279.000`.
- `resources/views/warehouse/index.blade.php:51` — delta transaksi tidak diformat bersama.
- `resources/views/warehouse/transfer-show.blade.php:49-60` — detail dan form penerimaan tetap memakai tiga desimal.
- `resources/views/warehouse/transit.blade.php:15` — Qty Transit ditampilkan mentah.
- `app/Services/SuratJalanService.php:614-616` — formatter service adalah formatter canonical storage, bukan formatter tampilan.

Kesimpulan: nilai database dan precision transaksi tidak perlu diubah. Yang hilang adalah helper formatter display yang mengelompokkan ribuan, menghapus trailing zero yang tidak perlu, dan mempertahankan digit desimal yang bermakna.

### 2. Transit kehilangan konteks dokumen Surat Jalan

`SuratJalanController::transit()` hanya mengambil `MaterialStok` dengan `lokasi_tipe = transit` dan eager-load Material serta Warehouse. `lokasi_id` kemudian langsung dipakai oleh view sebagai angka ID.

Bukti utama:

- `app/Http/Controllers/SuratJalanController.php:184-196` — query Transit tidak memuat Surat Jalan.
- `resources/views/warehouse/transit.blade.php:12` — label menjadi `SJ #{{ $stock->lokasi_id }}`.
- `resources/views/warehouse/transit.blade.php:13-16` — tidak ada nomor resmi, Warehouse tujuan, semantic status badge, atau action Detail yang jelas.

Daftar Surat Jalan umum sudah memiliki sebagian kontrak yang benar—nomor resmi, rute, status badge, Detail, dan Cetak—di `resources/views/warehouse/transfers.blade.php:10-12`. Kekurangannya terutama berada pada representasi Transit.

### 3. Penerbitan Surat Jalan masih redirect ke tab aktif

`SuratJalanController::issue()` mengembalikan redirect langsung ke route print pada baris `app/Http/Controllers/SuratJalanController.php:97-99`. Form di `resources/views/warehouse/index.blade.php:41` hanya memiliki `data-submit-loading`, dan script di baris 73 hanya men-disable tombol.

Tidak ada `target="_blank"`, `window.open`, atau alur client-side yang membuka tab setelah respons create berhasil. Karena itu browser mengganti halaman aplikasi dengan halaman print.

### 4. Pengiriman masuk belum menjadi bagian dari landing page Warehouse

Endpoint penerimaan sudah tersedia melalui `warehouse.transfers.receive` dan form pada detail Surat Jalan (`resources/views/warehouse/transfer-show.blade.php:54-64`). Namun `MaterialInventoryController::index()` hanya mengirim Warehouse, Material, saldo, drum, transaksi, dan destination warehouses ke view. Tidak ada daftar Surat Jalan berstatus `terbit` dengan tujuan salah satu Warehouse yang ditugaskan.

Akibatnya state machine penerimaan sudah ada, tetapi discoverability dan jalur kerja operator tujuan belum ada di halaman Operasional Material.

### 5. Temuan SN/Drum dari QC-0012 sudah tertutup pada source saat ini

Form penerimaan, pengeluaran, dan item Surat Jalan sudah memiliki field conditional `serial_number` dan `drum_id` di `resources/views/warehouse/index.blade.php:19,28,44`. JavaScript mengubah visibility dan `required` berdasarkan jenis Material, sedangkan validasi backend tetap berada di `MaterialInventoryController`.

Focused identity-transfer test lulus, sehingga temuan “field identifier tidak tersedia” tampaknya berasal dari versi source/deployment sebelumnya, bukan bug aktif pada source saat diagnosis.

## Hipotesis yang diuji

1. Formatting terpecah dan hard-coded — **terkonfirmasi**.
2. Transit hanya memiliki `lokasi_id` stok tanpa relasi Surat Jalan di view — **terkonfirmasi**.
3. Redirect create menyebabkan print membuka tab aktif — **terkonfirmasi melalui controller/form contract**.
4. Receipt tersedia di detail tetapi tidak ditemukan dari landing page Warehouse — **terkonfirmasi melalui controller data contract dan route**.
5. SN/Drum masih rusak — **tidak terkonfirmasi; focused test lulus dan field sudah ada**.

## Batasan verifikasi

- PostgreSQL tidak berjalan pada Windows workstation, sehingga test lokal gagal sebelum assertion dengan connection refused.
- Testing database `pms-dev` sempat mengalami credential drift. `scripts/bootstrap-testing.sh` berhasil membangun ulang database disposable yang disetujui dan memverifikasi role/RLS.
- Tidak ada perubahan source, database produksi, atau konfigurasi produksi selama diagnosis.

## Batasan perbaikan yang diterapkan

Implementasi memisahkan canonical storage formatting dari display formatting:

1. Tambahkan shared quantity display formatter dan gunakan pada semua output quantity Warehouse, Surat Jalan, Transit, Drum, dan ledger.
2. Beri Transit akses ke official Surat Jalan number serta origin/destination/status melalui query/read model yang jelas; jangan mengubah makna `material_stoks` atau ledger.
3. Pertahankan create sebagai operasi server-side yang atomik, lalu buka halaman print pada tab baru hanya setelah create berhasil.
4. Tambahkan daftar Surat Jalan masuk yang masih terbuka pada data contract halaman Warehouse dan gunakan endpoint penerimaan existing.
5. Tambahkan regression assertions untuk format Qty, nomor resmi/rute/status Transit, perilaku tab aktif, dan discoverability penerimaan.

Fitur yang harus tetap tidak berubah: SN/Drum identifier, multi-item Surat Jalan, partial receipt, Transit per item, append-only ledger, retur reverse Surat Jalan, correction append-only, permission, dan state machine.

## Implementasi selesai

Rangkaian commit issue #92, berujung pada `9e1f8a65c5cb8b8cec863a27571b238a02556844`, menerapkan seluruh perbaikan dalam scope issue:

1. `App\\Support\\QuantityDisplayFormatter` menjadi module tampilan bersama dengan interface satu metode `format`. Module ini menyembunyikan aturan grouping ribuan, koma desimal, dan penghapusan trailing zero; precision canonical storage dan formatter service transaksi tetap tidak berubah.
2. Query Transit eager-load relasi `SuratJalan` beserta origin, destination, dan items. Controller menyiapkan data contract per item, termasuk status partial receipt; view menampilkan nomor Surat Jalan resmi, rute, sisa Transit, semantic status badge, Detail, dan Cetak.
3. Form penerbitan dari landing page Warehouse dan seluruh action Cetak memakai `target="_blank"` serta `rel="noopener noreferrer"`, sehingga operasi server-side tetap atomik dan halaman aplikasi tidak tergantikan oleh halaman print.
4. Data contract landing page Warehouse menambahkan pengiriman masuk berstatus `terbit` yang menuju Warehouse yang ditugaskan. Jalur penerimaan menggunakan endpoint existing dan tidak membuat transaksi ganda.
5. Regression test ditambahkan untuk formatter display/input, pengiriman masuk, tab baru aman, status Transit per item pada multi-item Surat Jalan, nomor/rute/status Transit, dan preservasi identitas SN/Drum.

### Keputusan desain module

Seam dipasang pada helper tampilan, bukan pada model atau buku transaksi. Interface yang kecil memberi leverage ke seluruh view Warehouse/Surat Jalan, sementara implementation detail format terkonsentrasi pada satu tempat sehingga locality perubahan tetap tinggi. Query Transit menjadi bagian implementation controller; view hanya mengonsumsi data contract yang sudah memuat konteks Surat Jalan dan tidak mengetahui `lokasi_id` sebagai database ID.

Interface test surface adalah output HTML dan hasil `QuantityDisplayFormatter::format`, sehingga regression test tidak bergantung pada struktur internal query atau ledger. Tidak ada adapter tambahan karena tidak ada variasi dependency lintas seam yang perlu ditukar.

## Verifikasi penyelesaian

Pada `pms-dev`, temporary worktree bersih dari commit di atas menjalankan focused PostgreSQL tests:

```text
php artisan test tests/Feature/QuantityDisplayFormatterTest.php tests/Feature/MaterialOperationalUiTest.php tests/Feature/SuratJalanTransferTest.php
Tests: 21 passed (169 assertions)
```

Suite penuh pada SHA final juga lulus:

```text
php artisan test
Tests: 270 passed (1794 assertions)
```

Perubahan dokumentasi ini melengkapi commit implementasi di `main`; issue GitHub #92 ditutup setelah commit ini dipush dan diverifikasi.
