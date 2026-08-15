<x-layouts.app>
    <style>
        .command-center {
            box-sizing: border-box;
            margin: 0 auto;
            max-width: 1120px;
            padding: 32px 20px 48px;
            color: #172033;
            font-family: ui-sans-serif, system-ui, sans-serif;
        }

        .command-center *, .command-center *::before, .command-center *::after { box-sizing: border-box; }
        .command-center a { color: #155e75; }
        .command-center__header { align-items: flex-start; display: flex; gap: 24px; justify-content: space-between; }
        .command-center__header h1 { margin: 0 0 8px; font-size: clamp(1.8rem, 4vw, 2.75rem); letter-spacing: -0.04em; }
        .command-center__header p { color: #526071; margin: 0; }
        .command-center__logout { flex: 0 0 auto; }
        .command-center__logout button { background: transparent; border: 1px solid #b9c3d0; border-radius: 8px; color: #172033; cursor: pointer; padding: 9px 14px; }
        .command-center__nav { border-bottom: 1px solid #dbe2ea; display: flex; flex-wrap: wrap; gap: 8px 18px; margin: 28px 0; padding-bottom: 14px; }
        .command-center__nav a { border-radius: 6px; padding: 7px 4px; text-decoration: none; }
        .command-center__nav a[aria-current="page"] { background: #e0f2fe; font-weight: 700; padding-left: 9px; padding-right: 9px; }
        .command-center__panel { background: #fff; border: 1px solid #dbe2ea; border-radius: 14px; box-shadow: 0 8px 24px rgb(23 32 51 / 6%); padding: 22px; }
        .command-center > .command-center__panel + .command-center__panel { margin-top: 20px; }
        .command-center__panel-header { align-items: flex-start; display: flex; gap: 16px; justify-content: space-between; }
        .command-center__panel h2 { margin: 0 0 5px; font-size: 1.25rem; }
        .command-center__panel-header p { color: #526071; margin: 0; }
        .command-center__metric { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 10px; display: block; min-width: 190px; padding: 14px 16px; text-decoration: none; }
        .command-center__metric strong { color: #0c4a6e; display: block; font-size: 1.8rem; line-height: 1; }
        .command-center__metric span { color: #164e63; display: block; font-size: 0.9rem; margin-top: 7px; }
        .command-center__list { display: grid; gap: 10px; list-style: none; margin: 20px 0 0; padding: 0; }
        .command-center__item { align-items: center; border: 1px solid #e2e8f0; border-radius: 10px; display: flex; gap: 16px; justify-content: space-between; padding: 14px 16px; }
        .command-center__item a { font-weight: 700; text-decoration: none; }
        .command-center__item p { color: #526071; margin: 4px 0 0; }
        .command-center__item-status { background: #fef3c7; border-radius: 999px; color: #92400e; font-size: 0.82rem; padding: 5px 9px; white-space: nowrap; }
        .command-center__item-status--danger { background: #fee2e2; color: #991b1b; }
        .command-center__state { border-radius: 10px; margin-top: 20px; padding: 16px; }
        .command-center__state--empty { background: #f8fafc; color: #526071; }
        .command-center__state--error { background: #fef2f2; color: #991b1b; }
        .command-center__state--loading { background: #f0f9ff; color: #155e75; }
        .command-center__item--start { align-items: flex-start; }
        @media (max-width: 680px) {
            .command-center { padding: 24px 14px 36px; }
            .command-center__header, .command-center__panel-header, .command-center__item { align-items: stretch; flex-direction: column; }
            .command-center__logout, .command-center__logout button, .command-center__metric { width: 100%; }
            .command-center__item-status { align-self: flex-start; }
        }
    </style>

    <main class="command-center" aria-labelledby="command-center-title">
        <header class="command-center__header">
            <div>
                <p>Workspace User THC</p>
                <h1 id="command-center-title">Command Center</h1>
                <p>Prioritas operasional yang membutuhkan perhatian dan keputusan.</p>
            </div>
            <form class="command-center__logout" method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Keluar</button>
            </form>
        </header>

        <nav class="command-center__nav" aria-label="Menu utama">
            <a href="{{ route('dashboard') }}" aria-current="page">Command Center</a>
            @if ($user->hasIzin('read_project'))
                <a href="{{ route('projects.index') }}">Project</a>
            @endif
            @if ($user->hasIzin('manage_users'))
                <a href="{{ route('admin.users') }}">User</a>
            @endif
            @if ($user->mitra_id === null && $user->hasIzin('manage_warehouses'))
                <a href="{{ route('admin.warehouses') }}">Warehouse</a>
            @endif
            @if ($user->hasIzin('read_material_request'))
                <a href="{{ route('material-requests.index') }}">Request Material</a>
            @endif
            @if ($user->hasIzin('read_master_data'))
                <a href="{{ route('admin.materials') }}">Material</a>
                <a href="{{ route('admin.master.index', 'units') }}">Unit</a>
                <a href="{{ route('admin.master.index', 'pops') }}">PoP</a>
                <a href="{{ route('admin.master.index', 'pekerjaan-jasa') }}">Pekerjaan Jasa</a>
            @endif
        </nav>

        @if ($user->hasIzin('read_material_request'))
            <section id="material-request-panel" class="command-center__panel" aria-labelledby="material-request-queue-title" aria-busy="false">
                <div class="command-center__panel-header">
                    <div>
                        <h2 id="material-request-queue-title">Request Material yang membutuhkan keputusan</h2>
                        <p>Hanya Request Material berstatus <strong>diajukan</strong> yang ditampilkan.</p>
                    </div>
                    <a class="command-center__metric" href="{{ route('material-requests.index') }}">
                        <strong>{{ $pendingMaterialRequests->count() }}</strong>
                        <span>Request Material menunggu keputusan</span>
                    </a>
                </div>

                <div id="command-center-loading" class="command-center__state command-center__state--loading" data-dashboard-state="loading" role="status" aria-live="polite" hidden>
                    Memuat antrean Request Material…
                </div>

                @if ($materialRequestError)
                    <div class="command-center__state command-center__state--error" data-dashboard-state="error" role="alert">
                        {{ $materialRequestError }}
                        <a href="{{ route('material-requests.index') }}">Buka modul Request Material</a> untuk mencoba lagi.
                    </div>
                @elseif ($pendingMaterialRequests->isEmpty())
                    <div class="command-center__state command-center__state--empty" data-dashboard-state="empty">
                        Tidak ada Request Material yang menunggu keputusan THC.
                    </div>
                @else
                    <ul class="command-center__list" data-dashboard-state="ready">
                        @foreach ($pendingMaterialRequests as $materialRequest)
                            <li class="command-center__item" data-request-id="{{ $materialRequest->id }}">
                                <div>
                                    <a href="{{ route('material-requests.show', $materialRequest) }}">Request Material #{{ $materialRequest->id }}</a>
                                    <p>
                                        @if ($materialRequest->mitra) {{ $materialRequest->mitra->nama }} · @endif
                                        {{ $materialRequest->items->count() }} item material
                                    </p>
                                </div>
                                <span class="command-center__item-status">Menunggu keputusan</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <script>
                    (() => {
                        const panel = document.getElementById('material-request-panel');
                        const loading = document.getElementById('command-center-loading');

                        panel?.querySelectorAll('a').forEach((link) => {
                            link.addEventListener('click', () => {
                                if (link.target !== '_blank') {
                                    loading.hidden = false;
                                    panel.setAttribute('aria-busy', 'true');
                                }
                            });
                        });
                    })();
                </script>
            </section>
        @endif

        @if ($user->hasIzin('operate_warehouse'))
            <section id="delayed-transit-panel" class="command-center__panel" aria-labelledby="delayed-transit-title" aria-busy="false">
                <div class="command-center__panel-header">
                    <div>
                        <h2 id="delayed-transit-title">Transit terlambat</h2>
                        <p>Surat Jalan berstatus <strong>terbit</strong> yang lebih dari 3 hari berdasarkan waktu terbit.</p>
                    </div>
                    <a class="command-center__metric" href="{{ route('warehouse.transit') }}">
                        <strong>{{ $delayedTransits->count() }}</strong>
                        <span>Transit terlambat</span>
                    </a>
                </div>

                <div id="delayed-transit-loading" class="command-center__state command-center__state--loading" data-dashboard-state="loading" role="status" aria-live="polite" hidden>
                    Memuat antrean Transit…
                </div>

                @if ($transitError)
                    <div class="command-center__state command-center__state--error" data-dashboard-state="error" role="alert">
                        {{ $transitError }}
                        <a href="{{ route('warehouse.transit') }}">Buka modul Transit</a> untuk mencoba lagi.
                    </div>
                @elseif ($delayedTransits->isEmpty())
                    <div class="command-center__state command-center__state--empty" data-dashboard-state="empty">
                        Tidak ada Transit yang melewati batas 3 hari.
                    </div>
                @else
                    <ul class="command-center__list" data-dashboard-state="ready">
                        @foreach ($delayedTransits as $suratJalan)
                            <li class="command-center__item command-center__item--start" data-surat-jalan-id="{{ $suratJalan->id }}">
                                <div>
                                    <a href="{{ route('warehouse.transfers.print', $suratJalan) }}">{{ $suratJalan->nomor }}</a>
                                    <p>{{ $suratJalan->origin->nama }} → {{ $suratJalan->destination->nama }}</p>
                                    <p>Warehouse: {{ $suratJalan->origin->nama }} → {{ $suratJalan->destination->nama }}</p>
                                    <p>Material:
                                        @foreach ($suratJalan->items as $item)
                                            {{ $item->material->nama }} ({{ $item->qty }} {{ $item->material->unit->nama }})@if (!$loop->last), @endif
                                        @endforeach
                                    </p>
                                    <p>Terbit {{ $suratJalan->issued_at->format('d M Y H:i') }} · Umur Transit {{ $suratJalan->issued_at->diffInDays(now()) }} hari · Lebih dari 3 hari</p>
                                </div>
                                <span class="command-center__item-status command-center__item-status--danger">{{ $suratJalan->items->count() }} item</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <script>
                    (() => {
                        const panel = document.getElementById('delayed-transit-panel');
                        const loading = document.getElementById('delayed-transit-loading');

                        panel?.querySelectorAll('a').forEach((link) => {
                            link.addEventListener('click', () => {
                                if (link.target !== '_blank') {
                                    loading.hidden = false;
                                    panel.setAttribute('aria-busy', 'true');
                                }
                            });
                        });
                    })();
                </script>
            </section>
        @endif

        @if ($user->hasIzin('read_master_data'))
            <section id="critical-stock-panel" class="command-center__panel" aria-labelledby="critical-stock-title" aria-busy="false">
                <div class="command-center__panel-header">
                    <div>
                        <h2 id="critical-stock-title">Stok kritis</h2>
                        <p>Material dengan saldo aktual Warehouse pada atau di bawah ambang minimum positif.</p>
                    </div>
                    <a class="command-center__metric" href="{{ route('admin.materials') }}">
                        <strong>{{ $criticalStocks->count() }}</strong>
                        <span>Material kritis</span>
                    </a>
                </div>

                <div id="critical-stock-loading" class="command-center__state command-center__state--loading" data-dashboard-state="loading" role="status" aria-live="polite" hidden>
                    Memuat ringkasan stok…
                </div>

                @if ($criticalStockError)
                    <div class="command-center__state command-center__state--error" data-dashboard-state="error" role="alert">
                        {{ $criticalStockError }}
                        <a href="{{ route('admin.materials') }}">Buka modul Material</a> untuk mencoba lagi.
                    </div>
                @elseif ($criticalStocks->isEmpty())
                    <div class="command-center__state command-center__state--empty" data-dashboard-state="empty">
                        Tidak ada Material dengan stok kritis. Material tanpa ambang minimum tidak dihitung.
                    </div>
                @else
                    <ul class="command-center__list" data-dashboard-state="ready">
                        @foreach ($criticalStocks as $material)
                            <li class="command-center__item command-center__item--start" data-material-id="{{ $material->id }}">
                                <div>
                                    <a href="{{ route('admin.materials') }}#material-{{ $material->id }}">{{ $material->nama }}</a>
                                    <p>{{ $material->kode }} · {{ ucfirst(str_replace('_', ' ', $material->jenis)) }}</p>
                                    <p>Warehouse:
                                        @forelse ($material->stocks as $stock)
                                            {{ $stock->warehouse?->nama }}@if (!$loop->last), @endif
                                        @empty
                                            —
                                        @endforelse
                                    </p>
                                </div>
                                <span class="command-center__item-status">
                                    {{ number_format((float) $material->actual_balance, 3, '.', '') }} {{ $material->unit->nama }} · minimum {{ number_format((float) $material->ambang_minimum, 3, '.', '') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <script>
                    (() => {
                        const panel = document.getElementById('critical-stock-panel');
                        const loading = document.getElementById('critical-stock-loading');

                        panel?.querySelectorAll('a').forEach((link) => {
                            link.addEventListener('click', () => {
                                if (link.target !== '_blank') {
                                    loading.hidden = false;
                                    panel.setAttribute('aria-busy', 'true');
                                }
                            });
                        });
                    })();
                </script>
            </section>
        @endif
    </main>
</x-layouts.app>
