# ADR 0006: Model Hak Akses (Matriks Aksi di atas Isolasi Mitra)

## Konteks

Kita butuh sistem hak akses (Grup/Role & Permissions) yang fleksibel namun aman, berdampingan dengan sistem isolasi data per Mitra. Isu yang harus dijawab adalah:
1. Bagaimana izin aplikasi digabungkan dengan isolasi Mitra tanpa risiko bocor data.
2. Kedalaman izin (hanya menu atau sampai aksi CRUD/Approve).
3. Cara mengaitkan user ke Mitra, Project, dan Gudang.
4. Perilaku UI saat user mengakses halaman yang tak diizinkan.

## Keputusan

1. **Pemisahan Lapis Keamanan (RLS + Matriks Aplikasi):**
   Matriks akses beroperasi murni di level aplikasi (mengatur apa yang *bisa diklik* dan rute mana yang *bisa dibuka*). Isolasi data tetap ditegakkan di level database lewat Row-Level Security (RLS) berdasarkan `mitra_id` (sesuai ADR-0001). Ini menjamin bahwa kesalahan konfigurasi centang menu tidak akan pernah membuka data lintas mitra.

2. **Izin Berbasis Aksi (Granular Permissions):**
   Izin diberikan secara spesifik per aksi (contoh: `read_project`, `create_project`, `update_project`, `delete_project`, `approve_material_request`). Bukan sekadar per menu.
   - Hak persetujuan (seperti `approve_material_request` atau verifikasi progres) adalah *permission* tersendiri yang secara logis hanya akan diberikan ke peran internal THC (Grup THC).

3. **Kaitan User ke Entitas:**
   - **User ke Mitra:** Pada tabel `users`, terdapat kolom `mitra_id` yang bisa kosong (nullable). Jika terisi, user tersebut adalah bagian dari Mitra tersebut dan terkena limitasi RLS Mitra. Jika `null`, user adalah bagian dari internal THC.
   - **User ke Project:** Tidak ada tabel pivot khusus per-project untuk user. User Mitra otomatis memiliki akses ke semua project yang dikerjakan oleh mitranya (disaring via RLS `mitra_id`).
   - **User ke Gudang (Petugas Gudang):** Menggunakan tabel pivot `user_warehouses`. Satu user (biasanya user THC) bisa ditugaskan ke beberapa gudang sekaligus.

4. **Perilaku UI Terhadap Hak Akses:**
   Jika user tidak memiliki izin untuk suatu menu atau aksi, menu tersebut **disembunyikan (tidak muncul)** dari antarmuka (sidebar/navigasi/tombol aksi). Jika user memaksa mengakses URL secara langsung, sistem mengembalikan status HTTP 403 Forbidden.

## Konsekuensi

- Matriks akses di level framework/kode (misal Spatie Permission di Laravel) tidak bertabrakan dengan RLS; justru mereka saling melengkapi (Aplikasi mengatur *fitur*, DB mengatur *data baris*).
- Menambah tabel `user_warehouses` untuk mengakomodasi petugas gudang THC yang memegang lebih dari satu gudang.
- Desain UI wajib responsif terhadap daftar permission user (menyembunyikan tombol secara kondisional).
