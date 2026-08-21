# QC-0016 — Dashboard, Warehouse, dan Manajemen User Admin Mitra

| Field                     | Nilai                                    |
| ------------------------- | ---------------------------------------- |
| ID                        | `QC-0016`                                |
| Prefix                    | `mitra-workspace`                        |
| Status                    | `open`                                   |
| Severity                  | `major`                                  |
| Tanggal/waktu pengujian   | `2026-08-20 16:31 WIB`                   |
| Reviewer                  | Fatoni                                   |
| Persona/role              | Admin Mitra                              |
| Halaman atau URL produksi | https://deploythc.web.id/mitra/dashboard |
| Browser/device            | Chrome / laptop Windows                  |
| GitHub Issue              | [Implement workspace Admin Mitra setelah capability disetujui](https://github.com/thcjatim-bit/project-monitoring-system/issues/95) |

## Ringkasan

> Saat login sebagai **Admin Mitra**, Dashboard Mitra belum menggunakan design language yang sama dengan workspace User THC. Selain itu tidak tersedia menu **Warehouse** dan tidak tersedia menu untuk **mengelola User milik Mitra sendiri**, seperti menambah User, mengedit data/role, menonaktifkan, atau mengurangi User dari organisasi Mitra.
>
> Admin Mitra seharusnya dapat melakukan administrasi operasional dalam scope Mitranya sendiri tanpa mendapatkan akses ke data Mitra lain maupun fungsi khusus User THC.

## Langkah reproduksi

1. Buka aplikasi produksi.
2. Login menggunakan akun dengan persona/role **Admin Mitra**.
3. Buka `https://deploythc.web.id/mitra/dashboard`.
4. Perhatikan Dashboard Mitra dan menu pada sidebar.
5. Perhatikan bahwa tersedia menu seperti:

   * Dashboard Mitra;
   * Portfolio;
   * Project;
   * Material;
   * Unit;
   * PoP;
   * Pekerjaan Jasa;
   * Request Material.
6. Perhatikan bahwa tidak terdapat kelompok/menu **Warehouse**.
7. Perhatikan bahwa tidak terdapat menu **User / Kelola User Mitra**.
8. Cari mekanisme untuk menambah, mengedit, menonaktifkan, atau mengurangi User dalam Mitra.
9. Perhatikan bahwa fungsi tersebut tidak tersedia untuk Admin Mitra.

## Hasil aktual

> Dashboard Mitra saat ini masih berupa informasi teks sederhana seperti:
>
> ```text
> Dashboard Mitra
>
> Project
> Project aktif: 2 · Project selesai: 0
>
> PRJ-2608-0001 · kelurahan banar
> Status Project: Aktif
>
> aa · kelurahan rembiga
> Status Project: Aktif
>
> Saldo Stok Gudang Mitra
> tiang 7m · Btg · testwhmitra: 33,000
>
> Request Material
> Request Material #2 · kelurahan banar · disetujui
> Request Material #1 · kelurahan rembiga · disetujui
> ```
>
> Informasi belum menggunakan:
>
> * KPI cards;
> * reusable status badge;
> * management cards;
> * table/list styling;
> * shared quantity formatter;
> * visual hierarchy yang sama dengan Command Center/Portfolio.
>
> Selain masalah visual, sidebar Admin Mitra tidak mempunyai menu Warehouse.
>
> Akibatnya Admin Mitra tidak mempunyai jalur navigasi yang jelas untuk mengakses fungsi Warehouse yang memang berada dalam scope Mitranya.
>
> Admin Mitra juga tidak mempunyai menu khusus untuk mengelola User Mitra.
>
> Tidak tersedia UI yang jelas untuk:
>
> * melihat seluruh User milik Mitra;
> * membuat User Mitra baru;
> * mengedit User;
> * mengubah role User Mitra;
> * menonaktifkan User;
> * mengaktifkan kembali User jika didukung;
> * mengurangi/menghapus User dari Mitra sesuai business rule;
> * reset kredensial jika memang diizinkan bagi Admin Mitra.

## Hasil yang diharapkan

> Workspace **Admin Mitra** menggunakan design language dan reusable component yang sama dengan workspace User THC, tetapi seluruh data dan action tetap dibatasi hanya pada **scope Mitra milik Admin tersebut**.

### 1. Dashboard Mitra mengikuti satu design language

> Dashboard Mitra harus menggunakan pola dashboard yang sama dengan `QC-0001` dan halaman lainnya.
>
> Contoh:
>
> ```text
> MITRA · PT ABC
>
> Dashboard Mitra
> Ringkasan Project, Warehouse, Material, Request, dan aktivitas Mitra.
>
> ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
> │      2       │ │      0       │ │      2       │
> │ Project aktif│ │ Project      │ │ Request      │
> │              │ │ selesai      │ │ Material     │
> └──────────────┘ └──────────────┘ └──────────────┘
>
> ┌──────────────┐ ┌──────────────┐
> │ Warehouse    │ │ User aktif   │
> │      n       │ │      n       │
> └──────────────┘ └──────────────┘
> ```
>
> Gunakan data real yang authorized.
>
> Jangan menambahkan KPI dummy hanya untuk memenuhi layout.

### 2. Project pada Dashboard Mitra

> Project dibuat lebih compact.
>
> Contoh:
>
> ```text
> Project                                           2 aktif
>
> PRJ-2608-0001
> kelurahan banar                                  [Aktif]
>
> aa
> kelurahan rembiga                               [Aktif]
> ```
>
> Gunakan reusable StatusBadge.

### 3. Saldo Warehouse Mitra

> Saldo Warehouse tetap dapat ditampilkan sebagai ringkasan.
>
> Gunakan shared quantity formatter dari `QC-0012`.
>
> Contoh:
>
> ```text
> Saldo Warehouse
>
> Material       Warehouse          Saldo
> ─────────────────────────────────────────
> Tiang 7m       testwhmitra        33 Btg
> ```
>
> Jangan menampilkan trailing `.000` apabila nilai merupakan integer.

### 4. Request Material

> Request Material pada dashboard ditampilkan menggunakan status badge dan identitas Project yang jelas.
>
> Contoh:
>
> ```text
> RM-2608-0002                         [Disetujui]
> PRJ-2608-0001 · kelurahan banar
> ```
>
> Gunakan prinsip `QC-0015` agar Project ID dan Project Name tidak hilang.

---

# Warehouse untuk Admin Mitra

### 5. Tambahkan kelompok menu Warehouse

> Sidebar Admin Mitra perlu mempunyai kelompok:
>
> ```text
> WAREHOUSE
> ```
>
> Di dalamnya tampilkan hanya modul Warehouse yang memang diizinkan untuk Admin Mitra berdasarkan permission/domain existing.
>
> Secara konseptual dapat mencakup:
>
> ```text
> Warehouse
> Operasional Material
> Daftar Surat Jalan
> Transit
> ```
>
> Nama menu final harus mengikuti route dan terminology existing.

### 6. Scope Warehouse harus tenant-safe

> Admin Mitra **hanya boleh melihat Warehouse milik Mitranya sendiri atau Warehouse yang memang authorized untuk Mitra tersebut**.
>
> Contoh:
>
> Admin Mitra:
>
> ```text
> pt abc
> ```
>
> hanya boleh melihat:
>
> ```text
> Warehouse milik pt abc
> ```
>
> dan tidak boleh melihat Warehouse:
>
> ```text
> THC
> Mitra B
> Mitra C
> ```
>
> kecuali ada explicit authorization/business relation yang memang mengizinkannya.

### 7. Permission backend tetap authoritative

> Jangan hanya:
>
> ```text
> tampilkan/sembunyikan menu berdasarkan role
> ```
>
> tanpa memastikan endpoint backend juga menerapkan tenant scope.
>
> Backend wajib memvalidasi:
>
> * Mitra pemilik;
> * role;
> * Warehouse assignment;
> * action yang boleh dilakukan.

### 8. Operasional Material

> Jika Admin Mitra memang mempunyai permission operasional Warehouse, gunakan halaman dan prinsip yang sama dengan `QC-0012`:
>
> * penerimaan;
> * pengeluaran;
> * SN;
> * Drum ID;
> * multi-item Surat Jalan;
> * pengiriman masuk;
> * Request Material fulfillment;
> * quantity formatter.
>
> Namun data harus dibatasi ke Warehouse dalam scope Mitra tersebut.

### 9. Request approved

> Request Material approved milik Project Mitra harus dapat mengikuti flow:
>
> ```text
> Project Mitra
>      ↓
> Request Material
>      ↓
> Approved
>      ↓
> Warehouse source yang authorized
>      ↓
> Qty Request vs Qty Kirim
>      ↓
> Surat Jalan
> ```
>
> Jangan membuat implementasi fulfillment khusus Admin Mitra jika shared flow Warehouse sudah tersedia.

---

# Manajemen User Mitra

### 10. Tambahkan menu User / Kelola User

> Admin Mitra harus mempunyai menu untuk mengelola User dalam Mitranya.
>
> Contoh sidebar:
>
> ```text
> MITRA & USER
>
> User
> ```
>
> atau:
>
> ```text
> ADMINISTRASI
>
> Kelola User
> ```
>
> Gunakan terminology yang konsisten dengan aplikasi.

### 11. Daftar User Mitra

> Halaman User Mitra hanya menampilkan User yang berada dalam scope Mitra login.
>
> Contoh:
>
> ```text
> User Mitra                                      3 User
>
> ┌──────────────────────────────────────────────────────┐
> │ Budi Santoso                              [Aktif]   │
> │ budi@example.com                                     │
> │ Role: Waspang                                        │
> │                                                      │
> │                             [Edit] [Nonaktifkan]     │
> └──────────────────────────────────────────────────────┘
> ```
>
> Jangan tampilkan:
>
> * User THC;
> * User Mitra lain;
> * Admin Mitra organisasi lain.

### 12. Tambah User Mitra

> Admin Mitra dapat membuat User baru untuk organisasi Mitranya.
>
> Contoh:
>
> ```text
> Tambah User
>
> Nama
> [                                  ]
>
> Email
> [                                  ]
>
> Nomor WhatsApp
> [                                  ]
>
> Role
> [ Pilih Role                    ▾ ]
>
>                          [Buat User]
> ```
>
> Relation Mitra tidak perlu dipilih secara bebas jika Admin Mitra hanya boleh membuat User pada Mitranya sendiri.
>
> Backend harus secara authoritative menetapkan:
>
> ```text
> user.mitra_id = mitra milik Admin login
> ```
>
> dan tidak mempercayai arbitrary `mitra_id` dari frontend.

### 13. Admin Mitra tidak boleh membuat User lintas Mitra

> Tidak boleh tersedia cara untuk:
>
> ```text
> Admin pt abc
>       ↓
> membuat User untuk pt xyz
> ```
>
> baik melalui UI maupun direct request/API manipulation.

### 14. Role yang dapat diberikan dibatasi

> Admin Mitra hanya dapat memilih **role yang memang diperbolehkan dalam scope Mitra**.
>
> Admin Mitra tidak boleh dapat melakukan privilege escalation menjadi:
>
> * Admin THC;
> * User THC;
> * role internal THC lain;
> * Admin Mitra organisasi lain.
>
> Daftar role harus berasal dari permission/business rule backend.
>
> Jangan hardcode sekadar berdasarkan opsi yang terlihat pada frontend.

### 15. Edit User

> Admin Mitra dapat mengedit User dalam Mitranya sesuai permission.
>
> Contoh:
>
> ```text
> Edit User
>
> Nama
> [ Budi Santoso ]
>
> Email
> [ budi@example.com ]
>
> WhatsApp
> [ 628... ]
>
> Role
> [ Waspang       ▾ ]
>
>                     [Batal] [Simpan perubahan]
> ```
>
> Gunakan design pattern yang sama dengan `QC-0006`.

### 16. Nonaktifkan User

> Admin Mitra dapat menonaktifkan User dalam scope Mitranya jika business rule mengizinkan.
>
> Gunakan state/warning action:
>
> ```text
> [Nonaktifkan]
> ```
>
> Setelah nonaktif, User tidak boleh mempunyai akses aktif sesuai authentication/authorization existing.

### 17. Aktifkan kembali

> Jika domain existing mendukung reactivation, tampilkan:
>
> ```text
> [Aktifkan kembali]
> ```
>
> untuk User nonaktif.
>
> Jangan membuat state baru jika backend belum mendukungnya.

### 18. Mengurangi / menghapus User

> Jika maksud business rule adalah menghapus User dari organisasi Mitra, action harus dibedakan dari sekadar menonaktifkan.
>
> Contoh:
>
> ```text
> [Hapus User]
> ```
>
> menggunakan destructive treatment.
>
> Namun sebelum implementasi, inspect:
>
> * foreign key/history;
> * audit;
> * Project assignment;
> * Warehouse assignment;
> * transaksi;
> * Request Material;
> * Surat Jalan.
>
> Jika User sudah memiliki histori dan sistem menggunakan soft delete/nonaktif, **jangan hard-delete hanya untuk memenuhi UI**.
>
> Prioritaskan semantics existing.

### 19. Tidak boleh menghapus User Mitra lain

> Backend harus mencegah Admin Mitra memodifikasi User dari Mitra lain walaupun user mencoba:
>
> * mengganti ID pada URL;
> * mengirim request manual;
> * memanipulasi payload.

### 20. Proteksi akun Admin sendiri

> Inspect business rule mengenai Admin Mitra menghapus atau menonaktifkan akunnya sendiri.
>
> Sistem sebaiknya mencegah kondisi yang menyebabkan Mitra kehilangan seluruh administrator apabila domain existing memang memerlukan minimal satu Admin Mitra.
>
> Jangan membuat rule baru tanpa memeriksa model permission yang sudah ada.

### 21. Reset kredensial

> Jika Admin Mitra memang authorized melakukan reset kredensial User bawahannya, reuse flow dari `QC-0006`.
>
> Gunakan sensitive action:
>
> ```text
> [Reset kredensial]
> ```
>
> Jangan otomatis memberikan capability ini hanya karena halaman User tersedia.
>
> Permission backend menentukan apakah action boleh dilakukan.

### 22. Audit User Management

> Action administratif harus tetap tercatat pada audit/activity existing:
>
> * create User;
> * edit User;
> * role change;
> * deactivate;
> * reactivate;
> * remove/delete;
> * credential reset jika tersedia.
>
> Audit minimal harus dapat mengidentifikasi actor Admin Mitra dan User target.

---

# Satu Bahasa Design

### 23. Reuse component User THC

> Jangan membuat design system kedua untuk Admin Mitra.
>
> Reuse component yang telah dibuat pada QC sebelumnya:
>
> * PageHeader;
> * KPI Card;
> * Card;
> * DataTable;
> * StatusBadge;
> * SearchableSelect;
> * normal Select;
> * FormControl;
> * Button variants;
> * Alert/Toast;
> * ConfirmationDialog;
> * EmptyState;
> * QuantityFormatter.

### 24. Perbedaan workspace hanya pada scope dan capability

> Secara visual:
>
> ```text
> User THC
> Admin Mitra
> User Mitra
> ```
>
> tetap menggunakan design system yang sama.
>
> Yang berbeda adalah:
>
> ```text
> data scope
> permission
> action/capability
> ```
>
> bukan style/layout aplikasi yang sama sekali berbeda.

### 25. Dashboard responsive

> Dashboard Mitra harus mengikuti responsive behavior yang sama:
>
> Desktop:
>
> * KPI/grid;
> * section dua kolom bila relevan.
>
> Tablet:
>
> * 2 KPI per row.
>
> Mobile:
>
> * satu kolom;
> * tidak ada horizontal scrolling.

### 26. Empty state

> Jika Mitra tidak mempunyai Warehouse:
>
> ```text
> Belum ada Warehouse dalam cakupan Mitra Anda.
> ```
>
> Jika tidak mempunyai User tambahan:
>
> ```text
> Belum ada User tambahan.
> ```
>
> Gunakan reusable EmptyState.
>
> Jangan menampilkan halaman kosong tanpa penjelasan.

### 27. Ketentuan implementasi

> Sebelum implementasi, inspect:
>
> 1. role/permission Admin Mitra existing;
> 2. relation User → Mitra;
> 3. relation Warehouse → owner/Mitra;
> 4. Warehouse assignment;
> 5. accessible route middleware;
> 6. API authorization;
> 7. role assignment rules;
> 8. user lifecycle;
> 9. existing User management component dari `QC-0006`;
> 10. shared Warehouse component hasil `QC-0011` sampai `QC-0014`.
>
> Jangan menduplikasi logic hanya karena persona berbeda.
>
> Gunakan shared service/component dan parameterized authorization/scope jika architecture memungkinkan.

## Dampak dan catatan

> **Admin Mitra** merupakan role administratif untuk organisasi Mitra, tetapi saat ini workspace yang tersedia lebih menyerupai dashboard read-only.
>
> Tidak tersedianya **Warehouse** menyebabkan gap antara:
>
> ```text
> Project
> Request Material
> ```
>
> dengan proses operasional:
>
> ```text
> Warehouse
> Surat Jalan
> Transit
> Receipt
> ```
>
> Tidak tersedianya **Manajemen User Mitra** juga membuat organisasi tidak dapat mengelola sendiri pengguna internalnya.
>
> Flow yang diharapkan:
>
> ```text
>                    Admin Mitra
>                         │
>          ┌──────────────┼──────────────┐
>          │              │              │
>       Project       Warehouse        User
>          │              │              │
>       Request       Operasional     Tambah/Edit
>       Material       Material       Nonaktifkan
>          │              │
>          └──────→ Surat Jalan
>                         │
>                       Transit
> ```
>
> Semua operasi tetap dibatasi tenant:
>
> ```text
> Admin Mitra A
>      ↓
> hanya data Mitra A
>
> Admin Mitra B
>      ↓
> hanya data Mitra B
> ```

## Acceptance Criteria

* [ ] Dashboard Mitra menggunakan design language yang sama dengan workspace User THC.
* [ ] Dashboard menggunakan reusable KPI/card/status component.
* [ ] Project ditampilkan lebih compact.
* [ ] Project status menggunakan badge.
* [ ] Request Material menggunakan status badge.
* [ ] Project ID dan Project Name tetap terlihat pada Request.
* [ ] Qty menggunakan shared quantity formatter.
* [ ] Sidebar Admin Mitra mempunyai kelompok/menu Warehouse sesuai permission.
* [ ] Admin Mitra hanya melihat Warehouse dalam scope Mitranya.
* [ ] Backend menerapkan Warehouse tenant scope.
* [ ] Operasional Warehouse reuse flow/component existing.
* [ ] Surat Jalan dan Transit tetap mengikuti prinsip QC-0012 sampai QC-0014.
* [ ] Admin Mitra mempunyai menu Kelola User.
* [ ] Daftar User hanya berisi User Mitra yang sama.
* [ ] Admin Mitra dapat menambah User Mitra jika authorized.
* [ ] User baru otomatis berada dalam Mitra Admin tersebut.
* [ ] Admin Mitra tidak dapat memilih arbitrary Mitra untuk User baru.
* [ ] Admin Mitra dapat mengedit User Mitranya jika authorized.
* [ ] Admin Mitra dapat mengubah role hanya ke role yang diperbolehkan.
* [ ] Admin Mitra tidak dapat memberikan role THC.
* [ ] Admin Mitra dapat menonaktifkan User jika authorized.
* [ ] Reactivation tetap mengikuti business rule existing.
* [ ] Remove/delete User mengikuti lifecycle existing.
* [ ] User berhistori tidak di-hard-delete jika domain menggunakan soft-delete/nonaktif.
* [ ] Admin Mitra tidak dapat mengakses User Mitra lain.
* [ ] API menolak cross-tenant manipulation.
* [ ] Reset kredensial hanya tersedia jika permission mengizinkan.
* [ ] User management action tetap diaudit.
* [ ] Tidak ada privilege escalation.
* [ ] Tidak ada data leakage lintas Mitra.
* [ ] Design system tidak diduplikasi khusus Admin Mitra.
* [ ] Responsive pada desktop, tablet, dan mobile.
* [ ] Tidak ada horizontal scrolling yang tidak terkendali.
* [ ] Tidak ada dummy/fake data.
* [ ] Tidak ada JavaScript/runtime error baru.

## Bukti QC

* `01-actual.png` — kondisi Dashboard Mitra saat login sebagai Admin Mitra; sidebar belum mempunyai menu Warehouse maupun menu pengelolaan User Mitra.
* `02-context.png` — konteks kebutuhan Admin Mitra untuk mengakses operasional Warehouse dan mengelola User dalam scope Mitranya.

> Tambahkan bukti berikutnya dengan nomor berurutan. Jangan mengganti atau menghapus bukti lama.

## Riwayat status

| Tanggal      | Status | Oleh   | Catatan                                                                                                                                                                                                                    |
| ------------ | ------ | ------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `2026-08-20` | `open` | Fatoni | Temuan dibuat. Workspace Admin Mitra perlu diselaraskan dengan design system, menyediakan akses Warehouse sesuai tenant scope, dan menyediakan manajemen User milik Mitra tanpa membuka akses lintas tenant atau role THC. |
