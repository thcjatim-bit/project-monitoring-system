# QC-0005 — konsistensi design Manajemen Mitra

| Field                     | Nilai                                      |
| ------------------------- | ------------------------------------------ |
| ID                        | `QC-0005`                                  |
| Prefix                    | `mitra`                                    |
| Status                    | `open`                                     |
| Severity                  | `minor`                                    |
| Tanggal/waktu pengujian   | `2026-08-20 14:24 WIB`                     |
| Reviewer                  | Fatoni                                     |
| Persona/role              | User THC                                   |
| Halaman atau URL produksi | https://deploythc.web.id/admin/mitras      |
| Browser/device            | Chrome / laptop Windows                    |
| GitHub Issue              | [Bangun fondasi UI bersama dan selaraskan halaman dashboard, master, project, mitra, user](https://github.com/thcjatim-bit/project-monitoring-system/issues/90) |
| Related QC                | `QC-0001`, `QC-0002`, `QC-0003`, `QC-0004` |

## Ringkasan

Halaman **Manajemen Mitra** secara fungsi sudah dapat digunakan, tetapi tampilan visualnya perlu diselaraskan dengan design language yang sudah diterapkan pada:

* Command Center;
* Portfolio Cockpit;
* Project;
* Tambah Project.

Perubahan pada QC ini hanya berfokus pada:

* layout;
* visual hierarchy;
* spacing;
* card;
* form;
* typography;
* button hierarchy;
* status;
* responsive behavior.

Tidak diperlukan perubahan business logic atau workflow Manajemen Mitra.

Target akhirnya adalah halaman Manajemen Mitra terasa sebagai bagian dari **satu workspace dan satu design system**, bukan halaman administrasi dengan style tersendiri.

---

## Langkah reproduksi

1. Buka `https://deploythc.web.id/admin/mitras`.
2. Login sebagai **User THC**.
3. Buka menu **Mitra**.
4. Perhatikan bagian:

   * heading `Manajemen Mitra`;
   * form `Onboarding Mitra`;
   * daftar Mitra;
   * informasi admin-mitra;
   * form edit Mitra;
   * tombol Simpan;
   * tombol Nonaktifkan;
   * tombol Hapus Mitra.
5. Bandingkan dengan design language Command Center, Portfolio, dan Project.

---

## Hasil aktual

Halaman saat ini menampilkan form onboarding secara horizontal dengan lima field:

* Kode Mitra;
* Nama Mitra;
* Nama admin-mitra;
* Email admin-mitra;
* Nomor WhatsApp.

Di bawahnya terdapat daftar Mitra dalam bentuk card besar.

Setiap Mitra menampilkan:

* nama;
* kode;
* status;
* tanggal dibuat;
* admin-mitra pertama;
* toggle/section `Edit Mitra`;
* field edit;
* tombol Simpan;
* tombol Nonaktifkan;
* tombol Hapus Mitra.

Struktur saat ini kurang lebih:

```text
Manajemen Mitra

Onboarding Mitra

┌─────────────────────────────────────────────────────────────┐
│ Kode    Nama    Admin    Email    WhatsApp                  │
│ [   ]   [   ]   [   ]    [   ]    [   ]                   │
│                                                             │
│ [Buat Mitra dan Admin]                                      │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ PT. kartonyono — MTR-2608-0001 — Aktif                     │
│ Dibuat 20 Aug 2026                                          │
│ Admin-mitra pertama: ...                                    │
│ ▼ Edit Mitra                                                │
│ [Kode] [Nama] [Simpan Mitra]                                │
│ [Nonaktifkan] [Hapus Mitra]                                 │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ pt abc — 1 — Aktif                                          │
│ Dibuat 19 Aug 2026                                          │
│ Admin-mitra pertama: ...                                    │
│ ▶ Edit Mitra                                                │
│ [Nonaktifkan] [Hapus Mitra]                                 │
└─────────────────────────────────────────────────────────────┘
```

Secara fungsi sudah cukup jelas, tetapi visual masih terasa seperti form administrasi dasar.

Beberapa hal yang perlu diperbaiki:

* form onboarding terlalu melebar dalam satu baris;
* hierarchy antara informasi Mitra dan action belum cukup jelas;
* status `Aktif` masih tampil sebagai teks biasa;
* tombol primary, secondary, warning, dan destructive belum memiliki hierarchy visual yang kuat;
* card Mitra menggunakan ruang horizontal besar walaupun informasi relatif sedikit;
* form edit terlihat menyatu dengan informasi read-only;
* spacing antar elemen belum sepenuhnya konsisten;
* desain belum mengikuti pola management page yang digunakan pada modul lainnya.

---

# Hasil yang diharapkan

## 1. Page Header

Gunakan hierarchy halaman yang sama dengan modul lainnya.

Contoh:

```text
MITRA & USER

Manajemen Mitra
Kelola Mitra dan administrator utama dalam cakupan akses Anda.
```

Jika diperlukan, informasi keberhasilan seperti:

```text
Mitra dan administrator berhasil dibuat.
```

jangan diletakkan sebagai subtitle halaman permanen.

Gunakan komponen feedback seperti:

* success alert;
* toast;
* inline notification;

sesuai component existing.

Pesan keberhasilan harus terasa sebagai **state hasil aksi**, bukan bagian dari title halaman.

---

# 2. Onboarding Mitra

Form onboarding tetap berada pada bagian atas halaman, tetapi dibuat lebih terstruktur dan compact.

Contoh:

```text
┌──────────────────────────────────────────────────────────────┐
│ Onboarding Mitra                                             │
│ Buat Mitra baru sekaligus administrator utama.              │
│                                                              │
│ Kode Mitra                    Nama Mitra                      │
│ [ Kosongkan untuk otomatis ]  [ Nama Mitra                 ] │
│                                                              │
│ Nama admin-mitra             Email admin-mitra               │
│ [                         ]   [                            ]  │
│                                                              │
│ Nomor WhatsApp                                               │
│ [ 628...                                                   ] │
│                                                              │
│                               [Buat Mitra dan Admin]          │
└──────────────────────────────────────────────────────────────┘
```

Pada desktop, gunakan layout maksimal dua atau tiga kolom sesuai ukuran field.

Jangan memaksakan seluruh field berada dalam satu horizontal row.

---

## 3. Kode Mitra

Label:

```text
Kode Mitra
```

Helper text:

```text
Kosongkan untuk dibuat otomatis.
```

Lebih baik daripada menjadikan instruksi sebagai placeholder utama jika design system menyediakan helper text.

Mekanisme auto-generation existing tetap dipertahankan.

---

# 4. Daftar Mitra

Gunakan pola management card/list yang sama dengan Project.

Contoh:

```text
Mitra                                               2 Mitra

┌──────────────────────────────────────────────────────────────┐
│ PT. kartonyono                           [Aktif]              │
│ MTR-2608-0001                                                │
│                                                              │
│ Dibuat       20 Aug 2026 07:22                               │
│ Admin utama  udin · nopan@nopan.nopan                        │
│                                                              │
│                                      [Edit] [Nonaktifkan]     │
└──────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────┐
│ pt abc                                  [Aktif]              │
│ ID: 1                                                       │
│                                                              │
│ Dibuat       19 Aug 2026 06:04                               │
│ Admin utama  a be ce · sugeng@abc.com                        │
│                                                              │
│                                      [Edit] [Nonaktifkan]     │
└──────────────────────────────────────────────────────────────┘
```

Informasi utama:

1. Nama Mitra.
2. Kode/ID.
3. Status.
4. Tanggal dibuat.
5. Admin-mitra utama.
6. Aksi.

Gunakan whitespace secara proporsional.

---

# 5. Status Mitra

Status seperti:

```text
Aktif
```

jangan hanya menjadi teks setelah kode.

Gunakan reusable status badge/chip.

Contoh:

```text
[Aktif]
```

Style semantik:

* aktif → hijau/success;
* nonaktif → abu-abu atau warning sesuai design token existing.

Jangan membuat warna baru jika project sudah mempunyai status component.

---

# 6. Edit Mitra

Default state tidak perlu memperlihatkan form edit.

Contoh:

```text
PT. kartonyono                           [Aktif]

MTR-2608-0001
Admin utama: udin · nopan@nopan.nopan

                         [Edit] [Nonaktifkan]
```

Setelah user memilih **Edit**:

```text
┌──────────────────────────────────────────────────────────────┐
│ Edit Mitra                                                   │
│                                                              │
│ Kode Mitra                     Nama Mitra                    │
│ [ MTR-2608-0001             ]  [ PT. kartonyono           ] │
│                                                              │
│                             [Batal] [Simpan perubahan]       │
└──────────────────────────────────────────────────────────────┘
```

Jika implementation existing menggunakan accordion/collapse untuk Edit Mitra, pola tersebut boleh dipertahankan.

Namun styling harus konsisten dengan component disclosure/accordion existing.

---

# 7. Hierarchy Button

Gunakan hierarchy action yang jelas.

## Primary

```text
Buat Mitra dan Admin
Simpan perubahan
```

Gunakan primary button existing.

## Secondary

```text
Edit
Batal
```

Gunakan secondary/outline/ghost sesuai design system.

## Warning / state action

```text
Nonaktifkan
```

Harus secara visual berbeda dari primary action.

Jangan membuat `Nonaktifkan` tampak sama penting dengan `Simpan`.

## Destructive

```text
Hapus Mitra
```

Harus menggunakan destructive treatment existing.

Contoh:

```text
[Hapus Mitra]
```

dengan styling destructive.

Jangan menggunakan warna primary untuk aksi delete.

---

# 8. Hapus Mitra

UI destructive action harus mengikuti pola aplikasi.

Jika confirmation dialog sudah tersedia, reuse component tersebut.

Contoh flow:

```text
Hapus Mitra?
────────────────────────────────
Mitra "PT. kartonyono" akan dihapus.

Tindakan ini dapat mempengaruhi data terkait
sesuai aturan sistem yang berlaku.

                [Batal] [Hapus Mitra]
```

QC ini tidak meminta perubahan delete semantics.

Jangan:

* mengubah cascade behavior;
* mengubah soft-delete menjadi hard-delete;
* mengubah permission;
* mengubah backend delete logic.

Hanya styling/interaksi konfirmasi jika component existing tersedia.

---

# 9. Nonaktifkan Mitra

`Nonaktifkan` merupakan perubahan status, bukan destructive delete.

Secara visual harus dibedakan dari:

```text
Hapus Mitra
```

Contoh hierarchy:

```text
[Edit]  [Nonaktifkan]                 [Hapus Mitra]
```

atau pola action menu yang sudah digunakan project.

Jangan mengubah behavior business existing.

---

# 10. Informasi Admin-Mitra

Informasi:

```text
Admin-mitra pertama: udin · nopan@nopan.nopan
```

tetap dipertahankan, tetapi styling dapat dibuat lebih terstruktur.

Contoh:

```text
Admin utama
udin
nopan@nopan.nopan
```

atau compact:

```text
Admin utama   udin · nopan@nopan.nopan
```

Tidak perlu membuat sub-card tambahan jika hanya menambah noise visual.

---

# 11. Card Density

Card Mitra dibuat compact.

Hindari:

```text
1 Mitra = card sangat tinggi
```

apabila hanya memiliki sedikit informasi.

Target:

* lebih banyak data terlihat dalam satu viewport;
* tetap readable;
* action mudah ditemukan;
* edit state dapat diperluas hanya jika diperlukan.

---

# 12. Empty State

Jika tidak ada Mitra:

```text
Belum ada Mitra.

Buat Mitra pertama untuk mulai mengelola Project dan User.
```

Jika sesuai permission, dapat menampilkan CTA:

```text
[Buat Mitra]
```

Gunakan reusable empty-state component apabila tersedia.

---

# 13. Success dan Error Feedback

Success:

```text
Mitra dan administrator berhasil dibuat.
```

gunakan notification style yang konsisten.

Validation error:

```text
Email admin-mitra
[ invalid-email ]

Masukkan alamat email yang valid.
```

Error diletakkan dekat field terkait apabila backend/frontend menyediakan informasi tersebut.

Jangan mengubah validation business existing.

---

# 14. Responsive Behavior

## Desktop

* page menggunakan content width yang sama dengan Project/Portfolio;
* onboarding menggunakan grid maksimal 2–3 kolom;
* daftar Mitra tetap compact;
* action berada pada posisi konsisten.

## Tablet

Field onboarding dapat berubah menjadi dua atau satu kolom.

## Mobile

Urutan form:

```text
Kode Mitra
Nama Mitra
Nama admin-mitra
Email admin-mitra
Nomor WhatsApp
Buat Mitra dan Admin
```

Daftar Mitra menjadi satu kolom.

Action dapat wrap tetapi tidak menyebabkan horizontal scroll.

---

# 15. Satu Bahasa Design

Halaman Manajemen Mitra harus menggunakan component dan token yang sama dengan:

```text
Command Center
      │
Portfolio
      │
Project
      │
Mitra
      ▼
Satu Design System
```

Konsisten pada:

### Typography

* eyebrow;
* page title;
* description;
* section title;
* label;
* helper text.

### Layout

* content width;
* spacing;
* grid;
* section gap.

### Card

* border;
* radius;
* shadow;
* padding.

### Form

* label;
* input;
* placeholder;
* helper;
* error;
* focus state.

### Status

* badge/chip.

### Button

* primary;
* secondary;
* warning;
* destructive.

---

# 16. Ketentuan Implementasi

Scope QC ini hanya:

```text
UI
UX presentation
layout
visual consistency
component reuse
responsive design
```

Jangan mengubah:

* business logic onboarding Mitra;
* pembuatan admin-mitra;
* kode Mitra otomatis;
* permission;
* authorization;
* role;
* scope akses;
* validation business;
* database schema;
* API contract;
* email;
* nomor WhatsApp;
* relasi Mitra dengan Project/User;
* activate/deactivate behavior;
* delete behavior;
* audit/activity logging.

Sebelum implementasi:

1. Inspect halaman `/admin/mitras`.
2. Inspect component/design hasil `QC-0001` sampai `QC-0004`.
3. Reuse shared page container.
4. Reuse card component.
5. Reuse form control.
6. Reuse status badge.
7. Reuse button variants.
8. Reuse notification/alert component.
9. Reuse confirmation dialog jika tersedia.
10. Jangan membuat design system baru.
11. Jangan menambah dependency UI jika tidak diperlukan.
12. Jangan membuat dummy/fake data.

---

# Acceptance Criteria

## Design language

* [ ] Halaman Manajemen Mitra konsisten dengan Command Center, Portfolio, dan Project.
* [ ] Page title dan description menggunakan hierarchy yang sama.
* [ ] Content width konsisten.
* [ ] Border, radius, shadow, dan spacing card konsisten.
* [ ] Typography konsisten.
* [ ] Input dan button mempunyai tinggi/style konsisten.

## Onboarding Mitra

* [ ] Form onboarding tidak lagi dipaksakan menjadi satu row panjang.
* [ ] Form menggunakan grid yang proporsional.
* [ ] Kode Mitra tetap mendukung auto-generation existing.
* [ ] Nama Mitra tetap dapat diisi.
* [ ] Nama admin-mitra tetap dapat diisi.
* [ ] Email admin-mitra tetap dapat diisi.
* [ ] Nomor WhatsApp tetap dapat diisi.
* [ ] `Buat Mitra dan Admin` tetap bekerja.
* [ ] Validation existing tetap bekerja.

## Daftar Mitra

* [ ] Daftar Mitra lebih compact.
* [ ] Nama Mitra mudah dikenali.
* [ ] Kode/ID tetap ditampilkan.
* [ ] Status menggunakan badge/chip.
* [ ] Tanggal dibuat tetap tersedia.
* [ ] Admin-mitra utama tetap tersedia.
* [ ] Edit Mitra tetap dapat digunakan.
* [ ] Edit state mudah dibedakan dari read state.

## Action

* [ ] `Simpan` menggunakan primary action.
* [ ] `Edit` menggunakan secondary action.
* [ ] `Nonaktifkan` secara visual berbeda dari primary.
* [ ] `Hapus Mitra` menggunakan destructive treatment.
* [ ] Hapus tetap mengikuti permission dan behavior existing.
* [ ] Nonaktifkan tetap mengikuti business logic existing.

## Feedback

* [ ] Pesan sukses menggunakan notification/alert yang konsisten.
* [ ] Error form dapat terlihat dengan jelas.
* [ ] Empty state tersedia jika tidak ada Mitra.

## Responsive

* [ ] Desktop menggunakan ruang horizontal secara proporsional.
* [ ] Tablet tetap readable.
* [ ] Mobile menjadi satu kolom.
* [ ] Tidak terdapat horizontal page scrolling.
* [ ] Action tidak keluar dari viewport.

## Regression

* [ ] Mitra baru tetap dapat dibuat.
* [ ] Admin-mitra tetap dibuat sesuai behavior existing.
* [ ] Mitra existing tetap dapat diedit.
* [ ] Simpan perubahan tetap bekerja.
* [ ] Nonaktifkan tetap bekerja.
* [ ] Hapus tetap bekerja.
* [ ] Permission tidak berubah.
* [ ] Authorization tidak berubah.
* [ ] Relasi Mitra tidak berubah.
* [ ] Audit/activity tidak rusak.
* [ ] Tidak ada JavaScript error.
* [ ] Tidak ada dummy/fake data.

---

# Bukti QC

* `01-mitra-actual.png` — kondisi halaman Manajemen Mitra saat pengujian.
* `02-design-reference.png` — referensi design language Command Center/Portfolio/Project.

> Tambahkan bukti berikutnya menggunakan nomor berurutan. Jangan mengganti atau menghapus bukti lama.

---

# Riwayat status

| Tanggal      | Status | Oleh   | Catatan                                                                                                                                                                                                 |
| ------------ | ------ | ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `2026-08-20` | `open` | Fatoni | Halaman Manajemen Mitra secara fungsi sudah berjalan. QC dibuat khusus untuk menyelaraskan layout, form, card, status, action hierarchy, dan responsive design dengan design system workspace User THC. |
