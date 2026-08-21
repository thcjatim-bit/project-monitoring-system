# ADR-0022 — Workspace Admin Mitra dibatasi per domain

Workspace Admin Mitra diperlakukan sebagai kumpulan jalur kerja, bukan satu aggregate atau module mutasi besar. Dashboard Mitra tetap read-only, sedangkan User Mitra, Penugasan Gudang, Harga Jasa Mitra, dan Workspace Perencanaan Project mempertahankan aturan kepemilikan, approval, dan snapshot pada domain pemiliknya masing-masing; keputusan ini menjaga setiap seam tetap dapat mengunci authorization dan isolasi tenant, dengan konsekuensi alur lintas domain perlu dikomposisikan secara eksplisit.
