<?php

namespace App\Http\Controllers;

use App\Models\MitraHargaJasa;
use App\Services\MitraPriceBook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MitraPriceController extends Controller
{
    public function index(Request $request, MitraPriceBook $priceBook): View
    {
        $catalog = $priceBook->catalogFor($request->user());

        return view('mitra.prices', [
            'prices' => $priceBook->priceBookFor($request->user()),
            'jobs' => $catalog['jobs'],
            'pks' => $catalog['pks'],
        ]);
    }

    public function store(Request $request, MitraPriceBook $priceBook): RedirectResponse
    {
        $data = $request->validate([
            'pks_id' => ['required', 'integer'],
            'pekerjaan_jasa_id' => ['required', Rule::exists('pekerjaan_jasas', 'id')->where('aktif', true)],
            'harga' => ['required', 'numeric', 'gt:0'],
            'berlaku_mulai' => ['required', 'date'],
            'revisi_dari_id' => ['nullable', 'integer'],
        ]);
        $priceBook->submit($request->user(), $data);

        return back()->with('status', 'Harga Jasa Mitra diajukan untuk persetujuan THC.');
    }

    public function approve(Request $request, MitraHargaJasa $price, MitraPriceBook $priceBook): RedirectResponse
    {
        $priceBook->decide($request->user(), $price, 'disetujui');

        return back()->with('status', 'Harga Jasa Mitra disetujui.');
    }

    public function reject(Request $request, MitraHargaJasa $price, MitraPriceBook $priceBook): RedirectResponse
    {
        $priceBook->decide($request->user(), $price, 'ditolak');

        return back()->with('status', 'Harga Jasa Mitra ditolak.');
    }
}
