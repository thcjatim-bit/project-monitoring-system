# QC-0004 — konsistensi design dan form modul Project

| Field                     | Nilai                                                                            |
| ------------------------- | -------------------------------------------------------------------------------- |
| ID                        | `QC-0004`                                                                        |
| Prefix                    | `project`                                                                        |
| Status                    | `open`                                                                           |
| Severity                  | `minor`                                                                          |
| Tanggal/waktu pengujian   | `2026-08-20 14:16 WIB`                                                           |
| Reviewer                  | Fatoni                                                                           |
| Persona/role              | User THC                                                                         |
| Halaman atau URL produksi | `https://deploythc.web.id/projects` dan `https://deploythc.web.id/projects/buat` |
| Browser/device            | Chrome / laptop Windows                                                          |
| GitHub Issue              | [Bangun fondasi UI bersama dan selaraskan halaman dashboard, master, project, mitra, user](https://github.com/thcjatim-bit/project-monitoring-system/issues/90) |
| Related QC                | `QC-0001`, `QC-0002`, `QC-0003`                                                  |

## Ringkasan

Halaman **Project** dan **Tambah Project** perlu diselaraskan dengan design language yang digunakan pada **Command Center** dan **Portfolio Cockpit**.

Saat ini kedua halaman secara fungsional dapat digunakan, tetapi layout, form density, penggunaan ruang, button placement, dan pola pemilihan Mitra belum konsisten dengan dashboard/workspace lainnya.

Khusus pada halaman **Tambah Project**, pemilihan Mitra saat ini menggunakan kombinasi:

* field `Cari Mitra (kode atau nama)`;
* field/list `Mitra`;
* daftar pilihan yang tampil seperti native list/select dengan scrollbar.

Pola tersebut perlu diganti menggunakan **reusable searchable dropdown yang sama dengan QC-0003** sehingga user cukup:

```text
klik → ketik nama/kode → pilih Mitra
```

Target perubahan adalah membuat seluruh modul Project terasa sebagai bagian dari satu aplikasi dan satu design system, tanpa mengubah business logic Project.

---

# A. Halaman `/projects`

## Hasil aktual

Halaman Project saat ini menampilkan:

* heading `Project`;
* link `Tambah Project`;
* satu panel besar berisi Project;
* nama Project;
* field edit nama;
* tombol `Simpan perubahan`;
* tombol `Hapus`.

Pada kondisi yang diuji, satu Project menggunakan card horizontal yang sangat lebar meskipun informasi yang ditampilkan hanya sedikit.

Struktur kurang lebih:

```text
Project

Tambah Project

┌──────────────────────────────────────────────────────────────┐
│ aa — kelurahan rembiga                                       │
│                                                              │
│ Nama                      [Simpan perubahan]                  │
│ [kelurahan rembiga]                                          │
│                                                              │
│ [Hapus]                                                      │
└──────────────────────────────────────────────────────────────┘
```

Akibatnya:

* terlalu banyak whitespace;
* hierarchy informasi Project kurang jelas;
* aksi edit dan hapus terlihat seperti form administrasi mentah;
* halaman kurang konsisten dengan Command Center dan Portfolio;
* penggunaan ruang horizontal desktop belum optimal.

---

## Hasil yang diharapkan

Halaman `/projects` menggunakan pola **management list** yang compact.

### Header halaman

Gunakan header yang konsisten:

```text
PROJECT

Project
Kelola Project yang berada dalam cakupan akses Anda.

                                      [+ Tambah Project]
```

`Tambah Project` sebaiknya menjadi **primary action button** pada area header, bukan link teks yang terpisah dari hierarchy halaman.

---

## Daftar Project

Project ditampilkan menggunakan salah satu pola existing yang paling sesuai:

* compact table;
* compact list;
* management card list.

Prioritaskan komponen yang sudah digunakan di design system project.

Contoh:

```text
┌───────────────────────────────────────────────────────────────┐
│ Project                                            1 Project  │
├───────────────────────────────────────────────────────────────┤
│ aa                                                            │
│ kelurahan rembiga                                             │
│                                                               │
│ Mitra: pt abc                         [Edit] [Hapus]           │
└───────────────────────────────────────────────────────────────┘
```

atau jika menggunakan table:

```text
┌───────────────────────────────────────────────────────────────┐
│ Project        Nama                Mitra              Aksi     │
├───────────────────────────────────────────────────────────────┤
│ aa             kelurahan rembiga   pt abc         Edit · Hapus│
└───────────────────────────────────────────────────────────────┘
```

Jangan menampilkan informasi yang tidak tersedia atau tidak authorized hanya demi mengisi desain.

---

## Edit Project

Form edit tidak perlu selalu terbuka apabila pola component existing memungkinkan mode edit yang lebih compact.

Contoh behavior:

```text
Default:

kelurahan rembiga                         [Edit]


Setelah Edit:

Nama Project
[ kelurahan rembiga                       ]

                     [Batal] [Simpan perubahan]
```

Jika existing architecture lebih aman mempertahankan inline form, layout boleh tetap inline tetapi harus dibuat compact dan konsisten.

### Tombol

Gunakan hierarchy:

* `Simpan perubahan` → primary;
* `Batal` → secondary apabila tersedia;
* `Hapus` → destructive.

Tombol `Hapus` tidak boleh terlihat sama dengan primary button.

Gunakan destructive styling yang sudah tersedia dalam design system.

Jika aplikasi sudah memiliki confirmation dialog untuk destructive action, reuse dialog tersebut.

Jangan mengubah behavior delete atau authorization existing.

---

# B. Halaman `/projects/buat`

## Hasil aktual

Form Tambah Project saat ini menggunakan satu card yang sangat lebar dengan form tersebar secara horizontal.

Field terdiri dari:

* ID Project;
* Nama;
* Cari Mitra;
* Mitra;
* tombol Simpan.

Visual saat ini kurang lebih:

```text
Tambah Project

┌──────────────────────────────────────────────────────────────┐
│                                                              │
│ ID Project    Nama      Cari Mitra          Mitra            │
│ [        ]    [      ]  [ketik...]          [list         ]  │
│                                               pt abc          │
│                                               ...             │
│                                                              │
│ [Simpan]                                                     │
└──────────────────────────────────────────────────────────────┘
```

Masalah:

* form terlalu melebar;
* jarak antar field terlalu besar;
* alignment label dan input kurang rapi;
* dropdown Mitra berbeda dari pola dropdown Portfolio;
* terdapat field pencarian Mitra terpisah dari field pemilihan Mitra;
* daftar Mitra terlihat seperti native list/select;
* form menggunakan area desktop yang besar meskipun hanya mempunyai sedikit field;
* hierarchy action kurang jelas.

---

# Hasil yang diharapkan — Tambah Project

Gunakan form card compact yang sama dengan design language workspace.

Contoh:

```text
PROJECT

Tambah Project
Buat Project baru dan tentukan Mitra pemiliknya.

┌──────────────────────────────────────────────────────┐
│ Detail Project                                       │
│                                                      │
│ ID Project                                           │
│ [ Otomatis jika dikosongkan                       ]  │
│                                                      │
│ Nama Project                                         │
│ [                                                 ]  │
│                                                      │
│ Mitra                                                │
│ [ Pilih Mitra                                    ▾ ] │
│                                                      │
│                               [Batal] [Simpan]       │
└──────────────────────────────────────────────────────┘
```

Card form tidak perlu memenuhi hampir seluruh layar.

Gunakan `max-width` yang sesuai dengan design system existing, misalnya secara konseptual sekitar:

```text
700–900px
```

Nilai sebenarnya harus mengikuti container/grid existing.

---

# C. Standardisasi Dropdown Mitra

Dropdown Mitra pada `/projects/buat` **wajib menggunakan reusable searchable dropdown dari QC-0003**.

Jangan membuat komponen dropdown Project yang terpisah.

## Behavior yang diinginkan

User klik field:

```text
┌─────────────────────────────────────────────┐
│ Pilih Mitra                             ▴   │
├─────────────────────────────────────────────┤
│ Ketik nama atau kode Mitra                  │
├─────────────────────────────────────────────┤
│ pt abc - [1]                                │
│ Mitra lainnya                               │
│ ...                                         │
└─────────────────────────────────────────────┘
```

User dapat mengetik:

```text
abc
```

dan mendapatkan hasil:

```text
pt abc
```

Jika Mitra mempunyai identifier/code yang memang tersedia dan relevan, pencarian juga harus dapat mencocokkan identifier tersebut.

---

## Hilangkan pola pencarian ganda

Jika searchable select sudah mempunyai search input internal, maka field terpisah:

```text
Cari Mitra (kode atau nama)
```

tidak lagi diperlukan.

Jangan membuat user melakukan:

```text
ketik pada field A
        ↓
mencari hasil
        ↓
memilih pada field B
```

Target interaction:

```text
klik Mitra
   ↓
ketik
   ↓
hasil terfilter
   ↓
pilih
```

Namun sebelum menghapus field existing, pastikan bahwa field tersebut memang hanya berfungsi sebagai helper pencarian dan bukan memiliki business logic/API contract terpisah yang diperlukan.

Jika terdapat logic penting, refactor interaction tanpa menghilangkan behavior yang dibutuhkan.

---

# D. Searchable Select Project

Gunakan component yang sama secara konseptual dengan:

```text
SearchableSelect
```

atau nama reusable component yang sudah ditentukan pada implementasi `QC-0003`.

Harus mendukung minimal:

```text
options
value
onChange
placeholder
searchable
clearable
disabled
loading
emptyMessage
getOptionLabel
getOptionValue
```

API sebenarnya menyesuaikan framework/project.

Jangan menduplikasi:

```text
ProjectMitraSelect
PortfolioMitraSelect
WarehouseMitraSelect
```

jika seluruh kebutuhan dapat ditangani satu reusable component.

Target:

```text
                     ┌── Portfolio / Project
SearchableSelect ────┼── Tambah Project / Mitra
                     ├── Warehouse
                     ├── Material
                     └── entity selector lainnya
```

---

# E. Input dan Form Design

Seluruh input mengikuti design language yang sama.

## Label

Gunakan label di atas field:

```text
Nama Project
[ Input ]
```

bukan layout label/input yang tersebar horizontal jika membuat form sulit dipindai.

## Tinggi control

Input, searchable select, dan button harus mempunyai tinggi yang konsisten.

## Focus state

Input yang aktif harus mempunyai focus indicator yang jelas dan konsisten.

## Placeholder

Gunakan placeholder yang membantu.

Contoh:

```text
ID Project
Kosongkan untuk dibuat otomatis
```

atau helper text di bawah input.

Jangan menjadikan penjelasan panjang sebagai bagian label:

```text
ID Project (kosongkan untuk otomatis)
```

jika design system mempunyai support helper text.

Contoh yang lebih rapi:

```text
ID Project
[                              ]
Kosongkan untuk dibuat otomatis.
```

---

# F. Layout Form

Pada desktop dapat menggunakan satu atau dua kolom sesuai jenis field.

Contoh:

```text
┌─────────────────────────┬─────────────────────────┐
│ ID Project              │ Nama Project            │
│ [                    ]  │ [                    ]  │
└─────────────────────────┴─────────────────────────┘

Mitra
[ Pilih Mitra                                      ▾ ]

                                      [Batal] [Simpan]
```

Untuk form dengan hanya sedikit field, jangan memaksakan empat field dalam satu horizontal row seperti kondisi sekarang.

---

# G. Responsive Behavior

## Desktop

* form berada dalam content container yang proporsional;
* field dapat menggunakan grid 2 kolom;
* searchable select menggunakan lebar yang cukup;
* action berada pada bagian bawah form.

## Tablet

Grid 2 kolom dapat berubah menjadi satu kolom apabila ruang tidak mencukupi.

## Mobile

Urutan menjadi:

```text
ID Project
Nama Project
Mitra
Batal / Simpan
```

Seluruh control menggunakan lebar penuh sesuai kebutuhan.

Tidak boleh terdapat horizontal scrolling.

Dropdown/popover tidak boleh keluar dari viewport.

---

# H. Satu Bahasa Design

Modul Project harus mengikuti pola dari:

* `QC-0001` — Command Center;
* `QC-0002` — Portfolio Cockpit;
* `QC-0003` — Searchable Dropdown.

Gunakan konsistensi untuk:

### Typography

* page eyebrow/context;
* page title;
* description;
* section heading;
* label;
* helper text.

### Card

* background;
* border;
* radius;
* shadow;
* padding.

### Form

* input height;
* border;
* radius;
* focus state;
* error state;
* disabled state.

### Button

* primary;
* secondary;
* destructive.

### Dropdown

* searchable;
* selected state;
* hover state;
* keyboard navigation;
* menu shadow;
* scrollbar;
* empty state.

Target:

```text
Command Center
      │
Portfolio
      │
Project
      │
Tambah Project
      │
Warehouse
      ▼
Satu Design System
```

---

# I. Dropdown Accessibility

Searchable Mitra harus mendukung minimal:

* `Tab` → fokus;
* `Enter` / `Space` → membuka;
* typing → mencari;
* `Arrow Up` / `Arrow Down` → berpindah option;
* `Enter` → memilih;
* `Esc` → menutup.

Focus indicator harus terlihat.

Dropdown tidak boleh hanya dapat digunakan menggunakan mouse.

---

# J. Dropdown Overlay

Pastikan option menu:

* tampil di atas card;
* tidak terpotong parent;
* tidak berada di belakang sidebar/header;
* tidak memperbesar tinggi form ketika dibuka;
* tidak menyebabkan page horizontal scroll.

Periksa:

```text
overflow
z-index
stacking context
portal/popover
```

dan gunakan pattern yang sama dengan `QC-0003`.

---

# K. Loading dan Empty State

Saat Mitra sedang dimuat:

```text
Memuat Mitra...
```

Saat tidak ditemukan:

```text
Mitra tidak ditemukan.
```

Saat user tidak mempunyai Mitra authorized:

```text
Tidak ada Mitra yang tersedia dalam cakupan Anda.
```

Jangan menampilkan data di luar authorization user.

---

# L. Validation

Validation error harus muncul dekat field terkait.

Contoh:

```text
Nama Project
[                                      ]
Nama Project wajib diisi.
```

Jangan hanya menampilkan error umum yang tidak menunjukkan field bermasalah jika backend/frontend sekarang sudah dapat mengidentifikasi error field.

Gunakan styling error yang sama pada seluruh form aplikasi.

---

# M. Ketentuan implementasi

Perubahan ini berfokus pada:

```text
UI
UX
layout
form consistency
component reuse
dropdown consistency
```

Jangan mengubah:

* business logic Project;
* ownership Project;
* permission;
* authorization;
* scope Mitra;
* ID generation;
* database schema;
* API contract tanpa kebutuhan;
* validation bisnis;
* audit activity;
* delete semantics.

Sebelum implementasi:

1. Inspect implementasi `/projects`.
2. Inspect implementasi `/projects/buat`.
3. Inspect component/design hasil `QC-0001` dan `QC-0002`.
4. Inspect reusable searchable dropdown hasil `QC-0003`.
5. Reuse design token/component existing.
6. Jangan membuat design system baru khusus Project.
7. Jangan membuat dropdown baru jika `QC-0003` sudah menyediakan component yang sesuai.
8. Jangan menambah UI dependency baru tanpa kebutuhan.
9. Jangan membuat dummy/fake data.
10. Pertahankan authorized scope existing.

---

# N. Acceptance Criteria

## `/projects`

* [ ] Halaman Project menggunakan design language yang sama dengan Command Center dan Portfolio.
* [ ] Heading dan description menggunakan hierarchy yang konsisten.
* [ ] `Tambah Project` tampil sebagai primary action yang jelas.
* [ ] Daftar Project dibuat lebih compact.
* [ ] Satu Project tidak menggunakan ruang horizontal/vertikal berlebihan.
* [ ] Informasi Project tetap tersedia.
* [ ] Edit Project tetap berfungsi.
* [ ] Simpan perubahan tetap berfungsi.
* [ ] Hapus Project tetap berfungsi sesuai permission existing.
* [ ] Tombol destructive dapat dibedakan dari primary action.
* [ ] Tidak ada perubahan authorization.

## `/projects/buat`

* [ ] Halaman Tambah Project menggunakan card/form style yang konsisten.
* [ ] Form tidak tersebar terlalu lebar.
* [ ] Label dan input mempunyai alignment yang rapi.
* [ ] ID Project tetap dapat dikosongkan untuk mekanisme otomatis existing.
* [ ] Nama Project dapat diisi dan divalidasi.
* [ ] Mitra dapat dipilih.
* [ ] Tombol Simpan tetap bekerja.
* [ ] Error validation tetap ditampilkan secara jelas.
* [ ] Layout responsive.

## Dropdown Mitra

* [ ] Dropdown Mitra menggunakan reusable component dari `QC-0003`.
* [ ] Field pencarian terpisah tidak digunakan jika searchable select sudah menggantikannya sepenuhnya.
* [ ] Dropdown dapat diklik.
* [ ] User dapat mengetik untuk mencari.
* [ ] Search dapat menemukan nama Mitra.
* [ ] Search dapat menemukan code/identifier apabila tersedia.
* [ ] Search bersifat case-insensitive.
* [ ] Selected Mitra terlihat jelas.
* [ ] Dropdown panjang mempunyai internal scroll.
* [ ] Dropdown tidak terpotong card.
* [ ] Dropdown tidak berada di belakang component lain.
* [ ] Dropdown tidak mengubah tinggi layout saat dibuka.
* [ ] Keyboard navigation bekerja.
* [ ] Focus state terlihat.
* [ ] Empty state tersedia.
* [ ] Loading state tersedia jika diperlukan.
* [ ] Hanya Mitra authorized yang ditampilkan.

## Regression

* [ ] Project existing tetap dapat dibuka.
* [ ] Project existing tetap dapat diedit.
* [ ] Project baru tetap dapat dibuat.
* [ ] Auto-generated Project ID tetap bekerja sesuai behavior existing.
* [ ] Relasi Project–Mitra tidak berubah.
* [ ] Permission tidak berubah.
* [ ] Authorization tidak berubah.
* [ ] Audit/activity existing tidak rusak.
* [ ] Tidak ada JavaScript error.
* [ ] Tidak ada horizontal page scrolling.
* [ ] Tidak ada dummy/fake data.
* [ ] Tidak ada duplicate searchable-select implementation yang tidak diperlukan.

---

# Bukti QC

* `01-project-actual.png` — kondisi halaman `/projects` saat pengujian.
* `02-project-create-actual.png` — kondisi halaman `/projects/buat` saat pengujian.
* `03-searchable-dropdown-reference.png` — referensi searchable dropdown dari `QC-0003`.

> Tambahkan bukti berikutnya menggunakan nomor berurutan. Jangan mengganti atau menghapus bukti lama.

---

# Riwayat status

| Tanggal      | Status | Oleh   | Catatan                                                                                                                                                                                                       |
| ------------ | ------ | ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `2026-08-20` | `open` | Fatoni | Modul Project perlu diselaraskan dengan design language Command Center dan Portfolio. Form Tambah Project juga perlu menggunakan reusable searchable dropdown yang sama dengan QC-0003 untuk pemilihan Mitra. |
