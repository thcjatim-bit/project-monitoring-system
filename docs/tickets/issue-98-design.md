# Desain #98 - Tindak lanjut code review map QC #87

Status: direkomendasikan untuk implementasi
Tanggal: 2026-08-21
Issue: https://github.com/thcjatim-bit/project-monitoring-system/issues/98
Parent: https://github.com/thcjatim-bit/project-monitoring-system/issues/87

Bahasa domain pada dokumen ini mengikuti [CONTEXT.md](../../CONTEXT.md).
Kontrak Kode Master dan Warehouse mengikuti
[ADR-0020](../adr/0020-kontrak-kode-master-dan-warehouse.md), sedangkan fondasi
UI mengikuti keputusan issue #90.

## Ringkasan keputusan

Issue #98 tidak membutuhkan satu module besar untuk seluruh temuan. Gunakan tiga
seam yang sudah sesuai dengan perilaku yang berubah:

| Module | Seam | Keputusan |
| --- | --- | --- |
| `CodedMasterLifecycle` | mutasi Material, Unit, PoP, Pekerjaan Jasa, dan Warehouse | module baru dengan tiga operasi untuk memusatkan seluruh lifecycle kode |
| `ProjectPlanningService` | workflow Workspace Perencanaan Project | pertahankan external seam; jadikan publikasi Baseline sebagai internal seam |
| `x-ui.searchable-select` | pilihan searchable di Blade dan JavaScript | pertahankan interface existing, lengkapi interaksi dan migrasikan caller |

RAB freeze bukan module baru. Ini adalah invariant `addRabJasa()` yang harus
diperiksa di dalam transaksi setelah Project dikunci. Fondasi presentasi
`x-ui.*` juga tidak boleh mengambil alih authorization atau memilih data untuk
caller.

## Alternatif yang dipertimbangkan

### Desain A - interface minimal

Desain ini memberi `CodedMasterLifecycle` tiga entry point dan membuat satu
`BaselinePublisher::publish()` yang menerima sumber direct plan atau Usulan
Baseline.

Kelebihannya adalah depth tinggi pada publikasi: versioning, Original/Revised,
TOC, proposal, dan Linimasa Gabungan berada di belakang satu operasi. Kekurangannya,
`BaselinePublisher` menjadi external seam baru yang hanya dipakai oleh
`ProjectPlanningService`. Satu implementation dan satu caller membuat seam itu
hipotetis.

### Desain B - extensibility-first

Desain ini memakai typed command untuk setiap jenis master dan typed source
adapter untuk setiap sumber publikasi Baseline. Penambahan jenis master atau
sumber publikasi tidak mengubah method utama.

Kelebihannya adalah extension terkontrol. Kekurangannya adalah keluarga DTO dan
adapter lebih besar daripada variasi yang ada sekarang. Public registry atau
command bus juga akan memperluas interface tanpa leverage yang sebanding.

### Desain C - common-caller workflow

Desain ini memakai tiga operasi master dan dua operasi
`ProjectBaselineWorkflow::submit()`/`approve()`, sehingga controller menjadi
sangat tipis.

Kelebihannya adalah interface mengikuti dua route Baseline. Kekurangannya adalah
Project Planning terpecah menjadi dua external seam meskipun RAB Jasa, Baseline,
dan Variation Order berbagi Project lock, authorization, tenant facts, timeline,
dan test surface.

### Rekomendasi hybrid

Pilih bentuk master dari Desain A/C, tetapi letakkan bentuk publikasi dari
Desain A sebagai internal seam di dalam `ProjectPlanningService`. Ini memberi:

- leverage lintas lima jenis master dan dua controller;
- locality untuk seluruh aturan ADR-0020;
- satu external seam Project Planning yang tetap koheren;
- satu implementasi publikasi Baseline tanpa menambah public module;
- lock ordering yang sama untuk RAB freeze dan publikasi Baseline.

Deletion test mendukung keputusan ini. Jika `CodedMasterLifecycle` dihapus,
aturan kode kembali tersebar ke dua controller dan lima jenis record. Jika
sebuah external `BaselinePublisher` dihapus, kompleksitas cukup kembali ke
implementation `ProjectPlanningService`, bukan ke banyak caller. Karena itu
publikasi Baseline earns its keep sebagai internal seam, bukan external seam.

## Interface `CodedMasterLifecycle`

