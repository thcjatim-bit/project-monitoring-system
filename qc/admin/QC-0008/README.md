# QC-0008 — Konsistensi Design dan Auto-generate Kode Unit

| Field                     | Nilai                                       |
| ------------------------- | ------------------------------------------- |
| ID                        | `QC-0008`                                   |
| Prefix                    | `unit`                                      |
| Status                    | `open`                                      |
| Severity                  | `major`                                     |
| Tanggal/waktu pengujian   | `2026-08-20 14:46 WIB`                      |
| Reviewer                  | Fatoni                                      |
| Persona/role              | User THC                                    |
| Halaman atau URL produksi | https://deploythc.web.id/admin/master/units |
| Browser/device            | Chrome / laptop Windows                     |
| GitHub Issue              | [Bangun fondasi UI bersama dan selaraskan halaman dashboard, master, project, mitra, user](https://github.com/thcjatim-bit/project-monitoring-system/issues/90) |

## Ringkasan

> Halaman **Master Data Unit** perlu diselaraskan dengan design language yang sama dengan modul Command Center, Portfolio, Project, Mitra, User, dan Material. Selain itu, field **Kode Unit** saat membuat Unit baru diharapkan boleh dikosongkan; apabila kosong, sistem harus otomatis membuat kode Unit yang unik.

## Langkah reproduksi

1. Buka `https://deploythc.web.id/admin/master/units`.
2. Masuk sebagai persona/role **User THC**.
3. Buka menu **Unit** pada bagian **Master Data**.
4. Perhatikan bagian **Tambah Unit** dan **Daftar Unit**.
5. Coba membuat Unit baru tanpa mengisi field **Kode**.
6. Perhatikan behavior saat ini yang masih mengharuskan user menentukan Kode Unit secara manual.
7. Perhatikan juga bahwa seluruh Unit pada daftar langsung ditampilkan sebagai form edit sehingga halaman menjadi panjang ketika jumlah data bertambah.

## Hasil aktual

> Halaman Unit saat ini masih menggunakan layout form administrasi sederhana dan belum konsisten dengan design language baru pada modul lainnya.
>
> Bagian **Tambah Unit** menampilkan field `Kode`, `Nama`, dan tombol `Simpan`.
>
> Pada bagian **Daftar Unit**, setiap Unit langsung ditampilkan dalam kondisi edit dengan:
>
> * field Kode;
> * field Nama;
> * tombol `Simpan perubahan`;
> * tombol `Nonaktifkan`.
>
> Akibatnya daftar Unit menggunakan ruang vertikal cukup besar dan akan semakin panjang ketika jumlah Unit bertambah.
>
> Selain itu, proses pembuatan Unit saat ini masih bergantung pada **Kode Unit yang diisi manual**.

## Hasil yang diharapkan

> Halaman **Master Data Unit** menggunakan design language yang sama dengan modul lain dan reuse shared component/design token yang sudah tersedia.
>
> Bagian **Tambah Unit** dibuat dalam form card yang compact dan rapi.
>
> Contoh:
>
> ```text
> MASTER DATA
>
> Unit
> Kelola satuan yang digunakan oleh Material dan operasional Warehouse.
>
> ┌───────────────────────────────────────────────────┐
> │ Tambah Unit                                       │
> │                                                   │
> │ Kode Unit                 Nama Unit               │
> │ [                       ] [                     ] │
> │ Kosongkan untuk dibuat                            │
> │ otomatis.                                         │
> │                                                   │
> │                                  [Simpan Unit]    │
> └───────────────────────────────────────────────────┘
> ```
>
> ### Auto-generate Kode Unit
>
> Field **Kode Unit tidak wajib diisi ketika membuat Unit baru**.
>
> Behavior yang diharapkan:
>
> ```text
> Kode diisi
>     ↓
> gunakan kode dari user
>
> Kode kosong
>     ↓
> backend generate kode otomatis
>     ↓
> simpan kode unik
> ```
>
> Dengan demikian user tetap mempunyai dua pilihan:
>
> * memasukkan Kode Unit secara manual; atau
> * mengosongkan field Kode dan membiarkan sistem membuatnya otomatis.
>
> Auto-generated Kode Unit harus:
>
> * unik;
> * tidak collision dengan Unit existing;
> * dibuat oleh backend/server sebagai authoritative source;
> * aman ketika terdapat beberapa request create secara bersamaan;
> * mengikuti convention generator yang sudah digunakan pada entity lain apabila tersedia;
> * tidak hanya dibuat melalui JavaScript/frontend.
>
> Sebelum membuat generator baru, inspect mekanisme auto-generation yang sudah tersedia pada entity seperti **Mitra, Project, atau Material** dan reuse helper/service yang sama jika sesuai.
>
> Format kode tidak perlu ditentukan secara hardcoded dari QC ini apabila project sudah memiliki convention ID/code generator.
>
> ### Existing Unit
>
> Auto-generation hanya berlaku ketika **create Unit baru dengan Kode kosong**.
>
> Jangan melakukan perubahan otomatis terhadap Kode Unit existing.
>
> Contoh:
>
> ```text
> Existing:
>
> dd     → meter
> ddddd  → Pcs
> wdadwa → Btg
> ```
>
> Data tersebut tidak boleh diubah hanya untuk mengikuti format kode baru.
>
> ### Daftar Unit
>
> Daftar Unit dibuat lebih compact dan tidak perlu selalu berada dalam mode edit.
>
> Kondisi default dapat ditampilkan seperti:
>
> ```text
> ┌───────────────────────────────────────────────────┐
> │ Btg                                      [Aktif] │
> │ wdadwa                                            │
> │                                                   │
> │                                 [Edit] [Nonaktifkan]
> └───────────────────────────────────────────────────┘
>
> ┌───────────────────────────────────────────────────┐
> │ Pcs                                      [Aktif] │
> │ ddddd                                             │
> │                                                   │
> │                                 [Edit] [Nonaktifkan]
> └───────────────────────────────────────────────────┘
> ```
>
> atau menggunakan compact table/list apabila shared component project lebih cocok.
>
> Contoh:
>
> ```text
> Unit                                           3 Unit
>
> Kode        Nama                  Status       Aksi
> ─────────────────────────────────────────────────────
> wdadwa      Btg                   Aktif        Edit
> ddddd       Pcs                   Aktif        Edit
> dd          meter                 Aktif        Edit
> ```
>
> Pilih implementasi yang paling konsisten dengan management list pada modul Project, Mitra, User, dan Material.
>
> ### Edit Unit
>
> Form edit hanya perlu tampil setelah user memilih `Edit`.
>
> Contoh:
>
> ```text
> Edit Unit
>
> Kode
> [ wdadwa ]
>
> Nama
> [ Btg ]
>
>                     [Batal] [Simpan perubahan]
> ```
>
> Jika implementation existing menggunakan inline edit, mekanismenya boleh dipertahankan tetapi presentation harus dibuat compact.
>
> QC ini tidak mengubah aturan apakah Kode Unit existing boleh atau tidak boleh diedit.
>
> ### Status Unit
>
> Unit aktif/nonaktif menggunakan reusable badge/chip yang sama dengan modul lain.
>
> Contoh:
>
> ```text
> [Aktif]
> ```
>
> daripada hanya menampilkan status dalam teks biasa.
>
> ### Hierarchy action
>
> Gunakan hierarchy yang konsisten:
>
> * `Simpan Unit` → primary.
> * `Simpan perubahan` → primary.
> * `Edit` / `Batal` → secondary.
> * `Nonaktifkan` → state/warning action.
>
> Tombol `Nonaktifkan` tidak perlu memiliki bobot visual yang sama dengan `Simpan`.
>
> ### Responsive
>
> Pada desktop, form Tambah Unit dapat menggunakan dua kolom.
>
> Pada tablet/mobile:
>
> ```text
> Kode Unit
> Nama Unit
> Simpan Unit
> ```
>
> menjadi satu kolom apabila ruang tidak mencukupi.
>
> Tidak boleh terdapat horizontal scrolling.
>
> ### Ketentuan implementasi
>
> Reuse component/design system dari QC sebelumnya, terutama:
>
> * page header;
> * card;
> * input/form control;
> * status badge;
> * button variants;
> * alert/toast;
> * management list/card.
>
> Jangan membuat design system baru khusus halaman Unit.
>
> Perubahan tidak boleh mengubah:
>
> * permission;
> * authorization;
> * relasi Unit dengan Material;
> * reference Unit existing;
> * historical data;
> * activate/deactivate behavior;
> * database relation;
> * audit/activity logging;
> * business logic selain requirement auto-generation pada create.
>
> Karena Unit dapat direferensikan oleh Material atau data historis lainnya, perubahan UI tidak boleh menghapus atau mengganti referensi existing secara otomatis.

## Dampak dan catatan

> Kondisi layout saat ini membuat halaman semakin panjang seiring jumlah Unit karena seluruh record selalu tampil sebagai form edit.
>
> Menggunakan management list/card yang compact akan membuat data lebih mudah dipindai dan konsisten dengan modul master data lainnya.
>
> Auto-generation Kode Unit juga mengurangi kebutuhan user membuat kode secara manual dan mengurangi potensi:
>
> * kode tidak konsisten;
> * typo;
> * kode duplikat;
> * convention berbeda antar user.
>
> Flow yang diharapkan:
>
> ```text
> Tambah Unit
>
> Kode: [optional]
> Nama: Pcs
>       ↓
> Simpan
>       ↓
> kode kosong?
>   ├─ tidak → gunakan kode input
>   └─ ya    → backend generate kode unik
> ```
>
> Acceptance utama:
>
> * Halaman Unit menggunakan design language yang sama dengan modul lainnya.
> * Form Tambah Unit lebih rapi dan compact.
> * Field Kode Unit boleh dikosongkan ketika create.
> * Backend otomatis membuat Kode Unit jika field kosong.
> * Kode hasil auto-generation unik.
> * Kode manual tetap dapat digunakan jika user mengisinya.
> * Kode Unit existing tidak berubah.
> * Daftar Unit dibuat lebih compact.
> * Record tidak harus selalu tampil dalam mode edit.
> * Edit Unit tetap berfungsi.
> * Simpan perubahan tetap berfungsi.
> * Nonaktifkan tetap berfungsi.
> * Status menggunakan reusable badge jika ditampilkan.
> * Permission dan authorization tidak berubah.
> * Relasi Unit dengan Material tidak berubah.
> * Tidak ada dummy/fake data.
> * Tidak ada horizontal scrolling.
> * Tidak ada JavaScript/runtime error baru.

## Bukti QC

* `01-actual.png` — kondisi halaman Master Data Unit saat temuan terjadi.
* `02-context.png` — konteks form Tambah Unit, field Kode, Daftar Unit, Edit Unit, dan tombol Nonaktifkan.

> Tambahkan bukti berikutnya dengan nomor berurutan. Jangan mengganti atau menghapus bukti lama.

## Riwayat status

| Tanggal      | Status | Oleh   | Catatan                                                                                                                                                      |
| ------------ | ------ | ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `2026-08-20` | `open` | Fatoni | Temuan dibuat. Halaman Unit perlu diselaraskan dengan design language workspace dan Kode Unit perlu mendukung auto-generation ketika field Kode dikosongkan. |
