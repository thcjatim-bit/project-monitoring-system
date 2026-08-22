# Runbook — Tool infrastruktur yang diversikan

**Konteks tiket**: [Preflight deploy buntu sendiri ketika cached config production hilang (#122)](https://github.com/thcjatim-bit/project-monitoring-system/issues/122)

## Apa yang ada di sini

`deploy/sbin/` memuat salinan kanonik tool infrastruktur yang terpasang di
`/usr/local/sbin` pada host production:

| File | Terpasang sebagai |
| --- | --- |
| `deploy/sbin/pms-deploy` | `/usr/local/sbin/pms-deploy` |

Sebelumnya `pms-deploy` hanya ada sebagai file di host. Setiap perubahan padanya
tidak punya jejak review, tidak punya riwayat, dan tidak punya sumber kebenaran
selain mesin itu sendiri. Versinya di sini menutup celah tersebut.

`pms-install` **belum** diversikan. Tool itu masih hanya ada di host.

## Kenapa pemasangannya manual

Tool di `/usr/local/sbin` dijalankan **sebagai root** lewat NOPASSWD sudo,
sementara checkout repo bisa ditulis oleh user biasa. Kalau ada proses otomatis
yang menyalin file dari checkout ke `/usr/local/sbin` — misalnya langkah di
dalam deploy — maka **setiap commit menjadi jalan mengubah apa yang dijalankan
root**. Itu privilege escalation, bukan kemudahan.

Karena itu pemasangan tetap tindakan root yang disengaja oleh manusia:

```bash
sudo install -o root -g root -m 0755 deploy/sbin/pms-deploy /usr/local/sbin/pms-deploy
```

Simpan salinan lama lebih dulu kalau perubahannya berisiko:

```bash
sudo cp -p /usr/local/sbin/pms-deploy /usr/local/sbin/pms-deploy.bak-$(date +%Y-%m-%d)
```

Alur perubahan yang benar: ubah `deploy/sbin/pms-deploy` di repo, review, commit,
push, **baru** pasang ke host. Bukan sebaliknya. Mengedit langsung di
`/usr/local/sbin` membuat repo berbohong.

## Memeriksa drift

```bash
scripts/verify-infra-tools.sh
```

Membandingkan setiap file di `deploy/sbin/` dengan pasangannya di
`/usr/local/sbin` secara byte-per-byte, lalu exit non-nol kalau ada yang beda,
hilang, atau tidak terbaca. Perbandingannya byte-exact — beda newline di ujung
pun dihitung drift, karena yang dijalankan root adalah file itu apa adanya.

Script ini **assertion, bukan installer**: ia melaporkan drift dan tidak pernah
memperbaikinya, dengan alasan yang sama seperti di atas.

Override untuk test:

| Variabel | Arti |
| --- | --- |
| `PMS_INFRA_REPO_DIR` | direktori salinan yang diversikan |
| `PMS_INFRA_SBIN_DIR` | direktori salinan yang terpasang |

### Test

```bash
bash scripts/verify-infra-tools.test.sh
```

6 check, offline: salinan identik, salinan drift, tool belum terpasang, drift
yang hanya berupa whitespace di ujung, direktori kosong, dan direktori hilang.

## Drift yang sudah diketahui dan belum ditutup

`deploy/systemd/` **tidak cocok** dengan unit yang benar-benar terpasang:

| | Di repo | Terpasang |
| --- | --- | --- |
| `User` | `pms` | `jawan` |
| `Group` | `pms` | `www-data` |
| `WorkingDirectory` | `/opt/pms/current` | `/var/www/project-monitoring-system` |

Salinan di repo menggambarkan tata letak yang tidak pernah terwujud. Karena itu
`scripts/verify-infra-tools.sh` sengaja dibatasi pada `deploy/sbin/` saja —
memasukkan `deploy/systemd/` akan langsung merah, dan menyamakan keduanya berarti
memutuskan mana yang benar: menulis ulang unit di repo agar sesuai kenyataan,
atau memindahkan service ke user dan path terpisah sesuai #63.

Keputusan itu belum diambil. Salinan yang tidak cocok dengan kenyataan lebih
berbahaya daripada tidak ada salinan sama sekali, karena orang memercayainya.
