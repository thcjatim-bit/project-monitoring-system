# ADR 0008: Ruang Lingkup Laporan Gudang (MVP)

## Konteks

Setelah model data material, surat jalan, serta transit ditetapkan (ADR-0003 hingga ADR-0005), tim perlu mendefinisikan laporan gudang esensial yang harus dibangun di awal (Minimum Viable Product). Tujuannya agar waktu *development* tidak terbuang membuat analitik yang tidak terpakai, namun tetap menutup blind spot operasional gudang.

## Keputusan

Ruang lingkup Laporan Gudang pada fase awal difinalisasi menjadi 3 (tiga) bentuk laporan mandiri:

1. **Laporan Saldo Terkini**
   Menyajikan jumlah stok terkini dari setiap material. Laporan ini dapat difilter berdasarkan empat lokasi valid dari entitas material:
   - *Warehouse* (di dalam gudang).
   - *Project* (di lapangan, siap pasang).
   - *Terpasang* (sudah realisasi).
   - *Transit* (sedang dalam perjalanan antar gudang).

2. **Laporan Kartu Stok (Mutasi Barang)**
   Berfungsi sebagai alat audit harian, menampilkan histori transaksi riil (*in/out*) dari satu item material secara kronologis pada suatu rentang tanggal.

3. **Laporan Barang Transit**
   Laporan khusus untuk memonitor surat jalan perpindahan barang yang statusnya masih menggantung (status `terbit` tapi belum `diterima` di lokasi tujuan). Laporan ini sangat krusial untuk mencegah terjadinya barang hilang di tengah jalan tanpa diketahui penanggung jawabnya.

## Konsekuensi

- Semua laporan yang diakses oleh Mitra harus mematuhi aturan RLS; mereka hanya boleh melihat data saldo dan pergerakan dari gudang/project mereka sendiri.
- Laporan Barang Transit memaksa operasional untuk disiplin melakukan konfirmasi penerimaan surat jalan, yang mana ini adalah sifat yang kita kehendaki dalam sistem.
- Tidak ada laporan kompleks lain (seperti *forecasting* stok) pada rilis pertama.
