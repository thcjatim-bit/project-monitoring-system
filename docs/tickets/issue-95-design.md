# Desain #95 - Workspace Admin Mitra

Status: diimplementasikan pada `a43803a9ba5f2589fcf077d694d57e7190e39b79`
Tanggal: 2026-08-21
Issue: https://github.com/thcjatim-bit/project-monitoring-system/issues/95
Parent: https://github.com/thcjatim-bit/project-monitoring-system/issues/87
Keputusan capability: [ADR-0021](../adr/0021-capability-admin-mitra.md)

Bahasa domain yang dipakai di dokumen ini mengikuti [CONTEXT.md](../../CONTEXT.md).
Khususnya, Workspace Admin Mitra adalah kumpulan jalur kerja, Dashboard Mitra
adalah read-only, dan Penugasan Gudang bukan perubahan kepemilikan Warehouse.

## Ringkasan

Issue #95 adalah delivery umbrella yang membuka Workspace Admin Mitra setelah
capability pada issue #94 disetujui. Scope-nya mencakup Dashboard Mitra,
Warehouse, User Mitra, Workspace Perencanaan Project, dan Harga Jasa Mitra.

Desain yang dipilih tidak membuat satu module `AdminMitraWorkspace` besar.
Scope ini terdiri dari beberapa module dengan aturan domain yang berbeda.
Setiap module memiliki interface kecil, implementation yang dalam, dan seam
yang dapat diuji melalui interface tersebut.

## Keputusan desain

Gunakan empat module utama:

| Module domain | Seam | Tanggung jawab yang disembunyikan |
| --- | --- | --- |
| User Mitra | Operasi User Mitra | tenant scope, Grup yang boleh dipilih, nonaktif bukan hapus, perlindungan diri sendiri dan Admin Mitra terakhir, reset credential |
| Penugasan Gudang | Penugasan User Mitra ke Warehouse | ownership Mitra, status aktif, validasi User aktif, pivot assignment, direct URL |
| Harga Jasa Mitra | Pengelolaan Harga Jasa Mitra | PKS aktif, revisi append-only, status pengajuan, approval THC, tanggal berlaku, resolusi harga efektif |
| Workspace Perencanaan Project | RAB Jasa, Usulan Baseline, dan Variation Order | authorization Project, snapshot harga, transaksi atomik, Original Baseline/Revised Baseline, approval Variation Order |

Dashboard Mitra tetap memakai module query untuk read model-nya. Dashboard
Mitra tidak menjadi jalur mutasi ke module pemilik data.

`ProjectPlanningService` adalah identifier teknis existing untuk module terdalam
dalam desain saat ini dan dipertahankan sebagai seam utama Workspace
Perencanaan Project.

## Interface yang diimplementasikan

Kontrak berikut menunjukkan seam operasional pada source final. Identifier
teknis existing ditulis sebagai referensi source; istilah domain pada interface
dan UI tetap mengikuti `CONTEXT.md`.

### User Mitra

```php
rosterFor(User $actor): array
create(User $actor, array $data): User
update(User $actor, User $target, array $data): void
toggle(User $actor, User $target): void
resetCredentials(User $actor, User $target): void
```

Implementation wajib mengambil `mitra_id` dari actor. `mitra_id` dan status
Admin Mitra tidak boleh menjadi kewenangan payload User Mitra.

Reset credential memakai dependency `WahaClient` yang di-inject. Module tidak
membuat client sendiri sehingga external adapter dan test adapter dapat
diganti pada seam internalnya.

### Penugasan Gudang

```php
assignmentsFor(User $actor): array
assign(User $actor, Warehouse $warehouse, User $target): void
unassign(User $actor, Warehouse $warehouse, User $target): void
```

Warehouse dan User harus berada pada Mitra actor, keduanya harus memenuhi
status aktif yang diwajibkan, dan operasi assignment harus aman terhadap
duplicate submit.

### Harga Jasa Mitra

```php
priceBookFor(User $actor): Collection
catalogFor(User $actor): array
submit(User $actor, array $data): MitraHargaJasa
decide(User $actor, MitraHargaJasa $price, string $decision): MitraHargaJasa
effectiveFor(Project $project, int $priceId, DateTimeInterface $at): MitraHargaJasa
```

`decide` hanya dapat dijalankan User THC dengan permission approval. `submit`
selalu membuat baris baru berstatus `diajukan`; perubahan harga yang sudah ada
tidak dilakukan in-place.

`effectiveFor` hanya mengembalikan harga yang:

- memiliki Mitra yang sama dengan Project;
- berasal dari Pekerjaan Jasa aktif;
- berstatus `disetujui`;
- sudah berlaku pada tanggal yang diminta;
- terikat pada PKS yang sesuai.

