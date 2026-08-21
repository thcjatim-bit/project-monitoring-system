# QC-0019 — Konsistensi Design Master Unit dan PoP Admin Mitra

| Field                     | Nilai                                                                                          |
| ------------------------- | ---------------------------------------------------------------------------------------------- |
| ID                        | `QC-0019`                                                                                      |
| Prefix                    | `mitra-master-data`                                                                            |
| Status                    | `open`                                                                                         |
| Severity                  | `minor`                                                                                        |
| Tanggal/waktu pengujian   | `2026-08-20 16:48 WIB`                                                                         |
| Reviewer                  | Fatoni                                                                                         |
| Persona/role              | Admin Mitra                                                                                    |
| Halaman atau URL produksi | `https://deploythc.web.id/admin/master/units` dan `https://deploythc.web.id/admin/master/pops` |
| Browser/device            | Chrome / laptop Windows                                                                        |
| GitHub Issue              | [Bangun fondasi UI bersama dan selaraskan halaman dashboard, master, project, mitra, user](https://github.com/thcjatim-bit/project-monitoring-system/issues/90) |

## Ringkasan

> Saat login sebagai **Admin Mitra**, halaman master **Unit** dan **PoP** dapat dibuka tetapi masih ditampilkan sebagai daftar teks sederhana dan belum mengikuti design language workspace yang sudah digunakan pada modul lain. Kedua halaman perlu menggunakan shared component, hierarchy, status, search/filter, dan responsive layout yang konsisten tanpa memberikan hak edit master global kepada Admin Mitra apabila permission backend tidak mengizinkannya.

## Langkah reproduksi

1. Login menggunakan akun **Admin Mitra**.
2. Buka `https://deploythc.web.id/admin/master/units`.
3. Perhatikan bagian **Daftar Unit**.
4. Buka `https://deploythc.web.id/admin/master/pops`.
5. Perhatikan bagian **Daftar PoP**.
6. Bandingkan tampilan kedua halaman dengan design language yang telah diterapkan pada Command Center, Portfolio, Project, Material, dan Warehouse.
7. Perhatikan bahwa data saat ini hanya ditampilkan sebagai teks dan belum menggunakan management list/table yang konsisten.

## Hasil aktual

> Halaman **Unit** saat ini menampilkan data seperti:
>
> ```text
> Daftar Unit
>
> wdadwa — Btg
> ddddd — Pcs
> dd — meter
> ```
>
> Halaman **PoP** menampilkan:
>
> ```text
> Daftar PoP
>
> eee — pop rembige
> ```
>
> Kondisi saat ini:
>
> * data hanya berupa plain text;
> * kode dan nama belum memiliki hierarchy yang jelas;
> * status aktif/nonaktif belum terlihat;
> * belum menggunakan shared DataTable/management list;
> * belum tersedia search;
> * halaman memanfaatkan ruang horizontal secara kurang optimal;
> * belum sepenuhnya konsisten dengan halaman Master Data lain;
> * wording masih mencerminkan halaman master umum tetapi capability Admin Mitra belum diperjelas secara visual.

## Hasil yang diharapkan

> Halaman **Unit** dan **PoP** menggunakan design language yang sama dengan workspace lainnya dan tetap mengikuti permission/capability Admin Mitra.

### 1. Satu pola Master Data

> Gunakan struktur halaman yang konsisten:
>
> ```text
> MASTER DATA
>
> Unit
> Daftar Unit/Satuan yang digunakan oleh Material.
> ```
>
> dan:
>
> ```text
> MASTER DATA
>
> PoP
> Daftar Point of Presence yang tersedia dalam sistem.
> ```
>
> Gunakan PageHeader, Card, DataTable, StatusBadge, Search/Filter, EmptyState, dan component shared lainnya.

### 2. Unit menggunakan DataTable / management list

> Tampilkan Unit secara terstruktur.
>
> Contoh:
>
> ```text
> Unit                                             3 Unit
>
> ┌─────────────────────────────────────────────────────┐
> │ Kode        Nama                     Status         │
> ├─────────────────────────────────────────────────────┤
> │ wdadwa      Btg                      [Aktif]        │
> │ ddddd       Pcs                      [Aktif]        │
> │ dd          meter                    [Aktif]        │
> └─────────────────────────────────────────────────────┘
> ```
>
> Kolom final mengikuti field yang benar-benar tersedia pada model.

### 3. PoP menggunakan DataTable / management list

> Tampilkan PoP dengan pola yang sama.
>
> Contoh:
>
> ```text
> PoP                                               1 PoP
>
> ┌─────────────────────────────────────────────────────┐
> │ Kode        Nama                     Status         │
> ├─────────────────────────────────────────────────────┤
> │ eee         pop rembige              [Aktif]        │
> └─────────────────────────────────────────────────────┘
> ```

### 4. Kode dan nama dipisahkan dengan jelas

> Jangan hanya:
>
> ```text
> wdadwa — Btg
> ```
>
> atau:
>
> ```text
> eee — pop rembige
> ```
>
> Gunakan column/field yang jelas:
>
> ```text
> Kode   Nama
> ```
>
> sehingga data lebih mudah dipindai.

### 5. Status

> Jika Unit dan PoP mempunyai status aktif/nonaktif, tampilkan menggunakan shared StatusBadge:
>
> ```text
> [Aktif]
> [Nonaktif]
> ```
>
> Gunakan semantic color yang sama dengan modul lain.

### 6. Search Unit

> Tambahkan/reuse search sederhana:
>
> ```text
> Cari Unit
> [ kode atau nama unit                         ]
> ```
>
> Search minimal dapat mencocokkan:
>
> * kode;
> * nama.

### 7. Search PoP

> Tambahkan/reuse search:
>
> ```text
> Cari PoP
> [ kode atau nama PoP                          ]
> ```
>
> Search minimal dapat mencocokkan:
>
> * kode;
> * nama.

### 8. Empty state

> Jika tidak ada data Unit:
>
> ```text
> Belum ada Unit yang tersedia.
> ```
>
> Jika tidak ada PoP:
>
> ```text
> Belum ada PoP yang tersedia.
> ```
>
> Gunakan reusable EmptyState.

### 9. Read-only untuk Admin Mitra

> Jika Unit dan PoP merupakan **shared/global master data**, Admin Mitra tetap read-only.
>
> Jangan otomatis menampilkan:
>
> * Tambah Unit;
> * Edit Unit;
> * Nonaktifkan Unit;
> * Tambah PoP;
> * Edit PoP;
> * Nonaktifkan PoP.
>
> hanya untuk menyamakan tampilan dengan workspace User THC.

### 10. Capability tetap backend-driven

> Jika backend mempunyai capability tertentu:
>
> ```text
> canManageUnit
> canManagePoP
> ```
>
> maka action dapat muncul berdasarkan capability.
>
> Tetapi default untuk Admin Mitra tidak boleh diasumsikan memiliki hak mengelola master global.
>
> Backend tetap authoritative.

### 11. Jangan privilege escalation

> Admin Mitra tidak boleh memperoleh akses write hanya dengan:
>
> * memanggil endpoint User THC;
> * mengganti URL;
> * mengirim POST/PATCH manual;
> * memanipulasi form;
> * mengubah ID object.
>
> Endpoint mutation harus tetap memeriksa permission.

### 12. Unit sebagai reference Material

> Unit merupakan master yang digunakan oleh Material.
>
> Admin Mitra boleh melihat Unit yang diperlukan untuk memahami:
>
> ```text
> Material → Unit/Satuan
> ```
>
> tetapi tidak otomatis dapat mengubah Unit yang dapat mempengaruhi Material global.

### 13. PoP sebagai shared reference

> Jika PoP digunakan Project/operasional secara global, Admin Mitra dapat membaca master yang authorized tetapi tidak otomatis dapat mengubah referensi tersebut.
>
> Jangan memberikan capability edit global hanya karena Project Mitra menggunakan PoP tersebut.

### 14. Link antar master data

> Pada halaman Unit, link:
>
> ```text
> Lihat Material
> ```
>
> dapat dipertahankan dan menggunakan shared secondary navigation/link style.
>
> Pastikan wording sesuai capability.
>
> Jika Admin Mitra hanya read-only:
>
> ```text
> Lihat Material
> ```
>
> lebih tepat daripada wording `Kelola`.

### 15. Konsistensi dengan QC-0018

> Halaman ini harus mengikuti prinsip halaman Material Admin Mitra:
>
> ```text
> Shared master
>      +
> tenant-aware operational data
>      +
> capability-based actions
> ```
>
> Desain tidak perlu berbeda antara User THC dan Admin Mitra.
>
> Yang berbeda hanyalah permission/action.

### 16. Responsive design

> Desktop:
>
> * tabel memanfaatkan content width secara proporsional;
> * tidak menampilkan daftar terlalu sempit di sisi kiri halaman.
>
> Tablet:
>
> * DataTable tetap readable.
>
> Mobile:
>
> * row dapat berubah ke card/list apabila shared responsive table memang menggunakan pola tersebut;
> * tidak ada horizontal scrolling yang tidak terkendali.

### 17. Reuse shared components

> Reuse:
>
> * PageHeader;
> * ContentContainer;
> * Card;
> * DataTable;
> * StatusBadge;
> * Search/Filter;
> * EmptyState;
> * Button/link variants.
>
> Jangan membuat:
>
> ```text
> UnitMitraTable
> UnitTHCTable
> PoPMitraTable
> PoPTHCTable
> ```
>
> jika shared component yang sama dapat menerima capability/read-only state.

### 18. Ketentuan implementasi

> Sebelum implementasi, inspect:
>
> 1. apakah Unit merupakan global/shared master;
> 2. apakah PoP merupakan global/shared master;
> 3. permission Admin Mitra terhadap Unit;
> 4. permission Admin Mitra terhadap PoP;
> 5. relation Unit → Material;
> 6. relation PoP → Project/operational object;
> 7. status aktif/nonaktif;
> 8. component hasil `QC-0008`, `QC-0009`, dan `QC-0018`.
>
> Jangan memperluas permission hanya untuk menyamakan visual.

## Dampak dan catatan

> Secara fungsi, Admin Mitra sudah dapat membaca Unit dan PoP, tetapi penyajian saat ini terlalu sederhana dan tidak konsisten dengan design system aplikasi.
>
> Pola saat ini:
>
> ```text
> Kode — Nama
> Kode — Nama
> Kode — Nama
> ```
>
> akan semakin sulit dipindai ketika jumlah master data bertambah.
>
> Target:
>
> ```text
> Shared Master Data
>        │
>        ├── Material
>        ├── Unit
>        ├── PoP
>        └── Pekerjaan Jasa
>             ↓
>      satu design system
> ```
>
> Admin Mitra tetap mendapatkan data referensi yang diperlukan untuk operasional, sementara hak pengelolaan master global tetap berada pada role/capability yang memang berwenang.

## Acceptance Criteria

* [ ] Halaman Unit menggunakan design language yang sama dengan modul lainnya.
* [ ] Halaman PoP menggunakan design language yang sama dengan modul lainnya.
* [ ] Unit menggunakan shared DataTable/management list.
* [ ] PoP menggunakan shared DataTable/management list.
* [ ] Kode dan Nama Unit ditampilkan terpisah dan jelas.
* [ ] Kode dan Nama PoP ditampilkan terpisah dan jelas.
* [ ] Status menggunakan reusable badge jika field tersedia.
* [ ] Search Unit dapat menggunakan kode atau nama.
* [ ] Search PoP dapat menggunakan kode atau nama.
* [ ] Empty state menggunakan shared component.
* [ ] Link antar master data menggunakan styling konsisten.
* [ ] Admin Mitra tetap read-only jika Unit/PoP merupakan global master.
* [ ] Action edit hanya muncul berdasarkan capability backend.
* [ ] Direct endpoint mutation unauthorized ditolak.
* [ ] Tidak ada privilege escalation.
* [ ] Tidak ada perubahan relation Unit–Material.
* [ ] Tidak ada perubahan relation PoP–Project/operasional.
* [ ] Tidak ada dummy/fake data.
* [ ] Component User THC direuse jika memungkinkan.
* [ ] Responsive pada desktop, tablet, dan mobile.
* [ ] Tidak ada horizontal scrolling yang tidak terkendali.
* [ ] Tidak ada JavaScript/runtime error baru.

## Bukti QC

* `01-unit-actual.png` — kondisi halaman Master Data Unit saat login sebagai Admin Mitra; data Unit masih ditampilkan sebagai daftar teks sederhana.
* `02-pop-actual.png` — kondisi halaman Master Data PoP saat login sebagai Admin Mitra; data PoP masih ditampilkan sebagai daftar teks sederhana.

> Tambahkan bukti berikutnya dengan nomor berurutan. Jangan mengganti atau menghapus bukti lama.

## Riwayat status

| Tanggal      | Status | Oleh   | Catatan                                                                                                                                                                                  |
| ------------ | ------ | ------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `2026-08-20` | `open` | Fatoni | Temuan dibuat. Halaman Unit dan PoP Admin Mitra perlu diselaraskan dengan design system menggunakan shared master-data components tanpa memperluas permission pengelolaan master global. |
