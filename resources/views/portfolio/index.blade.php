<x-layouts.app>
    <style>
        .portfolio {
            box-sizing: border-box;
            margin: 0 auto;
            max-width: 1120px;
            padding: 32px 20px 48px;
            color: #172033;
            font-family: ui-sans-serif, system-ui, sans-serif;
        }

        .portfolio *, .portfolio *::before, .portfolio *::after { box-sizing: border-box; }
        .portfolio a { color: #155e75; }
        .portfolio__header h1 { margin: 0 0 8px; font-size: clamp(1.8rem, 4vw, 2.75rem); letter-spacing: -0.04em; }
        .portfolio__header p { color: #526071; margin: 0; }
        .portfolio__eyebrow { color: #0f766e; font-size: 0.78rem; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; }
        .portfolio__updated { color: #526071; font-size: 0.85rem; margin-top: 10px; }
        .portfolio__panel { background: #fff; border: 1px solid #dbe2ea; border-radius: 14px; box-shadow: 0 8px 24px rgb(23 32 51 / 6%); margin-top: 20px; padding: 22px; }
        .portfolio__panel h2 { font-size: 1.25rem; margin: 0 0 5px; }
        .portfolio__panel-note { color: #526071; margin: 0; }
        .portfolio__filters { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); margin-top: 16px; }
        .portfolio__field { display: flex; flex-direction: column; gap: 6px; }
        .portfolio__field label { color: #526071; font-size: 0.85rem; font-weight: 700; }
        .portfolio__field select { background: #fff; border: 1px solid #b9c3d0; border-radius: 8px; padding: 9px 10px; }
        .portfolio__filter-actions { align-items: center; display: flex; flex-wrap: wrap; gap: 12px; margin-top: 14px; }
        .portfolio__filter-actions button { background: #0f766e; border: 1px solid #0f766e; border-radius: 8px; color: #fff; cursor: pointer; padding: 9px 16px; }
        .portfolio__filter-summary { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 10px; color: #164e63; margin-top: 16px; padding: 12px 14px; }
        .portfolio__kpis { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); margin-top: 20px; }
        .portfolio__kpi { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; display: block; padding: 16px 18px; text-decoration: none; }
        .portfolio__kpi span { color: #526071; display: block; font-size: 0.9rem; }
        .portfolio__kpi strong { color: #0c4a6e; display: block; font-size: 1.9rem; line-height: 1.1; margin-top: 8px; }
        .portfolio__kpi p { color: #526071; font-size: 0.84rem; margin: 8px 0 0; }
        .portfolio__kpi[data-spi-status="green"] strong { color: #166534; }
        .portfolio__kpi[data-spi-status="yellow"] strong { color: #92400e; }
        .portfolio__kpi[data-spi-status="red"] strong, .portfolio__kpi--attention strong { color: #991b1b; }
        .portfolio__state { border-radius: 10px; margin-top: 20px; padding: 16px; }
        .portfolio__state--loading { background: #f0f9ff; color: #155e75; }
        .portfolio__state--empty { background: #f8fafc; color: #526071; }
        .portfolio__state--error { background: #fef2f2; color: #991b1b; }
        .portfolio__section-head { align-items: end; display: flex; flex-wrap: wrap; gap: 10px 20px; justify-content: space-between; }
        .portfolio__trend-list { display: grid; gap: 14px; list-style: none; margin: 18px 0 0; padding: 0; }
        .portfolio__trend-item { display: grid; gap: 7px; grid-template-columns: 92px minmax(0, 1fr) 92px; align-items: center; }
        .portfolio__trend-date { color: #526071; font-size: .82rem; }
        .portfolio__trend-bars { display: grid; gap: 5px; }
        .portfolio__trend-bar { background: #e2e8f0; border-radius: 999px; height: 9px; overflow: hidden; }
        .portfolio__trend-bar span { background: #0f766e; border-radius: inherit; display: block; height: 100%; }
        .portfolio__trend-bar--target span { background: #64748b; }
        .portfolio__trend-values { color: #526071; font-size: .78rem; text-align: right; }
        .portfolio__trend-legend { color: #526071; display: flex; flex-wrap: wrap; font-size: .8rem; gap: 14px; margin-top: 14px; }
        .portfolio__legend-dot { border-radius: 999px; display: inline-block; height: 9px; margin-right: 5px; width: 9px; }
        .portfolio__legend-dot--actual { background: #0f766e; }
        .portfolio__legend-dot--target { background: #64748b; }
        .portfolio__table-wrap { margin-top: 16px; overflow-x: auto; }
        .portfolio__matrix { border-collapse: collapse; min-width: 800px; width: 100%; }
        .portfolio__matrix th, .portfolio__matrix td { border-bottom: 1px solid #e2e8f0; padding: 11px 10px; text-align: left; vertical-align: top; }
        .portfolio__matrix th { color: #526071; font-size: .78rem; letter-spacing: .03em; text-transform: uppercase; }
        .portfolio__matrix tbody th { color: #172033; font-size: .9rem; letter-spacing: normal; text-transform: none; }
        .portfolio__matrix tbody tr:last-child th, .portfolio__matrix tbody tr:last-child td { border-bottom: 0; }
        .portfolio__matrix small { color: #526071; display: block; font-size: .78rem; font-weight: 400; margin-top: 4px; }
        .portfolio__risk { border-radius: 999px; display: inline-block; font-size: .76rem; font-weight: 800; padding: 4px 8px; white-space: nowrap; }
        .portfolio__risk[data-risk-status="green"] { background: #dcfce7; color: #166534; }
        .portfolio__risk[data-risk-status="yellow"] { background: #fef3c7; color: #92400e; }
        .portfolio__risk[data-risk-status="red"] { background: #fee2e2; color: #991b1b; }
        .portfolio__risk[data-risk-status="na"] { background: #e2e8f0; color: #475569; }
        .portfolio__distributions { display: grid; gap: 20px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); margin-top: 16px; }
        .portfolio__distribution { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; }
        .portfolio__distribution h3 { font-size: 1rem; margin: 0 0 12px; }
        .portfolio__distribution-row { align-items: center; display: grid; gap: 8px; grid-template-columns: minmax(74px, 1fr) minmax(80px, 2fr) auto; margin-top: 9px; }
        .portfolio__distribution-row:first-of-type { margin-top: 0; }
        .portfolio__distribution-track { background: #e2e8f0; border-radius: 999px; height: 8px; overflow: hidden; }
        .portfolio__distribution-track span { background: #0f766e; display: block; height: 100%; }
        .portfolio__distribution-track[data-status="yellow"] span { background: #d97706; }
        .portfolio__distribution-track[data-status="red"] span { background: #dc2626; }
        .portfolio__distribution-track[data-status="na"] span { background: #64748b; }
        .portfolio__distribution-count { font-size: .82rem; font-weight: 800; min-width: 28px; text-align: right; }
        .portfolio__activity-list { display: grid; gap: 10px; list-style: none; margin: 16px 0 0; padding: 0; }
        .portfolio__activity-item { border-left: 3px solid #0f766e; background: #f8fafc; padding: 11px 13px; }
        .portfolio__activity-meta { color: #526071; display: flex; flex-wrap: wrap; font-size: .78rem; gap: 6px 12px; }
        .portfolio__activity-item p { margin: 6px 0 0; }
        .portfolio__activity-item small { color: #526071; display: block; margin-top: 5px; }
        .portfolio__queue-list { display: grid; gap: 10px; list-style: none; margin: 16px 0 0; padding: 0; }
        .portfolio__queue-item { background: #f8fafc; border-left: 4px solid #d97706; padding: 13px 15px; }
        .portfolio__queue-item[data-decision-risk="tinggi"] { border-left-color: #dc2626; }
        .portfolio__queue-item h3 { font-size: 1rem; margin: 8px 0 0; }
        .portfolio__queue-item p { margin: 6px 0 0; }
        .portfolio__queue-item small { color: #526071; display: block; margin-top: 7px; }
        .portfolio__queue-badges { align-items: center; display: flex; flex-wrap: wrap; gap: 7px; }
        .portfolio__queue-badge { border-radius: 999px; display: inline-block; font-size: .74rem; font-weight: 800; padding: 4px 8px; }
        .portfolio__queue-badge--category { background: #e0f2fe; color: #075985; }
        .portfolio__queue-badge--risk { background: #fef3c7; color: #92400e; }
        .portfolio__queue-badge--risk-high { background: #fee2e2; color: #991b1b; }
        @media (max-width: 680px) {
            .portfolio { padding: 24px 14px 36px; }
            .portfolio__kpis { grid-template-columns: 1fr; }
            .portfolio__panel { padding: 16px; }
            .portfolio__trend-item { grid-template-columns: 76px minmax(0, 1fr) 76px; }
            .portfolio__matrix { min-width: 760px; }
        }
    </style>

    <main class="portfolio" aria-labelledby="portfolio-title">
        <header class="portfolio__header">
            <p class="portfolio__eyebrow">Portofolio lintas Project</p>
            <h1 id="portfolio-title">Portfolio Cockpit</h1>
            <p>Kesehatan seluruh Project dalam cakupan akses Anda. Halaman ini membaca dan menautkan; mutasi tetap di modul pemiliknya.</p>
            <p class="portfolio__updated">Data diperbarui {{ $generatedAt->format('d M Y H:i') }} · dihitung s.d. {{ $filters['as_of']->format('d M Y') }}</p>
        </header>

        <section class="portfolio__panel" aria-labelledby="portfolio-filter-title">
            <h2 id="portfolio-filter-title">Filter cakupan</h2>
            <p class="portfolio__panel-note">KPI dihitung dari Project berstatus aktif yang cocok dengan filter di bawah.</p>

            <form method="GET" action="{{ route('portfolio.index') }}">
                <div class="portfolio__filters">
                    <div class="portfolio__field">
                        <label for="filter-project">Project</label>
                        <x-ui.searchable-select
                            id="filter-project"
                            name="project"
                            placeholder="Semua Project"
                            :value="$filters['project'] ?? ''"
                            :options="collect(['' => 'Semua Project'])->union($options['projects']->mapWithKeys(fn ($option) => [$option->id => ['label' => $option->id_project.' — '.$option->nama, 'search' => $option->id_project.' '.$option->nama]]))->all()"
                            :clearable="true"
                        />
                    </div>
                    <div class="portfolio__field">
                        <label for="filter-mitra">Mitra</label>
                        <x-ui.searchable-select
                            id="filter-mitra"
                            name="mitra"
                            placeholder="Semua Mitra"
                            :value="$filters['mitra'] ?? ''"
                            :options="collect(['' => 'Semua Mitra'])->union($options['mitras']->mapWithKeys(fn ($option) => [$option->id => ['label' => $option->nama.' — '.$option->kode, 'search' => $option->kode.' '.$option->nama]]))->all()"
                            :clearable="true"
                        />
                    </div>
                    <div class="portfolio__field">
                        <label for="filter-periode">Periode</label>
                        <x-ui.searchable-select
                            id="filter-periode"
                            name="periode"
                            placeholder="Periode"
                            :value="$filters['periode']"
                            :options="$options['periodes']"
                            :searchable="false"
                        />
                    </div>
                    <div class="portfolio__field">
                        <label for="filter-risiko">Status risiko</label>
                        @if ($canReadProgress)
                            <x-ui.searchable-select
                                id="filter-risiko"
                                name="risiko"
                                placeholder="Status risiko"
                                :value="$filters['risiko']"
                                :options="$options['risikos']"
                                :searchable="false"
                            />
                        @else
                            <x-ui.searchable-select
                                id="filter-risiko"
                                name="risiko"
                                placeholder="Status risiko membutuhkan izin Progres jasa"
                                :options="['' => 'Status risiko membutuhkan izin Progres jasa']"
                                :disabled="true"
                                :searchable="false"
                            />
                        @endif
                    </div>
                </div>
                <div class="portfolio__filter-actions">
                    <button type="submit">Terapkan filter</button>
                    <a href="{{ route('portfolio.index') }}">Reset filter</a>
                    <a href="{{ route('portfolio.export', array_filter(['project' => $filters['project'], 'mitra' => $filters['mitra'], 'periode' => $filters['periode'], 'risiko' => $filters['risiko'] !== 'semua' ? $filters['risiko'] : null], static fn (mixed $value): bool => $value !== null)) }}">Unduh Excel</a>
                </div>
            </form>

            <p class="portfolio__filter-summary">
                Filter aktif:
                {{ $filters['project'] === null ? 'Semua Project' : ($options['projects']->firstWhere('id', $filters['project'])?->id_project ?? 'Project #'.$filters['project']) }}
                ·
                {{ $filters['mitra'] === null ? 'Semua Mitra' : ($options['mitras']->firstWhere('id', $filters['mitra'])?->nama ?? 'Mitra #'.$filters['mitra']) }}
                · {{ $filters['periode_label'] }}
                · {{ $filters['risiko_label'] }}
            </p>
        </section>

        <section class="portfolio__panel" aria-labelledby="portfolio-kpi-title" aria-busy="false">
            <h2 id="portfolio-kpi-title">KPI kesehatan portofolio</h2>
            <p class="portfolio__panel-note">Realisasi jasa hanya menghitung progres terverifikasi; kesiapan Material dihitung terpisah dari bobot jasa dan Material Transit tidak dianggap tersedia.</p>

            <div class="portfolio__state portfolio__state--loading" data-portfolio-state="loading" role="status" aria-live="polite" hidden>
                Memuat KPI portofolio…
            </div>

            @if ($portfolioError)
                <div class="portfolio__state portfolio__state--error" data-portfolio-state="error" role="alert">
                    {{ $portfolioError }}
                </div>
            @elseif ($scopedProjectCount === 0)
                <div class="portfolio__state portfolio__state--empty" data-portfolio-state="empty">
                    Belum ada Project dalam cakupan akses Anda.
                </div>
            @elseif ($matchedProjectCount === 0)
                <div class="portfolio__state portfolio__state--empty" data-portfolio-state="empty">
                    Tidak ada Project aktif yang cocok dengan filter yang sedang berlaku.
                </div>
            @else
                <div class="portfolio__kpis" data-portfolio-state="ready">
                    <div class="portfolio__kpi">
                        <span>Project aktif</span>
                        <strong data-kpi="active-projects">{{ $kpis['active_projects'] }}</strong>
                        <p>dari {{ $scopedProjectCount }} Project dalam cakupan akses.</p>
                        @if ($projectsUrl)
                            <p><a href="{{ $projectsUrl }}">Buka daftar Project</a></p>
                        @endif
                    </div>

                    <div class="portfolio__kpi">
                        <span>Realisasi jasa terverifikasi</span>
                        @if ($canReadProgress)
                            <strong data-kpi="verified-percent">{{ number_format($kpis['verified_percent'], 2) }}%</strong>
                            <p>Progres pending {{ number_format($kpis['pending_percent'], 2) }}% tidak menaikkan realisasi.</p>
                        @else
                            <strong data-kpi="verified-percent">Terbatas</strong>
                            <p>Butuh izin membaca Progres jasa.</p>
                        @endif
                        @if ($projectsUrl)
                            <p><a href="{{ $projectsUrl }}">Buka Progres jasa di Project</a></p>
                        @endif
                    </div>

                    <div class="portfolio__kpi" data-spi-status="{{ $kpis['spi_status'] }}">
                        <span>SPI portofolio</span>
                        @if ($canReadProgress)
                            <strong data-kpi="spi">{{ $kpis['spi_label'] }}</strong>
                            <p>Dihitung dari {{ $kpis['baselined_projects'] }} Project dengan baseline berlaku (kumulatif {{ number_format($kpis['plan_percent'], 2) }}%). N/A bila kumulatif baseline masih 0%.</p>
                        @else
                            <strong data-kpi="spi">Terbatas</strong>
                            <p>Butuh izin membaca Progres jasa.</p>
                        @endif
                        @if ($projectsUrl)
                            <p><a href="{{ $projectsUrl }}">Buka Kurva S per Project</a></p>
                        @endif
                    </div>

                    <div class="portfolio__kpi portfolio__kpi--attention">
                        <span>Project perlu perhatian</span>
                        @if ($canReadProgress)
                            <strong data-kpi="attention-projects">{{ $kpis['attention_projects'] }}</strong>
                            <p>SPI di bawah 1,00 (kuning atau merah) sesuai ADR-0010.</p>
                        @else
                            <strong data-kpi="attention-projects">Terbatas</strong>
                            <p>Butuh izin membaca Progres jasa.</p>
                        @endif
                        @if ($projectsUrl)
                            <p><a href="{{ $projectsUrl }}">Buka Project berisiko</a></p>
                        @endif
                    </div>

                    <div class="portfolio__kpi">
                        <span>Kesiapan Material</span>
                        @if (! $canReadMaterial)
                            <strong data-kpi="material-readiness">Terbatas</strong>
                            <p>Butuh izin membaca Material Project.</p>
                        @else
                            <strong data-kpi="material-readiness">{{ $kpis['material_readiness_percent'] === null ? 'N/A' : number_format($kpis['material_readiness_percent'], 2).'%' }}</strong>
                            <p>Rata-rata kesiapan {{ $kpis['material_projects'] }} Project ber-RAB Material s.d. {{ $filters['as_of']->format('d M Y') }}. {{ $kpis['material_transit_projects'] }} Project masih punya Material Transit yang belum dihitung sebagai Material tersedia.</p>
                        @endif
                        @if ($projectsUrl)
                            <p><a href="{{ $projectsUrl }}">Buka kesiapan Material per Project</a></p>
                        @endif
                    </div>

                    <div class="portfolio__kpi">
                        <span>Nilai RAB Jasa aktif</span>
                        <strong data-kpi="active-rab-value">Rp {{ number_format($kpis['active_rab_value'], 0, ',', '.') }}</strong>
                        <p>Grand total RAB Jasa Project aktif dalam filter ini.</p>
                        @if ($projectsUrl)
                            <p><a href="{{ $projectsUrl }}">Buka RAB Jasa di Project</a></p>
                        @endif
                    </div>
                </div>
            @endif
        </section>

        <section class="portfolio__panel" id="portfolio-decision-queue" aria-labelledby="portfolio-decision-queue-title" data-decision-queue>
            <div class="portfolio__section-head">
                <div>
                    <h2 id="portfolio-decision-queue-title">Decision Queue</h2>
                    <p class="portfolio__panel-note">Pengecualian lintas Project yang membutuhkan keputusan. Queue hanya membaca dan membuka sumber yang authorized.</p>
                </div>
                <span class="portfolio__updated">{{ $decisionQueue->count() }} item</span>
            </div>

            @if ($portfolioError)
                <div class="portfolio__state portfolio__state--error" role="alert">{{ $portfolioError }}</div>
            @elseif (! $decisionQueueAvailable)
                <div class="portfolio__state portfolio__state--empty">Decision Queue membutuhkan izin pada sumber Project, Progres jasa, atau Transit.</div>
            @elseif ($decisionQueue->isEmpty())
                <div class="portfolio__state portfolio__state--empty">Tidak ada pengecualian yang perlu ditindaklanjuti untuk filter aktif.</div>
            @else
                <ol class="portfolio__queue-list">
                    @foreach ($decisionQueue as $item)
                        <li class="portfolio__queue-item" data-decision-category="{{ $item['category'] }}" data-decision-risk="{{ $item['risk'] }}">
                            <div class="portfolio__queue-badges">
                                <span class="portfolio__queue-badge portfolio__queue-badge--category">{{ $item['category_label'] }}</span>
                                <span class="portfolio__queue-badge {{ $item['risk'] === 'tinggi' ? 'portfolio__queue-badge--risk-high' : 'portfolio__queue-badge--risk' }}">{{ $item['risk_label'] }}</span>
                            </div>
                            <h3>{{ $item['title'] }}</h3>
                            <p>
                                @if ($item['source_url'])
                                    <a href="{{ $item['source_url'] }}"><strong>{{ $item['id_project'] }}</strong> · {{ $item['project_name'] }}</a>
                                @else
                                    <strong>{{ $item['id_project'] }}</strong> · {{ $item['project_name'] }}
                                @endif
                                · {{ $item['mitra'] }}
                            </p>
                            <p>{{ $item['description'] }}</p>
                            <small>Diperbarui {{ $item['updated_at']->format('d M Y H:i') }} · Sumber: {{ $item['source_label'] }}</small>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <section class="portfolio__panel" id="portfolio-trend" aria-labelledby="portfolio-trend-title" data-portfolio-trend>
            <div class="portfolio__section-head">
                <div>
                    <h2 id="portfolio-trend-title">Tren realisasi jasa</h2>
                    <p class="portfolio__panel-note">Realisasi jasa terverifikasi dibanding target kumulatif untuk {{ $trend['periode_label'] }}, dihitung s.d. {{ $trend['as_of'] }}.</p>
                </div>
                <span class="portfolio__updated">Data diperbarui {{ $generatedAt->format('d M Y H:i') }}</span>
            </div>

            @if ($portfolioError)
                <div class="portfolio__state portfolio__state--error" role="alert">{{ $portfolioError }}</div>
            @elseif (! $canReadProgress)
                <div class="portfolio__state portfolio__state--empty">Tren realisasi jasa membutuhkan izin membaca Progres jasa.</div>
            @elseif ($trend['points'] === [])
                <div class="portfolio__state portfolio__state--empty">Belum ada data tren untuk filter yang sedang berlaku.</div>
            @else
                <ol class="portfolio__trend-list" aria-label="Tren realisasi jasa dan target kumulatif">
                    @foreach ($trend['points'] as $point)
                        <li class="portfolio__trend-item" data-trend-date="{{ $point['date'] }}">
                            <span class="portfolio__trend-date">{{ \Carbon\CarbonImmutable::parse($point['date'])->format('d M') }}</span>
                            <span class="portfolio__trend-bars">
                                <span class="portfolio__trend-bar" title="Realisasi jasa terverifikasi {{ number_format($point['verified_percent'], 2) }}%"><span style="width: {{ min(100, max(0, (float) $point['verified_percent'])) }}%"></span></span>
                                <span class="portfolio__trend-bar portfolio__trend-bar--target" title="Target kumulatif {{ $point['target_available'] ? number_format($point['target_percent'], 2).'%' : 'N/A' }}"><span style="width: {{ min(100, max(0, (float) $point['target_percent'])) }}%"></span></span>
                            </span>
                            <span class="portfolio__trend-values">{{ number_format($point['verified_percent'], 2) }}% / {{ $point['target_available'] ? number_format($point['target_percent'], 2).'%' : 'N/A' }}</span>
                        </li>
                    @endforeach
                </ol>
                <p class="portfolio__trend-legend"><span><span class="portfolio__legend-dot portfolio__legend-dot--actual"></span>Realisasi terverifikasi</span><span><span class="portfolio__legend-dot portfolio__legend-dot--target"></span>Target kumulatif</span></p>
            @endif
        </section>

        <section class="portfolio__panel" id="portfolio-health-matrix" aria-labelledby="portfolio-health-matrix-title" data-health-matrix>
            <div class="portfolio__section-head">
                <div>
                    <h2 id="portfolio-health-matrix-title">Health Matrix</h2>
                    <p class="portfolio__panel-note">Perbandingan Project dalam cakupan filter aktif. Progres pending ditampilkan terpisah dan tidak dihitung sebagai realisasi.</p>
                </div>
                <span class="portfolio__updated">{{ $healthMatrix->count() }} Project</span>
            </div>

            @if ($portfolioError)
                <div class="portfolio__state portfolio__state--error" role="alert">{{ $portfolioError }}</div>
            @elseif ($healthMatrix->isEmpty())
                <div class="portfolio__state portfolio__state--empty">Belum ada Project yang cocok dengan filter yang sedang berlaku.</div>
            @else
                <div class="portfolio__table-wrap">
                    <table class="portfolio__matrix">
                        <thead>
                            <tr>
                                <th scope="col">Project</th>
                                <th scope="col">Mitra</th>
                                <th scope="col">Progres jasa terverifikasi</th>
                                <th scope="col">SPI</th>
                                <th scope="col">Kesiapan Material</th>
                                <th scope="col">Status risiko</th>
                                <th scope="col">Status Project</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($healthMatrix as $row)
                                <tr data-project-id="{{ $row['project_id'] }}" data-risk-status="{{ $row['spi_status'] }}">
                                    <th scope="row" data-project-identity>
                                        @if ($row['url'])
                                            <a href="{{ $row['url'] }}">{{ $row['id_project'] }}</a>
                                        @else
                                            {{ $row['id_project'] }}
                                        @endif
                                        <small>{{ $row['nama'] }}</small>
                                    </th>
                                    <td>{{ $row['mitra'] }}</td>
                                    <td>
                                        @if ($canReadProgress)
                                            {{ number_format($row['verified_percent'], 2) }}%
                                            <small>Pending {{ number_format($row['pending_percent'], 2) }}%</small>
                                        @else
                                            Terbatas
                                        @endif
                                    </td>
                                    <td>{{ $row['spi_label'] }}</td>
                                    <td>
                                        @if (! $canReadMaterial)
                                            Terbatas
                                        @elseif ($row['material_readiness_percent'] === null)
                                            N/A
                                        @else
                                            {{ number_format($row['material_readiness_percent'], 2) }}%
                                        @endif
                                    </td>
                                    <td><span class="portfolio__risk" data-risk-status="{{ $row['spi_status'] }}">{{ $row['risk_label'] }}</span></td>
                                    <td>{{ $row['status_project_label'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="portfolio__panel" id="portfolio-status-distribution" aria-labelledby="portfolio-status-distribution-title" data-status-distribution>
            <h2 id="portfolio-status-distribution-title">Distribusi Status Project</h2>
            <p class="portfolio__panel-note">Distribusi mengikuti Project dan status risiko yang sama dengan Health Matrix.</p>
            @if ($portfolioError)
                <div class="portfolio__state portfolio__state--error" role="alert">{{ $portfolioError }}</div>
            @elseif ($healthMatrix->isEmpty())
                <div class="portfolio__state portfolio__state--empty">Belum ada Status Project yang cocok dengan filter yang sedang berlaku.</div>
            @else
                <div class="portfolio__distributions">
                    <div class="portfolio__distribution" aria-labelledby="portfolio-risk-distribution-title">
                        <h3 id="portfolio-risk-distribution-title">Status risiko</h3>
                        @foreach ($statusDistribution as $status)
                            <div class="portfolio__distribution-row" data-status-key="{{ $status['key'] }}">
                                <span>{{ $status['label'] }}</span>
                                <span class="portfolio__distribution-track" data-status="{{ $status['key'] }}"><span style="width: {{ min(100, max(0, (float) $status['percent'])) }}%"></span></span>
                                <strong class="portfolio__distribution-count">{{ $status['count'] }}</strong>
                            </div>
                        @endforeach
                    </div>
                    <div class="portfolio__distribution" aria-labelledby="portfolio-project-status-title">
                        <h3 id="portfolio-project-status-title">Status Project</h3>
                        @foreach ($projectStatusDistribution as $status)
                            <div class="portfolio__distribution-row" data-project-status="{{ $status['key'] }}">
                                <span>{{ $status['label'] }}</span>
                                <span class="portfolio__distribution-track"><span style="width: {{ min(100, max(0, (float) $status['percent'])) }}%"></span></span>
                                <strong class="portfolio__distribution-count">{{ $status['count'] }}</strong>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>

        <section class="portfolio__panel" id="portfolio-project-activity" aria-labelledby="portfolio-project-activity-title" data-project-activity>
            <div class="portfolio__section-head">
                <div>
                    <h2 id="portfolio-project-activity-title">Aktivitas terbaru lintas Project</h2>
                    <p class="portfolio__panel-note">Linimasa terbaru dari Project yang cocok dengan filter. Komentar Internal tidak ditampilkan.</p>
                </div>
            </div>
            @if ($portfolioError)
                <div class="portfolio__state portfolio__state--error" role="alert">{{ $portfolioError }}</div>
            @elseif (! $canReadTimeline)
                <div class="portfolio__state portfolio__state--empty">Aktivitas Project membutuhkan izin membaca Linimasa Project.</div>
            @elseif ($activity->isEmpty())
                <div class="portfolio__state portfolio__state--empty">Belum ada aktivitas terbaru yang dapat ditampilkan.</div>
            @else
                <ol class="portfolio__activity-list">
                    @foreach ($activity as $item)
                        <li class="portfolio__activity-item" data-project-activity-type="{{ $item['type'] }}">
                            <div class="portfolio__activity-meta">
                                <span>{{ $item['occurred_at']->format('d M Y H:i') }}</span>
                                <span>{{ $item['mitra'] }}</span>
                            </div>
                            <p>
                                @if ($item['url'])
                                    <a href="{{ $item['url'] }}"><strong>{{ $item['id_project'] }}</strong></a>
                                @else
                                    <strong>{{ $item['id_project'] }}</strong>
                                @endif
                                · {{ $item['project_name'] }} · {{ $item['title'] }}
                            </p>
                            @if ($item['body'])
                                <small>{{ $item['body'] }}</small>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </main>

    <script>
        document.querySelector('form[action="{{ route('portfolio.index') }}"]')?.addEventListener('submit', function () {
            const loading = document.querySelector('[data-portfolio-state="loading"]');
            if (loading) {
                loading.hidden = false;
            }
        });
    </script>
</x-layouts.app>
