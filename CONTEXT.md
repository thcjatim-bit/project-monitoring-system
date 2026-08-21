# CONTEXT — Project Monitoring System THC

Glosarium istilah domain. Pakai istilah persis seperti di sini pada nama tabel, kelas, judul issue, dan teks UI.

## Aktor & kepemilikan

- **THC** — pemilik sistem. User THC melihat seluruh data lintas mitra.
- **Mitra** — kontraktor yang mengerjakan **jasa saja** (bukan material). Punya user sendiri di sistem yang sama. Hanya melihat data miliknya.
- **Kode Mitra** — pengenal Mitra yang unik dan tidak berubah tanpa tindakan THC; boleh diisi manual untuk kode lama atau dibentuk otomatis sebagai `MTR-YYMM-NNNN` saat onboarding. Nomor otomatis berurutan per bulan dan tidak digunakan kembali.
- **Isolasi mitra** — jaminan bahwa user mitra mustahil membaca atau mengubah baris milik mitra lain. Ditegakkan di database lewat Row-Level Security, bukan di kode aplikasi. Lihat `docs/adr/0001-isolasi-mitra-row-level-security.md`.
- **Tabel bertenant** — tabel yang punya kolom `mitra_id` dan tunduk pada RLS.
- **Tabel bersama** — master data lintas mitra (Material, Unit, PoP, Pekerjaan Jasa). Tidak punya `mitra_id`; boleh dibaca semua, hanya bisa ditulis THC.
- **Tabel hibrida** — tabel bertenant yang barisnya boleh ber-`mitra_id` NULL untuk menandai kepemilikan THC. Dapat dibaca lintas tenant, tetapi hanya dapat ditulis dalam tenantnya sendiri. Saat ini hanya Warehouse. Pengecualian yang harus disebut namanya, bukan pola yang boleh ditiru; lihat `docs/adr/0023-warehouse-tabel-hibrida.md`.
- **Grup** — kumpulan hak akses (matriks centang menu) yang dipasang ke User. Preset peran bawaan disediakan, tapi matriksnya bebas untuk dikelola THC; pilihan Grup oleh Admin Mitra tetap dibatasi pada capability operasional Mitra.
- **Grup Operasional Mitra** — Grup yang dapat dipasang Admin Mitra kepada User Mitra dalam tenantnya sendiri. Grup ini tidak boleh membuka capability THC-only, mengubah `mitra_id`, atau mengubah matriks izin global.
- **Izin Aksi (Permissions)** — izin granular spesifik per aksi (seperti CRUD dan persetujuan/approve). Izin menentukan capability aplikasi, bukan kepemilikan data: authorization server-side tetap memeriksa tipe User dan cakupan Mitra, sementara elemen antarmuka menyembunyikan menu jika tidak ada akses. Lihat `docs/adr/0006-model-hak-akses-matriks-aksi.md`.
- **User THC vs User Mitra** — dibedakan dari isi `mitra_id` pada entitas User; `null` berarti internal THC.
- **Admin Mitra** — User Mitra dengan kewenangan super user dalam satu Mitra. Dapat menjalankan seluruh capability operasional yang tersedia bagi User Mitra lain, termasuk mengelola User, Warehouse, Project, Material, dan komentar, tetapi tidak dapat melakukan keputusan yang menjadi kewenangan THC atau melewati isolasi mitra.
- **Workspace Admin Mitra** — kumpulan halaman operasional untuk User Mitra dalam satu tenant, meliputi Dashboard Mitra, User Mitra, Warehouse, Project, dan Harga Jasa Mitra. Ini adalah cara mengelompokkan jalur kerja, bukan entitas baru, kepemilikan baru, atau izin gabungan; setiap halaman tetap tunduk pada Izin Aksi dan isolasi mitra.
- **Dashboard Mitra** — ringkasan baca-saja atas Project, Warehouse, Material, Request Material, Transit, dan aktivitas dalam cakupan Mitra User yang sedang masuk. Dashboard Mitra tidak menjadi jalur mutasi dan bukan pengganti Portfolio Cockpit.
- **Onboarding Mitra** — satu form THC yang membuat entitas Mitra + user admin-mitra pertamanya sekaligus. Password awal (dan password hasil reset) dikirim **otomatis** lewat WA gateway sebagai teks polos ke nomor WA terdaftar milik mitra — bukan link token. Setelah onboarding, Admin Mitra dapat mengelola User Mitra dalam tenantnya sendiri.
- **Reset password** — mitra bisa self-service (sistem generate password baru, kirim otomatis lewat WA seperti onboarding); THC juga bisa memicu reset paksa dari panel.
- **Nonaktifkan user mitra** — manual oleh THC atau Admin Mitra dalam tenantnya sendiri, tidak otomatis dari tanggal berakhir PKS. User nonaktif tidak bisa login; data historis (project, foto, linimasa) tetap ada dan tetap terlihat THC, sesuai pola "nonaktif bukan hapus". Admin Mitra tidak boleh menonaktifkan dirinya sendiri atau Admin Mitra terakhir. Project aktif milik mitra yang dinonaktifkan **tidak dicegah** — tetap bisa ditutup administratif oleh THC tanpa akses mitra.
- **Penugasan Gudang** — hubungan operasional antara User dan satu atau lebih Warehouse melalui tabel pivot. Penugasan bukan kepemilikan Warehouse; Admin Mitra hanya dapat mengatur penugasan User aktif ke Warehouse aktif milik Mitranya sendiri.
## Master Data