```php
enum MasterKind: string
{
    case Material = 'material';
    case Unit = 'unit';
    case Pop = 'pop';
    case PekerjaanJasa = 'pekerjaan_jasa';
    case Warehouse = 'warehouse';
}

final class CodedMasterLifecycle
{
    public function create(
        User $actor,
        MasterKind $kind,
        array $attributes,
    ): Model;

    public function update(
        User $actor,
        Model $record,
        array $attributes,
    ): Model;

    public function deactivate(
        User $actor,
        Model $record,
    ): void;
}
```

`update()` dan `deactivate()` menurunkan `MasterKind` dari record agar caller
tidak dapat memasangkan kind dan record yang berbeda. String route seperti
`units` dan `pekerjaan-jasa` diterjemahkan satu kali oleh HTTP adapter; string
tersebut bukan bahasa interface module.

Associative array dipertahankan karena input sekarang hanya datang dari form
yang sudah tervalidasi. Strict allow-list per kind berada di implementation.
Typed input baru layak ditambahkan jika muncul caller non-HTTP kedua atau field
antar-kind menjadi sulit dibedakan; membuat lima keluarga DTO sekarang akan
memperbesar interface lebih cepat daripada leverage-nya.

### Invariants

1. Actor wajib User THC dengan Izin Aksi existing; issue #98 tidak menambah
   permission.
2. Material, Unit, PoP, dan Pekerjaan Jasa tetap Tabel bersama dan tidak
   menerima `mitra_id`.
3. Warehouse boleh milik THC atau satu Mitra aktif. Kepemilikan Warehouse tidak
   disamakan dengan Penugasan Gudang.
4. Kode Manual dinormalisasi dengan `trim` dan uppercase, harus unik, dan tidak
   boleh menyamai pola Kode Otomatis.
5. Kode kosong pada create menerbitkan Kode Otomatis dengan prefix per entitas,
   bulan bisnis `Asia/Jakarta`, sequence per entitas/bulan, dan batas `9999`.
6. Kode Otomatis immutable setelah terbit dan tidak pernah digunakan kembali.
7. Record, sequence, dan Ledger Kode Terbit ditulis dalam satu transaksi.
8. `deactivate()` hanya mengubah `aktif = false`; module tidak menyediakan
   operasi delete.
9. Material tetap memvalidasi Unit aktif, jenis material, dan ambang minimum;
   Warehouse tetap memvalidasi Mitra aktif.
10. Unique constraint PostgreSQL adalah jaminan terakhir pada race condition.

### Ordering dan error modes

Create mengotorisasi actor, memetakan kind, memulai transaksi, memvalidasi
reference, menerbitkan atau memvalidasi kode, lalu menulis record. Update
mengunci record sebelum memeriksa immutability dan uniqueness.

- `403`: actor bukan THC atau tidak memiliki Izin Aksi existing.
- `404`: kind/record/reference tidak tersedia dalam scope.
- `ValidationException` pada `kode`: pola otomatis manual, duplicate, perubahan
  Kode Otomatis, atau sequence habis.
- `ValidationException` pada field terkait: Unit/Mitra tidak aktif atau input
  khusus kind tidak sah.

Database unique violation akibat concurrent write harus diterjemahkan ke error
`kode` yang sama, bukan membocorkan `QueryException`.

### Implementation yang disembunyikan

Implementation memiliki registry private untuk model, permission, code entity,
field allow-list, dan validasi reference setiap `MasterKind`. `MasterCodeGenerator`
menjadi collaborator internal. Controller tidak lagi mengurutkan normalize,
manual-pattern check, uniqueness check, transaction, generation, ledger, dan
persistence.

Jika model hook tetap dipertahankan untuk melindungi direct Eloquent write,
hook dan lifecycle harus memanggil policy internal yang sama. Menyalin ulang
kondisi ke hook akan mempertahankan duplikasi yang sedang dihapus.

PostgreSQL/Eloquent termasuk dependency local-substitutable. Tidak perlu
repository port: hanya ada satu adapter persistence yang nyata, dan SQLite tidak
memverifikasi advisory lock, RLS, atau perilaku PostgreSQL.

## External interface `ProjectPlanningService`

Pertahankan external seam existing agar caller dan test memakai workflow yang
sama:

```php
addRabJasa(Project $project, User $actor, int $hargaJasaId, numeric $qty)
savePlan(Project $project, User $actor, string $toc, array $planDays)
approveBaselineProposal(Project $project, ProjectBaselineProposal $proposal, User $actor)
createVariationOrder(Project $project, User $actor, string $reason, array $items)
approveVariationOrder(Project $project, ProjectVariationOrder $variation, User $actor)
currentRabQuantity(ProjectRabJasa $rab)
```

