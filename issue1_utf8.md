## Destination

Spesifikasi dan keputusan teknis terkunci untuk **Project Monitoring System THC** ΓÇö model data, stack, hak akses multi-tenant, alur gudang (SN / drum kabel / surat jalan), perhitungan kurva S & SPI, integrasi (Google Drive, WhatsApp gateway, API), dan cara deploy di server Ubuntu kantor. Peta ini **tidak membangun aplikasinya**; peta ini selesai ketika tidak ada lagi keputusan desain yang tersisa, sehingga pembangunan bisa dijalankan per gelombang sebagai effort terpisah.

## Notes

**Domain**: Monitoring proyek instalasi jaringan fiber optik. THC (pemilik sistem) mengelola project; **Mitra** adalah kontraktor yang mengerjakan **jasa saja** (bukan material) dan ikut login ke sistem yang sama.

**Skill yang dipakai tiap sesi**: `/grilling` dan `/domain-modeling` untuk tiket keputusan; `/research` untuk tiket riset; `/prototype` untuk tiket prototipe.

**Batasan dan preferensi yang sudah tetap (jangan digugat ulang tanpa alasan baru):**

- User tidak bisa coding dan merawat server sendirian; nyaman SSH tapi ragu-ragu ΓÇö **update harus satu perintah salin-tempel**, panduan berbahasa Indonesia.
- Server (terverifikasi, lihat Verifikasi kesiapan server): **Ubuntu 22.04.5 LTS, 4 core, 4,8GB RAM, disk 109GB (sisa 63GB)**, IP publik **statis**, Docker terpasang, SSH sebagai user `jawan`. Diakses lewat **port forward MikroTik**, sementara **tanpa HTTPS**.
- **Stack terkunci**: PHP 8.3 + Laravel 12 + Livewire 3, PostgreSQL 16, deploy **native systemd** di satu mesin (kecuali WAHA yang tetap Docker). Aset di-build di mesin developer ΓÇö server tidak perlu Node.js.
- ~10 user aktif bersamaan. **Web based, mobile friendly** ΓÇö tidak ada aplikasi mobile terpisah.
- **Hybrid build (Q6c)**: kode custom, tapi master data (material, jasa, unit, PoP, mitra) sepenuhnya CRUD dari UI.
- **Bahasa UI: Indonesia**; istilah teknis lazim (SN, PoP, SPI, TOC) dibiarkan apa adanya.
- **Kurva S berbobot rupiah jasa** ΓÇö harga jasa berbeda per mitra, di-create di data master. Material tidak masuk bobot.
- **Step project** (Design, Survey, DRM, SPK, Pengadaan Material, Delivery Material, MOS, Deployment, Test Comm, ATP, GO Live) = penanda fase manual, **terpisah dari kurva S**.
- Dashboard project menampilkan **indikator kesiapan material** berdampingan dengan kurva S (dilacak via project ID pada transaksi material).
- **Isolasi mitra**: mitra hanya melihat project, gudang, dan request miliknya sendiri. Wajib sejak awal di setiap tabel dan query.
- **Material milik THC** walau berada di gudang mitra (titipan). Multi-gudang dengan transfer antar gudang.
- **Alur approve**: request material mitra ΓåÆ approve THC ΓåÆ barang keluar di user THC ΓåÆ surat jalan terbit. Progres harian mitra ΓåÆ verifikasi THC ΓåÆ baru masuk kurva S.
- **Drum kabel**: potongan keluar mendapat ID turunan yang dapat dilacak (Q19b). Bentuknya terkunci di ADR-0004.
- **SN**: untuk sekarang dilacak sampai barang keluar dan ke project mana ΓÇö belum sampai titik pasang.
- **Hak akses matriks bebas** (grup + centang menu) dengan preset peran bawaan.
- **Foto**: disimpan di disk server + dikompres, lalu **disalin otomatis ke Google Drive** (unlimited) dengan susunan `ProjectID / Step / Tanggal`, agar mitra bisa mengaksesnya.
- **QR**: stiker berisi teks terbaca manusia **dan** QR yang menunjuk ke data hidup di sistem.
- **Notifikasi**: WhatsApp gateway **tidak resmi** (WAHA/Baileys) dengan nomor khusus ΓÇö risiko blokir diterima, mitigasi jadi tiket riset.
- **Backup**: harian ke Google Drive.
- **Integrasi**: REST API baca + user PostgreSQL read-only. Belum ada sistem konsumen ΓÇö rancang standar.
- **Export**: Excel wajib duluan, PDF menyusul, PPT ditunda.
- **Urutan gelombang pembangunan**: (1) Login + hak akses + Data Master + modul gudang lengkap; (2) Project tracking, kurva S, SPI, foto, komentar; (3) Dashboard gabungan, export, API.

## Decisions so far

<!-- satu baris per tiket yang ditutup -->

