# QC-0011 — Konsistensi Design dan Auto-generate Kode Warehouse

| Field                     | Nilai                                     |
| ------------------------- | ----------------------------------------- |
| ID                        | `QC-0011`                                 |
| Prefix                    | `warehouse`                               |
| Status                    | `open`                                    |
| Severity                  | `major`                                   |
| Tanggal/waktu pengujian   | `2026-08-20 14:53 WIB`                    |
| Reviewer                  | Fatoni                                    |
| Persona/role              | User THC                                  |
| Halaman atau URL produksi | https://deploythc.web.id/admin/warehouses |
| Browser/device            | Chrome / laptop Windows                   |
| GitHub Issue              | [Bangun fondasi UI bersama dan selaraskan halaman dashboard, master, project, mitra, user](https://github.com/thcjatim-bit/project-monitoring-system/issues/90) |

## Ringkasan

> Halaman **Assignment Warehouse** perlu diselaraskan dengan design language yang sama dengan modul lainnya. Selain itu, field **Kode Warehouse** saat membuat Warehouse baru saat ini wajib diisi manual; diharapkan field tersebut boleh dikosongkan dan sistem otomatis menghasilkan Kode Warehouse yang unik.

## Langkah reproduksi

1. Buka `https://deploythc.web.id/admin/warehouses`.
2. Masuk sebagai persona/role **User THC**.
3. Buka menu **Penugasan Warehouse** pada bagian **Warehouse**.
4. Pada bagian **Tambah Warehouse**, isi field Nama.
5. Pilih Pemilik Warehouse sesuai cakupan yang tersedia.
6. Biarkan field **Kode** kosong.
7. Coba simpan Warehouse baru.
8. Perhatikan browser menampilkan validasi `Please fill in this field.` sehingga Warehouse tidak dapat dibuat tanpa Kode manual.
9. Perhatikan Warehouse existing yang langsung menampilkan form edit, pengaturan Pemilik, tombol Nonaktifkan, selector User aktif, serta daftar assignment.
10. Perhatikan bahwa halaman menjadi panjang dan kurang compact ketika jumlah Warehouse serta assignment bertambah.

## Hasil aktual

> Halaman **Assignment Warehouse** saat ini belum mengikuti design language yang konsisten dengan Command Center, Portfolio, Project, Mitra, User, serta modul Master Data lainnya.
>
> Pada bagian **Tambah Warehouse** tersedia:
>
> * Kode;
> * Nama;
> * Pemilik;
> * tombol `Simpan Warehouse`.
>
> Field **Kode** saat ini menggunakan validasi required. Ketika Kode dikosongkan, browser menolak submit dan menampilkan:
>
> `Please fill in this field.`
>
> Warehouse existing langsung menampilkan seluruh kontrol edit, antara lain:
>
> * Kode;
> * Nama;
> * Pemilik;
> * `Simpan perubahan`;
> * `Nonaktifkan`;
> * selector `Pilih User aktif`;
> * tombol `Tugaskan`;
> * daftar User yang sudah ditugaskan;
> * tombol `Hapus assignment`.
>
> Seluruh informasi dan action tersebut ditampilkan secara terbuka sehingga hierarchy antara informasi Warehouse, edit data, status, dan assignment User belum jelas.
>
> Selector User aktif juga masih menggunakan select biasa sehingga akan semakin sulit digunakan ketika jumlah User bertambah.

## Hasil yang diharapkan

> Halaman **Assignment Warehouse** menggunakan design language yang sama dengan modul workspace lainnya dan reuse shared component yang sudah tersedia.
>
> Struktur halaman dibuat lebih compact dengan hierarchy yang jelas antara:
>
> 1. Tambah Warehouse.
> 2. Informasi Warehouse.
> 3. Edit Warehouse.
> 4. Assignment User.
> 5. Status/aksi Warehouse.

### Tambah Warehouse

> Form **Tambah Warehouse** dibuat lebih terstruktur.
>
> Contoh:
>
> ```text
> WAREHOUSE
>
> Assignment Warehouse
> Kelola Warehouse, kepemilikan, dan penugasan operator.
>
> ┌──────────────────────────────────────────────────────┐
> │ Tambah Warehouse                                     │
> │                                                      │
> │ Kode Warehouse           Nama Warehouse              │
> │ [                      ] [                         ] │
> │ Kosongkan untuk dibuat                               │
> │ otomatis.                                            │
> │                                                      │
> │ Pemilik                                              │
> │ [ Pilih Pemilik                                  ▾ ] │
> │                                                      │
> │                              [Simpan Warehouse]      │
> └──────────────────────────────────────────────────────┘
> ```

### Auto-generate Kode Warehouse

> Field **Kode Warehouse tidak wajib diisi ketika membuat Warehouse baru**.
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
> User tetap dapat:
>
> * memasukkan Kode Warehouse secara manual; atau
> * mengosongkan Kode agar sistem membuatnya otomatis.
>
> Auto-generated Kode Warehouse harus:
>
> * unik;
> * tidak collision dengan Warehouse existing;
> * dibuat secara authoritative pada backend/server;
> * aman terhadap concurrent create;
> * menggunakan convention/generator existing apabila tersedia;
> * tidak hanya dibuat menggunakan JavaScript/frontend.
>
> Validasi HTML/frontend yang saat ini menjadikan Kode required harus disesuaikan.
>
> Backend juga harus menerima Kode kosong/null ketika create dan melakukan generation sebelum record disimpan.
>
> Jangan hanya menghapus atribut `required` pada frontend apabila backend tetap mewajibkan nilai Kode.

### Reuse mekanisme generator

> Sebelum membuat generator Warehouse baru, inspect mekanisme auto-generation yang sudah tersedia pada:
>
> * Mitra;
> * Project;
> * Material;
> * Unit;
> * PoP;
> * Pekerjaan Jasa;
> * shared ID/code generator lain.
>
> Jika project sudah memiliki helper/service generator reusable, gunakan mekanisme tersebut.
>
> QC ini tidak menentukan format hardcoded tertentu seperti:
>
> ```text
> WH-000001
> ```
>
> apabila project sudah mempunyai convention lain.
>
> Prioritaskan konsistensi generator antar entity.

### Existing Warehouse

> Auto-generation hanya berlaku ketika **membuat Warehouse baru dengan Kode kosong**.
>
> Jangan mengubah Kode Warehouse existing.
>
> Contoh data existing seperti:
>
> ```text
> bb1 → testwhmitra
> bb  → test
> ```
>
> tetap dipertahankan.
>
> Tidak diperlukan migrasi kode lama hanya untuk mengikuti format generator baru.

### Daftar Warehouse

> Warehouse existing dibuat menjadi management card/list yang lebih compact.
>
> Kondisi default sebaiknya menampilkan informasi utama:
>
> ```text
> ┌───────────────────────────────────────────────────────┐
> │ testwhmitra                               [Aktif]    │
> │ bb1                                                   │
> │                                                       │
> │ Pemilik       pt abc                                  │
> │ Operator      4 User                                  │
> │                                                       │
> │                    [Kelola Assignment] [Edit]         │
> │                                      [Nonaktifkan]    │
> └───────────────────────────────────────────────────────┘
> ```
>
> Jangan menampilkan seluruh input edit secara permanen jika tidak sedang diperlukan.

### Edit Warehouse

> Setelah user memilih `Edit`, tampilkan form:
>
> ```text
> Edit Warehouse
>
> Kode                       Nama
> [ bb1                   ]  [ testwhmitra             ]
>
> Pemilik
> [ pt abc                                             ▾ ]
>
>                       [Batal] [Simpan perubahan]
> ```
>
> Jika implementation existing menggunakan inline editing, mekanisme tersebut dapat dipertahankan selama read state dan edit state dapat dibedakan dengan jelas.
>
> QC ini tidak mengubah business rule apakah Kode Warehouse existing boleh diedit.

### Status Warehouse

> Status seperti:
>
> ```text
> Aktif
> ```
>
> menggunakan reusable badge/chip:
>
> ```text
> [Aktif]
> ```
>
> Style harus sama dengan status pada Mitra, User, Material, Unit, PoP, dan Pekerjaan Jasa.

### Pemilik Warehouse

> Selector **Pemilik** tetap mempertahankan authorization dan business logic existing.
>
> Jika pilihan hanya berupa scope kecil seperti:
>
> * THC;
> * Mitra tertentu;
>
> gunakan select component family yang sama dengan modul lain.
>
> Jika pilihan Mitra dapat banyak, gunakan reusable searchable select dari `QC-0003`.
>
> Search dapat mencocokkan:
>
> * nama Mitra;
> * kode Mitra jika tersedia.
>
> Jangan menampilkan Mitra di luar cakupan akses User THC.

### Assignment User

> Assignment operator/User dipisahkan secara visual dari form edit Warehouse.
>
> Contoh:
>
> ```text
> Operator Warehouse                                 4 User
>
> [ Cari dan pilih User aktif                     ▾ ] [Tugaskan]
>
> ┌─────────────────────────────────────────────────────┐
> │ a be ce                                             │
> │ sugeng@abc.com                         [Hapus]      │
> ├─────────────────────────────────────────────────────┤
> │ Ahmad Fatoni Hakim                                  │
> │ thcjatim@gmail.com                     [Hapus]      │
> ├─────────────────────────────────────────────────────┤
> │ daffa                                               │
> │ daffa@gmail.com                        [Hapus]      │
> └─────────────────────────────────────────────────────┘
> ```
>
> Daftar assignment harus mudah dibaca dan tidak menempel langsung dengan input edit Warehouse.

### Searchable User selector

> Selector:
>
> `Pilih User aktif`
>
> sebaiknya menggunakan reusable searchable dropdown dari `QC-0003` karena jumlah User dapat bertambah.
>
> Behavior:
>
> ```text
> klik
>   ↓
> ketik nama / email
>   ↓
> hasil terfilter
>   ↓
> pilih User
>   ↓
> Tugaskan
> ```
>
> Search minimal mendukung:
>
> * nama User;
> * email User.
>
> Identifier lain dapat didukung jika memang tersedia dan relevan.
>
> Hanya User yang:
>
> * aktif;
> * authorized;
> * eligible untuk Warehouse terkait;
>
> yang boleh ditampilkan sesuai business logic existing.
>
> Jangan memperluas authorization hanya untuk kebutuhan search.

### User yang sudah ditugaskan

> User yang sudah mempunyai assignment pada Warehouse sebaiknya tidak muncul sebagai pilihan valid lagi jika behavior existing memang mencegah duplicate assignment.
>
> Jika tetap muncul dalam hasil pencarian karena kebutuhan teknis, tampilkan sebagai disabled/already assigned sesuai pola existing.
>
> Jangan mengubah aturan duplicate assignment yang sudah ada.

### Hapus Assignment

> `Hapus assignment` merupakan penghapusan relasi User dengan Warehouse, bukan penghapusan User.
>
> Gunakan action hierarchy yang sesuai:
>
> * `Tugaskan` → primary.
> * `Hapus assignment` → secondary/destructive relationship action sesuai design system.
>
> Jangan menggunakan wording atau styling yang membuat user mengira akun User akan dihapus.
>
> Business logic penghapusan assignment tidak berubah.

### Nonaktifkan Warehouse

> `Nonaktifkan` merupakan state-changing action.
>
> Gunakan warning/state treatment yang konsisten dan berbeda dari:
>
> * Simpan;
> * Tugaskan;
> * Hapus assignment.
>
> Menonaktifkan Warehouse tetap tidak boleh menghapus histori stok sesuai business rule existing.

### Feedback

> Pesan:
>
> `Penugasan Warehouse disimpan.`
>
> sebaiknya menggunakan reusable success alert/toast dan tidak menjadi bagian permanen dari header halaman.
>
> Gunakan pola feedback yang sama untuk:
>
> * create Warehouse;
> * update Warehouse;
> * assignment User;
> * remove assignment;
> * deactivate Warehouse.

### Responsive

> Pada desktop:
>
> * form create dapat menggunakan grid dua kolom;
> * card Warehouse menggunakan lebar secara proporsional;
> * assignment list tetap compact.
>
> Pada tablet/mobile:
>
> ```text
> Kode
> Nama
> Pemilik
> Simpan
> ```
>
> menjadi satu kolom.
>
> Selector User dan tombol `Tugaskan` dapat stack bila ruang tidak cukup.
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
> * searchable select;
> * normal select;
> * helper text;
> * status badge;
> * button variants;
> * alert/toast;
> * management list/card.
>
> Jangan membuat design system atau dropdown implementation baru khusus Warehouse.
>
> Perubahan tidak boleh mengubah:
>
> * permission;
> * authorization;
> * ownership semantics;
> * relasi Warehouse–Mitra;
> * relasi Warehouse–User;
> * aturan operator;
> * stock history;
> * stock calculation;
> * transaction access control;
> * activate/deactivate behavior;
> * assignment semantics;
> * historical data;
> * database relation existing;
> * audit/activity logging;
> * business logic selain kebutuhan auto-generation Kode pada create.

## Dampak dan catatan

> Kewajiban mengisi Kode Warehouse secara manual menyebabkan proses create tidak konsisten dengan pola auto-generation yang diharapkan pada master/entity lainnya dan meningkatkan risiko:
>
> * typo;
> * kode duplikat;
> * format tidak konsisten;
> * convention berbeda antar user.
>
> Flow yang diharapkan:
>
> ```text
> Nama Warehouse
>      +
> Pemilik
>      +
> optional Kode
>      ↓
> Simpan
>      ↓
> Kode kosong?
>   ├─ tidak → gunakan kode manual
>   └─ ya    → backend generate kode unik
> ```
>
> Layout saat ini juga kurang scalable karena data Warehouse, form edit, assignment User, dan action bercampur dalam satu area panjang.
>
> Dengan pemisahan visual:
>
> ```text
> Warehouse
>    │
>    ├── Informasi
>    ├── Edit
>    └── Assignment User
> ```
>
> halaman akan lebih mudah dipindai dan tetap nyaman ketika jumlah Warehouse maupun operator bertambah.
>
> Acceptance utama:
>
> * Halaman Assignment Warehouse menggunakan design language yang sama dengan modul lainnya.
> * Form Tambah Warehouse lebih compact dan responsive.
> * Field Kode Warehouse boleh dikosongkan saat create.
> * Frontend tidak memblokir create ketika Kode kosong.
> * Backend menerima Kode kosong.
> * Backend membuat Kode Warehouse otomatis jika kosong.
> * Kode hasil auto-generation unik.
> * Kode manual tetap digunakan bila diisi.
> * Kode Warehouse existing tidak berubah.
> * Reuse generator existing apabila tersedia.
> * Daftar Warehouse dibuat lebih compact.
> * Read state dan edit state dapat dibedakan.
> * Status menggunakan reusable badge.
> * Pemilik menggunakan shared select component.
> * Selector User aktif menggunakan reusable searchable dropdown.
> * Search User minimal dapat menggunakan nama dan email.
> * Hanya User authorized/eligible yang tampil.
> * Assignment User tetap bekerja.
> * Hapus assignment tetap bekerja.
> * Duplicate assignment tidak diperbolehkan sesuai behavior existing.
> * Nonaktifkan Warehouse tetap bekerja.
> * Histori stok tidak berubah ketika Warehouse dinonaktifkan.
> * Permission dan authorization tidak berubah.
> * Tidak ada dummy/fake data.
> * Tidak ada horizontal scrolling.
> * Tidak ada JavaScript/runtime error baru.

## Bukti QC

* `01-actual.png` — kondisi halaman Assignment Warehouse saat ini, termasuk Warehouse existing dan daftar assignment User.
* `02-context.png` — kondisi ketika field Kode Warehouse dikosongkan dan browser menampilkan validasi `Please fill in this field.`.

> Tambahkan bukti berikutnya dengan nomor berurutan. Jangan mengganti atau menghapus bukti lama.

## Riwayat status

| Tanggal      | Status | Oleh   | Catatan                                                                                                                                                                                                                                          |
| ------------ | ------ | ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `2026-08-20` | `open` | Fatoni | Temuan dibuat. Halaman Assignment Warehouse perlu mengikuti design language workspace, Kode Warehouse perlu mendukung auto-generation ketika kosong, dan assignment User perlu menggunakan interaction pattern yang lebih compact dan konsisten. |
