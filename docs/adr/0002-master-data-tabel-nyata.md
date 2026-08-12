# ADR-0002 — Master data pakai tabel nyata + CRUD generik, bukan tabel serba-guna

**Status**: Diterima — 2026-08-12
**Konteks tiket**: [#3 Model data inti dan penegakan isolasi mitra](https://github.com/thcjatim-bit/project-monitoring-system/issues/3)

## Konteks

Master data (Material, Unit, PoP, Pekerjaan Jasa, Mitra, Warehouse) harus bisa ditambah dan dikurangi dari UI tanpa memanggil developer. Godaan yang biasa muncul: satu tabel `master_data(tipe, kode, nama, atribut jsonb)` yang menampung semuanya. Itu membuat query mustahil — tidak ada foreign key, tidak ada tipe kolom, laporan berubah jadi tumpukan cast dan join ke tabel yang sama berkali-kali.

## Keputusan

Pisahkan dua hal yang sering dicampur:

- **Menambah baris master** (material baru, PoP baru, jenis pekerjaan baru) → **dari UI, tanpa koding**. Ini yang sebenarnya user minta, dan ini kejadian harian.
- **Menambah jenis master baru** (entitas yang belum pernah ada) → **butuh migrasi + satu kelas konfigurasi**. Ini kejadian tahunan, dan wajar melibatkan developer.

Realisasinya:

1. Satu **tabel nyata per entitas master**, dengan kolom bertipe benar dan foreign key sungguhan.
2. Setiap tabel master punya `kode` (unik, dibaca manusia), `nama`, `aktif` (boolean), timestamps.
3. Satu komponen Livewire **CRUD generik** yang didorong oleh kelas konfigurasi per entitas — mendeklarasikan kolom, label Indonesia, aturan validasi, dan relasi pilihan. Menambah entitas master baru = satu migrasi + satu kelas konfigurasi, bukan satu layar baru dari nol.
4. **Kolom tambahan yang jarang dipakai** boleh masuk kolom `jsonb` `atribut` pada tabel yang bersangkutan — sebagai pelengkap, bukan pengganti kolom nyata. Apa pun yang dipakai untuk filter, join, atau agregasi harus jadi kolom nyata.
5. **Tidak ada hapus keras** untuk baris yang sudah direferensikan transaksi: tombol Hapus menandai `aktif = false`. Baris nonaktif hilang dari dropdown, tetap utuh di riwayat. Hapus keras hanya boleh saat belum pernah dipakai.

## Konsekuensi

- Query laporan tetap wajar: join biasa, indeks biasa, foreign key menjaga integritas.
- Ada sedikit boilerplate per entitas (migrasi + kelas konfigurasi) — dibayar sekali, jarang.
- UI master data konsisten sendirinya karena datang dari satu komponen.

## Alternatif yang ditolak

- **EAV / tabel `master_data` serba-guna** — ditolak: mematikan foreign key dan tipe kolom; laporan dan kurva S jadi mahal dan rapuh.
- **Layar CRUD ditulis manual satu per satu** — ditolak: enam layar yang sama isinya, berbeda bug-nya.
