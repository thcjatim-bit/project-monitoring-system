# CONTEXT — Project Monitoring System THC

Glosarium istilah domain. Pakai istilah persis seperti di sini pada nama tabel, kelas, judul issue, dan teks UI.

## Aktor & kepemilikan

- **THC** — pemilik sistem. User THC melihat seluruh data lintas mitra.
- **Mitra** — kontraktor yang mengerjakan **jasa saja** (bukan material). Punya user sendiri di sistem yang sama. Hanya melihat data miliknya.
- **Isolasi mitra** — jaminan bahwa user mitra mustahil membaca atau mengubah baris milik mitra lain. Ditegakkan di database lewat Row-Level Security, bukan di kode aplikasi. Lihat `docs/adr/0001-isolasi-mitra-row-level-security.md`.
- **Tabel bertenant** — tabel yang punya kolom `mitra_id` dan tunduk pada RLS.
- **Tabel bersama** — master data lintas mitra (Material, Unit, PoP, Pekerjaan Jasa). Tidak punya `mitra_id`; boleh dibaca semua, hanya bisa ditulis THC.
- **Grup** — kumpulan hak akses (matriks centang menu) yang dipasang ke User. Preset peran bawaan disediakan, tapi matriksnya bebas.
- **Izin Aksi (Permissions)** — izin granular spesifik per aksi (seperti CRUD dan persetujuan/approve). Izin beroperasi di level aplikasi dan mengkondisikan elemen antarmuka (menu disembunyikan jika tidak ada akses). Lihat `docs/adr/0006-model-hak-akses-matriks-aksi.md`.
- **User THC vs User Mitra** — dibedakan dari isi `mitra_id` pada entitas User; `null` berarti internal THC.
- **Penugasan Gudang** — satu user dapat ditugaskan ke lebih dari satu gudang via tabel pivot.
## Proyek

- **Project** — satu pekerjaan instalasi yang dimonitor. Dimiliki tepat satu Mitra.
- **ID Project** — pengenal yang dibaca manusia, format `PRJ-YYMM-NNNN`. Diisi manual atau dibentuk otomatis saat dikosongkan; tidak pernah berubah setelah terbit.
- **PoP** — Point of Presence, lokasi simpul jaringan yang jadi acuan project.
- **Step** — penanda fase project baku (11 step). Fleksibel (bisa dilompati/dimundurkan) dan hanya mencatat tanggal aktual selesai. Terpisah dari kurva S.
- **Pekerjaan Jasa** — jenis pekerjaan yang ditagihkan mitra (mis. penarikan kabel per meter). Katalognya bersama; **harganya per mitra**.
- **Harga Jasa Mitra** — harga satu Pekerjaan Jasa untuk satu Mitra sesuai PKS, berlaku sejak tanggal tertentu.
- **PKS** — Perjanjian Kerja Sama antara THC dan Mitra; sumber harga jasa.
- **RAB Jasa** — daftar Pekerjaan Jasa + qty pada satu Project, dengan **harga yang dibekukan** saat baris dibuat. Jadi bobot kurva S.
- **Kurva S** — kurva progres berbobot rupiah jasa (bukan material).
- **SPI** — Schedule Performance Index, rasio progres aktual terhadap baseline. (Ditampilkan `N/A` jika kumulatif baseline 0%).
- **TOC** — Target Operation Complete, tanggal target selesai project.
- **Original & Revised Baseline** — Jika TOC diundur, kurva S awal dibekukan (Original), dan kurva baru (Revised) dicetak. Kinerja diukur terhadap Revised Baseline.
- **Variation Order** — Perubahan (tambah/kurang) RAB Jasa di tengah jalan. Bobot 100% dihitung ulang (*recalculated*) berdasarkan *grand total* baru. Harga PKS baru hanya berlaku pada *item* tambahan.
- **Linimasa Gabungan** — Satu riwayat aktivitas project yang mencampur log sistem otomatis (surat jalan, pindah step) dan komentar diskusi antar user.
- **Komentar Internal** — Tipe komentar di linimasa yang hanya bisa dibaca oleh user THC, tersembunyi dari Mitra. Tidak boleh dihapus, hanya boleh diedit.

## Gudang & material

