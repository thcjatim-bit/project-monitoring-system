# ADR 0011: Step Project, Linimasa Gabungan, dan Komentar Internal

## Status
Accepted

## Konteks
Sistem membutuhkan pencatatan fase pengerjaan proyek (Step) beserta alur komunikasi (komentar, mention) dan jejak audit aktivitas sistem. Pertanyaannya adalah bagaimana menstrukturkan step tersebut agar tidak kaku, serta bagaimana menyajikan riwayat interaksi dan notifikasi agar mudah dibaca oleh semua pihak tanpa membocorkan koordinasi internal THC ke Mitra.

## Keputusan
1. **Step Project Fleksibel**:
   - Terdapat 11 step baku untuk seluruh project (Design, Survey, DRM, SPK, Pengadaan Material, Delivery Material, MOS, Deployment, Test Comm, ATP, GO Live).
   - Step dapat dilompati atau dimundurkan (jika ada ralat/penyesuaian lapangan).
   - Tiap step hanya mencatat **tanggal aktual selesai**. Tanggal rencana tidak ada di level step, melainkan terpusat pada TOC (Target Operation Complete) di level Project.
2. **Linimasa Gabungan (Unified Timeline)**:
   - Komentar manusia (diskusi) dan log kejadian sistem otomatis ditampilkan dalam satu aliran linimasa yang sama, dibedakan secara visual.
   - Kejadian yang otomatis tercatat meliputi:
     - Perubahan Step Project (maju/mundur/lompat).
     - Perubahan TOC dan amandemen RAB (Variation Order).
     - Aktivitas Surat Jalan (terbit, selesai, batal) yang menunjuk ke project tersebut.
     - Penambahan/unggahan foto baru.
3. **Komentar Internal**:
   - Terdapat fitur "Internal Note" yaitu komentar khusus tim THC yang secara sistem disembunyikan dari penglihatan user Mitra.
   - Komentar (baik reguler maupun internal) tidak boleh dihapus sama sekali untuk menjaga rekam jejak, namun **boleh diedit** (sistem akan menambahkan label "edited").
4. **Mention dan Notifikasi**:
   - Semua user yang memiliki akses ke project dapat saling me-mention (termasuk Mitra me-mention THC dan sebaliknya).
   - Notifikasi mention akan dimunculkan pada lonceng web (in-app notification) **dan** dikirim via WhatsApp otomatis berisi cuplikan komentar beserta link ke halaman project.

## Konsekuensi
- Database `project_timelines` (atau sejenisnya) perlu mendukung `type` untuk membedakan antara `comment`, `internal_note`, dan `system_log`.
- Sistem harus menolak *soft-delete* atau *hard-delete* pada komentar.
- WhatsApp gateway diwajibkan menyala untuk memenuhi aliran notifikasi realtime, yang membutuhkan resource server.
