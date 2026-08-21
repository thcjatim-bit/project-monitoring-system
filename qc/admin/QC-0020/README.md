# QC-0020 — Konsistensi Design dan Harga Jasa per Mitra

| Field                     | Nilai                                                |
| ------------------------- | ---------------------------------------------------- |
| ID                        | `QC-0020`                                            |
| Prefix                    | `mitra-pekerjaan-jasa`                               |
| Status                    | `open`                                               |
| Severity                  | `major`                                              |
| Tanggal/waktu pengujian   | `2026-08-20 16:51 WIB`                               |
| Reviewer                  | Fatoni                                               |
| Persona/role              | Admin Mitra                                          |
| Halaman atau URL produksi | https://deploythc.web.id/admin/master/pekerjaan-jasa |
| Browser/device            | Chrome / laptop Windows                              |
| GitHub Issue              | [Implement workspace Admin Mitra setelah capability disetujui](https://github.com/thcjatim-bit/project-monitoring-system/issues/95) |

## Ringkasan

> Saat login sebagai **Admin Mitra**, halaman **Pekerjaan Jasa** saat ini hanya menampilkan master jenis pekerjaan dalam mode read-only. Admin Mitra seharusnya dapat mengisi dan mengelola **harga/nilai jasa milik Mitranya sendiri** untuk setiap master Pekerjaan Jasa, sehingga nilai tersebut dapat digunakan ketika membuat **RAB Jasa**, kemudian dibekukan pada baseline sesuai prinsip Project Planning yang sudah ada.

## Langkah reproduksi

1. Login sebagai **Admin Mitra**.
2. Buka `https://deploythc.web.id/admin/master/pekerjaan-jasa`.
3. Perhatikan daftar master Pekerjaan Jasa.
4. Perhatikan bahwa data hanya menampilkan kode dan nama pekerjaan.
5. Cari field/action untuk mengisi harga jasa Mitra.
6. Perhatikan bahwa tidak tersedia field harga/nilai jasa.
7. Buka Project milik Mitra dan masuk ke bagian **RAB Jasa / Workspace Perencanaan**.
8. Perhatikan bahwa belum tersedia sumber harga jasa Mitra yang dapat digunakan untuk membentuk RAB.

## Hasil aktual

> Halaman Pekerjaan Jasa saat ini hanya menampilkan master seperti:
>
> ```text
> dadwdada — Jasa sambung
> dadwad — Jasa Pemasangan ODP
> dwadwad — Jasa Penarikan Kabel
> dwadwadaw — Jasa Penanaman Tiang 9
> dadwa — Jasa Penanaman Tiang 7
> fff — jasa urut
> ```
>
> Admin Mitra tidak dapat menentukan nilai/harga jasanya sendiri.
>
> Akibatnya belum tersedia hubungan yang jelas:
>
> ```text
> Master Pekerjaan Jasa
>        ↓
> Harga Jasa Mitra
>        ↓
> RAB Jasa Project
>        ↓
> Baseline
> ```
>
> Jika master Pekerjaan Jasa bersifat global/shared, Admin Mitra juga tidak seharusnya mengubah nama atau kode master global tersebut hanya untuk menetapkan harga.

## Hasil yang diharapkan

> Master **Pekerjaan Jasa** tetap dapat menjadi shared/global master, tetapi setiap Mitra mempunyai **harga jasa sendiri** untuk masing-masing pekerjaan.
>
> Admin Mitra dapat mengelola harga jasa hanya untuk Mitranya sendiri.

### 1. Satu bahasa design

> Halaman menggunakan design system yang sama dengan modul sebelumnya:
>
> * PageHeader;
> * Card;
> * DataTable;
> * FormControl;
> * Currency/Number input;
> * StatusBadge;
> * Button variants;
> * Alert/Toast;
> * Search/Filter;
> * EmptyState.
>
> Jangan membuat design system khusus Admin Mitra.

### 2. Master pekerjaan tetap shared

> Informasi seperti:
>
> ```text
> Kode
> Nama Pekerjaan Jasa
> ```
>
> tetap berasal dari master Pekerjaan Jasa.
>
> Contoh:
>
> ```text
> Kode       Pekerjaan Jasa
> dadwad     Jasa Pemasangan ODP
> ```
>
> Admin Mitra tidak otomatis boleh:
>
> * mengganti kode;
> * mengganti nama master;
> * menonaktifkan master global;
> * menghapus master global.
>
> Hak tersebut tetap mengikuti permission master-data existing.

### 3. Tambahkan Harga Jasa Mitra

> Untuk setiap Pekerjaan Jasa, Admin Mitra dapat mengisi:
>
> ```text
> Harga Jasa Mitra
> ```
>
> Contoh:
>
> ```text
> Pekerjaan Jasa                 Harga Mitra
> ───────────────────────────────────────────
> Jasa Pemasangan ODP            Rp 150.000
> Jasa Penarikan Kabel           Rp 7.500
> Jasa Penanaman Tiang 7         Rp 250.000
> ```
>
> Harga tersebut hanya berlaku untuk Mitra login.

### 4. Tenant-specific pricing

> Harga harus tersimpan sebagai data **per Mitra + Pekerjaan Jasa**.
>
> Secara konseptual:
>
> ```text
> Mitra A + Jasa Pemasangan ODP → Rp 150.000
> Mitra B + Jasa Pemasangan ODP → Rp 175.000
> ```
>
> Perubahan harga Mitra A tidak boleh mengubah harga Mitra B.

### 5. Tenant isolation

> Admin Mitra hanya boleh membaca dan mengubah harga milik Mitranya sendiri.
>
> Backend wajib mencegah:
>
> ```text
> Admin Mitra A
>     ↓
> update harga Mitra B
> ```
>
> termasuk melalui:
>
> * manipulasi ID;
> * direct API request;
> * perubahan payload;
> * IDOR;
> * URL manual.

### 6. Tampilan halaman

> Contoh layout:
>
> ```text
> MASTER DATA
>
> Pekerjaan Jasa
> Kelola harga jasa Mitra yang digunakan pada penyusunan RAB Project.
>
> ┌─────────────────────────────────────────────────────────────┐
> │ Cari Pekerjaan Jasa                                        │
> │ [ kode atau nama pekerjaan                              ]   │
> └─────────────────────────────────────────────────────────────┘
>
> Pekerjaan Jasa
>
> Kode       Nama                       Harga Jasa Mitra    Aksi
> ─────────────────────────────────────────────────────────────
> dadwad     Jasa Pemasangan ODP        Rp 150.000          Edit
> dwadwad    Jasa Penarikan Kabel       Rp 7.500            Edit
> dadwa      Jasa Penanaman Tiang 7     Belum diisi         Isi
> ```

### 7. Edit harga

> Action `Edit` dapat membuka inline form atau modal/shared editor.
>
> Contoh:
>
> ```text
> Jasa Pemasangan ODP
>
> Harga Jasa Mitra
> Rp [ 150.000 ]
>
>                       [Batal] [Simpan harga]
> ```
>
> Gunakan currency input yang konsisten.

### 8. Harga kosong

> Jika Mitra belum mengisi harga:
>
> ```text
> Belum diisi
> ```
>
> harus terlihat jelas.
>
> Jangan otomatis menganggap:
>
> ```text
> 0
> ```
>
> sebagai harga jika sebenarnya belum pernah dikonfigurasi.
>
> Bedakan:
>
> ```text
> null / belum diisi
> ```
>
> dengan:
>
> ```text
> Rp 0
> ```
>
> apabila `0` memang nilai valid secara business rule.

### 9. Format Rupiah

> Tampilan harga menggunakan format Rupiah yang konsisten:
>
> ```text
> 150000   → Rp 150.000
> 1250000  → Rp 1.250.000
> ```
>
> Jika sistem mendukung pecahan, precision harus mengikuti business rule existing.
>
> Jangan menyimpan formatted string `Rp 150.000` sebagai nilai database.

### 10. Validation harga

> Minimal:
>
> * harga tidak boleh negatif;
> * input non-numeric ditolak;
> * precision mengikuti database/business rule;
> * save harus idempotent/aman dari duplicate submit;
> * validation error tampil dekat field.

### 11. Harga digunakan pada RAB Jasa

> Ketika Admin Mitra membuat item **RAB Jasa** pada Project miliknya:
>
> ```text
> pilih Pekerjaan Jasa
>        ↓
> sistem mengambil Harga Jasa Mitra
>        ↓
> Qty × Harga Satuan
>        ↓
> Total RAB
> ```
>
> Contoh:
>
> ```text
> Jasa Pemasangan ODP
>
> Qty           10
> Harga Mitra   Rp 150.000
>
> Total         Rp 1.500.000
> ```

### 12. Jangan ambil harga Mitra lain

> Lookup harga RAB harus menggunakan:
>
> ```text
> Project.mitra
> +
> Pekerjaan Jasa
> ```
>
> bukan harga global atau harga Mitra lain.
>
> Jika Project milik `pt abc`, maka hanya harga `pt abc` yang digunakan.

### 13. Jika harga belum diisi

> Jika Admin Mitra mencoba memasukkan Pekerjaan Jasa ke RAB tetapi harga Mitra belum tersedia:
>
> tampilkan feedback:
>
> ```text
> Harga jasa untuk pekerjaan ini belum dikonfigurasi.
> Isi harga jasa terlebih dahulu.
> ```
>
> Jangan diam-diam menggunakan:
>
> * `0`;
> * harga Mitra lain;
> * harga master lama;
> * dummy/default yang tidak jelas.

### 14. Harga dibekukan saat baris RAB dibuat

> Pertahankan prinsip yang sudah ada:
>
> ```text
> Harga satuan dibekukan ketika baris RAB dibuat.
> ```
>
> Artinya:
>
> ```text
> 20 Aug
> Harga Mitra = Rp 150.000
>
> RAB dibuat
> Harga snapshot RAB = Rp 150.000
>
> 25 Aug
> Harga Mitra berubah = Rp 175.000
>
> RAB lama tetap Rp 150.000
> ```
>
> Jangan membuat perubahan harga master Mitra mengubah RAB historis secara otomatis.

### 15. Harga terbaru untuk item baru

> Setelah harga Mitra diperbarui, **item RAB baru** dapat menggunakan harga terbaru.
>
> Contoh:
>
> ```text
> RAB existing → Rp 150.000
>
> Harga Mitra sekarang → Rp 175.000
>
> Item RAB baru → Rp 175.000
> ```

### 16. Baseline membekukan RAB

> Saat RAB dimasukkan ke **Baseline / TOC**, nilai RAB harus tetap menggunakan snapshot yang berlaku pada saat RAB tersebut dibuat/disahkan sesuai business rule existing.
>
> Baseline tidak boleh mengambil ulang harga terkini dari tabel harga Mitra.

### 17. Original Baseline tidak berubah

> Pertahankan prinsip:
>
> * baseline pertama → Original Baseline;
> * perubahan berikutnya → Revised Baseline;
> * Original Baseline tidak ditimpa.
>
> Harga historis juga harus ikut terlindungi dari perubahan master harga.

### 18. Variation Order

> Jika terdapat Variation Order yang menambah pekerjaan baru:
>
> harga yang digunakan mengikuti rule VO existing.
>
> Jika business rule menyatakan:
>
> ```text
> qty positif + Harga Jasa Mitra baru
> ```
>
> maka sistem dapat mengambil harga Mitra saat VO dibuat lalu membekukannya pada VO.
>
> Jangan mengubah VO lama jika harga jasa berubah kemudian.

### 19. RAB menampilkan sumber harga

> Pada detail RAB, jika berguna untuk audit, tampilkan:
>
> ```text
> Harga Satuan
> Rp 150.000
>
> Sumber
> Harga Mitra saat RAB dibuat
> ```
>
> Tidak perlu menampilkan detail teknis/database.

### 20. Riwayat perubahan harga

> Sebaiknya perubahan harga Mitra masuk ke audit/activity existing.
>
> Minimal:
>
> * Mitra;
> * Pekerjaan Jasa;
> * harga sebelumnya;
> * harga baru;
> * actor;
> * timestamp.
>
> Jangan mengubah historical RAB ketika audit harga dibuat.

### 21. Tidak perlu membuat master Pekerjaan Jasa per Mitra

> Hindari model:
>
> ```text
> Pekerjaan Jasa Mitra A
> Pekerjaan Jasa Mitra B
> Pekerjaan Jasa Mitra C
> ```
>
> apabila pekerjaan sebenarnya sama.
>
> Lebih baik secara konseptual:
>
> ```text
> Master Pekerjaan Jasa
>          │
>          ├── Harga Mitra A
>          ├── Harga Mitra B
>          └── Harga Mitra C
> ```
>
> sehingga nomenklatur pekerjaan tetap konsisten.

### 22. Harga dapat berbeda antar Mitra

> Jangan membuat harga master global sebagai satu-satunya harga.
>
> Contoh valid:
>
> ```text
> Jasa Penarikan Kabel
>
> PT ABC  → Rp 7.500 / unit
> PT XYZ  → Rp 8.000 / unit
> ```
>
> RAB masing-masing Project menggunakan harga Mitra pemilik Project.

### 23. Unit harga

> Jika harga jasa mempunyai basis Unit tertentu, tampilkan Unit tersebut jika model existing memilikinya.
>
> Contoh:
>
> ```text
> Jasa Penarikan Kabel
> Rp 7.500 / meter
> ```
>
> Jangan mengarang Unit jika field tidak tersedia.
>
> Inspect relation Pekerjaan Jasa dan Unit terlebih dahulu.

### 24. Project Admin Mitra

> Integrasikan dengan `QC-0017`.
>
> Admin Mitra yang authorized:
>
> ```text
> Kelola Harga Jasa
>       ↓
> Project
>       ↓
> Tambah RAB Jasa
>       ↓
> Baseline
>       ↓
> Progress / VO
> ```
>
> Tidak perlu membuat flow harga terpisah di dalam setiap Project jika harga memang merupakan price list Mitra.

### 25. Search

> Tambahkan/reuse search:
>
> ```text
> Cari Pekerjaan Jasa
> [ kode atau nama                                ]
> ```
>
> karena jumlah master dapat bertambah.
>
> Reuse shared search/filter component.

### 26. Status master

> Jika Pekerjaan Jasa memiliki status aktif/nonaktif, gunakan shared StatusBadge.
>
> Harga hanya dapat diatur/dipakai untuk master yang eligible sesuai business rule existing.
>
> Jangan menampilkan master nonaktif pada selector RAB jika sistem memang melarang pemakaiannya.

### 27. Permission Admin Mitra

> Pisahkan capability:
>
> ```text
> pekerjaan_jasa.read
> pekerjaan_jasa_price.manage_own
> ```
>
> dari:
>
> ```text
> pekerjaan_jasa_master.manage
> ```
>
> Admin Mitra dapat diberikan:
>
> ```text
> manage own price
> ```
>
> tanpa otomatis memperoleh:
>
> ```text
> manage global master
> ```

### 28. Backend authoritative

> Jangan hanya menampilkan field harga berdasarkan UI role.
>
> Endpoint update harga harus memastikan:
>
> ```text
> authenticated user
>       +
> Admin Mitra capability
>       +
> target mitra == user's mitra
> ```
>
> sebelum save dilakukan.

### 29. Cross-tenant test

> Minimal security regression:
>
> ```text
> Admin Mitra A
> update harga Mitra A
> → allowed
>
> Admin Mitra A
> update harga Mitra B
> → rejected 403/404
> ```
>
> Jangan menerima arbitrary `mitra_id` dari frontend tanpa object-level authorization.

### 30. User THC

> User THC dapat tetap melihat harga per Mitra jika memang authorized untuk kebutuhan governance/presales/project review.
>
> Namun capability tersebut berada di luar UI Admin Mitra dan tidak perlu membuka harga Mitra lain kepada Admin Mitra.

### 31. Empty state

> Jika belum ada harga yang diisi:
>
> ```text
> Belum ada harga jasa Mitra yang dikonfigurasi.
>
> Isi harga pada Pekerjaan Jasa yang akan digunakan untuk RAB Project.
> ```
>
> Gunakan reusable EmptyState/callout.

### 32. Responsive

> Desktop:
>
> * gunakan DataTable;
> * harga rata kanan;
> * action compact.
>
> Tablet/mobile:
>
> * row dapat berubah menjadi card;
> * currency input tetap mudah digunakan;
> * tidak ada horizontal scrolling yang tidak terkendali.

### 33. Ketentuan implementasi

> Sebelum implementasi, inspect:
>
> 1. model Pekerjaan Jasa;
> 2. apakah saat ini sudah ada harga global/harga Mitra;
> 3. Project → Mitra relation;
> 4. RAB Jasa model;
> 5. snapshot harga pada baris RAB;
> 6. Baseline/TOC;
> 7. Variation Order;
> 8. permission Admin Mitra;
> 9. audit/activity logging;
> 10. shared currency formatter.
>
> Jangan mengubah historical RAB atau baseline hanya untuk memperkenalkan price list Mitra.

## Dampak dan catatan

> Pekerjaan Jasa memang dapat berupa **master bersama**, tetapi harga jasa secara bisnis dapat berbeda untuk setiap Mitra.
>
> Kondisi saat ini:
>
> ```text
> Master Jasa
>    ↓
> tidak ada harga Mitra
>    ↓
> RAB tidak memiliki source harga Mitra
> ```
>
> Flow yang diharapkan:
>
> ```text
> Master Pekerjaan Jasa
>          ↓
> Mitra mengisi harga jasa
>          ↓
> Price List Mitra
>          ↓
> Project milik Mitra
>          ↓
> RAB Jasa
>          ↓
> snapshot harga
>          ↓
> Original Baseline
>          ↓
> Revised Baseline / Variation Order
> ```
>
> Dengan model ini, perubahan price list di kemudian hari tidak merusak histori biaya Project.

## Acceptance Criteria

* [ ] Halaman Pekerjaan Jasa Admin Mitra menggunakan design language yang sama dengan modul lain.
* [ ] Master Pekerjaan Jasa tetap dapat diperlakukan sebagai shared/global master.
* [ ] Admin Mitra tidak otomatis dapat mengedit kode/nama master global.
* [ ] Admin Mitra dapat mengisi harga jasa milik Mitranya sendiri.
* [ ] Harga disimpan per kombinasi Mitra + Pekerjaan Jasa.
* [ ] Harga Mitra A tidak mengubah harga Mitra B.
* [ ] Admin Mitra tidak dapat melihat/mengubah harga Mitra lain tanpa permission.
* [ ] Cross-tenant mutation ditolak backend.
* [ ] Harga menggunakan formatter Rupiah yang konsisten.
* [ ] Harga negatif ditolak.
* [ ] `Belum diisi` dibedakan dari harga `Rp 0`.
* [ ] Search berdasarkan kode/nama Pekerjaan Jasa tersedia.
* [ ] RAB Project mengambil harga dari Mitra pemilik Project.
* [ ] RAB tidak mengambil harga Mitra lain.
* [ ] Jika harga belum tersedia, sistem memberikan feedback yang jelas.
* [ ] Harga satuan disnapshot ketika baris RAB dibuat.
* [ ] Perubahan price list tidak mengubah RAB existing.
* [ ] Item RAB baru dapat menggunakan harga terbaru.
* [ ] Baseline tidak mengambil ulang harga terbaru.
* [ ] Original Baseline tidak berubah karena perubahan harga.
* [ ] Variation Order tetap mengikuti pricing semantics existing.
* [ ] Perubahan harga masuk audit/activity jika infrastructure tersedia.
* [ ] Admin Mitra hanya mendapat capability manage-own-price, bukan manage-global-master.
* [ ] Permission backend tetap authoritative.
* [ ] Tidak ada privilege escalation.
* [ ] Tidak ada data leakage lintas Mitra.
* [ ] Responsive pada desktop, tablet, dan mobile.
* [ ] Tidak ada dummy/fake data.
* [ ] Tidak ada horizontal scrolling yang tidak terkendali.
* [ ] Tidak ada JavaScript/runtime error baru.

## Bukti QC

* `01-actual.png` — kondisi halaman Master Data Pekerjaan Jasa saat login sebagai Admin Mitra; hanya kode dan nama pekerjaan yang tampil dan belum tersedia Harga Jasa Mitra.
* `02-context.png` — konteks kebutuhan Harga Jasa Mitra sebagai sumber harga RAB Jasa dan snapshot pada Baseline Project.

> Tambahkan bukti berikutnya dengan nomor berurutan. Jangan mengganti atau menghapus bukti lama.

## Riwayat status

| Tanggal      | Status | Oleh   | Catatan                                                                                                                                                                                                                         |
| ------------ | ------ | ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `2026-08-20` | `open` | Fatoni | Temuan dibuat. Admin Mitra perlu dapat mengelola harga per Pekerjaan Jasa milik Mitranya sendiri sebagai sumber Harga Satuan RAB Jasa, sementara master pekerjaan tetap shared dan harga historis RAB/Baseline tetap dibekukan. |
