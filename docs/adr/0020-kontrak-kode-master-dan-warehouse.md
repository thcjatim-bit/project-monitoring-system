# ADR-0020 — Kontrak Kode Master dan Warehouse

**Status**: Diterima — 2026-08-20
**Konteks tiket**: [#89 Tentukan kontrak generator Kode Master dan Warehouse](https://github.com/thcjatim-bit/project-monitoring-system/issues/89)

Material, Unit, PoP, Pekerjaan Jasa, dan Warehouse memerlukan pengenal yang konsisten tanpa merusak kode legacy. Dipilih format otomatis `PREFIX-YYMM-NNNN` dengan prefix berturut-turut `MAT`, `UNT`, `POP`, `JAS`, dan `WH`; sequence terpisah per entitas dan bulan bisnis `Asia/Jakarta`, dimulai dari `0001` dan berhenti setelah `9999`.

Kode manual legacy tetap diterima setelah `trim` dan uppercase, tetapi input manual yang menyamai pola otomatis ditolak. Kode otomatis yang berhasil diterbitkan dicatat permanen, immutable, dan tidak boleh dipakai ulang; generator melewati collision secara atomik dan bekerja dalam transaksi yang sama dengan pembuatan record. Data master yang sudah dipakai dinonaktifkan, bukan dihapus, dan ledger tetap dipertahankan bila penghapusan administratif diperlukan.
