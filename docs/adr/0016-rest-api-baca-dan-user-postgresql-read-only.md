# ADR-0016 — REST API baca dan user PostgreSQL read-only untuk integrasi

**Status**: Diterima — 2026-08-13
**Konteks tiket**: [#10 Rancangan REST API baca dan user PostgreSQL read-only](https://github.com/thcjatim-bit/project-monitoring-system/issues/10)

Keputusan owner public edge API diperbarui oleh [#64 — Tentukan owner reverse proxy dan certificate automation API publik Gelombang 3](https://github.com/thcjatim-bit/project-monitoring-system/issues/64) pada 2026-08-17. Pembaruan ini menggantikan pilihan reverse proxy pada poin jaringan; kontrak API key, RLS, data yang dikecualikan, dan secret handling tetap berlaku.

## Konteks

Belum ada sistem konsumen konkret hari ini, tapi peta (#1) minta dua permukaan baca disiapkan agar integrasi masa depan tidak terhambat: REST API (konsumen internal THC — dashboard, bot WA, tool lain) dan user PostgreSQL read-only (BI internal, mis. Metabase/Superset). Keduanya harus tunduk pola isolasi mitra yang sama dengan ADR-0001, dan tidak boleh membocorkan data yang memang tidak untuk keluar sistem.

## Keputusan

1. **Konsumen**. REST API murni untuk konsumen internal THC untuk sekarang — bukan mitra, bukan pihak ketiga eksternal. Tapi model key tetap disiapkan siap-mitra (lihat poin 2) supaya tidak perlu migrasi skema kalau nanti mitra jadi konsumen. User PostgreSQL read-only khusus BI internal THC; kredensial Postgres mentah tidak pernah diberikan ke mitra.

2. **Autentikasi API**. API key opaque acak, di-hash (SHA-256) sebelum disimpan, ditampilkan sekali saat dibuat. Dibuat dan dicabut lewat panel THC (bukan self-service). Setiap key punya kolom `mitra_id` nullable:
   - diisi → request dengan key itu men-set `app.mitra_id` seperti sesi user mitra, tunduk RLS ADR-0001 apa adanya;
   - `null` → men-set `app.is_thc = 'on'`, setara user THC (lihat semua tabel bertenant).

   Tidak ada tabel atau jalur kode terpisah untuk "key mitra" vs "key internal" — satu mekanisme, dibedakan lewat isi kolom.

3. **Data terbuka lewat API**: project (step, status, TOC, baseline), kurva S & SPI, stok, request material, transaksi material, rekon material, harga jasa mitra — semua tunduk isolasi mitra dari poin 2, sama seperti UI biasa.

4. **Data terkecuali mutlak**, terlepas dari scope key: password hash, Komentar Internal, file lampiran PKS mentah, dan binary foto. Foto pekerjaan diwakili sebagai link ke Folder Master Google Drive (lihat ADR-0012), API tidak pernah re-serve file dari disk server.

5. **Jaringan dan public edge API**. REST API tersedia pada `https://api.deploythc.web.id` melalui Nginx + Certbot. Untuk boundary PMS/API, Nginx adalah public edge pada listener TCP 80/443: port 80 dipertahankan untuk ACME HTTP-01 melalui webroot dan redirect HTTP ke HTTPS, sedangkan port 443 melayani API HTTPS. Certbot menjadi owner issuance dan renewal pada jalur yang disetujui. Platform/Network Owner bertanggung jawab atas DNS A/AAAA, forwarding PMS-owned MikroTik TCP 80/443, konfigurasi edge, renewal, dan eskalasi kegagalan. Upstream Laravel/PHP-FPM tetap privat. Header `X-Forwarded-*` dari client tidak dipercaya; edge menulis nilai canonical dan aplikasi hanya mempercayai proxy serta hostname yang di-allowlist. Caddy tidak dipasang dan bukan owner kedua. Jalur API ini tetap terpisah dari boundary ingress aplikasi utama. Ini bukan klaim bahwa shared public IPv4 hanya memiliki listener 80/443: existing operator-approved non-PMS dst-NAT rules remain operational exceptions and are outside this ADR. User PostgreSQL read-only tetap LAN-only permanen; tidak ada NAT, firewall rule, atau port-forward TCP 5432 atau TCP 5433 ke WAN dalam skenario apa pun.

6. **Grant PostgreSQL read-only**. Role read-only cuma di-`GRANT SELECT` ke view kurasi per domain (`v_projects`, `v_kurva_s`, `v_stok`, `v_rekon_material`, dst), tidak pernah ke tabel mentah. Tabel buku transaksi append-only (`material_transaksis`, lihat ADR-0003) dan tabel Komentar Internal tidak ter-grant sama sekali ke role ini — pertahanan berlapis meski jaringannya sudah LAN-only.

## Konsekuensi

- Middleware yang men-set `app.mitra_id`/`app.is_thc` dari ADR-0001 diperluas: sumber identitas bisa dari sesi login **atau** dari API key yang divalidasi, keduanya bermuara ke `set_config` yang sama.
- Panel THC butuh UI baru: buat/cabut API key, pilih `mitra_id` (opsional), lihat key sekali saat dibuat.
- Perlu view SQL kurasi per domain sebelum role read-only bisa dipakai BI tool; menambah domain baru ke ekspor berarti menambah view, bukan buka akses ke tabel mentah.
- Server membutuhkan Nginx sebagai edge API bersama PHP-FPM; Certbot mengelola sertifikat melalui webroot dan renewal. Tidak ada Caddy atau owner sertifikat kedua. Dua permukaan aplikasi utama dan API tetap diperlakukan sebagai boundary ingress terpisah dan tidak boleh disatukan dengan mengubah jalur Cloudflare Tunnel secara diam-diam.

## Alternatif yang ditolak

- **Kredensial Postgres langsung untuk mitra** — ditolak: mitra bisa jalankan query bebas (walau dibatasi RLS/view), permukaan serangan lebih luas daripada REST API yang kontrak responsnya dikendalikan aplikasi.
- **Numpang Cloudflare Tunnel yang sama dengan app utama untuk API** — ditolak untuk sekarang: konsumen API murni internal, LAN+FreeDDNS cukup dan tidak menambah beban ke tunnel yang direncanakan untuk app utama; bisa disatukan nanti tanpa mengubah model data.
- **Caddy sebagai public edge API** — ditolak oleh keputusan #64: Nginx adalah listener publik tunggal dan Certbot adalah owner certificate automation. Caddy-specific topology pada riset #61 menjadi catatan historis; safeguard HTTP-01, forwarded headers, Bearer transport, redaction, `/up`, dan PostgreSQL LAN-only tetap dipakai.

## Referensi keputusan

- [#64 — Tentukan owner reverse proxy dan certificate automation API publik Gelombang 3](https://github.com/thcjatim-bit/project-monitoring-system/issues/64) adalah keputusan authoritative untuk Nginx, Certbot, `api.deploythc.web.id`, listener 80/443, forwarded-header boundary, dan evidence sebelum API dibuka.
- [#61 — Riset kontrak reverse proxy API, TLS, dan secret exposure](https://github.com/thcjatim-bit/project-monitoring-system/issues/61) tetap menjadi sumber safeguard operasional; pilihan Caddy di dalam riset tersebut tidak lagi authoritative setelah #64.
- [ADR-0017](0017-cara-scan-qr-dan-https-domain.md) konsisten pada penggunaan domain dan Certbot, sementara detail public API berada di ADR ini dan #64.
- [#54 — Wayfinder: Kesiapan REST API baca dan PostgreSQL BI Gelombang 3](https://github.com/thcjatim-bit/project-monitoring-system/issues/54) adalah map handoff; ADR ini tidak mengimplementasikan server, database, credential, network, atau deployment.