Issue #98 tidak perlu mengubah interface ini. Perubahan utamanya adalah
memperdalam implementation dengan satu internal publication path dan menambah
RAB freeze pada entry point existing.

### Internal seam publikasi Baseline

Gunakan satu private operation konseptual:

```php
private function publishBaselineLocked(
    Project $lockedProject,
    User $actor,
    NormalizedBaselinePlan $plan,
    BaselineProvenance $provenance,
): ProjectBaseline;
```

Ini bukan interface yang dipelajari controller. `savePlan()` untuk User THC dan
`approveBaselineProposal()` memanggil operation yang sama di dalam transaksi.
`BaselineProvenance` hanya membawa fakta sumber yang dibutuhkan untuk transisi
Usulan Baseline dan event Linimasa Gabungan; ia tidak boleh menentukan version,
kind, tenant, atau authorization.

Implementation tunggal wajib:

1. memakai Project yang sudah dikunci;
2. mengunci latest Baseline;
3. membuat Original Baseline v1 bila belum ada;
4. selain itu membuat Revised Baseline dengan version berikutnya dan
   `supersedes_id` ke Baseline sebelumnya;
5. menyalin hari yang sudah dinormalisasi sebagai snapshot append-only;
6. memperbarui TOC Project;
7. bila berasal dari Usulan Baseline, mengubah proposal `diajukan` menjadi
   `disetujui` beserta actor dan waktu keputusan;
8. mencatat event sumber yang tepat pada Linimasa Gabungan;
9. meng-commit seluruh perubahan secara atomik.

Original Baseline dan Revised Baseline lama tidak pernah diubah. `mitra_id`
selalu diturunkan dari locked Project, bukan payload atau proposal.

Plan validation tetap sama dengan kontrak existing: minimal satu titik, tanggal
valid, percent `0..100`, diurutkan berdasarkan tanggal, dan titik terakhir tepat
`100%`. Issue #98 tidak boleh diam-diam menambah aturan monotonic atau tanggal
unik tanpa keputusan domain tersendiri.

### RAB freeze dan concurrency

`addRabJasa()` adalah seam yang tepat untuk invariant freeze. Urutan wajib:

```text
authorize dan normalisasi qty
-> begin transaction
-> lock Project
-> periksa published Baseline
-> resolve Harga Jasa Mitra efektif
-> tulis snapshot RAB Jasa
-> commit
```

Jika published Baseline sudah ada, operasi gagal dengan `ValidationException`
pada `rab_jasa` dan mengarahkan perubahan melalui Variation Order.

Publikasi Baseline juga selalu mengunci Project lebih dulu. Dengan lock ordering
yang sama, direct RAB add dan publikasi Baseline pertama tidak dapat melewati
satu sama lain:

```text
lock Project -> check/write RAB
lock Project -> check/write Baseline
```

Usulan Baseline yang belum disahkan tidak membekukan RAB karena belum menjadi
sumber pengukuran. Check tidak boleh diletakkan pada model `ProjectRabJasa`:
approval Variation Order memang harus dapat membuat baris RAB baru setelah
freeze. Hanya Variation Order berstatus approved/applied yang mengubah RAB
efektif; draft tidak boleh mengubah quantity.

Harga Jasa Mitra tetap disnapshot ke `harga_jasa_mitra_id`, `harga_satuan`, dan
`total_nilai`. Freeze dan refactor publication tidak boleh mengubah snapshot
historis.

## UI seam

`x-ui.searchable-select` menerima options yang sudah dipilih dan diotorisasi
oleh server. Module Blade/JavaScript tidak mengambil data sendiri dan tidak
menentukan tenant scope.

Project create mengganti pasangan `x-ui.search` + native `<select>` dengan satu:

```blade
<x-ui.searchable-select
    name="mitra_id"
    id="mitra_id"
    :options="$mitraOptions"
    :value="old('mitra_id')"
    placeholder="Pilih Mitra aktif"
/>
```

`$mitraOptions` hanya berasal dari kumpulan Mitra aktif existing. Hidden value
tetap ID Mitra dan server-side `Rule::exists(...aktif=true)` tetap authoritative.

Interface JavaScript adalah `initializeSearchableSelects(document)`. Browser
DOM dan DOM test environment adalah dua adapter pada seam nyata. Private helper
seperti filtering, focus movement, dan state synchronization tidak perlu menjadi
interface tambahan hanya untuk test.

Regression test melalui interface wajib mencakup:

