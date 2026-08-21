# QC-0018 — Konsistensi Design Master Material Admin Mitra

| Field                     | Nilai                                    |
| ------------------------- | ---------------------------------------- |
| ID                        | `QC-0018`                                |
| Prefix                    | `mitra-material`                         |
| Status                    | `open`                                   |
| Severity                  | `minor`                                  |
| Tanggal/waktu pengujian   | `2026-08-20 16:45 WIB`                   |
| Reviewer                  | Fatoni                                   |
| Persona/role              | Admin Mitra                              |
| Halaman atau URL produksi | https://deploythc.web.id/admin/materials |
| Browser/device            | Chrome / laptop Windows                  |
| GitHub Issue              | [Bangun fondasi UI bersama dan selaraskan halaman dashboard, master, project, mitra, user](https://github.com/thcjatim-bit/project-monitoring-system/issues/90) |

## Ringkasan

> Saat login sebagai **Admin Mitra**, halaman **Master Data Material** dapat dibuka tetapi tampilannya masih berupa daftar teks sederhana dan belum mengikuti design language yang digunakan pada modul-modul sebelumnya. Informasi Material, Unit, jenis tracking, status, dan Saldo Warehouse Mitra perlu ditampilkan secara lebih terstruktur tanpa membuka akses lintas Mitra atau memberikan hak edit master global secara tidak sengaja.

## Langkah reproduksi

1. Login menggunakan akun **Admin Mitra**.
2. Buka `https://deploythc.web.id/admin/materials`.
3. Buka menu **Material** pada bagian **Master Data**.
4. Perhatikan daftar Material yang ditampilkan.
5. Perhatikan informasi **Saldo Warehouse** pada masing-masing Material.
6. Perhatikan bahwa halaman masih menggunakan daftar teks vertikal sederhana.
7. Bandingkan dengan design language Command Center, Portfolio, Project, Request Material, dan modul Warehouse yang telah ditetapkan pada QC sebelumnya.

## Hasil aktual

> Halaman saat ini menampilkan daftar Material seperti:
>
> ```text
> dadasd — SFP
> Saldo Warehouse
> Tidak ada saldo Warehouse.
>
> dwadwaaa — splitter 1:8
> Saldo Warehouse
> Tidak ada saldo Warehouse.
>
> dwadwad — tiang 7m
> Saldo Warehouse
> testwhmitra: 33.000 Btg
> ```
>
> Beberapa kondisi yang terlihat:
>
> * setiap Material hanya ditampilkan sebagai teks;
> * kode dan nama belum memiliki hierarchy yang jelas;
> * Unit/Satuan tidak selalu terlihat sebagai atribut terstruktur;
> * jenis/tracking Material tidak terlihat;
> * status aktif/nonaktif tidak terlihat;
> * Saldo Warehouse ditampilkan sebagai teks di bawah setiap Material;
> * Qty masih menggunakan format seperti `33.000`;
> * belum menggunakan reusable card/table/status component;
> * halaman menggunakan ruang vertikal tetapi masih menyisakan banyak ruang horizontal kosong.

## Hasil yang diharapkan

> Halaman **Master Data Material** untuk Admin Mitra menggunakan design language yang sama dengan workspace lainnya.
>
> Karena Material merupakan master data bersama, halaman Admin Mitra secara default dapat tetap **read-only**, tetapi presentation harus lebih informatif dan tenant-safe.

### 1. Page header

> Gunakan hierarchy:
>
> ```text
> MASTER DATA
>
> Material
> Daftar Material yang tersedia untuk kebutuhan Project dan operasional Mitra Anda.
> ```
>
> Informasi teknis/policy dapat ditampilkan sebagai helper text atau info callout.

### 2. Daftar Material menggunakan management list/table

> Gunakan shared DataTable atau compact cards.
>
> Contoh:
>
> ```text
> Material                                           7 Material
>
> ┌──────────────────────────────────────────────────────────────┐
> │ Kode       Material       Unit      Jenis        Status     │
> ├──────────────────────────────────────────────────────────────┤
> │ dadads     SFP            Pcs       Ber-SN       [Aktif]    │
> │ dwadwaaa   Splitter 1:8   Pcs       Biasa        [Aktif]    │
> │ dwadwad    ODP            Pcs       Biasa        [Aktif]    │
> │ dwadwa     Tiang 9m       Btg       Biasa        [Aktif]    │
> │ dwadwadw   Tiang 7m       Btg       Biasa        [Aktif]    │
> │ ...                                                          │
> └──────────────────────────────────────────────────────────────┘
> ```
>
> Kolom final menyesuaikan field yang benar-benar tersedia pada model.

### 3. Tampilkan kode dan nama secara jelas

> Jangan hanya:
>
> ```text
> dwadwad — ODP
> ```
>
> tanpa hierarchy.
>
> Gunakan:
>
> ```text
> ODP
> dwadwad
> ```
>
> atau table:
>
> ```text
> Kode      Nama
> dwadwad   ODP
> ```

### 4. Unit/Satuan

> Tampilkan Unit/Satuan Material secara eksplisit:
>
> ```text
> Pcs
> Btg
> meter
> ```
>
> Gunakan data Unit existing.
>
> Jangan membuat Unit berdasarkan inference dari saldo.

### 5. Jenis / tracking Material

> Jika field tersedia, tampilkan jenis Material:
>
> ```text
> Biasa
> Ber-SN
> Drum Kabel
> ```
>
> Informasi ini penting agar Admin Mitra mengetahui behavior transaksi Material pada Warehouse.

### 6. Status Material

> Jika Material memiliki state aktif/nonaktif, gunakan reusable StatusBadge:
>
> ```text
> [Aktif]
> [Nonaktif]
> ```
>
> Jangan membuat semantic color baru khusus workspace Mitra.

### 7. Saldo Warehouse Mitra

> Saldo Warehouse harus tetap dibatasi pada Warehouse dalam scope Mitra login.
>
> Contoh:
>
> ```text
> Tiang 7m
> dwadwadw · Btg
>
> Saldo Warehouse
>
> testwhmitra                33 Btg
> ```
>
> Jika terdapat lebih dari satu Warehouse milik Mitra:
>
> ```text
> Warehouse A               100 Btg
> Warehouse B                25 Btg
> ```
>
> jangan menjumlahkan lintas Unit yang tidak kompatibel.

### 8. Tenant isolation untuk saldo

> Admin Mitra `pt abc` hanya boleh melihat saldo Warehouse yang authorized untuk `pt abc`.
>
> Jangan tampilkan:
>
> * Warehouse THC yang tidak authorized;
> * Warehouse Mitra lain;
> * saldo tenant lain.
>
> Backend harus menerapkan scope tersebut, bukan hanya template/frontend.

### 9. Material master bersama tetap aman

> Jika Material adalah **master data global/shared**, Admin Mitra tidak otomatis mendapat capability:
>
> * tambah Material;
> * edit Material;
> * nonaktifkan Material;
> * mengubah Unit;
> * mengubah tracking type.
>
> Capability tersebut tetap mengikuti permission backend.
>
> Jangan menambahkan tombol edit hanya untuk menyamakan halaman dengan User THC.

### 10. Jika Mitra memang memiliki capability khusus

> Jika architecture ternyata mendukung Material milik tenant tertentu, UI boleh menampilkan action berdasarkan capability.
>
> Contoh:
>
> ```text
> canManageMaterial = true
>        ↓
> [Tambah Material] [Edit]
>
> canManageMaterial = false
>        ↓
> read-only
> ```
>
> Backend tetap authoritative.

### 11. Quantity formatter

> Gunakan formatter dari `QC-0012`.
>
> Nilai integer:
>
> ```text
> stored/display lama: 33.000
> ```
>
> jika value sebenarnya adalah integer `33`, tampilkan:
>
> ```text
> 33
> ```
>
> Nilai besar:
>
> ```text
> 2312279.000 → 2.312.279
> ```
>
> Nilai pecahan:
>
> ```text
> 1250.5 → 1.250,5
> ```
>
> Jangan mengubah stored value.

### 12. Empty saldo

> Daripada berulang:
>
> ```text
> Saldo Warehouse
> Tidak ada saldo Warehouse.
> ```
>
> gunakan compact empty state:
>
> ```text
> Belum ada saldo pada Warehouse Mitra.
> ```
>
> Tidak perlu menggunakan card besar untuk setiap saldo kosong.

### 13. Ringkasan saldo

> Jika Material mempunyai saldo, dapat menggunakan badge/info compact:
>
> ```text
> Total pada Warehouse Anda: 33 Btg
> ```
>
> hanya jika agregasi memang valid.
>
> Jangan menjumlahkan data dengan Unit berbeda.

### 14. Search Material

> Karena jumlah Material dapat bertambah, halaman sebaiknya mempunyai pencarian Material.
>
> Minimal search:
>
> * nama;
> * kode.
>
> Contoh:
>
> ```text
> Cari Material
> [ kabel 24                                  ]
> ```
>
> Reuse shared filter/search component.

### 15. Filter jika tersedia

> Jika shared filter component sudah ada, filter dapat mencakup:
>
> * Unit;
> * Jenis Material;
> * Status;
> * punya saldo / tidak punya saldo.
>
> Tidak wajib menambah semua filter apabila scope implementasi hanya visual.

### 16. Link Kelola Unit/Satuan

> Link:
>
> ```text
> Kelola Unit/Satuan
> ```
>
> harus mengikuti permission Admin Mitra.
>
> Jika Unit merupakan master global read-only bagi Mitra, gunakan wording yang sesuai:
>
> ```text
> Lihat Unit/Satuan
> ```
>
> daripada `Kelola` apabila user sebenarnya tidak mempunyai hak edit.
>
> Jangan hanya mengganti wording; permission route/backend juga harus konsisten.

### 17. Hubungan Material dengan Warehouse

> Dari Material, user dapat melihat saldo Warehouse yang relevan.
>
> Namun direct stock edit tetap tidak diperbolehkan.
>
> Perubahan stok harus tetap melalui:
>
> ```text
> Operasional Material
>      ↓
> ledger append-only
> ```
>
> sesuai `QC-0012`.

### 18. Material ber-SN

> Jika Material bertipe ber-SN, halaman dapat menampilkan indicator:
>
> ```text
> Tracking: Serial Number
> ```
>
> tetapi tidak perlu menampilkan seluruh inventory SN pada list utama.
>
> Detail inventory SN tetap mengikuti flow Warehouse yang authorized.

### 19. Material Drum

> Material Drum dapat menampilkan:
>
> ```text
> Tracking: Drum ID
> ```
>
> tanpa menampilkan seluruh Drum pada list utama.
>
> Traceability Drum tetap berada pada operasional/detail Warehouse.

### 20. Design responsive

> Desktop:
>
> * gunakan content width secara efektif;
> * table/list tidak terlalu sempit seperti kondisi sekarang.
>
> Tablet:
>
> * table dapat menggunakan responsive layout.
>
> Mobile:
>
> * row dapat berubah menjadi cards jika diperlukan;
> * tidak terdapat horizontal scrolling yang tidak terkendali.

### 21. Shared component

> Reuse:
>
> * PageHeader;
> * Card;
> * DataTable;
> * StatusBadge;
> * QuantityFormatter;
> * Search/Filter;
> * EmptyState;
> * Button/link variants.
>
> Jangan membuat component Material khusus Admin Mitra apabila component User THC dapat direuse dengan capability/scoping berbeda.

### 22. Ketentuan implementasi

> Sebelum implementasi, inspect:
>
> 1. apakah Material adalah global/shared atau tenant-owned;
> 2. permission Admin Mitra terhadap Material;
> 3. relation Material → Unit;
> 4. jenis/tracking Material;
> 5. Warehouse ownership;
> 6. stock query scope;
> 7. shared QuantityFormatter;
> 8. Material component hasil `QC-0007`.
>
> Jangan memperluas permission hanya karena halaman perlu diselaraskan secara visual.

## Dampak dan catatan

> Halaman saat ini dapat digunakan untuk melihat Material, tetapi presentation kurang scalable dan kurang konsisten dengan workspace lainnya.
>
> Dengan semakin banyak Material, pola:
>
> ```text
> Material
> Saldo Warehouse
> Material
> Saldo Warehouse
> Material
> Saldo Warehouse
> ```
>
> akan sulit dipindai.
>
> Target:
>
> ```text
> Material Master
>      │
>      ├── Kode
>      ├── Nama
>      ├── Unit
>      ├── Jenis / tracking
>      ├── Status
>      └── Saldo Warehouse Mitra
> ```
>
> Untuk Admin Mitra, perbedaan utama dari User THC adalah **scope dan capability**, bukan design.
>
> Jika Material memang global, Admin Mitra cukup membaca master tersebut dan melihat saldo milik tenant-nya:
>
> ```text
> Shared Material
>       +
> Tenant-scoped stock
> ```
>
> tanpa mendapatkan hak mengubah master global.

## Acceptance Criteria

* [ ] Halaman Material Admin Mitra menggunakan design language yang sama dengan workspace lainnya.
* [ ] Daftar Material menggunakan shared management list/DataTable.
* [ ] Kode Material terlihat jelas.
* [ ] Nama Material terlihat jelas.
* [ ] Unit/Satuan ditampilkan jika tersedia.
* [ ] Jenis/tracking Material ditampilkan jika tersedia.
* [ ] Status menggunakan reusable badge jika tersedia.
* [ ] Saldo Warehouse hanya menampilkan Warehouse dalam scope Mitra.
* [ ] Tidak ada saldo Warehouse Mitra lain yang bocor.
* [ ] Backend stock query tenant-scoped.
* [ ] Quantity menggunakan shared formatter.
* [ ] Integer tidak menampilkan trailing `.000`.
* [ ] Pemisah ribuan konsisten.
* [ ] Precision valid tidak hilang.
* [ ] Empty saldo menggunakan compact EmptyState.
* [ ] Search Material berdasarkan kode/nama tersedia jika shared component mendukung.
* [ ] Direct stock editing tidak ditambahkan.
* [ ] Stock tetap dikelola melalui ledger append-only.
* [ ] Material ber-SN dapat dikenali dari tracking metadata.
* [ ] Material Drum dapat dikenali dari tracking metadata.
* [ ] Admin Mitra tidak otomatis mendapat edit master Material global.
* [ ] Action hanya tampil berdasarkan capability backend.
* [ ] Link Unit/Satuan menggunakan wording dan permission yang sesuai.
* [ ] Tidak ada privilege escalation.
* [ ] Tidak ada data leakage lintas Mitra.
* [ ] Responsive pada desktop, tablet, dan mobile.
* [ ] Tidak ada dummy/fake data.
* [ ] Tidak ada horizontal scrolling yang tidak terkendali.
* [ ] Tidak ada JavaScript/runtime error baru.

## Bukti QC

* `01-actual.png` — kondisi halaman Master Data Material saat login sebagai Admin Mitra; Material dan Saldo Warehouse masih ditampilkan sebagai daftar teks sederhana.


> Tambahkan bukti berikutnya dengan nomor berurutan. Jangan mengganti atau menghapus bukti lama.

## Riwayat status

| Tanggal      | Status | Oleh   | Catatan                                                                                                                                                                                                      |
| ------------ | ------ | ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `2026-08-20` | `open` | Fatoni | Temuan dibuat. Halaman Material Admin Mitra perlu diselaraskan dengan design system dan menampilkan master Material serta saldo Warehouse secara lebih terstruktur dengan tenant isolation tetap diterapkan. |
