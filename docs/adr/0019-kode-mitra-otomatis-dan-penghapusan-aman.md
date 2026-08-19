# ADR-0019 — Kode Mitra otomatis dan penghapusan aman

**Status**: Diterima — 2026-08-19
**Konteks tiket**: [#79 CRUD User dan Mitra](https://github.com/thcjatim-bit/project-monitoring-system/issues/79)

## Konteks

Onboarding Mitra perlu menerima kode manual yang sudah dipakai sistem lama, tetapi entry baru juga harus mendapat pengenal yang konsisten. Kode tidak boleh bertabrakan ketika dua onboarding berjalan bersamaan dan nomor yang pernah diterbitkan tidak boleh dipakai ulang. User dan Mitra juga sudah menjadi rujukan banyak histori operasional, sehingga penghapusan tidak boleh menghilangkan jejak atau memutus referensi.

## Keputusan

1. Jika THC mengosongkan Kode Mitra saat onboarding, sistem menerbitkan `MTR-YYMM-NNNN`, dengan urutan empat digit yang dimulai dari `0001` pada setiap bulan kalender.
2. Nomor otomatis yang telah diterbitkan dicatat sebagai urutan permanen. Penghapusan entitas tidak mengembalikan nomor itu ke urutan; kode manual lama tetap valid selama unik.
3. Penerbitan kode otomatis diserialisasi dalam transaksi database dan dilindungi lock per bulan. Constraint unik pada `mitras.kode` tetap menjadi pengaman terakhir.
4. User hanya boleh dihapus bila tidak memiliki histori yang direferensikan, bukan sekadar karena sedang nonaktif. Jika aman, penghapusan hard delete boleh dilakukan; jika tidak aman, sistem menolak dengan alasan dan menawarkan Nonaktifkan.
5. User THC aktif terakhir dan User yang sedang login tidak boleh dihapus. Mitra yang masih memiliki referensi juga tidak boleh dihapus dan harus dinonaktifkan.

## Konsekuensi

- Halaman Mitra menjadi pemilik onboarding, edit, nonaktifkan, dan penghapusan Mitra; halaman User hanya mengelola User.
- ADR-0002 tetap berlaku untuk master-data sederhana; User/Mitra memakai form khusus karena onboarding adalah workflow agregat yang membuat Mitra dan admin-mitra sekaligus, dengan guard histori dan kredensial WA yang tidak dimiliki master-data biasa.
- Data historis tetap dapat dirujuk dan tidak perlu dipindahkan ke User pengganti.
- Perubahan Kode Mitra adalah tindakan eksplisit THC; generator otomatis hanya digunakan saat onboarding dengan kode kosong.

## Alternatif yang ditolak

- **Mengambil nomor terbesar dari baris Mitra saja** — ditolak karena nomor akan dipakai ulang setelah baris dihapus.
- **Menghapus User yang memiliki histori lalu mengandalkan `ON DELETE SET NULL`** — ditolak karena jejak aktor historis menjadi kurang dapat diaudit.