- **Kode Master dan Warehouse** — pengenal manusia untuk Material, Unit, PoP, Pekerjaan Jasa, dan Warehouse. Kode otomatis memakai `MAT-YYMM-NNNN`, `UNT-YYMM-NNNN`, `POP-YYMM-NNNN`, `JAS-YYMM-NNNN`, atau `WH-YYMM-NNNN`; sequence berjalan terpisah per entitas dan per bulan.
- **Kode Otomatis** — kode yang diterbitkan sistem ketika field kode dikosongkan saat membuat entitas. Kode ini dinormalisasi uppercase, immutable setelah terbit, dan tidak pernah digunakan kembali.
- **Kode Manual** — kode lama atau kode yang diberikan THC sendiri. Setelah `trim` dan uppercase, formatnya bebas selama unik dan tidak menyamai pola Kode Otomatis yang belum diterbitkan.
- **Ledger Kode Terbit** — catatan permanen bahwa suatu Kode Otomatis pernah diterbitkan, termasuk bila record pemiliknya kemudian tidak aktif atau dihapus melalui prosedur administratif.

## Proyek

- **Project** — satu pekerjaan instalasi yang dimonitor. Dimiliki tepat satu Mitra.
- **Workspace Perencanaan Project** — ruang kerja untuk menyusun RAB Jasa, mengajukan Usulan Baseline, dan mengajukan Variation Order pada satu Project. Bukan Project baru dan bukan kewenangan untuk melewati approval THC.
- **ID Project** — pengenal yang dibaca manusia, format `PRJ-YYMM-NNNN`. Diisi manual atau dibentuk otomatis saat dikosongkan; tidak pernah berubah setelah terbit.
- **Status Project** — `aktif` / `selesai`, terpisah dari Step. Bukan penanda progres fase (itu tugas Step); ini penanda penutupan administratif, ditentukan dari Rekon Material. Lihat `docs/adr/0013-alur-pemakaian-material-dan-rekon.md`.
- **PoP** — Point of Presence, lokasi simpul jaringan yang jadi acuan project.
- **Step** — penanda fase project baku (11 step). Fleksibel (bisa dilompati/dimundurkan) dan hanya mencatat tanggal aktual selesai. Terpisah dari kurva S.
- **Pekerjaan Jasa** — jenis pekerjaan yang ditagihkan mitra (mis. penarikan kabel per meter). Katalognya bersama; **harganya per mitra**.
- **Harga Jasa Mitra** — harga satu Pekerjaan Jasa untuk satu Mitra sesuai PKS (foreign key wajib ke satu PKS), berlaku sejak tanggal tertentu. Siklus: `diajukan` (Mitra) → `disetujui`/`ditolak` (THC); hanya baris `disetujui` yang boleh dipakai membuat RAB Jasa. Mitra memilih dari katalog Pekerjaan Jasa yang sudah ada (tabel bersama, tidak bisa mengusulkan jenis baru — itu di luar sistem, ke THC). Revisi harga = baris **baru** (`diajukan`, merujuk baris lama), bukan edit di tempat; baris lama tetap `disetujui` dan tetap dipakai RAB yang sudah beku sampai baris baru disetujui dan tanggal berlakunya tiba.
- **PKS** — Perjanjian Kerja Sama antara THC dan Mitra; entitas sendiri (nomor, tanggal mulai/berakhir, file lampiran opsional). Sumber harga jasa. Tepat **satu PKS aktif** per mitra pada satu waktu; PKS baru mengisi tanggal mulai setelah PKS lama berakhir. Lihat `docs/adr/0015-onboarding-mitra-dan-harga-jasa.md`.
- **RAB Jasa** — daftar Pekerjaan Jasa + qty pada satu Project, dengan **harga yang dibekukan** saat baris dibuat. Jadi bobot kurva S.
- **Kurva S** — kurva progres berbobot rupiah jasa (bukan material).
- **SPI** — Schedule Performance Index, rasio progres aktual terhadap baseline. (Ditampilkan `N/A` jika kumulatif baseline 0%).
- **TOC** — Target Operation Complete, tanggal target selesai project.
- **Usulan Baseline** — TOC dan rencana persentase yang diajukan Admin Mitra untuk satu Project. Usulan belum menjadi sumber pengukuran sampai disahkan THC.
- **Original & Revised Baseline** — baseline yang telah disahkan THC. Baseline pertama menjadi Original dan tidak ditimpa; jika TOC diundur, kurva baru menjadi Revised. Kinerja diukur terhadap Revised Baseline.
- **Variation Order** — usulan perubahan (tambah/kurang) RAB Jasa di tengah jalan. Perubahan belum mengubah RAB efektif sampai disetujui THC; setelah disetujui, bobot 100% dihitung ulang (*recalculated*) berdasarkan *grand total* baru. Harga PKS baru hanya berlaku pada *item* tambahan dan snapshot historis tidak berubah.
- **Linimasa Gabungan** — Satu riwayat aktivitas project yang mencampur log sistem otomatis (surat jalan, pindah step) dan komentar diskusi antar user.
- **Komentar Project** — komentar reguler pada Linimasa Gabungan yang dapat dibaca dan dibuat oleh User yang memiliki akses ke Project. Tidak boleh dihapus; hanya pembuatnya yang boleh mengedit, dan hasil edit ditandai.
- **Komentar Internal** — Tipe komentar di linimasa yang hanya bisa dibaca oleh user THC, tersembunyi dari Mitra. Tidak boleh dihapus, hanya boleh diedit.
- **Portfolio Cockpit** — halaman baca lintas Project yang menjawab "apa yang perlu dibaca atau diputuskan sekarang?" dari tingkat portofolio. Terbuka untuk User THC maupun User Mitra yang punya izin `read_dashboard`, dengan cakupan data mengikuti isolasi mitra. Read-only: menautkan ke modul pemilik data dan tidak pernah jadi jalur mutasi. Bukan pengganti Project Control Room (halaman detail satu Project).
- **Decision Queue** — bagian baca dari Portfolio Cockpit yang mengelompokkan pengecualian lintas Project menurut kategori dan status risiko, menampilkan konteks, waktu pembaruan, alasan, serta tautan authorized ke modul pemilik data. Tidak memiliki jalur mutasi.
- **Status risiko** — penanda kesehatan satu Project di Portfolio Cockpit, memakai warna SPI ADR-0010 (`hijau`/`kuning`/`merah`, atau `N/A` saat kumulatif baseline masih 0%). Project berstatus kuning atau merah dihitung sebagai **Project perlu perhatian**.

