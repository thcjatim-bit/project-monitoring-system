# Draft ticket — Gelombang 1: Command Center THC

Status: siap dipindahkan ke GitHub Issue  
Keputusan desain: opsi A — Command Center  
Diputuskan setelah review prototype: 15 Agustus 2026

## Keputusan

Gelombang 1 memakai Command Center sebagai halaman kerja utama user THC. Halaman ini memprioritaskan hal yang membutuhkan keputusan dan tindakan, bukan sekadar menampilkan seluruh data modul dalam bentuk dashboard.

Pertanyaan yang diselesaikan:

> Untuk Gelombang 1, apakah pusat perhatian harian sebaiknya command center, meja operasional gudang, atau cockpit governance?

Jawaban: Command Center. Detail operasional Gudang, Request Material, Surat Jalan, User & Grup, dan Master Data tetap berada di modul masing-masing dan diakses dari konteks alert atau queue.

## Sumber keputusan

- Prototype branch: `prototype/gelombang-1-ui`
- Prototype commit: `456b636`
- URL saat development: `/prototype/gelombang-1?variant=command`
- Referensi visual: [resources/views/prototypes/gelombang-1.blade.php](../../resources/views/prototypes/gelombang-1.blade.php)

Prototype adalah artefak eksplorasi. Implementasi produksi harus ditulis ulang mengikuti seam, authorization, query, dan standar aplikasi; jangan mempromosikan view prototype secara langsung.

## Tujuan

User THC dapat mengetahui pekerjaan yang paling mendesak dan membuka antrean tindakan terkait dari satu halaman setelah login.

## Scope implementasi

- App shell THC dengan navigasi ke modul Gelombang 1.
- Ringkasan jumlah:
  - Request Material yang memerlukan keputusan.
  - Material yang sedang Transit dan melewati batas waktu.
  - Material dengan stok kritis.
  - User aktif dan perubahan onboarding yang menunggu tindak lanjut.
- Panel “Yang membutuhkan perhatian” dengan prioritas risiko operasional.
- Aktivitas lintas operasional terbaru dari Request Material, Surat Jalan, User, dan Master Data.
- Ringkasan kesiapan setiap Warehouse yang dapat dilihat user THC.
- Tautan dari setiap alert ke halaman antrean/detail yang relevan.
- Empty state, loading state, dan error state untuk tiap panel.
- Responsive layout untuk desktop dan layar kecil.

## Di luar scope

- CRUD baru dari Command Center.
- Perubahan status Request Material, Surat Jalan, stok, User, atau Master Data langsung dari kartu ringkasan.
- Penggantian halaman operasional Gudang dengan dashboard agregat.
- Implementasi notifikasi WhatsApp.
- Kurva S, SPI, foto, komentar, export, atau API Gelombang 2–3.

## Acceptance criteria

- [ ] Setelah login, user THC dengan `read_dashboard` dapat membuka Command Center.
- [ ] Menu dan panel hanya tampil bila user memiliki Izin Aksi yang sesuai.
- [ ] Setiap angka agregat berasal dari data aplikasi aktual dan memiliki tautan ke antrean sumbernya.
- [ ] Perhitungan Transit memakai status Surat Jalan dan aturan batas waktu yang sudah diputuskan; material Transit tidak dihitung sebagai stok Warehouse.
- [ ] Stok kritis mengikuti saldo material aktual, termasuk pembedaan material biasa, ber-SN, dan drum_kabel bila diperlukan oleh query.
- [ ] Data user Mitra tetap tunduk pada isolasi mitra; Command Center THC boleh melihat lintas Mitra sesuai hak aksesnya.
- [ ] Dashboard tidak melakukan mutasi data; semua tindakan lanjutan berpindah ke modul yang memiliki authorization dan validasi transaksinya.
- [ ] Panel memiliki state kosong yang informatif ketika tidak ada pekerjaan tertunda.
- [ ] Query dan policy tidak memperkenalkan akses lintas tenant.
- [ ] Feature test mencakup akses THC, user tanpa izin, dan data yang harus tersembunyi dari user Mitra.
- [ ] PostgreSQL integration/security tests lulus di `pms-dev`.
- [ ] Code review selesai sebelum ticket ditutup.

## Catatan implementasi

- Gunakan istilah domain dari `CONTEXT.md`: `Request Material`, `Surat Jalan`, `Transit`, `Warehouse`, `Material`, `User THC`, `User Mitra`, `Grup`, dan `Izin Aksi`.
- Dashboard sebaiknya memiliki query read model/aggregator yang jelas agar controller atau Livewire component tidak menjadi tempat aturan domain.
- Kartu hanya menjadi navigasi dan ringkasan. Mutasi tetap melalui service/controller modul yang sudah menjadi pemilik alur.
- Prototype A menunjukkan hierarki informasi yang dipilih: alert berisiko → aktivitas terbaru → kesiapan Warehouse → distribusi pekerjaan.
