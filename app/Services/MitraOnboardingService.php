<?php

namespace App\Services;

use App\Contracts\WahaClient;
use App\Models\Grup;
use App\Models\Mitra;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MitraOnboardingService
{
    public function __construct(private WahaClient $waha) {}

    /** @return array{mitra: Mitra, user: User, password: string} */
    public function onboard(array $attributes): array
    {
        return DB::transaction(function () use ($attributes): array {
            $password = Str::password(16);
            $mitra = Mitra::create(['kode' => $attributes['kode'], 'nama' => $attributes['nama'], 'aktif' => true]);
            $user = $mitra->users()->create([
                'name' => $attributes['admin_name'],
                'email' => $attributes['admin_email'],
                'no_wa' => $attributes['no_wa'],
                'password' => $password,
                'grup_id' => Grup::query()->where('preset', 'mitra')->value('id'),
                'aktif' => true,
            ]);
            $this->waha->sendText($attributes['no_wa'], "Akun Project Monitoring System\nEmail: {$user->email}\nKata sandi: {$password}");

            return compact('mitra', 'user', 'password');
        });
    }
}
