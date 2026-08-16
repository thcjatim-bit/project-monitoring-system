# Draft ticket — Gelombang 2: Project Control Room

Status: siap dipindahkan ke GitHub Issue  
Keputusan desain: opsi A — Control room  
Diputuskan setelah review prototype: 15 Agustus 2026

## Keputusan

Gelombang 2 memakai **Project Control Room** sebagai halaman detail utama sebuah Project. Halaman ini memberi user satu tempat untuk membaca kondisi project, progres terhadap baseline, kesiapan material, Step yang sedang berjalan, dan aktivitas terbaru.

Control room menjadi ringkasan kendali dan pintu masuk ke detail pekerjaan. Ia tidak menggantikan alur operasional yang memiliki aturan transaksinya sendiri, seperti Material, Surat Jalan, upload Foto Pekerjaan, atau Rekon Material.

Pertanyaan yang diselesaikan:

> Untuk Gelombang 2, apakah halaman utama project sebaiknya berupa control room desktop, alur field-first, atau evidence ledger?

Jawaban: **Control room**. Hierarki informasi yang dipilih adalah identitas dan aksi project → KPI inti → Kurva S → Step → aktivitas terbaru dan kesiapan material.

## Sumber keputusan

- Prototype branch: `prototype/gelombang-2-ui`
- Prototype commit: `39b1b9a1b4f6ee3921b56afaa2f712a5d8afe46b`
- URL saat development: `/prototype/gelombang-2?variant=control`
- Referensi visual: [resources/views/prototypes/gelombang-2.blade.php](../../resources/views/prototypes/gelombang-2.blade.php)

Prototype adalah artefak eksplorasi. Implementasi produksi harus ditulis ulang mengikuti seam, authorization, query, dan standar aplikasi; jangan mempromosikan view prototype secara langsung.

## Tujuan

User yang berhak dapat membuka satu Project dan segera memahami progres jasa, kinerja jadwal, kesiapan material, posisi Step, serta aktivitas terbaru tanpa menggabungkan aturan bisnis dari modul yang berbeda ke dalam view.

## Scope implementasi

- Menambahkan halaman detail Project dari daftar `/projects` dengan layout **Control room** yang responsif.
- App shell dan navigasi yang konsisten dengan aplikasi THC.
- Header Project yang menampilkan:
  - ID Project dan nama Project.
  - Mitra pemilik Project.
  - Status Project (`aktif` / `selesai`).
  - TOC (Target Operation Complete).
  - Aksi yang memang diizinkan oleh user, termasuk membuka linimasa atau menambah komentar.
- Empat ringkasan KPI:
  - Realisasi jasa terverifikasi.
  - SPI terhadap baseline yang berlaku.
  - Kesiapan material secara terpisah dari bobot jasa.
  - Status Project.
- Grafik Kurva S dengan tampilan terpisah untuk:
  - Baseline yang berlaku dan Original Baseline bila ada revisi.
  - Realisasi jasa yang sudah diverifikasi.
  - Progres yang masih pending sebagai garis putus-putus atau shadow.
  - Penanda status SPI sesuai threshold domain.
- Step Project berupa 11 Step baku, dengan Step aktif, Step selesai, dan tanggal aktual selesai.
- Panel aktivitas terbaru yang menggabungkan log sistem dan komentar Project secara visual berbeda.
- Komentar biasa dan Komentar Internal THC sesuai visibility serta aturan audit domain.
- Ringkasan kesiapan material yang memakai jumlah material terkirim dibanding kebutuhan RAB Material, termasuk tautan ke detail material yang relevan.
- Foto Pekerjaan dapat diakses dari konteks Project/Step dan menampilkan status bukti pekerjaan yang relevan.
- Empty state, loading state, error state, dan tampilan responsif untuk panel-panel utama.
- Query/read model yang jelas untuk merakit data control room; controller atau Livewire component tidak menjadi tempat seluruh aturan perhitungan.

## Di luar scope

- Menjadikan view prototype sebagai view produksi.
- Mengubah status Material, Surat Jalan, stok, progres, atau Step langsung dari KPI/card tanpa melewati pemilik alur dan authorization masing-masing.
- Export laporan dan API publik; direncanakan untuk Gelombang 3.
- Dashboard gabungan lintas Project; direncanakan untuk Gelombang 3.
- Implementasi gateway WhatsApp sebagai bagian dari halaman control room.
- Penggantian halaman operasional Gudang, Request Material, Surat Jalan, Foto Pekerjaan, atau Rekon Material.