- **Material** — barang milik **THC**, walau dititipkan di gudang mitra.
- **Unit** — satuan material (meter, batang, pcs).
- **Warehouse** — gudang. Bisa milik THC atau titipan di mitra.
- **Petugas Gudang** — user yang mencatat barang masuk/keluar di satu atau lebih Warehouse.
- **SN** — Serial Number material tertentu; dilacak sampai keluar gudang dan untuk project mana.
- **Request Material** — permintaan material oleh Mitra ke THC. Satu request bisa dipenuhi beberapa Surat Jalan (kirim bertahap); status `terpenuhi_sebagian` dan `selesai` **dihitung** dari qty yang diterima, tidak diketik. Lihat `docs/adr/0005-alur-perpindahan-material-transit.md`.
- **Surat Jalan** — dokumen perpindahan material antar gudang, nomor `SJ-YYMM-NNNN`, wajib bisa dicetak. Dipakai untuk semua arah (THC↔mitra, THC↔THC); berawal dari Request Material atau dari input langsung petugas THC. Siklusnya `terbit` → `diterima`, atau `terbit` → `dibatalkan`.
- **Transit** — lokasi material yang sudah keluar gudang asal tapi belum tercatat masuk di gudang tujuan. Melekat pada satu Surat Jalan. **Bukan** stok gudang manapun; selisih pengiriman tertinggal di sini sampai THC menyelesaikannya sebagai hilang atau kembali ke asal.
- **Retur** — pengembalian barang yang sudah diterima. Surat Jalan **baru arah sebaliknya** yang menunjuk Surat Jalan asal — bukan pembatalan, karena barangnya memang bergerak lagi.
- **Jenis material** — `biasa` (hanya jumlah), `ber_sn` (identitas per butir), `drum_kabel` (identitas + isi yang bisa berkurang). Menentukan tabel identitas mana yang dipakai. Lihat `docs/adr/0004-tiga-jenis-material-satu-buku.md`.
- **Drum** — satu roll kabel fisik dengan `panjang_awal` dan `sisa`. Potongan yang keluar jadi **drum turunan** ber-ID `DRM-00042-1`, punya baris sendiri, tidak pernah digabung balik ke induk.
- **Buku transaksi** — `material_transaksis`, append-only. Satu-satunya kebenaran stok; `UPDATE`/`DELETE` dicabut dari role aplikasi. Koreksi = baris pembatalan, bukan hapus. Lihat `docs/adr/0003-stok-material-buku-transaksi.md`.
- **Saldo stok** — `material_stoks`, cache per (lokasi, material) yang ditulis **trigger**, bukan kode aplikasi. Boleh dibangun ulang dari buku kapan saja.
- **Lokasi material** — empat kemungkinan: `warehouse` (di gudang), `transit` (dalam perjalanan antar gudang), `project` (sudah keluar, di lapangan, **belum terpasang**), `terpasang` (keluar dari stok, masuk realisasi project).
- **Rekon material** — pencocokan THC ↔ mitra di akhir project atas material yang keluar gudang. Sisa dikembalikan ke gudang; selisih yang tidak kembali diakui hilang/rusak dengan penanggung jawab.
- **Waste/Loss** — Selisih material (seperti potongan kabel) pada akhir proyek yang tidak dapat diretur dan diotorisasi/dinilai manual oleh THC sebagai pembuangan wajar atau kehilangan.

## Konvensi lintas domain

- **Nomor WhatsApp** — disimpan dalam format E.164 tanpa `+` (`628123456789`), ditampilkan sebagai tautan `wa.me` yang bisa diklik.
- **Master data** — tabel nyata per entitas, di-CRUD dari UI. **Bukan** tabel serba-guna / EAV. Lihat `docs/adr/0002-master-data-tabel-nyata.md`.
- **Nonaktif, bukan hapus** — baris master yang sudah dipakai transaksi tidak dihapus; ditandai `aktif = false`.
- **Cloudflare Tunnel** — Pendekatan infrastruktur untuk mendapatkan domain dan HTTPS tanpa perlu *port-forward* di MikroTik.
- **Build Artifact Git** — Aset statis (*frontend build*) dihasilkan di mesin lokal *developer* lalu di-*commit* ke repositori agar instalasi *server* tetap ringkas tanpa dependensi Node.js.
- **Foto pekerjaan** — dokumentasi lapangan (hanya JPEG, maks 10/unggahan, maks 5 MB mentah). Dikompres client-side ke 1920×1080 sebelum upload. Disimpan di disk server, lalu disalin ke Google Drive via `rclone` tiap jam. Retensi server 90 hari; setelahnya Google Drive = sumber kebenaran. Lihat `docs/adr/0012-alur-foto-pekerjaan-dan-sinkronisasi-google-drive.md`.
- **Folder Master** — satu folder Google Drive publik View-Only yang berisi semua foto project dalam struktur `ProjectID / Step / Tanggal`. Mitra mengaksesnya lewat tombol di aplikasi web.
