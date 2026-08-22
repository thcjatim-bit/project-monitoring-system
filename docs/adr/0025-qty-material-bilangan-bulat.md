# ADR-0025 — Qty material adalah bilangan bulat, ditegakkan di aplikasi

Status: diterima · Tanggal: 2026-08-22 · Tiket: [#126](https://github.com/thcjatim-bit/project-monitoring-system/issues/126)

## Konteks

Prefill form Terbitkan Surat Jalan memecah baris ber-SN dengan `Math.floor(item.sisa)`. Sisa 0,5 pcs menghasilkan **nol** baris; sisa 2,5 menghasilkan dua baris dan 0,5 sisanya lenyap tanpa sinyal apa pun ke operator — sementara label dropdown tetap menghitungnya sebagai "1 belum lengkap". Layar menyangkal dirinya sendiri.

Pertanyaan sebenarnya bukan "bagaimana membulatkan", melainkan **apakah qty pecahan itu punya arti**. Penelusuran menunjukkan pecahan hanya bisa lahir dari satu tempat:

- `SuratJalanService` menolak baris ber-SN kecuali `qty === 1.0` dengan satu Serial Number, jadi `terkirim` selalu bulat.
- `sisa = max(diminta − terkirim, 0)`, sehingga `sisa` pecahan **hanya** bisa berasal dari `diminta` pecahan.
- `MaterialRequestController::store` memvalidasi `['required', 'numeric', 'gt:0']` tanpa cek integer, dan form-nya memakai `step="0.001"` untuk **ketiga** jenis material. Kolomnya `decimal(18,3)` dengan `CHECK (qty > 0)`.

ADR-0004 sudah menyatakan ber-SN "qty selalu 1", tetapi kalimat itu tidak pernah menjadi validasi di jalur Request Material.

Produksi saat keputusan diambil: nol baris qty pecahan di `material_request_items` (12 baris), `surat_jalan_items` (3), dan `material_transaksis` (18). `drums` masih kosong. Lubangnya laten, belum termakan — dan menutupnya sekarang tidak memaksa migrasi data apa pun.

## Keputusan

**Qty material selalu bilangan bulat, untuk ketiga jenis material dan semua satuan.** Satu SN adalah satu pcs; material biasa bertransaksi per unit utuh; kabel bertransaksi per meter utuh dengan minimum 1 meter. Tidak ada setengah unit di domain ini.

Aturan ini **ditegakkan di lapisan aplikasi**, bukan trigger database.

Penegakannya bertahap:

- **#126** — validasi integer pada `MaterialRequestController::store` untuk material `ber_sn`, plus `step` input mengikuti jenis material.
- **Tiket lanjutan** — meluaskan penegakan yang sama ke jenis `biasa` dan `drum_kabel` serta jalur qty lain (Surat Jalan, Pemakaian Material, Rekon).

**Yang dilihat operator saat menemui sisa pecahan warisan:** `Math.floor` dipertahankan — dua baris untuk sisa 2,5 — ditambah **pemberitahuan tingkat-form** yang menyebut angkanya, sebabnya, dan siapa yang harus membetulkan. Pemberitahuan ini memakai salurannya sendiri, **bukan** `markRow`/`ui-list__item--deviating`.

## Alternatif yang ditolak

- **Prefill satu baris 0,5.** Jebakan: `SuratJalanService` menolak baris ber-SN yang qty-nya bukan tepat 1,0, sehingga baris itu menggagalkan **seluruh** Surat Jalan dengan pesan yang menyalahkan operator atas kesalahan mitra. Prefill tidak boleh melahirkan baris yang mustahil dikirim.
- **Membuang 0,5 diam-diam (perilaku lama).** Data rusak menjadi tak terlihat, ditutupi kode yang tampak benar.
- **Mengubah `Math.floor` menjadi `Math.ceil`.** Mengarang satu pcs yang tidak ada dan menaikkan total qty diam-diam (lihat b47b260).
- **Memakai ulang penandaan Menyimpang.** Klasifikasi penyimpangan di layar sengaja meniru `SuratJalanService::classifyRequestDeviations()` persis. Sisa pecahan bukan penyimpangan — mengirim 2 dari sisa 2,5 adalah kirim bertahap, dan server tidak menandainya apa pun. Memakai ulang saluran itu merusak invarian "layar dan server tak pernah beda kesimpulan".
- **Atribut ketakterbagian pada `Unit`.** Sempat dipertimbangkan agar `Pcs`/`Btg` tak terbagi sementara `meter` bebas pecahan. Ditolak karena keputusan akhirnya menjadikan **semua** satuan bulat, sehingga atribut per-Unit tidak lagi membedakan apa pun.
- **Trigger database.** Aturannya menyeberang tabel (`material_request_items.qty` bergantung `materials.jenis`), jadi tidak bisa `CHECK` biasa. Ditolak: proyek ini menaruh invarian keras di database untuk hal yang kalau bocor merusak uang atau isolasi tenant (RLS, buku append-only, `CHECK (sisa >= 0)`). Qty pecahan sudah punya jaring pengaman mutlak — barangnya tidak akan pernah bisa berangkat lewat `SuratJalanService`. Yang rusak hanya angka di daftar permintaan.

## Konsekuensi

- **`drums.sisa` ikut terikat aturan ini.** Ini bagian yang paling perlu diingat pembaca berikutnya. `drums.sisa` bukan angka yang diketik siapa pun; ia hasil pengurangan yang bergerak setiap kali drum dipotong, diretur dari lapangan, atau disusutkan lewat Rekon. Kalau kelak sebuah drum benar-benar tersisa 1.250,5 m, sistem menolak angka yang benar dan jalan keluar operator adalah membulatkannya — meter bocor diam-diam, persis penyakit yang ADR ini obati, pindah tabel. Risiko ini **diangkat dan diterima secara sadar**: penilaian operasional THC adalah kabel selalu ditransaksikan per meter utuh, dan `drums` belum punya satu baris pun di produksi sehingga tidak ada bukti tandingan. Bila sisa pecahan ternyata muncul di lapangan, ADR ini yang harus ditinjau ulang — bukan angkanya yang dibulatkan.
- `decimal(18,3)` di seluruh skema tetap ada dan menjadi ketelitian yang tidak terpakai. Tidak ada migrasi tipe kolom; presisi cadangan itu justru yang memungkinkan aturan dicabut kelak tanpa kehilangan data.
- Penegakan di aplikasi bisa dilewati seeder, `artisan`, dan SQL langsung. Diterima: jalur itu dipakai developer, bukan mitra.
- Label dropdown request tetap menghitung `sisa > 0` apa adanya. Untuk sisa 0,5 ia berbunyi "1 belum lengkap" — jujur, dan kini dijelaskan oleh pemberitahuan di form alih-alih dibiarkan menggantung.
