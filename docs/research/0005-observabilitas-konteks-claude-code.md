# Riset: Apakah Pemakaian Konteks Bisa Diamati Agent atau Hook Claude Code

Riset untuk issue [#136](https://github.com/thcjatim-bit/project-monitoring-system/issues/136), bagian dari peta [#135](https://github.com/thcjatim-bit/project-monitoring-system/issues/135) (kontrak anggaran konteks: plafon 140K, target kerja 100K).

Sumber: dokumentasi resmi Claude Code (`code.claude.com/docs`) plus **verifikasi empiris** di mesin ini — payload hook yang benar-benar diterima ditangkap dengan menjalankan sesi `claude -p` memakai `--settings` berisi hook yang menuangkan stdin ke berkas. Versi yang diuji: biner di `~/.local/bin/claude` per 2026-08-23.

## Ringkasan Eksekutif

**Jawaban: BISA diamati, tapi TIDAK di tempat yang bisa menegakkan plafon.**

Angka pemakaian konteks memang ada dan akurat, tapi terpisah dari jalur yang bisa menghentikan agent:

- **Statusline** menerima angka konteks yang lengkap (`context_window.used_percentage`, `total_input_tokens`, `context_window_size`, `exceeds_200k_tokens`) — tapi murni tampilan ke manusia. Exit code-nya diabaikan, dan **secara empiris statusline tidak jalan sama sekali di mode `-p`/headless**, jadi tidak berguna untuk worker autopilot.
- **Tidak ada satu pun hook yang menerima angka token.** Ini dikonfirmasi empiris, bukan sekadar dari dokumentasi: dump stdin `SessionStart`, `UserPromptSubmit`, `PreToolUse`, `PostToolUse`, `Stop`, dan `SessionEnd` tidak memuat field usage apa pun.
- **Tidak ada variabel lingkungan** yang membawa pemakaian *live*. Yang ada hanya knob kontrol (`CLAUDE_CODE_MAX_OUTPUT_TOKENS`, `MAX_THINKING_TOKENS`, ambang auto-compact).
- **`/context` dan `/usage` hanya render ke terminal manusia**, tidak ada padanan terprogram di CLI interaktif.

Yang **bisa** dipakai untuk tindakan otomatis ada tiga, semuanya dengan cacat:

1. **Hook + baca `transcript_path`** — semua hook menerima `transcript_path`, dan berkas `.jsonl` itu memang memuat `usage` per pesan asisten. Ini satu-satunya jalur yang menggabungkan angka token dengan hook yang bisa memblokir. Cacatnya: format transkrip dinyatakan internal dan bisa berubah tiap rilis.
2. **Hook `PreCompact` dengan trigger otomatis** — sinyal implisit bahwa jendela hampir penuh, tanpa angka. Tapi memicu pada ~ambang auto-compact (jauh di atas 140K), jadi terlalu telat untuk kontrak ini.
3. **Agent SDK / `claude -p --output-format json`** — mengembalikan `usage` dan `total_cost_usd`, tapi *setelah* query selesai. Berguna untuk harness di luar sesi, bukan untuk mengerem sesi yang sedang jalan.

**Rekomendasi untuk kontrak #135:** jangan gantungkan plafon 140K/100K pada penegakan otomatis. Jadikan **batas fase sebagai aturan utama** (sudah jadi keputusan awal di #135) dan **proksi yang bisa diamati agent sendiri** sebagai jaring pengaman — lihat bagian 7. Angka token boleh disebut di kontrak sebagai *orientasi*, dengan statusline sebagai alat bantu manusia, bukan sebagai gerbang.

---

## 1. Statusline: angka lengkap, tapi buntu

Statusline dikonfigurasi lewat `statusLine` di `settings.json` dan menerima satu objek JSON di stdin ([docs](https://code.claude.com/docs/en/statusline)). Objek itu memuat blok `context_window`:

```json
{
  "model": { "id": "claude-opus-5", "display_name": "Opus" },
  "context_window": {
    "total_input_tokens": 15500,
    "total_output_tokens": 1200,
    "context_window_size": 200000,
    "used_percentage": 8,
    "remaining_percentage": 92,
    "current_usage": {
      "input_tokens": 8500,
      "output_tokens": 1200,
      "cache_creation_input_tokens": 5000,
      "cache_read_input_tokens": 2000
    }
  },
  "cost": { "total_cost_usd": 0.01234, "total_duration_ms": 45000 },
  "exceeds_200k_tokens": false
}
```

**Bukti tambahan (bukan hanya dokumentasi):**

- Statusline milik user ini, `~/.claude/statusline.ps1`, sudah membaca `$data.context_window.total_input_tokens`, `$data.context_window.used_percentage`, dan `$data.context_window.context_window_size` — jadi field ini benar-benar terkirim pada versi yang terpasang.
- Nama field `context_window`, `used_percentage`, `total_input_tokens`, `context_window_size`, dan `exceeds_200k_tokens` semuanya ditemukan sebagai literal di dalam biner `claude`.

**Batasnya, dan ini yang menentukan:**

- Output statusline **hanya ditampilkan**. Exit code tidak diperiksa, tidak ada kanal `decision`/`block`. Statusline tidak bisa menolak tool call, tidak bisa menghentikan giliran, tidak bisa menyuntik instruksi ke agent.
- **Diverifikasi empiris**: menjalankan `claude -p "say OK" --settings <statusline yang menuangkan stdin ke berkas>` **tidak pernah memanggil** perintah statusline. Statusline adalah komponen UI interaktif. Untuk worker autopilot yang jalan headless, statusline nihil.

Artinya statusline berguna untuk satu hal saja dalam konteks #135: memberi **manusia** pandangan pemakaian konteks saat sesi interaktif berjalan. Ia tidak bisa jadi dasar klausul "sesi wajib berhenti pada 140K".

---

## 2. Hooks: tidak ada satu pun yang menerima angka token

Ini diuji langsung, bukan disimpulkan dari dokumentasi. Setup: sebuah skrip yang menuangkan stdin apa adanya, dipasang ke lima event lewat `--settings`, lalu satu sesi `claude -p` yang memanggil satu tool Bash.

Payload lengkap yang tertangkap (nilai dipendekkan):

| Event | Field yang diterima |
|---|---|
| `SessionStart` | `session_id`, `transcript_path`, `cwd`, `hook_event_name`, `source` |
| `UserPromptSubmit` | `session_id`, `prompt_id`, `transcript_path`, `cwd`, `permission_mode`, `hook_event_name`, `prompt` |
| `PreToolUse` | `session_id`, `prompt_id`, `transcript_path`, `cwd`, `permission_mode`, `effort.level`, `hook_event_name`, `tool_name`, `tool_input`, `tool_use_id` |
| `PostToolUse` | idem `PreToolUse` + `tool_response`, `duration_ms` |
| `Stop` | `session_id`, `prompt_id`, `transcript_path`, `cwd`, `permission_mode`, `effort.level`, `hook_event_name`, `stop_hook_active`, `last_assistant_message`, `background_tasks`, `session_crons` |
| `SessionEnd` | `session_id`, `transcript_path`, `cwd`, `prompt_id`, `hook_event_name`, `reason` |

**Tidak ada** `usage`, `tokens`, `context_window`, atau turunannya di mana pun. `duration_ms` pada `PostToolUse` mengukur waktu eksekusi tool, bukan biaya konteks.

Yang penting: **setiap payload memuat `transcript_path`**. Itulah jembatan ke angka token — lihat bagian 4.

Hook yang bisa memblokir atau mengarahkan agent (exit code 2, atau JSON dengan `decision`/`permissionDecision`/`hookSpecificOutput.additionalContext`) mencakup antara lain `PreToolUse`, `UserPromptSubmit`, `Stop`, `SubagentStop`, dan `PreCompact` ([docs hooks](https://code.claude.com/docs/en/hooks)). Kombinasi "hook yang bisa memblokir" + "`transcript_path`" adalah satu-satunya jalur penegakan yang nyata.

---

## 3. `PreCompact`: sinyal implisit, tapi terlalu telat

`PreCompact` menembak sebelum kompaksi, dan membedakan kompaksi manual dari otomatis. Kompaksi otomatis hanya terjadi ketika jendela mendekati penuh — jadi menembaknya `PreCompact` bertrigger otomatis **adalah** sinyal bahwa konteks hampir habis, meski tanpa angka.

Dua catatan jujur:

- **Nama field trigger belum terverifikasi empiris di mesin ini.** Probe `PreCompact` tidak berhasil menembak (sesi uji terlalu pendek untuk punya sesuatu yang bisa dikompaksi). Dokumentasi menyebut field pembeda manual/otomatis; nama persisnya (`trigger` vs `compact_trigger`) berbeda antar versi dokumentasi. **Siapa pun yang mengimplementasikan ini wajib menuangkan payload `PreCompact` sekali dulu** dan memakai nama yang benar-benar terlihat, bukan menyalin dari dokumen ini.
- **Ambangnya salah untuk kebutuhan kita.** Auto-compact memicu jauh di atas 140K pada jendela 200K. Kontrak #135 ingin sesi berhenti *sebelum* itu. `PreCompact` memberi tahu kita saat sudah kelewat batas, bukan saat mendekatinya. `--autocompact <tokens>` di CLI bisa menurunkan ambang secara paksa, tapi itu mengubah perilaku kompaksi untuk semua sesi, efek sampingnya jauh lebih besar daripada manfaat sinyalnya.

Kesimpulan: `PreCompact` layak dipakai sebagai **alarm terakhir** ("konteks sudah kelewat, tuliskan state durable sekarang"), bukan sebagai penegak plafon 140K.

---

## 4. Berkas transkrip: satu-satunya sumber angka yang bisa dibaca agent sendiri

Setiap hook menerima `transcript_path`, menunjuk ke `~/.claude/projects/<proyek-tersandi>/<session-id>.jsonl`. Agent juga bisa membaca berkas itu sendiri lewat tool Bash/Read.

**Diverifikasi di mesin ini**: baris bertipe `assistant` memuat `message.usage` dengan bentuk berikut.

```json
{
  "input_tokens": 2,
  "cache_creation_input_tokens": 9406,
  "cache_read_input_tokens": 27043,
  "output_tokens": 118,
  "output_tokens_details": { "thinking_tokens": 0 },
  "cache_creation": { "ephemeral_1h_input_tokens": 9406, "ephemeral_5m_input_tokens": 0 }
}
```

Baris tersebut juga memuat `isSidechain` (menandai pesan subagent), `gitBranch`, `cwd`, `sessionId`, `version`, dan `timestamp` — cukup untuk memisahkan pemakaian sesi utama dari pemakaian subagent.

Perkiraan konteks terpakai = `input_tokens + cache_read_input_tokens + cache_creation_input_tokens` pada pesan asisten **terakhir** yang bukan sidechain. Diuji pada transkrip nyata di repo ini dan menghasilkan angka yang masuk akal (ratusan ribu token pada sesi panjang).

**Peringatan yang harus ikut ke kontrak**: dokumentasi resmi menyatakan format entri transkrip bersifat internal dan bisa berubah pada rilis mana pun. Skrip yang mem-parsing-nya adalah ketergantungan rapuh. Kalau dipakai, ia harus **gagal-diam** (kalau parsing gagal, jangan blokir apa pun) supaya rilis Claude Code yang baru tidak melumpuhkan pekerjaan.

### Contoh konfigurasi minimal yang bekerja

Kalau penegakan berbasis token tetap diinginkan, ini bentuk terkecil yang jalan. Diletakkan di `.claude/settings.json` (repo ini belum punya berkas itu; baru ada `.claude/settings.local.json`).

```json
{
  "hooks": {
    "PostToolUse": [
      {
        "matcher": "*",
        "hooks": [
          {
            "type": "command",
            "command": "node .claude/hooks/context-budget.mjs"
          }
        ]
      }
    ]
  }
}
```

Skrip pendampingnya membaca payload dari stdin, mengambil `transcript_path`, menjumlahkan `usage` pesan asisten terakhir, dan — bila melewati ambang — mengembalikan JSON yang menyuntik peringatan ke agent lewat `additionalContext`, bukan memblokir keras:

```js
// .claude/hooks/context-budget.mjs — contoh, bukan implementasi final
import { readFileSync } from "node:fs";

const TARGET = 100_000;
const PLAFON = 140_000;

try {
  const hook = JSON.parse(readFileSync(0, "utf8"));
  const baris = readFileSync(hook.transcript_path, "utf8").split("\n");
  let usage = null;
  for (const b of baris) {
    if (!b.trim()) continue;
    let d;
    try { d = JSON.parse(b); } catch { continue; }
    if (d.type === "assistant" && !d.isSidechain && d.message?.usage) usage = d.message.usage;
  }
  if (!usage) process.exit(0);
  const terpakai =
    (usage.input_tokens ?? 0) +
    (usage.cache_read_input_tokens ?? 0) +
    (usage.cache_creation_input_tokens ?? 0);

  if (terpakai >= PLAFON) {
    console.log(JSON.stringify({
      hookSpecificOutput: {
        hookEventName: "PostToolUse",
        additionalContext:
          `PLAFON KONTEKS TERLEWAT (${terpakai} token). Jangan mulai unit kerja baru. ` +
          `Tuntaskan penulisan state durable ke issue GitHub, lalu berhenti.`,
      },
    }));
  } else if (terpakai >= TARGET) {
    console.log(JSON.stringify({
      hookSpecificOutput: {
        hookEventName: "PostToolUse",
        additionalContext:
          `Target konteks terlewat (${terpakai} token). Laporkan apa yang belum durable.`,
      },
    }));
  }
} catch {
  // Gagal-diam: rilis Claude Code baru boleh mematahkan skrip ini,
  // tapi tidak boleh mematahkan sesi.
}
process.exit(0);
```

**Cacat yang harus disadari sebelum ini dipilih:**

- Menempel di `PostToolUse` berarti skrip Node dijalankan **setiap tool call**. Pada sesi padat itu overhead nyata.
- `additionalContext` adalah imbauan yang disuntik ke konteks — agent bisa mengabaikannya. Penegakan keras (exit 2 pada `Stop`, memaksa agent lanjut, atau memblokir tool) justru berbahaya: memblokir tool call di dekat plafon dapat menghalangi agent menulis state durable, persis kebalikan dari yang diinginkan.
- Ironi yang tidak boleh dilewatkan: menyuntik peringatan ke konteks **menambah** pemakaian konteks.

---

## 5. Variabel lingkungan: tidak ada pemakaian live

Yang tersedia hanya knob kontrol, bukan pembacaan ([docs settings](https://code.claude.com/docs/en/settings)):

| Variabel / setting | Fungsi | Membawa pemakaian live? |
|---|---|---|
| `CLAUDE_CODE_MAX_OUTPUT_TOKENS` | Batas token output per giliran | Tidak |
| `MAX_THINKING_TOKENS` | Batas anggaran extended thinking | Tidak |
| `--autocompact <auto\|tokens>` | Ambang jendela auto-compact (100k–1M) | Tidak |
| `CLAUDE_PROJECT_DIR` | Akar proyek, untuk skrip hook | Tidak |
| `cleanupPeriodDays` | Retensi transkrip (default 30 hari) | Tidak |

`--autocompact` menarik untuk #135 karena bisa menggeser kapan `PreCompact` menembak, tapi seperti dibahas di bagian 3, ia mengubah perilaku kompaksi itu sendiri, bukan sekadar memberi sinyal.

---

## 6. `/context`, `/usage`, dan telemetri

- **`/context`** merender grid berwarna ke terminal yang memperlihatkan apa yang memakan konteks (CLAUDE.md, definisi tool MCP, riwayat, hasil baca berkas). Tidak ada padanan terprogram; agent tidak bisa memanggilnya dan membaca hasilnya.
- **`/usage`** menampilkan biaya, token per model, dan pemakaian rate limit. Sama: teks untuk manusia.
- **OpenTelemetry** (`CLAUDE_CODE_ENABLE_TELEMETRY=1`) mengekspor `claude_code.token.usage` dan `claude_code.cost.usage` ke kolektor OTLP ([docs monitoring](https://code.claude.com/docs/en/monitoring-usage)). Arahnya keluar, ke sistem pemantauan eksternal. Tidak bisa dikonsumsi di dalam sesi.
- **`claude -p --output-format json`** dan **Agent SDK** mengembalikan `usage` serta `total_cost_usd` pada objek hasil ([docs cost tracking](https://code.claude.com/docs/en/agent-sdk/cost-tracking)). Ini nyata dan bisa dipakai — tapi datang **setelah** query selesai. Cocok untuk harness yang membungkus worker (mis. `scripts/issue-autopilot.mjs` bisa mencatat pemakaian per run dan menolak menjadwalkan lanjutan), tidak cocok untuk mengerem sesi yang sedang berjalan.

---

## 7. Proksi yang bisa diamati agent sendiri

Karena jalur token tidak bisa diandalkan sebagai penegak, kontrak #135 sebaiknya bersandar pada proksi yang agent bisa hitung sendiri tanpa berkas eksternal dan tanpa hook. Tiga opsi, dari yang paling direkomendasikan.

### Opsi A — Batas fase murni (rekomendasi)

Aturannya sudah jadi keputusan awal di #135: satu sesi tidak melewati lebih dari satu dari empat batas fase (peta/keputusan → spesifikasi jadi issue → implementasi satu issue → review & deploy).

- **Untung**: nol tooling, nol kerapuhan, dan berkorelasi dengan hal yang sebenarnya kita pedulikan — apakah state sudah durable — bukan dengan proksi biaya. Agent selalu tahu fase apa yang sedang dikerjakannya.
- **Rugi**: tidak menangkap sesi yang mandek di dalam satu fase (mis. debugging yang berputar-putar dan membakar konteks tanpa pernah pindah fase).
- **Cocok untuk**: kedua jenis sesi, interaktif dan worker autopilot.

### Opsi B — Kuota baca berkas + hasil pencarian

Agent menghitung berapa berkas yang sudah dibaca utuh dan berapa pencarian luas yang sudah dijalankan; melewati kuota (mis. ~25 berkas utuh) memicu kewajiban lapor dan berhenti memulai eksplorasi baru.

- **Untung**: pembacaan berkaslah penyumbang konteks terbesar dan paling mudah diaudit; agent bisa menghitungnya tanpa alat apa pun. Menghukum perilaku yang tepat — eksplorasi malas alih-alih pencarian bertarget.
- **Rugi**: satu berkas 3000 baris dan satu berkas 20 baris dihitung sama. Bisa dimainkan tanpa sadar dengan membaca potongan berulang kali.
- **Cocok untuk**: jaring pengaman pendamping Opsi A, khususnya sesi riset/eksplorasi.

### Opsi C — Hitungan tool call

Ambang kasar pada jumlah total tool call dalam satu sesi (mis. 80 = lapor, 120 = tuntaskan dan berhenti).

- **Untung**: paling sederhana untuk dinyatakan dan paling sulit disalahtafsirkan. Berkorelasi longgar dengan pemakaian konteks.
- **Rugi**: korelasinya paling lemah dari ketiganya — 50 `git status` hampir gratis, 50 `Read` berkas besar tidak. Berisiko memberi rasa aman palsu, dan mendorong agent membundel perintah demi menekan hitungan, bukan demi kejelasan.
- **Cocok untuk**: hanya kalau dibutuhkan satu angka tunggal yang gampang diingat.

**Saran komposisi untuk kontrak**: Opsi A sebagai aturan yang mengikat, Opsi B sebagai jaring pengaman, dan angka 100K/140K tetap disebut sebagai orientasi dengan catatan eksplisit bahwa ia **tidak ditegakkan otomatis** — manusia memantaunya lewat statusline, agent memantau proksinya. Kalau nanti terbukti perlu penegakan keras, hook di bagian 4 adalah jalurnya, dengan mata terbuka pada kerapuhannya.

---

## Yang tidak bisa diverifikasi

- Nama persis field trigger pada payload `PreCompact` (`trigger` vs `compact_trigger`) — probe tidak menembak; wajib dituangkan sekali sebelum dipakai.
- Apakah `context_window.current_usage` pada statusline bernilai null sebelum panggilan API pertama.
- Stabilitas skema `message.usage` di transkrip lintas versi — dokumentasi resmi justru menyatakan sebaliknya, bahwa format itu bisa berubah.
