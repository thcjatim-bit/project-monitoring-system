# ADR-0027 — Glosarium mengikat nama yang menunjuk konsepnya, di lapisan mana pun

Status: diterima · Tanggal: 2026-08-25 · Tiket: [#165](https://github.com/thcjatim-bit/project-monitoring-system/issues/165)

## Konteks

[#134](https://github.com/thcjatim-bit/project-monitoring-system/issues/134) memperluas aturan glosarium dari "nama tabel, kelas, judul issue, teks UI" ke **nama di kawat** — nama field HTML dan atribut `data-*`. Perluasan itu dirumuskan lewat contohnya, bukan lewat prinsipnya, sehingga ia berhenti persis di batas yang kebetulan disentuh #134.

Batas itu langsung bocor. Di form Terbitkan Surat Jalan, konsep `asal` dieja dua bahasa **dalam satu tag**:

```html
<select name="warehouse_asal_id" data-origin-select required>
```

Dan di `SuratJalan.php`, kolom `warehouse_asal_id` dibaca oleh relasi bernama `origin()`. Ejaan Inggris itu tidak berhenti di internal PHP: `$transfer->origin->kode` muncul di lima Blade view, bersebelahan dengan label UI "Warehouse asal".

Aturan berbasis lapisan ("kawat wajib, internal PHP bebas") harus dibela persis di dua tempat itu — tempat ia paling sulit dibela. Karena itu ADR ini merumuskan ulang aturannya berdasarkan **apa yang ditunjuk sebuah nama**, bukan di lapisan mana nama itu hidup.

Rumusan itu sekaligus menyelesaikan temuan yang dicatat di #165: kata `origin` di repo ini memikul tiga arti yang tidak berhubungan — gudang asal Surat Jalan, path sumber sinkronisasi foto (`rclone`), dan `getRawOriginal()` milik Eloquent. Aturan berbasis lapisan akan menjerat ketiganya dan merusak `ProjectPhotoSyncService`; aturan berbasis rujukan hanya menjerat yang pertama.

## Keputusan

### Aturan: nama yang menunjuk konsep glosarium memakai ejaan glosarium

Berlaku di lapisan mana pun — kolom database, nama kelas dan relasi, kunci payload, nama field HTML, atribut `data-*`, variabel lokal, teks UI. Nama yang menunjuk konsep **di luar** glosarium tidak terjerat sama sekali: path `rclone`, API framework seperti `getRawOriginal()`, dan istilah teknis yang memang bukan istilah domain.

Konsekuensinya untuk jalur Surat Jalan: `origin`/`destination` yang menunjuk **Gudang asal**/**Gudang tujuan** diseragamkan ke `asal`/`tujuan`. Relasi Eloquent menjadi `asal()`/`tujuan()`, bukan `gudangAsal()`/`gudangTujuan()` — CONTEXT.md sudah menetapkan bahwa di seluruh Surat Jalan `asal` berarti gudang asal, dan `sumber` yang dipakai untuk asal-usul baris (#134), jadi prefiks `gudang` membayar biaya di ~15 tempat `->with([...])` untuk menyelesaikan ambiguitas yang sudah tidak ada.

Rename ini **tidak menyentuh migrasi** (kolomnya sudah `warehouse_asal_id`/`warehouse_tujuan_id`) dan **tidak memecah kontrak eksternal**: REST API v1 tidak pernah membuka `origin`/`destination` Surat Jalan. Satu-satunya `original` di `openapi.yaml` adalah Original Baseline, yang justru istilah glosarium.

### Variabel lokal ikut, karena penjaganya adalah grep

`$origin`, `$originWarehouses`, `originSelect` tidak dilihat siapa pun di luar fungsinya, jadi memasukkannya terlihat seperti kerapian belaka. Alasan sebenarnya struktural: penjaga regresi aturan ini adalah **test yang menolak kemunculan `origin`/`destination` pada daftar berkas jalur Surat Jalan**. Grep tidak bisa membedakan variabel lokal dari nama publik. Rename separuh membuat penjaga itu mustahil ditulis, dan tanpa penjaga aturan ini akan kembali sebagai tiket ketiga — #134, lalu #165, lalu berikutnya.

Penjaga sengaja dibuat **berdaftar berkas**, bukan repo-wide ber-allowlist. Repo-wide menuntut allowlist untuk sinkronisasi foto dan Eloquent yang beban rawatnya lebih besar daripada masalah yang dicegah.

### Aturan berlaku ke depan, tidak ditagih surut

Aturan ini tidak mewajibkan penyisiran seluruh repo dalam satu tiket. Ia mengikat kode baru dan kode yang sedang disentuh. Nama lama yang melanggar ditangani sebagai tiket sendiri saat ditemukan — sebagaimana #165 sendiri lahir dari #134.

Contoh yang sudah terlihat dan sengaja **tidak** dimasukkan ke #165: relasi `returnedFrom()` di `SuratJalan.php` menunjuk konsep glosarium **Retur**. Ia jadi tiket turunan: [#167](https://github.com/thcjatim-bit/project-monitoring-system/issues/167).

Yang tidak boleh terjadi sebaliknya: **membuat butir glosarium baru demi membenarkan sebuah rename**. `issuer()`/`receiver()` tidak punya butir glosarium, jadi aturan ini diam soal keduanya. Glosarium tumbuh karena domainnya butuh istilah, bukan karena kode butuh alasan.

## Alternatif yang ditolak

- **Batas "nama di kawat vs nama internal PHP".** Ini rumusan yang ditawarkan #165 sendiri. Ditolak karena batasnya jatuh di tengah `<select name="warehouse_asal_id" data-origin-select>` dan di tengah `SuratJalan.php`, dan karena "kawat" ternyata tidak punya definisi tunggal — payload server→JS (`initial_origin_id`) bukan field HTML, tapi dibaca berdampingan dengan `warehouse_asal_id` di fungsi JS yang sama.
- **Menerima Inggris untuk nama internal PHP dengan alasan tertulis.** Alasan terbaiknya adalah konvensi komunitas Laravel, yang tidak berlaku di repo dengan kolom, status (`terbit`, `diterima`), dan teks UI berbahasa Indonesia.
- **Rename repo-wide sekarang.** Menyapu ~218 kemunculan lintas tiga arti dalam satu tiket akan mengubur keputusan ini di bawah diff-nya, dan merusak jalur sinkronisasi foto yang tidak ada hubungannya dengan glosarium.

## Konsekuensi

- Setiap kata Inggris yang menunjuk konsep glosarium sekarang punya jawaban tunggal, tanpa perlu memutuskan dulu ia "di kawat" atau bukan.
- Pertanyaan yang tersisa bergeser ke tempat yang benar: bukan "lapisan apa ini?", melainkan **"nama ini menunjuk konsep glosarium yang mana?"**. Bila jawabannya tidak ada, aturan ini tidak berlaku.
- Penjaga berdaftar berkas perlu diperbarui saat berkas jalur Surat Jalan bertambah. Kelalaian itu senyap — penjaga hanya menjaga yang terdaftar. Diterima sebagai harga dari tidak punya allowlist.
