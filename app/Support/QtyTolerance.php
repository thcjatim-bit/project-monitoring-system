<?php

namespace App\Support;

/**
 * Toleransi pembanding qty pada klasifikasi penyimpangan Surat Jalan (ADR-0026): setengah
 * langkah terkecil yang bisa diketik operator (0,001), supaya pembulatan desimal tidak
 * melahirkan penyimpangan.
 *
 * Satu-satunya tempat ambang ini hidup. Ia dibaca `SuratJalanService` saat menilai penerbitan
 * dan diserahkan ke halaman lewat `SuratJalanFormQuery` karena penandaan di klien memakai ambang
 * yang sama; mengetiknya ulang di JS berarti dua ambang yang bisa menyimpang.
 */
final class QtyTolerance
{
    public const VALUE = 0.0005;
}
