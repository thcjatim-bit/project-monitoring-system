# ADR-0027 — Fondasi UI adalah Flowbite di balik komponen `x-ui.*`

Status: diterima · Tanggal: 2026-08-24 · Tiket: [#149](https://github.com/thcjatim-bit/project-monitoring-system/issues/149)

## Konteks

Peta [#145](https://github.com/thcjatim-bit/project-monitoring-system/issues/145) berangkat dari satu keluhan konkret: beban perawatan CSS/UI. Hari ini permukaan aplikasi disangga oleh dua sistem yang tidak saling kenal — `resources/css/app.css` berisi 93 baris CSS semantik tulisan tangan (`.ui-page`, `.ui-panel`, `.ui-table`), sementara App shell membawa `<style>` inline sendiri dengan token `--app-*` terpisah. Tailwind 4 sudah terpasang tapi nyaris tidak dipakai sebagai bahasa utama. Setiap komponen baru berarti menambah baris CSS baru, dan setiap keputusan visual diambil ulang dari nol.

[#147](https://github.com/thcjatim-bit/project-monitoring-system/issues/147#issuecomment-5394061915) mengunci kontraknya: komponen wajib, invarian seam, baseline aksesibilitas, matriks state, dan budget bundle. [#148](https://github.com/thcjatim-bit/project-monitoring-system/issues/148#issuecomment-5395800914) mengadu dua bahasa visual pada tiga layar operasional dengan fixture statis yang sama, dan manusia memilih Kandidat A — gaya Flowbite, *component-first*, App shell dipertahankan.

Yang belum diputuskan sampai ADR ini: apakah "gaya Flowbite" berarti memasang paketnya, atau menyalin polanya dengan tangan. Prototype #148 sengaja tidak menjawab itu — ia nol kode dan nol asset Flowbite, hanya peniruan visual lokal.

## Keputusan

### Flowbite masuk sebagai dependency nyata, di balik fasad `x-ui.*`

`flowbite@4.0.2` (MIT) dipasang sebagai `devDependency` dengan **pin eksak**, bukan caret. Plugin CSS-nya didaftarkan di `resources/css/app.css` lewat mekanisme Tailwind 4 (`@plugin` + `@source`).

Alternatif yang ditolak adalah menyalin pola markup Flowbite dengan tangan tanpa memasang paketnya. Itu terdengar hemat, tapi mengembalikan tepat beban yang memotivasi peta ini: kita tetap merawat sendiri *focus trap*, penanganan Escape, *restore focus*, dan posisi *dropdown* — semua yang diwajibkan #147 — sambil tetap menanggung risiko hasil kita menyimpang pelan-pelan dari rujukan visual yang dipilih manusia.

Pin eksak bukan kerewelan. `public/build` di-commit ke repositori dan `scripts/verify-production-assets.mjs` menolak build yang tidak reproducible; caret berarti asset bisa berubah tanpa ada satu commit pun yang menjelaskan kenapa.

**Markup Flowbite tidak pernah muncul langsung di view.** Ia selalu tinggal di dalam komponen `x-ui.*`. View berbicara dalam istilah domain, komponen menerjemahkannya jadi kelas utility. Ini yang membuat dependency tetap bisa dicabut kelak tanpa menyentuh 40+ berkas Blade.

### Daftar izin dan larangan

Boleh masuk: Flowbite core (MIT), inisialisator JS yang **diimpor selektif** (`initModals`, `initDrawers`, `initDropdowns`, `initTooltips`), dan SVG dari Flowbite Icons yang benar-benar dipakai — disalin satu per satu ke `x-ui.icon`.

Dilarang, dan larangannya berlaku sampai ada ADR yang mencabutnya:

- **Flowbite Blocks/Pro** atau asset komersial apa pun.
- **CDN dan runtime eksternal**. Semua asset dibundel dan diverifikasi lokal.
- **Webfont.** Repositori hari ini nol referensi CDN dan nol webfont; `Inter`/`Instrument Sans` hanya nama di *font stack* yang jatuh ke system font. Putusan visual #148 dinilai manusia **dengan** rendering system font — menambah webfont sekarang justru mengubah hal yang sudah disetujui, sambil menambah bundle dan risiko FOUT tanpa bukti kebutuhan.
- **Paket ikon dan icon font.** Hanya SVG yang dipakai yang masuk bundle.
- **Library chart JS.** Chart bersifat *read-only* dan angkanya sudah dihitung server (ADR-0010), jadi ia dirender server-side sebagai SVG/CSS dengan *fallback* tabel. ApexCharts sendirian berpotensi menghabiskan seluruh budget bundle untuk sesuatu yang tidak interaktif.
- **`import 'flowbite'` penuh**, karena menyeret `flowbite-datepicker` dan Popper yang tidak kita pakai.
- **Alpine.js**, React, dan Vue sebagai runtime.
- **Varian `dark:`.** #147 mewajibkan tiap komponen diuji pada sekitar sembilan state; dark mode menggandakan matriks itu tanpa ada yang memintanya. Menyalin `dark:` tanpa dukungan resmi lebih buruk lagi — kode mati yang menyamar sebagai fitur.
- **`<script>` inline baru di komponen Blade.** JS inline tidak terhitung dalam budget bundle, tidak ikut terverifikasi oleh gate asset, dan menutup pintu CSP. Skrip inline yang sudah ada di `x-ui.search` dipindahkan ke modul JS pada G0.

### App shell: perilakunya beku, CSS-nya tidak

#147 menyatakan "App shell dipertahankan". Yang dipertahankan adalah **struktur dan perilakunya**: sidebar desktop, drawer mobile, `aria-current="page"` pada route aktif, breadcrumb dari route nyata, dan menu yang tunduk pada Izin Aksi (ADR-0006).

Implementasi CSS-nya tidak beku. Pada G0, token `--app-*` pindah ke `@theme` di `app.css`, markup shell berpindah ke utility + pola sidebar/drawer Flowbite, dan `<style>` inline dihapus. Membiarkannya berarti shell dan isinya dipimpin dua sistem token yang berbeda — persis penyakit yang ADR ini obati.

### Seam: presentasi, state, dan data tidak boleh saling meminjam

Batas dari #147 berlaku tanpa pengecualian: `x-ui.*` menangani presentasi, Livewire menangani state, Query/Service menangani data dan authorization. Komponen tidak menyentuh database, tidak memilih tenant, tidak menghitung metrik domain, dan tidak mengambil alih authorization.

Dua aturan turunan yang lahir dari memasang Flowbite JS di atas Livewire, dan yang tanpa ditulis akan jadi sumber bug diam-diam:

1. **Overlay berstate domain dikendalikan Livewire, bukan Flowbite.** Konfirmasi hapus dan form bertahap adalah state aplikasi; Flowbite hanya menyediakan kerangka presentasinya. Yang boleh sepenuhnya milik Flowbite adalah overlay murni presentasional — dropdown, tooltip, drawer filter.
2. **Semua modul UI wajib idempoten dan di-*init* ulang setelah Livewire memperbarui DOM.** Flowbite mengikat diri ke DOM saat halaman dimuat, sementara Livewire mengganti DOM di tengah jalan. `resources/js/app.js` hari ini memanggil initializer tepat sekali saat impor; ia berubah menjadi satu registry `initializeUi()` yang dipanggil saat DOM siap **dan** pada event pembaruan Livewire. Idempotensi adalah syarat tertulis setiap modul, bukan kebiasaan.

### Aturan penulisan komponen

Konvensi yang sudah hidup di `resources/views/components/ui/` dinaikkan status menjadi aturan:

- `@props` dengan default eksplisit; `$attributes->class([...])`/`merge()` selalu diteruskan agar pemanggil bisa menimpa.
- Slot bernama untuk area komposisi (`$actions`, `$footer`), bukan prop berisi HTML.
- Varian diungkapkan sebagai **prop semantik domain** — `tone="hijau|kuning|merah|na"` mengikuti ADR-0010 — bukan nama warna Tailwind. Warna tidak boleh menjadi satu-satunya pembeda status.
- **View tidak boleh mengoper kelas utility mentah sebagai nilai prop.** Ini aturan yang paling mudah dilanggar dan paling mahal: begitu utility bocor lewat prop, `x-ui.*` berhenti menjadi API dan kembali menjadi pembungkus kosong, dan seluruh alasan memasang fasad ini gugur.

Helper varian bergaya CVA ditolak — abstraksi untuk masalah yang belum kita punya.

### Gelombang adopsi

Tujuh tiket, berurutan, masing-masing satu PR yang tidak pernah meninggalkan halaman dalam keadaan setengah bermigrasi:

| Gelombang | Isi |
| --- | --- |
| G0 | Dependency, `@theme`, refaktor App shell, 12 komponen inti, registry `initializeUi()`, katalog komponen |
| G1 | Dashboard Mitra — **pilot** |
| G2 | Command Center |
| G3 | Project Control Room |
| G4 | Portfolio Cockpit |
| G5 | Golden path Master Data |
| G6 | Golden path Warehouse/Surat Jalan |

Set inti G0: `page`, `page-header`, `panel/card`, `badge`, `button`, `table` + `table-state` (loading/empty/error), `form-field` (label, error, `aria-invalid`, `aria-describedby`), `search`, `pagination`, `modal`, `drawer`, `icon`. Chart dan metric-card **tidak** masuk G0 — bentuknya baru jujur ketika berhadapan dengan Dashboard Mitra sungguhan di G1, dan merancangnya lebih awal berarti menebak.

Layer `ui-*` lama dipangkas per gelombang: kelas `ui-*` hanya boleh tersisa di view yang belum bermigrasi, dan setiap gelombang wajib menghapus baris CSS yang sudah tidak terpakai. Saat G6 selesai, sisanya nol. Mempertahankan nama kelas lama dengan isi `@apply` ditolak: ia melestarikan indirection yang justru menjadi beban perawatan.

**G1 adalah gerbang.** Di sanalah token, spacing, dan varian komponen benar-benar terkunci. G2–G6 tidak dibuka sebelum hasil G1 disetujui manusia; kalau salah dan baru ketahuan di G4, biaya perbaikannya berlipat.

### Bukti dan penjagaan

Setiap gelombang melampirkan bukti sesuai ADR-0017 (kontrak bukti quality gate), ditambah checklist yang **ditulis sekali di sini** dan dirujuk oleh tiap tiket:

- responsive pada 320, 375, 768, 1024, dan 1440 px;
- navigasi keyboard, focus terlihat, asosiasi label/error, `aria-invalid`/`aria-describedby`, focus trap dan restore focus pada overlay, target sentuh sekitar 44 px;
- matriks state #147 per komponen: `default`, `hover` bila relevan, `focus`, `disabled`, `loading`, `validation error`, `success`, `empty`, `error`, serta `authorized`/`not authorized` bila relevan;
- angka bundle setelah build, mentah dan gzip, CSS dan JS terpisah.

Regression dijaga **tanpa menambah tooling browser**. Playwright/Dusk adalah keputusan infrastruktur tersendiri yang tidak diminta peta ini. Yang benar-benar bisa diregresi secara diam-diam adalah semantik — fokus, `aria-*`, dan menu yang tunduk Izin Aksi — dan itu bisa diuji tanpa browser dengan memperluas Feature test yang sudah ada (`ApplicationShellNavigationTest` dan kerabatnya). Soal "enak dilihat atau tidak", manusia sudah menjadi jurinya sejak #148 dan tetap menjadi juri lewat screenshot per gelombang.

**Katalog komponen** dikirim sebagai bagian G0: satu route yang hanya hidup saat `APP_ENV=local`, menampilkan tiap komponen pada seluruh state-nya, tanpa menyentuh query, data domain, atau authorization. Ia adalah tempat menguji state seperti `loading` dan `disabled` yang sulit dipentaskan di halaman nyata, sekaligus sumber screenshot untuk persetujuan manusia. Gerbang `local` bersifat mutlak — tanpa itu ia berubah menjadi pintu belakang.

### Budget bundle: G0 adalah step change yang diakui

Baseline sebelum ADR ini: `app.js` 71 KB dan `app.css` 21 KB (mentah, hasil build pada `f60ed57`). Budget "≤25%" dari #147 karenanya hanya menyediakan sekitar 18 KB tambahan untuk JS — jauh di bawah biaya Flowbite JS, bahkan dengan impor selektif. Konflik ini ditulis di sini alih-alih ditemukan di tengah G0.

#147 sendiri menyediakan jalannya: kenaikan di atas budget diizinkan **dengan alasan terdokumentasi**. Maka G0 diperlakukan sebagai *step change* sekali jalan. Tiket G0 mencatat angka aktual sebelum dan sesudah — mentah dan gzip, CSS dan JS terpisah — lalu baseline di-reset ke hasil G0. Budget ≤25% berlaku **kumulatif terhadap baseline baru itu** untuk G1–G6, dan ditegakkan mesin: `scripts/verify-production-assets.mjs` diperluas agar build gagal bila budget terlewat. Budget yang tidak dieksekusi mesin akan dilanggar diam-diam pada gelombang ketiga.

Budget per gelombang ditolak: izin tumbuh 25% enam kali berturut-turut berarti hampir empat kali lipat.

### Pintu keluar

Bila hasil G1 ditolak manusia dua kali, atau baseline aksesibilitas maupun budget tidak tercapai, **G2–G6 tidak dibuka** dan pilihan fondasi ditinjau ulang lewat tiket keputusan baru — bukan ditambal di dalam gelombang.

Syarat teknis yang membuat pintu itu tetap ada: **G0 harus dapat di-revert sebagai satu PR bersih** yang tidak mencampur perubahan domain, authorization, query, atau read model apa pun. Keputusan ini menyentuh 40+ view; satu-satunya cara membuatnya murah untuk salah adalah memastikan jalan pulangnya masih terbuka saat G1 berakhir.

## Konsekuensi

- Ada dependency frontend pihak ketiga di repositori untuk pertama kalinya, dengan konsekuensi rutin: pembaruan versi adalah commit tersendiri, dan setiap kenaikan versi harus melewati checklist gelombang yang sama.
- Selama G1–G6 berjalan, dua bahasa visual hidup berdampingan di aplikasi. Itu disengaja dan berbatas waktu; aturan "tidak ada halaman setengah-migrasi" menjaga agar ketidakrapian itu terjadi antar halaman, bukan di dalam satu halaman.
- Chart yang dirender server-side membatasi interaktivitas visualisasi. Bila kelak dibutuhkan, itu keputusan terpisah dengan bukti kebutuhan, bukan tambahan diam-diam.
- Prototype #148 tetap tinggal di branch `prototype/issue-148-ui-foundations` sebagai rujukan visual sampai G4 selesai, lalu dihapus. Ia tidak pernah di-merge; route `/prototype/...` di dalamnya tidak boleh masuk `main`.

## Tidak diputuskan di sini

Hierarki informasi Dashboard Mitra, Command Center, Project Control Room, dan Portfolio Cockpit; aturan authorization, RLS, query, read model, dan istilah domain — semuanya tidak berubah oleh ADR ini. Dashboard tetap *read-only* dan hanya menautkan ke modul pemilik data.
