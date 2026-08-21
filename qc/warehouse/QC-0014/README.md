# QC-0014 — Konsistensi Design Material dalam Transit

| Field                     | Nilai                                      |
| ------------------------- | ------------------------------------------ |
| ID                        | `QC-0014`                                  |
| Prefix                    | `warehouse-transit`                        |
| Status                    | `open`                                     |
| Severity                  | `minor`                                    |
| Tanggal/waktu pengujian   | `2026-08-20 16:05 WIB`                     |
| Reviewer                  | Fatoni                                     |
| Persona/role              | User THC                                   |
| Halaman atau URL produksi | https://deploythc.web.id/warehouse/transit |
| Browser/device            | Chrome / laptop Windows                    |
| GitHub Issue              | [Selesaikan presentation Warehouse, quantity formatter, Surat Jalan, dan Transit](https://github.com/thcjatim-bit/project-monitoring-system/issues/92) |

## Ringkasan

> Halaman **Material dalam Transit** secara fungsi sudah tersedia, tetapi perlu diselaraskan dengan design language yang sama dengan Operasional Material, Daftar Surat Jalan, Detail Surat Jalan, dan modul workspace lainnya. Tampilan tabel, nomor Surat Jalan, rute, status Transit, Qty, action Cetak, serta dukungan Surat Jalan multi-item perlu menggunakan reusable component dan prinsip UX yang telah ditetapkan pada QC sebelumnya.

## Langkah reproduksi

1. Buka `https://deploythc.web.id/warehouse/transit`.
2. Masuk sebagai persona/role **User THC**.
3. Buka menu **Transit** pada bagian **Warehouse**.
4. Perhatikan heading **Material dalam Transit**.
5. Perhatikan navigation link menuju **Operasional Material** dan **Daftar Surat Jalan**.
6. Perhatikan tabel Transit dengan kolom Surat Jalan, Material, Warehouse asal, Qty Transit, dan Aksi.
7. Perhatikan format Qty serta action `Cetak`.
8. Bandingkan dengan design language yang telah ditetapkan pada QC Operasional Material dan Surat Jalan.

## Hasil aktual

> Halaman **Material dalam Transit** masih menggunakan tabel sederhana dan belum sepenuhnya mengikuti design system baru.
>
> Navigation:
>
> `Operasional MaterialDaftar Surat Jalan`
>
> terlihat berdempetan dan belum menggunakan secondary navigation yang konsisten.
>
> Data Transit ditampilkan per Material dengan informasi:
>
> * Surat Jalan;
> * Material;
> * Warehouse asal;
> * Qty Transit;
> * action `Cetak`.
>
> Informasi Warehouse tujuan belum terlihat jelas pada tabel sehingga konteks perjalanan Material kurang lengkap.
>
> Qty masih mengikuti formatting lama dan belum menggunakan reusable quantity formatter dari `QC-0012`.
>
> Action `Cetak` masih berupa plain text link dan belum mengikuti pola button/action yang digunakan pada Daftar Surat Jalan.
>
> Struktur tabel juga perlu dipastikan tetap dapat menangani Surat Jalan yang mempunyai lebih dari satu item Material sebagaimana requirement multi-item pada `QC-0012`.

## Hasil yang diharapkan

> Halaman **Material dalam Transit** menggunakan design language yang sama dengan seluruh modul Warehouse dan reuse shared component yang sudah tersedia.

### 1. Header halaman

> Gunakan page hierarchy yang konsisten:
>
> ```text
> WAREHOUSE
>
> Material dalam Transit
> Pantau Material yang masih dalam perjalanan dan belum menjadi stok Warehouse tujuan.
> ```
>
> Penjelasan bahwa Material Transit belum dihitung sebagai stok Warehouse tujuan tetap dipertahankan karena penting secara operasional.

### 2. Navigasi Warehouse

> Navigation menuju:
>
> * Operasional Material;
> * Daftar Surat Jalan;
> * Transit;
>
> harus menggunakan pola navigation/action yang konsisten.
>
> Jangan menampilkan link berdempetan seperti:
>
> ```text
> Operasional MaterialDaftar Surat Jalan
> ```
>
> Gunakan spacing dan component yang jelas, misalnya:
>
> ```text
> Operasional Material · Daftar Surat Jalan · Transit
> ```
>
> atau tab/secondary navigation sesuai design system existing.

### 3. Tabel Transit

> Gunakan reusable DataTable yang sama dengan Daftar Surat Jalan.
>
> Informasi yang disarankan:
>
> ```text
> Surat Jalan
> Rute
> Material
> Qty dikirim
> Qty diterima
> Sisa Transit
> Status
> Aksi
> ```
>
> Jika data `Qty dikirim` atau `Qty diterima` belum tersedia pada query halaman saat ini, jangan membuat dummy data.
>
> Minimal tampilkan informasi existing dengan layout yang tetap kompatibel untuk penambahan data tersebut.

### 4. Rute Warehouse

> Tampilkan Warehouse asal dan tujuan secara jelas.
>
> Contoh:
>
> ```text
> bb — test → bb1 — testwhmitra
> ```
>
> Jangan hanya menampilkan Warehouse asal jika data Warehouse tujuan sebenarnya sudah tersedia dari Surat Jalan/Transit.
>
> Rute membantu user memahami Material sedang bergerak dari mana dan menuju ke mana.

### 5. Nomor Surat Jalan

> Nomor Surat Jalan seperti:
>
> ```text
> SJ-2608-0006
> ```
>
> harus dapat digunakan sebagai navigation action menuju Detail Surat Jalan jika user authorized.
>
> Hindari label teknis seperti:
>
> ```text
> SJ #6
> ```
>
> apabila nomor dokumen resmi Surat Jalan tersedia.
>
> Gunakan nomor dokumen domain sebagai informasi utama dan internal database ID hanya untuk routing/internal implementation.

### 6. Status Transit

> Gunakan reusable status badge/chip.
>
> Contoh:
>
> ```text
> [Dalam Transit]
> [Sebagian diterima]
> [Diterima]
> [Ada selisih]
> ```
>
> Nama status final tetap mengikuti state machine existing.
>
> Jangan menciptakan status baru hanya untuk kebutuhan design.

### 7. Semantic status

> Gunakan semantic color yang sama dengan modul sebelumnya:
>
> * info → sedang transit;
> * warning → partial/discrepancy;
> * success → diterima/selesai;
> * danger → masalah/cancelled jika status tersebut tersedia;
> * neutral → status lain.
>
> Reuse design token existing.

### 8. Quantity formatter

> Seluruh Qty pada halaman Transit harus menggunakan reusable formatter dari `QC-0012`.
>
> Contoh:
>
> ```text
> 100.000    → 100
> 2313.000   → 2.313
> 2312279.000 → 2.312.279
> ```
>
> Jika nilai benar-benar mempunyai pecahan:
>
> ```text
> 1250.5 → 1.250,5
> ```
>
> Jangan mengubah nilai pada database.
>
> Formatting hanya berlaku pada presentation layer.

### 9. Qty Transit

> Label quantity harus jelas secara domain.
>
> Jika angka sebenarnya merupakan:
>
> ```text
> Qty dikirim - Qty diterima - Qty diretur
> ```
>
> maka tampilkan sebagai **Sisa Transit** daripada hanya `Qty Transit`, jika terminology tersebut sesuai dengan model existing.
>
> Jangan mengganti label sebelum memastikan semantic field backend.

### 10. Surat Jalan multi-item

> Halaman Transit harus kompatibel dengan requirement **satu Surat Jalan memiliki banyak item Material** pada `QC-0012`.
>
> Contoh:
>
> ```text
> SJ-2608-0010
> bb — test → bb1 — testwhmitra
>
> 3 item
>
> Tiang 7m       100 Btg
> ODP             20 Pcs
> Splitter 1:8    10 Pcs
> ```
>
> Jangan mengasumsikan:
>
> ```text
> 1 Surat Jalan = 1 Material
> ```
>
> karena Surat Jalan dapat mempunyai collection item.

### 11. Tampilan multi-item

> Implementasi dapat menggunakan salah satu pola yang paling sesuai dengan shared component.

#### Opsi A — satu row per item

> ```text
> Surat Jalan     Material       Rute                 Sisa
> ─────────────────────────────────────────────────────────
> SJ-0010         Tiang 7m       bb → bb1             100
> SJ-0010         ODP            bb → bb1              20
> SJ-0010         Splitter       bb → bb1              10
> ```
>
> atau:

#### Opsi B — grouping per Surat Jalan

> ```text
> SJ-0010
> bb → bb1                             [Dalam Transit]
>
> 3 item
> ├─ Tiang 7m       100 Btg
> ├─ ODP             20 Pcs
> └─ Splitter        10 Pcs
> ```
>
> Prioritaskan pola yang paling konsisten dengan architecture dan DataTable existing.
>
> Jangan menduplikasi header/action Surat Jalan secara berlebihan jika grouping lebih efisien.

### 12. Qty per item

> Untuk Surat Jalan multi-item, sisa Transit dihitung **per item**.
>
> Contoh:
>
> ```text
> Material       Dikirim    Diterima    Sisa Transit
> ──────────────────────────────────────────────────
> Tiang 7m       100        100          0
> ODP             20         15          5
> Splitter        10          8          2
> ```
>
> Surat Jalan tidak boleh dianggap seluruhnya selesai apabila masih terdapat item dengan sisa Transit sesuai state machine existing.

### 13. Serial Number

> Jika Material ber-SN sedang Transit, halaman/detail Transit harus mempertahankan traceability Serial Number.
>
> Contoh:
>
> ```text
> ONU · 2 Pcs
> ├─ SN001
> └─ SN002
> ```
>
> Tidak perlu menampilkan seluruh SN di tabel utama jika membuat tabel terlalu padat.
>
> Identifier dapat ditampilkan melalui detail/disclosure Surat Jalan.
>
> Namun data tidak boleh hilang dari flow Transit.

### 14. Drum ID

> Jika Material merupakan drum kabel, Transit harus mempertahankan Drum ID.
>
> Contoh:
>
> ```text
> Kabel FO 24C
> DRM-00123 · 1.000 m
> ```
>
> Drum yang sama harus tetap dapat ditelusuri dari Warehouse asal sampai Warehouse tujuan.
>
> Jangan generate Drum ID baru pada tahap Transit.

### 15. Hubungan Request Material

> Jika Surat Jalan berasal dari Request Material approved, detail/navigasi Transit tetap harus mempertahankan relationship:
>
> ```text
> Request Material
>      ↓
> Surat Jalan
>      ↓
> Transit
>      ↓
> Receipt
> ```
>
> Tidak perlu menampilkan Request Material pada tabel utama jika membuat table terlalu padat, tetapi relation tetap harus tersedia melalui Detail Surat Jalan/Transit.

### 16. Qty Request → Kirim → Diterima

> Data model dan presentation harus kompatibel dengan tiga tahap quantity:
>
> ```text
> Qty Request
>      ↓
> Qty Kirim
>      ↓
> Qty Diterima
> ```
>
> Transit merepresentasikan barang yang sudah dikirim tetapi belum seluruhnya diselesaikan pada Warehouse tujuan.
>
> Jangan menyatukan ketiga quantity tersebut menjadi satu nilai.

### 17. Action

> Action `Cetak` mengikuti pola yang sama dengan `QC-0013`.
>
> Gunakan:
>
> ```text
> [Detail] [Cetak]
> ```
>
> atau menu/action shared component.
>
> `Detail` membuka Detail Surat Jalan.
>
> `Cetak` membuka dokumen Surat Jalan.

### 18. Cetak membuka tab baru

> Surat Jalan yang dicetak dari halaman Transit harus dibuka pada **tab baru**.
>
> Halaman Transit tetap terbuka.
>
> Flow:
>
> ```text
> Transit
>    ↓
> Klik Cetak
>    ↓
> tab baru → Surat Jalan
>
> tab lama → Transit tetap terbuka
> ```
>
> Gunakan safe new-tab behavior sesuai framework.

### 19. Tidak ada action penerimaan ganda

> Halaman Transit tidak boleh menambah flow penerimaan terpisah yang menyebabkan duplicate receipt dengan flow **Terima Pengiriman** pada Operasional Material.
>
> Jika nantinya tersedia shortcut:
>
> ```text
> [Terima]
> ```
>
> action tersebut harus menuju/membuka flow penerimaan yang sama, bukan membuat implementation receipt kedua.

### 20. Filter dan pencarian

> Jika jumlah Transit bertambah, struktur halaman harus siap menggunakan filter.
>
> Filter yang relevan di masa depan dapat berupa:
>
> * Nomor Surat Jalan;
> * Warehouse asal;
> * Warehouse tujuan;
> * Material;
> * status;
> * periode.
>
> Jika shared filter component sudah tersedia dan scope implementasi memungkinkan, reuse component tersebut.
>
> Jangan menambahkan filter besar yang tidak diperlukan hanya untuk memenuhi design.

### 21. Empty state

> Jika tidak terdapat Material Transit:
>
> ```text
> Tidak ada Material dalam Transit.
>
> Seluruh pengiriman telah diterima atau belum ada Surat Jalan yang sedang berjalan.
> ```
>
> Gunakan reusable empty-state component.

### 22. Responsive

> Pada desktop:
>
> * tabel memanfaatkan content width secara proporsional;
> * Qty mudah dibaca;
> * action tidak berdempetan.
>
> Pada tablet/mobile:
>
> * gunakan responsive table pattern existing;
> * grouping/card dapat digunakan jika lebih readable;
> * tidak terdapat horizontal scrolling yang tidak terkendali;
> * action tetap dapat digunakan.

### 23. Ketentuan implementasi

> Reuse shared component/design system dari QC sebelumnya:
>
> * PageHeader;
> * secondary navigation;
> * Card;
> * DataTable;
> * StatusBadge;
> * QuantityFormatter;
> * Button/action variants;
> * EmptyState;
> * Search/filter component jika tersedia.
>
> Jangan membuat design system khusus Transit.
>
> Jangan mengubah:
>
> * permission;
> * authorization;
> * Warehouse assignment;
> * Surat Jalan numbering;
> * Transit state machine;
> * receipt logic;
> * Request Material fulfillment;
> * Serial Number traceability;
> * Drum ID traceability;
> * ledger append-only;
> * stock calculation;
> * historical transaction;
> * audit/activity logging.
>
> Jangan membuat dummy/fake data.

## Dampak dan catatan

> Secara fungsi halaman Transit sudah menyediakan informasi Material yang masih dalam perjalanan, tetapi presentation-nya belum konsisten dengan flow Warehouse lainnya.
>
> Halaman ini merupakan bagian penting dari chain:
>
> ```text
> Request Material
>       ↓
> Warehouse source
>       ↓
> Surat Jalan
>       ↓
> TRANSIT
>       ↓
> Warehouse tujuan
>       ↓
> Terima Pengiriman
> ```
>
> Karena itu halaman Transit harus menggunakan konsep data dan terminology yang sama dengan Operasional Material dan Detail Surat Jalan.
>
> Halaman juga harus kompatibel dengan multi-item Surat Jalan:
>
> ```text
> 1 Surat Jalan
>      │
>      ├── Material A
>      ├── Material B
>      └── Material C
>           ↓
>      Transit per item
>           ↓
>      Receipt per item
> ```
>
> Acceptance utama:
>
> * Halaman Transit menggunakan design language yang sama dengan modul lainnya.
> * Header dan description konsisten.
> * Navigation Operasional Material / Daftar Surat Jalan / Transit rapi.
> * Data menggunakan shared DataTable.
> * Nomor Surat Jalan menggunakan nomor dokumen resmi jika tersedia.
> * Rute menampilkan Warehouse asal dan tujuan jika data tersedia.
> * Status menggunakan reusable badge.
> * Qty menggunakan shared formatter.
> * Integer tidak menampilkan trailing `.000`.
> * Pemisah ribuan konsisten.
> * Precision asli tidak hilang.
> * Halaman kompatibel dengan Surat Jalan multi-item.
> * Transit/sisa dihitung per item sesuai business logic existing.
> * SN tetap traceable untuk Material ber-SN.
> * Drum ID tetap traceable untuk drum.
> * Relation Request Material → Surat Jalan → Transit tetap dipertahankan.
> * Action Detail dan Cetak terpisah dengan jelas.
> * Cetak membuka tab baru.
> * Tab Transit tetap terbuka.
> * Tidak ada duplicate receipt atau stock movement.
> * Empty state menggunakan shared component.
> * Responsive pada desktop, tablet, dan mobile.
> * Permission dan authorization tidak berubah.
> * Transit state machine tidak berubah.
> * Ledger tetap append-only.
> * Tidak ada dummy/fake data.
> * Tidak ada horizontal scrolling yang tidak terkendali.
> * Tidak ada JavaScript/runtime error baru.

## Bukti QC

* `01-actual.png` — kondisi halaman Material dalam Transit saat pengujian.

> Tambahkan bukti berikutnya dengan nomor berurutan. Jangan mengganti atau menghapus bukti lama.

## Riwayat status

| Tanggal      | Status | Oleh   | Catatan                                                                                                                                                                                                                                        |
| ------------ | ------ | ------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `2026-08-20` | `open` | Fatoni | Temuan dibuat. Halaman Material dalam Transit perlu diselaraskan dengan design language Warehouse serta mengikuti prinsip multi-item Surat Jalan, quantity formatting, status, traceability, navigation, dan new-tab print dari QC sebelumnya. |
