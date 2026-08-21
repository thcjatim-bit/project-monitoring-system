# QC-0013 — Konsistensi Design Daftar dan Detail Surat Jalan

| Field                     | Nilai                                                                                               |
| ------------------------- | --------------------------------------------------------------------------------------------------- |
| ID                        | `QC-0013`                                                                                           |
| Prefix                    | `warehouse-transfer`                                                                                |
| Status                    | `open`                                                                                              |
| Severity                  | `minor`                                                                                             |
| Tanggal/waktu pengujian   | `2026-08-20 15:52 WIB`                                                                              |
| Reviewer                  | Fatoni                                                                                              |
| Persona/role              | User THC                                                                                            |
| Halaman atau URL produksi | `https://deploythc.web.id/warehouse/transfers` dan `https://deploythc.web.id/warehouse/transfers/4` |
| Browser/device            | Chrome / laptop Windows                                                                             |
| GitHub Issue              | [Selesaikan presentation Warehouse, quantity formatter, Surat Jalan, dan Transit](https://github.com/thcjatim-bit/project-monitoring-system/issues/92) |

## Ringkasan

> Halaman **Daftar Surat Jalan** dan **Detail Surat Jalan** secara fungsi sudah tersedia, tetapi perlu diselaraskan dengan design language yang sama dengan Command Center, Portfolio, Project, Master Data, dan Operasional Material. Tampilan status, tabel, informasi dokumen, Qty, action Cetak, Retur, dan Koreksi Buku Transaksi perlu menggunakan reusable component dan prinsip UX yang sudah ditetapkan pada QC sebelumnya.

## Langkah reproduksi

1. Buka `https://deploythc.web.id/warehouse/transfers`.
2. Masuk sebagai persona/role **User THC**.
3. Buka menu **Daftar Surat Jalan**.
4. Perhatikan tabel daftar Surat Jalan, status, rute, item, serta action `Buka detail` dan `Cetak`.
5. Klik salah satu Surat Jalan, misalnya `SJ-2608-0002`.
6. Buka halaman detail `/warehouse/transfers/4`.
7. Perhatikan bagian:

   * Status dokumen;
   * Item Material;
   * Retur Material;
   * Koreksi Buku Transaksi;
   * format Qty;
   * action Cetak Surat Jalan.
8. Bandingkan dengan design language dan formatting yang telah ditetapkan pada QC sebelumnya.

## Hasil aktual

> Halaman **Daftar Surat Jalan** masih menggunakan tabel sederhana dengan hierarchy visual yang terbatas.
>
> Status seperti `Diterima` masih berupa teks biasa.
>
> Action:
>
> `Buka detail` dan `Cetak`
>
> tampil sebagai link teks yang berdempetan sehingga hierarchy action kurang jelas.
>
> Pada halaman **Detail Surat Jalan**, informasi dokumen seperti:
>
> * Status;
> * Tanggal;
> * Pengirim;
> * Sopir;
> * Plat nomor;
>
> masih ditampilkan sebagai teks vertikal sederhana.
>
> Tabel Material menampilkan kolom:
>
> * Material;
> * Identitas;
> * Diterbitkan;
> * Diterima;
> * Diretur;
> * Sisa Transit.
>
> Bagian **Retur Material** dan **Koreksi Buku Transaksi** menggunakan form yang langsung terbuka dan belum mengikuti card/form hierarchy yang digunakan modul lain.
>
> Format Qty juga masih menampilkan nilai seperti:
>
> `33.000`
>
> yang dapat ambigu antara angka tiga puluh tiga dengan tiga puluh tiga ribu apabila tidak mengikuti formatter quantity yang konsisten.

## Hasil yang diharapkan

> Kedua halaman menggunakan satu design language dengan modul Warehouse dan aplikasi secara keseluruhan.
>
> Reuse:
>
> * PageHeader;
> * Card;
> * StatusBadge;
> * DataTable;
> * Button;
> * FormControl;
> * Alert/Toast;
> * Number/Quantity Formatter;
> * Confirmation Dialog;
> * Empty State.

### 1. Daftar Surat Jalan

> Header halaman dibuat konsisten:
>
> ```text
> WAREHOUSE
>
> Surat Jalan
> Pantau dokumen perpindahan Material dan status pengirimannya.
> ```
>
> Link navigasi ke **Operasional Material** dan **Transit** menggunakan secondary navigation/action yang rapi dan tidak berdempetan dengan heading.

### 2. Tabel Surat Jalan

> Tabel tetap dapat menggunakan kolom:
>
> ```text
> Nomor
> Tanggal
> Rute
> Item
> Status
> Aksi
> ```
>
> tetapi gunakan shared table styling yang konsisten.
>
> Contoh:
>
> ```text
> ┌──────────────────────────────────────────────────────────────┐
> │ Nomor        Tanggal      Rute      Item    Status    Aksi  │
> ├──────────────────────────────────────────────────────────────┤
> │ SJ-2608-0002 20 Aug 2026  bb → bb1  1 item  [Diterima] ... │
> │ SJ-2608-0001 20 Aug 2026  bb → bb1  1 item  [Diterima] ... │
> └──────────────────────────────────────────────────────────────┘
> ```

### 3. Status Surat Jalan

> Status jangan hanya berupa plain text.
>
> Gunakan reusable badge/chip, misalnya:
>
> ```text
> [Terbit]
> [Transit]
> [Sebagian diterima]
> [Diterima]
> [Selesai]
> [Dibatalkan]
> ```
>
> Nama status final tetap mengikuti state machine existing.
>
> Jangan menambah atau mengganti status domain hanya untuk kebutuhan tampilan.

### 4. Semantic status color

> Gunakan semantic color yang konsisten:
>
> * success → diterima/selesai;
> * info → terbit/transit;
> * warning → sebagian diterima/ada selisih;
> * danger → dibatalkan/error jika tersedia;
> * neutral → status lain/N/A.
>
> Gunakan token existing dan jangan membuat sistem warna khusus halaman ini.

### 5. Action tabel

> Jangan menampilkan:
>
> ```text
> Buka detailCetak
> ```
>
> tanpa separation yang jelas.
>
> Gunakan pola:
>
> ```text
> [Detail] [Cetak]
> ```
>
> atau secondary action / menu action sesuai design system.
>
> `Detail` menjadi navigation action.
>
> `Cetak` menjadi secondary action.

### 6. Cetak membuka tab baru

> Action **Cetak Surat Jalan** harus mengikuti prinsip pada `QC-0012`.
>
> Surat Jalan dibuka pada **tab baru**, sehingga halaman aplikasi tetap terbuka.
>
> Flow:
>
> ```text
> Daftar/Detail Surat Jalan
>        ↓
> Klik Cetak
>        ↓
> tab baru → dokumen Surat Jalan
>
> tab lama → tetap pada halaman aplikasi
> ```
>
> Gunakan safe new-tab behavior seperti `noopener` atau equivalent apabila relevan.

### 7. Detail Surat Jalan

> Detail dibuat menggunakan card hierarchy yang jelas.
>
> Contoh:
>
> ```text
> Detail Surat Jalan
>
> SJ-2608-0002                         [Diterima]
> bb — test → bb1 — testwhmitra
>
> ┌────────────────────────────────────────────────────┐
> │ Informasi dokumen                                  │
> │                                                    │
> │ Tanggal       20 Aug 2026                          │
> │ Pengirim      ahmad                                │
> │ Sopir         sapii                                │
> │ Plat nomor    N888ag                               │
> │                                                    │
> │                              [Cetak Surat Jalan]   │
> └────────────────────────────────────────────────────┘
> ```

### 8. Informasi rute

> Rute asal dan tujuan harus mudah dikenali.
>
> Gunakan format konsisten:
>
> ```text
> Dari
> bb — test
>
> Ke
> bb1 — testwhmitra
> ```
>
> atau:
>
> ```text
> bb — test  →  bb1 — testwhmitra
> ```
>
> Jangan hanya mengandalkan kode Warehouse apabila nama tersedia.

### 9. Hubungan Request Material

> Jika Surat Jalan dibuat dari **Request Material**, tampilkan reference Request Material pada detail.
>
> Contoh:
>
> ```text
> Request Material
> RM-2608-0005
> ```
>
> Reference dapat diklik jika user authorized untuk melihat Request tersebut.
>
> Jika Surat Jalan bukan berasal dari Request Material, tidak perlu membuat data palsu atau placeholder yang tidak berguna.

### 10. Tabel Item Material

> Gunakan shared DataTable.
>
> Kolom tetap dapat mencakup:
>
> ```text
> Material
> Identitas
> Dikirim
> Diterima
> Diretur
> Sisa Transit
> ```
>
> Saya sarankan label `Diterbitkan` pada quantity item dipertimbangkan menjadi **Dikirim** jika memang secara domain berarti Qty Material yang dikirim.
>
> Namun jangan mengganti terminology domain tanpa memastikan makna field pada backend.

### 11. Qty Request vs Kirim vs Diterima

> Jika Surat Jalan berasal dari Request Material dan data tersedia, detail sebaiknya dapat memperlihatkan:
>
> ```text
> Qty Request
> Qty Dikirim
> Qty Diterima
> Qty Diretur
> Sisa Transit
> ```
>
> Contoh:
>
> ```text
> Material      Request   Dikirim   Diterima   Retur   Sisa Transit
> ─────────────────────────────────────────────────────────────────
> Tiang 7m      100       95        93         0       2
> ```
>
> Jangan menyatukan seluruh quantity menjadi satu angka.

### 12. Format Qty

> Gunakan shared formatter yang sama dengan `QC-0012`.
>
> Jika stored value:
>
> ```text
> 33.000
> ```
>
> dan secara semantik merupakan integer `33`, tampilkan:
>
> ```text
> 33
> ```
>
> Jika:
>
> ```text
> 2312279.000
> ```
>
> tampilkan:
>
> ```text
> 2.312.279
> ```
>
> Jika nilai benar-benar mempunyai pecahan:
>
> ```text
> 1250.5
> ```
>
> tampilkan sesuai locale:
>
> ```text
> 1.250,5
> ```
>
> Jangan mengubah stored value/database precision hanya demi display.

### 13. Alignment angka

> Quantity pada tabel sebaiknya menggunakan alignment numerik yang konsisten, idealnya rata kanan apabila shared DataTable mendukungnya.
>
> Unit tetap ditampilkan bersama atau pada kolom terpisah secara konsisten.

### 14. Identitas Material

> Kolom **Identitas** digunakan untuk tracking Material.
>
> Untuk Material biasa:
>
> ```text
> —
> ```
>
> Untuk Material ber-SN:
>
> tampilkan Serial Number.
>
> Untuk drum:
>
> tampilkan Drum ID.
>
> Jika terdapat beberapa SN, gunakan layout compact atau detail disclosure yang tetap readable.

### 15. Catatan kontrol

> Penjelasan seperti:
>
> `Transit bukan stok Warehouse tujuan. Penerimaan, pembatalan, retur, dan koreksi selalu membuat jejak append-only.`
>
> tetap dipertahankan karena penting secara operasional.
>
> Namun tampilkan sebagai info callout/help text yang konsisten, bukan paragraf yang menyatu dengan seluruh konten.

### 16. Retur Material

> Section **Retur Material** dibuat sebagai card terpisah.
>
> Contoh:
>
> ```text
> ┌────────────────────────────────────────────────────┐
> │ Retur Material                                     │
> │ Buat pengiriman balik untuk Material yang diretur. │
> │                                                    │
> │ Tanggal            Pengirim                        │
> │ [20/08/2026]       [                            ]  │
> │                                                    │
> │ Sopir              Plat nomor                      │
> │ [            ]      [                           ]  │
> │                                                    │
> │ Tiang 7m                                           │
> │ Maks. tersedia untuk retur: 33 Btg                 │
> │ Qty retur                                          │
> │ [                                                ] │
> │                                                    │
> │                                  [Terbitkan Retur] │
> └────────────────────────────────────────────────────┘
> ```

### 17. Qty Retur

> Qty Retur harus menggunakan formatter/input behavior yang konsisten.
>
> User harus mengetahui batas maksimal yang dapat diretur.
>
> Qty Retur:
>
> * tidak boleh negatif;
> * tidak boleh melebihi Qty yang eligible untuk diretur;
> * harus mengikuti precision/unit Material;
> * tidak boleh menyebabkan stock movement ganda.

### 18. Retur menghasilkan Surat Jalan baru

> Pertahankan prinsip existing bahwa retur membuat **Surat Jalan baru arah sebaliknya**.
>
> Hubungan parent/original transfer dan retur harus tetap dapat ditelusuri.
>
> Setelah retur berhasil diterbitkan, Surat Jalan retur juga dibuka pada tab baru sesuai prinsip sebelumnya.

### 19. Koreksi Buku Transaksi

> Section **Koreksi Buku Transaksi** dibuat sebagai card yang jelas dan secara visual dibedakan dari flow normal.
>
> Karena ini operation sensitif, tampilkan explanatory text seperti:
>
> ```text
> Koreksi tidak mengubah transaksi asli.
> Sistem membuat pembalikan dan transaksi koreksi baru.
> ```
>
> Jangan mengubah prinsip append-only.

### 20. Form koreksi

> Contoh:
>
> ```text
> Koreksi Buku Transaksi
>
> Material
> Tiang 7m · Warehouse testwhmitra
>
> Qty koreksi
> [                                             ]
>
> Alasan
> [                                             ]
>
>                              [Simpan koreksi]
> ```
>
> `Alasan` harus diwajibkan jika business rule existing memang memerlukannya.
>
> Validation error ditampilkan dekat field terkait.

### 21. Confirmation untuk koreksi

> Karena koreksi mempengaruhi ledger, gunakan confirmation dialog reusable jika tersedia.
>
> Contoh:
>
> ```text
> Simpan koreksi transaksi?
>
> Transaksi asli tidak akan diubah.
> Sistem akan membuat entry pembalikan dan koreksi baru.
>
>                         [Batal] [Simpan koreksi]
> ```
>
> Jangan membuat direct update/delete pada row ledger.

### 22. Status dan action berdasarkan kondisi

> Action Retur atau Koreksi hanya ditampilkan jika user memang:
>
> * authorized;
> * status dokumen mengizinkan;
> * item masih eligible.
>
> Jangan hanya menyembunyikan tombol secara visual jika backend tetap mengizinkan operation unauthorized.
>
> Authorization backend harus tetap authoritative.

### 23. Empty state

> Jika tidak ada Surat Jalan:
>
> ```text
> Belum ada Surat Jalan.
>
> Surat Jalan yang diterbitkan dari Operasional Material akan muncul di sini.
> ```
>
> Gunakan reusable empty-state component.

### 24. Search dan filter daftar

> Jika jumlah Surat Jalan bertambah, halaman daftar perlu dapat berkembang tanpa menjadi sulit digunakan.
>
> Jika shared filter component sudah tersedia, dapat ditambahkan filter seperti:
>
> * Nomor Surat Jalan;
> * tanggal/periode;
> * Warehouse asal;
> * Warehouse tujuan;
> * status.
>
> Tidak perlu menambah filter baru apabila scope implementasi sekarang hanya visual, tetapi struktur tabel/page jangan menghalangi penambahan filter ke depan.

### 25. Responsive

> Pada desktop:
>
> * table menggunakan ruang horizontal dengan baik;
> * detail menggunakan cards dengan width proporsional;
> * Retur/Koreksi tidak memenuhi seluruh layar jika field sedikit.
>
> Pada tablet/mobile:
>
> * card menjadi satu kolom;
> * actions dapat wrap;
> * table menggunakan responsive pattern existing;
> * tidak terdapat horizontal page scrolling yang tidak terkendali.

### 26. Konsistensi navigasi

> Navigasi:
>
> ```text
> Operasional Material
> Daftar Surat Jalan
> Transit
> ```
>
> harus menggunakan pola yang konsisten.
>
> Jangan menampilkan link yang berdempetan seperti:
>
> ```text
> Operasional MaterialTransit
> ```
>
> Berikan spacing/component navigation yang jelas.

### 27. Ketentuan implementasi

> Reuse shared component/design system hasil QC sebelumnya:
>
> * page header;
> * breadcrumbs/secondary navigation jika tersedia;
> * card;
> * status badge;
> * data table;
> * number formatter;
> * button variants;
> * form controls;
> * alerts/callouts;
> * confirmation dialog;
> * empty state.
>
> Jangan membuat design system baru khusus Surat Jalan.
>
> Perubahan tidak boleh mengubah:
>
> * permission;
> * authorization;
> * Surat Jalan numbering;
> * state machine Surat Jalan;
> * Transit state machine;
> * Request Material relationship;
> * ledger append-only;
> * receipt logic;
> * return logic;
> * correction semantics;
> * Serial Number traceability;
> * Drum ID traceability;
> * Warehouse ownership;
> * historical transaction;
> * audit/activity logging.
>
> Jangan membuat dummy/fake data.

## Dampak dan catatan

> Kondisi saat ini tidak menunjukkan kegagalan fungsi utama pada daftar/detail Surat Jalan, tetapi presentation-nya belum mengikuti design system baru dan beberapa informasi penting seperti status, quantity, action, serta hubungan antar proses belum mempunyai hierarchy yang kuat.
>
> Dengan perubahan ini, flow Warehouse akan terlihat konsisten:
>
> ```text
> Request Material
>       ↓
> Operasional Material
>       ↓
> Surat Jalan
>       ↓
> Transit
>       ↓
> Penerimaan
>       ↓
> Retur / Koreksi bila diperlukan
> ```
>
> Daftar dan detail Surat Jalan juga menjadi pusat audit yang lebih mudah dibaca karena user dapat melihat:
>
> ```text
> Qty Request
>      ↓
> Qty Dikirim
>      ↓
> Qty Diterima
>      ↓
> Qty Diretur
>      ↓
> Sisa Transit
> ```
>
> tanpa mengubah prinsip ledger append-only.
>
> Acceptance utama:
>
> * Daftar Surat Jalan menggunakan design language yang sama dengan modul lain.
> * Detail Surat Jalan menggunakan card hierarchy yang konsisten.
> * Status menggunakan reusable status badge.
> * Action Detail dan Cetak terpisah dengan jelas.
> * Cetak Surat Jalan terbuka pada tab baru.
> * Tab aplikasi tetap terbuka.
> * Rute asal dan tujuan mudah dikenali.
> * Reference Request Material ditampilkan jika tersedia.
> * Item Material menggunakan shared DataTable.
> * SN/Drum ID dapat ditampilkan sebagai identitas Material jika tersedia.
> * Qty menggunakan shared formatter dari QC-0012.
> * Integer tidak menampilkan trailing `.000`.
> * Pemisah ribuan menggunakan format konsisten.
> * Precision valid tidak hilang.
> * Retur menggunakan card/form yang konsisten.
> * Retur tetap membuat Surat Jalan baru arah sebaliknya.
> * Surat Jalan retur dapat dibuka pada tab baru.
> * Koreksi menggunakan card/form yang jelas.
> * Koreksi tetap mempertahankan ledger append-only.
> * Action sensitif hanya tersedia jika authorized.
> * Navigation Operasional Material / Surat Jalan / Transit rapi.
> * Tidak ada perubahan state machine.
> * Tidak ada perubahan permission atau authorization.
> * Historical transaction tidak berubah.
> * Tidak ada dummy/fake data.
> * Tidak ada horizontal scrolling yang tidak terkendali.
> * Tidak ada JavaScript/runtime error baru.

## Bukti QC

* `01-list-actual.png` — kondisi halaman Daftar Surat Jalan saat pengujian.
* `02-detail-actual.png` — kondisi halaman Detail Surat Jalan, item Material, Retur Material, dan Koreksi Buku Transaksi.

> Tambahkan bukti berikutnya dengan nomor berurutan. Jangan mengganti atau menghapus bukti lama.

## Riwayat status

| Tanggal      | Status | Oleh   | Catatan                                                                                                                                                                                                                                                   |
| ------------ | ------ | ------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `2026-08-20` | `open` | Fatoni | Temuan dibuat. Daftar dan Detail Surat Jalan perlu diselaraskan dengan design language workspace serta menggunakan prinsip status, quantity formatter, navigation, new-tab print, retur, dan ledger append-only yang sudah ditetapkan pada QC sebelumnya. |
