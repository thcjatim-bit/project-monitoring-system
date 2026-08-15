# Draft ticket - Gelombang 3: Portfolio Cockpit, Export, dan API

Status: siap dipindahkan ke GitHub Issue
Keputusan desain: opsi A - Portfolio Cockpit
Diputuskan setelah review prototype: 15 Agustus 2026

## Keputusan

Gelombang 3 memakai **Portfolio Cockpit** sebagai dashboard gabungan lintas Project untuk user THC. Halaman ini menjawab pertanyaan "apa yang perlu saya baca atau putuskan sekarang?" dari tingkat portofolio, lalu mengarahkan user ke modul pemilik data untuk tindak lanjut.

Portfolio Cockpit tidak menggantikan Project Control Room Gelombang 2. Control Room tetap menjadi halaman detail satu Project; Portfolio Cockpit menjadi pintu masuk lintas Project.

Hierarki informasi yang dipilih:

1. KPI kesehatan portofolio.
2. Tren realisasi jasa terhadap target kumulatif.
3. Health matrix untuk membandingkan Project.
4. Decision queue untuk pengecualian berisiko.
5. Distribusi status dan linimasa aktivitas terbaru.

Pertanyaan yang diselesaikan:

> Untuk Gelombang 3, apakah pusat pengalaman user THC sebaiknya berupa ringkasan kesehatan lintas Project, antrean risiko/pengecualian, atau workspace laporan dan API read-only?

Jawaban: **ringkasan kesehatan lintas Project melalui Portfolio Cockpit**. Risk Desk tetap menjadi pola isi yang dapat dipakai di dalam decision queue, sedangkan Report Studio bukan halaman utama.

## Sumber keputusan

- Prototype branch: `prototype/gelombang-3-ui`
- Prototype commit: `a60e84685c969bd66ef59ae03bb913ab48ab8a47`
- URL saat development: `/prototype/gelombang-3?variant=portfolio`
- Referensi visual: [resources/views/prototypes/gelombang-3.blade.php](../../resources/views/prototypes/gelombang-3.blade.php)

Prototype adalah artefak eksplorasi. Implementasi produksi harus ditulis ulang mengikuti seam, authorization, query, dan standar aplikasi; jangan mempromosikan view prototype secara langsung.

Bagian pendukung dari variant lain yang dibawa: aksi Export ringkasan dan tautan API read-only. Struktur Report Studio, Risk Desk sebagai halaman penuh, serta switcher prototype tidak dibawa ke produksi.

## Tujuan

User THC yang memiliki `read_dashboard` dapat melihat kesehatan seluruh Project yang menjadi cakupan aksesnya, menemukan pengecualian yang membutuhkan keputusan, dan membuka sumber data tanpa menggabungkan aturan mutasi dari modul Project, Material, Surat Jalan, atau Progres ke dalam dashboard.

## Scope implementasi

- App shell dan navigasi yang konsisten dengan aplikasi THC.
- KPI portofolio yang berasal dari data aktual:
  - jumlah Project aktif;
  - realisasi jasa terverifikasi secara agregat;
  - jumlah Project yang perlu perhatian;
  - kesiapan material dan nilai RAB aktif.
- Filter eksplisit untuk cakupan Project, Mitra, periode, dan status risiko.
- Tren realisasi jasa portofolio terhadap target kumulatif, dengan penjelasan periode dan waktu pembaruan data.
- Health matrix lintas Project yang menampilkan minimal ID Project, nama, Mitra, progress jasa terverifikasi, SPI, kesiapan material, dan status risiko.
- Decision queue untuk pengecualian seperti SPI rendah, Transit melewati batas, material belum lengkap, TOC yang mendekat, dan bukti pekerjaan yang menunggu verifikasi.
- Tautan dari setiap KPI, baris health matrix, dan decision queue ke halaman sumber yang memiliki authorization serta aturan transaksinya.
- Distribusi status Project dan linimasa aktivitas terbaru lintas Project.
- Export ringkasan berdasarkan filter aktif, menggunakan read model yang sama dengan Portfolio Cockpit.
- API baca untuk konsumen internal sesuai kontrak API Key, view read-only, dan pembatasan pada ADR 0016.
- Empty state, loading state, error state, waktu sinkronisasi, dan layout responsif untuk desktop serta layar kecil.

