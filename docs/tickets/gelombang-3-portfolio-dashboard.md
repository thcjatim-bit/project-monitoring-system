# Draft ticket - Gelombang 3: Portfolio Dashboard, Export, dan API

Status: prototype siap direview; keputusan desain belum dipilih

## Pertanyaan desain

> Untuk Gelombang 3, apakah pusat pengalaman user THC sebaiknya berupa ringkasan kesehatan lintas Project, antrean risiko/pengecualian, atau workspace laporan dan API read-only?

## Sumber prototype

- Prototype branch: `prototype/gelombang-3-ui`
- URL saat development: `/prototype/gelombang-3?variant=portfolio`
- Variant A: `portfolio` - Portfolio cockpit
- Variant B: `risks` - Risk desk
- Variant C: `reports` - Report studio
- Referensi visual: [resources/views/prototypes/gelombang-3.blade.php](../../resources/views/prototypes/gelombang-3.blade.php)

Prototype ini adalah artefak eksplorasi. Mock data bersifat lokal, read-only, dan tidak disimpan. Implementasi produksi harus ditulis ulang dengan read model, authorization, query, dan standar aplikasi; jangan mempromosikan view prototype secara langsung.

## Hal yang dieksplorasi

- Agregasi lintas Project: progress jasa, SPI, kesiapan material, status, dan nilai RAB.
- Decision queue untuk SPI rendah, Transit terlambat, material belum lengkap, TOC yang mendekat, dan bukti yang menunggu verifikasi.
- Export ringkasan/briefing ke Excel atau PDF melalui simulasi read-only.
- Report builder dengan rentang tanggal, scope Mitra, pemilihan kolom, scheduled delivery, dan endpoint API baca.
- Batas penting: dashboard hanya mengarahkan ke modul pemilik; tidak melakukan mutasi status Project, Material, Surat Jalan, atau progres.

## Capture keputusan setelah review

- Variant terpilih: pending review
- Alasan dan hierarki informasi: pending review
- Bagian dari variant lain yang dipinjam: pending review

## Batas implementasi yang diperkirakan

- Dashboard gabungan tunduk pada `read_dashboard` dan isolasi Mitra untuk user Mitra.
- Export memakai read model yang sama dengan dashboard dan tidak membocorkan Komentar Internal.
- API publik/eksternal tidak boleh diasumsikan dari prototype; kontrak endpoint, API Key, rate limit, dan view read-only mengikuti ADR 0016.
- Semua export dan endpoint menggunakan data aktual, status filter yang eksplisit, dan audit yang sesuai.
