<?php

namespace App\Http\Controllers;

use App\Enums\MasterKind;
use App\Exceptions\SafeDeletionException;
use App\Models\Material;
use App\Models\Mitra;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WhatsappSessionStatus;
use App\Services\CodedMasterLifecycle;
use App\Services\MitraCodeGenerator;
use App\Services\MitraDeletionService;
use App\Services\MitraOnboardingService;
use App\Services\MitraUserAdministration;
use App\Services\UserDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function users(Request $request, MitraUserAdministration $administration): View
    {
        $roster = $administration->rosterFor($request->user());

        return view('admin.users', [
            ...$roster,
        ]);
    }

    public function mitras(): View
    {
        return view('admin.mitras', ['mitras' => Mitra::with('adminMitraPertama')->latest()->get()]);
    }

    public function createUser(Request $request, MitraUserAdministration $administration): RedirectResponse
    {
        $actor = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'no_wa' => ['required', 'regex:/^62[0-9]{8,13}$/'],
            'mitra_id' => ['nullable', Rule::exists('mitras', 'id')->where('aktif', true)],
            'grup_id' => ['required', 'exists:grups,id'],
        ]);
        $administration->create($actor, $data);

        return back()->with('status', 'User dibuat dan kredensial dikirim melalui WhatsApp.');
    }

    public function updateUser(Request $request, User $user, MitraUserAdministration $administration): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'no_wa' => ['required', 'regex:/^62[0-9]{8,13}$/'],
            'mitra_id' => ['nullable', Rule::exists('mitras', 'id')->where(
                fn ($query) => $query->where('aktif', true)->orWhere('id', $user->mitra_id)
            )],
            'grup_id' => ['required', 'exists:grups,id'],
        ]);
        $administration->update($request->user(), $user, $data);

        return back()->with('status', 'User diperbarui.');
    }

    public function toggleUser(Request $request, User $user, MitraUserAdministration $administration): RedirectResponse
    {
        $administration->toggle($request->user(), $user);

        return back()->with('status', $user->aktif ? 'User diaktifkan.' : 'User dinonaktifkan.');
    }

    public function resetCredentials(Request $request, User $user, MitraUserAdministration $administration): RedirectResponse
    {
        $administration->resetCredentials($request->user(), $user);

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

    public function createMaterial(Request $request, CodedMasterLifecycle $lifecycle): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['nullable', 'string', 'max:255'],
            'nama' => ['required', 'string', 'max:255'],
            'unit_id' => ['required', Rule::exists('units', 'id')->where('aktif', true)],
            'jenis' => ['required', Rule::in(['biasa', 'ber_sn', 'drum_kabel'])],
            'ambang_minimum' => ['nullable', 'numeric', 'min:0'],
        ]);
        $lifecycle->create($request->user(), MasterKind::Material, $data);

        return back()->with('status', 'Material dibuat.');
    }

    public function updateMaterial(Request $request, Material $material, CodedMasterLifecycle $lifecycle): RedirectResponse
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
        $lifecycle->update($request->user(), $material, $data);

        return back()->with('status', 'Material diperbarui.');
    }

    public function deactivateMaterial(Request $request, Material $material, CodedMasterLifecycle $lifecycle): RedirectResponse
    {
        $lifecycle->deactivate($request->user(), $material);

        return back()->with('status', 'Material dinonaktifkan.');
    }

    public function createWarehouse(Request $request, CodedMasterLifecycle $lifecycle): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['nullable', 'string', 'max:255'],
            'nama' => ['required', 'string', 'max:255'],
            'mitra_id' => ['nullable', Rule::exists('mitras', 'id')->where('aktif', true)],
        ]);
        $lifecycle->create($request->user(), MasterKind::Warehouse, $data);

        return back()->with('status', 'Warehouse dibuat.');
    }

    public function updateWarehouse(Request $request, Warehouse $warehouse, CodedMasterLifecycle $lifecycle): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:255'],
            'nama' => ['required', 'string', 'max:255'],
            'mitra_id' => ['nullable', Rule::exists('mitras', 'id')->where('aktif', true)],
        ]);
        $lifecycle->update($request->user(), $warehouse, $data);

        return back()->with('status', 'Warehouse diperbarui.');
    }

    public function deactivateWarehouse(Request $request, Warehouse $warehouse, CodedMasterLifecycle $lifecycle): RedirectResponse
    {
        $lifecycle->deactivate($request->user(), $warehouse);

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
}
