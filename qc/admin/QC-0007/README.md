# QC-0007 — Konsistensi Design dan Auto-generate Kode Material

| Field                     | Nilai                                    |
| ------------------------- | ---------------------------------------- |
| ID                        | `QC-0007`                                |
| Prefix                    | `material`                               |
| Status                    | `open`                                   |
| Severity                  | `major`                                  |
| Tanggal/waktu pengujian   | `2026-08-20 14:41 WIB`                   |
| Reviewer                  | Fatoni                                   |
| Persona/role              | User THC                                 |
| Halaman atau URL produksi | https://deploythc.web.id/admin/materials |
| Browser/device            | Chrome / laptop Windows                  |
| GitHub Issue              | [Bangun fondasi UI bersama dan selaraskan halaman dashboard, master, project, mitra, user](https://github.com/thcjatim-bit/project-monitoring-system/issues/90) |

## Ringkasan

> Halaman **Master Data Material** perlu diselaraskan dengan design language yang sama dengan Command Center, Portfolio, Project, Mitra, dan User. Selain itu, field **Kode Material** saat membuat Material baru saat ini wajib diisi manual; diharapkan field tersebut boleh dikosongkan dan sistem otomatis menghasilkan kode Material yang unik.

## Langkah reproduksi

1. Buka `https://deploythc.web.id/admin/materials`.
2. Masuk sebagai persona/role **User THC**.
3. Buka menu **Material** pada bagian **Master Data**.
4. Perhatikan form **Tambah Material** dan daftar Material di bawahnya.
5. Coba membuat Material baru tanpa mengisi field **Kode**.
6. Perhatikan bahwa sistem saat ini mewajibkan Kode Material diisi secara manual.
7. Perhatikan juga bahwa setiap Material langsung menampilkan seluruh field edit sehingga halaman menjadi sangat panjang ketika jumlah Material bertambah.

## Hasil aktual

> Halaman Material saat ini menggunakan layout yang belum konsisten dengan design language modul lainnya.
>
> Form **Tambah Material** menggunakan beberapa field dalam area yang relatif sempit, sedangkan daftar Material menampilkan form edit untuk setiap Material secara langsung.
>
> Setiap item Material menampilkan:
>
> * Kode;
> * Nama;
> * Unit/Satuan;
> * Jenis;
> * Ambang minimum;
> * tombol `Simpan perubahan`;
> * tombol `Nonaktifkan`;
> * informasi Saldo Warehouse.
>
> Karena seluruh form edit selalu terlihat, semakin banyak Material maka halaman semakin panjang dan sulit dipindai.
>
> Selain itu, pada proses pembuatan Material baru, field **Kode Material wajib diisi manual**. User tidak dapat membiarkannya kosong agar sistem membuat kode secara otomatis.

## Hasil yang diharapkan

> Halaman **Master Data Material** menggunakan design language yang sama dengan modul lainnya dengan reuse component, spacing, typography, card, form control, select, status badge, dan button hierarchy yang sudah tersedia.
>
> Bagian **Tambah Material** dibuat sebagai form card yang lebih rapi dan proporsional.
>
> Contoh struktur:
>
> ```text
> Master Data
>
> Material
> Kelola master Material yang digunakan dalam operasional Warehouse.
>
> ┌──────────────────────────────────────────────────────┐
> │ Tambah Material                                      │
> │                                                      │
> │ Kode Material            Nama Material               │
> │ [                    ]   [                       ]    │
> │ Kosongkan untuk dibuat                               │
> │ otomatis.                                            │
> │                                                      │
> │ Unit/Satuan              Jenis                       │
> │ [ Pilih Unit        ▾ ]  [ Pilih Jenis          ▾ ] │
> │                                                      │
> │ Ambang minimum                                       │
> │ [                                                ]   │
> │                                                      │
> │                              [Simpan Material]       │
> └──────────────────────────────────────────────────────┘
> ```
>
> ### Auto-generate Kode Material
>
> Field **Kode Material tidak lagi wajib diisi saat create**.
>
> Behavior yang diharapkan:
>
> ```text
> Jika Kode Material diisi
>          ↓
> gunakan kode yang diberikan user
>
> Jika Kode Material kosong
>          ↓
> sistem membuat kode otomatis
>          ↓
> kode harus unik
> ```
>
> Dengan demikian kedua cara tetap didukung:
>
> * User dapat memasukkan kode manual jika memang diperlukan.
> * User dapat mengosongkan kode dan membiarkan sistem membuatnya otomatis.
>
> Auto-generated code harus:
>
> * unik;
> * tidak collision dengan Material existing;
> * dibuat pada sisi backend/server yang authoritative;
> * aman terhadap request create secara bersamaan;
> * tidak hanya dibuat menggunakan JavaScript/frontend;
> * mengikuti pola/convention ID yang sudah digunakan aplikasi apabila generator reusable sudah tersedia.
>
> Sebelum membuat format generator baru, inspect terlebih dahulu mekanisme auto-generation yang sudah digunakan pada entity lain seperti **Mitra atau Project** dan reuse pattern tersebut jika sesuai.
>
> Jangan mengubah kode Material existing.
>
> Jangan melakukan regenerate ketika Material diedit.
>
> Contoh:
>
> ```text
> CREATE
>
> Kode: kosong
> Nama: Kabel FO 24 Core
>
>              ↓
>
> Sistem:
>
> Kode: <generated unique code>
> Nama: Kabel FO 24 Core
> ```
>
> Format kode final harus mengikuti convention project yang sudah ada dan tidak perlu mengubah database record lama hanya untuk menyamakan format.
>
> ### Daftar Material
>
> Daftar Material dibuat lebih compact.
>
> Kondisi default sebaiknya menampilkan informasi utama saja:
>
> ```text
> ┌─────────────────────────────────────────────────────────┐
> │ Kabel 24C                                      [Aktif] │
> │ MAT-xxxx                                                │
> │                                                         │
> │ Unit       meter                                        │
> │ Jenis      Drum kabel                                   │
> │ Minimum    Belum dikonfigurasi                          │
> │ Saldo      Tidak ada saldo Warehouse                    │
> │                                                         │
> │                                      [Edit] [Nonaktifkan]│
> └─────────────────────────────────────────────────────────┘
> ```
>
> Form edit tidak perlu selalu terbuka.
>
> Setelah user memilih `Edit`, baru tampilkan field:
>
> * Kode;
> * Nama;
> * Unit/Satuan;
> * Jenis;
> * Ambang minimum;
> * `Batal`;
> * `Simpan perubahan`.
>
> Kode Material existing tetap dapat mengikuti behavior edit yang sekarang apabila memang secara business rule boleh diedit. QC ini tidak mengubah aturan tersebut.
>
> ### Saldo Warehouse
>
> Informasi **Saldo Warehouse** tetap dipertahankan, tetapi dibuat sebagai informasi sekunder di dalam item Material dan tidak mendominasi layout.
>
> Contoh:
>
> ```text
> Saldo Warehouse
> test · 3213 Pcs
> ```
>
> atau:
>
> ```text
> Saldo Warehouse: 3213 Pcs
> Warehouse: test
> ```
>
> Jika Material belum mempunyai saldo:
>
> ```text
> Belum ada saldo Warehouse.
> ```
>
> ### Unit/Satuan dan Jenis
>
> Select **Unit/Satuan** dan **Jenis** harus menggunakan visual component family yang sama dengan dropdown pada QC sebelumnya:
>
> * tinggi sama;
> * border sama;
> * radius sama;
> * focus state sama;
> * dropdown menu konsisten;
> * keyboard accessible.
>
> Search tidak wajib jika jumlah opsi sedikit.
>
> Jika Unit/Satuan berasal dari master data yang jumlahnya dapat menjadi besar, reusable searchable select dari `QC-0003` dapat digunakan.
>
> Jangan membuat implementation dropdown baru jika shared component yang sesuai sudah tersedia.
>
> ### Hierarchy aksi
>
> Gunakan hierarchy yang konsisten:
>
> * `Simpan Material` / `Simpan perubahan` → primary.
> * `Edit` / `Batal` → secondary.
> * `Nonaktifkan` → state/warning action.
> * action destructive lain jika tersedia → destructive.
>
> ### Responsive
>
> Pada desktop, form dapat menggunakan grid 2 kolom.
>
> Pada tablet dan mobile, field harus wrap menjadi satu kolom apabila ruang tidak mencukupi.
>
> Tidak boleh muncul horizontal scrolling.
>
> ### Ketentuan implementasi
>
> Reuse design/component dari QC sebelumnya dan jangan membuat design system baru khusus Material.
>
> Perubahan design tidak boleh mengubah:
>
> * permission;
> * authorization;
> * Material ownership/scope;
> * Unit/Satuan semantics;
> * Jenis Material;
> * saldo Warehouse;
> * stock calculation;
> * ambang minimum;
> * activate/deactivate behavior;
> * API contract yang tidak terkait kebutuhan auto-generation;
> * audit/activity logging.
>
> Perubahan auto-generation hanya berlaku ketika **membuat Material baru dengan Kode kosong**.
>
> Kode Material existing tidak boleh berubah sebagai efek migrasi UI ini.

## Dampak dan catatan

> Layout saat ini akan semakin sulit digunakan ketika jumlah Material bertambah karena seluruh Material selalu berada dalam kondisi edit dan menggunakan ruang vertikal yang cukup besar.
>
> Mengubah daftar menjadi management list/card yang compact akan membuat lebih banyak Material dapat dipindai dalam satu viewport sekaligus tetap menyediakan form edit ketika dibutuhkan.
>
> Kewajiban memasukkan Kode Material secara manual juga menambah pekerjaan user dan berpotensi menghasilkan format kode yang tidak konsisten atau collision.
>
> Dengan auto-generation, flow yang diharapkan menjadi:
>
> ```text
> Nama Material
>      +
> Unit/Satuan
>      +
> Jenis
>      +
> optional Kode
>      ↓
> Simpan
>      ↓
> backend generate kode jika kosong
> ```
>
> Acceptance utama:
>
> * Halaman Material menggunakan design language yang sama dengan modul lain.
> * Form Tambah Material lebih rapi dan responsive.
> * Kode Material boleh dikosongkan pada proses create.
> * Kode otomatis dibuat jika field Kode kosong.
> * Kode yang dibuat harus unik.
> * Kode manual tetap dapat digunakan apabila diisi.
> * Kode Material existing tidak berubah.
> * Auto-generation dilakukan secara authoritative di backend.
> * Daftar Material tampil lebih compact.
> * Form edit hanya perlu tampil ketika user memilih Edit.
> * Status Material menggunakan reusable badge jika status ditampilkan.
> * Saldo Warehouse tetap tersedia.
> * Unit/Satuan dan Jenis menggunakan select component yang konsisten.
> * Simpan perubahan tetap bekerja.
> * Nonaktifkan tetap bekerja.
> * Permission dan authorization tidak berubah.
> * Tidak ada dummy/fake data.
> * Tidak ada horizontal scrolling.
> * Tidak ada JavaScript/runtime error baru.

## Bukti QC

* `01-actual.png` — kondisi halaman Master Data Material saat temuan terjadi.
* `02-context.png` — konteks form Tambah Material, field Kode, daftar Material, edit Material, dan Saldo Warehouse.

> Tambahkan bukti berikutnya dengan nomor berurutan. Jangan mengganti atau menghapus bukti lama.

## Riwayat status

| Tanggal      | Status | Oleh   | Catatan                                                                                                                                                              |
| ------------ | ------ | ------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `2026-08-20` | `open` | Fatoni | Temuan dibuat. Halaman Material perlu diselaraskan dengan design language workspace dan Kode Material perlu mendukung auto-generation ketika field Kode dikosongkan. |
