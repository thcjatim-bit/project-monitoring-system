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
        @media (max-width: 680px) {
            .portfolio { padding: 24px 14px 36px; }
            .portfolio__kpis { grid-template-columns: 1fr; }
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
                        <select id="filter-project" name="project">
                            <option value="">Semua Project</option>
                            @foreach ($options['projects'] as $option)
                                <option value="{{ $option->id }}" @selected($filters['project'] === (int) $option->id)>{{ $option->id_project }} — {{ $option->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="portfolio__field">
                        <label for="filter-mitra">Mitra</label>
                        <select id="filter-mitra" name="mitra">
                            <option value="">Semua Mitra</option>
                            @foreach ($options['mitras'] as $option)
                                <option value="{{ $option->id }}" @selected($filters['mitra'] === (int) $option->id)>{{ $option->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="portfolio__field">
                        <label for="filter-periode">Periode</label>
                        <select id="filter-periode" name="periode">
                            @foreach ($options['periodes'] as $value => $label)
                                <option value="{{ $value }}" @selected($filters['periode'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="portfolio__field">
                        <label for="filter-risiko">Status risiko</label>
                        <select id="filter-risiko" name="risiko">
                            @foreach ($options['risikos'] as $value => $label)
                                <option value="{{ $value }}" @selected($filters['risiko'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="portfolio__filter-actions">
                    <button type="submit">Terapkan filter</button>
                    <a href="{{ route('portfolio.index') }}">Reset filter</a>
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
                            <p>Rata-rata kesiapan {{ $kpis['material_projects'] }} Project ber-RAB Material, posisi terkini di luar filter periode. {{ $kpis['material_transit_projects'] }} Project masih punya Material Transit yang belum dihitung sebagai Material tersedia.</p>
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
    </main>
</x-layouts.app>
