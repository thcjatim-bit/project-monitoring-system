# ADR-0015 — Onboarding mitra, kredensial, dan approval harga jasa PKS

**Status**: Diterima — 2026-08-13
**Konteks tiket**: [#15 Onboarding mitra dan persetujuan harga jasa PKS](https://github.com/thcjatim-bit/project-monitoring-system/issues/15)

## Konteks

`Harga Jasa Mitra` sudah ada sejak ADR-0001/ADR-0003 sebagai sumber bobot RAB Jasa, dan `PKS` disebut di `CONTEXT.md` sebagai "sumber harga jasa" tanpa bentuk data. Yang belum diputuskan: bagaimana user mitra pertama lahir dan dapat kredensial, bagaimana harga PKS masuk sistem dan disahkan, apa yang terjadi saat harga direvisi, dan nasib user saat kerja sama berakhir.

## Keputusan

1. **Onboarding satu langkah.** THC membuat entitas Mitra + user admin-mitra pertamanya lewat satu form, bukan dua alur terpisah — menghindari Mitra tanpa user atau user tanpa Mitra.

2. **Kredensial via WA, teks polos, otomatis.** Password awal maupun hasil reset dikirim otomatis lewat WA gateway (WAHA) sebagai teks polos ke nomor WA terdaftar mitra — bukan link token satu-pakai. Dipilih karena WA gateway sudah infra existing untuk notifikasi, dan sistem ini sudah menerima trade-off keamanan setara di tempat lain (server tanpa HTTPS di fase awal, lihat issue #1). Reset password mendukung dua jalur: self-service oleh mitra dan reset paksa oleh THC dari panel.

3. **PKS jadi entitas sendiri**, bukan sekadar catatan teks — nomor, tanggal mulai/berakhir, file lampiran opsional. Tepat **satu PKS aktif** per mitra pada satu waktu; PKS baru mengisi tanggal mulai setelah PKS lama berakhir. Ini menyederhanakan form pengajuan harga (otomatis pakai PKS aktif mitra, tidak perlu dropdown pilih PKS) dan memberi tempat mencatat tanggal kerja sama berakhir.

4. **Harga Jasa Mitra kena approval, pola sama dengan Request Material/Pemakaian Material**: siklus `diajukan` (mitra, memilih dari katalog Pekerjaan Jasa yang sudah ada — mitra tidak bisa mengusulkan jenis pekerjaan baru, itu di luar sistem ke THC) → `disetujui`/`ditolak` (THC). Hanya baris `disetujui` yang boleh dipakai membuat baris RAB Jasa. `mitra_id` FK wajib ke satu PKS.

5. **Revisi harga = baris baru**, bukan edit di tempat — konsisten dengan pola "koreksi bukan hapus" (buku transaksi, rekon material). Baris baru berstatus `diajukan` boleh merujuk baris lama; baris lama tetap `disetujui` dan tetap jadi harga yang berlaku (termasuk untuk RAB baru) sampai baris baru disetujui dan tanggal berlakunya tiba. RAB Jasa yang sudah dibuat tidak pernah berubah — harganya sudah beku di `project_rab_jasas` sejak baris dibuat (ADR-0001), revisi harga PKS tidak menyentuhnya sama sekali.

6. **Nonaktifkan user manual, tidak otomatis dari tanggal PKS.** THC yang memutuskan kapan akses benar-benar dicabut — tanggal berakhir PKS cuma catatan administratif. Nonaktifkan **tidak dicegah** oleh project aktif milik mitra tsb (mencegah adalah aturan kaku yang gampang jadi jalan buntu); project tetap bisa berjalan dan ditutup administratif oleh THC tanpa akses mitra. Data historis mitra (project, foto, linimasa) tetap ada dan tetap terlihat THC — pola "nonaktif bukan hapus" yang sudah baku di sistem ini.

## Konsekuensi

- Tabel baru: `pks` (nomor, `mitra_id`, tanggal mulai/berakhir, path file lampiran nullable), dengan constraint tepat satu PKS aktif per mitra (mis. unique partial index `WHERE tanggal_berakhir IS NULL`).
- `mitra_harga_jasas` dapat kolom `status` (`diajukan`/`disetujui`/`ditolak`), `pks_id` FK, dan `revisi_dari_id` self-reference nullable.
- Password teks polos lewat WA adalah trade-off keamanan yang disengaja untuk fase ini (~10 user, server belum HTTPS) — dicatat di sini supaya tidak "diperbaiki" tanpa sadar konteksnya.

## Alternatif yang ditolak

- **Link token WA satu-pakai untuk kredensial** — ditolak: butuh infra tambahan (halaman set-password publik, expiry token) untuk manfaat keamanan yang tidak proporsional di skala ~10 user internal yang sudah menerima trade-off serupa di tempat lain.
- **Multi-PKS aktif per mitra** — ditolak: tidak merefleksikan realita bisnis (satu kontrak berlaku pada satu waktu) dan memaksa UI dropdown pilih-PKS yang tidak perlu.
- **Nonaktifkan otomatis saat PKS berakhir** — ditolak: PKS bisa telat diperpanjang; automasi ini berisiko memutus akses mitra yang masih aktif bekerja.
