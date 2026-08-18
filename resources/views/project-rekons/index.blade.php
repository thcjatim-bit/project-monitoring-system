<x-layouts.app>
    <main>
        <h1>Rekon Material: {{ $project->id_project }}</h1>

        @if (session('status')) <p>{{ session('status') }}</p> @endif

        @if (auth()->user()->mitra_id === null && auth()->user()->hasIzin('create_material_rekon'))
            <form method="POST" action="{{ route('projects.rekons.store', $project) }}">
                @csrf
                <label>Catatan pembukaan <input name="catatan" maxlength="2000"></label>
                <button type="submit">Buka Rekon Manual</button>
            </form>
        @endif

        @forelse ($rekons as $rekon)
            <article>
                <h2>{{ $rekon->nomor }} — {{ $rekon->source }} — {{ $rekon->status }}</h2>
                @if ($rekon->koreksi_dari_id)
                    <p>Koreksi Rekon #{{ $rekon->koreksi_dari_id }}</p>
                @endif
                <table>
                    <thead>
                        <tr><th>Material</th><th>Keluar</th><th>Terpasang</th><th>Sisa</th><th>Kembali</th><th>Hilang/Rusak</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($rekon->items as $item)
                            <tr>
                                <td>{{ $item->material?->nama }}</td>
                                <td>{{ $item->keluar_gudang }}</td>
                                <td>{{ $item->terpasang }}</td>
                                <td>{{ $item->sisa_project }}</td>
                                <td>{{ $item->dikembalikan }}</td>
                                <td>{{ $item->hilang_rusak }} {{ $item->kategori_hilang_rusak }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if ($rekon->status === 'diajukan' && auth()->user()->mitra_id === null)
                    @if (auth()->user()->hasIzin('edit_material_rekon'))
                        <form method="POST" action="{{ route('project-rekons.update', $rekon) }}">
                            @csrf @method('PATCH')
                            @foreach ($rekon->items as $item)
                                <input type="hidden" name="items[{{ $item->id }}][id]" value="{{ $item->id }}">
                                <label>{{ $item->material?->nama }} kembali
                                    <input name="items[{{ $item->id }}][dikembalikan]" value="{{ $item->dikembalikan }}" type="number" step="0.001" min="0">
                                </label>
                                <label>hilang/rusak
                                    <input name="items[{{ $item->id }}][hilang_rusak]" value="{{ $item->hilang_rusak }}" type="number" step="0.001" min="0">
                                </label>
                                <input type="hidden" name="items[{{ $item->id }}][keluar_gudang]" value="{{ $item->keluar_gudang }}">
                                <input type="hidden" name="items[{{ $item->id }}][terpasang]" value="{{ $item->terpasang }}">
                                <input type="hidden" name="items[{{ $item->id }}][sisa_project]" value="{{ $item->sisa_project }}">
                                <input type="hidden" name="items[{{ $item->id }}][penanggung_jawab]" value="{{ $item->penanggung_jawab }}">
                            @endforeach
                            <label>Catatan <input name="catatan" maxlength="2000"></label>
                            <button type="submit">Simpan Draft</button>
                        </form>
                    @endif
                    @if (auth()->user()->hasIzin('approve_material_rekon'))
                        <form method="POST" action="{{ route('project-rekons.approve', $rekon) }}" style="display:inline">
                            @csrf @method('PATCH') <button type="submit">Setujui</button>
                        </form>
                        <form method="POST" action="{{ route('project-rekons.reject', $rekon) }}" style="display:inline">
                            @csrf @method('PATCH') <button type="submit">Tolak</button>
                        </form>
                    @endif
                @endif
            </article>
        @empty
            <p>Belum ada Rekon Material.</p>
        @endforelse
    </main>
</x-layouts.app>
