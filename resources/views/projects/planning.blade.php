<x-layouts.app>
    <main class="project-workspace">
        <style>
            .project-workspace { color: #172033; margin: 0 auto; max-width: 1180px; padding: 32px 22px 68px; }
            .project-workspace__back { color: #687684; font-size: .86rem; text-decoration: none; }
            .project-workspace__header { align-items: flex-end; display: flex; gap: 20px; justify-content: space-between; margin: 20px 0 26px; }
            .project-workspace h1 { color: #15324b; font-size: clamp(1.8rem, 4vw, 2.7rem); letter-spacing: -.05em; margin: 0 0 7px; }
            .project-workspace h2 { color: #15324b; font-size: 1.15rem; margin: 0 0 8px; }
            .project-workspace p { color: #687684; line-height: 1.5; }
            .project-workspace__eyebrow { color: #087f8c; font-size: .75rem; font-weight: 800; letter-spacing: .1em; margin: 0 0 8px; text-transform: uppercase; }
            .project-workspace__actions, .project-workspace__inline { display: flex; flex-wrap: wrap; gap: 8px; }
            .project-workspace__button { background: #087f8c; border: 1px solid #087f8c; border-radius: 8px; color: #fff; display: inline-block; font-size: .82rem; font-weight: 700; padding: 9px 13px; text-decoration: none; }
            .project-workspace__button--muted { background: #fff; border-color: #cbd6dc; color: #15324b; }
            .project-workspace__grid { display: grid; gap: 18px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .project-workspace__panel { background: #fff; border: 1px solid #dce4e8; border-radius: 14px; padding: 20px; }
            .project-workspace__panel--wide { grid-column: 1 / -1; }
            .project-workspace__state { background: #f6f8f9; border-radius: 9px; color: #687684; margin-top: 14px; padding: 14px; }
            .project-workspace__state--error { background: #fef2f2; color: #991b1b; }
            .project-workspace__table { border-collapse: collapse; margin-top: 14px; width: 100%; }
            .project-workspace__table th, .project-workspace__table td { border-bottom: 1px solid #e8edef; padding: 10px 7px; text-align: left; vertical-align: top; }
            .project-workspace__table th { color: #687684; font-size: .72rem; text-transform: uppercase; }
            .project-workspace__table small { color: #687684; display: block; margin-top: 3px; }
            .project-workspace__form { border-top: 1px solid #e8edef; display: grid; gap: 10px; margin-top: 18px; padding-top: 17px; }
            .project-workspace__form label { color: #526071; display: grid; font-size: .76rem; gap: 5px; }
            .project-workspace__form input, .project-workspace__form select { border: 1px solid #cbd6dc; border-radius: 7px; min-height: 36px; padding: 7px 9px; }
            .project-workspace__form-grid { display: grid; gap: 10px; grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .project-workspace__badge { background: #fff0d5; border-radius: 999px; color: #a86314; display: inline-block; font-size: .72rem; padding: 4px 8px; }
            .project-workspace__badge--approved { background: #dff3ed; color: #11664f; }
            @media (max-width: 760px) { .project-workspace { padding: 24px 16px 50px; } .project-workspace__header { align-items: flex-start; flex-direction: column; } .project-workspace__grid, .project-workspace__form-grid { grid-template-columns: 1fr; } .project-workspace__table { display: block; overflow-x: auto; } }
        </style>

        <a class="project-workspace__back" href="{{ route('projects.show', $project) }}">← Kembali ke Project Control Room</a>
        <header class="project-workspace__header">
            <div>
                <p class="project-workspace__eyebrow">Project Planning Workspace</p>
                <h1>Workspace Perencanaan Project</h1>
                <p>{{ $project->id_project }} · {{ $project->nama }} · Mitra: {{ $project->mitra->nama }}</p>
            </div>
            <div class="project-workspace__actions">
                <a class="project-workspace__button project-workspace__button--muted" href="{{ route('projects.show', $project) }}">Control Room</a>
                <a class="project-workspace__button project-workspace__button--muted" href="#rab-jasa">Detail RAB Jasa</a>
                <a class="project-workspace__button project-workspace__button--muted" href="#baseline-toc">Detail Baseline / TOC</a>
                <a class="project-workspace__button project-workspace__button--muted" href="#variation-orders">Detail Variation Order</a>
                @if (auth()->user()->hasIzin('read_project_timeline'))
                    <a class="project-workspace__button project-workspace__button--muted" href="{{ route('projects.timeline.index', $project) }}">Linimasa</a>
                @endif
            </div>
        </header>

        @if (session('status')) <div class="project-workspace__state" role="status">{{ session('status') }}</div> @endif
        @if ($errors->any())
            <div class="project-workspace__state project-workspace__state--error" role="alert">
                <strong>Periksa data perencanaan.</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="project-workspace__grid">
            <article class="project-workspace__panel project-workspace__panel--wide" id="rab-jasa">
                <h2>RAB Jasa</h2>
                <p>Harga satuan dibekukan ketika baris RAB dibuat. Perubahan di tengah jalan dicatat melalui Variation Order.</p>
                @if ($rabJasas->isEmpty())
                    <div class="project-workspace__state">Belum ada RAB Jasa untuk Project ini.</div>
                @else
                    <table class="project-workspace__table">
                        <thead><tr><th>Pekerjaan Jasa</th><th>Qty</th><th>Harga satuan beku</th><th>Total nilai</th></tr></thead>
                        <tbody>
                            @foreach ($rabJasas as $rab)
                                <tr><td>{{ $rab->pekerjaanJasa?->nama ?? 'Pekerjaan Jasa' }} @if ($rab->variation_order_id)<small>Dari Variation Order #{{ $rab->variation_order_id }}</small>@endif<br><details><summary>Detail RAB Jasa</summary><small>Baris #{{ $rab->id }} · harga dibekukan saat dibuat oleh #{{ $rab->dibuat_oleh ?? 'User' }}</small></details></td><td>{{ number_format((float) $rab->qty, 3, '.', '') }}</td><td>Rp {{ number_format((float) $rab->harga_satuan, 2, ',', '.') }}</td><td>Rp {{ number_format((float) $rab->total_nilai, 2, ',', '.') }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
                @if ($canManage)
                    @if ($prices->isEmpty())
                        <div class="project-workspace__state">Belum ada Harga Jasa Mitra berstatus disetujui yang dapat dipakai.</div>
                    @else
                        <form class="project-workspace__form" method="POST" action="{{ route('projects.rab-jasa.store', $project) }}">
                            @csrf
                            <div class="project-workspace__form-grid">
                                <label>Pekerjaan Jasa dari Harga Mitra
                                    <select name="harga_jasa_id" required><option value="">Pilih Harga Jasa</option>@foreach ($prices as $price)<option value="{{ $price->id }}">{{ $price->pekerjaanJasa?->nama }} · Rp {{ number_format((float) $price->harga, 2, ',', '.') }}</option>@endforeach</select>
                                </label>
                                <label>Qty RAB Jasa <input name="qty" type="number" min="0.001" step="0.001" value="{{ old('qty') }}" required></label>
                                <div class="project-workspace__inline"><button class="project-workspace__button" type="submit">Tambah RAB Jasa</button></div>
                            </div>
                        </form>
                    @endif
                @endif
            </article>

            <article class="project-workspace__panel" id="baseline-toc">
                <h2>Baseline / TOC</h2>
                <p>TOC berada di level Project. Baseline berikutnya menjadi Revised Baseline dan tidak mengubah Original Baseline.</p>
                @if ($baselines->isEmpty())
                    <div class="project-workspace__state">Belum ada baseline. Baseline pertama akan menjadi Original Baseline.</div>
                @else
                    <table class="project-workspace__table"><thead><tr><th>Jenis / versi</th><th>TOC</th><th>Titik rencana</th></tr></thead><tbody>@foreach ($baselines as $baseline)<tr><td>{{ $baseline->kind === 'original' ? 'Original Baseline' : 'Revised Baseline' }} v{{ $baseline->version }}<br><details><summary>Detail Baseline / TOC</summary><small>Baseline #{{ $baseline->id }} · supersedes #{{ $baseline->supersedes_id ?? 'tidak ada' }}</small></details></td><td>{{ $baseline->toc->format('d M Y') }}</td><td>{{ $baseline->days->count() }} titik hingga {{ number_format((float) $baseline->days->last()?->cumulative_percent, 2, '.', '') }}%</td></tr>@endforeach</tbody></table>
                @endif
                @if ($baselineProposals->isNotEmpty())
                    <h3>Usulan Baseline</h3>
                    @foreach ($baselineProposals as $proposal)
                        <div class="project-workspace__state">
                            <strong>{{ $proposal->toc->format('d M Y') }}</strong>
                            <span class="project-workspace__badge {{ $proposal->status === 'disetujui' ? 'project-workspace__badge--approved' : '' }}">{{ $proposal->status }}</span>
                            <small>{{ $proposal->days->count() }} titik rencana</small>
                            @if ($canApproveBaselineProposal && $proposal->status === 'diajukan')
                                <form method="POST" action="{{ route('projects.baseline-proposals.approve', [$project, $proposal]) }}" style="margin-top:9px">
                                    @csrf @method('PATCH')
                                    <button class="project-workspace__button" type="submit">Setujui Usulan Baseline</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                @endif
                @if ($canManage)
                    <form class="project-workspace__form" method="POST" action="{{ route('projects.plan.update', $project) }}">
                        @csrf @method('PUT')
                        <label>TOC (Target Operation Complete) <input type="date" name="toc" value="{{ old('toc', $project->toc?->toDateString()) }}" required></label>
                        <div class="project-workspace__form-grid">
                            @for ($i = 0; $i < 3; $i++)
                                <label>Titik {{ $i + 1 }} · tanggal <input type="date" name="plan[{{ $i }}][date]" value="{{ old("plan.$i.date") }}" required></label>
                                <label>Titik {{ $i + 1 }} · kumulatif % <input type="number" name="plan[{{ $i }}][percent]" min="0" max="100" step="0.001" value="{{ old("plan.$i.percent") }}" required></label>
                            @endfor
                        </div>
                        <button class="project-workspace__button" type="submit">Simpan Baseline</button>
                    </form>
                @endif
            </article>

            <article class="project-workspace__panel" id="variation-orders">
                <h2>Variation Order</h2>
                <p>Gunakan qty negatif untuk mengurangi baris RAB existing, atau qty positif dengan Harga Jasa Mitra baru untuk menambah pekerjaan.</p>
                @if ($variationOrders->isEmpty())
                    <div class="project-workspace__state">Belum ada Variation Order.</div>
                @else
                    @foreach ($variationOrders as $variation)
                        <div class="project-workspace__state"><strong>{{ $variation->nomor }}</strong> <span class="project-workspace__badge {{ $variation->status === 'approved' ? 'project-workspace__badge--approved' : '' }}">{{ $variation->status }}</span><br><details open><summary>Detail Variation Order</summary>{{ $variation->alasan }}<small>{{ $variation->items->map(fn ($item) => ($item->rabJasa?->pekerjaanJasa?->nama ?? $item->hargaJasaMitra?->pekerjaanJasa?->nama ?? 'Pekerjaan Jasa').' '.number_format((float) $item->quantity_delta, 3, '.', ''))->join(', ') }}</small></details>@if ($canApproveVariationOrder && $variation->status === 'draft')<form method="POST" action="{{ route('projects.variation-orders.approve', [$project, $variation]) }}" style="margin-top:9px">@csrf @method('PATCH')<button class="project-workspace__button" type="submit">Setujui Variation Order</button></form>@endif</div>
                    @endforeach
                @endif
                @if ($canManage)
                    <form class="project-workspace__form" method="POST" action="{{ route('projects.variation-orders.store', $project) }}">
                        @csrf
                        <label>Alasan Variation Order <textarea name="reason" maxlength="2000" required>{{ old('reason') }}</textarea></label>
                        <label>Baris RAB existing (kosongkan untuk penambahan baru)
                            <select name="items[0][rab_jasa_id]"><option value="">Penambahan baru</option>@foreach ($rabJasas as $rab)<option value="{{ $rab->id }}">{{ $rab->pekerjaanJasa?->nama }} · qty {{ number_format((float) $rab->qty, 3, '.', '') }}</option>@endforeach</select>
                        </label>
                        <label>Harga Jasa Mitra untuk item tambahan
                            <select name="items[0][harga_jasa_id]"><option value="">Tidak digunakan untuk pengurangan</option>@foreach ($prices as $price)<option value="{{ $price->id }}">{{ $price->pekerjaanJasa?->nama }} · Rp {{ number_format((float) $price->harga, 2, ',', '.') }}</option>@endforeach</select>
                        </label>
                        <label>Perubahan qty <input name="items[0][quantity_delta]" type="number" step="0.001" value="{{ old('items.0.quantity_delta') }}" required></label>
                        <button class="project-workspace__button" type="submit">Ajukan Variation Order</button>
                    </form>
                @endif
            </article>
        </section>
    </main>
</x-layouts.app>
