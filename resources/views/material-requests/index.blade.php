<x-layouts.app>
    <main>
        <h1>Request Material</h1>

        @if (session('status')) <p>{{ session('status') }}</p> @endif

        @if (auth()->user()->mitra_id !== null && auth()->user()->hasIzin('create_material_request'))
            <h2>Ajukan Request Material</h2>
            <form method="POST" action="{{ route('material-requests.store') }}">
                @csrf
                <label>Project (opsional)
                    <select name="project_id">
                        <option value="">Tanpa Project</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->id_project }} — {{ $project->nama }}</option>
                        @endforeach
                    </select>
                </label>
                <div id="material-request-items">
                    <div class="material-request-item">
                        <label>Material
                            <select name="items[0][material_id]" required>
                                <option value="">Pilih Material</option>
                                @foreach ($materials as $material)
                                    <option value="{{ $material->id }}">{{ $material->kode }} — {{ $material->nama }} ({{ $material->unit->nama }})</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Qty <input name="items[0][qty]" type="number" min="0.001" step="0.001" required></label>
                    </div>
                </div>
                <button type="button" id="add-material-request-item">Tambah Material</button>
                <label>Catatan <textarea name="catatan"></textarea></label>
                <button type="submit">Ajukan Request</button>
            </form>
            <template id="material-request-item-template">
                <div class="material-request-item">
                    <label>Material
                        <select name="items[__INDEX__][material_id]" required>
                            <option value="">Pilih Material</option>
                            @foreach ($materials as $material)
                                <option value="{{ $material->id }}">{{ $material->kode }} — {{ $material->nama }} ({{ $material->unit->nama }})</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Qty <input name="items[__INDEX__][qty]" type="number" min="0.001" step="0.001" required></label>
                </div>
            </template>
            <script>
                (() => {
                    let index = 1;
                    const button = document.getElementById('add-material-request-item');
                    const list = document.getElementById('material-request-items');
                    const template = document.getElementById('material-request-item-template');
                    button?.addEventListener('click', () => {
                        list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index++)));
                    });
                })();
            </script>
        @endif

        <h2>Daftar Request</h2>
        <ul>
            @forelse ($requests as $materialRequest)
                <li>
                    <strong>#{{ $materialRequest->id }}</strong> — {{ $materialRequest->status }}
                    @if ($materialRequest->mitra) — {{ $materialRequest->mitra->nama }} @endif
                    <ul>
                        @foreach ($materialRequest->items as $item)
                            <li>{{ $item->material->nama }}: {{ $item->qty }} {{ $item->material->unit->nama }}</li>
                        @endforeach
                    </ul>
                    @if (auth()->user()->mitra_id === null && auth()->user()->hasIzin('approve_material_request') && $materialRequest->status === 'diajukan')
                        <form method="POST" action="{{ route('material-requests.approve', $materialRequest) }}" style="display:inline">
                            @csrf @method('PATCH') <button type="submit">Setujui</button>
                        </form>
                        <form method="POST" action="{{ route('material-requests.reject', $materialRequest) }}" style="display:inline">
                            @csrf @method('PATCH') <button type="submit">Tolak</button>
                        </form>
                    @endif
                </li>
            @empty
                <li>Belum ada Request Material.</li>
            @endforelse
        </ul>
    </main>
</x-layouts.app>
