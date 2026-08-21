# QC-0001 — layout dashboard Command Center

| Field                     | Nilai                              |
| ------------------------- | ---------------------------------- |
| ID                        | `QC-0001`                          |
| Prefix                    | `dashboard`                        |
| Status                    | `open`                             |
| Severity                  | `minor`                            |
| Tanggal/waktu pengujian   | `2026-08-20 13:04 WIB`             |
| Reviewer                  | Fatoni                             |
| Persona/role              | User THC                           |
| Halaman atau URL produksi | https://deploythc.web.id/dashboard |
| Browser/device            | Chrome / laptop Windows            |
| GitHub Issue              | [Bangun fondasi UI bersama dan selaraskan halaman dashboard, master, project, mitra, user](https://github.com/thcjatim-bit/project-monitoring-system/issues/90) |

## Ringkasan

Pada halaman **Command Center**, bagian **Aktivitas lintas operasional** saat ini ditampilkan sebagai panel utama dengan lebar penuh dan berada di posisi paling atas dashboard.

Panel tersebut dapat berisi banyak aktivitas sehingga menggunakan ruang vertikal yang cukup besar dan menyebabkan informasi operasional yang lebih penting, seperti **User aktif, Onboarding Mitra terbaru, Request Material, Transit terlambat, Stok kritis, dan Kesiapan Warehouse**, terdorong jauh ke bawah halaman.

Layout dashboard perlu disesuaikan agar **Aktivitas lintas operasional ditempatkan pada panel/kolom di sisi kanan dashboard**, sementara informasi operasional dan KPI utama tetap berada pada area utama di sisi kiri.

Tujuannya adalah membuat Command Center lebih compact, mudah dipindai, dan lebih menyerupai dashboard operasional daripada daftar section vertikal yang panjang.

## Langkah reproduksi

1. Buka halaman `https://deploythc.web.id/dashboard`.
2. Login menggunakan persona/role **User THC**.
3. Buka menu **Command Center**.
4. Perhatikan bagian **Aktivitas lintas operasional** pada bagian atas halaman.
5. Jika terdapat banyak aktivitas, perhatikan bahwa panel bertambah panjang secara vertikal.
6. Scroll halaman untuk melihat informasi **User aktif**, **Onboarding Mitra terbaru**, **Request Material**, **Transit terlambat**, **Stok kritis**, dan **Kesiapan Warehouse**.
7. Perhatikan bahwa informasi-informasi tersebut berada jauh di bawah daftar aktivitas.

## Hasil aktual

Bagian **Aktivitas lintas operasional**:

* berada pada area utama dashboard;
* menggunakan hampir seluruh lebar content area;
* menampilkan setiap aktivitas sebagai item horizontal;
* bertambah panjang ke bawah sesuai jumlah aktivitas;
* menjadi elemen paling dominan pada halaman meskipun fungsinya lebih bersifat riwayat/monitoring aktivitas;
* menyebabkan KPI dan informasi yang membutuhkan perhatian operasional berada di bawah fold dan membutuhkan banyak scrolling.

Pada kondisi bukti QC terdapat sekitar **10 aktivitas**, sehingga panel Aktivitas lintas operasional menggunakan porsi layar yang jauh lebih besar dibandingkan komponen dashboard lainnya.

Layout dashboard saat ini secara umum berbentuk:

```text
┌─────────────────────────────────────────────────────────┐
│ Aktivitas lintas operasional                            │
│                                                         │
│ Aktivitas 1                                             │
│ Aktivitas 2                                             │
│ Aktivitas 3                                             │
│ ...                                                     │
│ Aktivitas 10                                            │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ User aktif                                              │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ Onboarding Mitra terbaru                                │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ Request Material                                        │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ Transit terlambat                                       │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ Stok kritis                                             │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ Kesiapan Warehouse                                      │
└─────────────────────────────────────────────────────────┘
```

Akibatnya dashboard menjadi sangat panjang secara vertikal dan informasi yang seharusnya dapat dipantau secara cepat tidak terlihat dalam satu area pandang.

## Hasil yang diharapkan

Dashboard **Command Center** menggunakan layout yang lebih compact dengan pembagian area utama dan panel aktivitas.

Pada desktop/laptop, gunakan layout **dua kolom**:

* **Kolom kiri / main content** sekitar `65–75%` lebar halaman.
* **Kolom kanan / activity panel** sekitar `25–35%` lebar halaman.

Bagian **Aktivitas lintas operasional** dipindahkan ke sisi kanan dan ditampilkan sebagai **activity feed / recent activity panel** yang compact.

Contoh struktur yang diharapkan:

```text
┌─────────────────────────────────────┬───────────────────────┐
│ Command Center                      │ Aktivitas terbaru     │
│                                     │                       │
│ ┌────────┬────────┬────────┐        │ User: daffa           │
│ │ User   │ THC    │ Mitra  │        │ Diperbarui            │
│ │   3    │   2    │   1    │        │ 19 Aug 06:16          │
│ └────────┴────────┴────────┘        │ ───────────────────   │
│                                     │ Pekerjaan Jasa        │
│ ┌───────────────────────────────┐   │ Dibuat                │
│ │ Onboarding Mitra terbaru      │   │ 19 Aug 06:13          │
│ └───────────────────────────────┘   │ ───────────────────   │
│                                     │ PoP                   │
│ ┌───────────────┬───────────────┐   │ Dibuat                │
│ │ Request       │ Transit       │   │ 19 Aug 06:13          │
│ │ Material      │ terlambat     │   │                       │
│ │      0        │      0        │   │ ...                   │
│ └───────────────┴───────────────┘   │                       │
│                                     │ Lihat semua aktivitas │
│ ┌───────────────┬───────────────┐   │                       │
│ │ Stok kritis   │ Warehouse     │   │                       │
│ │      0        │      2        │   │                       │
│ └───────────────┴───────────────┘   │                       │
└─────────────────────────────────────┴───────────────────────┘
```

### Ketentuan layout

#### 1. Aktivitas lintas operasional

Panel aktivitas harus:

* berada di sisi kanan dashboard pada desktop;
* menggunakan desain activity feed/list yang lebih compact;
* tidak menggunakan card besar untuk setiap aktivitas;
* tetap menampilkan informasi penting seperti:

  * nama object;
  * jenis/master data;
  * user terkait jika tersedia;
  * jenis aktivitas seperti `Dibuat` atau `Diperbarui`;
  * tanggal/waktu aktivitas;
* menggunakan divider sederhana antar aktivitas;
* memiliki tinggi maksimum agar jumlah aktivitas tidak menentukan tinggi seluruh dashboard.

Jika jumlah aktivitas banyak, panel dapat menggunakan salah satu pendekatan berikut:

* internal vertical scroll;
* menampilkan maksimal 5–7 aktivitas terbaru;
* menyediakan tombol/link **Lihat semua aktivitas**.

Panel aktivitas **tidak boleh membuat main dashboard ikut bertambah panjang hanya karena jumlah activity bertambah**.

#### 2. Informasi operasional utama

Area kiri menjadi fokus utama dan menampilkan informasi yang membutuhkan perhatian atau keputusan dari User THC.

Prioritas informasi:

1. User aktif.
2. Onboarding Mitra terbaru.
3. Request Material yang membutuhkan keputusan.
4. Transit terlambat.
5. Stok kritis.
6. Kesiapan Warehouse.

KPI/status dengan nilai sederhana seperti `0`, `1`, `2`, atau `3` sebaiknya menggunakan **compact cards**, bukan panel horizontal penuh jika tidak terdapat detail yang perlu ditampilkan.

#### 3. User aktif

Card **User aktif** dapat diubah menjadi tiga KPI compact:

```text
┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│      3       │ │      2       │ │      1       │
│ Total User   │ │ User THC     │ │ User Mitra   │
│ aktif        │ │ aktif        │ │ aktif        │
└──────────────┘ └──────────────┘ └──────────────┘
```

Angka menjadi informasi visual utama dan label menjadi informasi sekunder.

#### 4. Status dengan nilai nol

Section seperti:

* Request Material menunggu keputusan;
* Transit terlambat;
* Stok kritis;

tidak perlu menggunakan panel horizontal besar ketika nilainya `0`.

Status tersebut dapat ditampilkan menggunakan KPI/status card compact.

Contoh:

```text
┌────────────────────┐
│ 0                  │
│ Request Material   │
│ ✓ Tidak ada issue  │
└────────────────────┘
```

Hal ini mengurangi penggunaan ruang tanpa menghilangkan informasi.

#### 5. Kesiapan Warehouse

**Kesiapan Warehouse** tetap dapat menampilkan detail warehouse karena mengandung informasi operasional yang lebih lengkap.

Namun layout list dibuat lebih compact dan konsisten dengan card dashboard lainnya.

Informasi utama yang tetap tersedia antara lain:

* nama Warehouse;
* kepemilikan;
* Petugas Gudang aktif;
* Material kritis;
* Transit aktif;
* Transit terlambat;
* status `Siap` atau status lain yang tersedia.

### Responsive behavior

#### Desktop / laptop

Gunakan layout dua kolom.

Contoh:

```text
grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr);
```

atau pembagian yang setara sekitar:

```text
70% main content / 30% activity panel
```

Panel aktivitas dapat dibuat `sticky` apabila sesuai dengan struktur frontend saat ini sehingga tetap mudah dipantau ketika user melakukan scroll pada dashboard.

Contoh behavior:

```text
position: sticky;
top: <header offset>;
```

Implementasi sticky bersifat opsional apabila berpotensi mengganggu layout existing.

#### Tablet

Layout dapat berubah menjadi satu kolom atau dua kolom dengan proporsi yang disesuaikan tergantung ruang yang tersedia.

#### Mobile

Semua komponen kembali menjadi satu kolom.

Urutan yang disarankan:

1. KPI/status utama;
2. informasi operasional;
3. Aktivitas lintas operasional.

Tidak boleh muncul horizontal scrolling.

### Ketentuan implementasi

Perubahan ini adalah perubahan **UI/layout**.

Implementasi tidak boleh mengubah:

* business logic;
* permission dan authorization;
* query database;
* API contract;
* source data;
* perhitungan KPI;
* status domain;
* audit/activity data;
* workflow existing.

Gunakan data yang sudah tersedia saat ini dan **jangan membuat dummy/fake data** hanya untuk menyesuaikan desain.

Sebelum implementasi:

1. Inspect component/template Command Center yang digunakan saat ini.
2. Identifikasi CSS framework/design system yang sudah digunakan project.
3. Identifikasi reusable card, grid, badge, typography, dan spacing component yang tersedia.
4. Reuse component existing sebisa mungkin.
5. Jangan menambahkan UI framework baru jika tidak benar-benar diperlukan.

Style tetap mengikuti identitas visual aplikasi saat ini:

* sidebar navy existing dipertahankan;
* background halaman existing dipertahankan;
* card menggunakan background putih;
* border/shadow dibuat ringan;
* border radius konsisten;
* warna biru muda existing tetap dapat digunakan untuk KPI/status;
* badge status seperti `Dibuat`, `Diperbarui`, dan `Siap` tetap konsisten dengan design system existing.

Target akhirnya adalah **Command Center yang terasa sebagai management/operational dashboard**, dengan informasi penting dapat dipindai dengan cepat dan aktivitas historis tidak mendominasi ruang utama.

## Dampak dan catatan

Layout saat ini tidak menyebabkan kegagalan fungsi, tetapi menurunkan efektivitas **Command Center sebagai dashboard monitoring operasional**.

Semakin banyak aktivitas tersimpan:

* semakin panjang halaman;
* semakin jauh KPI penting dari bagian atas halaman;
* semakin banyak scrolling yang diperlukan;
* semakin sulit User THC melakukan scanning cepat terhadap kondisi yang membutuhkan perhatian;
* informasi historis aktivitas menjadi lebih dominan dibandingkan status operasional.

Memindahkan **Aktivitas lintas operasional** ke panel kanan akan meningkatkan information hierarchy:

```text
Prioritas / membutuhkan tindakan → area utama
Riwayat / monitoring aktivitas   → side panel
```

Perubahan ini juga memanfaatkan ruang horizontal laptop/desktop yang saat ini masih dapat digunakan untuk membuat dashboard lebih padat tanpa mengurangi keterbacaan.

### Acceptance criteria

* [ ] Aktivitas lintas operasional tidak lagi menggunakan full-width area utama pada desktop.
* [ ] Aktivitas lintas operasional tampil pada panel kanan pada desktop/laptop.
* [ ] Main content dan activity panel menggunakan layout yang proporsional dan rapi.
* [ ] Jumlah aktivitas yang banyak tidak membuat panel aktivitas tumbuh tanpa batas.
* [ ] User tetap dapat melihat aktivitas terbaru beserta status dan waktu aktivitas.
* [ ] Tersedia mekanisme untuk melihat aktivitas lainnya jika aktivitas dibatasi.
* [ ] User aktif ditampilkan secara lebih compact.
* [ ] Request Material, Transit terlambat, dan Stok kritis tidak menggunakan card horizontal besar ketika tidak memiliki detail.
* [ ] Onboarding Mitra terbaru tetap menampilkan informasi mitra dan admin-mitra terkait.
* [ ] Kesiapan Warehouse tetap menampilkan detail operasional yang tersedia.
* [ ] Seluruh data yang tampil sebelum perubahan tetap dapat diakses setelah perubahan.
* [ ] Tidak ada perubahan business logic, permission, API, query, maupun perhitungan KPI.
* [ ] Tidak ada dummy/fake data yang ditambahkan untuk kebutuhan layout.
* [ ] Layout tidak menghasilkan horizontal scrolling.
* [ ] Tampilan tetap responsive pada desktop, tablet, dan mobile.
* [ ] Styling tetap konsisten dengan design system aplikasi saat ini.

## Bukti QC

* `01-actual.png` — kondisi Command Center saat ini; Aktivitas lintas operasional menggunakan area utama dan mendorong KPI lainnya jauh ke bawah.
* `02-context.png` — konteks/referensi layout dashboard yang lebih compact dengan pemanfaatan area horizontal.

> Tambahkan bukti berikutnya dengan nomor berurutan. Jangan mengganti atau menghapus bukti lama.

## Riwayat status

| Tanggal      | Status | Oleh   | Catatan                                                                                                                                                                                |
| ------------ | ------ | ------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `2026-08-20` | `open` | Fatoni | Temuan dibuat. Aktivitas lintas operasional dinilai terlalu mendominasi area utama Command Center dan diusulkan dipindahkan ke panel kanan dengan layout dashboard yang lebih compact. |
