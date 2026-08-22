<?php

namespace Tests\Support;

use Database\Factories\WarehouseFactory;

/**
 * WarehouseFactory yang mengisi `mitra_id` sendiri. Dipakai satu test untuk mencabut
 * kebetulan yang selama ini menutupi fixture: gudang THC harus ber-`mitra_id` NULL
 * karena pemanggil memintanya (ADR-0023), bukan karena factory kebetulan diam.
 */
class MitraFillingWarehouseFactory extends WarehouseFactory
{
    public static ?int $mitraId = null;

    public function definition(): array
    {
        return parent::definition() + ['mitra_id' => self::$mitraId];
    }
}
