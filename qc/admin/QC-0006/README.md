# QC-0006 — Konsistensi Design Manajemen User

| Field                     | Nilai                                |
| ------------------------- | ------------------------------------ |
| ID                        | `QC-0006`                            |
| Prefix                    | `user`                               |
| Status                    | `open`                               |
| Severity                  | `minor`                              |
| Tanggal/waktu pengujian   | `2026-08-20 14:36 WIB`               |
| Reviewer                  | Fatoni                               |
| Persona/role              | User THC                             |
| Halaman atau URL produksi | https://deploythc.web.id/admin/users |
| Browser/device            | Chrome / laptop Windows              |
| GitHub Issue              | [Bangun fondasi UI bersama dan selaraskan halaman dashboard, master, project, mitra, user](https://github.com/thcjatim-bit/project-monitoring-system/issues/90) |

## Ringkasan

> Halaman **Manajemen User** secara fungsi sudah berjalan, tetapi tampilan form tambah User, daftar User, edit User, status, tombol aksi, dan selector belum konsisten dengan design language yang digunakan pada Command Center, Portfolio, Project, dan Manajemen Mitra. Halaman perlu diselaraskan menggunakan komponen dan pola visual yang sama tanpa mengubah business logic yang sudah ada.

## Langkah reproduksi

1. Buka `https://deploythc.web.id/admin/users`.
2. Masuk sebagai persona/role **User THC**.
3. Buka menu **User** pada bagian **Mitra & User**.
4. Perhatikan form pembuatan User pada bagian atas halaman.
5. Perhatikan daftar User, status `Aktif`, bagian `Edit User`, serta tombol `Nonaktifkan`, `Reset kredensial`, dan `Hapus User`.
6. Buka salah satu bagian **Edit User** dan perhatikan input serta selector Mitra dan Role.
7. Bandingkan tampilan dan interaction pattern dengan halaman Command Center, Portfolio, Project, dan Manajemen Mitra.

## Hasil aktual

> Form pembuatan User saat ini ditampilkan sebagai satu baris horizontal berisi field Nama, Email, WhatsApp, cakupan/type User, Role, dan tombol `Buat User`.
>
> Field hanya mengandalkan placeholder dan belum memiliki label/form hierarchy yang konsisten.
>
> Setiap User ditampilkan sebagai card horizontal dengan format nama, email, dan status dalam satu baris. Status `Aktif` masih berupa teks biasa dan belum menggunakan reusable status badge.
>
> Saat `Edit User` dibuka, seluruh field edit tampil secara horizontal dan terlihat menyatu dengan informasi read-only.
>
> Tombol `Nonaktifkan`, `Reset kredensial`, dan `Hapus User` menggunakan treatment visual yang hampir sama sehingga tingkat kepentingan dan risiko setiap aksi kurang jelas.
>
> Selector Mitra masih menggunakan select biasa dan belum mengikuti reusable searchable dropdown yang digunakan pada modul lain.

## Hasil yang diharapkan

> Halaman **Manajemen User** menggunakan design language yang sama dengan halaman workspace lainnya, tanpa mengubah business logic maupun permission.
>
> Form **Tambah User** dibuat lebih terstruktur menggunakan card dan grid yang konsisten. Setiap field memiliki label yang jelas, misalnya:
>
> * Nama
> * Email
> * Nomor WhatsApp
> * Cakupan/Jenis User
> * Role
>
> Pada desktop, form dapat menggunakan grid 2–3 kolom dan tidak dipaksakan menjadi satu row panjang. Pada mobile, form berubah menjadi satu kolom.
>
> Daftar User dibuat sebagai management card/list yang lebih compact. Informasi utama yang ditampilkan tetap mencakup:
>
> * Nama User
> * Email
> * Status
> * Mitra/cakupan
> * Role
> * Nomor WhatsApp bila relevan
> * Aksi
>
> Status seperti `Aktif` atau `Nonaktif` menggunakan reusable badge/chip yang sama dengan modul lain.
>
> Mode read dan edit harus dapat dibedakan dengan jelas. Form edit hanya tampil ketika User memilih `Edit`, sedangkan kondisi default tetap compact.
>
> Selector **Mitra** menggunakan reusable searchable dropdown yang sama dengan `QC-0003`, sehingga user dapat mencari Mitra berdasarkan nama atau kode/identifier yang tersedia.
>
> Selector **Role** tidak wajib searchable karena jumlah pilihannya kecil, tetapi tetap menggunakan visual select yang sama dari sisi tinggi, border, radius, focus state, dan dropdown menu.
>
> Hierarchy tombol dibuat konsisten:
>
> * `Buat User` dan `Simpan perubahan` → primary action.
> * `Edit` dan `Batal` → secondary action.
> * `Reset kredensial` → secondary/sensitive action.
> * `Nonaktifkan` → state/warning action.
> * `Hapus User` → destructive action.
>
> Pesan seperti `Kata sandi baru dikirim melalui WhatsApp` ditampilkan menggunakan reusable notification/toast/alert dan tidak menjadi subtitle permanen halaman.
>
> Layout harus responsive pada desktop, tablet, dan mobile serta tidak menimbulkan horizontal scrolling.
>
> Implementasi wajib reuse component dan design token yang sudah digunakan pada Command Center, Portfolio, Project, dan Manajemen Mitra. Jangan membuat design system atau dropdown implementation baru apabila reusable component sudah tersedia.
>
> Perubahan tidak boleh mengubah:
>
> * business logic User;
> * authentication;
> * password generation;
> * mekanisme reset kredensial;
> * pengiriman WhatsApp;
> * permission;
> * authorization;
> * role semantics;
> * cakupan akses User;
> * relasi User–Mitra;
> * database schema;
> * API contract;
> * create/update/delete behavior;
> * audit/activity logging.

## Dampak dan catatan

> Kondisi saat ini tidak menyebabkan kegagalan fungsi utama, tetapi menyebabkan pengalaman penggunaan halaman Manajemen User berbeda dengan modul-modul lain yang sudah menggunakan design language baru.
>
> Form horizontal panjang membuat halaman lebih sulit dipindai dan akan semakin tidak efektif ketika jumlah field bertambah. Action dengan bobot visual yang sama juga dapat membuat user lebih sulit membedakan tindakan normal, sensitif, perubahan status, dan destructive.
>
> Standardisasi halaman ini penting agar seluruh workspace menggunakan pola yang sama:
>
> `Command Center → Portfolio → Project → Mitra → User`
>
> Selector Mitra juga perlu menggunakan reusable searchable dropdown dari QC sebelumnya agar tidak muncul implementasi selector yang berbeda-beda pada setiap modul.
>
> Untuk pilihan kecil seperti Role, searchable input tidak diperlukan. Cukup gunakan select biasa dalam visual component family yang sama.
>
> Acceptance utama:
>
> * Form tambah User lebih rapi dan memiliki label yang jelas.
> * Daftar User lebih compact.
> * Status menggunakan reusable badge.
> * Read state dan edit state mudah dibedakan.
> * Selector Mitra menggunakan reusable searchable dropdown.
> * Role menggunakan select konsisten tanpa wajib searchable.
> * Primary, secondary, warning, sensitive, dan destructive action dapat dibedakan secara visual.
> * Feedback create/update/reset menggunakan alert/toast yang konsisten.
> * Layout responsive dan tidak menghasilkan horizontal scroll.
> * Tidak ada perubahan business logic, permission, authorization, atau mekanisme kredensial.

## Bukti QC

* `01-actual.png` — kondisi halaman Manajemen User saat temuan terjadi.

> Tambahkan bukti berikutnya dengan nomor berurutan. Jangan mengganti atau menghapus bukti lama.

## Riwayat status

| Tanggal      | Status | Oleh   | Catatan                                                                                                                              |
| ------------ | ------ | ------ | ------------------------------------------------------------------------------------------------------------------------------------ |
| `2026-08-20` | `open` | Fatoni | Temuan dibuat. Halaman Manajemen User perlu diselaraskan dengan design language workspace dan reusable component dari QC sebelumnya. |
