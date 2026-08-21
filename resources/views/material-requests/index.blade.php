<x-layouts.app>
    <x-ui.page>
        <x-ui.page-header
            eyebrow="ALUR MATERIAL"
            title="Request Material"
            subtitle="Pantau kebutuhan Material Project dari pengajuan hingga fulfillment."
        />

        @if (session('status'))
            <div class="ui-state ui-state--success" role="status">{{ session('status') }}</div>
        @endif

        @if (auth()->user()->mitra_id !== null && auth()->user()->hasIzin('create_material_request'))
            <x-ui.panel>
                <h2>Ajukan Request Material</h2>
                <form class="ui-form" method="POST" action="{{ route('material-requests.store') }}">
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
                    <button class="ui-button ui-button--muted" type="button" id="add-material-request-item">Tambah Material</button>
                    <label>Catatan <textarea name="catatan"></textarea></label>
                    <button class="ui-button" type="submit">Ajukan Request</button>
                </form>
            </x-ui.panel>
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

        <x-ui.panel>
            <div class="ui-section-head">
                <div>
                    <h2>Daftar Request</h2>
                    <p class="ui-help">Project dan Mitra menunjukkan konteks kebutuhan Material.</p>
                </div>
            </div>
            <div class="ui-list">
                @forelse ($requests as $materialRequest)
                    <article class="ui-list__item" data-request-id="{{ $materialRequest->id }}">
                        <div class="ui-section-head">
                            <div>
                                <h3>Request Material #{{ $materialRequest->id }}</h3>
                                <p class="ui-help">
                                    Project:
                                    @if ($materialRequest->project)
                                        @if (auth()->user()->hasIzin('read_project'))
                                            <a href="{{ route('projects.show', $materialRequest->project) }}">{{ $materialRequest->project->id_project }} — {{ $materialRequest->project->nama }}</a>
                                        @else
                                            {{ $materialRequest->project->id_project }} — {{ $materialRequest->project->nama }}
                                        @endif
                                    @else
                                        <span>Tanpa Project</span>
                                    @endif
                                </p>
                                <p class="ui-help">Mitra: {{ $materialRequest->mitra?->nama ?? 'Mitra tidak tersedia' }}</p>
                            </div>
                            <x-material-request-status :status="$materialRequest->status" />
                        </div>
                        <div class="ui-table-wrap">
                            <table class="ui-table">
                                <caption class="ui-sr-only">Material pada Request Material #{{ $materialRequest->id }}</caption>
                                <thead><tr><th>Material</th><th>Qty Request</th><th>Unit</th></tr></thead>
                                <tbody>
                                    @forelse ($materialRequest->items as $item)
                                        <tr>
                                            <td>{{ $item->material?->nama ?? 'Material tidak tersedia' }}</td>
                                            <td>{{ \App\Support\QuantityDisplayFormatter::format($item->qty) }}</td>
                                            <td>{{ $item->material?->unit?->nama ?? '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3">Belum ada item material.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="ui-form__actions">
                            <a class="ui-button ui-button--muted" href="{{ route('material-requests.show', $materialRequest) }}">Lihat detail</a>
                            @if (auth()->user()->mitra_id === null && auth()->user()->hasIzin('approve_material_request') && $materialRequest->status === 'diajukan')
                                <form method="POST" action="{{ route('material-requests.approve', $materialRequest) }}" style="display:inline">
                                    @csrf @method('PATCH') <button class="ui-button" type="submit">Setujui</button>
                                </form>
                                <form method="POST" action="{{ route('material-requests.reject', $materialRequest) }}" style="display:inline">
                                    @csrf @method('PATCH') <button class="ui-button ui-button--danger" type="submit">Tolak</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @empty
                    <x-ui.empty-state title="Belum ada Request Material." message="Request Material yang dibuat dari kebutuhan Project akan muncul di sini." />
                @endforelse
            </div>
        </x-ui.panel>
    </x-ui.page>
</x-layouts.app>
