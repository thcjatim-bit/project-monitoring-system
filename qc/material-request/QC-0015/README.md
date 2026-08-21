# QC-0015 — Konsistensi Design dan Informasi Project pada Request Material

| Field                     | Nilai                                      |
| ------------------------- | ------------------------------------------ |
| ID                        | `QC-0015`                                  |
| Prefix                    | `material-request`                         |
| Status                    | `open`                                     |
| Severity                  | `major`                                    |
| Tanggal/waktu pengujian   | `2026-08-20 16:24 WIB`                     |
| Reviewer                  | Fatoni                                     |
| Persona/role              | User THC                                   |
| Halaman atau URL produksi | https://deploythc.web.id/material-requests |
| Browser/device            | Chrome / laptop Windows                    |
| GitHub Issue              | [Tambahkan konteks Project pada daftar Request Material](https://github.com/thcjatim-bit/project-monitoring-system/issues/93) |

## Ringkasan

> Halaman **Request Material** perlu diselaraskan dengan design language modul lainnya. Selain itu, Request Material saat ini tidak menampilkan **Project ID** dan **Project Name**, sehingga asal kebutuhan Material tidak dapat diketahui dengan jelas dari daftar Request.

## Langkah reproduksi

1. Buka `https://deploythc.web.id/material-requests`.
2. Masuk sebagai persona/role **User THC**.
3. Buka menu **Request Material**.
4. Perhatikan daftar Request Material yang sudah tersedia.
5. Perhatikan informasi yang ditampilkan pada setiap Request.
6. Perhatikan bahwa status, Mitra, dan daftar Material muncul.
7. Perhatikan bahwa **Project ID** dan **Project Name** tidak ditampilkan.
8. Bandingkan dengan flow Request Material → approval → Warehouse source → Surat Jalan → Transit → penerimaan yang telah ditentukan pada QC sebelumnya.

## Hasil aktual

> Halaman Request Material saat ini menampilkan Request dalam bentuk card sederhana seperti:
>
> ```text
> #2 — disetujui — pt abc
>
> kabel 12c: 312.000 meter
> kabel 24c: 312.000 meter
> ODP: 11.000 Pcs
> SFP: 22.000 Pcs
> splitter 1:8: 33.000 Pcs
> tiang 7m: 44.000 Btg
> tiang 9m: 55.000 Btg
> ```
>
> Informasi yang terlihat antara lain:
>
> * ID Request internal;
> * status Request;
> * Mitra;
> * daftar Material;
> * Qty;
> * Unit.
>
> Namun tidak terlihat:
>
> * Project ID;
> * Project Name.
>
> Akibatnya user tidak dapat mengetahui Request Material tersebut dibuat untuk Project mana hanya dari halaman daftar.
>
> Status masih ditampilkan sebagai plain text seperti `disetujui`.
>
> Item Material masih menggunakan format teks vertikal sederhana.
>
> Qty masih menampilkan trailing `.000`, misalnya:
>
> ```text
> 312.000
> 11.000
> 22.000
> ```
>
> sehingga belum mengikuti quantity formatting yang ditentukan pada `QC-0012`.
>
> Layout juga belum mengikuti management card/list dan status hierarchy yang digunakan pada modul lainnya.

## Hasil yang diharapkan

> Halaman **Request Material** menggunakan design language yang sama dengan Command Center, Portfolio, Project, Warehouse, Surat Jalan, dan Transit.
>
> Setiap Request harus menampilkan informasi Project secara jelas.

### 1. Header halaman

> Gunakan page hierarchy yang konsisten:
>
> ```text
> ALUR MATERIAL
>
> Request Material
> Pantau kebutuhan Material Project dari pengajuan hingga fulfillment.
> ```

### 2. Project ID dan Project Name wajib tampil

> Setiap Request Material harus menampilkan:
>
> * Project ID;
> * Project Name.
>
> Contoh:
>
> ```text
> Request Material RM-2608-0002                [Disetujui]
>
> Project
> aa — kelurahan rembiga
>
> Mitra
> pt abc
> ```
>
> Jika Project mempunyai code/ID dan nama:
>
> ```text
> aa — kelurahan rembiga
> ```
>
> gunakan pola:
>
> ```text
> <Project ID> — <Project Name>
> ```
>
> Project ID dan Project Name harus berasal dari relation/data sebenarnya.
>
> Jangan menggunakan dummy value atau mengambil nama Project dari string lain.

### 3. Investigasi data Project

> Sebelum hanya menambahkan field pada template/frontend, inspect terlebih dahulu:
>
> * relation Request Material → Project;
> * query/list endpoint;
> * serializer/view model;
> * permission scope;
> * database relation existing.
>
> Tentukan apakah Project sebenarnya sudah tersimpan tetapi tidak ikut dikirim ke UI, atau Project relation memang belum tersimpan pada Request.
>
> Jika relation sudah tersedia, expose data tersebut tanpa mengubah business logic yang tidak diperlukan.
>
> Jika relation belum tersedia pada Request existing, jangan menebak Project dari Mitra atau Material.

### 4. Project sebagai traceability utama

> Project harus tetap dapat ditelusuri sepanjang flow:
>
> ```text
> Project
>    ↓
> Request Material
>    ↓
> Approval
>    ↓
> Warehouse Source
>    ↓
> Surat Jalan
>    ↓
> Transit
>    ↓
> Penerimaan
> ```
>
> Jika Request Material dibuat untuk suatu Project, informasi Project tidak boleh hilang ketika Request berpindah ke tahap fulfillment.

### 5. Nomor Request

> Jika sistem memiliki nomor domain Request Material yang lebih resmi daripada internal database ID:
>
> ```text
> RM-2608-0002
> ```
>
> tampilkan nomor domain sebagai identifier utama.
>
> Internal ID seperti:
>
> ```text
> #2
> ```
>
> dapat tetap digunakan secara internal untuk routing apabila diperlukan, tetapi tidak perlu menjadi identifier utama jika nomor dokumen sudah tersedia.
>
> Jangan membuat format nomor baru apabila generator Request Material belum tersedia.

### 6. Status menggunakan badge

> Status:
>
> ```text
> disetujui
> ```
>
> jangan hanya ditampilkan sebagai teks.
>
> Gunakan reusable status badge/chip.
>
> Contoh:
>
> ```text
> [Diajukan]
> [Disetujui]
> [Ditolak]
> [Sebagian dipenuhi]
> [Dipenuhi]
> ```
>
> Nama status final harus mengikuti state machine existing.
>
> Jangan membuat status baru hanya untuk kebutuhan visual.

### 7. Semantic status

> Gunakan semantic color yang konsisten:
>
> * pending/diajukan → info/neutral;
> * approved/disetujui → success/info;
> * partial → warning;
> * fulfilled → success;
> * rejected/cancelled → danger/neutral sesuai token existing.
>
> Gunakan shared StatusBadge.

### 8. Card Request Material

> Setiap Request dibuat menjadi management card yang lebih terstruktur.
>
> Contoh:
>
> ```text
> ┌────────────────────────────────────────────────────────────┐
> │ RM-2608-0002                               [Disetujui]    │
> │                                                            │
> │ Project    aa — kelurahan rembiga                          │
> │ Mitra      pt abc                                          │
> │                                                            │
> │ 7 item Material                                            │
> │                                                            │
> │ Material           Qty Request         Unit                │
> │ ─────────────────────────────────────────────              │
> │ Kabel 12C          312                 meter               │
> │ Kabel 24C          312                 meter               │
> │ ODP                 11                 Pcs                 │
> │ SFP                 22                 Pcs                 │
> │ Splitter 1:8        33                 Pcs                 │
> │ Tiang 7m            44                 Btg                 │
> │ Tiang 9m            55                 Btg                 │
> │                                                            │
> │                                             [Lihat detail] │
> └────────────────────────────────────────────────────────────┘
> ```

### 9. Material item menggunakan struktur tabel/list

> Jangan hanya menampilkan Material dalam paragraph/list teks:
>
> ```text
> kabel 12c: 312.000 meter
> ```
>
> Gunakan compact table/list dengan kolom minimal:
>
> * Material;
> * Qty Request;
> * Unit.
>
> Jika fulfillment sudah dimulai, dapat diperluas dengan:
>
> * Qty Request;
> * Qty dikirim;
> * sisa Request.
>
> Jangan menambahkan value tersebut jika data belum tersedia.

### 10. Quantity formatter

> Gunakan shared formatter dari `QC-0012`.
>
> Jika stored Qty:
>
> ```text
> 312.000
> ```
>
> dan nilai sebenarnya adalah integer `312`, tampilkan:
>
> ```text
> 312
> ```
>
> Jika:
>
> ```text
> 12312.000
> ```
>
> tampilkan:
>
> ```text
> 12.312
> ```
>
> Jika nilai memiliki pecahan valid:
>
> ```text
> 1250.5
> ```
>
> tampilkan:
>
> ```text
> 1.250,5
> ```
>
> Formatting hanya pada presentation layer.
>
> Jangan mengubah stored value atau precision database.

### 11. Project dapat dibuka jika authorized

> Jika user mempunyai akses ke Project terkait, Project ID/name dapat menjadi navigation link:
>
> ```text
> aa — kelurahan rembiga
> ```
>
> menuju detail/management Project yang sesuai.
>
> Jika tidak ada detail Project route atau user tidak authorized, cukup tampilkan sebagai teks.
>
> Jangan memperluas permission hanya untuk membuat link bekerja.

### 12. Mitra

> Informasi Mitra tetap ditampilkan.
>
> Gunakan pola yang konsisten:
>
> ```text
> Mitra
> pt abc
> ```
>
> Jangan menggantikan Project dengan Mitra.
>
> Project dan Mitra merupakan informasi berbeda:
>
> ```text
> Project → kebutuhan pekerjaan
> Mitra   → ownership/cakupan
> ```

### 13. Request approved dan Warehouse fulfillment

> Request yang sudah `approved/disetujui` harus kompatibel dengan flow pada `QC-0012`.
>
> Secara domain:
>
> ```text
> Request approved
>       ↓
> Warehouse source
>       ↓
> Qty Request vs Qty Kirim
>       ↓
> Surat Jalan
> ```
>
> Informasi Project harus tetap terbawa pada proses tersebut.

### 14. Progress fulfillment

> Jika data fulfillment tersedia, Request dapat menampilkan progress secara ringkas:
>
> ```text
> Requested    7 item
> Fulfilled    4 item
> Remaining    3 item
> ```
>
> atau berdasarkan Qty per item.
>
> Jangan menghitung progress secara keliru hanya dari jumlah baris Material.
>
> Gunakan business logic fulfillment existing.

### 15. Partial fulfillment per Material

> Untuk Request multi-item:
>
> ```text
> Material       Request    Dikirim    Sisa
> ─────────────────────────────────────────
> Tiang 7m       100        60         40
> ODP             20        20          0
> ```
>
> status Request harus mencerminkan keseluruhan fulfillment sesuai state machine existing.
>
> Jangan menandai Request selesai jika masih terdapat sisa Material.

### 16. Request detail

> Jika halaman/detail Request tersedia atau akan digunakan, action:
>
> ```text
> [Lihat detail]
> ```
>
> harus menggunakan shared secondary/navigation button.
>
> Detail idealnya menampilkan:
>
> * nomor Request;
> * Project;
> * Mitra;
> * status;
> * tanggal;
> * requester;
> * approver jika tersedia;
> * Material;
> * Qty Request;
> * fulfillment;
> * Surat Jalan terkait.
>
> Jangan membuat data yang belum tersedia.

### 17. Hubungan Surat Jalan

> Untuk Request yang sudah diproses, jika relation Surat Jalan tersedia, Request dapat menampilkan:
>
> ```text
> Surat Jalan
> SJ-2608-0010
> ```
>
> dan user authorized dapat membuka detail Surat Jalan.
>
> Satu Request dapat mempunyai lebih dari satu Surat Jalan karena partial fulfillment.

### 18. Multi-item Request

> Halaman harus tetap mendukung Request dengan banyak Material seperti pada screenshot.
>
> Jangan mengasumsikan:
>
> ```text
> 1 Request = 1 Material
> ```
>
> Data model dan UI harus mempertahankan collection item.

### 19. Search/filter

> Karena Request akan bertambah, struktur halaman sebaiknya siap menggunakan shared filter component.
>
> Filter relevan dapat berupa:
>
> * nomor Request;
> * Project;
> * Mitra;
> * status;
> * periode.
>
> Jika implementasi filter berada di luar scope QC ini, tidak wajib ditambahkan sekarang.
>
> Namun jangan membuat layout yang sulit dikembangkan.

### 20. Empty state

> Jika tidak ada Request:
>
> ```text
> Belum ada Request Material.
>
> Request Material yang dibuat dari kebutuhan Project akan muncul di sini.
> ```
>
> Gunakan reusable EmptyState.

### 21. Responsive

> Pada desktop:
>
> * card/list menggunakan content width secara proporsional;
> * Material item mudah dibaca;
> * Project dan status mudah dipindai.
>
> Pada tablet/mobile:
>
> * card menjadi satu kolom;
> * item table menggunakan responsive pattern existing;
> * action dapat wrap;
> * tidak terdapat horizontal scrolling yang tidak terkendali.

### 22. Ketentuan implementasi

> Reuse shared component/design system hasil QC sebelumnya:
>
> * PageHeader;
> * Card;
> * StatusBadge;
> * DataTable;
> * QuantityFormatter;
> * Button/action variants;
> * EmptyState;
> * filter/search component jika tersedia.
>
> Jangan membuat design system khusus Request Material.
>
> Perubahan tidak boleh mengubah:
>
> * permission;
> * authorization;
> * Project ownership;
> * relation Project–Mitra;
> * approval rules;
> * Request Material state machine;
> * fulfillment logic;
> * Warehouse source rules;
> * Surat Jalan relationship;
> * Transit logic;
> * historical data;
> * audit/activity logging.
>
> Jangan membuat dummy/fake Project ID atau Project Name.

## Dampak dan catatan

> Tidak tampilnya **Project ID dan Project Name** mengurangi traceability Request Material.
>
> Saat ini user dapat melihat:
>
> ```text
> Request #2
> disetujui
> pt abc
> Material...
> ```
>
> tetapi tidak dapat menjawab dengan cepat:
>
> ```text
> "Material ini diminta untuk Project apa?"
> ```
>
> Informasi Project penting karena Request Material seharusnya menjadi bagian dari flow:
>
> ```text
> Project
>    ↓
> Request Material
>    ↓
> Approval
>    ↓
> Warehouse fulfillment
>    ↓
> Surat Jalan
>    ↓
> Transit
>    ↓
> Receipt
> ```
>
> Dengan Project ID dan Project Name yang tetap terbawa, user dapat melakukan audit dari kebutuhan Project sampai barang diterima.
>
> Acceptance utama:
>
> * Halaman Request Material menggunakan design language yang sama dengan modul lain.
> * Project ID tampil pada setiap Request jika relation tersedia.
> * Project Name tampil pada setiap Request jika relation tersedia.
> * Project menggunakan format `<ID> — <Name>` yang konsisten.
> * Project data berasal dari relation sebenarnya, bukan dummy/inference.
> * Mitra tetap ditampilkan.
> * Status menggunakan reusable badge.
> * Request multi-item tetap didukung.
> * Material menggunakan compact list/DataTable.
> * Qty menggunakan shared quantity formatter.
> * Integer tidak menampilkan trailing `.000`.
> * Pemisah ribuan menggunakan format konsisten.
> * Precision valid tidak hilang.
> * Request approved tetap kompatibel dengan Warehouse fulfillment pada QC-0012.
> * Qty Request, Qty Kirim, dan sisa fulfillment dapat dipertahankan per item jika data tersedia.
> * Relation Request → Surat Jalan tetap dapat ditelusuri.
> * Partial fulfillment tidak menyebabkan Request selesai terlalu dini.
> * Permission dan authorization tidak berubah.
> * Request state machine tidak berubah.
> * Tidak ada dummy/fake Project.
> * Tidak ada horizontal scrolling yang tidak terkendali.
> * Tidak ada JavaScript/runtime error baru.

## Bukti QC

* `01-actual.png` — kondisi halaman Request Material saat pengujian; daftar Request menampilkan status, Mitra, dan Material tetapi Project ID serta Project Name tidak terlihat.
* `02-context.png` — konteks Request multi-item dan kebutuhan traceability Project → Request Material → Warehouse fulfillment.

> Tambahkan bukti berikutnya dengan nomor berurutan. Jangan mengganti atau menghapus bukti lama.

## Riwayat status

| Tanggal      | Status | Oleh   | Catatan                                                                                                                                                                                                          |
| ------------ | ------ | ------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `2026-08-20` | `open` | Fatoni | Temuan dibuat. Halaman Request Material perlu mengikuti design language workspace serta menampilkan Project ID dan Project Name agar traceability kebutuhan Material sampai fulfillment Warehouse tetap terjaga. |
