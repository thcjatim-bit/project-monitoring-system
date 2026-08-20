# ADR-0021 — Capability Admin Mitra sebagai Super User Tenant

**Status**: Diterima — 2026-08-20  
**Konteks tiket**: [#94 Putuskan capability Admin Mitra dan harga jasa sebelum membuka write access](https://github.com/thcjatim-bit/project-monitoring-system/issues/94)

Admin Mitra perlu dapat menjalankan operasional Mitra dengan satu akun ketika tim kecil, tetapi tidak boleh memperoleh kewenangan THC atau membuka data tenant lain. Diputuskan bahwa Admin Mitra adalah User Mitra dengan capability gabungan seluruh User Mitra dalam tenantnya sendiri: administrasi User, assignment dan operasi Warehouse, Project planning/progress, Material, dan Komentar Project. Pengelolaan User berarti membuat, mengubah, menonaktifkan, reset password, dan menetapkan Grup operasional Mitra; status Admin Mitra, User THC, `mitra_id`, dan matriks izin global tetap tidak dapat dikelola oleh Admin Mitra. Pengurangan User berarti nonaktif, bukan hapus, dan Admin Mitra terakhir harus terlindungi.

Batas persetujuan tetap milik THC: persetujuan Harga Jasa Mitra, verifikasi progres, persetujuan Pemakaian/Rekon Material, baseline final, penutupan administratif Project, serta perubahan identitas atau kepemilikan Project/Warehouse. Admin Mitra dapat mengusulkan perubahan TOC/plan dan mengajukan atau merevisi Harga Jasa Mitra, tetapi harga hanya dapat dipakai setelah disetujui THC dan terikat pada PKS aktif. RAB serta baseline yang sudah beku tidak berubah. Admin Mitra dapat membaca dan membuat Komentar Project reguler, termasuk mention sesuai akses, tetapi tidak dapat membaca atau membuat Komentar Internal THC dan tidak dapat menghapus komentar.

Capability ini tetap dievaluasi melalui Izin Aksi eksplisit dan authorization server-side, dengan RLS sebagai batas data yang tidak dapat dilewati. Keputusan ini memisahkan kebutuhan operasional tim kecil dari kewenangan THC dan mencegah konfigurasi Grup atau direct URL mengubah batas tenant.
