# Riset: Semantik PostgreSQL 16 untuk RLS dan View BI

**Status**: Selesai — 2026-08-17  
**Branch riset**: `research/postgresql-bi-rls`  
**Konteks**: Riset ini mendukung [Wayfinder: Kesiapan REST API baca dan PostgreSQL BI Gelombang 3 (#54)](https://github.com/thcjatim-bit/project-monitoring-system/issues/54) dan menjawab [Riset semantik PostgreSQL RLS, view, dan konteks sesi untuk BI (#59)](https://github.com/thcjatim-bit/project-monitoring-system/issues/59).

Riset ini hanya membaca dokumentasi/source PostgreSQL 16 dan repository. Tidak ada kode aplikasi, migrasi, server, role, credential, atau konfigurasi jaringan yang dibuat atau diubah.

## Ringkasan keputusan

Untuk BI internal THC-only yang tetap tidak mendapat akses tabel mentah:

1. Gunakan dua role terpisah: role login BI (`pms_bi_reader`, tanpa `SUPERUSER`/`BYPASSRLS`) dan role owner view non-login (`pms_bi_view_owner`). Role owner boleh memiliki `SELECT` pada base table yang diperlukan view, tetapi tidak boleh menjadi role yang diberikan ke tool BI.
2. Gunakan view kurasi dengan `security_invoker = false` secara eksplisit. Ini mempertahankan semantik PostgreSQL bahwa privilege dan RLS base relation diperiksa sebagai owner view, sehingga `pms_bi_reader` tidak perlu diberi `SELECT` pada base table. `security_invoker = true` tidak cocok dengan kontrak “views only”: PostgreSQL mengharuskan pemanggil memiliki privilege pada view **dan** seluruh base relation yang dipakai.
3. Gunakan `security_barrier = true` pada view yang menyembunyikan baris melalui `WHERE` atau filter keamanan. Ini mencegah predicate/function dari query pemanggil menerima nilai dari baris yang seharusnya disaring sebelum filter view dijalankan; opsi ini bukan pengganti RLS, privilege, atau network boundary.
4. Tetap set `app.is_thc = 'on'` dan `app.mitra_id = ''` pada setiap koneksi BI, lalu verifikasi konteksnya. `ALTER ROLE ... SET` hanya memberi default saat login, bukan enforcement: custom GUC dua-bagian seperti `app.is_thc` adalah `PGC_USERSET` pada source PostgreSQL 16 dan dapat diubah oleh client. Karena itu GUC tidak boleh menjadi satu-satunya authorization boundary.
5. Authorization boundary yang sebenarnya adalah kombinasi `GRANT SELECT` eksplisit hanya pada view, schema `USAGE` tanpa `CREATE`, role tanpa `BYPASSRLS`, tidak ada membership ke role privileged, serta audit efektif `has_table_privilege` yang membuktikan `pms_bi_reader` tidak dapat `SELECT` atau privilege lain pada raw relation.

## Fakta repository

Fakta berikut berasal dari repository pada saat riset, bukan dari asumsi PostgreSQL:

- [ADR-0016](../adr/0016-rest-api-baca-dan-user-postgresql-read-only.md) memutuskan BI PostgreSQL hanya untuk internal THC, LAN-only, dan role read-only hanya mendapat `SELECT` pada view kurasi per domain; tabel mentah, `material_transaksis`, dan Komentar Internal tidak boleh di-grant ke role tersebut.
- [ADR-0001](../adr/0001-isolasi-mitra-row-level-security.md) memakai `current_setting('app.is_thc', true)` atau `current_setting('app.mitra_id', true)` dalam policy, mengaktifkan dan memaksa RLS pada tabel bertenant, serta mengharapkan konteks yang tidak diset menghasilkan default deny.
- [`TenantDatabaseContext`](../../app/Support/TenantDatabaseContext.php) saat ini memanggil `set_config` untuk kedua GUC dengan `is_local = false`, sehingga nilainya berlaku sepanjang sesi koneksi PostgreSQL.
- Tidak ada implementasi view BI/role BI pada source yang ditinjau untuk tiket ini. Pemetaan base relation, daftar kolom yang boleh keluar, dan operasi deployment tetap menjadi pekerjaan implementasi/operasi setelah decision ticket ini.

## Semantik PostgreSQL 16 yang relevan

### 1. RLS, default deny, owner, dan BYPASSRLS

Privilege SQL dan RLS adalah lapisan berbeda. Tabel yang mengaktifkan RLS tetap memerlukan privilege SQL; setelah privilege ada, setiap akses normal harus lolos policy. Jika tidak ada policy yang berlaku, PostgreSQL memakai default-deny sehingga tidak ada baris yang terlihat atau dapat dimodifikasi. Policy `USING` yang menghasilkan `false` **atau `NULL`** juga tidak membuat baris terlihat. [PostgreSQL 16 — Row Security Policies](https://www.postgresql.org/docs/16/ddl-rowsecurity.html) dan [PostgreSQL 16 — CREATE POLICY](https://www.postgresql.org/docs/16/sql-createpolicy.html) mendokumentasikan urutan ini.

Superuser dan role dengan atribut `BYPASSRLS` selalu melewati RLS. Table owner biasanya juga melewati RLS, kecuali tabel diberi `FORCE ROW LEVEL SECURITY`. Karena itu:

- `pms_bi_reader` dan `pms_bi_view_owner` harus `NOSUPERUSER NOBYPASSRLS`.
- `pms_bi_view_owner` tidak boleh menjadi owner base table. Semua tabel bertenant tetap perlu pola `ENABLE ROW LEVEL SECURITY` + `FORCE ROW LEVEL SECURITY` dari ADR-0001.
- Tidak adanya konteks GUC hanya memberi default deny pada tabel yang memang memiliki RLS policy. View tidak boleh memasukkan tabel sensitif atau tabel bertenant yang belum diaudit RLS dengan asumsi bahwa GUC akan menyelamatkannya.

### 2. `security_invoker` dan ownership view

Secara default, privilege untuk base relation yang direferensikan view diperiksa terhadap view owner. RLS pada base relation juga memakai policy dan privilege view owner. Dengan `security_invoker = true`, privilege dan policy base relation diperiksa terhadap user yang menjalankan query; user tersebut harus punya privilege yang relevan pada view **dan** base relation. [CREATE VIEW PostgreSQL 16](https://www.postgresql.org/docs/16/sql-createview.html#SQL-CREATEVIEW-NOTES) menyatakan kedua semantik tersebut secara eksplisit. Source PostgreSQL 16 menerapkannya saat rewrite query dengan memilih `checkAsUser` sebagai caller untuk security-invoker atau `relowner` untuk view biasa: [rewriteHandler.c, REL_16_STABLE](https://github.com/postgres/postgres/blob/REL_16_STABLE/src/backend/rewrite/rewriteHandler.c#L3177-L3202).

Implikasi untuk kontrak repository:

- `security_invoker = true` berarti `pms_bi_reader` harus diberi `SELECT` pada setiap base table yang dipakai `v_projects`, `v_stok`, dan seterusnya. Itu membuka permukaan raw-table dan bertentangan dengan ADR-0016.
- `security_invoker = false` bukan `SECURITY DEFINER` function. Fungsi yang dipanggil di dalam view tetap mengikuti atribut function-nya, dan user dapat memerlukan `EXECUTE`; jangan menaruh fungsi arbitrary atau `SECURITY DEFINER` yang belum diaudit di view.
- Owner view memiliki seluruh privilege object dan kemampuan mengubah/menghapus object tersebut. Owner harus role non-login yang hanya dapat diasumsikan oleh migrator/deployer yang berwenang, bukan role credential BI.
- View yang sederhana dapat otomatis updatable. “Read-only” harus ditegakkan dengan hanya memberi `SELECT` pada view dan tidak memberi `INSERT`, `UPDATE`, `DELETE`, `TRUNCATE`, atau `REFERENCES`; jangan mengandalkan bentuk query view saja. [CREATE VIEW — Updatable Views](https://www.postgresql.org/docs/16/sql-createview.html#SQL-CREATEVIEW-UPDATABLE-VIEWS) dan [GRANT](https://www.postgresql.org/docs/16/sql-grant.html) mendukung batas ini.

### 3. `security_barrier`

`security_barrier` mengatur urutan evaluasi untuk view yang menjadi batas keamanan: kondisi view dievaluasi sebelum kondisi yang ditambahkan pemanggil, kecuali fleksibilitas untuk operator/function yang dipercaya leakproof. PostgreSQL merekomendasikannya bila view dimaksudkan memberi row-level security. [Rules and Privileges PostgreSQL 16](https://www.postgresql.org/docs/16/rules-privileges.html) menjelaskan bahwa opsi ini mengurangi risiko function/operator pilihan pemanggil menerima nilai dari tuple yang tak boleh terlihat.

Gunakan:

```sql
CREATE VIEW bi.v_projects
WITH (security_barrier = true, security_invoker = false)
AS
SELECT ...;
```

Ini defense-in-depth, bukan bukti bahwa data aman dengan sendirinya. Dokumentasi juga memperingatkan kemungkinan biaya performa dan covert channel seperti `EXPLAIN` atau timing. View tetap harus memproyeksikan kolom yang diizinkan, memakai RLS pada base tenant table, dan hanya di-grant kepada role yang tepat.

### 4. Session GUC dan default deny

`current_setting(name, true)` mengembalikan `NULL` bila setting tidak ada; tanpa argumen `missing_ok = true`, setting yang tidak ada menghasilkan error. `set_config(name, value, true)` berlaku hanya pada transaksi berjalan, sedangkan `false` berlaku untuk sisa sesi. [System Administration Functions PostgreSQL 16](https://www.postgresql.org/docs/16/functions-admin.html#FUNCTIONS-ADMIN-SET) dan [SET](https://www.postgresql.org/docs/16/sql-set.html) adalah sumber resmi untuk perilaku ini.

Dengan policy repository:

```sql
current_setting('app.is_thc', true) = 'on'
OR mitra_id = nullif(current_setting('app.mitra_id', true), '')::bigint
```

konteks yang tidak ada membuat bagian pertama `NULL = 'on'` dan bagian kedua membandingkan `mitra_id` dengan `NULL`; keduanya tidak `TRUE`, sehingga baris tidak lolos. Untuk kesalahan format `app.mitra_id`, cast dapat menghasilkan error; ini lebih aman daripada diam-diam menganggap semua baris boleh, tetapi koneksi harus dianggap gagal dan dibuang.

`ALTER ROLE ... SET` dapat menyimpan default per-role/per-database yang diterapkan ketika sesi baru login. Dokumentasi PostgreSQL 16 menekankan bahwa setting tersebut diproses saat login dan dapat ditimpa oleh `SET` pada sesi. [ALTER ROLE](https://www.postgresql.org/docs/16/sql-alterrole.html#SQL-ALTERROLE-CONFIG) bukan mekanisme yang mengunci nilai GUC.

Custom option dua bagian diterima PostgreSQL sebagai placeholder. Pada source PostgreSQL 16, placeholder custom GUC diberi context `PGC_USERSET`, dan jalur `PGC_USERSET` menyatakan setting selalu boleh; lihat [guc.c, custom placeholder](https://github.com/postgres/postgres/blob/REL_16_STABLE/src/backend/utils/misc/guc.c#L1031-L1055) dan [guc.c, permission context](https://github.com/postgres/postgres/blob/REL_16_STABLE/src/backend/utils/misc/guc.c#L3281-L3304). Jadi `GRANT SET ON PARAMETER app.is_thc`/`REVOKE SET` tidak boleh dianggap sebagai pengunci authorization untuk GUC custom ini.

### 5. Ownership dan privilege object

`GRANT` ke `PUBLIC` berlaku untuk semua role, termasuk role yang dibuat kemudian. `GRANT ALL TABLES IN SCHEMA` juga mencakup view, sehingga tidak boleh digunakan sebagai shortcut pada schema BI. PostgreSQL mendokumentasikan bahwa owner memiliki privilege penuh secara default dan bahwa `USAGE` pada schema hanya memberi kemampuan lookup object; privilege object tetap harus dipenuhi. [GRANT PostgreSQL 16](https://www.postgresql.org/docs/16/sql-grant.html) dan [Privileges](https://www.postgresql.org/docs/16/ddl-priv.html) menjadi acuan.

Batas minimum schema BI:

```sql
REVOKE CREATE ON SCHEMA bi FROM PUBLIC;
GRANT USAGE ON SCHEMA bi TO pms_bi_reader;
REVOKE ALL ON bi.v_projects FROM pms_bi_reader;
GRANT SELECT ON bi.v_projects TO pms_bi_reader;
```

Daftar view harus eksplisit per domain. `REVOKE`/`GRANT` tersebut adalah contoh kontrak, bukan perintah yang dijalankan oleh tiket riset.

## Pola yang direkomendasikan untuk BI internal THC-only

### Role dan ownership

Gunakan pemisahan berikut (nama hanya rekomendasi; password tidak pernah ditulis di source/issue):

| Role | Login | Bypass RLS | Fungsi | Raw-table privilege |
| --- | --- | --- | --- | --- |
| `pms_bi_reader` | Ya, credential hanya di channel secret yang disetujui | Tidak | Satu-satunya identity yang dipakai tool BI | Tidak |
| `pms_bi_view_owner` | Tidak | Tidak | Owner view, menjalankan rewrite view dengan privilege terkontrol | `SELECT` minimum pada base relation yang diperlukan view |
| `pms_migrator` | Sesuai operasi migrasi | Tidak untuk akses aplikasi | Membuat/mengubah schema, view, policy, dan grant | Sesuai tugas migrasi; bukan credential BI |

`pms_bi_reader` tidak boleh menjadi member `pms_app`, `pms_migrator`, `pms_bi_view_owner`, role owner base table, atau role lain yang memiliki raw-table privilege. Role reader juga tidak boleh `SUPERUSER`, `BYPASSRLS`, `CREATEROLE`, atau `CREATEDB`. View owner harus `NOLOGIN` dan membership untuk mengasumsikannya dibatasi kepada migrator/deployer.

Catatan boundary: ordinary owner-executed view memang membutuhkan suatu owner yang punya privilege pada base relation. Jadi klaim “tidak ada raw-table grant” di sini berlaku pada role yang dipakai tool BI (`pms_bi_reader`), bukan berarti tidak ada role internal mana pun yang boleh memiliki privilege tersebut. Jika destination kelak menafsirkan larangan itu sebagai larangan absolut untuk semua role selain migrator, ordinary view tidak cukup; perlu keputusan terpisah tentang materialized/sanitized BI schema atau mekanisme `SECURITY DEFINER` yang diaudit.

### View dan policy

Untuk setiap view kurasi:

1. Schema-qualify seluruh base relation dan function yang dipakai.
2. Pilih kolom secara eksplisit; jangan `SELECT *`.
3. Gunakan `WITH (security_barrier = true, security_invoker = false)` bila view menyembunyikan baris atau menjadi batas keamanan.
4. Beri owner kepada `pms_bi_view_owner`, bukan `pms_bi_reader`.
5. Beri `SELECT` base relation minimum hanya kepada `pms_bi_view_owner`; ini adalah privilege internal untuk menjalankan view, bukan akses yang diberikan kepada konsumen BI.
6. Beri `SELECT` hanya pada view yang sudah melalui matriks domain dan pengecualian data sensitif. Komentar Internal, password hash, binary foto, lampiran PKS mentah, dan raw material ledger tetap tidak masuk projection.
7. Pastikan base tenant relation memiliki `ENABLE/FORCE ROW LEVEL SECURITY`, policy applicable untuk owner view, dan tidak ada owner/BYPASSRLS yang melewati policy.

### Setup koneksi dan konteks

Default operasional yang aman untuk koneksi `pms_bi_reader`:

```sql
-- Session default saja; bukan authorization boundary.
ALTER ROLE pms_bi_reader IN DATABASE <pms_database>
  SET app.is_thc = 'on';
ALTER ROLE pms_bi_reader IN DATABASE <pms_database>
  SET app.mitra_id = '';
```

Pada setiap checkout/establish koneksi, client yang dipercaya tetap menginisialisasi dan memverifikasi ulang:

```sql
SELECT
  set_config('app.is_thc', 'on', false),
  set_config('app.mitra_id', '', false);

SELECT
  current_user,
  session_user,
  current_setting('app.is_thc', true) = 'on' AS thc_context,
  NULLIF(current_setting('app.mitra_id', true), '') IS NULL AS no_mitra_context;
```

Koneksi harus ditolak/dibuang bila `thc_context IS NOT TRUE`, `no_mitra_context IS NOT TRUE`, atau identity bukan `pms_bi_reader`. Jangan pernah menggunakan nilai GUC dari pool sebelumnya tanpa overwrite. Untuk aplikasi yang memakai koneksi pooled dan konteks per-request, gunakan `set_config(..., true)`/`SET LOCAL` di dalam transaction yang mencakup seluruh query request, atau reset kedua GUC sebelum koneksi dikembalikan ke pool. `false` seperti implementasi `TenantDatabaseContext` saat ini hanya aman bila setiap pemakai koneksi selalu menimpa kedua nilai sebelum query.

Karena `app.is_thc` dapat diubah oleh client, pola ini menganggap siapa pun yang berhasil login sebagai `pms_bi_reader` memang sudah memiliki scope THC-only. GUC hanya menjadi konteks eksplisit dan fail-closed gate; ia bukan token privilege. Jika kelak BI scoped-per-Mitra dibuka, gunakan identity/role atau jalur koneksi berbeda dan audit policy baru—jangan mengandalkan client bebas mengubah `app.mitra_id`.

## Pembuktian tidak ada raw-table grant

Audit harus menguji **effective privilege**, bukan hanya ACL yang tampak sebagai grant langsung. `has_table_privilege` dapat menerima nama/OID user dan table, memperhitungkan privilege efektif, membership role, serta pseudo-role `PUBLIC`; [System Information Functions PostgreSQL 16](https://www.postgresql.org/docs/16/functions-info.html#FUNCTIONS-INFO-ACCESS-TABLE) mendokumentasikan fungsi ini. `information_schema.table_privileges` berguna sebagai laporan grant yang terlihat oleh role yang sedang enabled, tetapi bukan satu-satunya bukti; [table_privileges](https://www.postgresql.org/docs/16/infoschema-table-privileges.html) menjelaskan cakupannya. Jalankan query laporan itu sebagai `pms_bi_reader` (atau setelah `SET ROLE pms_bi_reader`) agar filter information schema tidak disalahartikan sebagai audit global.

Setelah schema/view benar-benar ada, jalankan audit read-only sebagai administrator database yang berwenang:

```sql
-- Harus mengembalikan nol baris.
WITH raw_relations AS (
  SELECT c.oid, n.nspname, c.relname, c.relkind
  FROM pg_class AS c
  JOIN pg_namespace AS n ON n.oid = c.relnamespace
  WHERE c.relkind IN ('r', 'p', 'f') -- table, partitioned table, foreign table
    AND n.nspname NOT IN ('pg_catalog', 'information_schema')
)
SELECT nspname, relname, relkind,
       has_table_privilege('pms_bi_reader', oid, 'SELECT') AS can_select,
       has_table_privilege('pms_bi_reader', oid, 'INSERT') AS can_insert,
       has_table_privilege('pms_bi_reader', oid, 'UPDATE') AS can_update,
       has_table_privilege('pms_bi_reader', oid, 'DELETE') AS can_delete,
       has_table_privilege('pms_bi_reader', oid, 'TRUNCATE') AS can_truncate,
       has_table_privilege('pms_bi_reader', oid, 'REFERENCES') AS can_reference,
       has_table_privilege('pms_bi_reader', oid, 'TRIGGER') AS can_trigger
FROM raw_relations
WHERE has_table_privilege(
        'pms_bi_reader', oid,
        'SELECT, INSERT, UPDATE, DELETE, TRUNCATE, REFERENCES, TRIGGER'
      )
ORDER BY nspname, relname;
```

`raw_relations` harus dibatasi lagi dengan daftar schema aplikasi yang benar pada implementasi; jangan menganggap exclusion `pg_catalog`/`information_schema` cukup sebagai domain policy. Untuk membuktikan sisi positif (akses hanya ke curated view), audit juga:

```sql
SELECT table_schema, table_name, privilege_type, is_grantable
FROM information_schema.table_privileges
WHERE grantee = 'pms_bi_reader'
ORDER BY table_schema, table_name, privilege_type;
```

Expected result: hanya `SELECT` pada allowlist view BI (dan privilege `USAGE` schema/database yang memang dibutuhkan), tidak ada raw relation. Tambahkan pemeriksaan role dan membership:

```sql
SELECT rolname, rolsuper, rolbypassrls, rolcreatedb,
       rolcreaterole, rolcanlogin, rolinherit
FROM pg_roles
WHERE rolname IN ('pms_bi_reader', 'pms_bi_view_owner');

SELECT member.rolname AS member_role,
       granted.rolname AS granted_role,
       m.inherit_option,
       m.set_option,
       m.admin_option
FROM pg_auth_members AS m
JOIN pg_roles AS member ON member.oid = m.member
JOIN pg_roles AS granted ON granted.oid = m.roleid
WHERE member.rolname = 'pms_bi_reader';
```

Terakhir, buat negative smoke test dari koneksi login `pms_bi_reader` (tanpa menampilkan credential):

- `SELECT` pada setiap raw table allowlist harus gagal dengan permission denied, bukan sekadar mengembalikan nol baris karena RLS.
- `SELECT` pada setiap curated view allowlist harus berhasil.
- `INSERT`, `UPDATE`, `DELETE`, `TRUNCATE`, dan `REFERENCES` pada curated view/raw table harus gagal.
- `SET app.is_thc = 'off'` atau mengosongkan konteks harus menghasilkan nol baris pada base tenant relation melalui view; mengubah GUC menjadi `'on'` tidak boleh memberi kemampuan membaca raw table karena privilege reader tetap tidak ada.
- `EXPLAIN`/timing bukan bukti tidak ada kebocoran; `security_barrier` hanya mengurangi kelas leakage yang dijelaskan dokumentasi PostgreSQL.

## Acceptance checklist handoff

- [ ] Matriks base relation → curated view → kolom output selesai dan mengecualikan data sensitif ADR-0016.
- [ ] Owner view non-login, `NOBYPASSRLS`, bukan owner base table, dan tidak dapat diasumsikan oleh `pms_bi_reader`.
- [ ] `security_invoker = false` eksplisit; `security_barrier = true` untuk view yang menjadi row-security boundary.
- [ ] Semua tenant base table yang dipakai view telah diaudit `ENABLE/FORCE RLS` dan policy-nya fail-closed tanpa konteks.
- [ ] `pms_bi_reader` hanya menerima `USAGE` schema dan `SELECT` eksplisit pada allowlist view; tidak ada `GRANT ALL TABLES`.
- [ ] Audit `has_table_privilege` menghasilkan nol raw relation dengan privilege efektif apa pun.
- [ ] Audit role/membership menghasilkan `NOSUPERUSER`, `NOBYPASSRLS`, tidak ada membership privileged, dan tidak ada kemampuan membuat object/role yang mengubah boundary.
- [ ] Koneksi menginisialisasi serta memverifikasi `app.is_thc`/`app.mitra_id` pada checkout; test pool reuse tidak membawa konteks lama.
- [ ] Negative smoke test raw table menghasilkan permission denied; positive smoke test hanya view allowlist.
- [ ] Verifikasi PostgreSQL/integrasi dilakukan di `pms-dev` sebelum exact-SHA production workflow. Ticket ini sendiri tidak melakukan perubahan server atau database.

## Sumber primer

- [PostgreSQL 16 — Row Security Policies](https://www.postgresql.org/docs/16/ddl-rowsecurity.html)
- [PostgreSQL 16 — CREATE POLICY](https://www.postgresql.org/docs/16/sql-createpolicy.html)
- [PostgreSQL 16 — CREATE VIEW](https://www.postgresql.org/docs/16/sql-createview.html)
- [PostgreSQL 16 — Rules and Privileges](https://www.postgresql.org/docs/16/rules-privileges.html)
- [PostgreSQL 16 — GRANT](https://www.postgresql.org/docs/16/sql-grant.html)
- [PostgreSQL 16 — Privileges](https://www.postgresql.org/docs/16/ddl-priv.html)
- [PostgreSQL 16 — ALTER ROLE](https://www.postgresql.org/docs/16/sql-alterrole.html)
- [PostgreSQL 16 — SET](https://www.postgresql.org/docs/16/sql-set.html)
- [PostgreSQL 16 — System Administration Functions](https://www.postgresql.org/docs/16/functions-admin.html)
- [PostgreSQL 16 — System Information Functions](https://www.postgresql.org/docs/16/functions-info.html)
- [PostgreSQL 16 — `table_privileges`](https://www.postgresql.org/docs/16/infoschema-table-privileges.html)
- [PostgreSQL 16 — Customized Options](https://www.postgresql.org/docs/16/runtime-config-custom.html)
- [PostgreSQL source, `guc.c`, `REL_16_STABLE`](https://github.com/postgres/postgres/blob/REL_16_STABLE/src/backend/utils/misc/guc.c)
- [PostgreSQL source, `rewriteHandler.c`, `REL_16_STABLE`](https://github.com/postgres/postgres/blob/REL_16_STABLE/src/backend/rewrite/rewriteHandler.c)

## Repository references

- [ADR-0001 — Isolasi Mitra Row-Level Security](../adr/0001-isolasi-mitra-row-level-security.md)
- [ADR-0016 — REST API baca dan user PostgreSQL read-only](../adr/0016-rest-api-baca-dan-user-postgresql-read-only.md)
- [`TenantDatabaseContext`](../../app/Support/TenantDatabaseContext.php)
