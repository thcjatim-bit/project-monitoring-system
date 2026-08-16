<x-layouts.app>
    @php
        $statusLabel = $project->status_project === 'selesai' ? 'Selesai' : 'Aktif';
        $progressStatusLabels = ['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Ditolak'];
    @endphp
    <main class="control-room">
        <style>
            .control-room { color: #172033; margin: 0 auto; max-width: 1280px; padding: 34px 24px 72px; }
            .control-room__back { color: #687684; font-size: .86rem; text-decoration: none; }
            .control-room__header { align-items: flex-end; display: flex; gap: 24px; justify-content: space-between; margin: 22px 0 28px; }
            .control-room__eyebrow { color: #087f8c; font-size: .76rem; font-weight: 800; letter-spacing: .1em; margin: 0 0 8px; text-transform: uppercase; }
            .control-room h1 { color: #15324b; font-size: clamp(1.8rem, 4vw, 3rem); letter-spacing: -.055em; line-height: 1.05; margin: 0 0 9px; }
            .control-room__subtitle { color: #687684; margin: 0; }
            .control-room__actions { display: flex; flex-wrap: wrap; gap: 8px; }
            .control-room__button { background: #087f8c; border: 1px solid #087f8c; border-radius: 8px; color: #fff; display: inline-block; font-size: .84rem; font-weight: 700; padding: 10px 14px; text-decoration: none; }
            .control-room__button--muted { background: #fff; border-color: #cbd6dc; color: #15324b; }
            .control-room__meta { background: #fff; border: 1px solid #dce4e8; border-radius: 14px; display: grid; gap: 16px; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 18px; padding: 20px; }
            .control-room__meta dt { color: #687684; font-size: .75rem; margin-bottom: 5px; }
            .control-room__meta dd { font-size: .96rem; font-weight: 700; margin: 0; }
            .control-room__badge { border-radius: 999px; display: inline-block; font-size: .74rem; padding: 5px 9px; }
            .control-room__badge--active { background: #dff3ed; color: #11664f; }
            .control-room__badge--done { background: #e8edf1; color: #526071; }
            .control-room__grid { display: grid; gap: 18px; grid-template-columns: minmax(0, 1.5fr) minmax(280px, .8fr); }
            .control-room__panel { background: #fff; border: 1px solid #dce4e8; border-radius: 14px; min-height: 180px; padding: 21px; }
            .control-room__panel h2 { color: #15324b; font-size: 1.08rem; margin: 0 0 8px; }
            .control-room__panel p { color: #687684; line-height: 1.5; margin: 0 0 15px; }
            .control-room__state { align-items: center; background: #f6f8f9; border-radius: 10px; color: #687684; display: flex; min-height: 92px; padding: 16px; }
            .control-room__state--error { background: #fef2f2; color: #991b1b; }
            .control-room__state--loading { background: #f0f9ff; color: #155e75; }
            .control-room__kpis { display: grid; gap: 12px; grid-template-columns: repeat(3, minmax(0, 1fr)); margin-bottom: 18px; }
            .control-room__kpi { background: #fff; border: 1px solid #dce4e8; border-radius: 12px; padding: 16px; }
            .control-room__kpi-label { color: #687684; display: block; font-size: .74rem; }
            .control-room__kpi-value { color: #15324b; display: block; font-size: 1.55rem; font-weight: 800; letter-spacing: -.04em; margin-top: 5px; }
            .control-room__kpi-note { color: #687684; display: block; font-size: .76rem; margin-top: 4px; }
            .control-room__kpi--green .control-room__kpi-value { color: #11664f; }
            .control-room__kpi--yellow .control-room__kpi-value { color: #a86314; }
            .control-room__kpi--red .control-room__kpi-value { color: #b34444; }
            .control-room__chart { min-height: 260px; overflow: hidden; }
            .control-room__chart svg { display: block; height: 230px; width: 100%; }
            .control-room__chart-grid { stroke: #e8edef; stroke-width: 1; }
            .control-room__chart-plan { fill: none; stroke: #9daab2; stroke-dasharray: 5 5; stroke-width: 2; }
            .control-room__chart-actual { fill: none; stroke: #087f8c; stroke-linecap: round; stroke-linejoin: round; stroke-width: 4; }
            .control-room__chart-pending { fill: none; stroke: #d79c38; stroke-dasharray: 3 6; stroke-linecap: round; stroke-width: 3; }
            .control-room__progress { grid-column: 1 / -1; }
            .control-room__progress-list { border-top: 1px solid #e8edef; display: grid; gap: 10px; list-style: none; margin: 15px 0 0; padding: 12px 0 0; }
            .control-room__progress-row { align-items: flex-start; background: #f6f8f9; border-left: 3px solid #cbd6dc; border-radius: 0 8px 8px 0; display: flex; flex-wrap: wrap; gap: 8px 14px; justify-content: space-between; padding: 11px 13px; }
            .control-room__progress-row--pending { border-color: #d79c38; }
            .control-room__progress-row--verified { border-color: #58a98c; }
            .control-room__progress-row--rejected { border-color: #b34444; }
            .control-room__progress-title { color: #15324b; display: block; font-weight: 750; }
            .control-room__progress-meta, .control-room__progress-note { color: #687684; display: block; font-size: .76rem; margin-top: 4px; }
            .control-room__progress-status { border-radius: 999px; display: inline-block; font-size: .72rem; font-weight: 750; padding: 4px 8px; }
            .control-room__progress-status--pending { background: #fff0d5; color: #a86314; }
            .control-room__progress-status--verified { background: #dff3ed; color: #11664f; }
            .control-room__progress-status--rejected { background: #fbe4e4; color: #9b3d3d; }
            .control-room__progress-actions { display: flex; flex-basis: 100%; flex-wrap: wrap; gap: 8px; margin-top: 4px; }
            .control-room__progress-actions form, .control-room__progress-form { display: grid; gap: 7px; }
            .control-room__progress-actions form { flex: 1 1 240px; }
            .control-room__progress-form { align-items: end; border-top: 1px solid #e8edef; grid-template-columns: minmax(160px, 1fr) 150px 130px auto; margin-top: 18px; padding-top: 16px; }
            .control-room__progress-form label { color: #687684; display: grid; font-size: .74rem; gap: 5px; }
            .control-room__progress-form select, .control-room__progress-form input, .control-room__progress-actions textarea { border: 1px solid #cbd6dc; border-radius: 7px; min-height: 36px; padding: 7px 9px; }
            .control-room__progress-actions textarea { min-height: 58px; width: 100%; }
            .control-room__legend { color: #687684; display: flex; flex-wrap: wrap; font-size: .74rem; gap: 12px; margin-top: 5px; }
            .control-room__legend span::before { background: currentColor; content: ""; display: inline-block; height: 3px; margin: 0 5px 3px 0; width: 17px; }
            .control-room__legend .plan { color: #9daab2; }
            .control-room__legend .actual { color: #087f8c; }
            .control-room__legend .pending { color: #d79c38; }
            .control-room__steps, .control-room__materials { grid-column: 1 / -1; }
            .control-room__material-summary { display: flex; flex-wrap: wrap; gap: 10px 22px; margin: 15px 0; }
            .control-room__material-summary strong { color: #15324b; display: block; font-size: 1.2rem; }
            .control-room__material-summary span { color: #687684; display: block; font-size: .74rem; margin-top: 3px; }
            .control-room__material-list { border-top: 1px solid #e8edef; display: grid; gap: 9px; list-style: none; margin: 15px 0 0; padding: 12px 0 0; }
            .control-room__material-row { align-items: center; display: flex; flex-wrap: wrap; gap: 6px 14px; justify-content: space-between; }
            .control-room__material-row small { color: #687684; }
            .control-room__material-links { display: flex; flex-wrap: wrap; gap: 8px; }
            .control-room__material-links a { color: #087f8c; font-size: .76rem; font-weight: 700; }
            .control-room__material-form { align-items: end; border-top: 1px solid #e8edef; display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; padding-top: 16px; }
            .control-room__material-form label { color: #687684; display: grid; font-size: .74rem; gap: 5px; }
            .control-room__material-form select, .control-room__material-form input { border: 1px solid #cbd6dc; border-radius: 7px; min-height: 36px; padding: 7px 9px; }
            .control-room__photos { grid-column: 1 / -1; }
            .control-room__photo-list { display: grid; gap: 8px; list-style: none; margin: 15px 0 0; padding: 0; }
            .control-room__photo-list a { color: #087f8c; font-weight: 700; text-decoration: none; }
            .control-room__photo-list small { color: #687684; display: block; margin-top: 3px; }
            .control-room__photo-form { align-items: end; border-top: 1px solid #e8edef; display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; padding-top: 16px; }
            .control-room__photo-form label { color: #687684; display: grid; font-size: .74rem; gap: 5px; }
            .control-room__photo-form select, .control-room__photo-form input { border: 1px solid #cbd6dc; border-radius: 7px; min-height: 36px; padding: 7px 9px; }
            .control-room__photo-help { color: #687684; font-size: .75rem; margin: 8px 0 0; }
            .control-room__timeline-list { display: grid; gap: 10px; list-style: none; margin: 15px 0 0; padding: 0; }
            .control-room__timeline-entry { border-left: 3px solid #cbd6dc; border-radius: 0 8px 8px 0; background: #f6f8f9; padding: 11px 13px; }
            .control-room__timeline-entry--comment { border-color: #087f8c; }
            .control-room__timeline-entry--internal_note { background: #fff7e8; border-color: #d79c38; }
            .control-room__timeline-meta { color: #687684; display: flex; flex-wrap: wrap; font-size: .73rem; gap: 6px 12px; }
            .control-room__timeline-body { color: #172033; line-height: 1.45; margin: 7px 0 0; white-space: pre-wrap; }
            .control-room__timeline-edit { margin-top: 9px; }
            .control-room__timeline-edit textarea, .control-room__comment-form textarea { border: 1px solid #cbd6dc; border-radius: 7px; display: block; min-height: 70px; padding: 8px; width: 100%; }
            .control-room__comment-form { border-top: 1px solid #e8edef; display: grid; gap: 8px; margin-top: 16px; padding-top: 15px; }
            .control-room__comment-form select { border: 1px solid #cbd6dc; border-radius: 7px; min-height: 36px; padding: 7px; }
            .control-room__comment-options { align-items: center; color: #687684; display: flex; flex-wrap: wrap; font-size: .75rem; gap: 12px; }
            .control-room__step-list { display: grid; gap: 9px; grid-template-columns: repeat(11, minmax(90px, 1fr)); list-style: none; margin: 18px 0 0; overflow-x: auto; padding: 0; }
            .control-room__step { border-top: 3px solid #dce4e8; min-width: 90px; padding-top: 9px; }
            .control-room__step--active { border-color: #087f8c; }
            .control-room__step--completed { border-color: #58a98c; }
            .control-room__step-name { display: block; font-size: .75rem; font-weight: 750; }
            .control-room__step-status { color: #687684; display: block; font-size: .68rem; margin-top: 4px; }
            @media (max-width: 780px) { .control-room { padding: 24px 16px 50px; } .control-room__header { align-items: flex-start; flex-direction: column; } .control-room__meta, .control-room__kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .control-room__grid { grid-template-columns: 1fr; } .control-room__progress-form { grid-template-columns: 1fr 1fr; } }
        </style>

        <a class="control-room__back" href="{{ route('projects.index') }}">← Kembali ke daftar Project</a>
        <header class="control-room__header">
            <div>
                <p class="control-room__eyebrow">Project Control Room</p>
                <h1>{{ $project->id_project }}</h1>
                <p class="control-room__subtitle">{{ $project->nama }}</p>
            </div>
            <div class="control-room__actions">
                <a class="control-room__button control-room__button--muted" href="#project-progress">Progres</a>
                <a class="control-room__button control-room__button--muted" href="#project-timeline">Linimasa</a>
            </div>
        </header>

        <div class="control-room__state control-room__state--loading" data-control-room-state="loading" role="status" aria-live="polite" hidden>
            Memuat Control Room {{ $project->id_project }}…
        </div>
        <div class="control-room__state control-room__state--error" data-control-room-state="error" role="alert" hidden>
            Gagal memuat Control Room {{ $project->id_project }}. Coba lagi atau kembali ke daftar Project.
        </div>
        @if ($controlRoomError)
            <div class="control-room__state control-room__state--error" data-control-room-state="error" role="alert">
                Gagal memuat Control Room {{ $project->id_project }}. Data diringkas agar konteks Project tetap terlihat.
            </div>
        @endif

        <dl class="control-room__meta">
            <div><dt>Mitra pemilik</dt><dd>{{ $project->mitra->nama }}</dd></div>
            <div><dt>Status Project</dt><dd><span class="control-room__badge {{ $project->status_project === 'selesai' ? 'control-room__badge--done' : 'control-room__badge--active' }}">{{ $statusLabel }}</span></dd></div>
            <div><dt>TOC</dt><dd>{{ $project->toc?->format('d M Y') ?? 'Belum ditetapkan' }}</dd></div>
            <div><dt>Akses</dt><dd>Read Project</dd></div>
        </dl>

        <section class="control-room__kpis" aria-label="KPI Project">
            <article class="control-room__kpi">
                <span class="control-room__kpi-label">Realisasi jasa verified</span>
                <strong class="control-room__kpi-value">{{ number_format($curve['verified_percent'], 2, '.', '') }}%</strong>
                <span class="control-room__kpi-note">Pending shadow: {{ number_format($curve['pending_percent'], 2, '.', '') }}%</span>
            </article>
            <article class="control-room__kpi control-room__kpi--{{ $curve['spi_status'] }}">
                <span class="control-room__kpi-label">SPI terhadap baseline berlaku</span>
                <strong class="control-room__kpi-value">{{ $curve['spi_label'] }}</strong>
                <span class="control-room__kpi-note">Rencana: {{ number_format($curve['plan_percent'], 2, '.', '') }}% per {{ $curve['as_of'] }}</span>
            </article>
            <article class="control-room__kpi">
                <span class="control-room__kpi-label">Kesiapan material</span>
                @if ($material['state'] === 'forbidden')
                    <strong class="control-room__kpi-value">Terbatas</strong>
                    <span class="control-room__kpi-note">Izin material diperlukan</span>
                @else
                    <strong class="control-room__kpi-value">{{ number_format($material['readiness_percent'], 2, '.', '') }}%</strong>
                    <span class="control-room__kpi-note">Diterima / kebutuhan RAB Material</span>
                @endif
            </article>
        </section>

        <section class="control-room__grid" aria-label="Ringkasan Project Control Room">
            <article class="control-room__panel">
                <h2>Kurva S dan SPI</h2>
                <p>Baseline berlaku: {{ $curve['revised_baseline'] ? 'Revised Baseline' : ($curve['original_baseline'] ? 'Original Baseline' : 'Belum tersedia') }}. Nilai pending tidak masuk Realisasi.</p>
                <div class="control-room__chart" role="img" aria-label="Kurva S baseline, realisasi verified, dan pending">
                    @php
                        $chartDates = collect($curve['baseline_series'])->pluck('date')
                            ->merge(collect($curve['verified_series'])->pluck('date'))
                            ->merge(collect($curve['pending_series'])->pluck('date'))
                            ->unique()->sort()->values();
                        $chartPoint = function (array $series) use ($chartDates): string {
                            $values = collect($series)->keyBy('date');
                            $lastIndex = max(1, $chartDates->count() - 1);
                            return $chartDates->map(function (string $date, int $index) use ($values, $lastIndex): ?string {
                                $point = $values->get($date);
                                return $point === null ? null : (string) (20 + (600 * $index / $lastIndex)).','. (210 - (1.7 * (float) $point['percent']));
                            })->filter()->implode(' ');
                        };
                    @endphp
                    @if ($chartDates->isEmpty())
                        <div class="control-room__state">Belum ada titik baseline atau progres untuk diplot.</div>
                    @else
                        <svg viewBox="0 0 640 230" preserveAspectRatio="none">
                            <line class="control-room__chart-grid" x1="20" y1="40" x2="620" y2="40" />
                            <line class="control-room__chart-grid" x1="20" y1="125" x2="620" y2="125" />
                            <line class="control-room__chart-grid" x1="20" y1="210" x2="620" y2="210" />
                            <polyline class="control-room__chart-plan" points="{{ $chartPoint($curve['baseline_series']) }}" />
                            <polyline class="control-room__chart-actual" points="{{ $chartPoint($curve['verified_series']) }}" />
                            <polyline class="control-room__chart-pending" points="{{ $chartPoint($curve['pending_series']) }}" />
                        </svg>
                        <div class="control-room__legend"><span class="plan">Baseline</span><span class="actual">Verified</span><span class="pending">Pending shadow</span></div>
                    @endif
                </div>
                @if ($curve['overdue'])
                    <p role="alert">Project melewati TOC; sumbu waktu diperpanjang sampai {{ $curve['x_axis_end'] }}@if ($curve['baseline_flat_after_toc']) dan baseline mendatar di 100%@endif.</p>
                @endif
            </article>
            <article class="control-room__panel control-room__progress" id="project-progress">
                <h2>Progres Jasa</h2>
                @if (! $canReadProgress)
                    <div class="control-room__state">Data Progres Jasa memerlukan izin baca progres.</div>
                @else
                    <p>Progres memakai tanggal aktual pekerjaan. Hanya status Verified yang masuk Realisasi; Pending tetap terlihat untuk ditindaklanjuti.</p>
                    @if ($progresses->isEmpty())
                        <div class="control-room__state">Belum ada Progres Jasa yang dilaporkan.</div>
                    @else
                        <ul class="control-room__progress-list">
                            @foreach ($progresses as $progress)
                                <li class="control-room__progress-row control-room__progress-row--{{ $progress->status }}">
                                    <div>
                                        <span class="control-room__progress-title">{{ $progress->rabJasa?->pekerjaanJasa?->nama ?? 'Pekerjaan Jasa' }}</span>
                                        <span class="control-room__progress-meta">{{ $progress->actual_date->format('d M Y') }} · Qty {{ number_format((float) $progress->qty, 3, '.', '') }} · Dilaporkan {{ $progress->reporter?->name ?? 'User' }}</span>
                                        @if ($progress->verification_note)
                                            <span class="control-room__progress-note">Catatan: {{ $progress->verification_note }}</span>
                                        @endif
                                    </div>
                                    <span class="control-room__progress-status control-room__progress-status--{{ $progress->status }}">{{ $progressStatusLabels[$progress->status] ?? ucfirst($progress->status) }}</span>
                                    @if ($progress->status === 'pending' && auth()->user()->mitra_id === null && auth()->user()->hasIzin('verify_project_progress'))
                                        <div class="control-room__progress-actions">
                                            <form method="POST" action="{{ route('projects.progress.verify', [$project, $progress->id]) }}">
                                                @csrf
                                                @method('PATCH')
                                                <textarea name="note" maxlength="1000" placeholder="Catatan verifikasi (opsional)"></textarea>
                                                <button class="control-room__button" type="submit">Verifikasi progres</button>
                                            </form>
                                            <form method="POST" action="{{ route('projects.progress.reject', [$project, $progress->id]) }}">
                                                @csrf
                                                @method('PATCH')
                                                <textarea name="note" maxlength="1000" placeholder="Alasan penolakan" required></textarea>
                                                <button class="control-room__button control-room__button--muted" type="submit">Tolak progres</button>
                                            </form>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if (auth()->user()->hasIzin('report_project_progress') && $rabJasas->isNotEmpty())
                        <form class="control-room__progress-form" method="POST" action="{{ route('projects.progress.store', $project) }}">
                            @csrf
                            <label>Pekerjaan Jasa
                                <select name="project_rab_jasa_id" required>
                                    @foreach ($rabJasas as $rabJasa)
                                        <option value="{{ $rabJasa->id }}">{{ $rabJasa->pekerjaanJasa?->nama ?? 'Pekerjaan Jasa' }} · RAB {{ number_format((float) $rabJasa->qty, 3, '.', '') }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>Tanggal aktual
                                <input type="date" name="actual_date" value="{{ now()->toDateString() }}" required>
                            </label>
                            <label>Qty progres
                                <input type="number" name="qty" min="0.001" step="0.001" required>
                            </label>
                            <button class="control-room__button" type="submit">Ajukan progres jasa</button>
                        </form>
                    @elseif (auth()->user()->hasIzin('report_project_progress'))
                        <div class="control-room__state">Belum ada RAB Jasa sebagai sumber Progres Jasa.</div>
                    @endif
                @endif
            </article>
            <article class="control-room__panel control-room__materials" id="project-materials">
                <h2>Kesiapan Material</h2>
                <p>Material yang masih Transit tidak dihitung sebagai material siap pakai.</p>
                @if ($material['state'] === 'forbidden')
                    <div class="control-room__state">Data kesiapan material memerlukan izin baca modul material.</div>
                @elseif ($material['state'] === 'empty')
                    <div class="control-room__state">Kebutuhan RAB Material belum disusun.</div>
                @else
                    <div class="control-room__material-summary">
                        <div><strong>{{ number_format($material['readiness_percent'], 2, '.', '') }}%</strong><span>Kesiapan</span></div>
                        <div><strong>{{ number_format($material['delivered'], 3, '.', '') }}</strong><span>Qty diterima</span></div>
                        <div><strong>{{ number_format($material['required'], 3, '.', '') }}</strong><span>Qty kebutuhan</span></div>
                        <div><strong>{{ number_format($material['transit'], 3, '.', '') }}</strong><span>Qty Transit, tidak dihitung</span></div>
                    </div>
                    @if ($material['state'] === 'no_delivery')
                        <div class="control-room__state">Belum ada material terkirim untuk Project ini.</div>
                    @endif
                    <ul class="control-room__material-list">
                        @foreach ($material['items'] as $item)
                            <li class="control-room__material-row">
                                <span>
                                    <strong>{{ $item['material']->nama }}</strong>
                                    <small>{{ number_format($item['delivered'], 3, '.', '') }} / {{ number_format($item['required'], 3, '.', '') }} {{ $item['material']->unit?->nama }}</small>
                                </span>
                                <span class="control-room__material-links">
                                    @foreach ($item['request_ids'] as $requestId)
                                        <a href="{{ route('material-requests.show', $requestId) }}">Request Material</a>
                                    @endforeach
                                    @foreach ($item['surat_jalan_ids'] as $suratJalanId)
                                        <a href="{{ route('warehouse.transfers.print', $suratJalanId) }}">Surat Jalan</a>
                                    @endforeach
                                    @if ($item['transit'] > 0)
                                        <a href="{{ route('warehouse.transit') }}">Transit</a>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if (auth()->user()->hasIzin('manage_project_material'))
                    <form class="control-room__material-form" method="POST" action="{{ route('projects.rab-material.store', $project) }}">
                        @csrf
                        <label>Material
                            <select name="material_id" required>
                                @foreach ($materials as $availableMaterial)
                                    <option value="{{ $availableMaterial->id }}">{{ $availableMaterial->kode }} — {{ $availableMaterial->nama }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Qty kebutuhan
                            <input type="number" name="qty" min="0.001" step="0.001" required>
                        </label>
                        <button class="control-room__button" type="submit">Tambah kebutuhan</button>
                    </form>
                @endif
            </article>
            <article class="control-room__panel control-room__photos" id="project-photos">
                <h2>Foto Pekerjaan</h2>
                <p>Bukti lapangan terikat pada Project dan Step. Status sinkronisasi tidak mengubah akses terhadap file aplikasi.</p>
                @if ($photos->isEmpty())
                    <div class="control-room__state">Belum ada Foto Pekerjaan untuk Project ini.</div>
                @else
                    <ul class="control-room__photo-list">
                        @foreach ($photos as $photo)
                            <li>
                                <a href="{{ route('projects.photos.show', [$project, $photo->id]) }}">{{ $photo->original_name }}</a>
                                <small>{{ $photo->step->label() }} · {{ $photo->created_at?->format('d M Y H:i') }} · Sync: {{ $photo->sync_status }}</small>
                                @if ($photo->sync_status === 'failed' && $photo->sync_error)
                                    <small role="alert">{{ $photo->sync_error }}</small>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if (auth()->user()->hasIzin('upload_project_photo'))
                    <form class="control-room__photo-form" data-photo-upload method="POST" action="{{ route('projects.photos.store', $project) }}" enctype="multipart/form-data">
                        @csrf
                        <label>Step
                            <select name="step" required>
                                @foreach ($steps as $step)
                                    <option value="{{ $step->step }}">{{ $step->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Foto JPEG
                            <input data-photo-input type="file" name="photos[]" accept="image/jpeg,.jpg,.jpeg" multiple required>
                        </label>
                        <button class="control-room__button" type="submit">Unggah Foto</button>
                    </form>
                    <p class="control-room__photo-help">Maksimal 10 foto per unggahan, 5 MB mentah per foto. Browser mengompres ke maksimal 1920×1080.</p>
                @endif
            </article>
            <article class="control-room__panel" id="project-timeline">
                <h2>Linimasa Gabungan</h2>
                @if ($timeline->isEmpty())
                    <div class="control-room__state" data-dashboard-state="empty">Belum ada aktivitas Project yang dapat ditampilkan.</div>
                @else
                    <ol class="control-room__timeline-list">
                        @foreach ($timeline as $entry)
                            <li class="control-room__timeline-entry control-room__timeline-entry--{{ $entry->type }}">
                                <div class="control-room__timeline-meta">
                                    <strong>{{ $entry->type === 'internal_note' ? 'Komentar Internal' : ($entry->type === 'comment' ? 'Komentar' : 'Log Sistem') }}</strong>
                                    <span>{{ $entry->actor?->name ?? 'Sistem' }}</span>
                                    <time datetime="{{ $entry->created_at?->toIso8601String() }}">{{ $entry->created_at?->format('d M Y H:i') }}</time>
                                    @if ($entry->edited_at)<span>edited</span>@endif
                                </div>
                                @if ($entry->type === 'system_log')
                                    <p class="control-room__timeline-body">{{ ucwords(str_replace('_', ' ', $entry->event_key ?? 'Aktivitas sistem')) }}</p>
                                @else
                                    <p class="control-room__timeline-body">{{ $entry->body }}</p>
                                    @if (auth()->user()->hasIzin('edit_project_comment') && (auth()->id() === $entry->actor_id || auth()->user()->mitra_id === null))
                                        <form class="control-room__timeline-edit" method="POST" action="{{ route('projects.comments.update', [$project, $entry->id]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <textarea name="body" required>{{ $entry->body }}</textarea>
                                            <button class="control-room__button control-room__button--muted" type="submit">Simpan edit</button>
                                        </form>
                                    @endif
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
                @if (auth()->user()->hasIzin('create_project_comment'))
                    <form class="control-room__comment-form" method="POST" action="{{ route('projects.comments.store', $project) }}">
                        @csrf
                        <textarea name="body" placeholder="Tulis komentar Project..." required></textarea>
                        @if ($mentionableUsers->isNotEmpty())
                            <label class="control-room__comment-options">Mention user
                                <select name="mentions[]" multiple>
                                    @foreach ($mentionableUsers as $mentionableUser)
                                        <option value="{{ $mentionableUser->id }}">{{ $mentionableUser->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                        <div class="control-room__comment-options">
                            @if (auth()->user()->mitra_id === null)
                                <label><input type="checkbox" name="internal" value="1"> Komentar Internal THC</label>
                            @endif
                            <button class="control-room__button" type="submit">Tambah komentar</button>
                        </div>
                    </form>
                @endif
            </article>
            <article class="control-room__panel control-room__steps">
                <h2>Step Project</h2>
                <p>Step dapat dilompati atau dimundurkan; hanya tanggal aktual selesai yang dicatat.</p>
                <ol class="control-room__step-list">
                    @foreach ($steps as $step)
                        <li class="control-room__step control-room__step--{{ $step->status }}">
                            <span class="control-room__step-name">{{ $step->label() }}</span>
                            <span class="control-room__step-status">
                                {{ $step->status === 'completed' ? 'Selesai' : ($step->status === 'active' ? 'Aktif' : 'Belum selesai') }}
                                @if ($step->completed_at) · {{ $step->completed_at->format('d M Y') }} @endif
                            </span>
                        </li>
                    @endforeach
                </ol>
            </article>
        </section>
    </main>
    <script>
        (() => {
            const form = document.querySelector('[data-photo-upload]');
            const input = form?.querySelector('[data-photo-input]');
            if (!form || !input) return;

            const maxWidth = 1920;
            const maxHeight = 1080;
            const maxBytes = 5 * 1024 * 1024;

            const resizeToJpeg = (file) => new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onerror = () => reject(reader.error || new Error('Foto tidak dapat dibaca.'));
                reader.onload = () => {
                    const image = new Image();
                    image.onerror = () => reject(new Error('Foto JPEG tidak valid.'));
                    image.onload = () => {
                        const ratio = Math.min(1, maxWidth / image.naturalWidth, maxHeight / image.naturalHeight);
                        const canvas = document.createElement('canvas');
                        canvas.width = Math.max(1, Math.round(image.naturalWidth * ratio));
                        canvas.height = Math.max(1, Math.round(image.naturalHeight * ratio));
                        canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
                        canvas.toBlob((blob) => {
                            if (!blob) return reject(new Error('Foto gagal dikompres.'));
                            resolve(new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() }));
                        }, 'image/jpeg', 0.82);
                    };
                    image.src = reader.result;
                };
                reader.readAsDataURL(file);
            });

            form.addEventListener('submit', async (event) => {
                if (form.dataset.compressed === 'true') return;
                event.preventDefault();
                const files = Array.from(input.files || []);
                if (files.length > 10 || files.some((file) => file.size > maxBytes || file.type !== 'image/jpeg')) {
                    input.setCustomValidity('Pilih maksimal 10 JPEG dengan ukuran mentah maksimal 5 MB per foto.');
                    input.reportValidity();
                    return;
                }
                input.setCustomValidity('');
                const transfer = new DataTransfer();
                for (const file of files) transfer.items.add(await resizeToJpeg(file));
                input.files = transfer.files;
                form.dataset.compressed = 'true';
                form.submit();
            });
        })();
    </script>
</x-layouts.app>