- [Model hak akses: matriks menu di atas isolasi mitra](https://github.com/thcjatim-bit/project-monitoring-system/issues/6) ΓÇö Akses matriks aplikasi (Grup & Izin aksi) beroperasi terpisah dari Isolasi RLS Database. User Mitra vs THC ditentukan dari mitra_id. Hak khusus (seperti ^Gpprove_material_request) adalah entitas izin mandiri. UI bereaksi menyembunyikan menu/akses yang tak diizinkan, API mengembalikan 403. ADR-0006.

- [Verifikasi kesiapan server Ubuntu dan akses jaringan](https://github.com/thcjatim-bit/project-monitoring-system/issues/14) ΓÇö Ubuntu 22.04.5, 4 core, 4,8GB RAM, disk **109GB bukan 500GB** (sisa 63GB), IP publik statis, Docker terpasang; swap sudah terpakai 803MB sebelum sistem ini dipasang.
- [Pilih stack teknis dan bentuk deploy](https://github.com/thcjatim-bit/project-monitoring-system/issues/2) ΓÇö PHP 8.3 + Laravel 12 + Livewire 3, PostgreSQL 16, native systemd satu mesin (WAHA satu-satunya Docker), update lewat `sudo /opt/pms/deploy.sh` yang selalu `pg_dump` sebelum migrasi; 4,8GB cukup untuk gelombang 1ΓÇô2, **upgrade ke 8GB sebelum WhatsApp gateway menyala**.

- [Model data inti dan penegakan isolasi mitra](https://github.com/thcjatim-bit/project-monitoring-system/issues/3) ΓÇö entitas inti + kontak mitra sebagai tabel sendiri (WA klik-ke-`wa.me`); isolasi mitra lewat **PostgreSQL RLS** (`FORCE`, `WITH CHECK`, default deny ΓÇö lupa set konteks = nol baris, bukan bocor); harga jasa per mitra di `mitra_harga_jasas` dan **dibekukan** saat masuk RAB; ID Project `PRJ-YYMM-NNNN`; master data = tabel nyata + CRUD generik, EAV ditolak. ADR-0001, ADR-0002.

- [Model material: biasa, ber-SN, dan drum kabel](https://github.com/thcjatim-bit/project-monitoring-system/issues/4) ΓÇö satu **buku transaksi append-only** untuk tiga jenis, identitas dipisah (`material_sns`, `drums`); saldo `material_stoks` cuma cache yang ditulis trigger + `CHECK (qty >= 0)`; drum turunan `DRM-00042-1` tidak pernah digabung balik ke induk; **lokasi punya beberapa nilai** ΓÇö `warehouse` / `project` (di lapangan, belum terpasang) / `terpasang` (kemudian bertambah `transit`, lihat ADR-0005), yang membuat empat angka di layar progres harian mitra jadi saldo nyata, bukan hasil pengurangan. ADR-0003, ADR-0004.

- [Alur material: request, approve, surat jalan, transfer gudang](https://github.com/thcjatim-bit/project-monitoring-system/issues/5) ΓÇö **request dan surat jalan punya siklus hidup sendiri**; status penuh/sebagian dihitung dari qty diterima, bukan diketik; satu dokumen surat jalan `SJ-YYMM-NNNN` untuk semua arah (request mitra maupun transfer langsung THC); **`transit` jadi lokasi keempat** ΓÇö stok tidak mendarat di gudang tujuan sampai dikonfirmasi terima, sehingga selisih pengiriman punya tempat dan terlihat hari itu juga; koreksi terbagi tiga (batal saat masih `terbit`, retur = surat jalan balik, koreksi = baris pembalik + baris benar). ADR-0005.

- [Penyelesaian spesifikasi sisa di peta: Kurva S, Barang Transit, Toleransi, HTTPS, QR, dan Deployment](https://github.com/thcjatim-bit/project-monitoring-system/issues/1) — Semua keputusan teknis terkait aturan main SPI, revisi TOC, amandemen RAB, peringatan material transit, serta deployment flow developer dikunci. ADR-0009.

- [Step project, komentar, mention, dan linimasa aktivitas](https://github.com/thcjatim-bit/project-monitoring-system/issues/8) — Step project fleksibel (bisa dilompati/dimundurkan) dengan tanggal aktual; Linimasa Gabungan menyatukan log sistem otomatis dan komentar user; Komentar Internal tersedia khusus tim THC; komentar tidak bisa dihapus namun bisa diedit; Mention didukung notifikasi web dan WhatsApp. ADR-0011.
## Not yet specified

(Semua spesifikasi telah diselesaikan dan dikunci. Peta selesai.)

## Out of scope

- **Export ke PowerPoint** ΓÇö ditunda; PDF dianggap cukup untuk kebutuhan rapat pada tahap ini.
- **Aplikasi mobile native** ΓÇö web mobile-friendly dinilai cukup untuk ~10 user aktif.
- **Pelacakan SN sampai titik terpasang** ΓÇö untuk sekarang berhenti di "keluar gudang, untuk project mana".
- **Modul harga material / pengadaan bernilai rupiah** ΓÇö mitra mengerjakan jasa saja; harga material tidak diperlukan.

