# CONTEXT — Project Monitoring System THC

Glosarium istilah domain. Pakai istilah persis seperti di sini pada nama tabel, kelas, judul issue, dan teks UI.

## Aktor & kepemilikan

- **THC** — pemilik sistem. User THC melihat seluruh data lintas mitra.
- **Mitra** — kontraktor yang mengerjakan **jasa saja** (bukan material). Punya user sendiri di sistem yang sama. Hanya melihat data miliknya.
- **Isolasi mitra** — jaminan bahwa user mitra mustahil membaca atau mengubah baris milik mitra lain. Ditegakkan di database lewat Row-Level Security, bukan di kode aplikasi. Lihat `docs/adr/0001-isolasi-mitra-row-level-security.md`.
- **Tabel bertenant** — tabel yang punya kolom `mitra_id` dan tunduk pada RLS.
- **Tabel bersama** — master data lintas mitra (Material, Unit, PoP, Pekerjaan Jasa). Tidak punya `mitra_id`; boleh dibaca semua, hanya bisa ditulis THC.
- **Grup** — kumpulan hak akses (matriks centang menu) yang dipasang ke User. Preset peran bawaan disediakan, tapi matriksnya bebas.

## Proyek

- **Project** — satu pekerjaan instalasi yang dimonitor. Dimiliki tepat satu Mitra.
- **ID Project** — pengenal yang dibaca manusia, format `PRJ-YYMM-NNNN`. Diisi manual atau dibentuk otomatis saat dikosongkan; tidak pernah berubah setelah terbit.
- **PoP** — Point of Presence, lokasi simpul jaringan yang jadi acuan project.
- **Step** — penanda fase project (Design, Survey, DRM, SPK, Pengadaan Material, Delivery Material, MOS, Deployment, Test Comm, ATP, GO Live). Ditandai manual, **terpisah dari kurva S**.
- **Pekerjaan Jasa** — jenis pekerjaan yang ditagihkan mitra (mis. penarikan kabel per meter). Katalognya bersama; **harganya per mitra**.
- **Harga Jasa Mitra** — harga satu Pekerjaan Jasa untuk satu Mitra sesuai PKS, berlaku sejak tanggal tertentu.
- **PKS** — Perjanjian Kerja Sama antara THC dan Mitra; sumber harga jasa.
- **RAB Jasa** — daftar Pekerjaan Jasa + qty pada satu Project, dengan **harga yang dibekukan** saat baris dibuat. Jadi bobot kurva S.
- **Kurva S** — kurva progres berbobot rupiah jasa (bukan material).
- **SPI** — Schedule Performance Index, rasio progres aktual terhadap baseline.
- **TOC** — Target Operation Complete, tanggal target selesai project.

## Gudang & material

- **Material** — barang milik **THC**, walau dititipkan di gudang mitra.
- **Unit** — satuan material (meter, batang, pcs).
- **Warehouse** — gudang. Bisa milik THC atau titipan di mitra.
- **Petugas Gudang** — user yang mencatat barang masuk/keluar di satu atau lebih Warehouse.
- **SN** — Serial Number material tertentu; dilacak sampai keluar gudang dan untuk project mana.
- **Surat Jalan** — dokumen barang keluar, terbit setelah request mitra di-approve THC.
- **Jenis material** — `biasa` (hanya jumlah), `ber_sn` (identitas per butir), `drum_kabel` (identitas + isi yang bisa berkurang). Menentukan tabel identitas mana yang dipakai. Lihat `docs/adr/0004-tiga-jenis-material-satu-buku.md`.
- **Drum** — satu roll kabel fisik dengan `panjang_awal` dan `sisa`. Potongan yang keluar jadi **drum turunan** ber-ID `DRM-00042-1`, punya baris sendiri, tidak pernah digabung balik ke induk.
- **Buku transaksi** — `material_transaksis`, append-only. Satu-satunya kebenaran stok; `UPDATE`/`DELETE` dicabut dari role aplikasi. Koreksi = baris pembatalan, bukan hapus. Lihat `docs/adr/0003-stok-material-buku-transaksi.md`.
- **Saldo stok** — `material_stoks`, cache per (lokasi, material) yang ditulis **trigger**, bukan kode aplikasi. Boleh dibangun ulang dari buku kapan saja.
- **Lokasi material** — tiga kemungkinan: `warehouse` (di gudang), `project` (sudah keluar, di lapangan, **belum terpasang**), `terpasang` (keluar dari stok, masuk realisasi project).
- **Rekon material** — pencocokan THC ↔ mitra di akhir project atas material yang keluar gudang. Sisa dikembalikan ke gudang; selisih yang tidak kembali diakui hilang/rusak dengan penanggung jawab.

## Konvensi lintas domain

- **Nomor WhatsApp** — disimpan dalam format E.164 tanpa `+` (`628123456789`), ditampilkan sebagai tautan `wa.me` yang bisa diklik.
- **Master data** — tabel nyata per entitas, di-CRUD dari UI. **Bukan** tabel serba-guna / EAV. Lihat `docs/adr/0002-master-data-tabel-nyata.md`.
- **Nonaktif, bukan hapus** — baris master yang sudah dipakai transaksi tidak dihapus; ditandai `aktif = false`.
