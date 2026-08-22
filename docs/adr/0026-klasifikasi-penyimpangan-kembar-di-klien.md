# ADR-0026 — Klasifikasi penyimpangan boleh kembar di klien, dijaga test kontrak

Status: diterima · Tanggal: 2026-08-22 · Tiket: [#128](https://github.com/thcjatim-bit/project-monitoring-system/issues/128)

## Konteks

ADR-0024 menetapkan bahwa form Terbitkan Surat Jalan **menandai baris menyimpang secara visual**. Penandaan itu mendarat di `b47b260`: `markDeviations()` menghitung sendiri baris mana yang `material_asing` dan mana yang `qty_melebihi`, dengan cara yang sengaja meniru `SuratJalanService::classifyRequestDeviations()` persis — per material atas seluruh baris, terhadap `sisa = diminta − sudah terkirim`, dengan toleransi `0,0005` yang sama.

Jadi ada dua klasifikator penyimpangan di repo ini, satu di PHP dan satu di JavaScript, yang harus selalu sampai pada kesimpulan yang sama. Tidak ada mekanisme yang memaksanya.

Kekembaran itu bukan kecelakaan yang tinggal dibersihkan. Ia sudah menjadi **invarian yang dipakai keputusan lain**: ADR-0025 menolak memakai ulang saluran `markRow`/`ui-list__item--deviating` untuk peringatan sisa pecahan justru dengan alasan "memakai ulang saluran itu merusak invarian *layar dan server tak pernah beda kesimpulan*". Kalimat itu bersandar pada aturan yang sampai sekarang belum pernah ada ADR-nya. Ini ADR-nya.

## Keputusan

### Klasifikasi penyimpangan di klien adalah duplikasi permanen yang disengaja

`markDeviations()` **tetap** mereplikasi `classifyRequestDeviations()`. Kami tidak mencabutnya dan tidak menggantinya dengan panggilan ke server.

Alasannya ada di sifat penandaannya: ia harus muncul **seketika saat operator mengetik qty**, dan datanya sudah ada di halaman untuk *prefill* — payload request membawa `sisa` per material. Menukar perhitungan lokal dengan endpoint klasifikasi (walau di-*debounce*) berarti membuat peringatan yang paling butuh seketika justru bergantung pada jaringan, demi menghapus duplikasi yang cakupannya kecil dan berubah sangat jarang.

Alternatif yang ditolak:

- **Endpoint klasifikasi yang dipanggil form.** Satu sumber kebenaran, tapi menambah *round trip* pada form yang selama ini murni klien, dan penandaan menjadi bisa gagal karena alasan yang tidak ada hubungannya dengan penyimpangan.
- **Mencabut penandaan klien seluruhnya.** Ini membatalkan janji ADR-0024 dan mengembalikan keadaan di mana salah pilih material yang mirip namanya baru tertangkap setelah POST ditolak.
- **Membiarkannya kembar tanpa penjaga (keadaan sebelum ADR ini).** Duplikasi tanpa mekanisme yang membuatnya gagal berisik — lihat bagian berikutnya.

### Yang kembar bukan cuma angkanya

Perlu ditulis eksplisit karena mudah diremehkan. Membawa toleransi lewat payload — pekerjaan [#130](https://github.com/thcjatim-bit/project-monitoring-system/issues/130) — menghapus **satu angka ajaib**, bukan kekembarannya. Yang tetap disalin tangan sesudahnya:

- agregasi **per material atas seluruh baris**, bukan per baris;
- basis `sisa = diminta − sudah terkirim`, yang di server lahir dari query `SENT_QUANTITY` dengan filter `surat_jalans.mitra_id` dan `status != 'dibatalkan'`, sementara di klien ia datang jadi dari payload;
- aturan `material_asing` = material tidak ada di daftar request;
- pengecualian baris `hidden`, yang di server tidak punya padanan sama sekali.

Bahaya nyatanya ada di baris kedua: kalau kelak definisi "sudah terkirim" di server bergeser — misalnya ikut memperhitungkan retur — layar akan diam-diam berbeda kesimpulan, dan tidak ada satu pun test yang merah.

### Penjaganya adalah test kontrak lintas bahasa

Duplikasi diterima; **menyimpang diam-diam tidak**. Satu berkas fixture kasus (daftar `sisa` per material, baris yang diketik, klasifikasi yang diharapkan) dibaca oleh test PHP atas `classifyRequestDeviations()` **dan** test JS atas `markDeviations()`. Menambah kasus berarti menambah satu entri, bukan dua test.

Fixture itu bukan test tambahan di samping yang sudah ada — ia satu-satunya tempat kasus klasifikasi ditulis. Konsekuensinya disengaja: mengubah aturan klasifikasi di satu sisi akan membuat sisi lain gagal, dan kegagalan itulah produk ADR ini. Implementasinya menjadi bagian [#130](https://github.com/thcjatim-bit/project-monitoring-system/issues/130).

### "Tidak memblokir submit" berarti klien, bukan server

ADR-0024 memuat dua kalimat yang terbaca bertegangan: `catatan` **wajib** untuk baris menyimpang dan **ditegakkan di server**, tetapi form "menandai baris menyimpang secara visual dan **tidak memblokir submit**".

Yang dimaksud kalimat kedua adalah **klien tidak memblokir**: tidak ada `required` pada field catatan, tidak ada modal konfirmasi, tidak ada tombol yang dimatikan. Server memang menolak baris menyimpang bercatatan kosong lewat `ensureDeviatingLinesAreExplained()`, dan penolakan itu justru isi keputusan ADR-0024 tentang jejak lapis 1 — bukan pelanggaran terhadapnya.

`required` di klien ditolak dengan alasannya sendiri: penyimpangan diputuskan server terhadap sisa saat terbit, jadi `required` di layar akan salah menuduh baris yang sebenarnya patuh.

## Konsekuensi

- Dua tempat harus disunting setiap kali aturan penyimpangan berubah. Itu diterima, dan test kontraknya yang membuat kelalaian itu berisik alih-alih senyap.
- Setiap saluran peringatan baru di form Surat Jalan harus memutuskan lebih dulu apakah ia penyimpangan atau bukan. Peringatan sisa pecahan (ADR-0025) sudah menjadi preseden: bukan penyimpangan, jadi saluran sendiri.
- Penegaknya sudah mendarat lewat [#130](https://github.com/thcjatim-bit/project-monitoring-system/issues/130): `tests/fixtures/klasifikasi-penyimpangan.json` dibaca `SuratJalanDeviationContractTest` di sisi PHP dan blok "kontrak klasifikasi penyimpangan" di `tests/JavaScript/warehouse-material-form.test.js` di sisi klien. Kasus batas menyebut toleransi sebagai faktor, bukan angka, jadi tiap sisi mengalikannya dengan ambang aplikasinya sendiri (`App\Support\QtyTolerance::VALUE`, yang sampai ke klien lewat payload).
