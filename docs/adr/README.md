# Index ADR

Satu baris per ADR: judul dan **kapan ADR itu relevan dibuka**. Pakai kolom "buka saat" untuk memilih — jangan buka berkas hanya untuk tahu isinya.

ADR di sini adalah keputusan yang sudah beku. Kalau pekerjaanmu bertentangan dengan salah satunya, angkat konfliknya secara eksplisit (lihat `docs/agents/domain.md`), jangan diam-diam menimpanya.

| ADR | Judul | Buka saat |
| --- | --- | --- |
| [0001](0001-isolasi-mitra-row-level-security.md) | Isolasi mitra ditegakkan lewat PostgreSQL Row-Level Security | Menyentuh tabel bertenant, menulis query/migrasi, menambah tabel baru, atau menjalankan job/command yang membaca data mitra. |
| [0002](0002-master-data-tabel-nyata.md) | Master data pakai tabel nyata + CRUD generik | Menambah atau mengubah entitas master (Material, Unit, PoP, Pekerjaan Jasa, Mitra, Warehouse) atau tergoda membuat tabel serba-guna. |
| [0003](0003-stok-material-buku-transaksi.md) | Stok material dihitung dari buku transaksi, saldo sebagai cache | Menghitung atau menampilkan angka stok, atau menulis apa pun yang mengubah posisi material. |
| [0004](0004-tiga-jenis-material-satu-buku.md) | Tiga jenis material dalam satu buku, identitas terpisah per jenis | Bekerja dengan material ber-SN, kabel/drum, atau material biasa — termasuk pemecahan drum turunan. |
| [0005](0005-alur-perpindahan-material-transit.md) | Perpindahan lewat surat jalan, transit sebagai lokasi nyata | Menyentuh Request Material, Surat Jalan, penerimaan barang, atau status transit/kurang terima. |
| [0006](0006-model-hak-akses-matriks-aksi.md) | Model hak akses: matriks aksi di atas isolasi mitra | Menambah menu/aksi baru, memasang authorization, atau memutuskan apa yang disembunyikan vs. 403. |
| [0007](0007-retensi-dan-pembersihan-foto.md) | Retensi dan pembersihan foto project — **superseded oleh ADR-0012** | Hanya untuk konteks historis retensi berbasis status project; kebijakan yang berlaku ada di ADR-0012. |
| [0008](0008-laporan-gudang-mvp.md) | Ruang lingkup laporan gudang (MVP) | Membangun atau memperluas laporan gudang, dan perlu tahu tiga laporan mana yang masuk lingkup awal. |
| [0009](0009-penyelesaian-spesifikasi-sisa-peta.md) | Penyelesaian spesifikasi sisa peta (SPI, batas transit, susut kabel, QR, deployment) | Butuh angka atau aturan sisa yang tidak punya ADR sendiri — mis. batas 3 hari transit, toleransi susut kabel. Poin #6 (HTTPS) sudah digantikan ADR-0017 (QR/HTTPS). |
| [0010](0010-kurva-s-dan-spi.md) | Rumus kurva S, SPI, dan indikator kesiapan material | Menghitung persen rencana/realisasi, SPI, atau menangani progres melewati TOC dan volume melebihi RAB. |
| [0011](0011-step-project-linimasa-komentar.md) | Step project, linimasa gabungan, dan komentar internal | Menyentuh step project, komentar, mention, notifikasi, atau audit aktivitas — terutama batas Komentar Internal THC. |
| [0012](0012-alur-foto-pekerjaan-dan-sinkronisasi-google-drive.md) | Alur foto pekerjaan dan sinkronisasi Google Drive | Menyentuh upload foto, kompresi klien, sinkronisasi Drive, retry, atau retensi foto (kebijakan 90 hari yang berlaku). |
| [0013](0013-alur-pemakaian-material-dan-rekon.md) | Pemakaian material harian dan rekon akhir project | Menyentuh Pemakaian Material, rekon, `projects.status_project`, atau penutupan project. |
| [0015](0015-onboarding-mitra-dan-harga-jasa.md) | Onboarding mitra, kredensial, dan approval harga jasa PKS | Membuat Mitra/user pertamanya, atau menyentuh PKS, Harga Jasa Mitra, revisi harga, dan akhir kerja sama. |
| [0016](0016-rest-api-baca-dan-user-postgresql-read-only.md) | REST API baca dan user PostgreSQL read-only untuk integrasi | Membangun endpoint API baca, API key, atau akses BI read-only ke database. |
| [0017-qr](0017-cara-scan-qr-dan-https-domain.md) | Cara pindai QR dan HTTPS via domain (menggantikan ADR-0009 #6) | Menyentuh scan QR, isi payload QR, Secure Context/`getUserMedia`, atau HTTPS dan domain. |
| [0017-gate](0017-kontrak-bukti-quality-gate-deployment.md) | Kontrak bukti quality gate sebelum deployment | Menutup tiket implementasi atau melakukan deploy — daftar bukti minimum yang wajib dilampirkan. |
| [0019](0019-kode-mitra-otomatis-dan-penghapusan-aman.md) | Kode Mitra otomatis dan penghapusan aman | Menerbitkan Kode Mitra (`MTR-YYMM-NNNN`), atau menghapus/menonaktifkan User dan Mitra. |
| [0020](0020-kontrak-kode-master-dan-warehouse.md) | Kontrak Kode Master dan Warehouse | Menerbitkan atau memvalidasi kode `MAT`/`UNT`/`POP`/`JAS`/`WH`, termasuk penerimaan kode legacy. |
| [0021](0021-capability-admin-mitra.md) | Capability Admin Mitra sebagai super user tenant | Memutuskan apa yang boleh dilakukan Admin Mitra, atau membuka write access baru di sisi mitra. |
| [0022](0022-batas-workspace-admin-mitra-per-domain.md) | Workspace Admin Mitra dibatasi per domain | Menyusun seam/alur lintas domain di workspace Admin Mitra (User, Penugasan Gudang, Harga Jasa, Perencanaan Project). |
| [0023](0023-warehouse-tabel-hibrida.md) | Warehouse adalah tabel hibrida: dibaca lintas tenant, ditulis per tenant | Menyentuh tabel `warehouses`, policy RLS-nya, atau `mitra_id` NULL sebagai penanda kepemilikan THC. |
| [0024](0024-daftar-request-material-prefill-bukan-plafon.md) | Daftar Request Material adalah prefill, bukan plafon Surat Jalan | Menyentuh form Terbitkan Surat Jalan, prefill dari request, atau penandaan baris menyimpang. |
| [0025](0025-qty-material-bilangan-bulat.md) | Qty material adalah bilangan bulat, ditegakkan di aplikasi | Memvalidasi, membulatkan, atau memecah qty material di mana pun (server maupun klien). |
| [0026](0026-klasifikasi-penyimpangan-kembar-di-klien.md) | Klasifikasi penyimpangan boleh kembar di klien, dijaga test kontrak | Mengubah `markDeviations()` di klien atau `classifyRequestDeviations()` di server — keduanya wajib bergerak bersama. |
| [0027](0027-glosarium-mengikat-nama-yang-menunjuk-konsepnya.md) | Glosarium mengikat nama yang menunjuk konsepnya, di lapisan mana pun | Menamai apa pun yang menunjuk konsep glosarium — kolom, kelas, relasi, kunci payload, `data-*` — atau tergoda memakai padanan Inggrisnya. |

## Catatan penomoran

- Tidak ada ADR-0014 dan ADR-0018 — nomor itu tidak pernah terpakai.
- Nomor 0017 dipakai dua berkas berbeda (QR/HTTPS dan quality gate). Rujuk berkasnya, bukan nomornya saja.
- ADR baru: ambil nomor berikutnya yang belum terpakai, lalu **tambahkan satu baris di tabel ini** pada langkah yang sama.
