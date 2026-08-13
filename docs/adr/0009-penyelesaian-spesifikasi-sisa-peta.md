# 9. Penyelesaian Spesifikasi Sisa Peta

Date: 2026-08-12

## Status

Accepted. Keputusan #6 (Migrasi HTTPS & Domain) **superseded by ADR-0017** — HTTPS kini via Domain + Let's Encrypt, bukan Cloudflare Tunnel. Poin #1–5, #7–8 tetap berlaku.

## Konteks

Dalam penyelesaian desain arsitektur pada Peta (Issue #1), terdapat beberapa poin spesifikasi yang belum terkunci ("Not yet specified"). Ini mencakup aturan perhitungan kurva S (termasuk SPI, perubahan TOC, dan variation order), batas waktu material transit, toleransi penyusutan kabel, metode HTTPS, bentuk fisik QR, serta alur kerja *deployment* dari *developer* ke server. Keputusan atas poin-poin ini diperlukan agar fase pembangunan (development) per gelombang bisa dijalankan tanpa ambiguitas atau hambatan *design decision*.

## Keputusan

1. **Aturan SPI dan Baseline Kosong**:
   - Jika kumulatif rencana (baseline) masih 0% (seperti sebelum proyek mulai), SPI akan ditampilkan sebagai `N/A`.
   - Distribusi Rencana (bobot) akan dibagikan merata/proporsional ke seluruh hari kalender (termasuk libur) sejak start sampai TOC, sehingga tidak ada pembagian dengan nol selama proyek berjalan.
2. **Perubahan TOC (Target Operation Complete)**:
   - Jika TOC diundur di tengah proyek, kurva rencana lama dibekukan menjadi **Original Baseline**.
   - Sistem menghasilkan **Revised Baseline** yang melar sesuai tanggal TOC baru. 
   - Kinerja (SPI) selanjutnya akan diukur menggunakan Revised Baseline, dengan tampilan grafik yang dapat menunjukkan kedua baseline untuk transparansi.
3. **Variation Order & Revisi Harga PKS**:
   - Jika terdapat penambahan/pengurangan Jasa (RAB Jasa) di tengah jalan, Kurva S akan dihitung ulang secara retrospektif (*recalculated*). Persentase 100% selalu merujuk pada *Grand Total* RAB yang terbaru.
   - Harga PKS yang baru hanya akan berlaku pada penambahan item RAB (amandemen). Item RAB yang sudah ada tetap memakai harga lama yang dibekukan (`project_rab_jasas`).
4. **Barang Transit**:
   - Batas waktu maksimal Surat Jalan berstatus `terbit` (transit) adalah **3 hari kalender**.
   - Jika melebihi batas, akan muncul indikator/badge merah di Dashboard THC, dan sistem akan mengirim notifikasi via WhatsApp kepada petugas pembuat Surat Jalan.
5. **Penyusutan & Toleransi Kabel**:
   - Tidak ada toleransi persentase penyusutan (*loss*) otomatis.
   - Seluruh sisa potongan/kabel di lapangan harus diretur ke gudang. Jika ada selisih, selisih tersebut diinput secara manual sebagai **Waste/Loss** dan memerlukan otorisasi/approval dari pihak THC pada saat rekonsiliasi akhir proyek.
6. **Migrasi HTTPS & Domain**:
   - Saat siap diimplementasikan, sistem akan memanfaatkan **Cloudflare Tunnel**. Server Ubuntu hanya perlu menjalankan *daemon* lokal tanpa perlu membuka *port forward* 80/443 di MikroTik. Sertifikat SSL dan nama domain akan diurus sepenuhnya secara otomatis oleh Cloudflare.
7. **Isi & Tata Letak Stiker QR**:
   - Mempertimbangkan efisiensi, stiker dicetak menggunakan kertas label biasa.
   - Teks pada stiker dibuat minimalis, hanya berisi cetakan kode QR dan ID Barang.
8. **Alur Kerja Developer (Deployment)**:
   - Menggunakan pendekatan *commit build artifact*. *Developer* mem-build *assets* (CSS/JS) secara lokal, lalu hasil *build* (`public/build`) di-*commit* ke repositori Git.
   - Di sisi server, *deployment* cukup dengan menjalankan skrip `sudo /opt/pms/deploy.sh` yang melakukan `git pull`. Tidak perlu ada instalasi Node.js di server produksi.

## Konsekuensi

- Sistem Kurva S memerlukan struktur tabel atau logika *query* yang dapat menampung *Original* dan *Revised Baseline* jika ada revisi TOC, serta kapabilitas *recalculate* jika nilai *Grand Total* RAB berubah.
- Peringatan Surat Jalan memerlukan penjadwalan (*scheduler/cron*) untuk memonitor umur Surat Jalan dan men-*trigger* notifikasi WAHA.
- Otorisasi *Waste/Loss* manual mengharuskan UI tambahan untuk menu rekonsiliasi *closing* proyek yang bisa diakses oleh *role* THC.
- Pemilihan Cloudflare Tunnel sangat meningkatkan keamanan *network* lokal perusahaan dan memudahkan konfigurasi TLS.
- Penggunaan stiker kertas biasa menghemat biaya pengadaan *thermal printer/vinyl*, namun dengan risiko memudar atau rusak lebih cepat di lapangan (dapat dicetak ulang sewaktu-waktu).
- Server produksi dijamin tetap *lean* karena tidak butuh dependensi *frontend build tools*.
