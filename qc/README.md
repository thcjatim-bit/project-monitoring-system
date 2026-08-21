# QC Produksi

Ruang kerja untuk mencatat hasil **Review QC Produksi** pada aplikasi Project Monitoring System. Folder ini menyimpan observasi reviewer dan **Bukti QC** yang sudah aman untuk disimpan di Git; pekerjaan perbaikan engineering dilacak melalui GitHub Issue.

## Struktur

```text
qc/
├── README.md
├── TEMPLATE.md
└── <prefix>/
    └── QC-NNNN/
        ├── README.md
        ├── 01-actual.png
        └── 02-context.png
```

ID Temuan QC bersifat global dan memakai format `QC-NNNN`. Nama folder harus sama persis dengan ID Temuan QC; folder tidak memakai sequence terpisah per area. Bukti lama tidak ditimpa; bukti tambahan diberi nomor berikutnya.

Sebelum membuat temuan baru, scan seluruh folder `qc/` dan field `ID` pada README untuk mengambil angka terbesar, lalu gunakan angka berikutnya. ID yang pernah terbit tidak boleh dipakai ulang, termasuk ketika temuan dipindah area atau ditutup. Judul README, field `ID`, dan nama folder wajib menunjuk ID yang sama.

## Prefix awal

| Prefix | Area |
| --- | --- |
| `auth` | Login, logout, reset password, dan akses awal |
| `admin` | User, master data, konfigurasi THC, dan administrasi |
| `mitra` | Halaman dan alur User Mitra |
| `portfolio` | Portfolio Cockpit dan Decision Queue |
| `project` | Project, planning, progress, timeline, Foto Pekerjaan, Pemakaian Material, dan Rekon Material |
| `warehouse` | Warehouse, stok, Surat Jalan, dan Transit |
| `material-request` | Request Material dan alur persetujuannya |
| `api` | API baca dan perilaku integrasi API |

Prefix baru ditambahkan hanya ketika area aplikasi baru benar-benar ditemukan. Jika temuan menyentuh beberapa area, pilih area pemilik perilaku yang perlu direvisi dan jelaskan area terkait di detail temuan.

## Alur temuan

1. Ambil ID Temuan QC global berikutnya, lalu salin [`TEMPLATE.md`](TEMPLATE.md) ke `qc/<prefix>/QC-NNNN/README.md`.
2. Isi konteks produksi, langkah reproduksi, hasil aktual, hasil yang diharapkan, severity, dan status.
3. Tambahkan screenshot tersensor dengan nama berurutan.
4. Buat paling banyak satu GitHub Issue utama untuk temuan tersebut.
5. Buat satu tautan bernama ke GitHub Issue utama dari README temuan, lalu tautkan kembali folder QC dari issue.
6. Perbarui index ini bila temuan baru dibuat atau statusnya berubah.

## Status dan severity

Status yang digunakan:

- `open` — temuan sudah dicatat dan belum dikerjakan.
- `in_progress` — revisi sedang dikerjakan.
- `fixed` — revisi sudah dibuat dan menunggu pemeriksaan ulang.
- `verified` — hasil revisi sudah diverifikasi reviewer.
- `wont_fix` — diputuskan tidak direvisi, dengan alasan yang dicatat.

Severity yang digunakan:

- `blocker` — menghalangi alur utama atau membuat penggunaan tidak aman.
- `major` — fungsi penting salah atau menghasilkan dampak besar.
- `minor` — masalah terbatas yang tidak menghalangi alur utama.
- `suggestion` — saran UX, teks, atau penyempurnaan kecil.

## Keamanan bukti

Screenshot produksi wajib disensor sebelum di-commit. Jangan masukkan nomor WhatsApp, password, token, data pribadi, kredensial, atau data proyek yang bersifat rahasia ke repository. Bukti asli yang sensitif disimpan di penyimpanan terproteksi di luar Git; README hanya boleh merujuk lokasinya tanpa menyalin rahasia.

## Index temuan

