<x-layouts.app>
    <x-ui.page>
        <x-ui.page-header
            eyebrow="Project Planning Workspace"
            title="Workspace Perencanaan Project"
            subtitle="{{ $project->id_project }} · {{ $project->nama }} · Mitra: {{ $project->mitra->nama }}"
        >
            <x-slot:actions>
                <a class="ui-button ui-button--muted" href="{{ route('projects.show', $project) }}">Control Room</a>
                <a class="ui-button ui-button--muted" href="#rab-jasa">RAB Jasa</a>
                <a class="ui-button ui-button--muted" href="#baseline-toc">Baseline / TOC</a>
                <a class="ui-button ui-button--muted" href="#variation-orders">Variation Order</a>
                @if (auth()->user()->hasIzin('read_project_timeline'))
                    <a class="ui-button ui-button--muted" href="{{ route('projects.timeline.index', $project) }}">Linimasa</a>
                @endif
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('status'))
            <div class="ui-state" role="status">{{ session('status') }}</div>
        @endif
        <x-form-errors />

        <div class="ui-grid">
            <x-ui.panel class="ui-panel--wide" id="rab-jasa">
                <h2>RAB Jasa</h2>
                <p>Harga satuan dibekukan ketika baris RAB dibuat. Perubahan setelah Baseline terbit dicatat melalui Variation Order.</p>

                @if ($rabJasas->isEmpty())
                    <x-ui.empty-state title="Belum ada RAB Jasa" message="Tambahkan RAB awal sebelum Baseline diterbitkan." />
                @else
                    <div class="ui-table-wrap"><table class="ui-table">
                        <thead><tr><th>Pekerjaan Jasa</th><th>Qty</th><th>Harga satuan beku</th><th>Total nilai</th></tr></thead>
                        <tbody>@foreach ($rabJasas as $rab)<tr>
                            <td>{{ $rab->pekerjaanJasa?->nama ?? 'Pekerjaan Jasa' }} @if ($rab->variation_order_id)<small>Dari Variation Order #{{ $rab->variation_order_id }}</small>@endif<details><summary>Detail RAB Jasa</summary><small>Baris #{{ $rab->id }} · dibuat oleh User #{{ $rab->dibuat_oleh ?? '-' }}</small></details></td>
                            <td>{{ number_format((float) $rab->qty, 3, '.', '') }}</td>
                            <td>Rp {{ number_format((float) $rab->harga_satuan, 2, ',', '.') }}</td>
                            <td>Rp {{ number_format((float) $rab->total_nilai, 2, ',', '.') }}</td>
                        </tr>@endforeach</tbody>
                    </table></div>
                @endif

                @if ($rabFrozen)
                    <div class="ui-state" role="status"><strong>RAB Jasa sudah dibekukan.</strong> Perubahan berikutnya harus diajukan melalui Variation Order.</div>
                @elseif ($canAddInitialRab)
                    @if ($prices->isEmpty())
                        <x-ui.empty-state title="Harga Jasa belum tersedia" message="Belum ada Harga Jasa Mitra disetujui yang dapat dipakai." />
                    @else
                        <form class="ui-form" method="POST" action="{{ route('projects.rab-jasa.store', $project) }}">
                            @csrf
                            <label for="rab_harga_jasa_id">Pekerjaan Jasa dari Harga Mitra</label>
                            <x-ui.searchable-select name="harga_jasa_id" id="rab_harga_jasa_id" :options="$priceOptions" :value="old('harga_jasa_id')" placeholder="Pilih Harga Jasa" />
                            <label>Qty RAB Jasa <input name="qty" type="number" min="0.001" step="0.001" value="{{ old('qty') }}" required></label>
                            <div class="ui-form__actions"><button class="ui-button" type="submit">Tambah RAB Jasa</button></div>
                        </form>
                    @endif
                @endif
            </x-ui.panel>

            <x-ui.panel id="baseline-toc">
                <h2>Baseline / TOC</h2>
                <p>Baseline pertama menjadi Original; publikasi berikutnya menjadi Revised tanpa mengubah snapshot sebelumnya.</p>
                @if ($baselines->isEmpty())
                    <x-ui.empty-state title="Belum ada Baseline" message="Baseline pertama akan menjadi Original Baseline." />
                @else
                    <div class="ui-table-wrap"><table class="ui-table">
                        <thead><tr><th>Jenis / versi</th><th>TOC</th><th>Titik rencana</th></tr></thead>
                        <tbody>@foreach ($baselines as $baseline)<tr>
                            <td>{{ $baseline->kind === 'original' ? 'Original Baseline' : 'Revised Baseline' }} v{{ $baseline->version }}<details><summary>Detail Baseline / TOC</summary><small>Baseline #{{ $baseline->id }} · supersedes #{{ $baseline->supersedes_id ?? '-' }}</small></details></td>
                            <td>{{ $baseline->toc->format('d M Y') }}</td>
                            <td>{{ $baseline->days->count() }} titik hingga {{ number_format((float) $baseline->days->last()?->cumulative_percent, 2, '.', '') }}%</td>
                        </tr>@endforeach</tbody>
                    </table></div>
                @endif

                @if ($baselineProposals->isNotEmpty())
                    <h3>Usulan Baseline</h3>
                    @foreach ($baselineProposals as $proposal)
                        <div class="ui-state">
                            <strong>{{ $proposal->toc->format('d M Y') }}</strong>
                            <x-ui.badge :tone="$proposal->status === 'disetujui' ? 'done' : 'pending'" :label="$proposal->status" />
                            <small>{{ $proposal->days->count() }} titik rencana</small>
                            @if ($canApproveBaselineProposal && $proposal->status === 'diajukan')
                                <form class="ui-form" method="POST" action="{{ route('projects.baseline-proposals.approve', [$project, $proposal]) }}">@csrf @method('PATCH')<button class="ui-button" type="submit">Setujui Usulan Baseline</button></form>
                            @endif
                        </div>
                    @endforeach
                @endif

                @if ($canManage)
                    <form class="ui-form" method="POST" action="{{ route('projects.plan.update', $project) }}">
                        @csrf @method('PUT')
                        <label>TOC (Target Operation Complete) <input type="date" name="toc" value="{{ old('toc', $project->toc?->toDateString()) }}" required></label>
                        <div class="ui-form__grid">
                            @for ($i = 0; $i < 3; $i++)
                                <label>Titik {{ $i + 1 }} · tanggal <input type="date" name="plan[{{ $i }}][date]" value="{{ old("plan.$i.date") }}" required></label>
                                <label>Titik {{ $i + 1 }} · kumulatif % <input type="number" name="plan[{{ $i }}][percent]" min="0" max="100" step="0.001" value="{{ old("plan.$i.percent") }}" required></label>
                            @endfor
                        </div>
                        <div class="ui-form__actions"><button class="ui-button" type="submit">Simpan Baseline</button></div>
                    </form>
                @endif
            </x-ui.panel>

            <x-ui.panel id="variation-orders">
                <h2>Variation Order</h2>
                <p>Gunakan qty negatif untuk mengurangi RAB existing, atau qty positif dengan Harga Jasa Mitra untuk menambah pekerjaan.</p>
                @if ($variationOrders->isEmpty())
                    <x-ui.empty-state title="Belum ada Variation Order" message="Perubahan RAB setelah freeze akan tercatat di sini." />
                @else
                    @foreach ($variationOrders as $variation)
                        <div class="ui-state">
                            <strong>{{ $variation->nomor }}</strong>
                            <x-ui.badge :tone="$variation->status === 'approved' ? 'done' : 'pending'" :label="$variation->status" />
                            <details open><summary>Detail Variation Order</summary>{{ $variation->alasan }}<small>{{ $variation->items->map(fn ($item) => ($item->rabJasa?->pekerjaanJasa?->nama ?? $item->hargaJasaMitra?->pekerjaanJasa?->nama ?? 'Pekerjaan Jasa').' '.number_format((float) $item->quantity_delta, 3, '.', ''))->join(', ') }}</small></details>
                            @if ($canApproveVariationOrder && $variation->status === 'draft')
                                <form class="ui-form" method="POST" action="{{ route('projects.variation-orders.approve', [$project, $variation]) }}">@csrf @method('PATCH')<button class="ui-button" type="submit">Setujui Variation Order</button></form>
                            @endif
                        </div>
                    @endforeach
                @endif

                @if ($canManage)
                    <form class="ui-form" method="POST" action="{{ route('projects.variation-orders.store', $project) }}">
                        @csrf
                        <label>Alasan Variation Order <textarea name="reason" maxlength="2000" required>{{ old('reason') }}</textarea></label>
                        <label for="vo_rab_jasa_id">Baris RAB existing (kosongkan untuk penambahan baru)</label>
                        <x-ui.searchable-select name="items[0][rab_jasa_id]" id="vo_rab_jasa_id" :options="$rabOptions" :value="old('items.0.rab_jasa_id')" placeholder="Penambahan baru" clearable />
                        <label for="vo_harga_jasa_id">Harga Jasa Mitra untuk item tambahan</label>
                        <x-ui.searchable-select name="items[0][harga_jasa_id]" id="vo_harga_jasa_id" :options="$priceOptions" :value="old('items.0.harga_jasa_id')" placeholder="Tidak digunakan untuk pengurangan" clearable />
                        <label>Perubahan qty <input name="items[0][quantity_delta]" type="number" step="0.001" value="{{ old('items.0.quantity_delta') }}" required></label>
                        <div class="ui-form__actions"><button class="ui-button" type="submit">Ajukan Variation Order</button></div>
                    </form>
                @endif
            </x-ui.panel>
        </div>
    </x-ui.page>
</x-layouts.app>
