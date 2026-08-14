<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Warehouse> */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return ['kode' => fake()->unique()->bothify('GDG-####'), 'nama' => 'Warehouse '.fake()->company(), 'aktif' => true];
    }
}