| ID | Prefix | Temuan | Severity | Status | GitHub Issue |
| --- | --- | --- | --- | --- | --- |
| [QC-0001](dashboard/QC-0001/) | dashboard | layout dashboard Command Center | minor | open | [Bangun fondasi UI bersama dan selaraskan halaman dashboard, master, project, mitra, user](https://github.com/thcjatim-bit/project-monitoring-system/issues/90) |
| [QC-0002](portfolio/QC-0002/) | portfolio | konsistensi design Portfolio Cockpit | minor | open | [Bangun fondasi UI bersama dan selaraskan halaman dashboard, master, project, mitra, user](https://github.com/thcjatim-bit/project-monitoring-system/issues/90) |
| [QC-0003](portfolio/QC-0003/) | portfolio-dropdown | bug dan standardisasi searchable dropdown Portfolio | major | open | [Perbaiki Portfolio Status Risiko dan searchable select](https://github.com/thcjatim-bit/project-monitoring-system/issues/91) |
| [QC-0004](project/QC-0004/) | project | konsistensi design dan form modul Project | minor | open | [Bangun fondasi UI bersama dan selaraskan halaman dashboard, master, project, mitra, user](https://github.com/thcjatim-bit/project-monitoring-system/issues/90) |
| [QC-0005](mitra/QC-0005/) | mitra | konsistensi design Manajemen Mitra | minor | open | [Bangun fondasi UI bersama dan selaraskan halaman dashboard, master, project, mitra, user](https://github.com/thcjatim-bit/project-monitoring-system/issues/90) |
| [QC-0006](admin/QC-0006/) | user | Konsistensi Design Manajemen User | minor | open | [Bangun fondasi UI bersama dan selaraskan halaman dashboard, master, project, mitra, user](https://github.com/thcjatim-bit/project-monitoring-system/issues/90) |
| [QC-0007](admin/QC-0007/) | material | Konsistensi Design dan Auto-generate Kode Material | major | open | [Bangun fondasi UI bersama dan selaraskan halaman dashboard, master, project, mitra, user](https://github.com/thcjatim-bit/project-monitoring-system/issues/90) |
| [QC-0008](admin/QC-0008/) | unit | Konsistensi Design dan Auto-generate Kode Unit | major | open | [Bangun fondasi UI bersama dan selaraskan halaman dashboard, master, project, mitra, user](https://github.com/thcjatim-bit/project-monitoring-system/issues/90) |
| [QC-0009](admin/QC-0009/) | pop | Konsistensi Design dan Auto-generate Kode PoP | major | open | [Bangun fondasi UI bersama dan selaraskan halaman dashboard, master, project, mitra, user](https://github.com/thcjatim-bit/project-monitoring-system/issues/90) |
| [QC-0010](admin/QC-0010/) | pekerjaan-jasa | Konsistensi Design dan Auto-generate Kode Pekerjaan Jasa | major | open | [Bangun fondasi UI bersama dan selaraskan halaman dashboard, master, project, mitra, user](https://github.com/thcjatim-bit/project-monitoring-system/issues/90) |
| [QC-0011](warehouse/QC-0011/) | warehouse | Konsistensi Design dan Auto-generate Kode Warehouse | major | open | [Bangun fondasi UI bersama dan selaraskan halaman dashboard, master, project, mitra, user](https://github.com/thcjatim-bit/project-monitoring-system/issues/90) |
| [QC-0012](warehouse/QC-0012/) | warehouse-operasional | Konsistensi Design dan Penyempurnaan Operasional Material | major | open | [Selesaikan presentation Warehouse, quantity formatter, Surat Jalan, dan Transit](https://github.com/thcjatim-bit/project-monitoring-system/issues/92) |
| [QC-0013](warehouse/QC-0013/) | warehouse-transfer | Konsistensi Design Daftar dan Detail Surat Jalan | minor | open | [Selesaikan presentation Warehouse, quantity formatter, Surat Jalan, dan Transit](https://github.com/thcjatim-bit/project-monitoring-system/issues/92) |
| [QC-0014](warehouse/QC-0014/) | warehouse-transit | Konsistensi Design Material dalam Transit | minor | open | [Selesaikan presentation Warehouse, quantity formatter, Surat Jalan, dan Transit](https://github.com/thcjatim-bit/project-monitoring-system/issues/92) |
| [QC-0015](material-request/QC-0015/) | material-request | Konsistensi Design dan Informasi Project pada Request Material | major | open | [Tambahkan konteks Project pada daftar Request Material](https://github.com/thcjatim-bit/project-monitoring-system/issues/93) |
| [QC-0016](dashboard/QC-0016/) | mitra-workspace | Dashboard, Warehouse, dan Manajemen User Admin Mitra | major | open | [Implement workspace Admin Mitra setelah capability disetujui](https://github.com/thcjatim-bit/project-monitoring-system/issues/95) |
| [QC-0017](project/QC-0017/) | mitra-project | Project Workspace dan Hak Edit Admin Mitra | major | open | [Implement workspace Admin Mitra setelah capability disetujui](https://github.com/thcjatim-bit/project-monitoring-system/issues/95) |
| [QC-0018](admin/QC-0018/) | mitra-material | Konsistensi Design Master Material Admin Mitra | minor | open | [Bangun fondasi UI bersama dan selaraskan halaman dashboard, master, project, mitra, user](https://github.com/thcjatim-bit/project-monitoring-system/issues/90) |
| [QC-0019](admin/QC-0019/) | mitra-master-data | Konsistensi Design Master Unit dan PoP Admin Mitra | minor | open | [Bangun fondasi UI bersama dan selaraskan halaman dashboard, master, project, mitra, user](https://github.com/thcjatim-bit/project-monitoring-system/issues/90) |
| [QC-0020](admin/QC-0020/) | mitra-pekerjaan-jasa | Konsistensi Design dan Harga Jasa per Mitra | major | open | [Implement workspace Admin Mitra setelah capability disetujui](https://github.com/thcjatim-bit/project-monitoring-system/issues/95) |
