# QC-0009 — Konsistensi Design dan Auto-generate Kode PoP

| Field                     | Nilai                                      |
| ------------------------- | ------------------------------------------ |
| ID                        | `QC-0009`                                  |
| Prefix                    | `pop`                                      |
| Status                    | `open`                                     |
| Severity                  | `major`                                    |
| Tanggal/waktu pengujian   | `2026-08-20 14:48 WIB`                     |
| Reviewer                  | Fatoni                                     |
| Persona/role              | User THC                                   |
| Halaman atau URL produksi | https://deploythc.web.id/admin/master/pops |
| Browser/device            | Chrome / laptop Windows                    |
| GitHub Issue              | [Bangun fondasi UI bersama dan selaraskan halaman dashboard, master, project, mitra, user](https://github.com/thcjatim-bit/project-monitoring-system/issues/90) |

## Ringkasan

> Halaman **Master Data PoP** perlu diselaraskan dengan design language yang sama dengan modul lainnya. Selain itu, field **Kode PoP** saat membuat PoP baru saat ini wajib diisi manual; diharapkan field tersebut boleh dikosongkan dan sistem otomatis menghasilkan Kode PoP yang unik.

## Langkah reproduksi

1. Buka `https://deploythc.web.id/admin/master/pops`.
2. Masuk sebagai persona/role **User THC**.
3. Buka menu **PoP** pada bagian **Master Data**.
4. Pada bagian **Tambah PoP**, isi field Nama.
5. Biarkan field **Kode** kosong.
6. Coba simpan PoP baru.
7. Perhatikan browser menampilkan validasi `Please fill in this field.` pada field Kode sehingga PoP tidak dapat dibuat tanpa kode manual.
8. Perhatikan juga tampilan bagian **Daftar PoP** yang langsung menampilkan seluruh field dalam mode edit.

## Hasil aktual

> Halaman PoP saat ini belum menggunakan design language yang konsisten dengan Command Center, Portfolio, Project, Mitra, User, Material, dan Unit.
>
> Form **Tambah PoP** terdiri dari field:
>
> * Kode;
> * Nama;
> * tombol Simpan.
>
> Field **Kode** mempunyai validasi required sehingga user diwajibkan membuat Kode PoP secara manual.
>
> Jika field Kode dikosongkan, browser menghentikan submit dan menampilkan:
>
> `Please fill in this field.`
>
> Pada bagian **Daftar PoP**, setiap record langsung ditampilkan sebagai form edit berisi:
>
> * Kode;
> * Nama;
> * tombol `Simpan perubahan`;
> * tombol `Nonaktifkan`.
>
> Pola tersebut menggunakan ruang lebih besar dari yang diperlukan dan akan menyebabkan halaman semakin panjang ketika jumlah PoP bertambah.

## Hasil yang diharapkan

> Halaman **Master Data PoP** mengikuti design language yang sama dengan modul Master Data dan workspace lainnya.
>
> Form **Tambah PoP** dibuat lebih compact, terstruktur, dan menggunakan shared component yang sama dengan halaman Unit dan Material.
>
> Contoh:
>
> ```text
> MASTER DATA
>
> PoP
> Kelola Point of Presence yang digunakan dalam operasional jaringan.
>
> ┌────────────────────────────────────────────────────┐
> │ Tambah PoP                                         │
> │                                                    │
> │ Kode PoP                  Nama PoP                 │
> │ [                        ] [                     ] │
> │ Kosongkan untuk dibuat                             │
> │ otomatis.                                          │
> │                                                    │
> │                                    [Simpan PoP]    │
> └────────────────────────────────────────────────────┘
> ```
>
> ### Auto-generate Kode PoP
>
> Field **Kode PoP tidak wajib diisi ketika membuat PoP baru**.
>
> Behavior yang diharapkan:
>
> ```text
> Kode diisi
>     ↓
> gunakan kode yang diberikan user
>
> Kode kosong
>     ↓
> backend membuat Kode PoP otomatis
>     ↓
> simpan kode unik
> ```
>
> User tetap mempunyai dua opsi:
>
> * mengisi Kode PoP manual; atau
> * mengosongkannya agar sistem membuat kode otomatis.
>
> Auto-generated Kode PoP harus:
>
> * unik;
> * tidak collision dengan PoP existing;
> * dibuat secara authoritative pada backend/server;
> * aman terhadap concurrent create;
> * mengikuti convention auto-generation yang sudah digunakan entity lain apabila tersedia;
> * tidak hanya dibuat melalui frontend/JavaScript.
>
> Validasi HTML/frontend yang saat ini membuat field Kode menjadi wajib harus disesuaikan agar **Kode optional hanya pada proses create**.
>
> Backend juga harus menerima Kode kosong/null pada create dan melakukan generation sebelum record disimpan.
>
> Jangan sekadar menghapus atribut `required` pada frontend apabila backend masih mewajibkan Kode.
>
> ### Reuse mekanisme generator
>
> Sebelum membuat generator khusus PoP, inspect terlebih dahulu mekanisme auto-generation pada:
>
> * Mitra;
> * Project;
> * Material;
> * Unit;
> * shared ID/code generator lain dalam project.
>
> Jika terdapat helper/service reusable, gunakan mekanisme yang sama.
>
> QC ini tidak menentukan format kode tertentu seperti:
>
> ```text
> POP-000001
> ```
>
> apabila aplikasi sudah mempunyai convention lain.
>
> Prioritaskan konsistensi dengan generator existing.
>
> ### Existing PoP
>
> Auto-generation hanya berlaku ketika **membuat PoP baru dengan field Kode kosong**.
>
> Kode PoP existing tidak boleh berubah.
>
> Contoh data saat ini:
>
> ```text
> Kode: eee
> Nama: pop rembige
> ```
>
> Data tersebut tetap dipertahankan dan tidak perlu dimigrasikan hanya untuk mengikuti format generator baru.
>
> ### Daftar PoP
>
> Daftar PoP dibuat menjadi management list/card yang lebih compact.
>
> Kondisi default tidak perlu selalu menampilkan input edit.
>
> Contoh:
>
> ```text
> PoP                                             1 PoP
>
> ┌────────────────────────────────────────────────────┐
> │ pop rembige                               [Aktif] │
> │ eee                                                │
> │                                                    │
> │                                [Edit] [Nonaktifkan]│
> └────────────────────────────────────────────────────┘
> ```
>
> atau menggunakan compact table/list jika lebih konsisten dengan shared component:
>
> ```text
> Kode        Nama                   Status       Aksi
> ────────────────────────────────────────────────────
> eee         pop rembige            Aktif        Edit
> ```
>
> Gunakan pola yang sama dengan halaman **Unit** agar Master Data memiliki satu bahasa design.

### Edit PoP

> Form edit hanya perlu tampil setelah user memilih `Edit`.
>
> Contoh:
>
> ```text
> Edit PoP
>
> Kode
> [ eee ]
>
> Nama
> [ pop rembige ]
>
>                       [Batal] [Simpan perubahan]
> ```
>
> Jika existing implementation menggunakan inline editing, mekanismenya boleh dipertahankan tetapi visual harus tetap compact.
>
> QC ini tidak mengubah business rule apakah Kode PoP existing boleh diedit.

### Status PoP

> Jika status Aktif/Nonaktif ditampilkan, gunakan reusable status badge/chip yang sama dengan modul lain:
>
> ```text
> [Aktif]
> ```
>
> Jangan membuat status style khusus hanya untuk PoP.

### Hierarchy aksi

> Gunakan hierarchy tombol yang konsisten:
>
> * `Simpan PoP` → primary.
> * `Simpan perubahan` → primary.
> * `Edit` / `Batal` → secondary.
> * `Nonaktifkan` → state/warning action.
>
> Tombol `Nonaktifkan` tidak perlu mempunyai treatment visual yang sama dengan tombol simpan.

### Responsive

> Pada desktop, form Tambah PoP dapat menggunakan dua kolom:
>
> ```text
> Kode PoP       Nama PoP
> [        ]     [        ]
> ```
>
> Pada tablet/mobile, field berubah menjadi satu kolom:
>
> ```text
> Kode PoP
> Nama PoP
> Simpan PoP
> ```
>
> Tidak boleh terdapat horizontal page scrolling.

### Ketentuan implementasi

> Reuse shared component/design system dari QC sebelumnya, terutama:
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
> Jangan membuat design system baru khusus PoP.
>
> Perubahan tidak boleh mengubah:
>
> * permission;
> * authorization;
> * penggunaan PoP pada Project atau operasional;
> * historical references;
> * relasi database existing;
> * activate/deactivate behavior;
> * audit/activity logging;
> * business logic selain kebutuhan auto-generation Kode pada create.

## Dampak dan catatan

> Kewajiban memasukkan Kode PoP secara manual menambah pekerjaan user dan berpotensi menghasilkan:
>
> * format kode yang tidak konsisten;
> * typo;
> * kode duplikat;
> * convention yang berbeda antar user.
>
> Dengan auto-generation, proses pembuatan PoP menjadi lebih sederhana:
>
> ```text
> Nama PoP
>    ↓
> optional Kode
>    ↓
> Simpan
>    ↓
> Kode kosong?
>   ├─ tidak → gunakan kode manual
>   └─ ya    → backend generate kode unik
> ```
>
> Layout daftar saat ini juga kurang scalable karena seluruh PoP selalu berada dalam mode edit. Jika data bertambah banyak, halaman akan semakin panjang dan sulit dipindai.
>
> Setelah perubahan, halaman PoP diharapkan mengikuti pola yang sama dengan halaman Unit:
>
> ```text
> Material
>    │
> Unit
>    │
> PoP
>    ▼
> Satu pola Master Data
> ```
>
> Acceptance utama:
>
> * Halaman PoP menggunakan design language yang sama dengan Unit dan modul lainnya.
> * Form Tambah PoP dibuat lebih compact dan responsive.
> * Kode PoP boleh dikosongkan saat create.
> * Browser/frontend tidak lagi memblokir create hanya karena Kode kosong.
> * Backend menerima Kode kosong pada create.
> * Backend otomatis membuat Kode PoP jika kosong.
> * Kode hasil auto-generation harus unik.
> * Kode manual tetap digunakan jika user mengisinya.
> * Kode PoP existing tidak berubah.
> * Generator reusable existing digunakan apabila tersedia.
> * Daftar PoP lebih compact.
> * Record tidak harus selalu tampil dalam mode edit.
> * Edit tetap bekerja.
> * Simpan perubahan tetap bekerja.
> * Nonaktifkan tetap bekerja.
> * Permission dan authorization tidak berubah.
> * Historical/reference data tidak berubah.
> * Tidak ada dummy/fake data.
> * Tidak ada horizontal scrolling.
> * Tidak ada JavaScript/runtime error baru.

## Bukti QC

* `01-actual.png` — kondisi halaman Master Data PoP dan validasi field Kode ketika dikosongkan.

> Tambahkan bukti berikutnya dengan nomor berurutan. Jangan mengganti atau menghapus bukti lama.

## Riwayat status

| Tanggal      | Status | Oleh   | Catatan                                                                                                                                       |
| ------------ | ------ | ------ | --------------------------------------------------------------------------------------------------------------------------------------------- |
| `2026-08-20` | `open` | Fatoni | Temuan dibuat. Halaman PoP perlu mengikuti design language Master Data dan field Kode PoP perlu mendukung auto-generation ketika dikosongkan. |
