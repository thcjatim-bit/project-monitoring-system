# 17. Cara Pindai QR dan HTTPS via Domain (Supersede ADR-0009 #6)

Date: 2026-08-13

## Status

Accepted. Menggantikan ADR-0009 Keputusan #6 (Migrasi HTTPS & Domain — Cloudflare Tunnel).

## Konteks

Riset QR (Issue #12) menemukan: scan QR dari **dalam web** (kamera via `getUserMedia`) diblokir browser selama server diakses lewat IP publik + HTTP tanpa HTTPS (bukan Secure Context). Kamera bawaan HP tetap bisa membuka link QR tanpa HTTPS, asal QR berisi skema `http://` lengkap.

THC memilih tombol "Scan QR" terintegrasi di dalam aplikasi web, bukan mengandalkan kamera bawaan HP — sehingga server butuh HTTPS (Secure Context).

ADR-0009 Keputusan #6 sebelumnya mengunci Cloudflare Tunnel untuk HTTPS. Keputusan itu digantikan di sini: THC ingin kontrol penuh atas domain dan sertifikat sendiri, bukan bergantung pada skema/subdomain pihak ketiga (Cloudflare).

## Keputusan

1. **Scan QR terintegrasi di web**: aplikasi THC punya tombol "Scan QR" yang mengaktifkan kamera lewat `getUserMedia` di dalam halaman web, lalu langsung navigasi ke data (drum/SN/gudang) yang di-scan.
2. **HTTPS via Domain + Let's Encrypt**: THC mendaftarkan/memakai domain sendiri, DNS A/AAAA record menunjuk ke IP publik yang disetujui THC, dan Nginx menjadi public edge untuk PMS. `certbot` (Let's Encrypt) menerbitkan serta memperbarui sertifikat melalui HTTP-01/webroot. Nginx mempertahankan TCP 80 untuk challenge dan redirect HTTP ke HTTPS, serta TCP 443 untuk HTTPS; MikroTik mempertahankan forwarding PMS-owned TCP 80/443 ke edge yang disetujui. Existing operator-approved non-PMS forwarding pada shared public IPv4 tetap berjalan dan berada di luar boundary ADR ini. Upstream Laravel/PHP-FPM tetap privat.
3. **URL QR pakai domain**, bukan IP — `https://domain-thc.com/drum/DRM-00042`, bukan `http://IP/...`.

Kontrak khusus API publik berada di [ADR-0016](0016-rest-api-baca-dan-user-postgresql-read-only.md) dan keputusan [#64](https://github.com/thcjatim-bit/project-monitoring-system/issues/64): hostname API adalah `api.deploythc.web.id`, Nginx + Certbot adalah owner tunggal edge/certificate automation, dan tidak ada Caddy atau owner sertifikat kedua. Penegasan ini menjaga konsistensi dengan keputusan Certbot pada ADR ini tanpa mengubah boundary ingress permukaan aplikasi lain.

## Konsekuensi

- Batasan tetap "tanpa HTTPS" di peta **tidak berlaku lagi** — server sekarang butuh HTTPS aktif dengan sertifikat valid.
- Tiket task baru dibutuhkan: daftar/beli domain, setup DNS A/AAAA record, install & konfigurasi Certbot melalui automation yang disetujui, serta memverifikasi forwarding PMS-owned TCP 80/443 di MikroTik — harus selesai sebelum fitur scan QR terintegrasi bisa jalan. Tiket tersebut tidak boleh menutup atau mengubah forwarding team-owned lainnya.
- Renewal sertifikat Let's Encrypt (tiap ~90 hari) jadi tanggung jawab operasional server THC sendiri (via cron certbot), bukan otomatis dari pihak ketiga seperti Cloudflare.
- ADR-0009 Keputusan #6 dianggap tidak berlaku lagi; poin lain ADR-0009 (SPI, TOC, transit, toleransi kabel, stiker QR, alur deployment) tetap berlaku.
