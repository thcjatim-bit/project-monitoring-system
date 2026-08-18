# Riset: Kontrak reverse proxy API, TLS, dan secret exposure

**Status**: Historical research snapshot — 2026-08-17
**Issue**: [#61 — Riset kontrak reverse proxy API, TLS, dan secret exposure](https://github.com/thcjatim-bit/project-monitoring-system/issues/61)
**Tujuan**: Menjawab kontrak resmi dan batas operasional yang saat itu dipertimbangkan untuk mengekspos REST API melalui Caddy/FreeDDNS + Let's Encrypt HTTP-01 pada port 80/443, sementara PostgreSQL tetap LAN-only.
**Batas riset**: Tidak mengubah source aplikasi, server, database, firewall, DNS, Caddy, atau secret.

> **Current deployment amendment (2026-08-18):** This snapshot predates the Nginx + Certbot decision in #64 and the shared-public-IPv4 operator decision for #66. Caddy is historical, and “80/443-only” applies only to the PMS/API edge. Existing operator-approved non-PMS dst-NAT rules on `103.149.15.22` must remain operational. The current PMS/API contract is Nginx + Certbot on 80/443, with PostgreSQL 5432 and 5433 WAN-negative. Do not use this historical research note as permission to remove unrelated public services.

## Jawaban singkat

Kontrak yang dapat diteruskan ke child ticket adalah:

1. FreeDDNS hanya menjadi nama publik. Record A/AAAA harus mengarah ke IP WAN THC dan port-forward MikroTik hanya meneruskan TCP 80 dan 443 ke Caddy. Caddy menjadi satu-satunya public ingress untuk API; upstream aplikasi tetap pada alamat privat/loopback.
2. Caddy memakai automatic HTTPS. Port 80 tetap harus dapat dijangkau dari internet untuk HTTP-01 dan redirect HTTP→HTTPS; port 443 melayani API HTTPS. Jangan menutup port 80 setelah sertifikat terbit karena renewal HTTP-01 juga membutuhkannya.
3. Jalur API menggunakan `Authorization: Bearer <opaque-api-key>` di atas HTTPS. Key tidak boleh dikirim di query string, URL, cookie, atau log. Repository tetap menyimpan hanya SHA-256, menampilkan raw key sekali, dan menyediakan revoke melalui panel THC.
4. Header `X-Forwarded-*` hanya dipercaya dari proxy yang benar-benar dikonfigurasi. Pada topologi langsung internet→Caddy, Caddy harus mengabaikan nilai header kiriman client dan membuat nilai proxy sendiri; Laravel hanya boleh mempercayai alamat internal Caddy dan hostname API yang disetujui.
5. Rate limit harus diterapkan pada endpoint API dan dibuktikan dengan respons `429`; key yang sudah dicabut atau gagal autentikasi tidak boleh membocorkan apakah suatu resource ada. Angka kuota masih merupakan keputusan operasional yang belum ada di repository dan harus ditetapkan child ticket.
6. Access log Caddy harus mempertahankan redaction credential bawaan. Laravel dan konfigurasi log aplikasi tidak boleh mencatat raw `Authorization`, query token, body credential, atau password; log hanya boleh memakai request ID, key ID/hash prefix yang tidak dapat dipakai ulang, status, durasi, dan identitas scope yang perlu untuk audit.
7. Secret runtime berada di environment/service-owned state di luar checkout, dengan permission yang membatasi pembacaan. `APP_DEBUG=false` wajib di produksi. Config Laravel dicache setelah secret tersedia; kode aplikasi membaca `config(...)`, bukan `env(...)` saat request.
8. Health publik adalah `/up` tanpa data diagnostik sensitif: 200 berarti aplikasi berhasil boot, 500 berarti boot gagal. Child ticket harus menguji `/up` melalui HTTPS dari luar dan memisahkan liveness ini dari readiness/database detail bila kelak diperlukan.
9. Tidak ada port-forward, NAT, atau rule public firewall untuk TCP 5432. PostgreSQL boleh menerima koneksi dari jaringan LAN yang disetujui melalui `listen_addresses`/`pg_hba.conf`, tetapi tidak pernah dari WAN; API berkomunikasi ke database melalui jaringan internal saja.

Poin 1, 2, 3, 8, dan 9 mengikuti keputusan repository yang sudah ada atau dokumentasi resmi. Poin 4, 5, 6, dan 7 adalah penajaman operasional yang diperlukan sebelum implementasi karena belum dikunci oleh source repository.

## 1. Kontrak yang sudah diputuskan repository

ADR-0016 menetapkan REST API untuk konsumen internal THC, API key opaque acak yang di-hash SHA-256 dan ditampilkan sekali, scope optional `mitra_id`, serta pengecualian mutlak untuk password hash, Komentar Internal, lampiran PKS mentah, dan binary foto. ADR yang sama menetapkan REST API melalui FreeDDNS + Caddy + Let's Encrypt HTTP-01, port-forward 80/443, dan PostgreSQL read-only tetap LAN-only tanpa port-forward 5432. ([ADR-0016](../adr/0016-rest-api-baca-dan-user-postgresql-read-only.md):12-26)

Isolasi data tidak boleh diserahkan kepada reverse proxy. ADR-0001 menetapkan PostgreSQL RLS sebagai enforcement, role aplikasi `pms_app` tanpa `SUPERUSER`/`BYPASSRLS`, konteks identitas melalui `set_config`, dan default-deny ketika konteks tidak ada. ([ADR-0001](../adr/0001-isolasi-mitra-row-level-security.md):12-43)

ADR-0017 untuk HTTPS QR menggunakan domain, sertifikat Let's Encrypt, dan port-forward 443, tetapi menyebut `certbot`; issue #61 secara spesifik meminta kontrak Caddy. Child ticket harus memilih satu ACME owner yang konsisten untuk API—Caddy automatic HTTPS atau certbot—dan tidak menjalankan dua pemilik sertifikat pada port yang sama. ([ADR-0017 QR/HTTPS](../adr/0017-cara-scan-qr-dan-https-domain.md):17-28)

## 2. Topologi dan TLS

### 2.1 HTTP-01 dan port 80/443

Caddy automatic HTTPS memperoleh dan memperbarui sertifikat, serta membuat redirect HTTP→HTTPS. Dokumentasi Caddy menyebut redirect memakai port 80 dan automatic HTTPS membuat listener port 80 untuk redirect serta penyelesaian ACME HTTP challenge. ([Caddy — Automatic HTTPS](https://caddyserver.com/docs/automatic-https):80-81, 116-126, 173-179)

Let's Encrypt melakukan lookup A/AAAA lalu meminta resource challenge HTTP pada `/.well-known/acme-challenge/<TOKEN>`. HTTP-01 hanya dapat dilakukan pada port 80; redirect challenge hanya diterima menuju HTTP/HTTPS pada port 80 atau 443. ([Let's Encrypt — Challenge Types](https://letsencrypt.org/docs/challenge-types/):10-15)

Implikasi operasional:

- DNS publik harus diuji untuk A dan AAAA. Jika ada AAAA, jalur IPv6 juga harus mencapai Caddy atau record tersebut harus dihapus/diperbaiki.
- MikroTik meneruskan TCP 80 dan 443 ke host Caddy. Port 80 tidak boleh diarahkan hanya ke aplikasi tanpa memastikan path ACME tetap dijawab oleh Caddy.
- HTTP biasa hanya berfungsi sebagai redirect ke HTTPS, kecuali path challenge yang dikelola Caddy. API key tidak boleh pernah dikirim oleh client pada HTTP.
- Sertifikat, renewal timer, Caddy data directory, dan kegagalan ACME harus masuk acceptance evidence. Jangan menyimpulkan “HTTPS siap” hanya dari konfigurasi Caddy.

### 2.2 Upstream aplikasi

README repository saat ini menyatakan produksi berjalan pada Nginx + PHP-FPM dan PostgreSQL native; Caddy belum menjadi konfigurasi yang tracked di repository. ([README.md](../../README.md):23-35) Karena itu child ticket harus mendokumentasikan topologi nyata, misalnya `internet → MikroTik → Caddy :80/:443 → Nginx/PHP-FPM pada loopback`, tanpa membuka upstream atau PostgreSQL ke WAN. Caddy tidak boleh proxy ke `0.0.0.0` sebagai cara pintas.

## 3. Forwarded headers dan host trust

Caddy meneruskan header masuk dengan tiga pengecualian: Caddy membuat atau menambah `X-Forwarded-For`, `X-Forwarded-Proto`, dan `X-Forwarded-Host`; secara default Caddy mengabaikan nilai header tersebut dari request masuk untuk mencegah spoofing. `trusted_proxies` hanya diperlukan bila ada proxy/CDN lain di depan Caddy, dan Caddy merekomendasikan konfigurasi global untuk itu. ([Caddy — reverse_proxy defaults](https://caddyserver.com/docs/caddyfile/directives/reverse_proxy):384-395)

Laravel menjelaskan bahwa aplikasi di belakang TLS-terminating proxy harus mengonfigurasi proxy yang dipercaya dan header proxy yang dipercaya. Laravel juga memperingatkan bahwa `Host` dipakai untuk membuat absolute URL dan menyediakan `TrustHosts` untuk membatasi hostname. ([Laravel 12 — HTTP Requests](https://laravel.com/docs/12.x/requests):1413-1470, 1489-1539)

Kontrak child ticket:

- Topologi langsung internet→Caddy: jangan percaya `X-Forwarded-For`/`X-Forwarded-Proto` dari client; hanya percaya hop internal Caddy yang dikenal oleh Laravel.
- Jika ada proxy tambahan di masa depan, daftar CIDR proxy dan metode parsing client IP harus eksplisit. Jangan memakai trust-all (`*`) tanpa alasan dan bukti topologi.
- Host API harus dibatasi ke hostname FreeDDNS yang disetujui. Request dengan `Host` lain harus ditolak atau tidak dirutekan ke aplikasi.
- Test harus mengirim forwarded header palsu dari luar, memverifikasi IP/HTTPS yang dipakai aplikasi tidak dapat dipilih client, dan memverifikasi URL/resource response memakai host HTTPS yang benar.
- Rate limiting berbasis IP hanya sah setelah rantai proxy dan client IP ini benar; rate limiting berbasis key harus tetap menggunakan identitas key yang tervalidasi.

Current source gap: `bootstrap/app.php` hanya mendaftarkan `web`, `commands`, dan health `/up`, lalu menambahkan `SetTenantDatabaseContext` ke group `web`; belum ada konfigurasi `trustProxies` atau `trustHosts`. ([bootstrap/app.php](../../bootstrap/app.php):13-25) Middleware tenant saat ini mengambil user sesi melalui `$request->user()` dan belum memvalidasi API key. ([SetTenantDatabaseContext.php](../../app/Http/Middleware/SetTenantDatabaseContext.php):10-19)

## 4. API key transport dan failure contract

RFC 6750 menetapkan skema `Authorization: Bearer <token>` dan menyatakan client sebaiknya memakai header tersebut serta resource server wajib mendukungnya. RFC yang sama menyebut token URI berisiko tinggi karena URL sering dicatat dan merekomendasikan agar query-token tidak dipakai bila header tersedia. TLS wajib dipakai untuk melindungi bearer token. ([RFC 6750](https://www.rfc-editor.org/info/rfc6750/):273-298, 345-396, 625-723)

Maka format normatif untuk child ticket adalah:

```http
GET /api/v1/projects HTTP/1.1
Host: <freeddns-host>
Authorization: Bearer <opaque-api-key>
Accept: application/json
```

Aturan minimum:

- Terima raw key hanya dari `Authorization` dengan scheme `Bearer` di HTTPS.
- Tolak key di query string (`?api_key=...`, `?access_token=...`), URL path, cookie, dan body JSON. Body form hanya relevan untuk metode dan konteks yang memenuhi RFC 6750; API baca GET tidak boleh membutuhkannya.
- Response tanpa credential atau credential tidak valid memakai 401 dan challenge `WWW-Authenticate: Bearer`; response tidak membedakan “key tidak ada”, “key dicabut”, dan “scope tidak ditemukan” melalui pesan sensitif.
- Hash incoming key dengan SHA-256 untuk pencocokan terhadap storage, sesuai ADR-0016. Raw key ditampilkan sekali saat dibuat dan tidak pernah dikembalikan lagi.
- Revoke harus efektif pada request berikutnya. Rotasi dilakukan dengan membuat key baru lalu mencabut key lama; client wajib dapat memperbarui secret tanpa memasukkan raw key ke URL atau log.
- Scope `mitra_id = null` berarti THC dan scope berisi mitra tunduk RLS; API controller/resource tidak boleh membuka data yang dikecualikan ADR-0016.

Current source gap: route yang tracked hanya `routes/web.php` dan `routes/console.php`; `routes/web.php` berisi login/session dan route UI yang dilindungi `auth`, bukan surface `/api`. Tidak ada controller/model/migration API key pada inventory saat riset. ([routes/web.php](../../routes/web.php):23-163; [composer.json](../../composer.json):8-14)

## 5. Rate limiting

Laravel menyediakan abstraction rate limiter berbasis cache, dengan key pembatas yang dapat dipilih aplikasi, dan dokumentasi memberi contoh `RateLimiter::attempt` serta jendela waktu/decay. ([Laravel 12 — Rate Limiting](https://laravel.com/docs/12.x/rate-limiting):133-158, 202-231)

Repository belum memiliki rate-limit contract untuk API. Karena tidak ada evidence volume konsumen di issue, ADR, atau source, angka kuota tidak boleh dipresentasikan sebagai fakta. Child ticket harus menetapkan dan menyetujui setidaknya:

- limit authenticated per API key dan limit unauthenticated per trusted client IP;
- apakah limit dihitung per endpoint, per scope, atau global;
- burst/window, status `429`, `Retry-After`, dan body error JSON yang tidak membocorkan data;
- storage limiter yang konsisten untuk semua worker/web process; dan
- test boundary (request tepat di bawah limit, request pertama yang ditolak, reset window, key revoked, serta forged forwarded IP).

Rekomendasi desain: letakkan limiter pada group route API sebelum query domain, gunakan key gabungan `api-key-id + route-group` untuk consumer yang sudah terautentikasi, dan jangan memakai raw key sebagai nama cache/log.

## 6. Log redaction dan observability

Caddy access log secara default meredaksi `Cookie`, `Set-Cookie`, `Authorization`, dan `Proxy-Authorization`; opsi global `log_credentials` dapat menonaktifkan perilaku itu. Caddy juga menyediakan filter log untuk menghapus atau mengganti field sensitif. ([Caddy — log](https://caddyserver.com/docs/caddyfile/directives/log):48-80, 477-515)

Kontrak log:

- Caddy config harus mempertahankan redaction credential default dan tidak mengaktifkan `log_credentials`.
- Query string API key harus ditolak, bukan sekadar dihapus dari log; ini menutup URL log, history, proxy log, dan referrer sebagai jalur bocor.
- Laravel tidak boleh memanggil `Log::*` dengan raw request headers, seluruh request object, raw key, password, database URL, atau webhook secret. Catat hanya request ID, route name, status, duration, outcome auth, key ID/hash prefix non-replayable, dan scope yang diperlukan untuk audit.
- Error response produksi tidak boleh memuat stack trace atau konfigurasi. Laravel mendokumentasikan `APP_DEBUG=false` sebagai kewajiban produksi karena debug aktif dapat mengekspos nilai konfigurasi sensitif. ([Laravel 12 — Configuration](https://laravel.com/docs/12.x/configuration):511-514)
- Acceptance evidence harus memanggil API dengan canary secret yang tidak dipakai produksi, lalu grep seluruh Caddy/app log dan memastikan raw canary tidak muncul. Jangan memakai credential nyata untuk test log.

Current source gap: `config/logging.php` hanya menentukan channel/level/path; tidak ada konfigurasi API-header redaction di aplikasi. Default log level pada contoh adalah `debug`, sedangkan `.env.example` juga memakai `LOG_LEVEL=debug`; production override harus eksplisit. ([config/logging.php](../../config/logging.php):21-83; [.env.example](../../.env.example):18-21)

## 7. Secret injection dan configuration cache

Laravel menyatakan `.env` tidak boleh dimasukkan ke source control karena dapat mengekspos credential, dan setelah `config:cache` file `.env` tidak dimuat pada request/Artisan; kode harus membaca nilai melalui configuration. ([Laravel 12 — Configuration](https://laravel.com/docs/12.x/configuration):185-187, 475-491)

Repository sudah mengabaikan `.env`, `.env.testing`, `.env.production`, dan `.env.backup`. README juga menetapkan `.env` dan `storage` sebagai shared state di luar checkout, serta melarang Service Account, token, dan rclone credential di repository. ([.gitignore](../../.gitignore):7-11; [README.md](../../README.md):23-35) `.env.example` hanya berisi placeholder untuk `APP_KEY`, database password, WAHA key, dan webhook secret. ([.env.example](../../.env.example):1-5, 23-29, 70-76)

Kontrak injection:

- Inject `APP_KEY`, database credentials, API-key pepper bila kelak diputuskan, WAHA secrets, dan secret operasional lain melalui environment/service-owned file atau mekanisme secret manager yang disetujui; jangan lewat Git, Caddyfile yang dibaca umum, command-line argument, issue, atau chat.
- File runtime hanya dapat dibaca user service/deployer yang perlu. Jangan menaruh secret di document root atau checkout release yang dapat diunduh.
- Isi environment lebih dulu, jalankan `php artisan config:cache`, lalu reload PHP-FPM/worker sesuai deployment contract. Setelah cache, gunakan `config(...)`; jangan mengandalkan `env(...)` dari controller/middleware.
- Set `APP_ENV=production`, `APP_DEBUG=false`, dan `APP_URL=https://<freeddns-host>`; periksa cached config sebelum membuka ingress.
- Caddy tidak perlu dan tidak boleh menerima API key aplikasi. Caddy hanya meneruskan header HTTPS; validasi key adalah tanggung jawab aplikasi.

## 8. Health check dan acceptance smoke

Source repository mengonfigurasi Laravel health route `/up` di `bootstrap/app.php`. Laravel mendokumentasikan bahwa route bawaan ini mengembalikan 200 jika aplikasi berhasil boot tanpa exception dan 500 jika boot gagal; route ini ditujukan untuk uptime monitor/load balancer/orchestrator. ([bootstrap/app.php](../../bootstrap/app.php):13-18; [Laravel 12 — Deployment / Health Route](https://laravel.com/docs/12.x/deployment):379-400)

`/up` adalah liveness, bukan bukti seluruh domain API atau database RLS benar. Child ticket harus membuktikan, dari jalur publik:

1. `http://<host>/up` berakhir pada HTTPS dan tidak menampilkan secret.
2. `https://<host>/up` mengembalikan 200 saat aplikasi sehat dan 5xx pada kondisi boot failure yang dapat diuji aman.
3. Sertifikat valid untuk hostname yang dipakai client, renewal/ACME path berhasil, dan tidak ada mixed HTTP API call.
4. Endpoint API tanpa key gagal sesuai contract; endpoint API dengan test key berhasil hanya pada projection yang diizinkan.
5. Bila readiness database diperlukan, buat endpoint/check terpisah dengan output minimal dan access policy eksplisit; jangan memperluas `/up` menjadi dump konfigurasi atau exception database.

## 9. PostgreSQL tetap LAN-only

ADR-0016 secara eksplisit melarang port-forward 5432. Dokumentasi PostgreSQL 16 menyatakan `listen_addresses` menentukan interface TCP yang menerima koneksi, default-nya `localhost`, dan `port` default-nya 5432; `pg_hba.conf` mencocokkan tipe koneksi, alamat client, database, user, dan metode auth, dan koneksi tanpa record yang cocok ditolak. ([ADR-0016](../adr/0016-rest-api-baca-dan-user-postgresql-read-only.md):24-26; [PostgreSQL 16 — Connections and Authentication](https://www.postgresql.org/docs/16/runtime-config-connection.html); [PostgreSQL 16 — pg_hba.conf](https://www.postgresql.org/docs/16/auth-pg-hba-conf.html))

Kontrak child ticket:

- Public NAT/firewall: PMS/API menggunakan TCP 80/443 menuju Nginx; existing operator-approved non-PMS forwards on the shared IPv4 remain outside this boundary. Tidak ada rule TCP 5432 atau 5433 dari WAN, termasuk rule sementara untuk troubleshooting.
- PostgreSQL bind hanya ke loopback dan/atau interface LAN yang dibutuhkan; jangan memakai `listen_addresses='*'` tanpa alasan dan firewall pembatas yang teruji.
- `pg_hba.conf` hanya mengizinkan CIDR LAN/loopback yang dibutuhkan, dengan role/database yang eksplisit dan metode auth aman. Caddy tidak pernah menjadi jalur SQL.
- Uji negatif dari jaringan luar: TCP 5432 tidak connect/refused/filtered. Uji positif hanya dari host/segment LAN yang memang menjadi konsumen database. Simpan output redacted dan timestamp, bukan credential.
- API smoke test harus menunjukkan aplikasi terhubung ke database internal tanpa membuat port database publik.

## 10. Acceptance checklist child ticket

- [ ] DNS A/AAAA FreeDDNS diverifikasi dari resolver publik dan mengarah ke WAN yang benar.
- [ ] Nginx + Certbot menjadi edge PMS/API untuk 80/443; upstream aplikasi hanya privat/loopback, sementara unrelated approved shared-IP services remain unchanged.
- [ ] HTTP-01 issuance dan renewal diuji; port 80 reachable dari internet; HTTP API redirect ke HTTPS.
- [ ] TLS certificate hostname, expiry, renewal timer, dan failure path diverifikasi.
- [ ] Caddy `X-Forwarded-*` policy dan Laravel trusted proxy/host policy diuji dengan forged headers.
- [ ] API key menggunakan `Authorization: Bearer`, TLS-only, SHA-256 storage, display-once, revoke, dan 401/`WWW-Authenticate` contract.
- [ ] Query/path/cookie/body token ditolak dan tidak muncul di access/app log.
- [ ] Rate limit numbers disetujui, route/API-key/IP keys didefinisikan, `429`/`Retry-After` diuji, dan storage limiter cocok dengan multi-process deployment.
- [ ] Caddy credential redaction tetap aktif; app logs tidak memuat raw header, raw key, password, atau secret.
- [ ] Secret runtime berada di luar Git/checkout, permission dibuktikan, config cache diregenerasi, dan `APP_DEBUG=false`.
- [ ] `/up` diuji publik melalui HTTPS; output tetap minimal dan tidak menjadi database diagnostic endpoint.
- [ ] TCP 5432 tidak dipublish melalui MikroTik/firewall; LAN-only positive/negative tests tersimpan.
- [ ] PostgreSQL RLS/API scope/ineligible fields diuji terpisah; reverse proxy bukan pengganti authorization.

## 11. Source gaps dan keputusan yang masih diperlukan

Riset ini menemukan bahwa #61 belum dapat ditutup sebagai implementasi API. Pada commit yang diteliti, repository belum mempunyai `routes/api.php`, API controller/resource, API-key model/migration, API-key middleware, Caddy configuration, atau test public-ingress contract. Route health `/up` ada, tetapi bukan API contract. Source yang ada juga masih mendokumentasikan Nginx + PHP-FPM sebagai production web stack. ([bootstrap/app.php](../../bootstrap/app.php):13-25; [routes/web.php](../../routes/web.php):23-163; [README.md](../../README.md):23-35)

Child ticket harus terlebih dahulu menetapkan:

1. apakah Caddy menggantikan atau berdampingan dengan Nginx;
2. hostname FreeDDNS final dan A/AAAA ownership;
3. API version/path serta response/error schema;
4. kuota rate limit dan retention/access policy log;
5. storage/permission mechanism untuk runtime secret dan Caddy certificate data; dan
6. exact test authority untuk validasi WAN, LAN, MikroTik NAT, ACME renewal, dan PostgreSQL exposure.

Tidak ada source gap di atas yang dibenarkan untuk diisi dengan asumsi diam-diam pada implementation ticket. Semua perubahan server harus mengikuti authority deployment/infrastructure repository dan dibuktikan sebelum API dianggap ready.

## Sumber primer tambahan

- [GitHub issue #61](https://github.com/thcjatim-bit/project-monitoring-system/issues/61)
- [Caddy — Automatic HTTPS](https://caddyserver.com/docs/automatic-https)
- [Caddy — reverse_proxy](https://caddyserver.com/docs/caddyfile/directives/reverse_proxy)
- [Caddy — log](https://caddyserver.com/docs/caddyfile/directives/log)
- [Let's Encrypt — Challenge Types](https://letsencrypt.org/docs/challenge-types/)
- [RFC 6750 — Bearer Token Usage](https://www.rfc-editor.org/info/rfc6750/)
- [Laravel 12 — HTTP Requests](https://laravel.com/docs/12.x/requests)
- [Laravel 12 — Rate Limiting](https://laravel.com/docs/12.x/rate-limiting)
- [Laravel 12 — Configuration](https://laravel.com/docs/12.x/configuration)
- [Laravel 12 — Deployment](https://laravel.com/docs/12.x/deployment)
- [PostgreSQL 16 — Connections and Authentication](https://www.postgresql.org/docs/16/runtime-config-connection.html)
- [PostgreSQL 16 — `pg_hba.conf`](https://www.postgresql.org/docs/16/auth-pg-hba-conf.html)
