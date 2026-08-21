# ADR-0023 — Warehouse adalah tabel hibrida: dibaca lintas tenant, ditulis per tenant

**Status**: Diterima — 2026-08-21
**Menyempurnakan**: [ADR-0001](0001-isolasi-mitra-row-level-security.md), [ADR-0005](0005-alur-perpindahan-material-transit.md)

## Konteks

ADR-0001 membagi tabel menjadi dua: **tabel bertenant** (punya `mitra_id`, kena RLS) dan **tabel bersama** (Material, Unit, PoP, Pekerjaan Jasa — tanpa `mitra_id`, tanpa RLS). `warehouses` tidak muat di keduanya.

Warehouse punya `mitra_id` dan kena RLS seperti tabel bertenant, tapi kolomnya **nullable**: `mitra_id IS NULL` berarti gudang milik THC. ADR-0001 poin 1 mensyaratkan `mitra_id` **NOT NULL** pada tabel bertenant justru supaya kasus ini tidak ada — karena policy `tenant_isolation` membandingkan `mitra_id = current_setting('app.mitra_id')`, dan `NULL = <apa pun>` bernilai NULL, bukan true. Konsekuensinya: **gudang THC tidak terlihat sama sekali oleh user Mitra**.

Itu bertabrakan langsung dengan ADR-0005, yang memutuskan Surat Jalan dipakai untuk arah THC→mitra dan mewajibkan dokumen cetaknya memuat "gudang asal → gudang tujuan". Setiap Surat Jalan dari gudang THC ke gudang Mitra karena itu punya relasi `origin` yang ter-resolve menjadi `null` di konteks Mitra. Empat halaman men-dereference-nya tanpa pengaman dan membalas **500 Server Error**: `warehouse/index` (panel Pengiriman masuk), `warehouse/transfers`, `warehouse/transfers/{id}`, dan `warehouse/transfers/{id}/print` — sehingga Mitra tidak dapat menerima kiriman maupun mencetak surat jalan yang kertasnya sudah ada di tangan mereka.

Dua halaman lain — `warehouse/transit` dan Dashboard Mitra — sudah ditambal `?->` sebelumnya. Tambalan itu menghentikan error tapi menampilkan asal yang kosong, jadi ia menyembunyikan gejala tanpa menyentuh sebabnya.

## Keputusan

`warehouses` diakui sebagai **tabel hibrida**: tabel bertenant yang barisnya boleh ber-`mitra_id` NULL untuk menandai kepemilikan THC, **dapat dibaca lintas tenant, tetapi hanya dapat ditulis dalam tenantnya sendiri**.

1. Identitas gudang THC (`kode`, `nama`) **bukan rahasia terhadap Mitra**. Mitra menerima truknya dan memegang surat jalannya; sistem yang menyembunyikan asal barang sedang berbohong tentang dokumen yang sudah ada di tangan penggunanya.

2. Bacanya dibuka lewat **policy permissive kedua**, bukan dengan melonggarkan policy yang ada:

   ```sql
   CREATE POLICY warehouse_shared_read ON warehouses
     FOR SELECT
     USING (mitra_id IS NULL);
   ```

   `tenant_isolation` tidak disentuh. PostgreSQL meng-OR policy permissive, jadi pada `SELECT` Mitra melihat gudangnya sendiri **plus** gudang THC; pada `INSERT`, `UPDATE`, dan `DELETE` hanya `tenant_isolation` yang berlaku.

3. **Gudang milik Mitra lain tetap tidak terlihat.** Pelonggaran dibatasi tepat pada `mitra_id IS NULL`. ADR-0005 hanya mengenal arah THC↔mitra dan THC↔THC — tidak pernah mitra↔mitra — jadi tidak ada kebutuhan yang menuntut lebih dari ini.

4. Klausa `FOR SELECT` adalah inti keamanannya, bukan kerapian. `pms_app` memegang `DELETE` pada seluruh tabel (`2026_08_15_000004_grant_application_role_privileges`), dan `DELETE` di PostgreSQL **hanya** diperiksa terhadap `USING`, tidak pernah terhadap `WITH CHECK`. Melonggarkan `USING` pada policy `FOR ALL` — alternatif yang terlihat lebih sederhana — akan membuat satu query mentah `DELETE FROM warehouses WHERE mitra_id IS NULL` dari konteks Mitra menghapus seluruh gudang THC.

5. Perbaikan berhenti di lapisan database. Tidak ada blade yang diubah: begitu `origin` selalu ter-resolve, keempat halaman pulih sendiri. `?->` yang sudah terlanjur ada di `warehouse/transit` dan Dashboard Mitra **dibiarkan** sebagai jaring pengaman.

## Konsekuensi

- Query `Warehouse` dari konteks Mitra sekarang mengembalikan gudang THC juga. Kode yang menyusun daftar gudang **milik** Mitra harus menyebut `mitra_id` secara eksplisit — `MaterialInventoryController::destinationWarehouses()` sudah melakukannya, tapi ini berhenti menjadi otomatis dan menjadi tanggung jawab pemanggil.
- `warehouses` punya dua policy. Test kontrak RLS yang memetakan satu nama policy per tabel harus memetakan daftar nama.
- Kontrak "tabel bertenant `mitra_id` NOT NULL" pada ADR-0001 tetap berlaku untuk seluruh tabel lain. Tabel hibrida adalah pengecualian yang harus disebut namanya, bukan pola yang boleh ditiru diam-diam: tabel baru yang tergoda memakai `mitra_id` nullable untuk menandai kepemilikan THC wajib melewati ADR sendiri.
- `mitra_id IS NULL` kini mengemban dua arti yang tidak boleh tertukar: "milik THC" (di `warehouses`) dan "tidak terisi" (di kolom nullable lain). Perbedaannya hanya hidup di dokumen ini.

## Alternatif yang ditolak

- **Snapshot `kode`/`nama` gudang ke kolom `surat_jalans`** — ditolak: menduplikasi data yang alasan perubahannya tidak pernah datang dari Surat Jalan, dan hanya menolong Surat Jalan — halaman lain yang butuh objek `Warehouse` tetap buta.
- **Reklasifikasi `warehouses` menjadi tabel bersama** (matikan RLS, batasi tulis lewat izin menu) — ditolak: membuang penegakan isolasi di database untuk gudang milik Mitra, persis yang ADR-0001 ada untuk mencegah, dan membuat gudang Mitra lain terbaca.
- **Melonggarkan `USING` pada policy `tenant_isolation` yang ada** — ditolak karena lubang `DELETE` pada poin 4.
- **Placeholder generik "Gudang THC" di antarmuka** — ditolak: menghentikan 500 tanpa menjawab apa pun, dan bertentangan dengan isi surat jalan cetak yang dipegang Mitra.
