# ADR-0005 — Perpindahan material lewat surat jalan, dengan transit sebagai lokasi nyata

Status: diterima · Tanggal: 2026-08-12 · Tiket: [#5](https://github.com/thcjatim-bit/project-monitoring-system/issues/5)

## Konteks

Material milik THC berpindah antar gudang: dari gudang THC ke gudang titipan di mitra, kembali lagi, atau antar gudang THC. Alur yang disepakati: mitra request → THC approve → petugas gudang THC mengeluarkan barang → surat jalan terbit → barang dikirim → gudang tujuan mencatat masuk.

Pengiriman tidak seketika. Ada jeda berjam-jam sampai berhari-hari antara barang naik kendaraan dan barang tercatat di gudang tujuan, dan pada jeda itu barang bisa kurang. Buku transaksi append-only (ADR-0003) berarti tiap perpindahan harus punya baris yang bisa dipertanggungjawabkan, termasuk perpindahan yang gagal.

## Keputusan

### Dua siklus hidup yang terpisah

**Request bukan surat jalan.** Satu request bisa dipenuhi beberapa surat jalan (kirim bertahap), jadi satu kolom status tidak cukup untuk keduanya.

`material_requests`:

| Status | Ditulis oleh |
|---|---|
| `diajukan` | Mitra (pembuat) |
| `disetujui` / `ditolak` | THC — approver |
| `terpenuhi_sebagian` | **dihitung** — ada surat jalan diterima, qty belum penuh |
| `selesai` | **dihitung** — seluruh qty diterima |
| `ditutup` | THC — sisa tidak jadi dikirim |
| `dibatalkan` | Mitra, hanya selama `diajukan` |

`surat_jalans`: `terbit` → `diterima`, atau `terbit` → `dibatalkan`.
`terbit` oleh Petugas Gudang asal; `diterima` oleh Petugas Gudang tujuan; `dibatalkan` oleh THC dan **hanya selama masih `terbit`**.

`terpenuhi_sebagian` dan `selesai` tidak bisa dipindah manual — keduanya turunan dari qty yang benar-benar diterima. Status yang bisa diketik manusia hanya yang mewakili keputusan manusia; sisanya mewakili kenyataan barang, dan kenyataan barang tidak boleh bisa diketik.

`ditutup` ada supaya request yang sisanya tidak jadi dikirim tidak menggantung terbuka selamanya. Tanpa itu daftar request kehilangan arti setelah beberapa bulan.

### Satu dokumen untuk semua arah

Surat jalan dipakai untuk THC→mitra, mitra→THC, dan THC→THC. `surat_jalans.material_request_id` **nullable**: terisi kalau berawal dari request mitra, kosong kalau petugas THC memindahkan langsung. Tidak ada alur kedua untuk transfer antar gudang.

### Transit adalah lokasi, bukan bendera

Saat surat jalan terbit, stok pindah dari gudang asal ke lokasi `transit` milik surat jalan tersebut. Saat penerima konfirmasi, `transit` → gudang tujuan. Dua baris transaksi, bukan satu.

Alternatif yang ditolak — stok langsung mendarat di gudang tujuan saat surat jalan terbit — punya dua lubang: barang yang belum sampai sudah terhitung sebagai stok mitra, dan **tidak ada tempat menyimpan selisih** kalau yang tiba lebih sedikit. Selisih itu harus punya lokasi, kalau tidak dia menguap tanpa jejak.

Ini menambah nilai keempat pada lokasi material (ADR-0004): `warehouse` / `transit` / `project` / `terpasang`.

**Terima kurang**: penerima mencatat qty yang benar-benar diterima. Sisanya tertinggal di `transit` dan wajib diselesaikan THC — `hilang_dalam_perjalanan` (dengan penanggung jawab) atau `kembali_ke_asal` (salah hitung saat muat). Surat jalan tidak bisa berstatus `diterima` selagi masih ada sisa di transit.

### Surat jalan

Nomor `SJ-YYMM-NNNN`, urut per bulan, dibentuk dalam transaksi database, sebentuk dengan `PRJ-YYMM-NNNN`. Nomor yang dibatalkan **tidak dipakai ulang** — nomor yang hilang dari urutan adalah pertanyaan yang bisa dijawab, nomor yang dipakai dua kali tidak.

Wajib bisa dicetak A4 — sopir membawa kertas. Isinya: nomor + tanggal, gudang asal → gudang tujuan, nama Mitra, ID Project (bila ada), tabel barang (material, SN / ID drum, qty, unit), nama pengirim, sopir + plat nomor, kotak tanda tangan penerima, dan QR ke halaman surat jalan di sistem.

### Koreksi: tiga jalan, semuanya menambah baris

Pemisahnya satu kalimat: **selama barang belum diterima, koreksi = batal; setelah diterima, koreksi harus menyebut apakah barangnya benar-benar bergerak (retur) atau bukunya yang salah (koreksi).**

1. **Batal** — hanya selagi `terbit`. Baris balik `transit` → gudang asal. Surat jalan bertanda `dibatalkan`, cetakannya tidak berlaku.
2. **Retur** — barang sudah diterima lalu dikembalikan. **Surat jalan baru arah sebaliknya** yang menunjuk surat jalan asal, bukan pembatalan; barangnya memang bergerak lagi secara fisik.
3. **Koreksi** — salah ketik qty/material yang ketahuan setelah diterima. Baris pembalik yang menunjuk transaksi asal + baris yang benar, wajib alasan dan penanggung jawab, hanya untuk user THC.

## Konsekuensi

- Petugas gudang tujuan menanggung satu langkah kerja tambahan (konfirmasi terima). Itu harganya; imbalannya adalah selisih pengiriman jadi terlihat pada hari kejadian, bukan saat rekon akhir project.
- Barang yang sedang di jalan bisa ditanya kapan saja: `SUM(qty)` pada lokasi `transit` per surat jalan. Ini juga daftar pekerjaan yang belum selesai untuk THC.
- Layar stok gudang harus jelas bahwa transit **bukan** stok gudang manapun — ditampilkan sebagai kolom sendiri, bukan dijumlahkan ke gudang tujuan.
- Drum turunan (ADR-0004) dibentuk saat barang keluar gudang, jadi potongan sudah jadi entitas tersendiri sebelum masuk transit. Tidak ada perlakuan khusus.
- SN yang sedang dikirim berlokasi `transit`; dia tidak bisa dikeluarkan lagi dari gudang asal.
- Menambah lokasi keempat berarti tiap query stok harus menyebut lokasi mana yang dimaksud. Disengaja — query yang lupa menyebut lokasi memang query yang salah.
