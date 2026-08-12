# ADR 0012: Alur Foto Pekerjaan dan Sinkronisasi ke Google Drive

## Status
Accepted — menggantikan kebijakan retensi di ADR-0007.

## Konteks
Sistem membutuhkan dokumentasi foto lapangan sebagai bukti pekerjaan. Foto diunggah dari HP petugas lapangan (Mitra maupun THC), disimpan di server kantor, lalu disalin ke Google Drive agar Mitra bisa mengaksesnya tanpa membebani server. Kapasitas disk server terbatas (~63 GB sisa), koneksi internet lapangan sering lambat, dan pemilik sistem tidak akan memeriksa log server secara rutin.

## Keputusan

### 1. Upload dan Kompresi Client-Side
- **Siapa boleh upload**: Mitra dan THC.
- **Format**: hanya JPEG.
- **Batas per unggahan**: maksimal **10 foto**, masing-masing maksimal **5 MB** (ukuran mentah dari kamera HP).
- **Kompresi di browser**: sebelum dikirim ke server, JavaScript di browser HP meresize foto ke maksimal **1920×1080** (Full HD) dan mengompres ke kisaran **500 KB – 1 MB**. Ini menghemat kuota dan mempercepat upload dari lapangan.
- Server menyimpan hasil kompresi ini apa adanya — tidak ada kompresi ulang di sisi server.

### 2. Sinkronisasi ke Google Drive via rclone
- **Metode**: `rclone sync` dijalankan sebagai **cron job setiap 1 jam**.
- **Autentikasi**: Google **Service Account** (file JSON), tidak perlu login ulang berkala.
- **Struktur folder di Drive**: `ProjectID / Step / Tanggal` (contoh: `PRJ-2608-0001 / Deployment / 2026-08-12`).
- **Ketahanan terhadap gangguan**: jika internet kantor putus, rclone pada jadwal berikutnya otomatis mendeteksi file yang belum ter-sync dan mengunggahnya (*catch-up*). Tidak perlu intervensi manual.

### 3. Akses Mitra ke Folder Google Drive
- Satu **Folder Master** di Google Drive dibuat publik **View-Only** ("Anyone with the link can view").
- Di dalam aplikasi web, tersedia tombol **"Buka Gudang Foto"** yang mengarahkan ke Folder Master tersebut.
- Mitra mencari sendiri folder `ProjectID` miliknya di dalam Drive. Karena aplikasi web sudah mengisolasi data per mitra, risiko akses silang dianggap dapat diterima.
- UI web menampilkan foto menggunakan URL direct-content Google Drive pada tag `<img>`, sehingga server tidak perlu menjadi proxy gambar.

### 4. Notifikasi Kegagalan Sinkronisasi
- **Dua kanal sekaligus**:
  1. **Banner merah** di Dashboard admin THC — terlihat saat admin login.
  2. **Pesan WhatsApp** ke nomor admin — dikirim otomatis oleh script yang memeriksa exit code rclone.
- Banner ditampilkan selama kondisi gagal berlanjut dan hilang otomatis setelah sync berhasil kembali.

### 5. Retensi Foto di Disk Server
- Foto di disk server **dihapus otomatis setelah 90 hari** sejak tanggal upload, dengan syarat file sudah berhasil ter-sync ke Google Drive.
- Setelah 90 hari, Google Drive menjadi sumber kebenaran tunggal.
- **Safeguard disk** (dari ADR-0007) tetap berlaku: jika sisa disk turun di bawah **10 GB**, foto paling usang dihapus lebih awal meskipun belum 90 hari.
- Kebijakan ini **menggantikan** retensi berbasis status project di ADR-0007 karena lebih sederhana dan lebih dapat diprediksi.

## Konsekuensi

- Membutuhkan library JavaScript client-side untuk resize/kompresi gambar (misalnya `browser-image-compression` atau canvas API manual).
- Server harus memasang `rclone` dan mengonfigurasi Service Account Google — menjadi bagian dari panduan deploy.
- Cron job retensi (hapus file > 90 hari yang sudah ter-sync) dan safeguard disk (< 10 GB) perlu ditulis sebagai Laravel Scheduled Command atau shell script.
- Folder Master Google Drive bersifat publik View-Only — siapa pun yang memiliki link bisa melihat foto. Risiko ini diterima karena link berisi ID acak yang sulit ditebak.
- Ketergantungan pada WhatsApp gateway (WAHA) untuk notifikasi kegagalan sync — fitur ini baru aktif setelah gateway dipasang di gelombang berikutnya; sebelumnya hanya banner.
