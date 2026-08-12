# ADR-0004 — Tiga jenis material dalam satu buku transaksi, dengan tabel identitas terpisah

Status: diterima · Tanggal: 2026-08-12 · Tiket: [#4](https://github.com/thcjatim-bit/project-monitoring-system/issues/4)

## Konteks

Tiga jenis material berperilaku berbeda:

- **Biasa** (tiang, aksesoris) — hanya jumlah. Tidak ada identitas per butir.
- **Ber-SN** (SFP, modem) — tiap butir punya identitas unik, qty selalu 1.
- **Drum kabel** — tiap drum punya identitas *dan* isi yang bisa berkurang sebagian. Potongan yang keluar jadi drum turunan.

Godaan yang harus ditolak: memaksa ketiganya jadi satu bentuk. Memberi material biasa kolom `serial_number` yang selalu kosong, atau menganggap drum sebagai "material biasa bersatuan meter" (identitas drum hilang — tidak bisa lagi menjawab "ada berapa roll").

## Keputusan

**Satu buku transaksi untuk semuanya; identitas dipisah ke tabel sendiri per jenis.**

`materials.jenis` (`biasa` / `ber_sn` / `drum_kabel`) menentukan tabel identitas mana yang dipakai:

- `biasa` → tidak ada tabel identitas. Transaksi cukup membawa `qty`.
- `ber_sn` → `material_sns` (satu baris per butir: `serial_number`, `status`, `lokasi`). Transaksi membawa `material_sn_id` dan `qty` = ±1.
- `drum_kabel` → `drums` (satu baris per drum/potongan: `drum_id`, `panjang_awal`, `sisa`, `induk_drum_id`, `lokasi`). Transaksi membawa `drum_id` dan `qty` = meter.

`material_transaksis` punya kolom `material_sn_id` dan `drum_id` yang **nullable**, dijaga satu `CHECK` bahwa yang terisi cocok dengan `materials.jenis`. Nullable di sini bukan kompromi — jenisnya memang tidak punya identitas, dan `CHECK` membuat "lupa mengisi SN" ditolak database.

Alternatif yang ditolak: tiga tabel transaksi terpisah. Setiap laporan gudang, setiap perhitungan kesiapan material, dan setiap rekon akan jadi `UNION` tiga arah selamanya — biaya seumur hidup untuk keuntungan sekali di awal.

## Drum: kenapa potongan jadi baris baru

Saat 300m ditarik dari drum 2000m, yang keluar gudang **adalah benda fisik baru** — punya lokasi sendiri, bisa dipotong lagi, bisa kembali ke gudang dengan sisa berbeda. Dia bukan "sebagian dari drum induk".

- **ID turunan**: `DRM-00042-1`, `DRM-00042-2`, … — akhiran urut per induk, dibentuk dalam transaksi database. Potongan dari potongan meneruskan: `DRM-00042-1-1`. ID-nya sendiri menceritakan silsilahnya, dan `induk_drum_id` membuatnya bisa ditelusuri dengan query.
- **Kekekalan meter**: `drums.sisa` induk berkurang tepat sebanyak `panjang_awal` anak, dalam satu transaksi database yang sama, dijaga `CHECK (sisa >= 0 AND sisa <= panjang_awal)`. Total meter tidak bisa bertambah atau hilang karena bug.
- **Dashboard** menjawab keduanya dari satu tabel: total meter = `SUM(sisa)`, jumlah roll = `COUNT(*)` pada `drums` yang `sisa > 0` di lokasi tersebut.
- **Sisa kembali ke gudang**: potongan yang sama pulang dengan `sisa` lebih kecil. Baris `drums`-nya tidak dihapus dan tidak digabung balik ke induk — menggabungkan balik akan menghapus jejak berapa yang benar-benar terpakai di project.

## Konsekuensi

- Layar input berbeda per jenis (scan SN, pilih drum + isi meter, atau ketik jumlah), tapi semuanya bermuara ke satu buku.
- Menambah jenis material keempat kelak = satu tabel identitas baru + satu cabang di `CHECK`, bukan bongkar buku transaksi.
- `drums` tumbuh satu baris per potongan. Ribuan baris per tahun — kecil.
