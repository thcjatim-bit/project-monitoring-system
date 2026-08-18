<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\Mitra;
use App\Services\ApiKeyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiKeyController extends Controller
{
    public function index(): View
    {
        return view('admin.api-keys', [
            'apiKeys' => ApiKey::query()->with('mitra')->latest()->get(),
            'mitras' => Mitra::query()->where('aktif', true)->orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request, ApiKeyService $service): View
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mitra_id' => ['nullable', 'integer', 'exists:mitras,id'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);
        if (($data['mitra_id'] ?? null) !== null) {
            abort_unless(Mitra::query()->whereKey($data['mitra_id'])->where('aktif', true)->exists(), 422, 'Mitra nonaktif tidak dapat dipakai untuk API Key.');
        }

        $credential = $service->create(
            actor: $request->user(),
            name: $data['name'],
            mitraId: isset($data['mitra_id']) ? (int) $data['mitra_id'] : null,
            expiresInDays: (int) ($data['expires_in_days'] ?? ApiKeyService::DEFAULT_EXPIRY_DAYS),
        );

        return view('admin.api-keys-reveal', [
            'apiKey' => $credential->apiKey,
            'plaintext' => $credential->plaintext,
        ]);
    }

    public function revoke(Request $request, ApiKey $apiKey, ApiKeyService $service): RedirectResponse
    {
        $service->revoke($apiKey, $request->user());

        return redirect()->route('admin.api-keys.index')->with('status', 'API Key dicabut.');
    }

    public function rotate(Request $request, ApiKey $apiKey, ApiKeyService $service): View
    {
        $credential = $service->rotate($apiKey, $request->user());

        return view('admin.api-keys-reveal', [
            'apiKey' => $credential->apiKey,
            'plaintext' => $credential->plaintext,
            'rotation' => true,
        ]);
    }
}
