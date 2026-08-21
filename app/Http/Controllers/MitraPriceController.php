<?php

namespace App\Http\Controllers;

use App\Models\MitraHargaJasa;
use App\Models\PekerjaanJasa;
use App\Models\Pks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MitraPriceController extends Controller
{
    public function index(Request $request): View
    {
        $mitraId = $request->user()->mitra_id;

        return view('mitra.prices', [
            'prices' => MitraHargaJasa::query()->with('pekerjaanJasa', 'pks')->latest()->get(),
            'jobs' => PekerjaanJasa::query()->where('aktif', true)->orderBy('nama')->get(),
            'pks' => Pks::query()
                ->whereDate('tanggal_mulai', '<=', today())
                ->where(fn ($query) => $query->whereNull('tanggal_berakhir')->orWhereDate('tanggal_berakhir', '>=', today()))
                ->where('mitra_id', $mitraId)
                ->orderByDesc('tanggal_mulai')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pks_id' => ['required', 'integer'],
            'pekerjaan_jasa_id' => ['required', Rule::exists('pekerjaan_jasas', 'id')->where('aktif', true)],
            'harga' => ['required', 'numeric', 'gt:0'],
            'berlaku_mulai' => ['required', 'date'],
            'revisi_dari_id' => ['nullable', 'integer'],
        ]);
        $pks = Pks::query()
            ->whereKey($data['pks_id'])
            ->where('mitra_id', $request->user()->mitra_id)
            ->whereDate('tanggal_mulai', '<=', $data['berlaku_mulai'])
            ->where(fn ($query) => $query->whereNull('tanggal_berakhir')->orWhereDate('tanggal_berakhir', '>=', $data['berlaku_mulai']))
            ->firstOrFail();
        $revision = null;
        if (! empty($data['revisi_dari_id'])) {
            $revision = MitraHargaJasa::query()->whereKey($data['revisi_dari_id'])->firstOrFail();
            abort_unless((int) $revision->mitra_id === (int) $request->user()->mitra_id, 404);
        }

        MitraHargaJasa::query()->create([
            'mitra_id' => $request->user()->mitra_id,
            'pks_id' => $pks->id,
            'pekerjaan_jasa_id' => $data['pekerjaan_jasa_id'],
            'harga' => $data['harga'],
            'status' => 'diajukan',
            'berlaku_mulai' => $data['berlaku_mulai'],
            'diajukan_oleh' => $request->user()->id,
            'revisi_dari_id' => $revision?->id,
        ]);

        return back()->with('status', 'Harga Jasa Mitra diajukan untuk persetujuan THC.');
    }

    public function approve(Request $request, MitraHargaJasa $price): RedirectResponse
    {
        if ($price->status !== 'diajukan') {
            throw ValidationException::withMessages(['status' => 'Harga Jasa Mitra sudah diputuskan.']);
        }

        $price->update([
            'status' => 'disetujui',
            'diputuskan_oleh' => $request->user()->id,
            'diputuskan_at' => now(),
        ]);

        return back()->with('status', 'Harga Jasa Mitra disetujui.');
    }

    public function reject(Request $request, MitraHargaJasa $price): RedirectResponse
    {
        if ($price->status !== 'diajukan') {
            throw ValidationException::withMessages(['status' => 'Harga Jasa Mitra sudah diputuskan.']);
        }

        $price->update([
            'status' => 'ditolak',
            'diputuskan_oleh' => $request->user()->id,
            'diputuskan_at' => now(),
        ]);

        return back()->with('status', 'Harga Jasa Mitra ditolak.');
    }
}