### Workspace Perencanaan Project

Interface existing pada [ProjectPlanningService.php](../../app/Services/ProjectPlanningService.php)
dipertahankan:

```php
addRabJasa(Project $project, User $actor, int $hargaJasaId, string|int|float $qty)
savePlan(Project $project, User $actor, string $toc, array $planDays)
approveBaselineProposal(Project $project, ProjectBaselineProposal $proposal, User $actor)
createVariationOrder(Project $project, User $actor, string $reason, array $items)
approveVariationOrder(Project $project, ProjectVariationOrder $variation, User $actor)
```

Method-method ini membentuk satu interface workflow yang dalam. Controller
tidak boleh mengambil harga lalu mengisi `harga_satuan` sendiri. Untuk Admin
Mitra, `savePlan` membuat Usulan Baseline; `approveBaselineProposal` kemudian
mengesahkannya sebagai Baseline final melalui keputusan THC. Untuk User THC,
`savePlan` dapat menerbitkan Baseline secara langsung sesuai permission. Nama
method existing tidak memberi Admin Mitra kewenangan menetapkan Baseline final.

### Baseline dan Variation Order sebagai usulan

Admin Mitra boleh mengirim Usulan Baseline dan membuat Variation Order untuk
Project milik Mitranya. Baseline final dan persetujuan Variation Order tetap
merupakan keputusan THC. Original Baseline yang sudah disahkan tidak pernah
ditimpa; revisi yang disahkan menjadi Revised Baseline.

## Invariants authorization dan isolasi

Semua Adapter HTTP/middleware boleh menyembunyikan menu atau menolak rute,
tetapi module tetap melakukan pemeriksaan ulang pada saat operasi dijalankan.

Aturan yang tidak boleh dilewati:

1. Permission menentukan capability aplikasi.
2. Ownership menentukan tenant scope.
3. RLS PostgreSQL tetap menjadi perlindungan data terakhir.
4. `mitra_id` tidak pernah dipercaya dari input User Mitra.
5. Direct URL, manipulasi ID, dan request manual harus menghasilkan 403/404
   tanpa perubahan data.
6. Permission THC-only tidak boleh didelegasikan kepada User Mitra.
7. Usulan Baseline tidak boleh berubah menjadi Baseline final tanpa keputusan
   THC yang dapat diaudit.

Daftar permission User Mitra sebaiknya memakai allow-list capability yang boleh
didelegasikan, bukan deny-list `THC_ONLY_PERMISSIONS`. Deny-list dapat membuka
privilege baru secara tidak sengaja ketika permission sensitif baru ditambah
tetapi lupa dimasukkan ke daftar pengecualian.

Capability yang tetap THC-only meliputi approval Harga Jasa Mitra, verifikasi
progress, approval Pemakaian/Rekon Material, baseline final sesuai keputusan
domain, penutupan administratif Project, serta perubahan identitas atau
kepemilikan Project/Warehouse.

## Snapshot dan state Harga Jasa Mitra

Alur harga yang canonical adalah:

```text
Master Pekerjaan Jasa bersama
        |
        v
Harga Jasa Mitra berstatus diajukan
        |
        v
Approval THC
        |
        v
Harga efektif pada tanggal berlaku
        |
        v
RAB Jasa mengambil harga dan menyimpan snapshot
        |
        v
Baseline dan RAB historis tidak berubah
```

Perubahan price list tidak boleh mengubah `ProjectRabJasa` lama. Baris RAB
menyimpan `harga_jasa_mitra_id`, `harga_satuan`, dan `total_nilai` sebagai
histori. Variation Order yang menambah pekerjaan juga harus menyimpan harga
satuan sesuai pricing semantics existing dan tidak boleh berubah karena revisi
Harga Jasa Mitra berikutnya.

Harga `null`/belum dikonfigurasi harus berbeda dari nilai Rupiah nol. Jika
harga belum tersedia, pembuatan RAB harus gagal dengan feedback yang jelas;
sistem tidak boleh memakai harga global, harga Mitra lain, atau default diam-
diam.

## Penempatan seam pada source final

Implementasi Workspace Admin Mitra diselesaikan pada commit `a43803a` dan
diverifikasi kembali pada rangkaian perbaikan sampai `27dcad1`:

- [ProjectPlanningService.php](../../app/Services/ProjectPlanningService.php:20)
  sudah mengunci Project dan memvalidasi harga berdasarkan tenant, status, dan
  tanggal berlaku sebelum membuat snapshot RAB. Service ini memisahkan Usulan
  Baseline milik Admin Mitra dari Baseline final dan menyediakan keputusan THC
  melalui `approveBaselineProposal`.
