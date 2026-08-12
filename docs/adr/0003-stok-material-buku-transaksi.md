# ADR-0003 — Stok material dihitung dari buku transaksi, dengan saldo sebagai cache

Status: diterima · Tanggal: 2026-08-12 · Tiket: [#4](https://github.com/thcjatim-bit/project-monitoring-system/issues/4)

## Konteks

Material milik THC, tapi bisa berada di gudang THC, gudang titipan di mitra, atau sudah keluar ke lapangan dan belum terpasang. Mitra harus bisa melihat empat angka saat mengisi progres harian: **di gudang**, **keluar gudang**, **terpasang**, **sisa belum terpasang**. Di akhir project ada **rekon** dan sisa dikembalikan ke gudang.

Dua pilihan yang biasa dipakai:

1. **Saldo saja** — satu kolom `qty` per (gudang, material), di-`UPDATE` tiap transaksi.
   Cepat dibaca, tapi tidak bisa menjawab "kenapa angkanya segini". Satu bug atau satu proses yang mati di tengah jalan membuat angka melenceng **selamanya**, tanpa cara mendeteksinya.
2. **Buku transaksi saja** — stok selalu `SUM(qty)` dari riwayat.
   Selalu bisa dipertanggungjawabkan dan mustahil melenceng, tapi tiap layar gudang menjumlahkan ratusan ribu baris, dan **tidak ada tempat untuk memasang `CHECK (qty >= 0)`** — stok minus baru ketahuan setelah dijumlahkan.

## Keputusan

Pakai **keduanya, dengan peran yang tegas berbeda**:

- **`material_transaksis` adalah kebenaran.** Append-only. Role `pms_app` hanya diberi `INSERT` + `SELECT`; `UPDATE` dan `DELETE` dicabut di level database. Koreksi = baris baru berlawanan yang menunjuk baris yang dikoreksi, bukan mengubah baris lama.
- **`material_stoks` adalah cache.** Satu baris per (lokasi, material), diperbarui **oleh trigger** pada `INSERT` ke `material_transaksis` — bukan oleh kode aplikasi.

Tiga pagar yang membuat angka tidak bisa melenceng:

1. **Trigger, bukan kode aplikasi.** Tidak ada jalan menulis transaksi tanpa saldo ikut berubah — termasuk lewat `psql`, seeder, atau job antrian yang lupa memanggil service.
2. **`CHECK (qty >= 0)` di `material_stoks`.** Stok minus ditolak database pada saat transaksi ditulis, bukan ditemukan seminggu kemudian. Hal yang sama untuk `drums`: `CHECK (sisa >= 0 AND sisa <= panjang_awal)`.
3. **Job rekonsiliasi harian** membandingkan `material_stoks.qty` dengan `SUM(material_transaksis.qty)` per lokasi. Selisihnya harus selalu nol; kalau tidak, kirim notifikasi. Karena buku transaksi adalah kebenaran, cache selalu bisa dibangun ulang dari nol.

## Konsekuensi

- Layar gudang membaca satu baris cache — murah, tidak peduli riwayat sudah berapa panjang.
- Tiap angka stok bisa ditelusuri ke daftar transaksi pembentuknya. Ini yang dibutuhkan saat rekon akhir project.
- Tulis jadi sedikit lebih mahal (satu `INSERT` + satu `UPDATE` cache). Tidak relevan pada ~10 user aktif.
- Baris cache jadi titik penguncian: dua petugas yang mengeluarkan material sama dari gudang sama akan berbaris otomatis (`UPDATE` mengunci baris). Ini yang diinginkan — mencegah dua-duanya membaca stok sama lalu sama-sama mengeluarkan.
- Riwayat tidak pernah dihapus. Ukurannya kecil (teks + angka); yang besar adalah foto, bukan ini.
- Konsekuensi yang diterima: **membatalkan sesuatu selalu meninggalkan jejak.** Salah input tidak bisa "dihapus saja" — harus dikoreksi dengan baris pembatalan yang ada penanggung jawabnya. Ini disengaja, karena ini catatan barang milik THC yang dititipkan ke pihak lain.
