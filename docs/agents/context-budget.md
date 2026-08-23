# Anggaran Konteks per Sesi

Kontrak yang mengikat setiap sesi agent di repo ini: sesi interaktif, worker
autopilot, dan sesi wayfinder. Tujuannya satu — sesi berakhir dengan keputusan
dan kode yang **durable** di issue, ADR, atau commit, bukan dengan konteks penuh
dan pekerjaan yang hanya hidup di percakapan.

Dua aturan menjaga itu: **batas fase** yang mengikat, dan **kuota unit baca**
sebagai jaring pengaman. Keduanya bisa dihitung agent sendiri. Angka token tidak
bisa — lihat bagian terakhir.

## Batas fase — aturan yang mengikat

Pekerjaan di repo ini melewati empat fase:

1. **Peta dan keputusan** — grilling, domain modeling, wayfinding.
2. **Spesifikasi jadi issue** — keputusan diubah menjadi issue dengan acceptance
   criteria.
3. **Implementasi satu issue** — kode, tes, verifikasi lokal dan `pms-dev`.
4. **Review dan deploy** — `/code-review`, commit, push, smoke produksi.

**Satu sesi tidak melewati lebih dari satu batas fase.** Sesi yang mulai di fase
2 boleh menutup fase 2 dan masuk fase 3, lalu berhenti — ia tidak melanjutkan ke
fase 4. Berhenti berarti menulis state durable, lalu melaporkan fase berikutnya
dan issue-nya.

Fase 3 dibatasi lebih jauh: **satu issue per sesi**. Dua issue kecil yang
"sekalian" adalah dua sesi.

## Kuota unit baca — jaring pengaman

Batas fase tidak melihat sesi yang mandek di dalam satu fase. Kuota unit baca
menangkapnya.

Satu **unit baca** = satu pembacaan yang mengembalikan **≥100 baris** ke
konteks — berkas utuh, potongan besar, atau blok hasil `grep -C`. Satu subagent
= **1 unit**, berapa pun yang ia baca; yang masuk konteks induk hanya
kesimpulannya. Pembacaan kecil dan bertarget tidak dihitung: `sed -n '40,70p'`,
`git status`, hasil pencarian ringkas.

- **20 unit** — laporkan apa yang belum durable, lalu lanjutkan.
- **30 unit** — tuntaskan penulisan durable, lalu berhenti. Tidak memulai unit
  kerja baru.

Tidak ada berhenti keras di angka mana pun: berhenti sebelum state durable
tertulis persis membuang yang ingin diselamatkan.

Angka ini sama untuk semua jenis sesi. Perbedaan antar jenis sesi sudah
ditangkap batas fase.

## Disiplin output tool

Sebagian besar konteks terbakar oleh output tool, bukan oleh percakapan.

- **Sempitkan di shell sebelum membaca.** `grep -n`, `head`, `sed -n`, `--jq`,
  `--stat`, `| head -50`. Baca rentang baris yang dituju, bukan berkas utuh.
- **Diff dan log besar disimpan ke berkas, lalu dibaca sebagiannya.**
  `git diff > /tmp/d.diff` lalu `grep`/`sed` di atasnya.
- **Eksplorasi luas lewat subagent.** Subagent mengembalikan kesimpulan dan
  daftar berkas — bukan isi berkas.
- **Gabungkan perintah independen dalam satu giliran** bila hasilnya memang
  dibutuhkan bersama.

## Akhir sesi

**Default: `/clear` lalu tunjuk issue.** Sesi berikutnya membaca issue dan
memulai dengan konteks bersih. Ini yang dipakai kecuali ada alasan spesifik.

**Sebelum apa pun, tulis ke issue.** Keputusan, temuan, jalan buntu, SHA commit,
langkah berikutnya. State yang tertulis di issue tidak perlu diwariskan.

**Handoff doc hanya untuk konteks yang belum durable dan mahal direkonstruksi** —
misalnya hasil investigasi setengah jalan yang belum berbentuk keputusan.
Handoff yang tebal adalah gejala state yang bocor: kembalikan dulu ke issue.
Gunakan skill handoff yang sudah ada (`/handoff`, `/claude-handoff`,
`paseo-handoff`); repo ini tidak menambah yang baru.

**State handoff hidup sebagai komentar GitHub issue**, bukan berkas temp OS.

**Sesi lanjutan.** Sesi HITL berhenti dan menunggu manusia. Sesi AFK — issue
dengan acceptance criteria yang sudah beku — boleh lanjut otomatis.

## Per jenis sesi

Yang berbeda hanya ke mana state durable ditulis dan batas fase mana yang
berlaku.

| Jenis sesi | State durable ditulis ke | Batas fase khas |
| --- | --- | --- |
| Interaktif | Issue, `CONTEXT.md`, ADR di `docs/adr/` | Bergantung fase yang dimasuki |
| Worker autopilot | Komentar issue yang diklaim, lalu commit | Fase 3 → 4, satu issue; lihat `docs/agents/autopilot.md` |
| Wayfinder | Komentar resolusi tiket, lalu body peta | Fase 1 → 2, satu tiket per sesi |

## Untuk manusia: angka token

Target kerja **100K token**, plafon **140K**. Angka ini **tidak ditegakkan
otomatis** dan agent tidak bisa membacanya — jangan menganggap laporan agent
tentang pemakaian tokennya sendiri sebagai fakta. Yang bisa melihat angkanya
adalah manusia, di statusline.

Angka ini tetap berguna sebagai orientasi: bila sesi rutin melewati 140K
sementara kuota unit baca tidak pernah tersentuh, kuotanya yang salah.

Mengapa tidak ada hook yang menegakkan plafon ini: lihat
`docs/research/0005-observabilitas-konteks-claude-code.md`.
