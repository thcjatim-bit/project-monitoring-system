<x-layouts.app>
    <main class="module-page">
        <style>
            .module-page { color: #172033; margin: 0 auto; max-width: 1180px; padding: 32px 22px 68px; }
            .module-page h1 { color: #15324b; letter-spacing: -.04em; margin: 18px 0 7px; }
            .module-page h2 { color: #15324b; font-size: 1.1rem; margin: 24px 0 8px; }
            .module-page p { color: #687684; line-height: 1.5; }
            .module-page__back { color: #687684; font-size: .86rem; text-decoration: none; }
            .module-page__card { background: #fff; border: 1px solid #dce4e8; border-radius: 13px; margin-top: 18px; padding: 19px; }
            .module-page__table { border-collapse: collapse; margin-top: 14px; width: 100%; }
            .module-page__table th, .module-page__table td { border-bottom: 1px solid #e8edef; padding: 9px 7px; text-align: left; vertical-align: top; }
            .module-page__table th { color: #687684; font-size: .72rem; text-transform: uppercase; }
            .module-page__table input, .module-page__table select { border: 1px solid #cbd6dc; border-radius: 6px; max-width: 120px; min-height: 34px; padding: 6px; }
            .module-page__button { background: #087f8c; border: 1px solid #087f8c; border-radius: 8px; color: #fff; display: inline-block; font-size: .82rem; font-weight: 700; padding: 9px 13px; }
            .module-page__button--muted { background: #fff; border-color: #cbd6dc; color: #15324b; }
            .module-page__actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; }
            @media (max-width: 760px) { .module-page { padding: 24px 16px 50px; } .module-page__table { display: block; overflow-x: auto; } }
        </style>

        <a class="module-page__back" href="{{ route('projects.rekons.index', $rekon->project) }}">← Kembali ke daftar Rekon Material</a>
        <h1>Detail Rekon Material</h1>
        <p>{{ $rekon->project?->id_project }} · {{ $rekon->nomor }} · {{ $rekon->source }} · status {{ $rekon->status }}</p>
        @if (session('status')) <p role="status">{{ session('status') }}</p> @endif
        @if ($errors->any()) <div role="alert"><strong>Periksa Rekon Material.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif

        <section class="module-page__card">
            <h2>Rincian Material</h2>
            @if ($rekon->items->isEmpty())
                <p role="status">Belum ada baris material pada Rekon ini.</p>
            @else
                <form method="POST" action="{{ route('project-rekons.update', $rekon) }}">
                    @csrf @method('PATCH')
                    <table class="module-page__table">
                        <thead><tr><th>Material</th><th>Keluar</th><th>Terpasang</th><th>Sisa</th><th>Kembali</th><th>Hilang/Rusak</th><th>Kategori / PJ</th></tr></thead>
                        <tbody>
                            @foreach ($rekon->items as $item)
                                <tr>
                                    <td>{{ $item->material?->nama ?? 'Material' }}<input type="hidden" name="items[{{ $item->id }}][id]" value="{{ $item->id }}"></td>
                                    <td>{{ number_format((float) $item->keluar_gudang, 3, '.', '') }}<input type="hidden" name="items[{{ $item->id }}][keluar_gudang]" value="{{ $item->keluar_gudang }}"></td>
                                    <td>{{ number_format((float) $item->terpasang, 3, '.', '') }}<input type="hidden" name="items[{{ $item->id }}][terpasang]" value="{{ $item->terpasang }}"></td>
                                    <td>{{ number_format((float) $item->sisa_project, 3, '.', '') }}<input type="hidden" name="items[{{ $item->id }}][sisa_project]" value="{{ $item->sisa_project }}"></td>
                                    <td><input name="items[{{ $item->id }}][dikembalikan]" value="{{ $item->dikembalikan }}" type="number" min="0" step="1"></td>
                                    <td><input name="items[{{ $item->id }}][hilang_rusak]" value="{{ $item->hilang_rusak }}" type="number" min="0" step="1"></td>
                                    <td><select name="items[{{ $item->id }}][kategori_hilang_rusak]"><option value="">—</option>@foreach (['hilang', 'rusak', 'waste_wajar'] as $category)<option value="{{ $category }}" @selected($item->kategori_hilang_rusak === $category)>{{ $category }}</option>@endforeach</select><select name="items[{{ $item->id }}][penanggung_jawab]"><option value="mitra" @selected($item->penanggung_jawab === 'mitra')>Mitra</option><option value="thc" @selected($item->penanggung_jawab === 'thc')>THC</option></select></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($rekon->status === 'diajukan' && auth()->user()->mitra_id === null && auth()->user()->hasIzin('edit_material_rekon'))
                        <label>Catatan <input name="catatan" maxlength="2000"></label>
                        <div class="module-page__actions"><button class="module-page__button" type="submit">Simpan Draft Rekon</button></div>
                    @endif
                </form>
            @endif
            @if ($rekon->status === 'diajukan' && auth()->user()->mitra_id === null && auth()->user()->hasIzin('approve_material_rekon'))
                <div class="module-page__actions">
                    <form method="POST" action="{{ route('project-rekons.approve', $rekon) }}">@csrf @method('PATCH')<button class="module-page__button" type="submit">Setujui Rekon Material</button></form>
                    <form method="POST" action="{{ route('project-rekons.reject', $rekon) }}">@csrf @method('PATCH')<button class="module-page__button module-page__button--muted" type="submit">Tolak Rekon Material</button></form>
                </div>
            @endif
        </section>
    </main>
</x-layouts.app>
