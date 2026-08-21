# QC-0002 — konsistensi design Portfolio Cockpit

| Field                     | Nilai                              |
| ------------------------- | ---------------------------------- |
| ID                        | `QC-0002`                          |
| Prefix                    | `portfolio`                        |
| Status                    | `open`                             |
| Severity                  | `minor`                            |
| Tanggal/waktu pengujian   | `2026-08-20 14:06 WIB`             |
| Reviewer                  | Fatoni                             |
| Persona/role              | User THC                           |
| Halaman atau URL produksi | https://deploythc.web.id/portfolio |
| Browser/device            | Chrome / laptop Windows            |
| GitHub Issue              | [Bangun fondasi UI bersama dan selaraskan halaman dashboard, master, project, mitra, user](https://github.com/thcjatim-bit/project-monitoring-system/issues/90) |

## Ringkasan

Halaman **Portfolio Cockpit** secara fungsi sudah memiliki struktur dashboard yang cukup baik, tetapi tampilan visualnya perlu diselaraskan dengan design language baru pada **Command Center**.

Tujuannya bukan membuat desain baru yang berbeda, melainkan menjadikan seluruh workspace User THC terasa sebagai **satu aplikasi dengan satu design system**.

Elemen seperti:

* typography;
* card;
* KPI;
* spacing;
* border;
* border radius;
* shadow;
* warna status;
* filter;
* empty state;
* table;
* activity section;

harus menggunakan pola visual yang sama dengan halaman Command Center setelah perubahan `QC-0001`.

## Langkah reproduksi

1. Buka `https://deploythc.web.id/portfolio`.
2. Login sebagai **User THC**.
3. Buka menu **Portfolio**.
4. Perhatikan komponen:

   * Filter cakupan;
   * KPI kesehatan portfolio;
   * Decision Queue;
   * Tren realisasi jasa;
   * Health Matrix;
   * Distribusi Status Project;
   * Aktivitas terbaru lintas Project.
5. Bandingkan visual hierarchy dan density dengan design dashboard Command Center yang telah disepakati.

## Hasil aktual

Portfolio Cockpit saat ini menggunakan banyak panel horizontal full-width yang disusun secara vertikal.

Beberapa masalah visual yang terlihat:

* setiap section menggunakan card besar meskipun isi sedikit;
* terdapat banyak whitespace di dalam beberapa panel;
* KPI masih berada di dalam container besar sehingga hierarchy angka utama kurang kuat;
* section dengan empty state tetap menggunakan ruang vertikal cukup besar;
* struktur halaman menjadi panjang;
* density visual belum sepenuhnya konsisten dengan pendekatan dashboard compact pada Command Center;
* beberapa kontrol filter mempunyai visual/behavior yang berbeda dari komponen lain.

Struktur saat ini kurang lebih:

```text
┌─────────────────────────────────────────────┐
│ Filter cakupan                              │
│ [Project] [Mitra] [Periode] [Status risiko] │
└─────────────────────────────────────────────┘

┌─────────────────────────────────────────────┐
│ KPI kesehatan portfolio                     │
│                                             │
│ [KPI] [KPI] [KPI] [KPI]                    │
│ [KPI] [KPI]                                │
└─────────────────────────────────────────────┘

┌─────────────────────────────────────────────┐
│ Decision Queue                              │
└─────────────────────────────────────────────┘

┌─────────────────────────────────────────────┐
│ Tren realisasi jasa                         │
└─────────────────────────────────────────────┘

┌─────────────────────────────────────────────┐
│ Health Matrix                               │
└─────────────────────────────────────────────┘

┌─────────────────────────────────────────────┐
│ Distribusi Status Project                   │
└─────────────────────────────────────────────┘

┌─────────────────────────────────────────────┐
│ Aktivitas terbaru lintas Project            │
└─────────────────────────────────────────────┘
```

## Hasil yang diharapkan

Portfolio Cockpit tetap mempertahankan informasi dan business logic saat ini, tetapi menggunakan design system yang sama dengan **Command Center**.

### 1. Satu design language

Gunakan style yang konsisten untuk seluruh workspace User THC:

* sidebar navy existing tetap dipertahankan;
* background workspace tetap menggunakan warna existing;
* card background putih;
* border ringan;
* shadow halus;
* radius card konsisten;
* typography konsisten;
* spacing antar component konsisten;
* warna status konsisten;
* KPI menggunakan visual hierarchy yang sama;
* empty state menggunakan style yang sama;
* button dan dropdown menggunakan component/style yang sama.

Hindari membuat style khusus Portfolio jika komponen yang sama sudah tersedia di design system aplikasi.

---

### 2. Filter cakupan

Bagian **Filter cakupan** tetap berada pada bagian atas karena merupakan global control untuk Portfolio.

Buat lebih compact dan menyerupai toolbar/filter panel.

Contoh:

```text
┌──────────────────────────────────────────────────────────────┐
│ Filter Portfolio                                             │
│                                                              │
│ Project       Mitra         Periode       Status risiko      │
│ [ Select ▾ ]  [ Select ▾ ]  [ Select ▾ ]  [ Select ▾ ]      │
│                                                              │
│ [Terapkan filter]  Reset filter  Unduh Excel                 │
│                                                              │
│ Filter aktif: Semua Project · Semua Mitra · Agustus 2026     │
└──────────────────────────────────────────────────────────────┘
```

Jangan menambah tinggi card secara berlebihan.

Pada desktop, dropdown sebaiknya berada dalam satu baris selama ruang memungkinkan.

---

### 3. KPI kesehatan portfolio

KPI dibuat mengikuti style KPI pada Command Center.

Angka atau status utama menjadi visual paling dominan.

Contoh:

```text
┌───────────────┐ ┌───────────────┐ ┌───────────────┐
│ 1             │ │ Terbatas      │ │ Terbatas      │
│ Project aktif │ │ Realisasi     │ │ SPI Portfolio │
└───────────────┘ └───────────────┘ └───────────────┘

┌───────────────┐ ┌───────────────┐ ┌───────────────┐
│ Terbatas      │ │ Terbatas      │ │ Rp 0          │
│ Perlu perhatian││ Kesiapan Mat. │ │ RAB Jasa      │
└───────────────┘ └───────────────┘ └───────────────┘
```

Ketentuan:

* tinggi card konsisten;
* angka/status utama dibuat lebih besar;
* label sekunder lebih kecil;
* link seperti `Buka Project berisiko` tetap tersedia tetapi tidak mendominasi;
* warna status dipakai secara semantik, bukan dekoratif.

---

### 4. Gunakan grid untuk section yang dapat berdampingan

Pada desktop, tidak semua section perlu menggunakan satu baris penuh.

Contoh layout yang disarankan:

```text
┌──────────────────────────────────────────────────────────────┐
│ Filter Portfolio                                             │
└──────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────┐
│ KPI Portfolio                                                │
│ [KPI] [KPI] [KPI] [KPI]                                     │
└──────────────────────────────────────────────────────────────┘

┌─────────────────────────────────┬────────────────────────────┐
│ Decision Queue                  │ Distribusi Status          │
│                                 │                            │
│ exception / empty state         │ Risk     █████            │
│                                 │ Project  ███████          │
└─────────────────────────────────┴────────────────────────────┘

┌──────────────────────────────────────────────────────────────┐
│ Tren realisasi jasa                                          │
└──────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────┐
│ Health Matrix                                                │
└──────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────┐
│ Aktivitas terbaru                                            │
└──────────────────────────────────────────────────────────────┘
```

Layout final boleh menyesuaikan isi sebenarnya, tetapi harus mengurangi penggunaan ruang kosong yang tidak memberikan informasi.

---

### 5. Empty state

Section tanpa data seperti **Decision Queue** atau **Tren realisasi jasa** tidak perlu menggunakan card yang terlalu tinggi.

Contoh:

```text
Decision Queue                                      0 item
Tidak ada pengecualian yang perlu ditindaklanjuti.
```

Empty state harus:

* compact;
* mudah dibaca;
* tidak menggunakan ruang besar;
* tetap jelas bahwa kondisi tersebut bukan error.

---

### 6. Health Matrix

Health Matrix merupakan data penting dan tetap dapat menggunakan lebar yang cukup besar.

Pastikan:

* header mudah dibedakan;
* row height compact;
* status menggunakan badge/chip konsisten;
* link Project tetap jelas;
* tabel responsive;
* tidak menyebabkan horizontal overflow yang tidak terkendali.

Jika jumlah row banyak, pertimbangkan pagination atau mekanisme existing tanpa mengubah business logic.

---

### 7. Distribusi Status Project

Visual status tetap dipertahankan tetapi style progress/bar diselaraskan dengan Command Center.

Warna harus memiliki arti yang konsisten:

* hijau → sehat/aktif/siap;
* kuning → warning;
* merah → membutuhkan perhatian;
* abu-abu → N/A/inactive.

Jangan menggunakan warna berbeda untuk arti status yang sama pada halaman lain.

---

### 8. Aktivitas terbaru lintas Project

Activity section harus menggunakan pola yang sama dengan **Aktivitas lintas operasional** pada Command Center.

Jika terdapat banyak activity:

* tampilkan secara compact;
* batasi jumlah item awal;
* gunakan scroll internal atau `Lihat semua`;
* jangan membiarkan activity membuat dashboard sangat panjang.

---

### 9. Konsistensi spacing

Gunakan spacing yang konsisten, misalnya mengikuti token/design system existing.

Sebagai acuan:

* card padding: `16–24px`;
* gap antar card: `16–20px`;
* border radius: `10–14px`;
* jarak title dengan description dibuat konsisten;
* tinggi control/filter konsisten.

Nilai tersebut bukan kewajiban absolut apabila project sudah memiliki design token sendiri.

Prioritaskan token existing.

## Responsive behavior

### Desktop

* gunakan ruang horizontal secara optimal;
* KPI dapat tampil 3–4 card per row;
* beberapa section dapat menggunakan grid dua kolom;
* filter tampil satu baris jika ruang cukup.

### Tablet

* KPI menjadi 2 card per row;
* section dua kolom dapat turun menjadi satu kolom jika diperlukan;
* filter dapat wrap.

### Mobile

* seluruh layout menjadi satu kolom;
* dropdown menggunakan lebar penuh;
* tabel harus tetap dapat digunakan;
* tidak muncul horizontal scrolling pada halaman utama.

## Ketentuan implementasi

Perubahan ini fokus pada:

**UI / layout / visual consistency.**

Jangan mengubah:

* business logic;
* permission;
* authorization;
* query database;
* API contract;
* formula KPI;
* source data;
* filter logic;
* export Excel;
* ownership/access scope.

Sebelum implementasi:

1. Inspect component dan stylesheet Command Center hasil `QC-0001`.
2. Identifikasi reusable component yang dapat digunakan kembali.
3. Reuse card, KPI, badge, button, form control, spacing, dan typography yang sama.
4. Jangan membuat design system kedua khusus Portfolio.
5. Jangan menambahkan library UI baru apabila component existing cukup.
6. Jangan membuat dummy/fake data.

## Dampak dan catatan

Perubahan ini tidak memperbaiki kegagalan fungsi tertentu, tetapi penting untuk meningkatkan konsistensi UX.

User berpindah antara:

* Command Center;
* Portfolio;
* Project;
* Warehouse;
* modul lainnya.

Jika setiap halaman menggunakan pola visual berbeda, aplikasi akan terasa seperti kumpulan modul terpisah.

Target yang diinginkan:

```text
Command Center
      │
Portfolio
      │
Project
      │
Warehouse
      ▼
Satu design system / satu bahasa visual
```

### Acceptance criteria

* [ ] Portfolio Cockpit menggunakan design language yang konsisten dengan Command Center.
* [ ] Typography konsisten.
* [ ] Card radius, border, dan shadow konsisten.
* [ ] KPI menggunakan pola visual yang sama.
* [ ] Filter panel dibuat compact.
* [ ] Empty state tidak menggunakan ruang vertikal berlebihan.
* [ ] Layout memanfaatkan ruang horizontal desktop.
* [ ] Status badge dan warna mempunyai arti yang konsisten.
* [ ] Activity menggunakan pola yang konsisten dengan Command Center.
* [ ] Layout responsive pada desktop, tablet, dan mobile.
* [ ] Tidak terdapat horizontal scrolling pada halaman utama.
* [ ] Semua informasi existing tetap tersedia.
* [ ] Tidak ada perubahan permission atau business logic.
* [ ] Tidak ada dummy/fake data.
* [ ] Tidak ada UI framework baru yang ditambahkan tanpa kebutuhan.

## Bukti QC

* `01-actual.png` — kondisi halaman Portfolio Cockpit saat ini.

> Tambahkan bukti berikutnya dengan nomor berurutan. Jangan mengganti atau menghapus bukti lama.

## Riwayat status

| Tanggal      | Status | Oleh   | Catatan                                                                                                                                                            |
| ------------ | ------ | ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `2026-08-20` | `open` | Fatoni | Portfolio Cockpit perlu diselaraskan dengan design language Command Center agar seluruh workspace User THC memiliki visual dan interaction pattern yang konsisten. |