## Di luar scope

- Menjadikan view prototype sebagai view produksi.
- Menjadikan Risk Desk atau Report Studio sebagai halaman utama terpisah.
- Mutasi status Project, Material, Surat Jalan, Progres, Step, atau Rekon Material langsung dari dashboard.
- Penggantian Project Control Room Gelombang 2 atau halaman operasional Gudang.
- Penyusunan report builder bebas, scheduled delivery, atau konfigurasi laporan ad-hoc; dapat dibuat sebagai ticket lanjutan setelah kontrak export stabil.
- API tulis, API untuk user Mitra, atau pemberian akses langsung ke tabel mentah dan Komentar Internal.

## Acceptance criteria

- [ ] User THC dengan `read_dashboard` dapat membuka Portfolio Cockpit setelah login.
- [ ] User tanpa `read_dashboard` menerima penolakan authorization dan tidak dapat mengakses data melalui URL langsung.
- [ ] User THC hanya melihat Project sesuai cakupan hak aksesnya; user Mitra hanya melihat Project milik Mitranya.
- [ ] Semua KPI dan angka agregat berasal dari query/read model data aktual, bukan angka yang dihitung di view.
- [ ] Progress agregat hanya memasukkan progres jasa yang sudah diverifikasi; progres pending tetap dibedakan dan tidak menaikkan realisasi.
- [ ] SPI memakai baseline yang berlaku dan menampilkan `N/A` bila kumulatif baseline masih 0%; threshold warna mengikuti aturan domain.
- [ ] Kesiapan material dihitung terpisah dari bobot jasa dan Material Transit tidak dianggap sebagai stok tersedia.
- [ ] Filter Mitra, periode, dan status memengaruhi KPI, matrix, decision queue, dan export secara konsisten serta terlihat pada halaman.
- [ ] Setiap item decision queue mempunyai sumber, konteks Project, dan tautan ke modul pemilik; dashboard tidak melakukan mutasi.
- [ ] Health matrix dapat dibuka pada layar kecil tanpa kehilangan identitas Project dan status risiko.
- [ ] Export mengikuti filter aktif, menggunakan read model Portfolio Cockpit, dan tidak membocorkan Komentar Internal atau data lintas tenant.
- [ ] API hanya menyediakan permukaan baca yang disetujui, memakai API Key yang di-hash, dan tidak memberikan grant ke tabel mentah.
- [ ] Panel memiliki empty, loading, dan error state yang informatif tanpa menghilangkan filter atau konteks user.
- [ ] Feature test mencakup akses THC, penolakan tanpa izin, isolasi Mitra, agregasi KPI, threshold SPI, material Transit, filter, decision queue, dan export.
- [ ] PostgreSQL integration/security tests lulus di `pms-dev` menggunakan database testing khusus.
- [ ] Full test suite, code review, dan verifikasi `pms-dev` selesai sebelum ticket ditutup.

## Catatan implementasi

- Gunakan istilah domain dari `CONTEXT.md`: `Project`, `Mitra`, `Status Project`, `TOC`, `RAB Jasa`, `Kurva S`, `SPI`, `Material`, `Transit`, `Surat Jalan`, `Foto Pekerjaan`, dan `Komentar Internal`.
- Siapkan read model/aggregator yang dapat diuji terpisah untuk KPI, health matrix, decision queue, dan export. Controller atau Livewire component tidak menjadi tempat seluruh aturan agregasi.
- Pertahankan batas ownership: Portfolio Cockpit membaca dan menautkan; Project Control Room, Gudang, Surat Jalan, Progres, dan Rekon Material tetap memiliki alur mutasinya.
- Rujuk ADR 0010 untuk Kurva S/SPI, ADR 0012 untuk Foto Pekerjaan, ADR 0013 untuk Rekon Material, dan ADR 0016 untuk API baca serta role PostgreSQL read-only.
