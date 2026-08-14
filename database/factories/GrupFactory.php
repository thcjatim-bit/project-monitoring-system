<?php

namespace Database\Factories;

use App\Models\Grup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Grup> */
class GrupFactory extends Factory
{
    protected $model = Grup::class;

    public function definition(): array
    {
        return ['nama' => fake()->unique()->jobTitle(), 'preset' => null];
    }
}
