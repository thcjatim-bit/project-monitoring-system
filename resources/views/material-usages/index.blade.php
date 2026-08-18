<x-layouts.app>
    <main>
        <h1>Pemakaian Material</h1>

        @if (session('status')) <p>{{ session('status') }}</p> @endif

        @if (auth()->user()->mitra_id !== null && auth()->user()->hasIzin('create_material_usage'))
            <h2>Ajukan Pemakaian Material</h2>
            @forelse ($projects as $project)
                <form method="POST" action="{{ route('projects.material-usages.store', $project) }}">
                    @csrf
                    <fieldset>
                        <legend>{{ $project->id_project }} — {{ $project->nama }}</legend>
                        <label>Warehouse
                            <select name="warehouse_id" required>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->nama }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Material
                            <select name="material_id" required>
                                @foreach ($materials as $material)
                                    <option value="{{ $material->id }}">{{ $material->kode }} — {{ $material->nama }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Qty <input name="qty" type="number" min="0.001" step="0.001" required></label>
                        <label>Catatan <input name="catatan" maxlength="2000"></label>
                        <button type="submit">Ajukan</button>
                    </fieldset>
                </form>
            @empty
                <p>Belum ada Project aktif untuk Mitra ini.</p>
            @endforelse
        @endif

        <h2>Daftar Pemakaian</h2>
        <ul>
            @forelse ($usages as $usage)
                <li>
                    <strong>#{{ $usage->id }}</strong> — {{ $usage->status }} — {{ $usage->material?->nama }} {{ $usage->qty }}
                    @if ($usage->project) — {{ $usage->project->id_project }} @endif
                    @if (auth()->user()->mitra_id !== null && auth()->user()->hasIzin('create_material_usage') && $usage->status === 'diajukan')
                        <form method="POST" action="{{ route('material-usages.cancel', $usage) }}" style="display:inline">
                            @csrf @method('PATCH') <button type="submit">Batalkan</button>
                        </form>
                    @endif
                    @if (auth()->user()->mitra_id === null && auth()->user()->hasIzin('approve_material_usage') && $usage->status === 'diajukan')
                        <form method="POST" action="{{ route('material-usages.approve', $usage) }}" style="display:inline">
                            @csrf @method('PATCH') <button type="submit">Setujui</button>
                        </form>
                        <form method="POST" action="{{ route('material-usages.reject', $usage) }}" style="display:inline">
                            @csrf @method('PATCH') <button type="submit">Tolak</button>
                        </form>
                    @endif
                </li>
            @empty
                <li>Belum ada Pemakaian Material.</li>
            @endforelse
        </ul>
    </main>
</x-layouts.app>
