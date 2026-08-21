# QC-0012 — Konsistensi Design dan Penyempurnaan Operasional Material

| Field                     | Nilai                              |
| ------------------------- | ---------------------------------- |
| ID                        | `QC-0012`                          |
| Prefix                    | `warehouse-operasional`            |
| Status                    | `open`                             |
| Severity                  | `major`                            |
| Tanggal/waktu pengujian   | `2026-08-20 15:40 WIB`             |
| Reviewer                  | Fatoni                             |
| Persona/role              | User THC                           |
| Halaman atau URL produksi | https://deploythc.web.id/warehouse |
| Browser/device            | Chrome / laptop Windows            |
| GitHub Issue              | [Selesaikan presentation Warehouse, quantity formatter, Surat Jalan, dan Transit](https://github.com/thcjatim-bit/project-monitoring-system/issues/92) |

## Ringkasan

> Halaman **Operasional Material** perlu diselaraskan dengan design language modul lainnya. Selain itu terdapat beberapa masalah fungsional pada transaksi stok: Material ber-Serial Number dan Drum ID tidak dapat dicatat karena form tidak menyediakan field identifier, format Qty menampilkan `.000` dan belum menggunakan pemisah ribuan, penerbitan Surat Jalan menggantikan halaman aplikasi, serta belum tersedia flow penerimaan untuk pengiriman/Transit yang sedang terbuka menuju Warehouse tujuan.

## Langkah reproduksi

1. Buka `https://deploythc.web.id/warehouse`.
2. Masuk sebagai persona/role **User THC**.
3. Buka menu **Operasional Material**.
4. Pada bagian **Penerimaan stok**, pilih Material dengan jenis yang membutuhkan **Serial Number**.
5. Isi Qty lalu coba mencatat penerimaan.
6. Perhatikan muncul validasi bahwa Serial Number wajib diisi, tetapi tidak tersedia field untuk memasukkan Serial Number.
7. Ulangi menggunakan Material jenis **drum kabel**.
8. Perhatikan muncul validasi bahwa Drum ID wajib diisi, tetapi tidak tersedia field untuk memasukkan Drum ID.
9. Periksa tabel **Saldo Warehouse** dan **Aktivitas buku transaksi**.
10. Perhatikan Qty seperti `2312279.000`, `2313.000`, `100.000`, dan nilai lainnya masih menampilkan digit desimal `.000` serta belum menggunakan format pemisah ribuan yang mudah dibaca.
11. Pada bagian **Terbitkan Surat Jalan**, buat pengiriman Material.
12. Perhatikan setelah Surat Jalan diterbitkan user harus berpindah dari halaman aplikasi dan perlu kembali/back untuk melanjutkan pekerjaan.
13. Buat atau gunakan Surat Jalan/Transit yang masih terbuka menuju Warehouse lain.
14. Login/buka Warehouse tujuan.
15. Perhatikan pada bagian **Penerimaan stok** belum terdapat pilihan untuk menerima pengiriman terbuka tersebut dan mencatat jumlah aktual yang diterima.

## Hasil aktual

> Halaman Operasional Material saat ini menampilkan beberapa fungsi dalam satu halaman panjang:
>
> * Penerimaan stok;
> * Pengeluaran stok;
> * Split drum;
> * Penerbitan Surat Jalan;
> * Saldo Warehouse;
> * Drum tersedia;
> * Aktivitas buku transaksi.
>
> Layout masih berupa form dan tabel yang tersusun vertikal tanpa hierarchy/card yang konsisten dengan design language baru.
>
> Beberapa masalah fungsional yang ditemukan:
>
> **1. Material Serial Number**
>
> Sistem mempunyai rule bahwa Material ber-SN wajib mempunyai Serial Number, tetapi form penerimaan/pengeluaran tidak menyediakan input atau selector Serial Number.
>
> Akibatnya transaksi tidak dapat diselesaikan.
>
> **2. Material Drum**
>
> Sistem mempunyai rule bahwa drum kabel wajib mempunyai Drum ID, tetapi form transaksi tidak menyediakan input/selector Drum ID.
>
> Akibatnya transaksi juga tidak dapat diselesaikan.
>
> **3. Format Qty**
>
> Nilai Qty ditampilkan seperti:
>
> `2312279.000`
>
> `2313.000`
>
> `100.000`
>
> sehingga:
>
> * `.000` selalu terlihat meskipun Qty merupakan bilangan bulat;
> * tidak terdapat grouping setiap tiga digit;
> * angka besar lebih sulit dibaca.
>
> **4. Penerbitan Surat Jalan**
>
> Setelah Surat Jalan diterbitkan, user tidak mempunyai flow nyaman untuk tetap berada di halaman aplikasi. User perlu kembali/back untuk melanjutkan pekerjaan.
>
> **5. Penerimaan pengiriman Warehouse**
>
> Jika terdapat Surat Jalan/Transit terbuka menuju Warehouse tujuan, belum tersedia action pada halaman penerimaan stok untuk memilih pengiriman tersebut dan melakukan proses penerimaan berdasarkan Qty yang dikirim dan Qty aktual yang diterima.

## Hasil yang diharapkan

> Halaman **Operasional Material** mengikuti satu design language yang sama dengan Command Center, Portfolio, Project, Mitra, User, Master Data, dan Penugasan Warehouse.
>
> Perubahan harus tetap mempertahankan prinsip existing bahwa saldo Warehouse dibentuk dari **buku transaksi append-only**, bukan diedit secara langsung.

### 1. Struktur dan bahasa design

> Pecah fungsi utama menjadi section/card yang jelas dan compact.
>
> Contoh:
>
> ```text
> WAREHOUSE
>
> Operasional Material
> Catat seluruh pergerakan Material melalui buku transaksi.
>
> ┌────────────────────────────┬────────────────────────────┐
> │ Penerimaan Stok            │ Pengeluaran Stok           │
> │                            │                            │
> │ Warehouse                  │ Warehouse                  │
> │ Material                   │ Material                   │
> │ Qty                        │ Qty                        │
> │ Identifier conditional     │ Identifier conditional     │
> │ Alasan                     │ Alasan                     │
> │                            │                            │
> │ [Catat penerimaan]         │ [Catat pengeluaran]        │
> └────────────────────────────┴────────────────────────────┘
>
> ┌─────────────────────────────────────────────────────────┐
> │ Pengiriman masuk / Transit terbuka                      │
> └─────────────────────────────────────────────────────────┘
>
> ┌─────────────────────────────────────────────────────────┐
> │ Split Drum                                              │
> └─────────────────────────────────────────────────────────┘
>
> ┌─────────────────────────────────────────────────────────┐
> │ Terbitkan Surat Jalan                                   │
> └─────────────────────────────────────────────────────────┘
>
> ┌─────────────────────────────────────────────────────────┐
> │ Saldo Warehouse                                         │
> └─────────────────────────────────────────────────────────┘
>
> ┌─────────────────────────────────────────────────────────┐
> │ Aktivitas buku transaksi                                │
> └─────────────────────────────────────────────────────────┘
> ```
>
> Layout final boleh menggunakan grid lain selama lebih compact, responsive, dan konsisten dengan shared design system.

### 2. Dropdown Warehouse dan Material

> Selector Warehouse dan Material menggunakan shared select component dari QC sebelumnya.
>
> Untuk dataset yang dapat bertambah, gunakan reusable searchable select.
>
> **Warehouse** dapat dicari berdasarkan:
>
> * nama;
> * kode.
>
> **Material** dapat dicari berdasarkan:
>
> * nama;
> * kode.
>
> Hanya data authorized dan aktif yang boleh ditampilkan sesuai business logic existing.
>
> Jangan membuat implementation dropdown baru khusus halaman ini.

### 3. Material biasa

> Untuk Material biasa, flow tetap sederhana:
>
> ```text
> Warehouse
> Material
> Qty
> Alasan
> ```
>
> Tidak perlu menampilkan field Serial Number atau Drum ID jika Material tidak membutuhkannya.

### 4. Material ber-Serial Number

> Jika Material yang dipilih mempunyai jenis/flag **ber-SN**, form harus berubah secara dinamis dan menyediakan field Serial Number.
>
> Pada **penerimaan stok**:
>
> ```text
> Material
> [ ODP / perangkat ber-SN ]
>
> Serial Number
> [                              ]
> ```
>
> Serial Number wajib diisi sebelum penerimaan dapat disimpan.
>
> Jika business rule existing menetapkan Material ber-SN mempunyai `Qty = 1`, pertahankan rule tersebut.
>
> Jangan menghapus validasi SN; perbaiki form agar user mempunyai tempat untuk memenuhi validasi tersebut.
>
> Serial Number yang diterima harus tersimpan melalui mekanisme inventory existing dan dapat ditelusuri pada histori transaksi.

### 5. Pengeluaran Material ber-SN

> Pada **pengeluaran stok**, Serial Number sebaiknya tidak dimasukkan sebagai free text apabila sistem sudah mengetahui daftar SN yang tersedia di Warehouse.
>
> Gunakan selector seperti:
>
> ```text
> Serial Number
> [ Cari Serial Number tersedia                     ▾ ]
> ```
>
> Hanya SN yang:
>
> * berada pada Warehouse terpilih;
> * masih tersedia;
> * sesuai Material yang dipilih;
> * authorized untuk transaksi;
>
> yang boleh dipilih.
>
> Setelah SN dikeluarkan, SN tersebut tidak boleh tetap dianggap tersedia di Warehouse asal.
>
> Jangan mengubah model stock ownership existing; reuse logic inventory yang sudah tersedia.

### 6. Material Drum / Drum ID

> Jika Material yang dipilih merupakan **drum kabel**, form harus menyediakan Drum ID.

#### Penerimaan drum

> Contoh:
>
> ```text
> Material
> [ Kabel 24C ]
>
> Drum ID
> [ DRM-XXXXXXXX ]
>
> Qty / panjang
> [              ]
> ```
>
> Drum ID wajib diisi sesuai validation existing.
>
> Drum ID harus unik sesuai aturan inventory existing.

#### Pengeluaran drum

> Pada pengeluaran, gunakan selector Drum yang memang tersedia pada Warehouse:
>
> ```text
> Drum
> [ Cari Drum tersedia                              ▾ ]
> ```
>
> Option dapat menampilkan informasi yang membantu, misalnya:
>
> ```text
> DRM-00123 · Kabel 24C · tersedia 1.250 m
> ```
>
> Jangan menampilkan Drum dari Warehouse lain atau Drum yang sudah tidak tersedia.

### 7. Conditional form

> Form harus menyesuaikan Material yang dipilih:
>
> ```text
> Material biasa
>      ↓
> Qty + Alasan
>
> Material ber-SN
>      ↓
> Serial Number + Alasan
>
> Material drum
>      ↓
> Drum ID/Drum + Qty + Alasan
> ```
>
> Jangan selalu menampilkan seluruh field sekaligus karena akan membuat form membingungkan.

### 8. Error dan validation state

> Pesan seperti:
>
> `Serial Number wajib diisi untuk material ber-SN.`
>
> atau:
>
> `Drum ID wajib diisi untuk material drum kabel.`
>
> harus muncul dekat field yang bermasalah.
>
> Jangan hanya menampilkan error global tanpa menyediakan field yang dapat digunakan untuk memperbaikinya.
>
> Setelah user memperbaiki input, error state harus dapat hilang secara normal.

### 9. Format Qty

> Tampilan Qty harus mengikuti format angka yang mudah dibaca tanpa mengubah nilai numerik yang tersimpan pada database.

#### Bilangan bulat

> Nilai:
>
> ```text
> 2312279.000
> ```
>
> apabila secara semantik merupakan bilangan bulat, tampilkan sebagai:
>
> ```text
> 2.312.279
> ```
>
> Contoh lain:
>
> ```text
> 2313.000  → 2.313
> 3213.000  → 3.213
> 100.000   → 100
> ```
>
> Gunakan locale formatting yang konsisten, misalnya pola `id-ID`, jika sesuai dengan helper formatting project.

#### Nilai desimal

> Jangan membuang precision jika Qty memang mempunyai nilai desimal yang valid.
>
> Contoh:
>
> ```text
> stored: 1250.5
> display: 1.250,5
> ```
>
> Prinsipnya:
>
> * trailing zero yang tidak diperlukan tidak ditampilkan;
> * digit ribuan dikelompokkan;
> * precision sebenarnya tetap dipertahankan;
> * jangan mengubah nilai database hanya untuk kebutuhan display.

### 10. Shared quantity formatter

> Jangan melakukan format angka berbeda-beda di setiap tabel.
>
> Buat/reuse helper formatter yang dapat digunakan pada:
>
> * Saldo Warehouse;
> * Aktivitas buku transaksi;
> * Qty Surat Jalan;
> * Qty Transit;
> * Qty penerimaan;
> * Qty pengeluaran;
> * Drum;
> * halaman lain yang menampilkan quantity.
>
> Format harus konsisten di seluruh aplikasi.

### 11. Saldo Warehouse

> Tabel **Saldo Warehouse** tetap dipertahankan tetapi menggunakan card/table style yang sama dengan halaman lain.
>
> Contoh:
>
> ```text
> Saldo Warehouse
>
> Warehouse   Material         Saldo       Unit
> ──────────────────────────────────────────────
> bb — test   tiang 7m         2.312.279   Btg
> bb — test   tiang 9m         2.313       Btg
> bb — test   ODP              100         Pcs
> ```
>
> Gunakan alignment numerik yang jelas, idealnya angka Qty rata kanan jika pattern table project mendukung.

### 12. Aktivitas buku transaksi

> Tabel transaksi dibuat lebih readable dan tetap mencerminkan ledger append-only.
>
> Kolom utama tetap dapat mencakup:
>
> * Waktu;
> * Warehouse;
> * Material;
> * Delta;
> * Jenis;
> * Alasan.
>
> Delta positif dan negatif harus mudah dibaca.
>
> Contoh:
>
> ```text
> +2.000 Pcs
> -2.000 Pcs
> ```
>
> Styling visual boleh membantu membedakan incoming/outgoing, tetapi nilai dan makna transaksi tidak boleh berubah.

### 13. Penerbitan Surat Jalan membuka tab baru

> Setelah user berhasil menerbitkan Surat Jalan, dokumen/halaman Surat Jalan harus dibuka pada **tab browser baru**.
>
> Tab aplikasi Operasional Material tetap terbuka pada state sebelumnya.
>
> Flow:
>
> ```text
> Isi Surat Jalan
>       ↓
> Klik "Terbitkan Surat Jalan"
>       ↓
> backend berhasil membuat Surat Jalan
>       ↓
> tab baru → Surat Jalan
>
> tab lama → tetap Operasional Material
> ```
>
> Dengan demikian user dapat:
>
> * melihat/print Surat Jalan pada tab baru;
> * kembali ke aplikasi cukup dengan berpindah tab;
> * tidak perlu browser back.
>
> Jangan membuka tab baru sebelum server memastikan Surat Jalan berhasil dibuat.
>
> Gunakan mekanisme browser yang aman seperti `noopener`/equivalent jika relevan dengan framework.

### 14. State form setelah Surat Jalan diterbitkan

> Setelah create berhasil:
>
> * tampilkan feedback sukses pada aplikasi;
> * reset field sesuai behavior existing yang aman;
> * jangan melakukan create ulang hanya karena user refresh tab Surat Jalan;
> * hindari duplicate submission.
>
> Business logic nomor Surat Jalan tidak berubah.

### 15. Pengiriman masuk / Transit terbuka

> Jika terdapat pengiriman/Surat Jalan yang statusnya masih terbuka dan **Warehouse tujuan adalah Warehouse yang sedang dioperasikan user**, halaman Penerimaan Stok harus menyediakan pilihan untuk menerima pengiriman tersebut.
>
> Tambahkan section seperti:
>
> ```text
> Pengiriman masuk
>
> 2 pengiriman menunggu penerimaan
>
> ┌────────────────────────────────────────────────────────┐
> │ SJ-2608-0002                                          │
> │ Dari: Warehouse A                                     │
> │ Ke: bb — test                                         │
> │ Tanggal: 20 Aug 2026                                  │
> │                                           [Terima]    │
> └────────────────────────────────────────────────────────┘
> ```

### 16. Action "Terima Pengiriman"

> Ketika user memilih **Terima Pengiriman**, tampilkan detail item yang dikirim.
>
> Contoh:
>
> ```text
> Terima Pengiriman
>
> Surat Jalan: SJ-2608-0002
> Asal: Warehouse A
> Tujuan: bb — test
>
> Material          Qty dikirim     Qty diterima
> ───────────────────────────────────────────────
> Tiang 7m          100 Btg         [ 100 ]
> ODP                20 Pcs          [ 20  ]
>
> Catatan penerimaan
> [                                      ]
>
>                         [Batal] [Terima Pengiriman]
> ```
>
> **Qty diterima otomatis terisi dengan Qty dikirim sebagai default**, tetapi user dapat mengubahnya sesuai jumlah fisik yang benar-benar diterima apabila business rule mengizinkan discrepancy.

### 17. Qty dikirim vs Qty diterima

> Sistem harus menyimpan perbedaan antara:
>
> ```text
> Qty dikirim
> ```
>
> dan:
>
> ```text
> Qty diterima
> ```
>
> Contoh:
>
> ```text
> Dikirim   : 100
> Diterima  : 98
> Selisih   : -2
> ```
>
> Perbedaan tidak boleh diam-diam dianggap sama.
>
> Jika terdapat selisih, tampilkan informasi yang jelas dan simpan sesuai model audit/Transit existing.
>
> Jangan menentukan secara sembarang apakah selisih otomatis menjadi loss/damage/claim. Gunakan business rule existing; jika belum ada, cukup simpan discrepancy secara eksplisit untuk tindak lanjut.

### 18. Validasi penerimaan pengiriman

> Minimal:
>
> * Qty diterima tidak boleh negatif.
> * Qty diterima tidak boleh melebihi Qty yang masih terbuka/tersisa kecuali business rule explicitly memperbolehkannya.
> * Surat Jalan yang sudah selesai tidak dapat diterima dua kali.
> * Request ulang/double click tidak boleh menghasilkan duplicate receipt.
> * Hanya Warehouse tujuan yang dapat menerima.
> * Hanya user/operator authorized yang dapat menjalankan action.
>
> Implementasi harus aman terhadap duplicate submission dan concurrent receive.

### 19. Partial receipt

> Apabila business logic existing mendukung penerimaan parsial, status pengiriman harus mencerminkan sisa yang belum diterima.
>
> Contoh:
>
> ```text
> Qty dikirim   : 100
> Sudah diterima: 60
> Sisa terbuka  : 40
> ```
>
> Pengiriman tetap tampil sebagai terbuka sampai jumlah/status memenuhi aturan penyelesaian.
>
> Jika sistem saat ini tidak mendukung partial receipt, jangan memaksakan perubahan domain besar tanpa pemeriksaan schema dan state machine terlebih dahulu. Minimal desain dan implementation harus tidak menghilangkan informasi Qty aktual yang diterima.

### 20. Penerimaan Material ber-SN dari pengiriman

> Jika item Surat Jalan merupakan Material ber-SN, flow penerimaan harus menggunakan identifier yang ikut dikirim.
>
> User tidak boleh hanya memasukkan angka Qty lalu kehilangan traceability SN.
>
> Tampilkan identifier yang dikirim dan izinkan user mengonfirmasi identifier yang benar-benar diterima sesuai business rule.

### 21. Penerimaan Drum dari pengiriman

> Jika item berupa drum, pengiriman dan penerimaan harus mempertahankan **Drum ID**.
>
> Drum yang diterima harus berpindah secara traceable dari Warehouse asal ke Warehouse tujuan melalui workflow Transit existing.
>
> Jangan membuat Drum ID baru secara otomatis pada Warehouse tujuan untuk barang yang sebenarnya merupakan drum yang sama.

### 22. Jangan double-count stock

> Flow `Terima Pengiriman` harus terintegrasi dengan buku transaksi existing.
>
> Jangan membuat:
>
> ```text
> receipt manual
> +
> receipt transfer
> ```
>
> untuk pengiriman yang sama sehingga saldo terhitung dua kali.
>
> Satu penerimaan pengiriman harus menghasilkan movement/receipt sesuai transaction model existing hanya sekali.

### 23. Status pengiriman setelah diterima

> Setelah transaksi penerimaan berhasil:
>
> * saldo Warehouse tujuan diperbarui melalui buku transaksi;
> * status Transit/Surat Jalan diperbarui sesuai state machine existing;
> * item yang sudah selesai tidak lagi muncul sebagai pengiriman terbuka;
> * histori penerimaan tetap dapat ditelusuri;
> * user mendapat success feedback.

### 24. Search/filter pengiriman masuk

> Jika pengiriman terbuka dapat banyak, sediakan layout compact dan pencarian/filter sederhana jika shared component sudah tersedia.
>
> Minimal informasi yang membantu:
>
> * nomor Surat Jalan;
> * Warehouse asal;
> * tanggal;
> * jumlah item;
> * status.
>
> Jangan menampilkan pengiriman yang bukan tujuan Warehouse user.

### 25. Responsive behavior

> Pada desktop:
>
> * Penerimaan dan Pengeluaran dapat menggunakan grid 2 kolom bila ruang memungkinkan;
> * section Surat Jalan, pengiriman masuk, saldo, dan transaksi menggunakan lebar sesuai kebutuhan;
> * tabel mudah dibaca.
>
> Pada tablet/mobile:
>
> * form berubah menjadi satu kolom;
> * dropdown menggunakan full width;
> * tabel menggunakan responsive strategy existing;
> * tidak ada horizontal page scrolling yang tidak terkendali.
>
> Semua modal/popover/select harus tetap berada dalam viewport.

### 26. Ketentuan implementasi

> Reuse shared design/component dari QC sebelumnya:
>
> * page header;
> * content container;
> * card;
> * form control;
> * input;
> * searchable select;
> * standard select;
> * status badge;
> * button variants;
> * alert/toast;
> * modal/dialog;
> * data table;
> * number formatter.
>
> Jangan membuat design system baru khusus Warehouse.
>
> Jangan mengubah prinsip ledger append-only atau melakukan direct edit terhadap saldo.
>
> Perubahan tidak boleh merusak:
>
> * permission;
> * authorization;
> * Warehouse assignment;
> * ownership;
> * stock ledger;
> * stock calculation;
> * Serial Number traceability;
> * Drum traceability;
> * Surat Jalan numbering;
> * Transit state machine;
> * historical transactions;
> * audit/activity logging;
> * existing stock data.
>
> Jangan membuat dummy/fake data.
>
> Sebelum implementasi, inspect:
>
> 1. model Material dan jenis tracking-nya;
> 2. model Serial Number;
> 3. model Drum;
> 4. transaction ledger;
> 5. Surat Jalan dan item Surat Jalan;
> 6. Transit state machine;
> 7. Warehouse assignment/authorization;
> 8. formatter number existing;
> 9. shared searchable-select;
> 10. shared UI component hasil QC sebelumnya.

## Dampak dan catatan

> Temuan pada halaman ini mempunyai dampak operasional karena Material dengan tracking **Serial Number** atau **Drum ID** pada praktiknya tidak dapat diproses melalui form yang tersedia meskipun backend sudah menerapkan validation identifier tersebut.
>
> Ini membuat validation dan UI tidak sinkron:
>
> ```text
> Backend meminta SN / Drum ID
>             ↓
> UI tidak menyediakan field
>             ↓
> transaksi tidak dapat diselesaikan
> ```
>
> Format Qty juga berpotensi menyulitkan pembacaan nilai stok besar. Perubahan formatting harus hanya mempengaruhi **presentation**, bukan precision atau nilai database.
>
> Membuka Surat Jalan pada tab baru akan mengurangi navigasi bolak-balik dan mempertahankan context Operasional Material.
>
> Penambahan **Terima Pengiriman** sangat penting agar perpindahan stok antar Warehouse mempunyai flow end-to-end:
>
> ```text
> Warehouse asal
>      ↓
> Surat Jalan diterbitkan
>      ↓
> Transit terbuka
>      ↓
> Warehouse tujuan
>      ↓
> Terima Pengiriman
>      ↓
> Qty dikirim vs Qty diterima
>      ↓
> Buku transaksi
>      ↓
> Saldo Warehouse tujuan
> ```
>
> Acceptance utama:
>
> * Halaman Operasional Material menggunakan design language yang sama dengan modul lain.
> * Form dibuat lebih compact dan responsive.
> * Warehouse dan Material menggunakan reusable select/searchable select.
> * Material biasa tetap dapat diterima dan dikeluarkan.
> * Material ber-SN mempunyai field/selector Serial Number.
> * Material drum mempunyai field/selector Drum ID.
> * Validation SN dan Drum dapat dipenuhi melalui UI.
> * Pengeluaran SN hanya memilih SN yang tersedia pada Warehouse.
> * Pengeluaran Drum hanya memilih Drum yang tersedia pada Warehouse.
> * Traceability SN dan Drum tidak hilang.
> * Qty integer tidak lagi menampilkan trailing `.000`.
> * Qty menggunakan pemisah ribuan yang mudah dibaca.
> * Qty desimal tetap mempertahankan precision sebenarnya.
> * Formatting hanya mengubah display, bukan stored value.
> * Saldo Warehouse menggunakan formatter yang konsisten.
> * Aktivitas buku transaksi menggunakan formatter yang konsisten.
> * Surat Jalan yang berhasil diterbitkan terbuka pada tab baru.
> * Tab Operasional Material tetap tersedia.
> * Tidak terjadi duplicate Surat Jalan akibat perubahan navigation.
> * Pengiriman/Transit terbuka menuju Warehouse tampil sebagai pengiriman masuk.
> * Tersedia action `Terima Pengiriman`.
> * Qty diterima default mengikuti Qty dikirim.
> * User dapat mencatat Qty aktual diterima sesuai business rule.
> * Qty dikirim dan Qty diterima tetap dapat dibandingkan.
> * Selisih penerimaan tidak disembunyikan.
> * Pengiriman tidak dapat diterima dua kali.
> * Hanya Warehouse tujuan yang dapat menerima.
> * Hanya User authorized yang dapat menerima.
> * Receipt tidak menyebabkan double-count saldo.
> * Material ber-SN pada transfer tetap mempertahankan SN.
> * Material drum pada transfer tetap mempertahankan Drum ID.
> * Saldo tetap dibentuk dari transaction ledger append-only.
> * Historical transaction tidak berubah.
> * Permission dan authorization tidak berubah.
> * Tidak ada dummy/fake data.
> * Tidak ada horizontal scrolling yang tidak terkendali.
> * Tidak ada JavaScript/runtime error baru.

## Tambahan — Fulfillment Request Material yang sudah Approved

> Jika terdapat **Request Material** yang sudah berstatus `approved`, Warehouse source yang ditentukan pada Request tersebut harus dapat melihat permintaan tersebut pada halaman **Operasional Material** dan memprosesnya menjadi pengiriman.
>
> Request Material yang sudah approved tidak seharusnya mengharuskan petugas Warehouse membuat Surat Jalan secara manual dari awal tanpa referensi terhadap Request Material.

### 27. Request Material Approved muncul di Warehouse Source

> Tambahkan section pada halaman Operasional Material, misalnya:
>
> ```text
> Request Material siap diproses
>
> 2 Request menunggu pengiriman
>
> ┌──────────────────────────────────────────────────────────┐
> │ RM-2608-0005                              [Approved]     │
> │ Project: Project ABC                                    │
> │ Warehouse source: bb — test                             │
> │ Tujuan: Warehouse B / lokasi tujuan                     │
> │ Diajukan: 20 Aug 2026                                   │
> │                                                         │
> │ 3 item Material                                         │
> │                                           [Proses]      │
> └──────────────────────────────────────────────────────────┘
> ```
>
> Hanya Request Material yang:
>
> * sudah `approved`;
> * belum selesai dipenuhi;
> * mempunyai Warehouse source yang sesuai;
> * berada dalam authorization/scope user;
>
> yang boleh ditampilkan.
>
> Request yang masih:
>
> * `diajukan`;
> * `ditolak`;
> * `dibatalkan`;
> * atau sudah selesai;
>
> tidak boleh muncul sebagai Request siap dikirim.

### 28. Proses Request Material

> Ketika user memilih **Proses** pada Request Material approved, tampilkan detail Request dan item yang harus disiapkan.
>
> Contoh:
>
> ```text
> Proses Request Material
>
> Request: RM-2608-0005
> Project: Project ABC
> Warehouse source: bb — test
> Tujuan: Warehouse B
>
> Material                Qty Request       Qty Kirim
> ─────────────────────────────────────────────────────
> Tiang 7m                100 Btg           [ 100 ]
> ODP                      20 Pcs            [ 20  ]
> Kabel 24C                1.000 m           [ 900 ]
>
> Catatan pengiriman
> [                                             ]
>
>                         [Batal] [Terbitkan Surat Jalan]
> ```
>
> Kolom **Qty Kirim** secara default dapat terisi sama dengan **Qty Request**, tetapi user Warehouse harus dapat menyesuaikan jumlah aktual yang akan dikirim sesuai stok dan kondisi operasional.

### 29. Qty Request vs Qty Kirim

> Sistem harus mempertahankan dua nilai yang berbeda:
>
> ```text
> Qty Request
> ```
>
> dan:
>
> ```text
> Qty Kirim
> ```
>
> Contoh:
>
> ```text
> Request : 1.000 meter
> Kirim   :   900 meter
> Selisih :   100 meter belum terpenuhi
> ```
>
> Qty Kirim tidak boleh secara otomatis dianggap selalu sama dengan Qty Request.
>
> User harus dapat melihat dengan jelas:
>
> * jumlah yang diminta;
> * jumlah yang akan dikirim;
> * jumlah yang sudah pernah dikirim jika fulfillment dilakukan bertahap;
> * sisa Request yang belum terpenuhi.

### 30. Validasi Qty Kirim

> Minimal validasi:
>
> * Qty Kirim tidak boleh negatif.
> * Qty Kirim tidak boleh melebihi sisa Qty Request kecuali business rule secara eksplisit mengizinkannya.
> * Qty Kirim tidak boleh melebihi stok tersedia pada Warehouse source.
> * Material yang tidak tersedia harus mendapatkan feedback yang jelas.
> * Submit ganda tidak boleh menghasilkan duplicate fulfillment atau duplicate Surat Jalan.
>
> Contoh:
>
> ```text
> Qty Request : 100
> Sudah dikirim: 60
> Sisa Request : 40
>
> Qty Kirim maksimal: 40
> ```

### 31. Partial fulfillment

> Request Material harus dapat mendukung pemenuhan sebagian apabila business logic existing memungkinkan.
>
> Contoh:
>
> ```text
> Request Material RM-2608-0005
>
> Requested     100
> Shipment #1    60
> Remaining      40
> ```
>
> Status dapat tetap terbuka/partially fulfilled sampai seluruh Qty Request terpenuhi.
>
> Jika sistem saat ini belum mempunyai status partial fulfillment, inspect terlebih dahulu state machine dan schema Request Material sebelum menambah status baru.
>
> Jangan menandai Request sebagai selesai hanya karena satu Surat Jalan sudah diterbitkan apabila Qty yang dikirim masih kurang dari Qty Request.

### 32. Material ber-SN pada Request

> Jika item Request Material adalah Material ber-Serial Number, proses fulfillment harus meminta user memilih SN aktual yang akan dikirim dari Warehouse source.
>
> Contoh:
>
> ```text
> Material: ONU
> Qty Request: 2
>
> Serial Number yang dikirim:
> [ SN001234 ]
> [ SN001235 ]
> ```
>
> Hanya SN yang:
>
> * tersedia di Warehouse source;
> * sesuai Material;
> * belum dialokasikan/dikirim;
>
> yang boleh dipilih.
>
> Qty Kirim harus konsisten dengan jumlah Serial Number yang dipilih.

### 33. Material Drum pada Request

> Jika item Request Material merupakan drum kabel, user harus memilih **Drum ID** yang akan dikirim.
>
> Contoh:
>
> ```text
> Material: Kabel FO 24C
> Qty Request: 1.000 m
>
> Drum
> [ DRM-00123 · tersedia 1.250 m                  ▾ ]
>
> Qty Kirim
> [ 1.000 ]
> ```
>
> Traceability Drum ID harus tetap dipertahankan hingga Warehouse/lokasi tujuan menerima pengiriman.

### 34. Terbitkan Surat Jalan dari Request Material

> Setelah user mengisi Qty Kirim dan identifier yang dibutuhkan, action:
>
> ```text
> Terbitkan Surat Jalan
> ```
>
> harus membuat Surat Jalan berdasarkan **Request Material tersebut**.
>
> Surat Jalan harus menyimpan referensi ke Request Material sumber.
>
> Secara konseptual:
>
> ```text
> Request Material
>       ↓
> Fulfillment
>       ↓
> Surat Jalan
>       ↓
> Transit
>       ↓
> Penerimaan
> ```
>
> Jangan membuat Surat Jalan yang kehilangan hubungan dengan Request Material asal.

### 35. Prefill Surat Jalan

> Data yang sudah tersedia dari Request Material sebaiknya otomatis digunakan untuk mengisi form Surat Jalan, misalnya:
>
> * Warehouse source;
> * Warehouse/lokasi tujuan;
> * Material;
> * Qty Kirim;
> * Project;
> * reference Request Material;
> * identifier SN/Drum bila relevan.
>
> User tidak perlu menginput ulang data yang sudah diketahui sistem.
>
> Field tambahan seperti:
>
> * tanggal;
> * pengirim;
> * sopir;
> * plat nomor;
>
> tetap dapat diisi sesuai flow Surat Jalan existing.

### 36. Surat Jalan terbuka di tab baru

> Behavior yang sudah disebut sebelumnya tetap berlaku:
>
> setelah Surat Jalan dari Request Material berhasil diterbitkan, dokumen Surat Jalan dibuka pada **tab baru**, sementara tab Operasional Material tetap terbuka.

### 37. Status Request setelah Surat Jalan dibuat

> Setelah Surat Jalan berhasil dibuat:
>
> sistem harus memperbarui progress fulfillment Request Material berdasarkan Qty yang benar-benar dikirim.
>
> Contoh:
>
> ```text
> Qty Request      100
> Qty dikirim       60
> Sisa              40
> ```
>
> Request tidak boleh langsung dianggap selesai jika masih mempunyai Qty tersisa.
>
> Jika seluruh Qty sudah terpenuhi:
>
> ```text
> Qty Request      100
> Total dikirim    100
> Sisa               0
> ```
>
> Request dapat berubah menjadi fulfilled/completed sesuai state machine existing.

### 38. Jangan double-count pengeluaran

> Pengeluaran stok yang berasal dari fulfillment Request Material harus menggunakan transaction flow existing.
>
> Jangan sampai user:
>
> ```text
> proses Request
>      +
> catat pengeluaran manual
>      +
> Surat Jalan
> ```
>
> menghasilkan pengurangan stok lebih dari satu kali.
>
> Satu shipment harus menghasilkan movement ledger sesuai business rule hanya sekali.

### 39. Hubungan dengan pengiriman masuk

> Flow Request Material harus terhubung dengan flow **Terima Pengiriman** pada Warehouse tujuan:
>
> ```text
> Request Material approved
>         ↓
> Warehouse source proses
>         ↓
> Qty Request vs Qty Kirim
>         ↓
> Terbitkan Surat Jalan
>         ↓
> Transit terbuka
>         ↓
> Warehouse tujuan melihat Pengiriman Masuk
>         ↓
> Qty Kirim vs Qty Diterima
>         ↓
> Terima Pengiriman
>         ↓
> Buku transaksi + saldo tujuan
> ```
>
> Dengan demikian tiga quantity berbeda tetap dapat ditelusuri:
>
> ```text
> Qty Request
> Qty Kirim
> Qty Diterima
> ```
>
> Contoh:
>
> ```text
> Request   : 100
> Dikirim   : 95
> Diterima  : 93
>
> Sisa Request       : 5
> Selisih pengiriman : 2
> ```
>
> Jangan menggabungkan ketiga nilai tersebut menjadi satu field Qty karena masing-masing mewakili tahap proses yang berbeda.

### 40. Audit trail

> Histori harus dapat menunjukkan hubungan antara:
>
> * Request Material;
> * approval;
> * Warehouse source;
> * siapa yang memproses;
> * Qty Request;
> * Qty Kirim;
> * Surat Jalan;
> * Transit;
> * Qty Diterima;
> * waktu penerimaan;
> * discrepancy jika ada.
>
> Reuse audit/activity logging existing dan jangan membuat histori terpisah yang tidak terhubung dengan domain object.

## Tambahan Dampak dan Catatan

> Tanpa integrasi Request Material ke Warehouse source, proses setelah approval masih terputus.
>
> Kondisi yang tidak diinginkan:
>
> ```text
> Request dibuat
>      ↓
> Approved
>      ↓
> ??
>      ↓
> Warehouse membuat Surat Jalan manual
> ```
>
> Flow target:
>
> ```text
> Request Material
>      ↓
> Approval
>      ↓
> Warehouse source mendapat work queue
>      ↓
> Qty Request vs Qty Kirim
>      ↓
> Surat Jalan
>      ↓
> Transit
>      ↓
> Warehouse tujuan
>      ↓
> Qty Kirim vs Qty Diterima
>      ↓
> Receipt
> ```
>
> Flow ini membuat proses material dapat dilacak end-to-end dan mengurangi entry data manual yang berulang.

## Tambahan Acceptance Criteria

* [ ] Request Material berstatus approved muncul pada Warehouse source yang sesuai.
* [ ] Request yang belum approved tidak muncul sebagai siap diproses.
* [ ] Request yang sudah selesai tidak muncul sebagai pekerjaan terbuka.
* [ ] User Warehouse dapat membuka detail Request.
* [ ] Qty Request ditampilkan secara read-only sebagai referensi.
* [ ] Qty Kirim dapat diisi oleh Warehouse source.
* [ ] Qty Kirim default dapat mengikuti Qty Request.
* [ ] Qty Kirim tidak boleh melebihi sisa Request tanpa business rule yang mengizinkan.
* [ ] Qty Kirim tidak boleh melebihi stok tersedia.
* [ ] Partial fulfillment dapat direkam jika domain mendukungnya.
* [ ] Sisa Request dapat diketahui setelah partial shipment.
* [ ] Material ber-SN mengharuskan pemilihan SN yang dikirim.
* [ ] Material Drum mengharuskan pemilihan Drum ID.
* [ ] Surat Jalan dapat diterbitkan langsung dari Request Material.
* [ ] Surat Jalan menyimpan referensi Request Material.
* [ ] Field Surat Jalan yang sudah diketahui diprefill dari Request.
* [ ] Surat Jalan hasil create terbuka pada tab baru.
* [ ] Request tidak dianggap selesai jika Qty masih tersisa.
* [ ] Total fulfillment tidak boleh melebihi Qty Request tanpa rule eksplisit.
* [ ] Pengeluaran stok tidak terhitung dua kali.
* [ ] Transit hasil Surat Jalan muncul pada Warehouse tujuan.
* [ ] Warehouse tujuan dapat melihat Qty Kirim dan mengisi Qty Diterima.
* [ ] Qty Request, Qty Kirim, dan Qty Diterima tersimpan sebagai informasi yang dapat ditelusuri.
* [ ] Audit trail menghubungkan Request Material → Surat Jalan → Transit → Receipt.
* [ ] Permission dan authorization Warehouse source tetap diterapkan.
* [ ] Tidak ada duplicate fulfillment akibat double-click/retry.


## Tambahan — Surat Jalan harus mendukung lebih dari satu item

> Pada bagian **Terbitkan Surat Jalan**, saat ini satu Surat Jalan hanya dapat memuat **1 item Material**. Walaupun terdapat konsep/tombol `Tambah item`, user belum dapat membentuk satu Surat Jalan dengan beberapa item Material.
>
> Surat Jalan harus mendukung **multi-item shipment**, karena satu pengiriman Warehouse dapat membawa beberapa jenis Material dalam satu kendaraan dan satu dokumen.

### 41. Kondisi aktual multi-item Surat Jalan

> Flow saat ini secara praktis:
>
> ```text
> Surat Jalan
>
> Material
> [ Tiang 7m ]
>
> Qty
> [ 100 ]
>
> [Tambah item]
>
> [Terbitkan Surat Jalan]
> ```
>
> tetapi user tidak dapat menghasilkan daftar item seperti:
>
> ```text
> 1. Tiang 7m       100 Btg
> 2. ODP             20 Pcs
> 3. Splitter 1:8    10 Pcs
> ```
>
> Akibatnya satu Surat Jalan hanya dapat berisi satu Material.
>
> Untuk mengirim beberapa Material, user harus membuat beberapa Surat Jalan terpisah, meskipun:
>
> * Warehouse asal sama;
> * Warehouse tujuan sama;
> * tanggal pengiriman sama;
> * pengirim sama;
> * sopir sama;
> * kendaraan sama.

### 42. Surat Jalan mendukung multiple items

> Form **Terbitkan Surat Jalan** harus memungkinkan user menambahkan lebih dari satu item Material sebelum dokumen diterbitkan.
>
> Contoh:
>
> ```text
> Terbitkan Surat Jalan
>
> Warehouse asal
> [ bb — test                                      ▾ ]
>
> Warehouse tujuan
> [ bb1 — testwhmitra                              ▾ ]
>
> Tanggal
> [ 20/08/2026 ]
>
> Pengirim
> [ Ahmad ]
>
> Sopir
> [ Budi ]
>
> Plat nomor
> [ N 1234 AB ]
>
> Item Surat Jalan
>
> ┌───────────────────────────────────────────────────────────┐
> │ Material             Qty              Aksi                │
> ├───────────────────────────────────────────────────────────┤
> │ Tiang 7m             100 Btg          [Hapus]             │
> │ ODP                   20 Pcs           [Hapus]             │
> │ Splitter 1:8          10 Pcs           [Hapus]             │
> └───────────────────────────────────────────────────────────┘
>
> Material
> [ Pilih Material                                  ▾ ]
>
> Qty
> [                                                ]
>
> [Tambah item]
>
>                              [Terbitkan Surat Jalan]
> ```

### 43. Behavior tombol Tambah item

> Tombol:
>
> ```text
> Tambah item
> ```
>
> harus:
>
> 1. membaca Material yang dipilih;
> 2. membaca Qty;
> 3. menjalankan validation;
> 4. menambahkan Material tersebut ke daftar sementara item Surat Jalan;
> 5. mengosongkan input Material/Qty untuk penambahan berikutnya;
> 6. **tidak langsung menerbitkan Surat Jalan**.
>
> Contoh:
>
> ```text
> Pilih ODP
> Qty 20
>      ↓
> Tambah item
>      ↓
> ODP · 20 Pcs masuk ke daftar
>      ↓
> form siap untuk item berikutnya
> ```

### 44. Item dapat dihapus sebelum diterbitkan

> Setiap item yang belum diterbitkan harus mempunyai action:
>
> ```text
> Hapus
> ```
>
> agar user dapat memperbaiki kesalahan input sebelum Surat Jalan disimpan.
>
> Menghapus item pada tahap draft form tidak boleh membuat transaksi stock karena Surat Jalan belum diterbitkan.

### 45. Minimal satu item

> Surat Jalan tidak boleh diterbitkan tanpa item.
>
> Validation:
>
> ```text
> Minimal satu Material harus ditambahkan ke Surat Jalan.
> ```
>
> harus muncul jika user mencoba menerbitkan dokumen dalam kondisi daftar item kosong.

### 46. Validation setiap item

> Setiap item harus divalidasi secara independen.
>
> Minimal:
>
> * Material wajib dipilih.
> * Qty harus lebih besar dari `0`.
> * Qty tidak boleh melebihi stok yang tersedia.
> * Material harus tersedia pada Warehouse source.
> * Unit mengikuti Material.
> * User harus authorized terhadap Warehouse tersebut.

### 47. Material yang sama ditambahkan dua kali

> Tentukan behavior yang konsisten jika Material yang sama dimasukkan dua kali.
>
> Preferensi:
>
> ```text
> Tiang 7m · 50 Btg
> +
> Tiang 7m · 20 Btg
>        ↓
> Tiang 7m · 70 Btg
> ```
>
> atau blok duplikasi dengan pesan:
>
> ```text
> Material sudah terdapat pada Surat Jalan.
> Ubah Qty pada item yang sudah ada.
> ```
>
> Gunakan pendekatan yang paling sesuai dengan data model existing.
>
> Jangan menghasilkan dua row identik secara tidak sengaja apabila hal tersebut menyebabkan ambiguity pada fulfillment/receipt.

### 48. Material ber-Serial Number

> Multi-item Surat Jalan harus tetap mendukung Material ber-SN.
>
> Contoh:
>
> ```text
> ONU
> Qty: 2
>
> Serial Number:
> SN-00001
> SN-00002
> ```
>
> Item pada Surat Jalan harus mempertahankan identifier tersebut.
>
> Secara konseptual:
>
> ```text
> ONU · 2 Pcs
> ├── SN-00001
> └── SN-00002
> ```
>
> Jumlah SN yang dipilih harus konsisten dengan Qty item.

### 49. Material Drum

> Multi-item juga harus mendukung Drum ID.
>
> Contoh:
>
> ```text
> Kabel FO 24C
> Drum: DRM-00123
> Qty: 1.000 m
> ```
>
> Jika dalam satu pengiriman terdapat dua drum:
>
> ```text
> Kabel FO 24C
> ├── DRM-00123 · 1.000 m
> └── DRM-00124 ·   800 m
> ```
>
> traceability setiap Drum harus tetap dipertahankan.

### 50. Request Material dengan banyak item

> Requirement ini juga berlaku ketika Surat Jalan dibuat dari **Request Material approved**.
>
> Jika Request Material mempunyai:
>
> ```text
> Tiang 7m       Request 100
> ODP            Request 20
> Splitter 1:8   Request 10
> ```
>
> user Warehouse source harus dapat memproses seluruh item tersebut dalam **satu Surat Jalan**, selama memang dikirim dalam shipment yang sama.
>
> Contoh:
>
> ```text
> Request Material RM-2608-0005
>
> Material       Request    Sudah kirim    Qty Kirim
> ──────────────────────────────────────────────────
> Tiang 7m       100        0              [100]
> ODP             20        0              [ 20]
> Splitter        10        0              [ 10]
>
>                      [Terbitkan Surat Jalan]
> ```

### 51. Partial fulfillment per item

> Partial fulfillment harus dihitung **per item**, bukan hanya per Request.
>
> Contoh:
>
> ```text
> Request:
>
> Tiang 7m     100
> ODP           20
>
> Shipment #1:
>
> Tiang 7m      60
> ODP           20
>
> Remaining:
>
> Tiang 7m      40
> ODP            0
> ```
>
> Request belum dianggap fulfilled seluruhnya karena masih terdapat sisa Tiang 7m.

### 52. Satu Surat Jalan, satu header, banyak item

> Data header seperti:
>
> * Nomor Surat Jalan;
> * tanggal;
> * Warehouse asal;
> * Warehouse tujuan;
> * pengirim;
> * sopir;
> * plat nomor;
>
> hanya disimpan sekali sebagai header dokumen.
>
> Kemudian mempunyai collection item:
>
> ```text
> Surat Jalan
> ├── Item 1
> ├── Item 2
> ├── Item 3
> └── ...
> ```
>
> Jangan membuat satu header Surat Jalan baru untuk setiap Material.

### 53. Transaction atomicity

> Penerbitan multi-item harus diperlakukan sebagai satu operasi konsisten.
>
> Jika Surat Jalan mempunyai:
>
> ```text
> Item A
> Item B
> Item C
> ```
>
> kemudian Item C gagal validation/database operation, sistem tidak boleh meninggalkan kondisi:
>
> ```text
> Item A sudah keluar stock
> Item B sudah keluar stock
> Item C gagal
> Surat Jalan gagal/tidak lengkap
> ```
>
> Gunakan transaction handling existing sehingga create Surat Jalan dan stock movement terkait tetap konsisten.

### 54. Tidak boleh double-deduct stock

> Setiap item Surat Jalan hanya boleh menghasilkan pengurangan/movement stok **sekali** sesuai ledger model existing.
>
> Jangan terjadi:
>
> ```text
> Tambah item
>      ↓
> stok berkurang
>
> lalu
>
> Terbitkan Surat Jalan
>      ↓
> stok berkurang lagi
> ```
>
> `Tambah item` hanya membentuk draft item pada UI/state.
>
> Stock movement baru terjadi sesuai lifecycle Surat Jalan existing ketika dokumen benar-benar diterbitkan.

### 55. Detail Surat Jalan menampilkan seluruh item

> Setelah diterbitkan, halaman Detail Surat Jalan harus menampilkan seluruh item.
>
> Contoh:
>
> ```text
> SJ-2608-0010
>
> Material          Dikirim     Diterima    Retur    Sisa
> ───────────────────────────────────────────────────────
> Tiang 7m          100 Btg     100 Btg     0        0
> ODP                20 Pcs      20 Pcs     0        0
> Splitter 1:8       10 Pcs       8 Pcs     0        2
> ```

### 56. Penerimaan multi-item

> Flow **Terima Pengiriman** pada Warehouse tujuan juga harus membaca seluruh item Surat Jalan.
>
> Contoh:
>
> ```text
> SJ-2608-0010
>
> Material          Qty Kirim      Qty Diterima
> ──────────────────────────────────────────────
> Tiang 7m          100            [100]
> ODP                20            [ 20]
> Splitter 1:8       10            [  8]
>
>                        [Terima Pengiriman]
> ```
>
> Qty diterima dapat berbeda untuk masing-masing item.

### 57. Status per item dan dokumen

> Jika domain existing mendukung partial receipt, status dokumen harus mempertimbangkan keseluruhan item.
>
> Contoh:
>
> ```text
> Tiang 7m      selesai
> ODP           selesai
> Splitter      masih 2 transit
> ```
>
> maka Surat Jalan tidak boleh dianggap seluruhnya selesai jika masih terdapat item dengan sisa Transit.
>
> Gunakan state machine existing dan jangan membuat status baru tanpa pemeriksaan domain terlebih dahulu.

### 58. Retur multi-item

> Surat Jalan dengan beberapa item juga harus dapat melakukan retur terhadap satu atau beberapa item yang eligible.
>
> Retur tidak wajib mencakup seluruh isi Surat Jalan.
>
> Contoh:
>
> ```text
> SJ asli:
> Tiang   100
> ODP      20
>
> Retur:
> ODP       2
> ```
>
> Surat Jalan retur baru hanya memuat item yang benar-benar diretur.

### 59. UX draft items

> Daftar item yang belum diterbitkan harus terlihat jelas sebagai **draft Surat Jalan**.
>
> Jangan mencampur daftar draft dengan:
>
> * Saldo Warehouse;
> * histori transaksi;
> * pengiriman existing.
>
> Gunakan card/table compact dengan jumlah item:
>
> ```text
> Item Surat Jalan                                 3 item
> ```

### 60. Quantity formatter

> Semua Qty pada draft multi-item juga menggunakan formatter dari requirement sebelumnya.
>
> Contoh:
>
> ```text
> 1000     → 1.000
> 25000    → 25.000
> 1250.5   → 1.250,5
> ```
>
> Jangan menyimpan formatted string sebagai nilai database.
>
> Formatting hanya berlaku pada layer presentation.

## Tambahan Dampak dan Catatan

> Keterbatasan satu item per Surat Jalan membuat workflow pengiriman tidak sesuai praktik operasional Warehouse.
>
> Contoh pengiriman yang seharusnya cukup:
>
> ```text
> 1 kendaraan
> 1 sopir
> 1 tujuan
> 1 Surat Jalan
>
> ├── Tiang 7m        100 Btg
> ├── ODP              20 Pcs
> └── Splitter 1:8     10 Pcs
> ```
>
> saat ini berpotensi harus menjadi:
>
> ```text
> SJ-001 → Tiang
> SJ-002 → ODP
> SJ-003 → Splitter
> ```
>
> Hal tersebut menambah:
>
> * jumlah dokumen;
> * input berulang;
> * nomor Surat Jalan;
> * record Transit;
> * pekerjaan penerimaan;
> * potensi mismatch dokumen dengan kendaraan fisik.
>
> Flow target:
>
> ```text
> Tambah item
>      ↓
> Tambah item
>      ↓
> Tambah item
>      ↓
> Review seluruh item
>      ↓
> Terbitkan 1 Surat Jalan
>      ↓
> 1 Transit dengan banyak item
>      ↓
> Warehouse tujuan menerima per item
> ```

## Tambahan Acceptance Criteria

* [ ] Satu Surat Jalan dapat mempunyai lebih dari satu item Material.
* [ ] Tombol `Tambah item` benar-benar menambahkan item ke draft Surat Jalan.
* [ ] `Tambah item` tidak langsung membuat Surat Jalan atau mengubah stok.
* [ ] User dapat menambahkan item kedua, ketiga, dan seterusnya.
* [ ] User dapat menghapus item sebelum Surat Jalan diterbitkan.
* [ ] Minimal satu item diperlukan untuk menerbitkan Surat Jalan.
* [ ] Setiap item mempunyai Material dan Qty sendiri.
* [ ] Qty setiap item divalidasi terhadap stok Warehouse source.
* [ ] Material duplicate ditangani secara konsisten.
* [ ] Material ber-SN tetap membawa Serial Number.
* [ ] Jumlah SN konsisten dengan Qty item.
* [ ] Material Drum tetap membawa Drum ID.
* [ ] Request Material multi-item dapat dibuat menjadi satu Surat Jalan.
* [ ] Qty Request vs Qty Kirim dihitung per item.
* [ ] Partial fulfillment dihitung per item.
* [ ] Satu header Surat Jalan mempunyai banyak item.
* [ ] Create multi-item bersifat atomic/konsisten.
* [ ] Tidak terjadi stock deduction ketika hanya menekan `Tambah item`.
* [ ] Tidak terjadi double-deduct ketika Surat Jalan diterbitkan.
* [ ] Detail Surat Jalan menampilkan seluruh item.
* [ ] Cetak Surat Jalan menampilkan seluruh item.
* [ ] Warehouse tujuan menerima seluruh item dalam satu flow penerimaan.
* [ ] Qty Diterima dapat dicatat berbeda untuk masing-masing item.
* [ ] Sisa Transit dihitung per item.
* [ ] Retur dapat dilakukan terhadap item tertentu.
* [ ] Quantity formatter konsisten pada seluruh item.
* [ ] Permission dan authorization tidak berubah.
* [ ] Ledger tetap append-only.
* [ ] Tidak ada duplicate Surat Jalan akibat retry/double-click.
* [ ] Tidak ada JavaScript/runtime error baru.


## Bukti QC

* `01-actual.png` — kondisi keseluruhan halaman Operasional Material saat pengujian.
* `02-drum-validation.png` — validasi Drum ID wajib diisi tetapi field Drum ID belum tersedia.
* `03-serial-validation.png` — validasi Serial Number wajib diisi tetapi field Serial Number belum tersedia.
* `04-qty-ledger.png` — kondisi Saldo Warehouse dan Aktivitas buku transaksi yang menampilkan Qty dengan trailing `.000` dan tanpa grouping ribuan.

> Tambahkan bukti berikutnya dengan nomor berurutan. Jangan mengganti atau menghapus bukti lama.

## Riwayat status

| Tanggal      | Status | Oleh   | Catatan                                                                                                                                                                                                                                                        |
| ------------ | ------ | ------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `2026-08-20` | `open` | Fatoni | Temuan dibuat. Operasional Material perlu diselaraskan dengan design system, menyediakan input/selector SN dan Drum ID, memperbaiki format Qty, membuka Surat Jalan pada tab baru, serta menyediakan flow penerimaan pengiriman terbuka pada Warehouse tujuan. |
