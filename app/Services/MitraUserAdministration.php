<?php

namespace App\Services;

use App\Contracts\WahaClient;
use App\Models\Grup;
use App\Models\Mitra;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MitraUserAdministration
{
    public function __construct(private WahaClient $waha, private MitraGroupPolicy $groups) {}

    /** @return array{users: Collection, grups: Collection, mitras: Collection, editableMitras: Collection} */
    public function rosterFor(User $actor): array
    {
        $this->assertAccess($actor);
        $isMitra = $actor->mitra_id !== null;
        $mitraIds = $isMitra ? collect([$actor->mitra_id]) : User::query()->whereNotNull('mitra_id')->pluck('mitra_id');

        return [
            'users' => User::with(['mitra', 'grup'])
                ->when($isMitra, fn ($query) => $query->where('mitra_id', $actor->mitra_id))
                ->latest()
                ->get(),
            'grups' => ($isMitra ? $this->groups->assignableToMitra() : Grup::query())
                ->with('izins')
                ->orderBy('nama')
                ->get(),
            'mitras' => $isMitra ? Mitra::whereKey($actor->mitra_id)->get() : Mitra::where('aktif', true)->orderBy('nama')->get(),
            'editableMitras' => $isMitra
                ? Mitra::whereKey($actor->mitra_id)->get()
                : Mitra::where(fn ($query) => $query->where('aktif', true)->orWhereIn('id', $mitraIds))->orderBy('nama')->get(),
        ];
    }

    public function create(User $actor, array $data): User
    {
        $this->assertAccess($actor);
        if ($actor->mitra_id !== null) {
            $this->groups->assertAssignableToMitra((int) $data['grup_id']);
            $data['mitra_id'] = $actor->mitra_id;
        }

        return DB::transaction(function () use ($data): User {
            $password = Str::password(16);
            $user = User::create([...$data, 'password' => $password, 'aktif' => true]);
            $this->waha->sendText($user->no_wa, "Akun Project Monitoring System\nEmail: {$user->email}\nKata sandi: {$password}");

            return $user;
        });
    }

    public function update(User $actor, User $target, array $data): void
    {
        $this->assertAccess($actor);
        $this->assertInActorScope($actor, $target);
        if ($actor->mitra_id !== null) {
            if ($target->hasIzin('manage_mitra_users')) {
                $data['grup_id'] = $target->grup_id;
            } else {
                $this->groups->assertAssignableToMitra((int) $data['grup_id']);
            }
            $data['mitra_id'] = $actor->mitra_id;
        }

        $target->update($data);
    }

    public function toggle(User $actor, User $target): void
    {
        $this->assertAccess($actor);
        $this->assertInActorScope($actor, $target);
        abort_if($actor->mitra_id !== null && $actor->is($target), 422, 'User tidak dapat menonaktifkan dirinya sendiri.');
        if ($actor->mitra_id !== null && $target->aktif && $target->hasIzin('manage_mitra_users')) {
            $activeAdmins = User::query()
                ->where('mitra_id', $actor->mitra_id)
                ->where('aktif', true)
                ->whereHas('grup.izins', fn ($query) => $query->where('kode', 'manage_mitra_users'))
                ->count();
            abort_unless($activeAdmins > 1, 422, 'Admin Mitra terakhir tidak dapat dinonaktifkan.');
        }

        $target->update(['aktif' => ! $target->aktif]);
    }

    public function resetCredentials(User $actor, User $target): void
    {
        $this->assertAccess($actor);
        $this->assertInActorScope($actor, $target);
        DB::transaction(function () use ($target): void {
            $password = Str::password(16);
            $target->update(['password' => $password]);
            $this->waha->sendText($target->no_wa, "Kata sandi baru Project Monitoring System: {$password}");
        });
    }

    private function assertInActorScope(User $actor, User $target): void
    {
        abort_unless($actor->mitra_id === null || (int) $target->mitra_id === (int) $actor->mitra_id, 404);
    }

    private function assertAccess(User $actor): void
    {
        $permission = $actor->mitra_id === null ? 'manage_users' : 'manage_mitra_users';
        abort_unless($actor->hasIzin($permission), 403);
    }
}
