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
        .command-center__metrics { display: flex; flex-wrap: wrap; gap: 10px; justify-content: flex-end; }
        .command-center__metric { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 10px; display: block; min-width: 190px; padding: 14px 16px; text-decoration: none; }
        .command-center__metric--compact { min-width: 132px; }
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
            .command-center__metrics { justify-content: stretch; width: 100%; }
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
            @if ($user->hasIzin('manage_mitras'))
                <a href="{{ route('admin.mitras') }}">Mitra</a>
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

        <section id="activity-feed-panel" class="command-center__panel" aria-labelledby="activity-feed-title" aria-busy="false">
            <div class="command-center__panel-header">
                <div>
                    <h2 id="activity-feed-title">Aktivitas lintas operasional</h2>
                    <p>Ringkasan navigasi dari timestamp dan status domain yang sudah tersimpan, bukan audit log perubahan field.</p>
                </div>
                <span class="command-center__item-status">{{ $activityFeed->count() }} aktivitas</span>
            </div>

            <div id="activity-feed-loading" class="command-center__state command-center__state--loading" data-dashboard-state="loading" role="status" aria-live="polite" hidden>
                Memuat aktivitas lintas operasional…
            </div>

            @if ($activityFeedError)
                <div class="command-center__state command-center__state--error" data-dashboard-state="error" role="alert">
                    {{ $activityFeedError }}
                </div>
            @elseif ($activityFeed->isEmpty())
                <div class="command-center__state command-center__state--empty" data-dashboard-state="empty">
                    Belum ada aktivitas lintas operasional yang dapat ditampilkan.
                </div>
            @else
                <ul class="command-center__list" data-dashboard-state="ready">
                    @foreach ($activityFeed as $activity)
                        <li class="command-center__item command-center__item--start" data-activity-source="{{ $activity['source'] }}" data-activity-entity="{{ $activity['entity'] }}">
                            <div>
                                <a href="{{ $activity['url'] }}">{{ $activity['title'] }}</a>
                                <p>{{ $activity['source'] }} · {{ $activity['entity'] }} · {{ $activity['description'] }}</p>
                                <p>Aktivitas {{ $activity['occurred_at']->format('d M Y H:i') }}</p>
                            </div>
                            <span class="command-center__item-status">{{ $activity['status'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <script>
                (() => {
                    const panel = document.getElementById('activity-feed-panel');
                    const loading = document.getElementById('activity-feed-loading');

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

        @if ($user->hasIzin('manage_users'))
            <section id="active-user-panel" class="command-center__panel" aria-labelledby="active-user-title" aria-busy="false">
                <div class="command-center__panel-header">
                    <div>
                        <h2 id="active-user-title">User aktif</h2>
                        <p>Kapasitas aktual User aktif, dibedakan antara User THC dan User Mitra.</p>
                    </div>
                    <div class="command-center__metrics">
                        <a class="command-center__metric command-center__metric--compact" href="{{ route('admin.users') }}">
                            <strong>{{ $activeUserCounts['total'] }}</strong>
                            <span>Total User aktif</span>
                        </a>
                        <a class="command-center__metric command-center__metric--compact" href="{{ route('admin.users') }}">
                            <strong>{{ $activeUserCounts['thc'] }}</strong>
                            <span>User THC aktif</span>
                        </a>
                        <a class="command-center__metric command-center__metric--compact" href="{{ route('admin.users') }}">
                            <strong>{{ $activeUserCounts['mitra'] }}</strong>
                            <span>User Mitra aktif</span>
                        </a>
                    </div>
                </div>

                <div id="active-user-loading" class="command-center__state command-center__state--loading" data-dashboard-state="loading" role="status" aria-live="polite" hidden>
                    Memuat kapasitas User aktif…
                </div>

                @if ($activeUserError)
                    <div class="command-center__state command-center__state--error" data-dashboard-state="error" role="alert">
                        {{ $activeUserError }}
                        <a href="{{ route('admin.users') }}">Buka modul User</a> untuk mencoba lagi.
                    </div>
                @elseif ($activeUserCounts['total'] === 0)
                    <div class="command-center__state command-center__state--empty" data-dashboard-state="empty">
                        Belum ada User aktif yang tercatat.
                    </div>
                @endif

                <script>
                    (() => {
                        const panel = document.getElementById('active-user-panel');
                        const loading = document.getElementById('active-user-loading');

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

        @if ($user->hasIzin('manage_mitras'))
            <section id="recent-mitra-onboarding-panel" class="command-center__panel" aria-labelledby="recent-mitra-onboarding-title" aria-busy="false">
                <div class="command-center__panel-header">
                    <div>
                        <h2 id="recent-mitra-onboarding-title">Onboarding Mitra terbaru</h2>
                        <p>Mitra yang dibuat dalam 30 hari kalender terakhir beserta konteks admin-mitra pertamanya.</p>
                    </div>
                    <a class="command-center__metric" href="{{ route('admin.mitras') }}">
                        <strong>{{ $recentMitraOnboardings->count() }}</strong>
                        <span>Onboarding Mitra terbaru</span>
                    </a>
                </div>

                <div id="recent-mitra-onboarding-loading" class="command-center__state command-center__state--loading" data-dashboard-state="loading" role="status" aria-live="polite" hidden>
                    Memuat Onboarding Mitra terbaru…
                </div>

                @if ($recentMitraOnboardingError)
                    <div class="command-center__state command-center__state--error" data-dashboard-state="error" role="alert">
                        {{ $recentMitraOnboardingError }}
                        <a href="{{ route('admin.mitras') }}">Buka modul Mitra</a> untuk mencoba lagi.
                    </div>
                @elseif ($recentMitraOnboardings->isEmpty())
                    <div class="command-center__state command-center__state--empty" data-dashboard-state="empty">
                        Belum ada Mitra yang dibuat dalam 30 hari kalender terakhir.
                    </div>
                @else
                    <ul class="command-center__list" data-dashboard-state="ready">
                        @foreach ($recentMitraOnboardings as $mitra)
                            <li id="recent-mitra-{{ $mitra->id }}" class="command-center__item command-center__item--start">
                                <div>
                                    <a href="{{ route('admin.mitras') }}#mitra-{{ $mitra->id }}">{{ $mitra->nama }}</a>
                                    <p>{{ $mitra->kode }} · Dibuat {{ $mitra->created_at->format('d M Y H:i') }}</p>
                                    <p>Admin-mitra pertama:
                                        @if ($mitra->adminMitraPertama)
                                            {{ $mitra->adminMitraPertama->name }} · {{ $mitra->adminMitraPertama->email }}
                                        @else
                                            Belum tersedia
                                        @endif
                                    </p>
                                </div>
                                <span class="command-center__item-status">30 hari terakhir</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <script>
                    (() => {
                        const panel = document.getElementById('recent-mitra-onboarding-panel');
                        const loading = document.getElementById('recent-mitra-onboarding-loading');

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
