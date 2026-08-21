<x-layouts.app>
    <x-ui.page>
        <x-ui.page-header eyebrow="Mitra · {{ $user->mitra?->nama }}" title="Dashboard Mitra" subtitle="Ringkasan read-only Project, Material, alur permintaan, Transit, dan aktivitas dalam cakupan Anda." />

        @if (!empty($dashboardError))
            <div class="ui-state ui-state--error" role="alert">Ringkasan belum dapat dimuat. Coba lagi atau buka modul sumber.</div>
        @endif

        <section class="ui-grid" data-dashboard-kpis aria-label="KPI Dashboard Mitra">
            @if ($user->hasIzin('read_project'))
                <x-ui.panel><div class="ui-section-head"><div><p class="ui-help">Project aktif</p><strong>{{ $projectCounts['active'] }}</strong></div><x-ui.badge tone="done" label="Operasional" /></div></x-ui.panel>
                <x-ui.panel><div class="ui-section-head"><div><p class="ui-help">Project selesai</p><strong>{{ $projectCounts['completed'] }}</strong></div><x-ui.badge tone="neutral" label="Historis" /></div></x-ui.panel>
            @endif
            @if ($user->hasIzin('read_material_request'))
                <x-ui.panel><div class="ui-section-head"><div><p class="ui-help">Request Material</p><strong>{{ $requestCount }}</strong></div><x-ui.badge tone="warning" label="Alur material" /></div></x-ui.panel>
            @endif
            @if ($warehouseCount !== null)
                <x-ui.panel><div class="ui-section-head"><div><p class="ui-help">Warehouse aktif</p><strong>{{ $warehouseCount }}</strong></div><x-ui.badge tone="neutral" label="Tenant" /></div></x-ui.panel>
            @endif
            @if ($activeUserCount !== null)
                <x-ui.panel><div class="ui-section-head"><div><p class="ui-help">User aktif</p><strong>{{ $activeUserCount }}</strong></div><x-ui.badge tone="neutral" label="Mitra" /></div></x-ui.panel>
            @endif
        </section>

        @if ($user->hasIzin('read_project'))
            <x-ui.panel>
                <div class="ui-section-head"><div><h2 id="project-summary-title">Project</h2><p class="ui-help">Project aktif: <strong>{{ $projectCounts['active'] }}</strong> · Project selesai: <strong>{{ $projectCounts['completed'] }}</strong></p></div><x-ui.badge tone="neutral" label="{{ $projectCounts['active'] + $projectCounts['completed'] }} Project" /></div>
                @forelse ($projects as $card)
                    <article class="ui-list__item"><h3><a href="{{ route('projects.show', $card['project']) }}">{{ $card['project']->id_project }} · {{ $card['project']->nama }}</a></h3><p>Status Project: <x-ui.badge :tone="$card['project']->status_project === 'selesai' ? 'done' : 'neutral'" :label="$card['project']->status_project === 'selesai' ? 'Selesai' : 'Aktif'" /></p>@if ($card['verified_percent'] !== null)<p>Progres jasa terverifikasi: {{ number_format($card['verified_percent'], 2, ',', '.') }}%</p>@endif @if ($card['readiness'] !== null)<p>Kesiapan Material Project: {{ number_format($card['readiness']['readiness_percent'], 2, ',', '.') }}% · Transit: {{ number_format($card['readiness']['transit'], 3, ',', '.') }}</p>@endif</article>
                @empty
                    <x-ui.empty-state title="Belum ada Project dalam cakupan Mitra." />
                @endforelse
            </x-ui.panel>
        @else
            <x-ui.empty-state title="Project" message="Izin Project belum tersedia." />
        @endif

        @if ($user->hasIzin('read_master_data'))
            <x-ui.panel>
                <div class="ui-section-head"><div><h2 id="stock-summary-title">Saldo Stok Gudang Mitra</h2><p class="ui-help">Diringkas per Material, Unit, dan Warehouse; kuantitas lintas Unit tidak dijumlahkan.</p></div></div>
                @forelse ($stocks as $stock)
                    <p class="ui-list__item">{{ $stock['material']->nama }} · {{ $stock['material']->unit?->nama }} · {{ $stock['warehouse']->nama }}: <strong>{{ \App\Support\QuantityDisplayFormatter::format($stock['qty']) }}</strong></p>
                @empty
                    <x-ui.empty-state title="Belum ada Saldo Stok Gudang Mitra." />
                @endforelse
            </x-ui.panel>
        @endif

        @if ($user->hasIzin('read_material_request'))
            <x-ui.panel>
                <h2 id="request-summary-title">Request Material</h2>
                @forelse ($requests as $request)
                    <div class="ui-list__item">
                        <a href="{{ route('material-requests.show', $request) }}">Request Material #{{ $request->id }}</a>
                        <p class="ui-help">Project:
                            @if ($request->project)
                                @if ($user->hasIzin('read_project'))
                                    <a href="{{ route('projects.show', $request->project) }}">{{ $request->project->id_project }} — {{ $request->project->nama }}</a>
                                @else
                                    {{ $request->project->id_project }} — {{ $request->project->nama }}
                                @endif
                            @else
                                Tanpa Project
                            @endif
                        </p>
                        <x-material-request-status :status="$request->status" />
                    </div>
                @empty
                    <x-ui.empty-state title="Belum ada Request Material." />
                @endforelse
            </x-ui.panel>
        @endif

        @if ($user->hasIzin('read_material_usage'))
            <x-ui.panel>
                <h2 id="usage-summary-title">Pemakaian Material</h2>
                @forelse ($usages as $usage)
                    <p class="ui-list__item" id="pemakaian-material-{{ $usage->id }}"><a href="{{ route('material-usages.index') }}#pemakaian-material-{{ $usage->id }}">{{ $usage->material?->nama ?? 'Material' }}</a> · {{ $usage->project?->nama ?? 'Tanpa Project' }} · <x-ui.badge tone="warning" :label="$usage->status" /></p>
                @empty
                    <x-ui.empty-state title="Belum ada Pemakaian Material." />
                @endforelse
            </x-ui.panel>
        @endif

        @if ($user->hasIzin('read_transit'))
            <x-ui.panel>
                <h2 id="transit-summary-title">Transit</h2>
                @forelse ($transits as $transit)
                    <p class="ui-list__item"><a href="{{ route('warehouse.transfers.print', $transit) }}">{{ $transit->nomor }}</a> · {{ $transit->origin?->nama }} → {{ $transit->destination?->nama }} · <x-ui.badge tone="warning" label="Terbit" /></p>
                @empty
                    <x-ui.empty-state title="Tidak ada Transit aktif." />
                @endforelse
            </x-ui.panel>
        @endif

        @if ($user->hasIzin('read_project_timeline'))
            <x-ui.panel>
                <h2 id="activity-summary-title">Aktivitas terbaru</h2>
                @forelse ($activities as $activity)
                    <p class="ui-list__item">{{ $activity->created_at?->format('d M Y H:i') }} · <a href="{{ route('projects.show', $activity->project) }}">{{ $activity->project?->id_project }}</a> · {{ $activity->body ?: $activity->event_key }}</p>
                @empty
                    <x-ui.empty-state title="Belum ada aktivitas terbaru." />
                @endforelse
            </x-ui.panel>
        @endif
    </x-ui.page>
</x-layouts.app>
