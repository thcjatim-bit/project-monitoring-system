<?php

namespace Database\Seeders;

use App\Models\Grup;
use App\Models\Mitra;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AccessControlSeeder::class);

        $password = env('PMS_DEMO_PASSWORD', 'password');

        User::query()->updateOrCreate(
            ['email' => 'thc@example.com'],
            [
                'name' => 'Admin THC',
                'password' => $password,
                'grup_id' => Grup::query()->where('preset', 'admin_thc')->value('id'),
                'mitra_id' => null,
                'aktif' => true,
            ],
        );

        $mitra = Mitra::query()->firstOrCreate(
            ['kode' => 'MITRA-DEMO'],
            ['nama' => 'Mitra Demo', 'aktif' => true],
        );

        User::query()->updateOrCreate(
            ['email' => 'mitra@example.com'],
            [
                'name' => 'User Mitra Demo',
                'password' => $password,
                'grup_id' => Grup::query()->where('preset', 'mitra')->value('id'),
                'mitra_id' => $mitra->id,
                'aktif' => true,
            ],
        );
    }
}
