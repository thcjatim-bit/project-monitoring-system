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
2. **HTTPS via Domain + Let's Encrypt**: THC mendaftarkan/memakai domain sendiri, DNS A record menunjuk ke IP publik statis THC, sertifikat diterbitkan `certbot` (Let's Encrypt) dengan auto-renew. MikroTik menambah forward port 443 (di samping forward yang sudah ada).
3. **URL QR pakai domain**, bukan IP — `https://domain-thc.com/drum/DRM-00042`, bukan `http://IP/...`.

## Konsekuensi

- Batasan tetap "tanpa HTTPS" di peta **tidak berlaku lagi** — server sekarang butuh HTTPS aktif dengan sertifikat valid.
- Tiket task baru dibutuhkan: daftar/beli domain, setup DNS A record, install & konfigurasi certbot, buka port 443 di MikroTik — harus selesai sebelum fitur scan QR terintegrasi bisa jalan.
- Renewal sertifikat Let's Encrypt (tiap ~90 hari) jadi tanggung jawab operasional server THC sendiri (via cron certbot), bukan otomatis dari pihak ketiga seperti Cloudflare.
- ADR-0009 Keputusan #6 dianggap tidak berlaku lagi; poin lain ADR-0009 (SPI, TOC, transit, toleransi kabel, stiker QR, alur deployment) tetap berlaku.
