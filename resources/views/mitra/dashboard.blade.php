<x-layouts.app>
    <main class="mitra-dashboard">
        <header>
            <p>Mitra · {{ $user->mitra?->nama }}</p>
            <h1>Dashboard Mitra</h1>
            <p>Ringkasan read-only Project, Material, alur permintaan, Transit, dan aktivitas dalam cakupan Anda.</p>
        </header>

        @if (!empty($dashboardError))
            <p role="alert">Ringkasan belum dapat dimuat. Coba lagi atau buka modul sumber.</p>
        @endif

        @if ($user->hasIzin('read_project'))
            <section aria-labelledby="project-summary-title">
                <h2 id="project-summary-title">Project</h2>
                <p>Project aktif: <strong>{{ $projectCounts['active'] }}</strong> · Project selesai: <strong>{{ $projectCounts['completed'] }}</strong></p>
                @forelse ($projects as $card)
                    <article>
                        <h3><a href="{{ route('projects.show', $card['project']) }}">{{ $card['project']->id_project }} · {{ $card['project']->nama }}</a></h3>
                        <p>Status Project: {{ $card['project']->status_project === 'selesai' ? 'Selesai' : 'Aktif' }}</p>
                        @if ($card['verified_percent'] !== null)
                            <p>Progres jasa terverifikasi: {{ number_format($card['verified_percent'], 2, ',', '.') }}%</p>
                        @endif
                        @if ($card['readiness'] !== null)
                            <p>Kesiapan Material Project: {{ number_format($card['readiness']['readiness_percent'], 2, ',', '.') }}% · Transit: {{ number_format($card['readiness']['transit'], 3, ',', '.') }}</p>
                        @endif
                    </article>
                @empty
                    <p>Belum ada Project dalam cakupan Mitra.</p>
                @endforelse
            </section>
        @else
            <section><h2>Project</h2><p>Izin Project belum tersedia.</p></section>
        @endif

        @if ($user->hasIzin('read_master_data'))
            <section aria-labelledby="stock-summary-title">
                <h2 id="stock-summary-title">Saldo Stok Gudang Mitra</h2>
                <p>Diringkas per Material, Unit, dan Warehouse; kuantitas lintas Unit tidak dijumlahkan.</p>
                @forelse ($stocks as $stock)
                    <p>{{ $stock['material']->nama }} · {{ $stock['material']->unit?->nama }} · {{ $stock['warehouse']->nama }}: <strong>{{ number_format($stock['qty'], 3, ',', '.') }}</strong></p>
                @empty
                    <p>Belum ada Saldo Stok Gudang Mitra.</p>
                @endforelse
            </section>
        @endif

        @if ($user->hasIzin('read_material_request'))
            <section aria-labelledby="request-summary-title">
                <h2 id="request-summary-title">Request Material</h2>
                @forelse ($requests as $request)
                    <p><a href="{{ route('material-requests.show', $request) }}">Request Material #{{ $request->id }}</a> · {{ $request->project?->nama ?? 'Tanpa Project' }} · {{ $request->status }}</p>
                @empty
                    <p>Belum ada Request Material.</p>
                @endforelse
            </section>
        @endif

        @if ($user->hasIzin('read_material_usage'))
            <section aria-labelledby="usage-summary-title">
                <h2 id="usage-summary-title">Pemakaian Material</h2>
                @forelse ($usages as $usage)
                    <p id="pemakaian-material-{{ $usage->id }}"><a href="{{ route('material-usages.index') }}#pemakaian-material-{{ $usage->id }}">{{ $usage->material?->nama ?? 'Material' }}</a> · {{ $usage->project?->nama ?? 'Tanpa Project' }} · {{ $usage->status }}</p>
                @empty
                    <p>Belum ada Pemakaian Material.</p>
                @endforelse
            </section>
        @endif

        <section aria-labelledby="transit-summary-title">
            <h2 id="transit-summary-title">Transit</h2>
            @forelse ($transits as $transit)
                <p><a href="{{ route('warehouse.transfers.print', $transit) }}">{{ $transit->nomor }}</a> · {{ $transit->origin?->nama }} → {{ $transit->destination?->nama }} · Terbit</p>
            @empty
                <p>Tidak ada Transit aktif.</p>
            @endforelse
        </section>

        @if ($user->hasIzin('read_project_timeline'))
            <section aria-labelledby="activity-summary-title">
                <h2 id="activity-summary-title">Aktivitas terbaru</h2>
                @forelse ($activities as $activity)
                    <p>{{ $activity->created_at?->format('d M Y H:i') }} · <a href="{{ route('projects.show', $activity->project) }}">{{ $activity->project?->id_project }}</a> · {{ $activity->body ?: $activity->event_key }}</p>
                @empty
                    <p>Belum ada aktivitas terbaru.</p>
                @endforelse
            </section>
        @endif
    </main>
</x-layouts.app>
