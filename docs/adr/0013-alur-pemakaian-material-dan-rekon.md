# ADR-0013 — Alur Pemakaian Material harian dan Rekon di akhir/tengah project

Status: diterima · Tanggal: 2026-08-13 · Tiket: [#16](https://github.com/thcjatim-bit/project-monitoring-system/issues/16)

## Konteks

ADR-0003/0004 mengunci bentuk buku transaksi dan tiga jenis material. ADR-0005 mengunci alur material sampai ke gudang mitra. Yang belum dikunci: bagaimana mitra memakai material dari gudangnya sendiri untuk project, dan bagaimana sisanya ditutup.

## Keputusan

### Pemakaian Material — entitas baru, bukan Request Material

Request Material (ADR-0005) arahnya THC → mitra, dengan pengiriman bertahap. Pemakaian Material arahnya mitra mengeluarkan dari stok yang **sudah** dititip di gudangnya sendiri, satu qty satu tujuan — tidak ada pengiriman bertahap, tidak butuh dokumen cetak seperti Surat Jalan karena barang tidak berpindah gudang.

`pemakaian_materials`: `diajukan` (mitra buat) → `disetujui`/`ditolak` (THC). Mitra boleh batalkan hanya selama `diajukan`; tidak bisa edit qty (batalkan lalu ajukan ulang, sama sederhananya dengan pola `dibatalkan` pada Surat Jalan).

**Stok baru berkurang saat `disetujui`.** Baris `diajukan` tidak menulis apa pun ke `material_transaksis` — konsisten dengan buku append-only, baris yang bisa ditolak tidak boleh pernah masuk buku. Konsekuensinya: kalau approve tertunda berhari-hari, layar stok gudang belum mencerminkan barang yang mitra mungkin sudah bawa secara fisik. Diterima sebagai trade-off; mitigasi cukup badge "N pengajuan menunggu approve", bukan pengurangan stok optimistik yang bisa salah kalau ditolak.

Saat `disetujui`, ditulis transaksi `warehouse → project`. Progres harian mitra menulis `project → terpasang`, dan **ditolak** (`CHECK`-level) bila membuat saldo `lokasi_tipe = 'project'` untuk project itu jadi minus — mitra tidak bisa mencatat terpasang lebih dari yang pernah keluar gudang untuk project tsb.

### Status Project — field baru, terpisah dari Step

Step (ADR-0011) sengaja fleksibel — bisa dilompati/dimundurkan, cuma penanda fase. Memakainya juga sebagai penanda "ditutup" mencampur progres fase dengan penutupan administratif. `projects.status_project`: `aktif` / `selesai`.

`status_project` jadi `selesai` otomatis begitu rekon **aktif** untuk project itu (lihat di bawah) berstatus `disetujui`. Kalau rekon susulan dibuat setelah itu, `status_project` balik `aktif` sampai rekon susulan itu `disetujui` lagi.

### Rekon Material — banyak per project, saling mengoreksi

`project_rekons`, nomor `REK-YYMM-NNNN` otomatis (pola sama dengan `SJ-YYMM-NNNN`). Satu project bisa punya beberapa baris rekon. Rekon susulan **mengoreksi** rekon sebelumnya lewat `koreksi_dari_id` — pola sama dengan koreksi `material_transaksis` (ADR-0003): baris pembalik + baris baru, rekon lama tidak dihapus. Ditolak: menandai rekon lama `dibatalkan` dan berdiri sendiri — itu menghapus jejak kenapa rekon diulang.

"Rekon aktif" = ujung rantai koreksi (`koreksi_dari_id` kosong di dirinya sendiri, atau tidak ada rekon lain yang mengoreksinya).

Trigger pembuatan rekon:
- **Manual**, THC bisa membuka rekon kapan saja (termasuk project belum sampai *GO Live*).
- **Otomatis** saat Step *GO Live* tercapai — **hanya jika** project belum punya rekon aktif berstatus `disetujui`. Kalau sudah ada (THC sudah menuntaskan rekon manual lebih dulu), *GO Live* tidak memicu rekon kedua.

Isi per baris material saat rekon dibuka: `keluar_gudang`, `terpasang`, `sisa_project` — **prasi-isi otomatis** dari saldo `material_stoks` (`lokasi_tipe = 'project'`) saat itu, bukan diketik ulang. THC mengisi `dikembalikan` dan `hilang_rusak` per baris, dengan kategori tetap untuk yang hilang/rusak: `hilang` / `rusak` / `waste_wajar`, plus catatan bebas. Penanggung jawab default **mitra**, bisa diubah THC per baris. Approver: THC saja.

Sisa yang `dikembalikan` menulis transaksi `project → warehouse` ke **gudang mitra asal** (titipan yang sama) — bukan ditarik balik ke gudang THC. Penarikan ke gudang THC, kalau memang perlu, lewat Surat Jalan transfer biasa (ADR-0005), di luar rekon.

### Drum turunan — tidak ada perlakuan khusus saat dikembalikan

Drum turunan (`DRM-00042-1`, ADR-0004) yang kembali sebagai sisa rekon langsung berstatus `lokasi_tipe = 'warehouse'` seperti drum manapun; boleh dipakai project lain apa adanya. `induk_drum_id` sudah cukup melacak silsilah tanpa proses tambahan.

## Konsekuensi

- Tiga entitas baru: `pemakaian_materials`, `project_rekons` (dengan `koreksi_dari_id`), kolom `projects.status_project`.
- Dua `jenis_transaksi` baru pada buku: `rekon_kembali` (project → warehouse) dan `rekon_hilang`/`rekon_waste` (keluar dari `project`, tanpa lokasi tujuan).
- `status_project` bisa berayun `selesai` → `aktif` → `selesai` kalau rekon dikoreksi berkali-kali setelah ditutup. Ini disengaja — status project mengikuti kenyataan rekon, bukan sekali kunci selamanya.
- Layar progres harian mitra (empat angka, ADR-0004 §5) tidak berubah bentuk; Pemakaian Material cuma sumber baru bagi transaksi `warehouse → project` yang sebelumnya diasumsikan langsung tanpa status approval eksplisit.
