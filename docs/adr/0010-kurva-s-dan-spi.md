# 10. Rumus Kurva S, SPI, dan Indikator Kesiapan Material

Date: 2026-08-12

## Status

Accepted

## Konteks

Kurva S digunakan untuk memantau kemajuan proyek berdasarkan nilai rupiah jasa. Terdapat kebutuhan spesifikasi mendetail mengenai bagaimana rumusan Persen Rencana dan Persen Realisasi dihitung, bagaimana perhitungan dan tampilan SPI (Schedule Performance Index), serta penanganan untuk kondisi *edge-cases* seperti progres yang melampaui tanggal TOC (Time of Completion), realisasi volume yang melebihi batas RAB Jasa, dan cara menampilkan indikator kesiapan material.

Keputusan ini mendokumentasikan rumus pasti dan aturan UI/UX dari grafik kemajuan proyek tersebut, untuk memastikan standarisasi saat implementasi fitur (Issue #7).

## Keputusan

1. **Rumus Kurva S**:
   - **Persen Rencana (Plan)** = `(Kumulatif Plan Harian s.d. hari ini / Total Plan) * 100%`.
   - **Persen Realisasi (Actual)** = `(Total Rupiah Jasa dari Progres yang TERVERIFIKASI / Grand Total RAB Jasa) * 100%`.

2. **Indikator Kesiapan Material**:
   - Dihitung menggunakan rumus: `(Total Qty Material Terkirim ke Lokasi / Total Qty Kebutuhan RAB Material) * 100%`.
   - Divisualisasikan **secara terpisah** (misal dalam bentuk *progress bar* atau *gauge chart* tersendiri) agar tidak digabungkan ke dalam grafik Kurva S, karena satuan dan bobot dasarnya berbeda (Material vs Jasa).

3. **Progres Belum Diverifikasi (Pending Verification)**:
   - Progres yang diajukan mitra tetapi belum diverifikasi oleh THC tetap ditampilkan di Kurva S sebagai **garis putus-putus atau bayangan (shadow)** yang bersambung dari titik terakhir realisasi terverifikasi.

4. **Penanggalan (Plotting Tanggal)**:
   - Node progres pada grafik diplot berdasarkan **tanggal aktual pekerjaan**, bukan tanggal input sistem. Ini memungkinkan penggambaran retrospektif (*backdated*) di mana kurva masa lalu bisa naik/berubah bila laporan masuk terlambat.

5. **Ambang Batas (Threshold) dan Warna SPI**:
   - `SPI >= 1.0` : **Hijau** (Sehat / Lebih cepat dari jadwal)
   - `0.9 <= SPI < 1.0` : **Kuning** (Peringatan / Sedikit terlambat)
   - `SPI < 0.9` : **Merah** (Kritis / Terlambat parah)

6. **Keterlambatan Melewati TOC Tanpa Revisi**:
   - Jika proyek terlambat melewati batas TOC dan belum ada *Revised Baseline*, Sumbu X pada grafik otomatis memanjang. 
   - Garis Persen Rencana akan **mendatar (flat) di 100%**. Area keterlambatan (melewati TOC) diberikan *highlight* atau warna latar kemerahan.

7. **Pencegahan Realisasi > 100%**:
   - Sistem **mencegah** penginputan progres pekerjaan yang jumlah volumenya melampaui sisa volume RAB Jasa saat ini.
   - Pekerjaan tambahan (*extra work*) mengharuskan pengesahan melalui **Variation Order** terlebih dahulu di sistem (yang akan me-recalculate 100%), baru setelah itu progres tambahannya bisa diinput. Kurva tidak pernah menembus angka >100%.

## Konsekuensi

- Backend perlu menyediakan API untuk Kurva S yang tidak hanya mengembalikan data terverifikasi, tetapi juga *subset* data *pending* untuk diplot sebagai garis putus-putus oleh Frontend.
- Validasi ketat di sisi backend dan database diperlukan saat insert/update progres agar tidak melebihi sisa *Quantity* pada RAB Jasa.
- UI grafik harus mendukung perpanjangan otomatis sumbu X (*dynamic X-axis scaling*) serta dukungan pewarnaan *background* untuk periode setelah TOC.
