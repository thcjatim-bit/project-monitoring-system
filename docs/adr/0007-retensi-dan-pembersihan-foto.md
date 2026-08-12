# ADR 0007: Retensi dan Pembersihan Foto Project

## Status
Superseded by ADR-0012 — kebijakan retensi diganti dari berbasis status project menjadi 90 hari tetap. Safeguard disk (< 10 GB) tetap berlaku.

## Konteks

Aplikasi menyimpan dokumentasi foto lapangan dari mitra ke server, dan secara otomatis mencadangkannya ke Google Drive (storage tak terbatas). Namun, kapasitas disk server lokal kita cukup terbatas (tersisa sekitar 63GB). 
Isu yang harus diselesaikan:
1. Kapan file foto di disk lokal server dihapus?
2. Bagaimana antarmuka web (UI) menampilkan foto tersebut setelah file lokalnya dihapus dari server?

## Keputusan

1. **Retensi Normal Berbasis Status:**
   Foto di disk server akan dihapus secara otomatis hanya setelah status Project berubah menjadi **"GO Live"** atau **"Selesai"**, dengan syarat utama salinan foto tersebut sudah berhasil diamankan di Google Drive. Selama project masih aktif, foto lokal dipertahankan demi kecepatan akses web UI.

2. **Safeguard Disk (Hard Fallback):**
   Meskipun penghapusan bergantung pada status project, kita memasang jaring pengaman (*safeguard*). Sebuah *cron job* akan memantau sisa kapasitas disk server. Jika sisa disk turun di bawah ambang batas **10GB**, sistem akan otomatis menghapus foto lokal paling usang (berdasarkan tanggal), meskipun project-nya belum selesai. Ini mencegah terjadinya *crash* server akibat kehabisan memori.

3. **Mekanisme Tampilan di UI (Direct Link GDrive):**
   Folder Google Drive penyimpanan akan diatur menjadi publik (*Anyone with link*). Dengan demikian, pada aplikasi web, kita cukup meletakkan URL *direct content* GDrive ke dalam atribut `<img src="...">`. 
   - UI akan tetap menampilkan gambar secara mulus tanpa me-redirect user ke halaman teks.
   - Server backend kita terbebas dari beban operasional dan *bandwidth* karena tidak perlu melakukan *proxy* untuk men-download gambar dari GDrive secara *on-the-fly*.

## Konsekuensi

- Membutuhkan penulisan *cron job* atau *Scheduled Task* di Laravel untuk rutin mengecek status folder dan ruang disk server menggunakan perintah OS.
- Setup akun dan folder Google Drive tujuan tidak boleh diset sepenuhnya privat (harus *anyone with link*), sehingga pihak luar yang mengetahui kombinasi ID acak GDrive tersebut berteori bisa melihat foto tersebut (risiko ini dianggap bisa diterima dibandingkan *server overload*).
