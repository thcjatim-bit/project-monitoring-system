# QC-0017 — Project Workspace dan Hak Edit Admin Mitra

| Field                     | Nilai                                                                                    |
| ------------------------- | ---------------------------------------------------------------------------------------- |
| ID                        | `QC-0017`                                                                                |
| Prefix                    | `mitra-project`                                                                          |
| Status                    | `open`                                                                                   |
| Severity                  | `major`                                                                                  |
| Tanggal/waktu pengujian   | `2026-08-20 16:42 WIB`                                                                   |
| Reviewer                  | Fatoni                                                                                   |
| Persona/role              | Admin Mitra                                                                              |
| Halaman atau URL produksi | `https://deploythc.web.id/projects/2` dan `https://deploythc.web.id/projects/2/planning` |
| Browser/device            | Chrome / laptop Windows                                                                  |
| GitHub Issue              | [Implement workspace Admin Mitra setelah capability disetujui](https://github.com/thcjatim-bit/project-monitoring-system/issues/95) |

## Ringkasan

> Saat login sebagai **Admin Mitra**, halaman Project dan Workspace Perencanaan sudah dapat dibuka tetapi seluruh fungsi Project bersifat read-only. Pada Project yang memang dimiliki oleh Mitra login, Admin Mitra seharusnya dapat menjalankan fungsi pengelolaan Project sesuai capability/permission yang diberikan, seperti mengelola perencanaan, RAB Jasa, baseline/TOC, Variation Order, progress, timeline, dan data operasional Project lainnya tanpa memperoleh akses lintas Mitra atau kewenangan khusus THC.

## Langkah reproduksi

1. Login sebagai **Admin Mitra**.
2. Buka Project milik Mitra sendiri, misalnya:

   * `https://deploythc.web.id/projects/2`
3. Perhatikan informasi Project Control Room.
4. Perhatikan bagian `Akses` menampilkan:

   * `Read Project`
5. Buka:

   * `https://deploythc.web.id/projects/2/planning`
   * `https://deploythc.web.id/projects/2/planning#rab-jasa`
   * `https://deploythc.web.id/projects/2/planning#baseline-toc`
   * `https://deploythc.web.id/projects/2/planning#variation-orders`
   * `https://deploythc.web.id/projects/2#project-progress`
   * `https://deploythc.web.id/projects/2#project-timeline`
6. Perhatikan bahwa Admin Mitra dapat membaca halaman tetapi tidak mempunyai control untuk mengisi atau mengubah data Project.
7. Perhatikan beberapa section menampilkan pesan bahwa akses baca module tertentu belum tersedia.
8. Bandingkan dengan fungsi Admin Mitra yang seharusnya dapat melakukan administrasi Project dalam scope Mitranya sendiri.

## Hasil aktual

> Project Control Room dapat dibuka oleh Admin Mitra, tetapi Project berada dalam mode read-only.
>
> Pada Project yang diuji terlihat:
>
> ```text
> Akses
> Read Project
> ```
>
> Section Project antara lain:
>
> * RAB Jasa;
> * Baseline / TOC;
> * Variation Order;
> * Progress Jasa;
> * Kesiapan Material;
> * Foto Pekerjaan;
> * Linimasa Gabungan;
> * Step Project;
>
> dapat dibaca sebagian, tetapi tidak tersedia action yang memungkinkan Admin Mitra melakukan pengelolaan data.
>
> Workspace Perencanaan juga menampilkan:
>
> ```text
> RAB Jasa
> Belum ada RAB Jasa untuk Project ini.
>
> Baseline / TOC
> Belum ada baseline.
>
> Variation Order
> Belum ada Variation Order.
> ```
>
> tetapi tidak tersedia action seperti:
>
> * tambah;
> * edit;
> * simpan;
> * submit;
> * update progress;
> * upload bukti;
> * atau action pengelolaan lain.
>
> Akibatnya Admin Mitra hanya dapat melihat Project miliknya tanpa dapat menjalankan pekerjaan administrasi Project.

## Hasil yang diharapkan

> Project Workspace Admin Mitra mengikuti design language yang sama dengan workspace User THC dan memberikan **write capability yang tepat pada Project milik Mitra login**, berdasarkan permission/capability backend.
>
> Jangan menjadikan semua Admin Mitra otomatis memiliki semua privilege. Implementasikan capability matrix yang jelas dan backend tetap authoritative.

### 1. Satu bahasa design

> Halaman:
>
> * Project Control Room;
> * Workspace Perencanaan;
> * RAB Jasa;
> * Baseline / TOC;
> * Variation Order;
> * Project Progress;
> * Timeline;
>
> harus mengikuti design system dari QC sebelumnya.
>
> Reuse:
>
> * PageHeader;
> * KPI Card;
> * Card;
> * StatusBadge;
> * DataTable;
> * FormControl;
> * SearchableSelect;
> * Button variants;
> * Alert/Toast;
> * EmptyState;
> * ConfirmationDialog.

### 2. Project header

> Header Project dibuat tetap compact dan informatif.
>
> Contoh:
>
> ```text
> PROJECT CONTROL ROOM
>
> PRJ-2608-0001
> kelurahan banar
>
> Mitra: pt abc              [Aktif]
>
> [Perencanaan] [Progress] [Linimasa]
> ```
>
> Jangan membuat style khusus untuk Admin Mitra.

### 3. Capability Admin Mitra

> Untuk Project yang berada dalam scope Mitra login, Admin Mitra seharusnya mendapatkan capability sesuai business rule, misalnya secara konseptual:
>
> ```text
> project.read
> project.plan
> project.progress.update
> project.timeline.read
> project.rab.manage
> project.baseline.manage
> project.vo.manage
> project.photo.manage
> material.request.manage
> ```
>
> Nama permission aktual harus mengikuti architecture existing.
>
> Jangan hardcode permission berdasarkan nama role di frontend saja.

### 4. Project milik Mitra sendiri

> Admin Mitra hanya dapat memperoleh write access jika Project memang termasuk scope Mitranya.
>
> Contoh:
>
> ```text
> Admin Mitra: pt abc
>
> Project A → Mitra pt abc → boleh sesuai capability
> Project B → Mitra pt xyz → tidak boleh
> ```
>
> Backend harus melakukan pengecekan tenant/project ownership pada setiap write action.

### 5. Jangan membuka privilege THC

> Admin Mitra tidak boleh otomatis memperoleh action yang khusus diperuntukkan bagi THC, misalnya jika domain mempunyai:
>
> * approval final;
> * approval commercial;
> * perubahan ownership lintas Mitra;
> * administrasi global;
> * override audit;
> * konfigurasi internal THC.
>
> Pisahkan:
>
> ```text
> manage Project sendiri
> ```
>
> dari:
>
> ```text
> global/admin THC authority
> ```

### 6. RAB Jasa

> Jika Admin Mitra mempunyai capability RAB Jasa, section `RAB Jasa` harus menyediakan action yang relevan.
>
> Contoh:
>
> ```text
> RAB Jasa
>
> [Tambah Item Jasa]
>
> Pekerjaan Jasa       Qty       Harga Satuan      Total
> ───────────────────────────────────────────────────────
> Splicing             10        Rp ...            Rp ...
> ```
>
> Gunakan searchable select untuk **Pekerjaan Jasa** apabila jumlah option dapat banyak.
>
> Jangan membuat dropdown baru jika shared searchable select sudah tersedia.

### 7. Harga RAB

> Pertahankan business rule existing:
>
> ```text
> Harga satuan dibekukan ketika baris RAB dibuat.
> ```
>
> Perubahan harga master setelah itu tidak boleh diam-diam mengubah historical RAB.
>
> QC ini tidak mengubah calculation semantics existing.

### 8. Baseline / TOC

> Jika Admin Mitra mempunyai capability planning/baseline, section harus menyediakan action seperti:
>
> ```text
> [Buat Baseline]
> ```
>
> atau action sesuai workflow existing.
>
> Pertahankan prinsip:
>
> * baseline pertama menjadi Original Baseline;
> * revisi berikutnya menjadi Revised Baseline;
> * Original Baseline tidak ditimpa.
>
> Jangan mengubah versioning semantics hanya untuk menambahkan UI edit.

### 9. Variation Order

> Jika Admin Mitra mempunyai capability VO:
>
> tampilkan action:
>
> ```text
> [Tambah Variation Order]
> ```
>
> sesuai workflow existing.
>
> VO harus tetap menjaga relation ke:
>
> * Project;
> * baseline/RAB terkait;
> * item Jasa;
> * perubahan Qty;
> * harga baru jika memang domain mendukungnya.

### 10. Progress Jasa

> Admin Mitra yang authorized harus dapat menginput/update progress pekerjaan Project.
>
> Contoh:
>
> ```text
> Pekerjaan Jasa
> Splicing
>
> Progress
> [ 75 ] %
>
> Tanggal aktual
> [ ... ]
>
> Catatan
> [ ... ]
>
>                    [Simpan Progress]
> ```
>
> Field aktual mengikuti model existing.
>
> Jangan menciptakan field dummy hanya untuk memenuhi desain.

### 11. Verified vs submitted progress

> Jika domain membedakan:
>
> * progress yang diinput Mitra;
> * progress yang sudah diverifikasi THC;
>
> maka kedua state harus tetap berbeda.
>
> Admin Mitra boleh menginput/submitted progress sesuai capability tetapi **tidak otomatis boleh memverifikasi progress-nya sendiri** jika verification merupakan fungsi THC.
>
> Contoh:
>
> ```text
> Progress Mitra       75%
> Verified             60%
> ```
>
> Jangan menggabungkan keduanya.

### 12. Foto Pekerjaan

> Jika Project menggunakan Foto Pekerjaan sebagai bukti lapangan, Admin Mitra yang authorized harus dapat mengunggah bukti untuk Project miliknya.
>
> Reuse upload component existing.
>
> Foto harus tetap terikat ke:
>
> * Project;
> * step/progress jika relevan;
> * uploader;
> * timestamp.
>
> Jangan membuka akses ke file Project Mitra lain.

### 13. Step Project

> Section Step Project dibuat lebih jelas menggunakan stepper/timeline visual yang konsisten.
>
> Contoh:
>
> ```text
> Design        [Aktif]
> Survey        [Belum selesai]
> DRM           [Belum selesai]
> SPK           [Belum selesai]
> Pengadaan     [Belum selesai]
> Delivery      [Belum selesai]
> MOS           [Belum selesai]
> Deployment    [Belum selesai]
> Test Comm     [Belum selesai]
> ATP           [Belum selesai]
> GO Live       [Belum selesai]
> ```
>
> Jika Admin Mitra memang diizinkan mengubah step tertentu, tampilkan action berdasarkan capability.
>
> Jangan membuat seluruh step editable tanpa business rule.

### 14. Timeline / Linimasa

> Timeline Project harus menggunakan pola activity feed/timeline yang konsisten.
>
> Jika Admin Mitra mempunyai capability menambahkan komentar/update tertentu, action hanya muncul pada event/context yang authorized.
>
> Historical event tidak boleh diedit sembarangan.

### 15. Kesiapan Material

> Kesiapan Material harus tetap terhubung dengan flow:
>
> ```text
> Project
>     ↓
> Request Material
>     ↓
> Approval
>     ↓
> Warehouse
>     ↓
> Surat Jalan
>     ↓
> Receipt
> ```
>
> Admin Mitra tidak perlu mendapatkan direct stock editing.
>
> Gunakan ledger/material flow existing dari `QC-0012`.

### 16. Request Material dari Project

> Dari Project, Admin Mitra yang mempunyai capability Request Material harus dapat menuju/membuat Request Material dengan Project sudah terisi.
>
> Jangan meminta user memilih ulang Project jika context Project sudah diketahui.
>
> Relation:
>
> ```text
> Project ID
> Project Name
> Mitra
> ```
>
> harus tersimpan pada Request sebagaimana requirement `QC-0015`.

### 17. Empty state harus actionable

> Saat belum ada data, jangan hanya:
>
> ```text
> Belum ada RAB Jasa untuk Project ini.
> ```
>
> jika user sebenarnya authorized untuk membuat data.
>
> Tampilkan:
>
> ```text
> Belum ada RAB Jasa.
>
> [Tambah RAB Jasa]
> ```
>
> Untuk user read-only:
>
> ```text
> Belum ada RAB Jasa untuk Project ini.
> ```
>
> tanpa action.
>
> UI harus mengikuti capability aktual.

### 18. Permission-aware UI

> Component harus membaca capability dan menampilkan action secara konsisten.
>
> Contoh:
>
> ```text
> canEditRAB = true
>       ↓
> tampilkan Tambah/Edit
>
> canEditRAB = false
>       ↓
> read-only
> ```
>
> Namun hiding UI bukan security boundary.
>
> Backend tetap harus menolak request unauthorized.

### 19. Perbaiki informasi Akses

> Saat ini terlihat:
>
> ```text
> Akses
> Read Project
> ```
>
> Informasi tersebut sebaiknya merefleksikan capability yang sebenarnya.
>
> Tidak perlu menampilkan daftar permission teknis panjang kepada user.
>
> Dapat menggunakan label sederhana seperti:
>
> ```text
> Akses
> Kelola Project
> ```
>
> atau:
>
> ```text
> Read-only
> ```
>
> sesuai permission.
>
> Terminologi final mengikuti design/product language existing.

### 20. Navigation Planning

> Tombol:
>
> * Control Room;
> * Detail RAB Jasa;
> * Detail Baseline / TOC;
> * Detail Variation Order;
>
> perlu menggunakan secondary navigation/tab pattern yang konsisten.
>
> Hindari tombol besar yang terlihat seperti primary actions jika fungsinya hanya navigasi.

### 21. Anchor navigation

> URL:
>
> * `#rab-jasa`;
> * `#baseline-toc`;
> * `#variation-orders`;
> * `#project-progress`;
> * `#project-timeline`;
>
> tetap dapat digunakan.
>
> Pastikan fixed header tidak menutupi heading target ketika anchor dibuka.
>
> Jika menggunakan tab/section navigation, deep-link anchor tetap sebaiknya berfungsi.

### 22. Design density

> Section kosong tidak perlu menggunakan card terlalu tinggi.
>
> Gunakan compact EmptyState.
>
> Section dengan banyak data dapat menggunakan table/card sesuai kebutuhan.
>
> Tujuan:
>
> ```text
> lebih banyak informasi terlihat
> tanpa mengorbankan readability
> ```

### 23. Responsive

> Desktop:
>
> * KPI 3–4 card per row;
> * planning sections menggunakan grid bila sesuai;
> * navigation tetap compact.
>
> Tablet:
>
> * grid berkurang menjadi 2/1 kolom.
>
> Mobile:
>
> * satu kolom;
> * action dapat wrap;
> * tidak ada horizontal page scrolling yang tidak terkendali.

### 24. Shared components

> Jangan membuat UI khusus Admin Mitra jika fungsi yang sama sudah digunakan User THC.
>
> Ideal:
>
> ```text
> ProjectControlRoom
>        +
> capabilities
>        +
> tenant scope
> ```
>
> bukan:
>
> ```text
> ProjectControlRoomTHC
> ProjectControlRoomMitra
> ```
>
> apabila architecture memungkinkan reuse.

### 25. Tenant isolation

> Ini requirement kritis.
>
> Backend harus memastikan Admin Mitra tidak dapat mengakses atau mengubah Project Mitra lain melalui:
>
> * perubahan `/projects/{id}`;
> * request API manual;
> * manipulasi form;
> * IDOR;
> * direct POST/PATCH/DELETE;
> * nested endpoint planning/progress.
>
> Semua query/write harus scoped terhadap Mitra user atau explicit access relation.

### 26. Cross-tenant test

> Regression/security test minimal:
>
> ```text
> Admin Mitra A
> PATCH Project A → allowed sesuai capability
>
> Admin Mitra A
> PATCH Project B milik Mitra B → 403/404 sesuai convention
> ```
>
> Hal yang sama harus diuji untuk:
>
> * RAB;
> * baseline;
> * VO;
> * progress;
> * foto;
> * Request Material.

### 27. Jangan privilege escalation lewat role

> Jangan hanya memeriksa:
>
> ```text
> role == admin_mitra
> ```
>
> lalu memberikan write terhadap semua Project.
>
> Pemeriksaan harus mencakup:
>
> ```text
> role/capability
> +
> tenant
> +
> Project relation
> +
> object-level permission
> ```

### 28. Audit trail

> Seluruh write action Admin Mitra harus masuk audit/activity existing, minimal:
>
> * actor;
> * Mitra;
> * Project;
> * action;
> * timestamp;
> * object terkait.
>
> Contoh:
>
> ```text
> Admin Mitra A
> memperbarui Progress Jasa
> Project PRJ-2608-0001
> ```
>
> Jangan membuat log terpisah yang tidak terintegrasi dengan activity model existing.

### 29. Approval tetap terpisah

> Untuk object yang mempunyai workflow approval, bedakan:
>
> ```text
> buat/edit/submit oleh Mitra
> ```
>
> dengan:
>
> ```text
> approve/verify oleh THC
> ```
>
> jika itu memang business rule.
>
> Admin Mitra tidak otomatis memperoleh hak approve hanya karena ia dapat mengedit object.

### 30. Ketentuan implementasi

> Sebelum implementasi, inspect:
>
> 1. role dan capability Admin Mitra;
> 2. Project ownership/relation;
> 3. current `Read Project` gate;
> 4. RAB permission;
> 5. baseline permission;
> 6. Variation Order permission;
> 7. Progress permission;
> 8. Material permission;
> 9. photo/file permission;
> 10. timeline permission;
> 11. object-level authorization;
> 12. audit logging;
> 13. shared Project component User THC.
>
> Jangan menghapus security gate hanya agar form muncul.
>
> Ubah capability secara eksplisit dan tetap fail-closed apabila permission tidak tersedia.

## Dampak dan catatan

> Admin Mitra saat ini dapat melihat Project tetapi tidak dapat melakukan pekerjaan administrasi Project miliknya.
>
> Kondisi saat ini:
>
> ```text
> Admin Mitra
>     ↓
> Project milik Mitra
>     ↓
> Read-only
>     ↓
> Tidak dapat mengisi RAB / planning / progress
> ```
>
> Hal ini membuat role Admin Mitra bergantung pada User THC untuk hampir seluruh perubahan Project.
>
> Flow target:
>
> ```text
> Admin Mitra
>      ↓
> Project milik Mitra
>      │
>      ├── Planning
>      ├── RAB Jasa
>      ├── Baseline / TOC
>      ├── Variation Order
>      ├── Progress
>      ├── Foto Pekerjaan
>      └── Request Material
>             ↓
>       sesuai capability
> ```
>
> Sementara fungsi governance tetap terpisah:
>
> ```text
> Mitra → input / update / submit
> THC   → verify / approve / governance
> ```
>
> apabila sesuai business rule existing.

## Acceptance Criteria

* [ ] Project Control Room menggunakan design language yang sama dengan modul lainnya.
* [ ] Workspace Perencanaan menggunakan shared components.
* [ ] Admin Mitra tidak otomatis hanya `Read Project` untuk Project miliknya jika capability write memang diberikan.
* [ ] Write capability ditentukan oleh backend permission/capability.
* [ ] Admin Mitra hanya dapat mengelola Project dalam scope Mitranya.
* [ ] Project Mitra lain tidak dapat dibaca/diubah tanpa explicit authorization.
* [ ] Direct URL manipulation tidak melewati tenant scope.
* [ ] API write lintas tenant ditolak.
* [ ] RAB Jasa menampilkan action tambah/edit bagi Admin Mitra yang authorized.
* [ ] Baseline/TOC dapat dikelola jika capability tersedia.
* [ ] Original Baseline tidak ditimpa oleh revisi.
* [ ] Variation Order dapat dikelola jika capability tersedia.
* [ ] Progress dapat diinput/update jika capability tersedia.
* [ ] Progress Mitra tidak otomatis menjadi verified jika verification adalah kewenangan THC.
* [ ] Foto Pekerjaan dapat dikelola jika capability tersedia.
* [ ] Step Project hanya editable sesuai business rule.
* [ ] Timeline/history tidak dapat diedit sembarangan.
* [ ] Request Material dapat tetap terhubung dengan Project.
* [ ] Project ID dan Project Name tetap terbawa ke Request Material.
* [ ] Empty state mempunyai CTA hanya jika user authorized.
* [ ] User read-only tidak melihat action edit.
* [ ] Informasi `Akses` mencerminkan capability aktual.
* [ ] Navigation Control Room / RAB / Baseline / VO konsisten.
* [ ] Deep-link anchor tetap berfungsi.
* [ ] Design responsive pada desktop, tablet, dan mobile.
* [ ] Tidak ada duplicate component khusus Mitra jika shared component dapat direuse.
* [ ] Approval/verification khusus THC tidak otomatis diberikan ke Admin Mitra.
* [ ] Semua mutation penting masuk audit trail.
* [ ] Permission fail-closed.
* [ ] Tidak ada privilege escalation.
* [ ] Tidak ada data leakage lintas Mitra.
* [ ] Tidak ada dummy/fake data.
* [ ] Tidak ada horizontal scrolling yang tidak terkendali.
* [ ] Tidak ada JavaScript/runtime error baru.

## Bukti QC

* `01-control-room-actual.png` — kondisi Project Control Room saat login sebagai Admin Mitra; Project dapat dibaca tetapi akses ditampilkan sebagai `Read Project`.
* `02-planning-actual.png` — kondisi Workspace Perencanaan; RAB Jasa, Baseline/TOC, dan Variation Order dapat dilihat tetapi tidak tersedia action pengelolaan.

> Tambahkan bukti berikutnya dengan nomor berurutan. Jangan mengganti atau menghapus bukti lama.

## Riwayat status

| Tanggal      | Status | Oleh   | Catatan                                                                                                                                                                                                                                                                                      |
| ------------ | ------ | ------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `2026-08-20` | `open` | Fatoni | Temuan dibuat. Admin Mitra dapat melihat Project milik Mitranya tetapi saat ini hanya memperoleh akses read-only. Project Workspace perlu mengikuti design system dan mendukung write capability berbasis permission serta tenant scope tanpa membuka privilege THC atau akses lintas Mitra. |
