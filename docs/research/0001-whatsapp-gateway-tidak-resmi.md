# Riset: WhatsApp Gateway Tidak Resmi dan Cara Menekan Sesi Putus

Riset untuk issue #11. Konteks server: Ubuntu 22.04.5 LTS, 4 core CPU, 4.8GB RAM total, disk 109GB (sisa 63GB), aplikasi utama Laravel/PHP + PostgreSQL 16 jalan native systemd di mesin yang sama, ~10 user aktif. HTTPS via domain sendiri + Let's Encrypt sudah diputuskan (lihat [ADR-0017](../adr/0017-cara-scan-qr-dan-https-domain.md)).

## Ringkasan Eksekutif

Rekomendasi awal: **WAHA dengan engine NOWEB**, dijalankan via Docker terpisah dari aplikasi utama, dengan session storage di-mount ke volume Docker (Local storage) agar tidak perlu scan ulang QR tiap restart. Alasan:

- NOWEB tidak menjalankan Chromium (beda dari WEBJS/whatsapp-web.js), sehingga jauh lebih hemat RAM — layak untuk server 4.8GB yang sudah dipakai aplikasi Laravel utama.
- WAHA edisi Core (gratis) sudah cukup untuk 1 sesi WhatsApp, sesuai kebutuhan (1 nomor internal).
- WAHA punya webhook `session.status` resmi yang bisa dipakai Laravel untuk tahu otomatis kalau sesi putus, tanpa polling terus-menerus — cocok diintegrasikan ke halaman admin Livewire.
- Risiko blokir nomor **tidak bisa dihilangkan** — ini tetap penggunaan tidak resmi (melanggar ToS WhatsApp) dan laporan GitHub issue menunjukkan ban bisa terjadi bahkan pada pemakaian yang "berhati-hati". Untuk notifikasi internal skala kecil (~10 user yang sudah save kontak nomor gateway, no broadcast), risiko jauh lebih rendah dibanding spam/marketing massal, tapi tetap bukan nol.
- Kalau anggaran memungkinkan dan notifikasi dianggap kritikal (misal alarm SLA), opsi resmi (Fonnte/Wablas untuk cepat, atau WhatsApp Business API resmi untuk zero-risk) tetap layak dipertimbangkan sebagai fallback — biayanya kecil untuk volume puluhan-ratusan pesan/bulan.

---

## 1. Perbandingan WAHA vs Baileys vs whatsapp-web.js

