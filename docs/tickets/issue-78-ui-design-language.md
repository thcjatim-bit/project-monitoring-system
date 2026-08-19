# Issue #78 — Bahasa desain UI PMS untuk THC dan Mitra

Status: Variant A dipilih dan fondasi visualnya diterapkan pada app shell; prototype tetap menjadi artefak referensi, bukan sumber data production.

## Pertanyaan yang dijawab

Bahasa visual bersama apa yang membuat menu Project Monitoring System terasa satu sistem untuk user THC dan User Mitra, tanpa menghapus perbedaan cakupan data dan Izin Aksi?

## Variasi prototype

- **A — Ruang Kendali:** sidebar permanen, keputusan dan antrean di depan, lalu jalan masuk ke keluarga menu.
- **B — Papan Modul:** navigasi tab di atas, tabel padat sebagai pusat kerja, dan inspector form di samping.
- **C — Alur Lapangan:** mobile-first, satu tindakan utama, timeline pekerjaan, dan navigasi bawah.

URL review: `/prototype/ui-language?variant=a`, `/prototype/ui-language?variant=b`, dan `/prototype/ui-language?variant=c`.

## Resolution

Variasi terpilih adalah **A — Ruang Kendali**. A paling cocok dengan pola Command Center dan Portfolio Cockpit yang sudah ada: user THC mulai dari hal yang perlu diputuskan, sedangkan setiap kartu tetap mengarah ke modul pemilik alur. B dipertahankan sebagai pembanding untuk daftar/worklist padat; C dipertahankan untuk menguji layar kecil dan pekerjaan lapangan.

Token yang diuji:

- Warna: ink `#172033`, accent aktif `#4656D8`, success hijau, attention amber, error merah; status tidak boleh bergantung pada warna saja.
- Typography: system sans; heading rapat, label kecil eksplisit, teks UI Indonesia.
- Spacing: unit dasar 8 px dengan skala 4 / 8 / 12 / 16 / 24 / 32 px.
- Shape: A 16 px, B 7 px, C 24 px sebagai pembeda eksperimen; bukan keputusan production.

Aturan komponen yang menjadi kontrak implementasi:

1. Satu primary action untuk satu konteks kerja.
2. Badge status selalu memuat teks dan warna; status domain memakai istilah dari `CONTEXT.md`.
3. Empty, loading, dan error mempertahankan identitas halaman serta jalan kembali ke modul sumber.
4. Form dan searchable select memakai istilah domain lengkap (`Request Material`, `Pemakaian Material`, `Rekon Material`, `Material`, dan `Unit`).
5. Role THC/Mitra mengubah cakupan dan copy, bukan sekadar mengganti tema warna.

Artefak primer: `resources/views/prototypes/ui-language.blade.php`.

Fondasi production Variant A: `resources/views/components/layouts/app.blade.php`.

Prototype ini read-only, memakai mock-data in-memory, tidak memakai session/database, dan hanya dirouting ketika environment bukan production. Implementasi production harus ditulis ulang mengikuti authorization, query/read model, RLS, dan pemilik alur transaksi.
