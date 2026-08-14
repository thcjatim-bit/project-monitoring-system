<?php

namespace Database\Factories;

use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Material> */
class MaterialFactory extends Factory
{
    protected $model = Material::class;

    public function definition(): array
    {
        return ['kode' => fake()->unique()->bothify('MAT-####'), 'nama' => fake()->words(2, true), 'unit' => 'pcs', 'jenis' => 'biasa', 'aktif' => true];
    }
}
