# ADR-0024 — Daftar Request Material adalah prefill, bukan plafon Surat Jalan

Status: diterima · Tanggal: 2026-08-22 · Tiket: [#102](https://github.com/thcjatim-bit/project-monitoring-system/issues/102)

## Konteks

Form Terbitkan Surat Jalan menjadi *request-driven*: operator memilih Request Material yang sudah disetujui, dan item ter-*prefill* dari daftar request. Tim gudang meminta tetap bisa menambah atau mengurangi item yang dikirim **meskipun tidak sesuai dengan daftar request**.

Kode hari ini melarangnya. `SuratJalanService::ensureRequestQuantitiesAvailable()` melempar `ValidationException` dalam dua kondisi: qty satu material melebihi sisa yang belum terkirim, dan material yang dikirim sama sekali tidak ada di daftar request. Begitu operator memilih sebuah request, dia terkunci pada daftar dan plafon qty request itu.

Larangan itu **tidak pernah menjadi keputusan terdokumentasi**. ADR-0005 hanya menyatakan `surat_jalans.material_request_id` nullable dan satu request bisa dipenuhi beberapa Surat Jalan; ia diam soal apakah Surat Jalan wajib patuh pada daftar. Aturan ketat itu adalah pilihan kode.

Kenyataan lapangan yang melatarinya: gudang asal kehabisan material yang diminta dan mengirim substitusi, atau menitipkan barang tambahan pada kendaraan yang memang sudah berangkat ke gudang tujuan itu. Hari ini keduanya memaksa operator melepas kaitan ke request — dan begitu kaitan lepas, pemenuhan request berhenti terhitung.

## Keputusan

### Daftar request adalah prefill, bukan plafon

`ensureRequestQuantitiesAvailable()` berhenti menjadi **validator** dan menjadi **klasifikator**: ia tidak lagi melempar `ValidationException`, ia menandai baris mana yang menyimpang. Material di luar daftar dan qty melebihi permintaan keduanya diperbolehkan.

Rem tidak hilang, hanya berpindah. Yang tetap menahan adalah rem yang mewakili kenyataan barang, bukan kenyataan dokumen: saldo gudang asal (`ensureOrdinaryAvailability`), dan `createItem()` untuk SN dan drum. Sebuah Surat Jalan tetap mustahil mengirim barang yang tidak ada.

Alternatif yang ditolak — melonggarkan hanya salah satu (boleh material asing tapi qty tetap berplafon, atau sebaliknya) — menghasilkan aturan yang tidak bisa dijelaskan ke operator: kalau dia boleh mengirim material yang sama sekali tidak diminta, melarangnya mengirim 12 dari 10 yang diminta tidak punya dasar.

### Penyimpangan diukur terhadap sisa

**Baris Menyimpang** = material di luar daftar request, atau qty melebihi `diminta − sudah terkirim`. Ini basis yang memang sudah dihitung `ensureRequestQuantitiesAvailable()`; hanya perlakuannya yang berubah.

Mengirim **kurang** dari sisa bukan penyimpangan — itu kirim bertahap, alasan `terpenuhi_sebagian` ada di ADR-0005. Surat Jalan tanpa Request Material tidak punya pembanding, jadi tidak punya baris menyimpang sama sekali.

### Satu Surat Jalan, satu request — termasuk penyimpangannya

Seluruh Surat Jalan tetap memakai `material_request_id` yang dipilih, termasuk baris menyimpang. Tidak ada FK request per baris; satu Surat Jalan tidak bisa setengah punya request.

Alternatif yang ditolak — melepas `material_request_id` begitu ada penyimpangan — merusak justru kasus paling umum. "Kirim 10 yang diminta + 2 tambahan" akan membuat request tampak tidak pernah terpenuhi, padahal 10 itu benar-benar sampai.

`updateMaterialRequestStatus()` tidak perlu diubah. Ia melakukan iterasi pada daftar request, jadi material asing sudah otomatis diabaikan dan kelebihan qty sudah otomatis terhitung `selesai`. `terpenuhi_sebagian` dan `selesai` tetap dihitung, tidak diketik.

### Jejak dua lapis, bukan satu

Penyimpangan tidak boleh buta saat rekon material.

1. **`catatan` per baris `surat_jalan_items`** — teks bebas, **wajib** untuk baris menyimpang, opsional untuk sisanya. Ditegakkan di server, bukan hanya di form.
2. **Event linimasa `surat_jalan_deviation`** — dicatat **saat terbit**, karena penyimpangan adalah keputusan pengirim, bukan penerima. Metadatanya menyebut material asing dan material yang melebihi sisa.

Dua lapis karena masing-masing punya lubang yang ditutup lapis lain. `recordProjectEvent()` no-op saat `project_id` null, jadi request tanpa Project tidak mendapat event sama sekali — lapis 1 menutup itu. Sebaliknya, teks bebas yang diisi manusia bisa berisi "-", sementara metadata event diisi sistem dan tidak bisa dikosongkan — lapis 2 menutup itu.

Tidak ada daftar pilihan tetap untuk alasan. Kategori yang dipaksakan sekarang akan menebak-nebak alasan yang belum pernah kita baca; teks bebas dulu, kategori menyusul kalau polanya sudah terlihat.

### Amandemen (#107) — hasil klasifikasi ikut tersimpan di baris

Jejak di atas ditulis sebagai dua lapis. Tiket [#107](https://github.com/thcjatim-bit/project-monitoring-system/issues/107) menambah lapis ketiga yang sebenarnya sudah tersirat: **hasil klasifikasi disimpan**, di kolom `jenis_penyimpangan` (nullable, `material_asing` / `qty_melebihi`) pada `surat_jalan_items`.

Alasannya bukan penyajian, melainkan kebenaran dokumen. Klasifikasi diukur terhadap **sisa saat terbit**; Surat Jalan lain yang terbit sesudahnya menggeser "sudah terkirim", jadi menghitung ulang saat render bisa membuat baris yang dulu patuh terbaca menyimpang, dan sebaliknya. Surat Jalan sudah ditandatangani — isinya tidak boleh berubah arti karena kejadian sesudahnya. **Klasifikasi penyimpangan dibekukan saat terbit, tidak pernah dihitung ulang.**

Kolom ini juga yang menegakkan `catatan` wajib: server perlu tahu baris mana yang menyimpang pada saat validasi, dan klasifikator sudah menghitungnya. Enum, bukan boolean, karena kedua jenis itu sudah dibedakan untuk metadata event `surat_jalan_deviation` dan punya arti operasional berbeda saat rekon.

### Peringatan, bukan penghalang

Form menandai baris menyimpang secara visual dan tidak memblokir submit. Penyimpangan di sini adalah alur kerja yang sah, bukan kesalahan — modal konfirmasi akan menjadi refleks-klik dalam seminggu. Tapi diam total juga salah: salah pilih material yang mirip namanya harus tertangkap, dan data request sudah ada di halaman untuk *prefill* sehingga penandaan itu gratis.

### Request yang disubstitusi ditutup, bukan digantung

Kalau gudang mengirim Kabel B sebagai pengganti Kabel A, Kabel A tidak akan pernah terkirim dan requestnya menggantung selamanya.

Ini bukan keputusan baru: ADR-0005 sudah menetapkan `ditutup` = "THC — sisa tidak jadi dikirim". Statusnya ada di check constraint dan label UI, tetapi tidak ada kode yang pernah menuliskannya. Kelonggaran ini membuat implementasinya mendesak.

Aturannya: aksi THC di belakang izin `approve_material_request`, sah dari `disetujui` dan `terpenuhi_sebagian` saja (`diajukan` sudah punya `ditolak`), alasan wajib di `decision_note`, dan tidak bisa dibuka kembali — kalau sisanya ternyata masih perlu dikirim, Mitra membuat request baru.

`ditutup` bersifat terminal, jadi `updateMaterialRequestStatus()` tetap perlu satu penjaga: Surat Jalan lama yang baru diterima setelah penutupan tidak boleh menghitung ulang status request menjadi `terpenuhi_sebagian` atau `selesai`. Selain penjaga itu, kesimpulan di atas — bahwa fungsinya tidak perlu diubah untuk baris menyimpang — tetap berlaku.

Admin Mitra tidak boleh menutup requestnya sendiri. Penutupan adalah pengakuan bahwa THC tidak akan mengirim sisanya; itu keputusan pihak pengirim, bukan peminta.

## Konsekuensi

- Pertanyaan "apakah request ini dipenuhi sesuai permintaan?" tidak lagi bisa dijawab dari keberadaan `material_request_id` saja. Jawabannya sekarang ada di baris menyimpang dan event linimasanya.
- Linimasa mendapat percabangan render per-event yang pertama: `surat_jalan_deviation` perlu menyusun kalimat dari metadata, sementara `projects/show.blade.php` hari ini hanya merender `event_key` mentah dan tidak pernah menampilkan metadata. Kecil, tapi ini menjadi pola yang akan diikuti event berikutnya.
- Satu-satunya penjaga yang tersisa antara operator dan Surat Jalan yang salah adalah saldo gudang asal. Salah ketik material tidak lagi ditolak sistem — ia tertangkap oleh penandaan visual dan oleh catatan wajib yang memaksa operator berhenti sejenak, bukan oleh validasi.
- `ditutup` menjadi satu-satunya jalan keluar untuk request yang disubstitusi. Kalau aksinya tidak jadi diimplementasikan, kelonggaran ini justru menambah jumlah request yang menggantung.
