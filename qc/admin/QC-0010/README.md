# QC-0010 — Konsistensi Design dan Auto-generate Kode Pekerjaan Jasa

| Field                     | Nilai                                                |
| ------------------------- | ---------------------------------------------------- |
| ID                        | `QC-0010`                                            |
| Prefix                    | `pekerjaan-jasa`                                     |
| Status                    | `open`                                               |
| Severity                  | `major`                                              |
| Tanggal/waktu pengujian   | `2026-08-20 14:49 WIB`                               |
| Reviewer                  | Fatoni                                               |
| Persona/role              | User THC                                             |
| Halaman atau URL produksi | https://deploythc.web.id/admin/master/pekerjaan-jasa |
| Browser/device            | Chrome / laptop Windows                              |
| GitHub Issue              | [Bangun fondasi UI bersama dan selaraskan halaman dashboard, master, project, mitra, user](https://github.com/thcjatim-bit/project-monitoring-system/issues/90) |

## Ringkasan

> Halaman **Master Data Pekerjaan Jasa** perlu diselaraskan dengan design language yang sama dengan modul lainnya. Selain itu, field **Kode Pekerjaan Jasa** saat membuat data baru saat ini wajib diisi manual; diharapkan field tersebut boleh dikosongkan dan sistem otomatis menghasilkan kode yang unik.

## Langkah reproduksi

1. Buka `https://deploythc.web.id/admin/master/pekerjaan-jasa`.
2. Masuk sebagai persona/role **User THC**.
3. Buka menu **Pekerjaan Jasa** pada bagian **Master Data**.
4. Pada bagian **Tambah Pekerjaan Jasa**, isi field Nama.
5. Biarkan field **Kode** kosong.
6. Coba simpan Pekerjaan Jasa baru.
7. Perhatikan browser menampilkan validasi `Please fill in this field.` sehingga data tidak dapat dibuat tanpa Kode manual.
8. Perhatikan bagian **Daftar Pekerjaan Jasa** yang menampilkan seluruh record langsung dalam mode edit.

## Hasil aktual

> Halaman Pekerjaan Jasa saat ini belum mengikuti design language yang konsisten dengan Command Center, Portfolio, Project, Mitra, User, Material, Unit, dan PoP.
>
> Pada bagian **Tambah Pekerjaan Jasa**, field yang tersedia adalah:
>
> * Kode;
> * Nama;
> * tombol Simpan.
>
> Field **Kode** saat ini memiliki validasi required sehingga wajib diisi secara manual.
>
> Apabila field Kode dikosongkan, browser menolak proses submit dan menampilkan:
>
> `Please fill in this field.`
>
> Pada bagian **Daftar Pekerjaan Jasa**, setiap record langsung ditampilkan sebagai form edit yang berisi:
>
> * Kode;
> * Nama;
> * tombol `Simpan perubahan`;
> * tombol `Nonaktifkan`.
>
> Karena seluruh record selalu berada dalam mode edit, halaman menjadi panjang dan kurang efisien ketika jumlah Pekerjaan Jasa bertambah.

## Hasil yang diharapkan

> Halaman **Master Data Pekerjaan Jasa** menggunakan design language yang sama dengan halaman Master Data lainnya, khususnya **Unit** dan **PoP**.
>
> Form **Tambah Pekerjaan Jasa** dibuat lebih compact dan terstruktur.
>
> Contoh:
>
> ```text
> MASTER DATA
>
> Pekerjaan Jasa
> Kelola master pekerjaan jasa yang digunakan dalam Project.
>
> ┌────────────────────────────────────────────────────┐
> │ Tambah Pekerjaan Jasa                              │
> │                                                    │
> │ Kode                       Nama                    │
> │ [                        ] [                     ] │
> │ Kosongkan untuk dibuat                             │
> │ otomatis.                                          │
> │                                                    │
> │                           [Simpan Pekerjaan Jasa]  │
> └────────────────────────────────────────────────────┘
> ```
>
> ### Auto-generate Kode Pekerjaan Jasa
>
> Field **Kode tidak wajib diisi ketika membuat Pekerjaan Jasa baru**.
>
> Behavior:
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
> User tetap dapat:
>
> * mengisi Kode secara manual jika diperlukan; atau
> * membiarkan field Kode kosong agar sistem membuatnya otomatis.
>
> Auto-generated code harus:
>
> * unik;
> * tidak collision dengan Pekerjaan Jasa existing;
> * dibuat secara authoritative pada backend/server;
> * aman terhadap concurrent create;
> * menggunakan generator/convention existing apabila tersedia;
> * tidak hanya dibuat pada frontend.
>
> Validasi frontend/HTML yang saat ini mewajibkan Kode harus disesuaikan agar Kode optional pada proses create.
>
> Backend juga harus menerima Kode kosong/null dan menjalankan generator sebelum record disimpan.
>
> Jangan hanya menghapus atribut `required` di frontend jika backend masih tetap mewajibkan Kode.

### Reuse mekanisme generator

> Sebelum membuat generator baru, inspect implementasi auto-generation yang sudah digunakan pada:
>
> * Mitra;
> * Project;
> * Material;
> * Unit;
> * PoP;
> * shared code/ID generator lain.
>
> Jika sudah tersedia helper/service reusable, gunakan implementasi tersebut.
>
> QC ini tidak menentukan format hardcoded tertentu seperti:
>
> ```text
> JSA-000001
> ```
>
> apabila aplikasi sudah memiliki convention lain.
>
> Prioritaskan satu pola generator untuk seluruh master data.

### Existing Pekerjaan Jasa

> Auto-generation hanya berlaku pada **create ketika field Kode kosong**.
>
> Kode existing tidak boleh diubah.
>
> Contoh data yang sudah ada seperti:
>
> ```text
> dadwadada → Jasa sambung
> dadwad    → Jasa Pemasangan ODP
> dwadwad   → Jasa Penarikan Kabel
> ```
>
> harus tetap dipertahankan dan tidak perlu dimigrasikan hanya untuk mengikuti convention auto-generation baru.

### Daftar Pekerjaan Jasa

> Daftar dibuat lebih compact dan tidak selalu berada dalam mode edit.
>
> Contoh:
>
> ```text
> Pekerjaan Jasa                              3 item
>
> ┌──────────────────────────────────────────────────────┐
> │ Jasa sambung                               [Aktif] │
> │ dadwadada                                           │
> │                                                     │
> │                                 [Edit] [Nonaktifkan]│
> └──────────────────────────────────────────────────────┘
>
> ┌──────────────────────────────────────────────────────┐
> │ Jasa Pemasangan ODP                        [Aktif] │
> │ dadwad                                              │
> │                                                     │
> │                                 [Edit] [Nonaktifkan]│
> └──────────────────────────────────────────────────────┘
> ```
>
> Atau gunakan compact table/list apabila lebih sesuai dengan shared management component:
>
> ```text
> Kode          Nama                     Status    Aksi
> ─────────────────────────────────────────────────────
> dadwadada     Jasa sambung             Aktif     Edit
> dadwad        Jasa Pemasangan ODP      Aktif     Edit
> dwadwad       Jasa Penarikan Kabel     Aktif     Edit
> ```

### Edit Pekerjaan Jasa

> Form edit hanya perlu muncul setelah user memilih `Edit`.
>
> Contoh:
>
> ```text
> Edit Pekerjaan Jasa
>
> Kode
> [ dadwad ]
>
> Nama
> [ Jasa Pemasangan ODP ]
>
>                     [Batal] [Simpan perubahan]
> ```
>
> Jika existing implementation menggunakan inline edit, mekanismenya dapat dipertahankan selama presentation dibuat lebih compact.
>
> QC ini tidak mengubah aturan apakah Kode existing boleh diedit atau tidak.

### Status

> Gunakan reusable status badge/chip apabila status ditampilkan:
>
> ```text
> [Aktif]
> ```
>
> Gunakan component dan semantic color yang sama dengan Unit, PoP, Material, Mitra, dan User.

### Hierarchy aksi

> Gunakan hierarchy action yang sama:
>
> * `Simpan Pekerjaan Jasa` → primary.
> * `Simpan perubahan` → primary.
> * `Edit` / `Batal` → secondary.
> * `Nonaktifkan` → state/warning action.
>
> Jangan menggunakan treatment primary yang sama untuk semua jenis action.

### Responsive

> Desktop dapat menggunakan dua kolom:
>
> ```text
> Kode                    Nama
> [                    ]  [                    ]
> ```
>
> Pada tablet/mobile berubah menjadi:
>
> ```text
> Kode
> Nama
> Simpan Pekerjaan Jasa
> ```
>
> Tidak boleh terdapat horizontal scrolling.

### Ketentuan implementasi

> Reuse shared component/design system dari QC sebelumnya:
>
> * page header;
> * content container;
> * card;
> * form control;
> * input;
> * helper text;
> * status badge;
> * button variants;
> * management list/card;
> * alert/toast.
>
> Jangan membuat design system atau component khusus hanya untuk Pekerjaan Jasa jika shared component sudah tersedia.
>
> Perubahan tidak boleh mengubah:
>
> * permission;
> * authorization;
> * relasi Pekerjaan Jasa dengan Project;
> * progress jasa;
> * RAB jasa;
> * historical references;
> * activate/deactivate behavior;
> * database relation existing;
> * audit/activity logging;
> * business logic selain kebutuhan auto-generation Kode pada create.

## Dampak dan catatan

> Kewajiban membuat Kode Pekerjaan Jasa secara manual meningkatkan kemungkinan:
>
> * typo;
> * kode duplikat;
> * format kode tidak konsisten;
> * convention berbeda antar user.
>
> Auto-generation menyederhanakan flow menjadi:
>
> ```text
> Nama Pekerjaan Jasa
>       ↓
> Kode optional
>       ↓
> Simpan
>       ↓
> Kode kosong?
>   ├─ tidak → gunakan kode manual
>   └─ ya    → backend generate kode unik
> ```
>
> Layout saat ini juga kurang scalable karena setiap record langsung berada dalam mode edit. Jika jumlah master Pekerjaan Jasa meningkat, halaman akan semakin panjang dan sulit dipindai.
>
> Halaman ini sebaiknya mengikuti pola yang sama dengan:
>
> ```text
> Material
>    │
> Unit
>    │
> PoP
>    │
> Pekerjaan Jasa
>    ▼
> Satu pola Master Data
> ```
>
> Acceptance utama:
>
> * Halaman Pekerjaan Jasa menggunakan design language yang sama dengan Unit dan PoP.
> * Form Tambah Pekerjaan Jasa lebih compact dan responsive.
> * Field Kode boleh dikosongkan pada create.
> * Frontend tidak lagi memblokir create karena Kode kosong.
> * Backend menerima Kode kosong.
> * Backend membuat Kode otomatis jika kosong.
> * Kode hasil auto-generation unik.
> * Kode manual tetap digunakan apabila user mengisinya.
> * Kode existing tidak berubah.
> * Reuse generator existing apabila tersedia.
> * Daftar Pekerjaan Jasa dibuat lebih compact.
> * Record tidak selalu berada dalam mode edit.
> * Edit tetap bekerja.
> * Simpan perubahan tetap bekerja.
> * Nonaktifkan tetap bekerja.
> * Status menggunakan reusable badge jika ditampilkan.
> * Permission dan authorization tidak berubah.
> * Relasi/historical data tidak berubah.
> * Tidak ada dummy/fake data.
> * Tidak ada horizontal scrolling.
> * Tidak ada JavaScript/runtime error baru.

## Bukti QC

* `01-actual.png` — kondisi halaman Master Data Pekerjaan Jasa dan validasi field Kode ketika dikosongkan.
* `02-context.png` — konteks form Tambah Pekerjaan Jasa, daftar existing, edit, dan tombol Nonaktifkan.

> Tambahkan bukti berikutnya dengan nomor berurutan. Jangan mengganti atau menghapus bukti lama.

## Riwayat status

| Tanggal      | Status | Oleh   | Catatan                                                                                                                                              |
| ------------ | ------ | ------ | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| `2026-08-20` | `open` | Fatoni | Temuan dibuat. Halaman Pekerjaan Jasa perlu mengikuti design language Master Data dan field Kode perlu mendukung auto-generation ketika dikosongkan. |
