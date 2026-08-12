# ADR-0001 — Isolasi mitra ditegakkan lewat PostgreSQL Row-Level Security

**Status**: Diterima — 2026-08-12
**Konteks tiket**: [#3 Model data inti dan penegakan isolasi mitra](https://github.com/thcjatim-bit/project-monitoring-system/issues/3)

## Konteks

Mitra login ke sistem yang sama dengan THC. Satu query yang lupa `where mitra_id = ?` membocorkan data mitra lain — dan kebocoran itu tidak akan terlihat sampai ada yang mengeluh. Penyaringan di lapisan aplikasi (scope Eloquent, filter di controller) bisa dilewati oleh: query raw, join yang menyeret tabel anak, `Model::withoutGlobalScopes()`, job antrian, perintah artisan, dan kode yang ditulis enam bulan lagi oleh orang yang tidak ingat aturannya.

## Keputusan

Isolasi ditegakkan di **database**, dengan PostgreSQL Row-Level Security. Aplikasi hanya *menyetel identitas*; database yang memutuskan baris mana yang terlihat.

1. Setiap **tabel bertenant** punya kolom `mitra_id` **NOT NULL** — termasuk tabel anak (progres harian, foto, request material, item surat jalan). Tabel anak tidak mengandalkan join ke induk; `mitra_id` didenormalisasi ke sana supaya RLS bisa berdiri sendiri per tabel.

2. Aplikasi konek sebagai role PostgreSQL **`pms_app`** yang **bukan superuser dan tanpa `BYPASSRLS`**. Migrasi jalan sebagai role terpisah `pms_migrator`. Role read-only untuk integrasi juga tunduk RLS.

3. Setiap tabel bertenant kena:

   ```sql
   ALTER TABLE projects ENABLE ROW LEVEL SECURITY;
   ALTER TABLE projects FORCE ROW LEVEL SECURITY;

   CREATE POLICY tenant_isolation ON projects
     USING (
       current_setting('app.is_thc', true) = 'on'
       OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
     )
     WITH CHECK (
       current_setting('app.is_thc', true) = 'on'
       OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
     );
   ```

   `WITH CHECK` menutup sisi tulis: mitra tidak bisa menyisipkan atau memindahkan baris ke mitra lain.

4. **Default deny.** `current_setting(..., true)` mengembalikan `NULL` kalau variabel belum diset, dan `mitra_id = NULL` bernilai NULL — bukan true. Jadi konteks yang lupa diset melihat **nol baris**, bukan semua baris. Ini yang membuat mekanismenya tidak bisa "lupa diterapkan": lupa menyetel identitas berarti aplikasi mogok terang-terangan, bukan bocor diam-diam.

5. Identitas diset di satu tempat: listener `Illuminate\Database\Events\ConnectionEstablished` + middleware, memanggil
   `select set_config('app.mitra_id', ?, false)` dan `set_config('app.is_thc', ?, false)`
   dari user yang sedang login. Job antrian dan perintah artisan menyetelnya eksplisit di awal; kalau tidak, mereka melihat nol baris dan gagal keras.

6. **Uji regresi wajib**: satu test yang login sebagai mitra A lalu memanggil `DB::table('projects')->get()` mentah (tanpa model, tanpa scope) dan menuntut hasilnya nol untuk data mitra B. Test ini adalah alasan ADR ini ada — jangan dihapus.

7. Scope Eloquent per model tetap dipasang, tapi hanya sebagai **kenyamanan dan optimasi**, bukan sebagai pengaman. Kalau scope dan RLS tidak sepakat, RLS yang menang.

## Konsekuensi

- Query mitra otomatis terfilter di semua jalur, termasuk raw SQL dan integrasi read-only.
- `mitra_id` harus diisi saat insert; disediakan lewat trait model yang mengisinya dari konteks aktif.
- Butuh indeks komposit berawalan `mitra_id` di tabel bertenant agar policy tidak memaksa seq scan.
- Debugging via `psql` sebagai `pms_app` akan tampak "kosong" sampai `set_config` dijalankan. Ini disengaja; catat di runbook.
- Master data bersama (Material, Unit, PoP, Pekerjaan Jasa) **tidak** kena RLS — pembatasan tulisnya lewat hak akses menu.

## Alternatif yang ditolak

- **Global scope Eloquent saja** — ditolak: bisa dilewati raw query, `withoutGlobalScopes()`, dan job antrian; gagal secara diam-diam.
- **Satu database/schema per mitra** — ditolak: ~10 user aktif, satu server 4,8GB; biaya migrasi dan pelaporan lintas mitra untuk THC jadi mahal tanpa manfaat sepadan.