## Gudang & material

- **Material** — barang milik **THC**, walau dititipkan di gudang mitra.
- **Unit** — satuan material (meter, batang, pcs).
- **Warehouse** — gudang. Bisa milik THC (`mitra_id` NULL) atau titipan di mitra. Tabel hibrida: User Mitra dapat membaca gudangnya sendiri dan gudang THC — supaya asal Surat Jalan terbaca — tetapi tidak pernah gudang Mitra lain, dan hanya dapat menulis gudang Mitranya sendiri.
- **Petugas Gudang** — user yang mencatat barang masuk/keluar di satu atau lebih Warehouse.
- **SN** — Serial Number material tertentu; dilacak sampai keluar gudang dan untuk project mana.
- **Request Material** — permintaan material oleh Mitra ke THC. Satu request bisa dipenuhi beberapa Surat Jalan (kirim bertahap); status `terpenuhi_sebagian` dan `selesai` **dihitung** dari qty yang diterima, tidak diketik, sedangkan `ditutup` adalah **keputusan** THC bahwa sisanya tidak jadi dikirim — wajib beralasan, hanya dari `disetujui`/`terpenuhi_sebagian`, dan tidak bisa dibuka kembali. Request tidak punya gudang tujuan sendiri: "request yang ditujukan ke suatu gudang" berarti request milik Mitra pemilik gudang itu. Ini bersandar pada asumsi **satu gudang penerima per Mitra**, yang tidak ditegakkan database; kalau satu Mitra punya lebih dari satu gudang, pencocokannya berhenti membedakan gudang dan cabang `warehouse_tujuan_id` harus dibuka kembali. Lihat `docs/adr/0005-alur-perpindahan-material-transit.md`.
- **Surat Jalan** — dokumen perpindahan material antar gudang, nomor `SJ-YYMM-NNNN`, wajib bisa dicetak. Dipakai untuk semua arah (THC↔mitra, THC↔THC); berawal dari Request Material atau dari input langsung petugas THC. Siklusnya `terbit` → `diterima`, atau `terbit` → `dibatalkan`.
- **Baris Menyimpang** — baris Surat Jalan yang membawa material di luar daftar Request Material yang dirujuk, atau qty melebihi **sisa** permintaan (`diminta − sudah terkirim`). Daftar request adalah *prefill*, bukan plafon: penyimpangan diperbolehkan, tetapi wajib beralasan per baris dan tercatat di Linimasa Gabungan. Mengirim kurang dari sisa bukan penyimpangan — itu kirim bertahap. Lihat `docs/adr/0024-daftar-request-material-prefill-bukan-plafon.md`.
- **Transit** — lokasi material yang sudah keluar gudang asal tapi belum tercatat masuk di gudang tujuan. Melekat pada satu Surat Jalan. **Bukan** stok gudang manapun; selisih pengiriman tertinggal di sini sampai THC menyelesaikannya sebagai hilang atau kembali ke asal.
- **Retur** — pengembalian barang yang sudah diterima. Surat Jalan **baru arah sebaliknya** yang menunjuk Surat Jalan asal — bukan pembatalan, karena barangnya memang bergerak lagi.
- **Jenis material** — `biasa` (hanya jumlah), `ber_sn` (identitas per butir), `drum_kabel` (identitas + isi yang bisa berkurang). Menentukan tabel identitas mana yang dipakai. Lihat `docs/adr/0004-tiga-jenis-material-satu-buku.md`.
- **Drum** — satu roll kabel fisik dengan `panjang_awal` dan `sisa`. Potongan yang keluar jadi **drum turunan** ber-ID `DRM-00042-1`, punya baris sendiri, tidak pernah digabung balik ke induk.
- **Buku transaksi** — `material_transaksis`, append-only. Satu-satunya kebenaran stok; `UPDATE`/`DELETE` dicabut dari role aplikasi. Koreksi = baris pembatalan, bukan hapus. Lihat `docs/adr/0003-stok-material-buku-transaksi.md`.
- **Saldo stok** — `material_stoks`, cache per (lokasi, material) yang ditulis **trigger**, bukan kode aplikasi. Boleh dibangun ulang dari buku kapan saja.
- **Lokasi material** — empat kemungkinan: `warehouse` (di gudang), `transit` (dalam perjalanan antar gudang), `project` (sudah keluar, di lapangan, **belum terpasang**), `terpasang` (keluar dari stok, masuk realisasi project).
- **Pemakaian Material** — pengeluaran material oleh Mitra dari gudangnya sendiri (titipan THC) untuk dipakai di satu Project. Terpisah dari Request Material (yang arahnya THC → mitra); di sini mitra mengeluarkan dari stok yang sudah dititip padanya. Siklus: `diajukan` (mitra) → `disetujui`/`ditolak` (THC). Stok baru berkurang dari gudang saat `disetujui` — pengajuan pending tidak menyentuh buku transaksi. Mitra boleh membatalkan selama masih `diajukan`. Lihat `docs/adr/0013-alur-pemakaian-material-dan-rekon.md`.
- **Rekon material** — pencocokan THC ↔ mitra atas material yang keluar gudang untuk satu Project, dicatat di `project_rekons` bernomor `REK-YYMM-NNNN`. Satu project bisa punya beberapa rekon: rekon susulan **mengoreksi** rekon sebelumnya (`koreksi_dari_id`, pola sama dengan koreksi buku transaksi), bukan menggantikannya. Isi per baris material: keluar gudang, terpasang, sisa di project, dikembalikan, hilang/rusak. THC yang approve. Sisa dikembalikan ke **gudang mitra asal** (bukan ditarik ke gudang THC). Lihat `docs/adr/0013-alur-pemakaian-material-dan-rekon.md`.
- **Waste/Loss** — Selisih material (seperti potongan kabel) yang tidak dapat diretur, dicatat sebagai baris Rekon Material dengan kategori tetap (`hilang`/`rusak`/`waste_wajar`), penanggung jawab default Mitra (bisa diubah THC per baris).