- trigger mouse membuka dan menutup popup;
- click option mengubah label, `aria-selected`, dan hidden form value;
- Enter/Space/Arrow membuka; ArrowUp/ArrowDown berpindah; Enter memilih;
- Escape menutup dan mengembalikan focus;
- clear dan form reset mengembalikan default value/label;
- outside click menutup;
- filter dan empty state;
- disabled state tidak dapat dibuka.

## Migrasi Workspace Perencanaan Project

Shared CSS sudah menyediakan `ui-page`, `ui-grid`, `ui-panel`, `ui-button`,
`ui-form`, `ui-table`, `ui-badge`, dan responsive behavior. Karena itu jangan
membuat Blade wrapper untuk setiap elemen; wrapper pass-through akan menjadi
module shallow.

Migrasikan `projects/planning.blade.php` untuk memakai:

- `x-ui.page` dan `x-ui.page-header`;
- `x-ui.panel`, `x-ui.badge`, dan `x-ui.empty-state`;
- shared `ui-button`, `ui-form`, `ui-grid`, dan `ui-table` contracts;
- `x-ui.searchable-select` untuk pilihan panjang Harga Jasa/RAB bila relevan;
- satu shared alert/status primitive hanya jika markup status/error memang
  dipakai ulang oleh caller lain.

Hapus inline `<style>` dan seluruh `project-workspace__*`. Pertahankan semua
conditional permission dan route existing.

Presentation menerima fakta server-side `rabFrozen`/`canAddInitialRab`. Form
Tambah RAB hanya muncul sebelum published Baseline. Setelah freeze, tampilkan
penjelasan bahwa perubahan dilakukan lewat Variation Order. Backend check tetap
authoritative untuk direct URL dan race condition.

## Test surface

### Kode Master dan Warehouse

Test melalui interface `CodedMasterLifecycle` untuk setiap `MasterKind`:

- penerbitan otomatis, timezone, sequence, collision, dan batas `9999`;
- normalisasi manual, penolakan pola otomatis, uniqueness, immutability;
- atomic rollback antara record dan Ledger Kode Terbit;
- concurrent issuance;
- Nonaktif, bukan hapus;
- exact Izin Aksi existing dan reference aktif.

Endpoint test cukup membuktikan request validation, pemetaan route ke kind,
authorization, dan response. Test lama yang menguji ulang implementation
controller diganti, bukan ditumpuk.

### Project Planning

Test melalui external interface `ProjectPlanningService` dan HTTP adapter:

- direct RAB add berhasil sebelum published Baseline;
- direct method dan POST gagal setelah Original atau Revised Baseline tanpa
  mutasi RAB/Timeline;
- Usulan Baseline pending belum membekukan RAB;
- publikasi direct THC dan approval Usulan Baseline menghasilkan sequencing
  Original/Revised yang identik;
- double approval ditolak;
- Project/proposal lintas tenant ditolak;
- concurrent direct add vs first publication serial dan tidak melanggar freeze;
- Variation Order approved tetap dapat menambah/mengurangi RAB;
- snapshot harga lama tidak berubah.

PostgreSQL integration/security tests di `pms-dev` wajib mencakup RLS, tenant
foreign keys, row locking, dan race-sensitive behavior. Test SQLite lokal tidak
menggantikan bukti tersebut.

## Urutan implementasi

1. Tambahkan failing regression test RAB freeze pada interface dan route.
2. Implementasikan Project lock + published Baseline check, lalu test Variation
   Order sebagai jalur sah setelah freeze.
3. Satukan publication path di implementation `ProjectPlanningService`.
4. Bentuk `CodedMasterLifecycle`, migrasikan kedua controller, lalu ganti test
   implementation lama dengan test pada interface baru.
5. Tambahkan DOM test adapter dan lengkapi seluruh interaksi searchable select.
6. Migrasikan Project create ke satu searchable select tanpa memperluas options.
7. Migrasikan Workspace Perencanaan Project ke fondasi UI bersama dan hapus CSS
   lokal.
8. Jalankan focused PHP/JavaScript tests dan build lokal.
9. Sinkronkan ke `pms-dev`; jalankan PostgreSQL integration/security tests dan
   full Laravel suite sampai hijau.
10. Jalankan code review ulang dan dokumentasikan bukti verifikasi sesuai issue
    #98.

## Batas scope

Desain ini tidak mengubah permission, capability Admin Mitra, RLS, tenant
ownership, aturan Harga Jasa Mitra, snapshot RAB, state Variation Order,
append-only Linimasa Gabungan/Ledger Kode Terbit, atau keputusan domain di luar
acceptance criteria #98.

Dokumen ini hanya menetapkan desain. Belum ada implementation atau runtime
verification yang dilakukan pada tahap ini.
