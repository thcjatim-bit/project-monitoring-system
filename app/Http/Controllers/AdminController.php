<?php

namespace App\Http\Controllers;

use App\Contracts\WahaClient;
use App\Models\Grup;
use App\Models\Material;
use App\Models\Mitra;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WhatsappSessionStatus;
use App\Services\MitraOnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function users(): View
    {
        return view('admin.users', ['users' => User::with(['mitra', 'grup'])->latest()->get(), 'grups' => Grup::orderBy('nama')->get()]);
    }

    public function createUser(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'no_wa' => ['required', 'regex:/^62[0-9]{8,13}$/'],
            'mitra_id' => ['nullable', Rule::exists('mitras', 'id')->where('aktif', true)],
            'grup_id' => ['required', 'exists:grups,id'],
        ]);
        DB::transaction(function () use ($data): void {
            $password = Str::password(16);
            $user = User::create([...$data, 'password' => $password, 'aktif' => true]);
            app(WahaClient::class)->sendText($user->no_wa, "Akun Project Monitoring System\nEmail: {$user->email}\nKata sandi: {$password}");
        });

        return back()->with('status', 'User dibuat dan kredensial dikirim melalui WhatsApp.');
    }

    public function toggleUser(User $user): RedirectResponse
    {
        $user->update(['aktif' => ! $user->aktif]);

        return back()->with('status', $user->aktif ? 'User diaktifkan.' : 'User dinonaktifkan.');
    }

    public function resetCredentials(User $user): RedirectResponse
    {
        DB::transaction(function () use ($user): void {
            $password = Str::password(16);
            $user->update(['password' => $password]);
            app(WahaClient::class)->sendText($user->no_wa, "Kata sandi baru Project Monitoring System: {$password}");
        });

        return back()->with('status', 'Kata sandi baru dikirim melalui WhatsApp.');
    }

    public function onboardMitra(Request $request, MitraOnboardingService $service): RedirectResponse
    {
        $data = $request->validate(['kode' => ['required', 'string', 'max:255', 'unique:mitras,kode'], 'nama' => ['required', 'string', 'max:255'], 'admin_name' => ['required', 'string', 'max:255'], 'admin_email' => ['required', 'email', 'unique:users,email'], 'no_wa' => ['required', 'regex:/^62[0-9]{8,13}$/']]);
        $service->onboard($data);

        return back()->with('status', 'Mitra dan administrator berhasil dibuat.');
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
            'materials' => Material::with('unit')->latest()->get(),
            'units' => Unit::query()->where('aktif', true)->orderBy('nama')->get(),
        ]);
    }

    public function createMaterial(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:255', 'unique:materials,kode'],
            'nama' => ['required', 'string', 'max:255'],
            'unit_id' => ['required', Rule::exists('units', 'id')->where('aktif', true)],
            'jenis' => ['required', Rule::in(['biasa', 'ber_sn', 'drum_kabel'])],
        ]);
        Material::create($data);

        return back()->with('status', 'Material dibuat.');
    }

    public function updateMaterial(Request $request, Material $material): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:255', Rule::unique('materials', 'kode')->ignore($material->id)],
            'nama' => ['required', 'string', 'max:255'],
            'unit_id' => ['required', Rule::exists('units', 'id')->where('aktif', true)],
            'jenis' => ['required', Rule::in(['biasa', 'ber_sn', 'drum_kabel'])],
        ]);
        $material->update($data);

        return back()->with('status', 'Material diperbarui.');
    }

    public function deactivateMaterial(Material $material): RedirectResponse
    {
        $material->update(['aktif' => false]);

        return back()->with('status', 'Material dinonaktifkan.');
    }

    public function createWarehouse(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:255', 'unique:warehouses,kode'],
            'nama' => ['required', 'string', 'max:255'],
            'mitra_id' => ['nullable', Rule::exists('mitras', 'id')->where('aktif', true)],
        ]);
        Warehouse::create([...$data, 'aktif' => true]);

        return back()->with('status', 'Warehouse dibuat.');
    }

    public function updateWarehouse(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:255', Rule::unique('warehouses', 'kode')->ignore($warehouse->id)],
            'nama' => ['required', 'string', 'max:255'],
            'mitra_id' => ['nullable', Rule::exists('mitras', 'id')->where('aktif', true)],
        ]);
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
}