## Integrasi baca

- **API Key** — kredensial baca opaque untuk konsumen internal THC (bukan mitra), di-hash saat disimpan, dibuat/dicabut lewat panel THC. Punya `mitra_id` nullable: diisi → tunduk isolasi mitra seperti user mitra; `null` → setara user THC. Lihat `docs/adr/0016-rest-api-baca-dan-user-postgresql-read-only.md`.
- **View read-only** — view SQL kurasi per domain (mis. `v_projects`, `v_stok`) yang jadi satu-satunya permukaan ter-`GRANT` ke role Postgres read-only untuk BI internal. Tabel mentah, terutama Buku transaksi dan Komentar Internal, tidak pernah ter-grant langsung.

## Konvensi lintas domain

- **Nomor WhatsApp** — disimpan dalam format E.164 tanpa `+` (`628123456789`), ditampilkan sebagai tautan `wa.me` yang bisa diklik.
- **Bulan Kode Otomatis** — `YYMM` mengikuti timezone bisnis `Asia/Jakarta`, bukan pergantian bulan UTC.
- **Master data** — tabel nyata per entitas, di-CRUD dari UI. **Bukan** tabel serba-guna / EAV. Lihat `docs/adr/0002-master-data-tabel-nyata.md`.
- **Nonaktif, bukan hapus** — baris master yang sudah dipakai transaksi tidak dihapus; ditandai `aktif = false`.
- **Cloudflare Tunnel** — Pendekatan infrastruktur untuk mendapatkan domain dan HTTPS tanpa perlu *port-forward* di MikroTik.
- **Build Artifact Git** — Aset statis (*frontend build*) dihasilkan di mesin lokal *developer* lalu di-*commit* ke repositori agar instalasi *server* tetap ringkas tanpa dependensi Node.js.
- **Foto pekerjaan** — dokumentasi lapangan (hanya JPEG, maks 10/unggahan, maks 5 MB mentah). Dikompres client-side ke 1920×1080 sebelum upload. Disimpan di disk server, lalu disalin ke Google Drive via `rclone` tiap jam. Retensi server 90 hari; setelahnya Google Drive = sumber kebenaran. Lihat `docs/adr/0012-alur-foto-pekerjaan-dan-sinkronisasi-google-drive.md`.
- **Folder Master** — satu folder Google Drive publik View-Only yang berisi semua foto project dalam struktur `ProjectID / Step / Tanggal`. Mitra mengaksesnya lewat tombol di aplikasi web.

## Review kualitas

- **Review QC Produksi** — pemeriksaan terarah terhadap aplikasi yang sedang berjalan di lingkungan produksi untuk menemukan perilaku yang perlu direvisi atau diputuskan.
- **Temuan QC** — satu observasi Review QC Produksi yang dapat ditindaklanjuti, baik berupa bug, masalah UX/teks, maupun saran penyempurnaan.
- **ID Temuan QC** — pengenal global dan tidak digunakan ulang untuk satu Temuan QC; formatnya `QC-NNNN` dan menjadi identitas yang sama pada judul laporan serta folder buktinya.
- **Bukti QC** — screenshot atau artefak pendukung yang menunjukkan konteks dan kondisi Temuan QC. Bukti yang mengandung data sensitif harus disensor sebelum dibagikan.
- **Status QC** — keadaan tindak lanjut Temuan QC: `open`, `in_progress`, `fixed`, `verified`, atau `wont_fix`.
- **Severity QC** — tingkat dampak Temuan QC: `blocker`, `major`, `minor`, atau `suggestion`.
