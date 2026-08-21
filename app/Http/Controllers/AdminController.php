<?php

namespace App\Http\Controllers;

use App\Contracts\WahaClient;
use App\Exceptions\SafeDeletionException;
use App\Models\Grup;
use App\Models\Material;
use App\Models\Mitra;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WhatsappSessionStatus;
use App\Services\MasterCodeGenerator;
use App\Services\MitraCodeGenerator;
use App\Services\MitraDeletionService;
use App\Services\MitraOnboardingService;
use App\Services\UserDeletionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminController extends Controller
{
    /** @var list<string> Permissions that can never be delegated to a Mitra User. */
    private const THC_ONLY_PERMISSIONS = [
        'manage_users',
        'manage_grups',
        'manage_mitras',
        'manage_warehouses',
        'manage_materials',
        'manage_master_data',
        'manage_api_keys',
        'approve_material_request',
        'approve_material_usage',
        'approve_material_rekon',
        'verify_project_progress',
        'manage_project_plan',
        'manage_project_material',
        'approve_mitra_price',
    ];

    public function users(Request $request): View
    {
        $actor = $request->user();
        $isMitra = $actor->mitra_id !== null;
        $mitraIds = $isMitra ? collect([$actor->mitra_id]) : User::query()->whereNotNull('mitra_id')->pluck('mitra_id');
        $users = User::with(['mitra', 'grup'])
            ->when($isMitra, fn ($query) => $query->where('mitra_id', $actor->mitra_id))
            ->latest()
            ->get();
        $grups = Grup::query()
            ->with('izins')
            ->when($isMitra, fn ($query) => $query
                ->where(fn ($groups) => $groups->whereNull('preset')->orWhere('preset', '!=', 'admin_mitra'))
                ->whereDoesntHave('izins', fn ($permissions) => $permissions->whereIn('kode', self::THC_ONLY_PERMISSIONS)))
            ->orderBy('nama')
            ->get();

        return view('admin.users', [
            'users' => $users,
            'grups' => $grups,
            'mitras' => $isMitra ? Mitra::whereKey($actor->mitra_id)->get() : Mitra::where('aktif', true)->orderBy('nama')->get(),
            'editableMitras' => $isMitra
                ? Mitra::whereKey($actor->mitra_id)->get()
                : Mitra::where(fn ($query) => $query->where('aktif', true)->orWhereIn('id', $mitraIds))->orderBy('nama')->get(),
        ]);
    }

    public function mitras(): View
    {
        return view('admin.mitras', ['mitras' => Mitra::with('adminMitraPertama')->latest()->get()]);
    }

    public function createUser(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'no_wa' => ['required', 'regex:/^62[0-9]{8,13}$/'],
            'mitra_id' => ['nullable', Rule::exists('mitras', 'id')->where('aktif', true)],
            'grup_id' => ['required', 'exists:grups,id'],
        ]);
        if ($actor->mitra_id !== null) {
            $this->assertMitraGroupAllowed((int) $data['grup_id']);
            $data['mitra_id'] = $actor->mitra_id;
        }
        DB::transaction(function () use ($data): void {
            $password = Str::password(16);
            $user = User::create([...$data, 'password' => $password, 'aktif' => true]);
            app(WahaClient::class)->sendText($user->no_wa, "Akun Project Monitoring System\nEmail: {$user->email}\nKata sandi: {$password}");
        });

        return back()->with('status', 'User dibuat dan kredensial dikirim melalui WhatsApp.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $this->assertUserInActorScope($actor, $user);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'no_wa' => ['required', 'regex:/^62[0-9]{8,13}$/'],
            'mitra_id' => ['nullable', Rule::exists('mitras', 'id')->where(
                fn ($query) => $query->where('aktif', true)->orWhere('id', $user->mitra_id)
            )],
            'grup_id' => ['required', 'exists:grups,id'],
        ]);
        if ($actor->mitra_id !== null) {
            if ($user->hasIzin('manage_mitra_users')) {
                $data['grup_id'] = $user->grup_id;
            } else {
                $this->assertMitraGroupAllowed((int) $data['grup_id']);
            }
            $data['mitra_id'] = $actor->mitra_id;
        }
        $user->update($data);

        return back()->with('status', 'User diperbarui.');
    }

    public function toggleUser(Request $request, User $user): RedirectResponse
    {
        $this->assertUserInActorScope($request->user(), $user);
        abort_if($request->user()->mitra_id !== null && $request->user()->is($user), 422, 'User tidak dapat menonaktifkan dirinya sendiri.');
        if ($request->user()->mitra_id !== null && $user->aktif && $user->hasIzin('manage_mitra_users')) {
            $activeAdmins = User::query()
                ->where('mitra_id', $request->user()->mitra_id)
                ->where('aktif', true)
                ->whereHas('grup.izins', fn ($query) => $query->where('kode', 'manage_mitra_users'))
                ->count();
            abort_unless($activeAdmins > 1, 422, 'Admin Mitra terakhir tidak dapat dinonaktifkan.');
        }
        $user->update(['aktif' => ! $user->aktif]);

        return back()->with('status', $user->aktif ? 'User diaktifkan.' : 'User dinonaktifkan.');
    }

    public function resetCredentials(Request $request, User $user): RedirectResponse
    {
        $this->assertUserInActorScope($request->user(), $user);
        DB::transaction(function () use ($user): void {
            $password = Str::password(16);
            $user->update(['password' => $password]);
            app(WahaClient::class)->sendText($user->no_wa, "Kata sandi baru Project Monitoring System: {$password}");
        });

        return back()->with('status', 'Kata sandi baru dikirim melalui WhatsApp.');
    }

    public function deleteUser(Request $request, User $user, UserDeletionService $service): RedirectResponse
    {
        $actor = $request->user();
        $this->assertUserInActorScope($actor, $user);
        if ($actor->mitra_id !== null) {
            return back()->withErrors(['delete' => 'User Mitra dikurangi dengan menonaktifkan akun, bukan menghapus histori.']);
        }
        try {
            $service->delete($user, $request->user());
        } catch (SafeDeletionException $exception) {
            return back()->withErrors(['delete' => $exception->getMessage()])->with('delete_user_id', $user->id);
        }

        return back()->with('status', 'User dihapus.');
    }

    private function assertUserInActorScope(User $actor, User $target): void
    {
        abort_unless($actor->mitra_id === null || (int) $target->mitra_id === (int) $actor->mitra_id, 404);
    }

    private function assertMitraGroupAllowed(int $groupId): void
    {
        abort_unless(
            Grup::query()
                ->whereKey($groupId)
                ->where(fn ($query) => $query->whereNull('preset')->orWhere('preset', '!=', 'admin_mitra'))
                ->whereDoesntHave('izins', fn ($query) => $query->whereIn('kode', self::THC_ONLY_PERMISSIONS))
                ->exists(),
            422,
            'Grup tersebut hanya boleh digunakan oleh User THC.',
        );
    }

    public function onboardMitra(Request $request, MitraOnboardingService $service): RedirectResponse
    {
        $data = $request->validate(['kode' => ['nullable', 'string', 'max:255', 'unique:mitras,kode'], 'nama' => ['required', 'string', 'max:255'], 'admin_name' => ['required', 'string', 'max:255'], 'admin_email' => ['required', 'email', 'unique:users,email'], 'no_wa' => ['required', 'regex:/^62[0-9]{8,13}$/']]);
        $data['kode'] = trim((string) ($data['kode'] ?? '')) ?: null;
        $service->onboard($data);

        return back()->with('status', 'Mitra dan administrator berhasil dibuat.');
    }

    public function updateMitra(Request $request, Mitra $mitra, MitraCodeGenerator $codes): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:255', Rule::unique('mitras', 'kode')->ignore($mitra->id)],
            'nama' => ['required', 'string', 'max:255'],
        ]);
        if ($data['kode'] !== $mitra->kode && $codes->wasIssued($data['kode'])) {
            return back()->withErrors(['kode' => 'Kode Mitra otomatis yang pernah diterbitkan tidak dapat digunakan kembali.']);
        }
        $mitra->update($data);

        return back()->with('status', 'Mitra diperbarui.');
    }

    public function toggleMitra(Mitra $mitra): RedirectResponse
    {
        $mitra->update(['aktif' => ! $mitra->aktif]);

        return back()->with('status', $mitra->aktif ? 'Mitra diaktifkan.' : 'Mitra dinonaktifkan.');
    }

    public function deleteMitra(Mitra $mitra, MitraDeletionService $service): RedirectResponse
    {
        try {
            $service->delete($mitra);
        } catch (SafeDeletionException $exception) {
            return back()->withErrors(['delete' => $exception->getMessage()])->with('delete_mitra_id', $mitra->id);
        }

        return back()->with('status', 'Mitra dihapus.');
    }

    public function warehouses(): View
    {
        return view('admin.warehouses', [
            'warehouses' => Warehouse::with('users', 'mitra')->latest()->get(),
            'users' => User::where('aktif', true)->orderBy('name')->get(),
            'mitras' => Mitra::where('aktif', true)->orderBy('nama')->get(),
        ]);
    }

    public function materials(): View
    {
        return view('admin.materials', [
            'materials' => Material::with(['unit', 'stocks.warehouse'])->latest()->get(),
            'units' => Unit::query()->where('aktif', true)->orderBy('nama')->get(),
        ]);
    }

    public function createMaterial(Request $request, MasterCodeGenerator $codes): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['nullable', 'string', 'max:255'],
            'nama' => ['required', 'string', 'max:255'],
            'unit_id' => ['required', Rule::exists('units', 'id')->where('aktif', true)],
            'jenis' => ['required', Rule::in(['biasa', 'ber_sn', 'drum_kabel'])],
            'ambang_minimum' => ['nullable', 'numeric', 'min:0'],
        ]);
        $data['kode'] = $codes->normalize($data['kode'] ?? null);
        $this->assertManualCodeIsAllowed($codes, 'material', $data['kode']);
        $this->assertCodeIsUnique(Material::class, $data['kode']);
        DB::transaction(function () use ($data, $codes): void {
            $data['kode'] ??= $codes->generate('material', CarbonImmutable::now('Asia/Jakarta'));
            Material::create($data);
        });

        return back()->with('status', 'Material dibuat.');
    }

    public function updateMaterial(Request $request, Material $material, MasterCodeGenerator $codes): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:255'],
            'nama' => ['required', 'string', 'max:255'],
            'unit_id' => ['required', Rule::exists('units', 'id')->where(
                fn ($query) => $query->where('aktif', true)->orWhere('id', $material->unit_id)
            )],
            'jenis' => ['required', Rule::in(['biasa', 'ber_sn', 'drum_kabel'])],
            'ambang_minimum' => ['nullable', 'numeric', 'min:0'],
        ]);
        $data['kode'] = $codes->normalize($data['kode']);
        $original = $codes->normalize($material->kode);
        if ($data['kode'] !== $original && $original !== null && $codes->wasIssued('material', $original)) {
            throw ValidationException::withMessages(['kode' => 'Kode otomatis tidak dapat diubah setelah diterbitkan.']);
        }
        $this->assertManualCodeIsAllowed($codes, 'material', $data['kode'], $original);
        $this->assertCodeIsUnique(Material::class, $data['kode'], $material->id);
        $material->update($data);

        return back()->with('status', 'Material diperbarui.');
    }

    public function deactivateMaterial(Material $material): RedirectResponse
    {
        $material->update(['aktif' => false]);

        return back()->with('status', 'Material dinonaktifkan.');
    }

    public function createWarehouse(Request $request, MasterCodeGenerator $codes): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['nullable', 'string', 'max:255'],
            'nama' => ['required', 'string', 'max:255'],
            'mitra_id' => ['nullable', Rule::exists('mitras', 'id')->where('aktif', true)],
        ]);
        $data['kode'] = $codes->normalize($data['kode'] ?? null);
        $this->assertManualCodeIsAllowed($codes, 'warehouse', $data['kode']);
        $this->assertCodeIsUnique(Warehouse::class, $data['kode']);
        DB::transaction(function () use ($data, $codes): void {
            $data['kode'] ??= $codes->generate('warehouse', CarbonImmutable::now('Asia/Jakarta'));
            Warehouse::create([...$data, 'aktif' => true]);
        });

        return back()->with('status', 'Warehouse dibuat.');
    }

    public function updateWarehouse(Request $request, Warehouse $warehouse, MasterCodeGenerator $codes): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:255'],
            'nama' => ['required', 'string', 'max:255'],
            'mitra_id' => ['nullable', Rule::exists('mitras', 'id')->where('aktif', true)],
        ]);
        $data['kode'] = $codes->normalize($data['kode']);
        $original = $codes->normalize($warehouse->kode);
        if ($data['kode'] !== $original && $original !== null && $codes->wasIssued('warehouse', $original)) {
            throw ValidationException::withMessages(['kode' => 'Kode otomatis tidak dapat diubah setelah diterbitkan.']);
        }
        $this->assertManualCodeIsAllowed($codes, 'warehouse', $data['kode'], $original);
        $this->assertCodeIsUnique(Warehouse::class, $data['kode'], $warehouse->id);
        $warehouse->update($data);

        return back()->with('status', 'Warehouse diperbarui.');
    }

    public function deactivateWarehouse(Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update(['aktif' => false]);

        return back()->with('status', 'Warehouse dinonaktifkan.');
    }

    public function assignWarehouse(Request $request, Warehouse $warehouse): RedirectResponse
    {
        abort_unless($warehouse->aktif, 422, 'Warehouse nonaktif tidak dapat dipilih.');
        $data = $request->validate(['user_id' => ['required', Rule::exists('users', 'id')->where('aktif', true)]]);
        $warehouse->users()->syncWithoutDetaching([$data['user_id']]);

        return back()->with('status', 'Penugasan Warehouse disimpan.');
    }

    public function unassignWarehouse(Warehouse $warehouse, User $user): RedirectResponse
    {
        $warehouse->users()->detach($user);

        return back()->with('status', 'Penugasan Warehouse dihapus.');
    }

    public function wahaWebhook(Request $request): Response
    {
        $signature = hash_hmac('sha256', $request->getContent(), (string) config('waha.webhook_secret'));
        abort_unless(hash_equals($signature, (string) $request->header('X-WAHA-Signature')), 401);
        $payload = $request->json()->all();
        if (($payload['event'] ?? null) === 'session.status') {
            $session = $payload['session'] ?? config('waha.session', 'default');
            WhatsappSessionStatus::updateOrCreate(['session' => $session], ['status' => data_get($payload, 'payload.status', 'UNKNOWN'), 'payload' => $payload]);
        }

        return response()->noContent();
    }

    private function assertManualCodeIsAllowed(MasterCodeGenerator $codes, string $entity, ?string $code, ?string $original = null): void
    {
        if ($code !== null && $code !== $original && $codes->isAutomaticCode($entity, $code)) {
            throw ValidationException::withMessages(['kode' => 'Kode dengan pola otomatis hanya boleh diterbitkan generator.']);
        }
    }

    private function assertCodeIsUnique(string $model, ?string $code, ?int $ignoreId = null): void
    {
        if ($code === null) {
            return;
        }

        $query = $model::query()->where('kode', $code);
        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['kode' => 'Kode sudah digunakan.']);
        }
    }
}
