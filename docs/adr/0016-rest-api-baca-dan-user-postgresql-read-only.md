# ADR-0016 — REST API baca dan user PostgreSQL read-only untuk integrasi

**Status**: Diterima — 2026-08-13
**Konteks tiket**: [#10 Rancangan REST API baca dan user PostgreSQL read-only](https://github.com/thcjatim-bit/project-monitoring-system/issues/10)

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

5. **Jaringan**. REST API dibuka lewat domain FreeDDNS + reverse proxy Caddy (Let's Encrypt HTTP-01) di server, dengan port-forward 80/443 di MikroTik. Ini jalur terpisah dari rencana Cloudflare Tunnel untuk aplikasi utama (ADR-0009) — ADR-0009 tidak berubah maupun digantikan. User PostgreSQL read-only tetap LAN-only permanen; tidak ada port-forward 5432 ke internet dalam skenario apa pun.

6. **Grant PostgreSQL read-only**. Role read-only cuma di-`GRANT SELECT` ke view kurasi per domain (`v_projects`, `v_kurva_s`, `v_stok`, `v_rekon_material`, dst), tidak pernah ke tabel mentah. Tabel buku transaksi append-only (`material_transaksis`, lihat ADR-0003) dan tabel Komentar Internal tidak ter-grant sama sekali ke role ini — pertahanan berlapis meski jaringannya sudah LAN-only.

## Konsekuensi

- Middleware yang men-set `app.mitra_id`/`app.is_thc` dari ADR-0001 diperluas: sumber identitas bisa dari sesi login **atau** dari API key yang divalidasi, keduanya bermuara ke `set_config` yang sama.
- Panel THC butuh UI baru: buat/cabut API key, pilih `mitra_id` (opsional), lihat key sekali saat dibuat.
- Perlu view SQL kurasi per domain sebelum role read-only bisa dipakai BI tool; menambah domain baru ke ekspor berarti menambah view, bukan buka akses ke tabel mentah.
- Server butuh Caddy berjalan berdampingan dengan rencana Cloudflare Tunnel (ADR-0009) — dua jalur masuk berbeda untuk dua permukaan berbeda (app utama vs API), didokumentasikan agar tidak dikira duplikasi yang harus disatukan.

## Alternatif yang ditolak

- **Kredensial Postgres langsung untuk mitra** — ditolak: mitra bisa jalankan query bebas (walau dibatasi RLS/view), permukaan serangan lebih luas daripada REST API yang kontrak responsnya dikendalikan aplikasi.
- **Numpang Cloudflare Tunnel yang sama dengan app utama untuk API** — ditolak untuk sekarang: konsumen API murni internal, LAN+FreeDDNS cukup dan tidak menambah beban ke tunnel yang direncanakan untuk app utama; bisa disatukan nanti tanpa mengubah model data.
