<?php

namespace App\Support;

/**
 * Ambang batas dan warna SPI sesuai ADR-0010 §5.
 *
 * Satu-satunya tempat threshold ini hidup, dipakai baik oleh Kurva S per
 * Project maupun oleh agregat portofolio.
 */
final class SpiThreshold
{
    public static function status(?float $spi): string
    {
        if ($spi === null) {
            return 'na';
        }

        return $spi >= 1.0 ? 'green' : ($spi >= 0.9 ? 'yellow' : 'red');
    }

    public static function label(?float $spi): string
    {
        return $spi === null ? 'N/A' : number_format($spi, 2, '.', '');
    }
}
