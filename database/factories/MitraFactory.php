<?php

namespace Database\Factories;

use App\Models\Mitra;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Mitra> */
class MitraFactory extends Factory
{
    protected $model = Mitra::class;

    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->bothify('MTR-####'),
            'nama' => fake()->company(),
            'aktif' => true,
        ];
    }
}
