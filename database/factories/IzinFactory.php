<?php

namespace Database\Factories;

use App\Models\Izin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Izin> */
class IzinFactory extends Factory
{
    protected $model = Izin::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return ['kode' => Str::slug($name, '_'), 'nama' => Str::headline($name)];
    }
}