- [MitraPriceController.php](../../app/Http/Controllers/MitraPriceController.php:32)
  menjadi Adapter HTTP tipis untuk lifecycle Harga Jasa Mitra yang dipusatkan
  pada service source `MitraPriceBook`.
- [AdminController.php](../../app/Http/Controllers/AdminController.php:31)
  mendelegasikan aturan User Mitra kepada service administrasi dan aturan Grup
  kepada `MitraGroupPolicy`, termasuk allow-list capability Mitra.
- [MitraWarehouseController.php](../../app/Http/Controllers/MitraWarehouseController.php)
  mendelegasikan ownership Mitra dan Penugasan Gudang kepada service khusus.
- [AdminMitraWorkspaceTest.php](../../tests/Feature/AdminMitraWorkspaceTest.php)
  menjadi test surface utama untuk Workspace Admin Mitra.

View Workspace Admin Mitra memakai fondasi UI bersama pada
`resources/views/components/ui`. View Workspace Perencanaan Project sudah
memakai PageHeader, Panel, DataTable, FormControl, Badge, Search, EmptyState,
searchable select, dan formatter Rupiah bersama. Audit terminologi pasca-commit
yang masih terbuka dicatat terpisah pada
[issue-98-code-review.md](issue-98-code-review.md#audit-pasca-commit-2026-08-21).

## Test surface

Test harus melintasi interface module atau route Adapter, bukan implementation
internal. Minimum regression matrix:

| Area | Skenario wajib |
| --- | --- |
| Dashboard Mitra | ringkasan read-only hanya memuat Project, Warehouse, Material, Request Material, Transit, dan aktivitas dalam cakupan Mitra actor |
| User | list/create/update/deactivate/reset hanya tenant sendiri; tidak dapat menonaktifkan diri sendiri atau Admin Mitra terakhir |
| Grup | User Mitra tidak dapat memilih Grup dengan capability THC-only; permission baru tidak bocor melalui konfigurasi Grup |
| Warehouse | assignment own tenant berhasil; Warehouse/User tenant lain melalui ID langsung ditolak |
| Harga | submit own PKS berhasil; PKS/job/price tenant lain ditolak; harga ditolak atau belum berlaku tidak dapat dipakai |
| RAB | harga diambil dari Mitra Project; `harga_satuan` tetap setelah price list berubah |
| Baseline | Admin Mitra mengirim Usulan Baseline; hanya THC mengesahkan; Original Baseline tidak tertimpa; Revised Baseline memiliki versi baru |
| Variation Order | harga item baru disnapshot; RAB lama dan VO lama tidak berubah karena revisi harga berikutnya |
| Navigation | menu dan action hanya muncul ketika permission tersedia; direct URL tetap ditolak |
| RLS | query dan mutation lintas tenant gagal pada PostgreSQL, termasuk melalui payload dan route model binding |
| Smoke flow | login Admin Mitra, buka Dashboard Mitra, akses Project dan RAB Jasa yang diizinkan, lalu buka Warehouse yang ditugaskan tanpa memperoleh data tenant lain |

Test pada [AdminMitraWorkspaceTest.php](../../tests/Feature/AdminMitraWorkspaceTest.php)
dan test service terkait mencakup tenant scope utama. Matrix ini tetap menjadi
kontrak regression; penambahan capability baru wajib memperluas cross-tenant,
duplicate submit, concurrent state transition, dan smoke flow yang relevan.

## Urutan implementasi yang diterapkan

1. Bekukan capability matrix dan permission allow-list.
2. Bentuk module Harga Jasa Mitra karena menjadi sumber harga bagi RAB Jasa.
3. Pastikan snapshot RAB, Baseline, dan Variation Order memiliki regression
   test sebelum mengubah UI.
4. Ekstrak aturan User Mitra dan Penugasan Gudang dari controller.
5. Pindahkan controller menjadi Adapter tipis yang hanya memetakan request,
   memanggil module, dan memilih response.
6. Selaraskan seluruh view dengan fondasi UI bersama dan formatter Rupiah.
7. Jalankan focused test, PostgreSQL integration/security test, lalu full suite
   di `pms-dev`.

## Status verifikasi

Implementasi awal issue #95 pada `a43803a9ba5f2589fcf077d694d57e7190e39b79`
lulus verifikasi `pms-dev` dengan 290 test dan 1.880 assertion. Rangkaian
perbaikan berikutnya sampai `27dcad10d242993ea3a0417d232639b7fa27aaa9`
lulus full suite `pms-dev` dengan 314 test dan 1.938 assertion, kemudian
dideploy serta diverifikasi sehat di production. Perubahan pada dokumen ini
hanya menyelaraskan catatan desain dengan source final dan tidak mengubah
implementation code.
