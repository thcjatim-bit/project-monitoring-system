# Riset: Kontrak Reverse Proxy API, TLS, dan Secret Exposure

Riset untuk [Wayfinder: Kesiapan REST API baca dan PostgreSQL BI Gelombang 3 (#54)](https://github.com/thcjatim-bit/project-monitoring-system/issues/54), khususnya [Riset kontrak reverse proxy API, TLS, dan secret exposure (#61)](https://github.com/thcjatim-bit/project-monitoring-system/issues/61).

Tanggal riset: 2026-08-17
Branch: `research/api-network-tls`

Riset ini hanya menghasilkan temuan dan kontrak handoff. Tidak ada kode aplikasi, migrasi, role, credential, konfigurasi server, konfigurasi jaringan, atau perubahan production yang dibuat.

## Kesimpulan eksekutif

Kontrak aman yang dapat diserahkan ke child ticket kesiapan server adalah:

1. Gunakan satu hostname FreeDDNS yang A/AAAA record-nya menunjuk ke jalur publik yang benar. Hanya port 80 dan 443 yang boleh di-forward ke edge API. Port 80 diperlukan untuk HTTP-01 dan redirect; endpoint API hanya boleh dilayani melalui HTTPS di port 443.
2. Caddy menjadi pemilik listener publik API dan certificate automation untuk hostname tersebut, dengan data directory yang persisten dan terlindungi. Caddy dan `certbot` tidak boleh sama-sama menjadi pemilik sertifikat/listener yang sama tanpa kontrak integrasi yang eksplisit.
3. Forwarded headers hanya dipercaya dari proxy yang benar-benar dikelola. Pada jalur langsung Internet → Caddy, biarkan default Caddy yang mengabaikan nilai `X-Forwarded-*` dari client dan mengisi `X-Forwarded-For`, `X-Forwarded-Proto`, serta `X-Forwarded-Host`.
4. Transport API key yang direkomendasikan adalah `Authorization: Bearer <opaque-key>` melalui HTTPS. Jangan menerima key di query string, path, `Referer`, atau body GET. Simpan hanya hash SHA-256; plaintext ditampilkan satu kali melalui kanal delivery yang disetujui.
5. Rate limit wajib berada pada kontrak API. Karena rate-limit handler bukan directive standar Caddy, gunakan Laravel rate limiter atau modul edge yang secara eksplisit dipilih, dipin, diverifikasi, dan dioperasikan melalui jalur infra yang disetujui. Rate limit harus memakai IP yang sudah tervalidasi dan/atau identitas key, bukan `X-Forwarded-For` yang dikirim client.
6. Caddy access log harus mempertahankan redaksi default untuk `Authorization`; aplikasi juga tidak boleh mencatat plaintext key, password, query sensitif, atau request body sensitif. Jika transport akhirnya dipilih sebagai header custom, header itu harus diberi filter redaksi eksplisit.
7. Secret aplikasi dan Caddy masuk melalui environment/service secret di luar checkout dan diatur lewat prosedur infra yang disetujui. Jangan menaruh secret di repository, issue, log, atau output diagnostik. Caddy admin API tidak boleh dipublikasikan.
8. `/up` tetap menjadi health route tanpa API key. Probe eksternal menguji DNS, redirect 80→443, TLS 443, dan status `/up`; Caddy dapat memakai `/up` sebagai active upstream health check dengan ekspektasi `200`.
9. PostgreSQL 5432 tetap LAN-only: tidak ada port-forward/NAT WAN, tidak ada rule firewall WAN yang mengizinkannya, dan `listen_addresses` tidak boleh mencakup interface publik. `pg_hba.conf` dan role read-only adalah pertahanan tambahan, bukan pengganti perimeter jaringan.

Ada dua keputusan implementasi yang belum dikunci oleh repository: siapa pemilik certificate automation (Caddy atau `certbot`) dan siapa pemilik listener publik 80/443 (Caddy atau web server yang sekarang didokumentasikan sebagai Nginx). Keduanya harus diselesaikan sebelum instalasi; jangan menjalankan dua edge yang berebut port atau dua renewal controller yang tidak terkoordinasi.

## Batas sumber dan fakta repository

### Fakta yang sudah disetujui repository

- [ADR-0016](../adr/0016-rest-api-baca-dan-user-postgresql-read-only.md) menetapkan API untuk konsumen internal THC, API key opaque acak yang di-hash SHA-256 dan ditampilkan sekali, serta konteks `mitra_id` nullable atau `app.is_thc`. Dokumen yang sama menetapkan Caddy + FreeDDNS + Let's Encrypt HTTP-01 dengan port-forward 80/443 dan melarang port-forward PostgreSQL 5432.
- ADR yang sama membatasi role BI pada `SELECT` ke view kurasi, bukan tabel mentah. Riset ini tidak mengubah grant tersebut.
- [Dokumen HTTPS via domain](../adr/0017-cara-scan-qr-dan-https-domain.md) menetapkan DNS A record ke IP publik, sertifikat Let's Encrypt dengan auto-renew, dan tanggung jawab renewal di sisi server. Dokumen ini menyebut `certbot`, sehingga ada konflik kepemilikan certificate automation dengan ADR-0016 yang menyebut Caddy.
- [README produksi](../../README.md) menyebut aplikasi berjalan native dengan Nginx + PHP-FPM + PostgreSQL, `.env` dan `storage` sebagai shared state di luar release, `APP_DEBUG=false`, serta larangan menyimpan token, service-account file, dan credential di repository.
- [Kontrak operasi](../../AGENTS.md) menjadikan `pms-dev` authority untuk verifikasi runtime/integrasi, membatasi perubahan infra pada profile `pms-install`, melarang secret di Git, dan mensyaratkan exact-SHA untuk production.
- [Bootstrap Laravel](../../bootstrap/app.php) saat ini mendaftarkan health route `/up`. [Riset deployment sebelumnya](0002-deploy-rollback-backup-google-drive.md) sudah menetapkan probe eksternal ke `/up`, bukti health service/infrastructure, dan secret `.env` di luar checkout/release.

### Inventaris gap yang relevan

Pada parent commit yang diteliti tidak ditemukan `Caddyfile` atau konfigurasi Caddy/FreeDDNS yang tracked. Tidak ditemukan pula konfigurasi explicit trusted proxy atau rate limiter untuk permukaan REST API. Ini adalah gap kesiapan, bukan alasan untuk menambahkan implementasi pada ticket riset.

## Kontrak TLS, DNS, dan port

### Fakta primer

Let's Encrypt HTTP-01 memberikan token kepada ACME client, lalu memeriksa resource pada `http://<domain>/.well-known/acme-challenge/<token>`. Validasi dapat dilakukan beberapa kali dari beberapa vantage point dan HTTP-01 hanya boleh memakai port 80; redirect yang diikuti hanya menuju `http`/`https` pada port 80 atau 443 ([Let's Encrypt — Challenge Types](https://letsencrypt.org/docs/challenge-types/)).

Caddy automatic HTTPS menggunakan hostname publik untuk memperoleh dan memperbarui sertifikat, mengarahkan HTTP ke HTTPS, dan mensyaratkan A/AAAA record yang benar serta akses eksternal ke port 80 dan 443 atau forwarding kedua port tersebut ke Caddy ([Caddy — Automatic HTTPS](https://caddyserver.com/docs/automatic-https)). Caddy mendokumentasikan HTTP challenge pada port 80 dan TLS-ALPN challenge pada port 443; HTTP challenge aktif secara default ([Caddy — Automatic HTTPS, ACME challenges](https://caddyserver.com/docs/automatic-https#http-challenge)).

### Kontrak operasional

- FreeDDNS hanya menjadi hostname; ia bukan pengganti verifikasi jalur jaringan. Sebelum issuance, operator harus membuktikan A/AAAA record yang dilihat publik menunjuk ke alamat publik yang benar. Jika ada AAAA, jalur IPv6-nya juga harus benar-benar melayani 80/443; AAAA stale dapat membuat validasi gagal.
- MikroTik/NAT dan firewall hanya membuka 80/443 menuju Caddy. Port 80 dipakai untuk challenge ACME dan redirect biasa; jangan mengirim request API berisi credential melalui HTTP. Port 443 adalah satu-satunya jalur API.
- Site block API harus menyebut hostname yang benar dan tidak memakai on-demand TLS untuk satu hostname tetap. Caddy sendiri mensyaratkan pembatasan `ask` untuk on-demand TLS agar fitur itu tidak disalahgunakan; fitur tersebut tidak diperlukan untuk satu domain FreeDDNS ([Caddy — Automatic HTTPS, On-Demand TLS](https://caddyserver.com/docs/automatic-https#on-demand-tls)).
- Data directory Caddy harus persisten. Caddy menyimpan sertifikat publik, private key, dan asset certificate-management di storage-nya; permission dan backup-nya harus diperlakukan sebagai secret-bearing infrastructure ([Caddy — Automatic HTTPS, Storage](https://caddyserver.com/docs/automatic-https#storage)).
- Tetapkan satu owner renewal. Pilihan yang paling konsisten dengan ticket ini adalah Caddy mengelola ACME untuk API; `certbot` tidak boleh sekaligus mengelola sertifikat yang sama. Jika organisasi memilih `certbot` karena dokumen HTTPS yang lebih lama, child ticket harus mendokumentasikan bagaimana sertifikat dan reload Caddy diintegrasikan serta siapa yang memonitor renewal.
- Caddy admin API tetap loopback/default atau Unix socket berpermission terbatas. Dokumentasi Caddy menyatakan default admin endpoint adalah `localhost:2019`, dan memperingatkan bahwa endpoint yang dipublikasikan dapat memberi kendali atas server ([Caddy — Global options, `admin`](https://caddyserver.com/docs/caddyfile/options#admin)). Jangan forward port 2019.
- Port ownership wajib dibuktikan sebelum cutover. README saat ini mendokumentasikan Nginx sebagai web server produksi, sedangkan ADR-0016 meminta Caddy untuk API. Caddy harus mendapat listener publik eksklusif, alamat publik terpisah, atau desain front-door yang eksplisit; child ticket tidak boleh mengasumsikan dua service dapat bind 80/443 bersamaan.

### Acceptance evidence

1. DNS A/AAAA dari resolver publik dan dari minimal satu vantage point eksternal.
2. HTTP-01 path dapat dijangkau dari luar selama issuance/renewal.
3. `http://<hostname>/up` hanya redirect ke HTTPS untuk request biasa; tidak ada API key pada URL redirect.
4. `https://<hostname>/up` memiliki sertifikat valid untuk hostname, mengembalikan status health yang disepakati, dan renewal dapat diuji di staging sebelum production ACME. Caddy sendiri memperingatkan agar eksperimen ACME memakai staging untuk menghindari rate limit CA ([Caddy — Automatic HTTPS, Testing](https://caddyserver.com/docs/automatic-https#testing)).
5. Dari jaringan publik, 80/443 mencapai edge API dan 5432/2019 tidak mencapai service publik.

## Kontrak forwarded headers

Caddy `reverse_proxy` secara default meneruskan header incoming dan, sebagai pengecualian, mengisi atau menambah `X-Forwarded-For`, `X-Forwarded-Proto`, dan `X-Forwarded-Host`. Caddy mengabaikan nilai `X-Forwarded-*` yang datang dari client agar tidak mudah dipalsukan. Hanya jika ada proxy tepercaya di depan Caddy, operator boleh mengonfigurasi CIDR `trusted_proxies`; Caddy merekomendasikan konfigurasi global `servers > trusted_proxies` ([Caddy — reverse_proxy, Defaults](https://caddyserver.com/docs/caddyfile/directives/reverse_proxy#defaults)).

Kontrak untuk jalur saat ini adalah:

- Jalur langsung Internet → Caddy tidak mengaktifkan `trusted_proxies` untuk semua jaringan. Nilai client-supplied `X-Forwarded-For`, `X-Forwarded-Proto`, dan `X-Forwarded-Host` tidak boleh menentukan authorization, rate limit, audit identity, atau URL scheme.
- Aplikasi boleh memakai `X-Forwarded-Proto=https` yang ditulis Caddy untuk mengenali request HTTPS, tetapi hanya setelah trust boundary Caddy diuji. Tambahkan integration test yang mengirim header spoofed langsung ke edge dan memastikan aplikasi tidak mempercayai nilai tersebut sebagai client identity.
- Jika suatu hari CDN/proxy ditambahkan, daftar `trusted_proxies` harus berupa CIDR provider yang sempit, didokumentasikan, dan diuji; jangan mengaktifkan `private_ranges` atau wildcard hanya untuk “membuat IP client terbaca”. Perubahan itu menjadi change infra terpisah karena ADR-0016 saat ini memisahkan jalur API dari Cloudflare Tunnel.
- Rate limiter memakai remote IP yang sudah divalidasi oleh edge atau identitas API key yang sudah berhasil di-resolve. Jangan menjadikan header forwarded yang belum dipercaya sebagai key rate limit.
- Caddy meneruskan `Authorization` ke upstream secara normal. Upstream aplikasi harus menerima header itu hanya untuk route API yang dimaksud dan tidak meneruskannya lagi ke service lain.

## Kontrak transport API key

### Fakta repository

ADR-0016 sudah menetapkan key opaque acak, hash SHA-256 sebelum disimpan, one-time display, pembuatan/pencabutan oleh panel THC, dan konteks tenant yang sama dengan sesi login. ADR tersebut belum menetapkan nama header transport secara eksplisit. Header `X-Api-Key` yang ada di repository hanya digunakan oleh client WAHA internal ([`WahaHttpClient`](../../app/Services/WahaHttpClient.php)); itu bukan kontrak public REST API dan tidak boleh disalin tanpa keputusan baru.

### Rekomendasi kontrak

Gunakan:

```http
Authorization: Bearer <opaque-key>
```

RFC 6750 mendefinisikan bearer token sebagai credential yang harus dilindungi dalam storage dan transport, mewajibkan TLS untuk skema tersebut, dan merekomendasikan `Authorization: Bearer` ([RFC 6750, sections 1.2, 2.1](https://www.rfc-editor.org/rfc/rfc6750#section-2.1)). RFC yang sama menyatakan token di URI query berisiko tinggi ikut tercatat di log dan sebaiknya tidak dipakai bila header `Authorization` tersedia ([RFC 6750, section 2.3](https://www.rfc-editor.org/rfc/rfc6750#section-2.3)).

Dengan demikian implementasi harus:

- menerima key hanya pada header `Authorization` dengan skema `Bearer` melalui HTTPS;
- menolak key di query string, path, `Referer`, cookie, atau body GET;
- tidak menerima dua metode transport sekaligus pada request yang sama;
- mengembalikan `401` untuk key hilang/tidak valid/revoked dan tidak membedakan secara berlebihan apakah ID key pernah ada; bila memakai tantangan HTTP standar, gunakan `WWW-Authenticate: Bearer` tanpa membocorkan credential;
- meng-resolve key ke key ID dan scope, lalu mengisi konteks `app.is_thc` atau `app.mitra_id` yang sudah diputuskan ADR-0016 sebelum query read model berjalan;
- menyimpan hash dan metadata audit yang tidak dapat memulihkan plaintext. Plaintext hanya ada pada saat pembuatan/delivery satu kali, tidak di issue, log, repository, database, atau response API;
- tidak menaruh key di JavaScript/browser bila konsumen dapat memanggil API dari server-side service. API internal THC tetap read-only dan tidak menjadi jalur penulisan.

`Authorization` dipilih juga karena Caddy access log secara default meredaksi header `Authorization`, `Proxy-Authorization`, `Cookie`, dan `Set-Cookie`; perilaku ini tidak boleh dimatikan ([Caddy — log directive](https://caddyserver.com/docs/caddyfile/directives/log#log_credentials)).

## Rate limiting dan redaksi log

### Rate limiting

Directive standar Caddy mencantumkan `reverse_proxy` dan handler inti lain, tetapi tidak mencantumkan rate limiting sebagai directive standar ([Caddy — Caddyfile Directives](https://caddyserver.com/docs/caddyfile/directives)). Dokumentasi module registry Caddy menandai module `http.handlers.rate_limit` sebagai non-standard dan bukan bagian dari Caddy; non-standard modules tidak secara resmi dipelihara atau didukung oleh project Caddy ([Caddy — module registry, rate_limit](https://caddyserver.com/docs/modules/http.handlers.rate_limit)).

Karena itu, default handoff yang paling kecil risikonya adalah rate limiting di Laravel pada route group API. Laravel menyediakan `RateLimiter::for`, segmentasi dengan `by`, middleware `throttle`, dan response `429` saat limit terlampaui ([Laravel 12 — Rate Limiting](https://laravel.com/docs/12.x/routing#rate-limiting)). Child implementation ticket harus memilih angka dan window berdasarkan pola konsumen, tetapi minimal harus mencakup:

- budget per IP untuk request tanpa credential atau credential invalid, menggunakan IP edge yang tervalidasi;
- budget per API key ID setelah authentication berhasil, sehingga satu key yang bocor tidak dapat melakukan scraping tanpa batas;
- response `429` dengan header retry metadata yang tidak memuat key atau password;
- batas request/concurrency/timeout pada endpoint yang mahal, bila query read model memerlukannya;
- health probe `/up` dikecualikan atau memakai budget monitoring tersendiri;
- bila dipilih edge module, module, versi build Caddy, policy, storage, dan rollback harus menjadi bagian dari approved infra profile. Jangan menambahkan plugin rate limit secara ad hoc di server.

### Redaksi log

Kontrak log adalah defense in depth:

- Pertahankan redaksi default Caddy dan jangan mengaktifkan `log_credentials`. Untuk header custom apa pun, gunakan `format filter` untuk menghapus/meredaksi header tersebut; Caddy mendokumentasikan filter untuk header dan query serta contoh penghapusan header ([Caddy — log directive](https://caddyserver.com/docs/caddyfile/directives/log)).
- Karena query string hampir selalu masuk access log, larangan key di URL tetap wajib meskipun operator merasa sudah meredaksi log.
- Log aplikasi hanya boleh memuat request ID, route, status, latency, key ID/fingerprint satu arah yang tidak dapat dipakai sebagai credential, dan error category. Jangan log header Authorization, raw request, response payload, password, atau SQL binding sensitif.
- Error response production harus tetap generik. README dan Laravel deployment docs mewajibkan `APP_DEBUG=false` di production karena debug dapat mengekspos konfigurasi sensitif ([Laravel 12 — Debug Mode](https://laravel.com/docs/12.x/deployment#debug-mode)).
- Acceptance test harus membuat request dengan marker credential sintetis yang tidak valid, kemudian memeriksa access log Caddy dan application log tanpa mencetak marker mentah. Test evidence sendiri tidak boleh menempelkan marker/key ke issue.

## Secret injection dan certificate storage

Repository sudah menetapkan pola `.env`/shared state di luar checkout dan melarang service-account file, token, password, private key, serta production secret masuk Git ([README produksi](../../README.md); [AGENTS.md](../../AGENTS.md)). Laravel juga mendokumentasikan bahwa `.env` tidak boleh di-commit karena credential sensitif dapat terbuka bila repository diakses pihak lain ([Laravel 12 — Environment File Security](https://laravel.com/docs/12.x/configuration#environment-file-security)).

Caddyfile mendukung environment-variable substitution dan dokumentasi Caddy menyebut systemd service override sebagai tempat untuk mendefinisikannya ([Caddy — Caddyfile environment variables](https://caddyserver.com/docs/caddyfile/concepts#environment-variables)). Kontrak handoff:

- secret API/database dan nilai private tidak ditulis ke Caddyfile, source tree, issue body/comment, atau artifact log;
- aplikasi membaca secret melalui `.env`/environment service yang berada di luar release dan dikelola sesuai profile operasi yang disetujui;
- Caddy hanya menerima nilai yang memang perlu untuk konfigurasi; hostname/upstream non-secret tidak perlu diperlakukan seperti credential, sedangkan private key certificate dan environment values tetap dibatasi permission-nya;
- certificate storage Caddy bersifat persistent, root/service-owned, dan tidak dipublikasikan sebagai backup biasa;
- jangan memakai opsi diagnostik yang mencetak environment ke output evidence. Dokumentasi Caddy secara eksplisit menyebut `--environ` mencetak environment variables saat startup ([Caddy — Automatic HTTPS, Storage](https://caddyserver.com/docs/automatic-https#storage)); opsi itu tidak boleh dipakai pada log/chat/ticket;
- proses provisioning menggunakan `sudo -n /usr/local/sbin/pms-install <approved-profile>` sesuai [AGENTS.md](../../AGENTS.md), bukan `sudo` arbitrary atau edit manual production;
- bila key baru dibuat oleh panel THC, delivery plaintext dilakukan satu kali melalui kanal yang diverifikasi. Riset ini tidak membuat atau menampilkan credential apa pun.

## Health check dan bukti cutover

Laravel memiliki health route built-in yang mengembalikan `200` bila aplikasi berhasil boot dan `500` bila boot gagal; route dapat memicu `DiagnosingHealth` untuk pemeriksaan tambahan seperti database/cache ([Laravel 12 — The Health Route](https://laravel.com/docs/12.x/deployment#the-health-route)). Repository saat ini sudah memakai `/up` di `bootstrap/app.php`.

Caddy menyediakan active upstream health check melalui `health_uri`, `health_interval`, `health_timeout`, `health_method`, dan `health_status`; default health status adalah `200` ([Caddy — reverse_proxy, Active health checks](https://caddyserver.com/docs/caddyfile/directives/reverse_proxy#active-health-checks)). Kontrak health:

- `GET /up` tidak memerlukan API key dan hanya mengembalikan status minimal; jangan memasukkan data bisnis, credential, versi detail, atau exception trace ke response publik;
- monitor eksternal menguji `https://<hostname>/up` untuk memverifikasi DNS, TLS, edge, upstream, dan aplikasi end-to-end; monitor tidak menyimpan atau mengirim API key;
- Caddy boleh memakai upstream `/up` dengan expected `200`; bila `/up` diperluas untuk mengecek database, listener health harus tetap tidak membocorkan detail error;
- implementer mencatat status Caddy, certificate expiry/renewal, port listener, upstream health, response `/up`, dan log redaction sebagai evidence. Evidence mengikuti format command/check, environment, result, timestamp, dan SHA dari [ADR kontrak quality gate](../adr/0017-kontrak-bukti-quality-gate-deployment.md);
- deployment/infra ticket juga menguji boundary authorization: missing key dan invalid key `401`, valid key read-only sesuai scope, endpoint write tidak tersedia, serta `/up` tetap sehat tanpa credential.

## PostgreSQL tetap LAN-only

ADR-0016 melarang port-forward 5432 dan membatasi role BI pada view kurasi. PostgreSQL sendiri menjelaskan bahwa `listen_addresses` menentukan interface yang menerima koneksi TCP/IP, nilai `*` berarti semua interface, dan port default adalah 5432. Dokumentasi yang sama membedakan kontrol interface dari client authentication ([PostgreSQL — Connections and Authentication](https://www.postgresql.org/docs/current/runtime-config-connection.html)).

Kontrak operasionalnya:

- tidak ada dst-nat/port-forward WAN, firewall allow WAN, tunnel public, atau reverse-proxy route menuju TCP 5432;
- PostgreSQL hanya listen pada loopback dan/atau interface LAN yang benar-benar dibutuhkan BI; jangan memakai `*`, `0.0.0.0`, `::`, atau alamat public WAN untuk kebutuhan ini;
- `pg_hba.conf` membatasi database, role read-only, dan CIDR client BI yang disetujui. HBA membatasi authentication setelah koneksi mencapai PostgreSQL; ia tidak menggantikan NAT/firewall/listen-address boundary. Bila koneksi melewati jaringan yang tidak sepenuhnya dipercaya, gunakan metode host SSL/SCRAM sesuai kontrak operasi ([PostgreSQL — Encryption Options](https://www.postgresql.org/docs/current/encryption-options.html));
- Caddy reverse proxy hanya mengarah ke HTTP application upstream, bukan ke PostgreSQL. API key HTTP tidak pernah ditukar menjadi credential PostgreSQL di edge;
- acceptance test dari jaringan publik membuktikan TCP 5432 tidak reachable, termasuk IPv6. Acceptance test dari jaringan LAN yang diizinkan membuktikan BI dapat membaca view dengan role yang tepat. Tidak ada password/connection string dalam evidence;
- perubahan `listen_addresses`, `pg_hba.conf`, firewall, NAT, atau role harus melalui child implementation/operations ticket dan diverifikasi di `pms-dev` terlebih dahulu. Tidak ada perubahan tersebut pada ticket #61.

## Handoff checklist untuk child readiness ticket

Child ticket yang mengambil temuan ini harus menyelesaikan, secara berurutan:

1. **Port ownership** — pilih Caddy sebagai public edge eksklusif atau desain IP/front-door yang setara; dokumentasikan bagaimana Nginx yang ada tidak berebut 80/443.
2. **Certificate ownership** — pilih Caddy ACME atau `certbot`; pilih satu owner, gunakan staging saat uji, persistenkan storage, dan buktikan renewal.
3. **DNS/NAT** — verifikasi FreeDDNS A/AAAA, forward hanya 80/443 ke edge, dan bukti 5432/2019 tidak publik.
4. **Application seam** — tetapkan route prefix, `Authorization: Bearer`, `401`/`WWW-Authenticate`, scope key, trust boundary forwarded headers, dan rate-limit identity.
5. **Secret/log controls** — environment injection di luar checkout, one-time key delivery, Caddy default redaction, application redaction, `APP_DEBUG=false`, dan larangan diagnostic output yang mencetak environment.
6. **Health and evidence** — `/up` tanpa key, upstream health, TLS/DNS/redirect checks, invalid/valid key checks, log-redaction check, dan public/LAN PostgreSQL reachability checks.
7. **Approved operations** — instalasi/reload lewat profile dan workflow yang diizinkan; verifikasi `pms-dev`, review, commit/push, exact SHA, lalu production hanya bila ticket implementasi memang mencakup deployment dan seluruh gate terpenuhi.

## Hal yang masih harus diputuskan, bukan diasumsikan

- hostname FreeDDNS final dan apakah jalur IPv6 sengaja didukung;
- owner certificate automation dan owner 80/443;
- angka/window rate limit per IP dan per key ID, termasuk kebutuhan distributed limiter;
- apakah `/up` hanya boot check atau juga DB/cache check;
- profile `pms-install` dan permission model untuk Caddy service, Caddy data directory, serta application `.env`;
- exact API route surface dan response contract dari #52 setelah dependency Portfolio selesai.

Temuan di atas cukup untuk menutup pertanyaan riset #61 dan menjadi input child readiness ticket, tetapi belum menjadi izin untuk membuat role, memasang Caddy, membuka port, atau menulis API.

## Sumber primer

### Repository

- [ADR-0016 — REST API baca dan user PostgreSQL read-only](../adr/0016-rest-api-baca-dan-user-postgresql-read-only.md)
- [HTTPS via domain dan Let's Encrypt](../adr/0017-cara-scan-qr-dan-https-domain.md)
- [ADR kontrak bukti quality gate](../adr/0017-kontrak-bukti-quality-gate-deployment.md)
- [AGENTS.md — autonomous development and production operations](../../AGENTS.md)
- [README.md — production and secret handling](../../README.md)
- [bootstrap/app.php — `/up`](../../bootstrap/app.php)
- [Riset deploy/rollback/backup](0002-deploy-rollback-backup-google-drive.md)

### External primary sources

- [Caddy — Automatic HTTPS](https://caddyserver.com/docs/automatic-https)
- [Caddy — reverse_proxy directive](https://caddyserver.com/docs/caddyfile/directives/reverse_proxy)
- [Caddy — log directive](https://caddyserver.com/docs/caddyfile/directives/log)
- [Caddy — Global options](https://caddyserver.com/docs/caddyfile/options)
- [Caddy — Caddyfile concepts and environment variables](https://caddyserver.com/docs/caddyfile/concepts)
- [Caddy — Caddyfile directives](https://caddyserver.com/docs/caddyfile/directives)
- [Caddy — rate_limit module registry entry](https://caddyserver.com/docs/modules/http.handlers.rate_limit)
- [Let's Encrypt — Challenge Types](https://letsencrypt.org/docs/challenge-types/)
- [RFC 6750 — Bearer Token Usage](https://www.rfc-editor.org/rfc/rfc6750)
- [Laravel 12 — Deployment](https://laravel.com/docs/12.x/deployment)
- [Laravel 12 — Routing and rate limiting](https://laravel.com/docs/12.x/routing#rate-limiting)
- [Laravel 12 — Configuration and environment file security](https://laravel.com/docs/12.x/configuration#environment-file-security)
- [PostgreSQL — Connections and Authentication](https://www.postgresql.org/docs/current/runtime-config-connection.html)
- [PostgreSQL — Encryption Options](https://www.postgresql.org/docs/current/encryption-options.html)