## Acceptance criteria

- [ ] User terautentikasi dengan `read_project` dapat membuka detail Project dari daftar Project.
- [ ] User tanpa `read_project` menerima penolakan authorization dan tidak dapat membuka detail Project melalui URL langsung.
- [ ] User Mitra hanya dapat melihat Project milik Mitranya; user THC dapat melihat Project sesuai hak aksesnya.
- [ ] Query detail Project, relasi, agregasi, dan endpoint turunannya tidak membuka data lintas tenant; PostgreSQL RLS tetap menjadi pertahanan dasar.
- [ ] Header menampilkan ID Project, nama, Mitra, status administratif, dan TOC dari data aktual.
- [ ] Persen Realisasi dihitung dari nilai rupiah jasa pada progres yang sudah diverifikasi dibagi Grand Total RAB Jasa; nilai tidak boleh melebihi 100%.
- [ ] Persen Rencana memakai baseline yang berlaku pada tanggal yang diminta dan plotting progres memakai tanggal aktual pekerjaan.
- [ ] Bila ada Revised Baseline, Original Baseline tetap dapat dibedakan dan kinerja/SPI memakai Revised Baseline sesuai ADR.
- [ ] Progres pending tetap terlihat sebagai garis putus-putus atau shadow dan tidak dihitung sebagai Realisasi terverifikasi.
- [ ] SPI menampilkan `N/A` bila kumulatif baseline masih 0%; warna indikator mengikuti threshold: hijau untuk `>= 1.0`, kuning untuk `0.9–< 1.0`, dan merah untuk `< 0.9`.
- [ ] Jika Project melewati TOC tanpa Revised Baseline, sumbu waktu memanjang, baseline mendatar di 100%, dan periode keterlambatan diberi penanda yang jelas.
- [ ] Kesiapan material dihitung dan ditampilkan terpisah dari Kurva S; material Transit tidak dianggap sebagai stok tersedia.
- [ ] Control room menampilkan 11 Step baku dan membedakan Step selesai, aktif, belum selesai, serta tanggal aktual selesai tanpa menciptakan tanggal rencana baru di level Step.
- [ ] Linimasa menggabungkan log sistem dan komentar, menampilkan perubahan Step, perubahan TOC/Variation Order, aktivitas Surat Jalan terkait Project, serta Foto Pekerjaan bila event-nya tersedia.
- [ ] Komentar Internal hanya terlihat oleh user THC; komentar tidak dapat dihapus dan perubahan edit diberi penanda `edited`.
- [ ] Aksi tambah komentar, perubahan Step, upload foto, dan aksi lain tunduk pada Izin Aksi masing-masing serta memanggil alur pemiliknya.
- [ ] Upload Foto Pekerjaan mengikuti batas domain: JPEG saja, maksimal 10 foto per unggahan, maksimal 5 MB per foto mentah, dan kompresi client-side hingga maksimal 1920×1080 sebelum dikirim.
- [ ] Panel utama memiliki empty, loading, dan error state yang informatif tanpa menghilangkan konteks Project.
- [ ] Feature test mencakup akses THC, user tanpa izin, isolasi Project antar Mitra, perhitungan KPI, visibility Komentar Internal, dan state pending Kurva S.
- [ ] PostgreSQL integration/security tests lulus di `pms-dev` menggunakan database testing khusus.
- [ ] Full test suite, code review, dan verifikasi pms-dev selesai sebelum ticket ditutup.

## Catatan implementasi

- Gunakan istilah domain dari `CONTEXT.md`: `Project`, `Mitra`, `Status Project`, `Step`, `TOC`, `RAB Jasa`, `Kurva S`, `SPI`, `Original Baseline`, `Revised Baseline`, `Linimasa Gabungan`, `Komentar Internal`, dan `Foto Pekerjaan`.
- Rujuk ADR 0010 untuk rumus Kurva S/SPI dan kesiapan material, ADR 0011 untuk Step/Linimasa/komentar, serta ADR 0012 untuk Foto Pekerjaan dan sinkronisasi Google Drive.
- Data control room sebaiknya disiapkan melalui read model/aggregator yang bisa diuji terpisah. View hanya menyajikan data dan navigasi ke alur detail.
- Tombol `Export` pada prototype adalah simulasi visual dan tidak termasuk ticket ini.
- Variant B dan C tetap menjadi bahan pembanding di branch prototype; keputusan yang dibawa ke implementasi produksi hanya struktur dan hierarki informasi Variant A.
