# Runbook — Berkas infrastruktur yang diversikan

**Konteks tiket**: [Preflight deploy buntu sendiri ketika cached config production hilang (#122)](https://github.com/thcjatim-bit/project-monitoring-system/issues/122)

## Apa yang ada di sini

`deploy/` memuat salinan kanonik berkas infrastruktur yang terpasang di host
production:

| File di repo | Terpasang sebagai | Dijalankan oleh |
| --- | --- | --- |
| `deploy/sbin/pms-deploy` | `/usr/local/sbin/pms-deploy` | root, lewat NOPASSWD sudo |
| `deploy/systemd/pms-queue.service` | `/etc/systemd/system/pms-queue.service` | systemd |
| `deploy/systemd/pms-schedule.service` | `/etc/systemd/system/pms-schedule.service` | systemd |
| `deploy/systemd/pms-schedule.timer` | `/etc/systemd/system/pms-schedule.timer` | systemd |

Semuanya **byte-identical** dengan yang terpasang, dan dijaga tetap begitu oleh
`scripts/verify-infra-tools.sh`.

`pms-install` **belum** diversikan; masih hanya ada di host.

## Kenapa pemasangannya manual

Berkas di `/usr/local/sbin` dijalankan **sebagai root** lewat NOPASSWD sudo,
sementara checkout repo bisa ditulis user biasa. Kalau ada proses otomatis yang
menyalin dari checkout ke direktori sistem — misalnya langkah di dalam deploy —
maka **setiap commit menjadi jalan mengubah apa yang dijalankan root**. Itu
privilege escalation, bukan kemudahan.

Karena itu pemasangan tetap tindakan root yang disengaja oleh manusia:

```bash
# tool root
sudo install -o root -g root -m 0755 deploy/sbin/pms-deploy /usr/local/sbin/pms-deploy

# unit systemd
sudo install -o root -g root -m 0644 deploy/systemd/pms-queue.service /etc/systemd/system/pms-queue.service
sudo systemctl daemon-reload
```

Simpan salinan lama lebih dulu kalau perubahannya berisiko:

```bash
sudo cp -p /usr/local/sbin/pms-deploy /usr/local/sbin/pms-deploy.bak-$(date +%Y-%m-%d)
```

Alur perubahan yang benar: ubah salinan di repo, review, commit, push, **baru**
pasang ke host. Bukan sebaliknya. Mengedit langsung di direktori sistem membuat
repo berbohong.

## Memeriksa drift

```bash
scripts/verify-infra-tools.sh
```

Membandingkan setiap berkas di `deploy/sbin/` dan `deploy/systemd/` dengan
pasangannya yang terpasang secara byte-per-byte, lalu exit non-nol kalau ada yang
beda, hilang, atau tidak terbaca. Perbandingannya byte-exact — beda newline di
ujung pun dihitung drift, karena yang dibaca root dan systemd adalah berkas itu
apa adanya.

Script ini **assertion, bukan installer**: ia melaporkan drift dan tidak pernah
memperbaikinya, dengan alasan yang sama seperti di atas.

Override untuk test:

| Variabel | Arti |
| --- | --- |
| `PMS_INFRA_PAIRS` | daftar `direktori_repo\|direktori_terpasang`, dipisah baris baru, menggantikan default |

### Test

```bash
bash scripts/verify-infra-tools.test.sh
```

7 check, offline: salinan identik, salinan drift, berkas belum terpasang, drift
yang hanya berupa whitespace di ujung, drift pada pasangan direktori kedua,
direktori kosong, dan direktori hilang.

## BOM dan CRLF pada unit systemd

Ketiga unit yang terpasang diawali **BOM UTF-8** dan memuat satu baris berakhiran
**CRLF** (`WantedBy=` di baris terakhir). Jejak bahwa unit-unit itu pernah ditulis
dari Windows.

Salinan di repo mempertahankannya **apa adanya**, karena tujuan versioning ini
adalah menggambarkan kenyataan, bukan versi yang lebih rapi dari kenyataan.
`.gitattributes` menandai `deploy/sbin/*` dan `deploy/systemd/*` sebagai `-text`
supaya Git tidak menormalkan byte-nya saat commit maupun checkout; tanpa itu CR
tersebut akan hilang dan salinan repo berhenti cocok dengan yang terpasang.

systemd menoleransinya: kedua unit `enabled`, symlink `*.wants` terbentuk di
direktori yang benar, tidak ada direktori target yang ternoda CR. Jadi ini
kosmetik, bukan bug yang sedang berjalan.

Merapikannya berarti menulis ulang unit di production, dan itu keputusan
tersendiri di luar cakupan #122. Kalau nanti dikerjakan, urutannya: rapikan
salinan di repo, pasang ke host, `daemon-reload`, lalu jalankan drift check.

## Catatan sejarah

Sampai 22 Agustus 2026 `deploy/systemd/` menggambarkan tata letak yang tidak
pernah terwujud — `User=pms`, `Group=pms`, `WorkingDirectory=/opt/pms/current` —
sementara yang benar-benar berjalan adalah `jawan`, `www-data`, dan
`/var/www/project-monitoring-system`. Salinan yang tidak cocok dengan kenyataan
lebih berbahaya daripada tidak ada salinan sama sekali, karena orang
memercayainya. Drift check ada supaya keadaan itu tidak terulang diam-diam.
