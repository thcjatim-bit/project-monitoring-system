<?php

namespace App\Support;

final class QuantityDisplayFormatter
{
    public static function format(string|int|float $quantity): string
    {
        $formatted = number_format((float) $quantity, 3, ',', '.');
        [$integer, $fraction] = array_pad(explode(',', $formatted, 2), 2, '');
        $fraction = rtrim($fraction, '0');

        return $fraction === '' ? $integer : $integer.','.$fraction;
    }
}