**Arsitektur WAHA**: WAHA (WhatsApp HTTP API, [devlikeapro/waha](https://github.com/devlikeapro/waha), [docs](https://waha.devlike.pro/docs/)) bukan library WhatsApp sendiri — ia REST API wrapper di atas beberapa "engine": **WEBJS** (browser-based, pakai Puppeteer/Chromium — sebenarnya adalah whatsapp-web.js di baliknya), **NOWEB** (WebSocket Node.js langsung tanpa browser), dan **GOWS** (WebSocket Golang, generasi baru, pengganti NOWEB ke depan). Lihat [WAHA docs — Engines](https://waha.devlike.pro/docs/how-to/engines/). WAHA edisi gratis (Core) mendukung 1 sesi WhatsApp dengan engine WEBJS dan NOWEB; WAHA Plus (berbayar via Patreon) menambah multi-sesi, engine GOWS, dan storage lanjutan (Postgres/MongoDB) — lihat halaman engine yang sama dan [WAHA Storages docs](https://waha.devlike.pro/docs/how-to/storages/).

**Cara pasang**:
- **WAHA**: `docker run` satu image, langsung dapat REST API + engine terpilih. Paling cepat setup di antara ketiganya. Docs: [Quick Start](https://waha.devlike.pro/docs/overview/quick-start/).
- **Baileys** ([WhiskeySockets/Baileys](https://github.com/WhiskeySockets/Baileys)): library Node.js/TypeScript, perlu ditulis sendiri sebagai service (socket-based, tanpa browser). Lebih banyak kerja integrasi manual (auth, event handler, dsb) dibanding WAHA, tapi ini justru engine yang dipakai WAHA NOWEB di baliknya.
- **whatsapp-web.js** ([pedroslopez/whatsapp-web.js](https://github.com/pedroslopez/whatsapp-web.js)): library Node.js yang mengontrol WhatsApp Web via Puppeteer (headless Chromium). Ini juga yang jadi basis engine WEBJS di WAHA.

**Konsumsi RAM realistis di server 4 core/4.8GB (dipakai bersama Laravel)**:
- **NOWEB (WAHA/Baileys tanpa browser)**: tidak menjalankan Chromium, jauh lebih hemat CPU dan RAM — dokumentasi WAHA eksplisit menyebut ini keunggulan utama NOWEB dibanding WEBJS ([WAHA Engines](https://waha.devlike.pro/docs/how-to/engines/), [NOWEB engine docs](https://waha.devlike.pro/docs/engines/noweb/)). Tidak ada angka RAM pasti dari WAHA docs, tapi karena tidak ada instance Chromium per sesi, order-of-magnitude realistis untuk 1 sesi jauh di bawah 500MB (mirip proses Node.js biasa).
- **WEBJS (WAHA) / whatsapp-web.js murni**: menjalankan 1 instance Chromium per sesi via Puppeteer. Tidak ada spesifikasi RAM resmi di README whatsapp-web.js, tapi dari laporan komunitas: [panduan deploy Fly.io](https://community.fly.io/t/running-whatsapp-web-js-on-fly-io/18214) merekomendasikan minimal 1024MB RAM (kalau kurang, sering kena OOM kill). GitHub issue soal [multi-akun di server 4 core/8GB](https://github.com/pedroslopez/whatsapp-web.js/issues/75) melaporkan RAM bisa habis total hanya dengan 3 akun berjalan — menunjukkan tiap sesi Chromium bisa makan ratusan MB hingga lebih, dan cenderung naik (memory leak) pada sesi yang lama hidup ([issue #350](https://github.com/pedroslopez/whatsapp-web.js/issues/350), [issue #3459](https://github.com/pedroslopez/whatsapp-web.js/issues/3459)). Baseline sehat per sesi disebut sekitar 200-400MB, tapi bisa naik terus tanpa restart berkala.
- **Kesimpulan untuk server ini**: dengan RAM total 4.8GB dan aplikasi Laravel+PostgreSQL sudah memakai porsi signifikan, engine berbasis Chromium (WEBJS/whatsapp-web.js) berisiko OOM atau membuat aplikasi utama lambat — apalagi kalau ada memory leak jangka panjang. **NOWEB (via WAHA) adalah pilihan paling aman dari sisi resource** untuk 1 sesi WhatsApp internal.

**Kestabilan sesi (drop/reconnect)**:
- Tidak ada satupun dari ketiga proyek yang mengklaim "tidak pernah putus" — semuanya WhatsApp Web tidak resmi, rentan terhadap perubahan protokol WhatsApp, race condition socket, dan tindakan anti-abuse WhatsApp sendiri.
- Baileys punya banyak GitHub issue soal disconnect/reconnect (mis. [463 error investigation](https://github.com/WhiskeySockets/Baileys/issues/2441) — server menganggu koneksi sebagai "reaching out" berlebihan dan menerapkan rate limit berbasis waktu).
- whatsapp-web.js rentan disconnect akibat memory leak pada sesi lama-hidup, mendorong komunitas melakukan restart berkala sebagai mitigasi ([issue #75](https://github.com/pedroslopez/whatsapp-web.js/issues/75)).
- WAHA mewarisi karakteristik reconnect dari engine yang dipakai (NOWEB = karakteristik Baileys, WEBJS = karakteristik whatsapp-web.js), plus menambah lapisan sendiri untuk auto-reconnect dan event `session.status` yang bisa dipantau (lihat bagian 2 dan 6).

Sumber: [WAHA GitHub](https://github.com/devlikeapro/waha), [WAHA Engines docs](https://waha.devlike.pro/docs/how-to/engines/), [Baileys GitHub](https://github.com/WhiskeySockets/Baileys), [whatsapp-web.js GitHub](https://github.com/pedroslopez/whatsapp-web.js).

---

## 2. Penyimpanan sesi login (auth state) agar tidak perlu scan ulang QR

**Baileys**: mekanisme resmi adalah `useMultiFileAuthState()` — menyimpan seluruh auth state (credentials + signal keys) dalam satu folder lokal:

```js
const { state, saveCreds } = await useMultiFileAuthState('auth_info_baileys')
const sock = makeWASocket({ auth: state })
sock.ev.on('creds.update', saveCreds)
```

Docstring resmi di source Baileys sendiri memperingatkan: mekanisme file ini "tidak direkomendasikan untuk production selain bot sederhana" — untuk produksi mereka menyarankan menulis auth state kustom ke SQL/NoSQL DB, dengan `useMultiFileAuthState` sebagai referensi implementasi. ([Baileys README](https://github.com/WhiskeySockets/Baileys/blob/master/README.md))

**WAHA**: dikontrol lewat [Storages docs](https://waha.devlike.pro/docs/how-to/storages/) — untuk sesi bertahan lewat restart container, folder `/app/.sessions` di dalam container **wajib** di-mount ke volume Docker persisten (Local storage, default, sudah teruji untuk produksi termasuk multi-sesi). Tanpa mount ini, semua sesi hilang saat container berhenti dan wajib scan QR ulang tiap restart. Opsi lain: PostgreSQL/MongoDB sebagai session storage (WAHA Plus saja, fitur baru sejak rilis 2025.1 — [changelog](https://dev.to/waha/waha-20251-postgresql-support-gows-engine-and-more-4njk)), tapi ada laporan bug: [Discussion #842](https://github.com/devlikeapro/waha/discussions/842) melaporkan tabel Postgres tetap kosong setelah setting URL koneksi (belum jelas resolusinya saat dilaporkan). Untuk NOWEB spesifik ada juga bug lama [issue #868](https://github.com/devlikeapro/waha/issues/868): store default session tidak otomatis aktif lagi setelah container di-restart meski sudah pernah diaktifkan. Serta laporan [issue #1591](https://github.com/devlikeapro/waha/issues/1591): redeploy/update container WAHA Plus kadang menghapus semua sesi tersimpan.

**Rekomendasi konkret untuk proyek ini**: pakai **Local storage** (mount volume `./.sessions:/app/.sessions`) — ini jalur yang didokumentasikan resmi sebagai "well-tested" dan gratis (tidak butuh WAHA Plus). Hindari Postgres session storage WAHA untuk saat ini mengingat laporan bug di atas, kecuali sudah diverifikasi ulang di versi WAHA terbaru.

**Pemulihan otomatis setelah restart**: begitu volume sesi ter-mount dengan benar, WAHA/Baileys akan mencoba reconnect otomatis memakai credential tersimpan saat proses start — status sesi akan pindah dari `STARTING` ke `WORKING` tanpa perlu scan QR baru, selama WhatsApp di sisi server belum menganggap sesi invalid (logout paksa dari HP, device limit, atau ban).

---

## 3. Praktik menurunkan risiko nomor diblokir

WAHA punya halaman dokumentasi resmi khusus soal ini: **["How to Avoid Blocking"](https://waha.devlike.pro/docs/overview/%EF%B8%8F-how-to-avoid-blocking/)**. Inti panduan resminya:

- Pesan ke nomor yang belum menyimpan kontak gateway berisiko ditandai spam; tertandai spam berulang (disebut kisaran 5-10 kali) bisa berujung banned.
- WhatsApp memantau seluruh pola aktivitas — perilaku harus semirip mungkin manusia (bukan burst besar dalam waktu singkat).
- Percakapan dua arah (bukan cuma kirim satu arah) dan nomor yang sudah tersimpan di kontak penerima memperkuat "skor" akun.
- WAHA menggambarkannya sebagai sistem poin: mulai dari nol (atau negatif kalau pernah kena blacklist sebelumnya); interaksi wajar menambah poin, laporan spam/blokir mengurangi; jatuh di bawah nol → banned.
- Konten spam tetap berisiko banned baik dikirim lewat broadcast list, grup, maupun pesan langsung satu-satu.

**Angka konkret**: WAHA docs sendiri **tidak** memberi angka jeda antar pesan (detik) atau batas harian pasti — panduan resminya bersifat kualitatif ("berperilaku seperti manusia"), bukan kuantitatif. Diskusi komunitas ([WAHA Discussion #442 — "limit messages"](https://github.com/devlikeapro/waha/discussions/442)) juga menyebut tidak ada limit keras yang didokumentasikan proyek; saran praktis yang beredar di komunitas (bukan sumber resmi WAHA) adalah memberi delay acak antar pesan dan tidak mengirim ke ratusan nomor dalam hitungan menit. Karena tidak ada angka resmi, kebijakan aman untuk proyek ini sebaiknya konservatif secara mandiri, misal jeda beberapa detik antar pesan dan hindari pengiriman serentak ke banyak nomor sekaligus — ini kebijakan internal, bukan kutipan dari sumber resmi.

**Opt-in (kirim hanya ke nomor yang sudah pernah chat/save kontak)**: didukung langsung oleh mekanisme "skor" yang dijelaskan WAHA docs di atas — nomor yang sudah menyimpan kontak gateway dan pernah berinteraksi dua arah menambah poin ketahanan akun. Ini konsisten dengan konteks proyek: 10 user internal yang tahu dan menyimpan nomor gateway, jauh lebih aman dibanding broadcast ke nomor asing.

**Nomor dedicated**: WAHA docs tidak eksplisit merekomendasikan nomor khusus, tapi implikasi dari sistem poin di atas (dan praktik umum otomasi WhatsApp tidak resmi) adalah: pakai nomor yang dikhususkan untuk gateway (bukan nomor pribadi harian yang tercampur pola pemakaian manusia acak), supaya lebih mudah dipantau dan risiko kalau di-ban tidak mengganggu komunikasi pribadi siapa pun.

**Realita dari laporan nyata (GitHub issues resmi WAHA/Baileys) — ban tetap terjadi meski hati-hati**:
- [WAHA issue #2068](https://github.com/devlikeapro/waha/issues/2068): akun WhatsApp otomatis banned tak lama setelah sesi dibuat, walau memakai engine GOWS yang diklaim lebih baru/stabil.
- [WAHA issue #1362](https://github.com/devlikeapro/waha/issues/1362): dua nomor berbeda kena ban saat memakai WAHA (engine NOWEB).
- [WAHA issue #1262](https://github.com/devlikeapro/waha/issues/1262): nomor WhatsApp di-ban saat WAHA diintegrasikan dengan AI (auto-reply).
- [WAHA issue #765](https://github.com/devlikeapro/waha/issues/765): akun banned saat mengirim pesan ke grup (NOWEB).
- [Baileys issue #1869](https://github.com/WhiskeySockets/Baileys/issues/1869): laporan "high number of bans" — gelombang ban menimpa bahkan bot yang sudah jalan 3+ tahun tanpa masalah, kehilangan beberapa bot sekaligus dalam waktu seminggu.
- Kasus serupa di [whatsmeow issue #810](https://github.com/tulir/whatsmeow/issues/810) — proyek Go setara Baileys — melaporkan peringatan "account may be at risk" muncul bahkan pada akun yang cuma membalas pesan masuk secara manual/AI, bukan bulk messaging; sebagian pengguna melaporkan Meta Verified pada akun bisnis mengurangi peringatan ini.

Kesimpulan jujur: **panduan resmi WAHA membantu menekan risiko, tapi tidak menjamin nol-ban** — proyek WAHA sendiri mengakui ini adalah API tidak resmi dan risiko blokir tetap ada secara inheren.

---

## 4. Laporan nyata: frekuensi ban untuk notifikasi internal skala kecil (~10 user)

Pencarian di GitHub issues WAHA dan Baileys **tidak menemukan laporan spesifik** yang membedakan secara eksplisit "kasus notifikasi internal skala kecil (~10 user)" dari kasus lain — kebanyakan issue tracker tidak mencantumkan skala pemakaian pelapor secara detail. Yang bisa disimpulkan secara jujur dari sumber yang ada:

- Sebagian laporan ban **tidak berasal dari broadcast masif** — misalnya [WAHA issue #1262](https://github.com/devlikeapro/waha/issues/1262) (integrasi AI, notifikasi/balasan otomatis, bukan blast marketing) dan kasus whatsmeow ([issue #810](https://github.com/tulir/whatsmeow/issues/810)) yang eksplisit menyebut ban terjadi meski hanya membalas pesan masuk manual/AI, bukan bulk messaging — mengindikasikan **volume rendah tidak membuat kebal ban**, meski secara statistik risikonya jauh lebih kecil dibanding broadcast ribuan kontak.
- Argumen "risiko lebih rendah untuk notifikasi internal ke kontak yang sudah dikenal" didukung secara **tidak langsung** oleh mekanisme skor resmi WAHA (bagian 3) — pesan ke nomor yang sudah save kontak dan saling kenal jauh lebih kecil kemungkinan ditandai spam dibanding pesan ke nomor asing/broadcast. Tapi ini kesimpulan logis dari mekanisme yang didokumentasikan, **bukan** statistik langsung "notifikasi internal 10 user = X% aman".
- Tidak ditemukan studi/data resmi kuantitatif (mis. "akun X% aman setelah Y bulan pemakaian notifikasi internal skala kecil") dari WAHA, Baileys, maupun Meta. General consensus komunitas (termasuk dari GitHub issues di atas): **tidak ada pola yang bisa diprediksi** — akun bisa jalan bertahun-tahun tanpa masalah, atau kena ban dalam hitungan hari, tanpa jaminan apapun karena ini di luar kontrak resmi dengan Meta.

**Implikasi untuk proyek ini**: profil pemakaian THC (10 user internal, sudah save kontak nomor gateway, notifikasi bukan broadcast) berada di kategori risiko-lebih-rendah menurut logika mekanisme WAHA, tapi bukan risiko nol — sistem tetap perlu punya fallback (misal notifikasi tetap tampil di web/dashboard, WhatsApp sebagai kanal tambahan bukan satu-satunya) untuk mengantisipasi jika nomor di-ban sewaktu-waktu.

---

## 5. Biaya alternatif resmi (Fonnte, Wablas, WhatsApp Business API resmi)

Harga diambil dari halaman pricing resmi masing-masing per Agustus 2026; harga bisa berubah sewaktu-waktu, cek langsung sumber untuk angka terkini.

**Fonnte** ([fonnte.com](https://fonnte.com/)) — gateway tidak resmi berbayar: paket Free Rp0/bulan (untuk development/uji coba), Lite Rp25.000/bulan, Regular Rp66.000/bulan, Regular Pro Rp110.000/bulan, Master Rp175.000/bulan. Untuk volume puluhan-ratusan pesan/bulan, paket Free atau Lite kemungkinan sudah cukup — tapi tetap gateway tidak resmi, jadi risiko ban di bagian 3-4 tetap berlaku, cek langsung [fonnte.com](https://fonnte.com/) untuk detail limit tiap paket.

**Wablas** ([wablas.com/pricing](https://wablas.com/pricing)) — juga gateway tidak resmi: entry-level mulai **$2/bulan (~Rp22.000/bulan)**, dengan trial gratis 15 hari (pesan trial ada watermark, hilang setelah upgrade paket berbayar). Detail limit pesan per paket perlu dicek langsung di halaman pricing resmi.

**WhatsApp Business Platform resmi (Meta)** — via Meta langsung atau BSP (Business Solution Provider): model harga **per-message** (berubah dari per-conversation sejak 1 Juli 2025), dengan 4 kategori: Marketing, Utility, Authentication, Service. Pesan **Service** (balasan dalam window 24 jam setelah customer chat duluan) **gratis tanpa batas** sejak 1 November 2024 (cap lama 1.000 gratis/bulan sudah dihapus). Utility/Authentication jauh lebih murah dari Marketing (contoh tarif Indonesia: Marketing ~$0.0271, Utility ~$0.0036, Authentication ~$0.0079 per pesan — angka dari agregator pihak ketiga, cek [halaman pricing resmi Meta](https://business.whatsapp.com/products/platform-pricing) untuk tarif final dan terkini). **Catatan penting**: mulai 1 Oktober 2026, Meta berencana mulai mengenakan biaya juga untuk balasan servis dan utility dalam window 24 jam yang selama ini gratis — perlu dipantau ulang sebelum tanggal itu kalau opsi ini dipilih. Karena kebutuhan proyek adalah notifikasi (bukan chat interaktif dua arah dengan pelanggan), pesan kemungkinan besar masuk kategori **Utility** (tarif rendah) — untuk volume puluhan-ratusan pesan/bulan, estimasi biaya resmi Meta kemungkinan hanya beberapa dolar/bulan, jauh di bawah biaya integrasi teknis (harus lewat BSP resmi seperti Meta Cloud API langsung atau provider seperti Qontak, plus proses verifikasi bisnis).

Sumber: [Fonnte](https://fonnte.com/), [Wablas Pricing](https://wablas.com/pricing), [WhatsApp Business Platform Pricing](https://business.whatsapp.com/products/platform-pricing).

---

## 6. Integrasi halaman scan ulang QR WAHA ke Laravel

**Endpoint WAHA relevan** (docs: [Quick Start](https://waha.devlike.pro/docs/overview/quick-start/), [Security](https://waha.devlike.pro/docs/how-to/security/), [Events](https://waha.devlike.pro/docs/how-to/events/)):

- **`GET /api/{session}/auth/qr`** — ambil QR code. Default mengembalikan gambar biner (PNG). Bisa diminta format lain: header `Accept: application/json` → dapat base64 image; parameter `?format=raw` → dapat raw string yang bisa digenerate jadi QR image sendiri di sisi Laravel (misal pakai library QR seperti `simple-qrcode`). QR code pertama berlaku ~60 detik, berikutnya ~20 detik per kode — jadi halaman admin perlu auto-refresh gambar QR tiap beberapa detik selama status sesi masih `SCAN_QR_CODE`.
- **Status sesi**: siklus `STARTING` → `SCAN_QR_CODE` → `WORKING` (dan bisa jatuh ke `STOPPED`/`FAILED`). Bisa dicek via endpoint session info REST, atau lebih efisien lewat webhook di bawah.
- **Webhook `session.status`**: WAHA bisa dikonfigurasi mengirim event ke URL webhook Laravel setiap kali status sesi berubah, contoh payload `{ "event": "session.status", "session": "default", "payload": { "status": "WORKING", ... } }`. Ini menghilangkan kebutuhan polling terus-menerus — Laravel cukup punya route webhook (mis. `POST /webhooks/waha`) yang menyimpan status terbaru ke DB, lalu halaman admin Livewire membaca status dari DB (real-time via Livewire polling ringan atau broadcasting).
- **Autentikasi API**: semua endpoint WAHA (kecuali webhook masuk, yang punya HMAC key sendiri) butuh header `X-Api-Key: <API Key>`. WAHA juga punya **scoped API key** — bisa mint key terbatas untuk satu sesi/satu aksi (misal cuma boleh ambil QR sesi tertentu), berguna kalau endpoint QR mau dipanggil sebagian dari browser admin tanpa expose API key utama.
- **WAHA Plus vs Core**: dashboard/UI bawaan WAHA (halaman web sederhana untuk lihat QR dan kelola sesi) tersedia, tapi fitur penuh (multi-sesi, dsb) ada di WAHA Plus berbayar. Untuk kebutuhan 1 sesi WhatsApp internal, **WAHA Core (gratis) sudah cukup** — tidak perlu WAHA Plus hanya untuk fitur QR.

**Usulan desain integrasi ke Laravel + Livewire** (halaman admin "Status WhatsApp Gateway"):

1. Laravel menyimpan `WAHA_BASE_URL` dan `WAHA_API_KEY` di `.env`, dipanggil lewat HTTP client Laravel (`Http::withHeaders(['X-Api-Key' => ...])`).
2. Route webhook `POST /webhooks/waha` (di luar middleware auth biasa, diverifikasi via HMAC key WAHA) menerima event `session.status` dan menyimpan status terbaru (`WORKING`/`SCAN_QR_CODE`/`STOPPED`/dst) ke tabel kecil di PostgreSQL, misal `whatsapp_session_status`.
3. Komponen Livewire halaman admin membaca status dari tabel tersebut (polling `wire:poll.5s` cukup untuk skala ini, tidak perlu WebSocket/broadcasting tambahan).
4. Kalau status = `SCAN_QR_CODE`: komponen memanggil `GET /api/{session}/auth/qr` (format base64/raw) dan menampilkan gambar QR di halaman, dengan auto-refresh tiap ~15-20 detik mengikuti masa berlaku QR.
5. Tombol "Restart Sesi" di halaman admin memanggil endpoint start/restart sesi WAHA (mis. `POST /api/sessions/{session}/restart` atau start ulang session) supaya admin tidak perlu SSH — cukup klik dari browser (yang sudah HTTPS sesuai ADR-0017, jadi endpoint publik ini aman diakses dari luar).
6. Opsional: kirim notifikasi (email atau tampilan banner di dashboard utama) begitu webhook melaporkan status berubah ke `STOPPED`/`FAILED`, supaya admin tahu perlu rescan tanpa harus buka halaman status secara manual.

Pendekatan ini menghindari kebutuhan SSH sepenuhnya untuk proses scan ulang rutin — SSH hanya dibutuhkan untuk maintenance container WAHA itu sendiri (upgrade image, dsb), bukan operasi harian.

---

## Tabel Perbandingan Biaya Singkat

| Opsi | Jenis | Biaya untuk volume puluhan-ratusan pesan/bulan | Risiko ban | Kompleksitas setup |
|---|---|---|---|---|
| **WAHA/Baileys self-host** | Tidak resmi | Rp0 (hanya resource server sendiri) | Ada, tidak bisa dihilangkan — lihat bagian 3-4 | Sedang (Docker + integrasi webhook Laravel) |
| **Fonnte** | Tidak resmi (SaaS) | Rp0 (Free tier) – Rp25.000/bulan (Lite) | Sama seperti self-host (basis WhatsApp Web juga) | Rendah (tinggal pakai API mereka) |
| **Wablas** | Tidak resmi (SaaS) | ~$2/bulan (~Rp22.000/bulan) entry-level | Sama seperti self-host | Rendah |
| **WhatsApp Business API resmi (Meta/BSP)** | Resmi | Estimasi beberapa dolar/bulan (kategori Utility jauh lebih murah dari Marketing; Service masih gratis sampai proyeksi perubahan Okt 2026) | Tidak ada risiko ban ToS (ini kanal resmi) | Tinggi (verifikasi bisnis Meta, integrasi via BSP/Cloud API) |

Sumber tabel: lihat sitasi di bagian 5 masing-masing opsi.
