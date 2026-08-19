<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Project Monitoring System</title>
        <style>
            :root {
                color-scheme: light;
                --app-ink: #172033;
                --app-muted: #667085;
                --app-line: #dbe2eb;
                --app-canvas: #f2f5f9;
                --app-surface: #ffffff;
                --app-accent: #4656d8;
                --app-accent-strong: #3042c2;
                --app-accent-soft: #edf0ff;
                --app-success: #16845f;
                --app-success-soft: #e9f8f0;
                --app-warning: #b56a12;
                --app-warning-soft: #fff3df;
                --app-danger: #c04d59;
                --app-danger-soft: #fff0f1;
                --app-sidebar: #18243a;
                --app-sidebar-muted: #b8c4d9;
                --app-shadow: 0 14px 38px rgb(28 45 72 / 8%);
                --app-radius: 16px;
                font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            }

            *, *::before, *::after { box-sizing: border-box; }
            html { background: var(--app-canvas); }
            body { margin: 0; min-width: 320px; color: var(--app-ink); background: var(--app-canvas); }
            a { color: var(--app-accent-strong); }
            button, input, select, textarea { font: inherit; }
            button { cursor: pointer; }

            .app-shell__skip-link {
                position: fixed;
                z-index: 100;
                top: 12px;
                left: 12px;
                padding: 9px 12px;
                border-radius: 9px;
                color: #fff;
                background: var(--app-accent-strong);
                transform: translateY(-160%);
            }
            .app-shell__skip-link:focus { transform: translateY(0); }
            .app-shell { display: grid; grid-template-columns: 248px minmax(0, 1fr); min-height: 100vh; }
            .app-shell__sidebar {
                position: sticky;
                top: 0;
                display: flex;
                min-height: 100vh;
                flex-direction: column;
                padding: 20px 12px;
                color: #f3f6fc;
                background: var(--app-sidebar);
            }
            .app-shell__brand {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 2px 9px 20px;
                border-bottom: 1px solid rgb(255 255 255 / 12%);
                color: #fff;
                text-decoration: none;
            }
            .app-shell__brand-mark {
                display: grid;
                width: 34px;
                height: 34px;
                place-items: center;
                border-radius: 11px;
                color: var(--app-sidebar);
                background: #b9c4ff;
                font-size: 14px;
                font-weight: 900;
            }
            .app-shell__brand-copy { display: grid; gap: 1px; }
            .app-shell__brand-copy strong { font-size: 14px; letter-spacing: -.02em; }
            .app-shell__brand-copy small { color: #99a9c2; font-size: 10px; }
            .app-shell__nav { display: grid; gap: 18px; padding-top: 20px; }
            .app-shell__nav-group { display: grid; gap: 4px; }
            .app-shell__nav-label {
                padding: 0 9px 5px;
                color: #8193ae;
                font-size: 10px;
                font-weight: 850;
                letter-spacing: .1em;
                text-transform: uppercase;
            }
            .app-shell__nav-link {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
                min-height: 38px;
                padding: 9px;
                border-radius: 9px;
                color: var(--app-sidebar-muted);
                font-size: 12px;
                font-weight: 700;
                text-decoration: none;
            }
            .app-shell__nav-link:hover,
            .app-shell__nav-link.is-active {
                color: #fff;
                background: rgb(185 196 255 / 16%);
            }
            .app-shell__nav-link.is-active { box-shadow: inset 3px 0 0 #b9c4ff; }
            .app-shell__nav-link-mark {
                display: grid;
                width: 22px;
                height: 22px;
                place-items: center;
                border-radius: 7px;
                color: #cbd4ff;
                background: rgb(185 196 255 / 12%);
                font-size: 9px;
                font-weight: 900;
            }
            .app-shell__nav-link-copy { display: flex; flex: 1; align-items: center; gap: 8px; }
            .app-shell__nav-note { padding: 4px 9px 0 39px; color: #8193ae; font-size: 10px; line-height: 1.35; }
            .app-shell__sidebar-footer { margin-top: auto; padding: 16px 10px 4px; color: #99a9c2; font-size: 10px; }
            .app-shell__sidebar-footer strong { display: block; margin-bottom: 3px; color: #eef3ff; font-size: 12px; }
            .app-shell__body { min-width: 0; }
            .app-shell__topbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 18px;
                min-height: 74px;
                padding: 15px 32px;
                border-bottom: 1px solid rgb(100 117 139 / 18%);
                background: rgb(255 255 255 / 88%);
                backdrop-filter: blur(14px);
            }
            .app-shell__context { display: grid; gap: 2px; }
            .app-shell__context small { color: var(--app-muted); font-size: 10px; font-weight: 750; }
            .app-shell__context strong { font-size: 16px; letter-spacing: -.03em; }
            .app-shell__identity { display: flex; align-items: center; gap: 12px; }
            .app-shell__identity-copy { display: grid; gap: 1px; text-align: right; }
            .app-shell__identity-copy strong { font-size: 12px; }
            .app-shell__identity-copy span { color: var(--app-muted); font-size: 10px; }
            .app-shell__avatar {
                display: grid;
                width: 36px;
                height: 36px;
                place-items: center;
                border-radius: 12px;
                color: #fff;
                background: var(--app-accent);
                font-size: 11px;
                font-weight: 900;
            }
            .app-shell__logout { margin: 0; }
            .app-shell__logout button {
                min-height: 34px;
                padding: 7px 10px;
                border: 1px solid var(--app-line);
                border-radius: 9px;
                color: var(--app-muted);
                background: #fff;
                font-size: 11px;
                font-weight: 750;
            }
            .app-shell__logout button:hover { color: var(--app-danger); border-color: #f2c8cc; background: var(--app-danger-soft); }
            .app-shell__page { min-width: 0; padding: 30px 32px 60px; }
            .app-shell__page > .command-center { max-width: 1120px; margin: 0 auto; padding: 0; }

            /* Shared Variant A foundation for the existing domain pages. */
            .app-shell__page > h1,
            .app-shell__page > h2,
            .app-shell__page > p,
            .app-shell__page > form,
            .app-shell__page > ul,
            .app-shell__page > section,
            .app-shell__page > article,
            .app-shell__page > main:not(.command-center):not(.control-room) {
                max-width: 1120px;
                margin-right: auto;
                margin-left: auto;
            }
            .app-shell__page > h1 { margin-top: 0; margin-bottom: 8px; font-size: clamp(26px, 3vw, 38px); line-height: 1.08; letter-spacing: -.045em; }
            .app-shell__page > h2 { margin-top: 26px; margin-bottom: 9px; font-size: 18px; letter-spacing: -.025em; }
            .app-shell__page > p { color: var(--app-muted); }
            .app-shell__page > main:not(.command-center):not(.control-room) { width: 100%; }
            .app-shell__page > main:not(.command-center):not(.control-room) h1 { margin: 0 0 8px; font-size: clamp(26px, 3vw, 38px); line-height: 1.08; letter-spacing: -.045em; }
            .app-shell__page > main:not(.command-center):not(.control-room) h2 { margin: 26px 0 9px; font-size: 18px; }
            .app-shell__page > main:not(.command-center):not(.control-room) > p { color: var(--app-muted); }
            .app-shell__page > main:not(.command-center):not(.control-room) > form,
            .app-shell__page > form,
            .app-shell__page > section,
            .app-shell__page > article {
                padding: 20px;
                border: 1px solid var(--app-line);
                border-radius: var(--app-radius);
                background: var(--app-surface);
                box-shadow: var(--app-shadow);
            }
            .app-shell__page > main:not(.command-center):not(.control-room) > form + h2,
            .app-shell__page > form + h2 { margin-top: 28px; }
            .app-shell__page :where(input:not([type="hidden"]), select, textarea) {
                min-height: 40px;
                padding: 8px 10px;
                border: 1px solid #cdd6e2;
                border-radius: 9px;
                color: var(--app-ink);
                background: #fff;
            }
            .app-shell__page :where(input:not([type="hidden"]), select, textarea):focus {
                border-color: var(--app-accent);
                outline: 0;
                box-shadow: 0 0 0 3px var(--app-accent-soft);
            }
            .app-shell__page :where(label) { display: inline-flex; flex-direction: column; gap: 6px; margin: 0 10px 12px 0; color: #445269; font-size: 12px; font-weight: 750; }
            .app-shell__page :where(button, input[type="submit"]) {
                min-height: 38px;
                padding: 8px 13px;
                border: 1px solid transparent;
                border-radius: 9px;
                color: #fff;
                background: var(--app-accent);
                font-size: 12px;
                font-weight: 800;
            }
            .app-shell__page :where(button, input[type="submit"]):hover { background: var(--app-accent-strong); }
            .app-shell__page :where(ul) { padding-left: 20px; }
            .app-shell__page > ul,
            .app-shell__page > main:not(.command-center):not(.control-room) > ul { display: grid; gap: 10px; }
            .app-shell__page > ul > li,
            .app-shell__page > main:not(.command-center):not(.control-room) > ul > li,
            .app-shell__page > main:not(.command-center):not(.control-room) > article { padding: 14px 16px; border: 1px solid var(--app-line); border-radius: 12px; background: #fff; }
            .app-shell__page :where(table) { width: 100%; border-collapse: collapse; background: #fff; }
            .app-shell__page :where(th, td) { padding: 11px 10px; border-bottom: 1px solid #edf0f4; text-align: left; vertical-align: top; font-size: 12px; }
            .app-shell__page :where(th) { color: var(--app-muted); font-size: 10px; letter-spacing: .08em; text-transform: uppercase; }
            .app-shell__page :where(tr:last-child td) { border-bottom: 0; }
            .app-shell__page :where(fieldset) { padding: 16px; border: 1px solid var(--app-line); border-radius: 12px; }
            .app-shell__page :where(legend) { padding: 0 6px; color: var(--app-accent-strong); font-weight: 800; }
            .app-shell__page :where(.error, [role="alert"]) { color: var(--app-danger); }

            @media (max-width: 900px) {
                .app-shell { display: block; }
                .app-shell__sidebar { position: static; min-height: auto; padding: 12px; }
                .app-shell__brand { padding-bottom: 12px; border-bottom: 0; }
                .app-shell__nav { display: flex; gap: 12px; overflow-x: auto; padding-top: 4px; }
                .app-shell__nav-group { display: flex; flex: 0 0 auto; align-items: center; gap: 3px; }
                .app-shell__nav-label { display: none; }
                .app-shell__nav-link { flex: 0 0 auto; min-height: 36px; white-space: nowrap; }
                .app-shell__nav-note, .app-shell__sidebar-footer { display: none; }
                .app-shell__topbar { padding: 14px 18px; }
                .app-shell__page { padding: 24px 18px 48px; }
            }
            @media (max-width: 560px) {
                .app-shell__topbar { align-items: flex-start; }
                .app-shell__identity-copy { display: none; }
                .app-shell__logout button { padding: 7px 9px; }
                .app-shell__page > main:not(.command-center):not(.control-room) > form,
                .app-shell__page > form,
                .app-shell__page > section,
                .app-shell__page > article { padding: 15px; }
                .app-shell__page :where(label) { display: flex; margin-right: 0; }
                .app-shell__page :where(input:not([type="hidden"]), select, textarea, button, input[type="submit"]) { width: 100%; }
                .app-shell__page :where(table) { display: block; overflow-x: auto; min-width: 560px; }
            }
        </style>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <livewire:styles />
    </head>
    <body>
        @auth
            @php
                $authenticatedUser = auth()->user();
                $isThcUser = $authenticatedUser->mitra_id === null;
                $canViewDashboard = $isThcUser && $authenticatedUser->hasIzin('read_dashboard');
                $canViewProjects = $authenticatedUser->hasIzin('read_project');
                $canViewMitraUsers = $isThcUser && ($authenticatedUser->hasIzin('manage_users') || $authenticatedUser->hasIzin('manage_mitras'));
                $canViewMasterData = $authenticatedUser->hasIzin('read_master_data');
                $canViewWarehouse = ($isThcUser && $authenticatedUser->hasIzin('manage_warehouses')) || $authenticatedUser->hasIzin('operate_warehouse');
                $canViewMaterialRequest = $authenticatedUser->hasIzin('read_material_request');
                $canViewMaterialUsage = $authenticatedUser->hasIzin('read_material_usage');
                $canViewMaterialRekon = $authenticatedUser->hasIzin('read_material_rekon');
                $homeUrl = $canViewDashboard ? route('dashboard') : ($canViewProjects ? route('projects.index') : route('login'));
                $activeContext = match (true) {
                    request()->routeIs('dashboard') => 'Command Center',
                    request()->routeIs('projects.*', 'project-rekons.*') => 'Project',
                    request()->routeIs('admin.users', 'admin.mitras') => 'Mitra & User',
                    request()->routeIs('admin.materials', 'admin.master.*') => 'Material & Unit',
                    request()->routeIs('admin.warehouses', 'warehouse.*') => 'Warehouse',
                    request()->routeIs('material-requests.*') => 'Request Material',
                    request()->routeIs('material-usages.*') => 'Pemakaian Material',
                    default => 'Ruang kerja',
                };
                $roleLabel = $isThcUser ? 'User THC' : 'User Mitra';
                $scopeLabel = $isThcUser ? 'Cakupan lintas Mitra sesuai Izin Aksi' : 'Cakupan data milik Mitra';
                $avatarLabel = collect(preg_split('/\s+/', trim($authenticatedUser->name)))->filter()->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))->take(2)->implode('');
            @endphp
            <a class="app-shell__skip-link" href="#main-content">Lewati ke konten</a>
            <div class="app-shell">
                <aside class="app-shell__sidebar" aria-label="Navigasi utama">
                    <a class="app-shell__brand" href="{{ $homeUrl }}">
                        <span class="app-shell__brand-mark">P</span>
                        <span class="app-shell__brand-copy">
                            <strong>PMS · THC</strong>
                            <small>Ruang Kendali</small>
                        </span>
                    </a>

                    <nav class="app-shell__nav">
                        @if ($canViewDashboard || $canViewProjects)
                            <div class="app-shell__nav-group">
                                <span class="app-shell__nav-label">Pusat kerja</span>
                                @if ($canViewDashboard)
                                    <a @class(['app-shell__nav-link', 'is-active' => request()->routeIs('dashboard')]) href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>
                                        <span class="app-shell__nav-link-copy"><span class="app-shell__nav-link-mark">⌂</span>Command Center</span>
                                    </a>
                                @endif
                                @if ($canViewProjects)
                                    <a @class(['app-shell__nav-link', 'is-active' => request()->routeIs('projects.*', 'project-rekons.*')]) href="{{ route('projects.index') }}" @if (request()->routeIs('projects.*', 'project-rekons.*')) aria-current="page" @endif>
                                        <span class="app-shell__nav-link-copy"><span class="app-shell__nav-link-mark">PRJ</span>Project</span>
                                    </a>
                                    @if ($canViewMaterialRekon)
                                        <span class="app-shell__nav-note">Rekon Material tersedia dari detail Project</span>
                                    @endif
                                @endif
                            </div>
                        @endif

                        @if ($canViewMitraUsers)
                            <div class="app-shell__nav-group">
                                <span class="app-shell__nav-label">Mitra &amp; User</span>
                                @if ($authenticatedUser->hasIzin('manage_mitras'))
                                    <a @class(['app-shell__nav-link', 'is-active' => request()->routeIs('admin.mitras')]) href="{{ route('admin.mitras') }}" @if (request()->routeIs('admin.mitras')) aria-current="page" @endif>
                                        <span class="app-shell__nav-link-copy"><span class="app-shell__nav-link-mark">MIT</span>Mitra</span>
                                    </a>
                                @endif
                                @if ($authenticatedUser->hasIzin('manage_users'))
                                    <a @class(['app-shell__nav-link', 'is-active' => request()->routeIs('admin.users')]) href="{{ route('admin.users') }}" @if (request()->routeIs('admin.users')) aria-current="page" @endif>
                                        <span class="app-shell__nav-link-copy"><span class="app-shell__nav-link-mark">USR</span>User</span>
                                    </a>
                                @endif
                            </div>
                        @endif

                        @if ($canViewMasterData)
                            <div class="app-shell__nav-group">
                                <span class="app-shell__nav-label">Material &amp; Unit</span>
                                <a @class(['app-shell__nav-link', 'is-active' => request()->routeIs('admin.materials')]) href="{{ route('admin.materials') }}" @if (request()->routeIs('admin.materials')) aria-current="page" @endif>
                                    <span class="app-shell__nav-link-copy"><span class="app-shell__nav-link-mark">MAT</span>Material</span>
                                </a>
                                <a @class(['app-shell__nav-link', 'is-active' => request()->routeIs('admin.master.*')]) href="{{ route('admin.master.index', ['entity' => 'units']) }}" @if (request()->routeIs('admin.master.*')) aria-current="page" @endif>
                                    <span class="app-shell__nav-link-copy"><span class="app-shell__nav-link-mark">UNT</span>Unit &amp; master data</span>
                                </a>
                            </div>
                        @endif

                        @if ($canViewWarehouse)
                            <div class="app-shell__nav-group">
                                <span class="app-shell__nav-label">Warehouse</span>
                                @if ($isThcUser && $authenticatedUser->hasIzin('manage_warehouses'))
                                    <a @class(['app-shell__nav-link', 'is-active' => request()->routeIs('admin.warehouses')]) href="{{ route('admin.warehouses') }}" @if (request()->routeIs('admin.warehouses')) aria-current="page" @endif>
                                        <span class="app-shell__nav-link-copy"><span class="app-shell__nav-link-mark">WH</span>Penugasan Warehouse</span>
                                    </a>
                                @endif
                                @if ($authenticatedUser->hasIzin('operate_warehouse'))
                                    <a @class(['app-shell__nav-link', 'is-active' => request()->routeIs('warehouse.*')]) href="{{ route('warehouse.transit') }}" @if (request()->routeIs('warehouse.*')) aria-current="page" @endif>
                                        <span class="app-shell__nav-link-copy"><span class="app-shell__nav-link-mark">↔</span>Surat Jalan &amp; Transit</span>
                                    </a>
                                @endif
                            </div>
                        @endif

                        @if ($canViewMaterialRequest || $canViewMaterialUsage)
                            <div class="app-shell__nav-group">
                                <span class="app-shell__nav-label">Alur Material</span>
                                @if ($canViewMaterialRequest)
                                    <a @class(['app-shell__nav-link', 'is-active' => request()->routeIs('material-requests.*')]) href="{{ route('material-requests.index') }}" @if (request()->routeIs('material-requests.*')) aria-current="page" @endif>
                                        <span class="app-shell__nav-link-copy"><span class="app-shell__nav-link-mark">REQ</span>Request Material</span>
                                    </a>
                                @endif
                                @if ($canViewMaterialUsage)
                                    <a @class(['app-shell__nav-link', 'is-active' => request()->routeIs('material-usages.*')]) href="{{ route('material-usages.index') }}" @if (request()->routeIs('material-usages.*')) aria-current="page" @endif>
                                        <span class="app-shell__nav-link-copy"><span class="app-shell__nav-link-mark">USE</span>Pemakaian Material</span>
                                    </a>
                                @endif
                            </div>
                        @endif
                    </nav>

                    <div class="app-shell__sidebar-footer">
                        <strong>{{ $roleLabel }}</strong>
                        <span>{{ $scopeLabel }}</span>
                    </div>
                </aside>

                <div class="app-shell__body">
                    <header class="app-shell__topbar">
                        <div class="app-shell__context">
                            <small>Workspace {{ $roleLabel }}</small>
                            <strong>{{ $activeContext }}</strong>
                        </div>
                        <div class="app-shell__identity">
                            <div class="app-shell__identity-copy">
                                <strong>{{ $authenticatedUser->name }}</strong>
                                <span>{{ $scopeLabel }}</span>
                            </div>
                            <span class="app-shell__avatar" aria-hidden="true">{{ $avatarLabel }}</span>
                            <div class="app-shell__logout">
                                <button
                                    type="button"
                                    data-logout
                                    data-logout-action="{{ route('logout') }}"
                                    data-logout-token="{{ csrf_token() }}"
                                >Keluar</button>
                            </div>
                        </div>
                    </header>
                    <div class="app-shell__page" id="main-content">{{ $slot }}</div>
                </div>
            </div>
        @else
            <div id="main-content">{{ $slot }}</div>
        @endauth

        <livewire:scripts />
        <script>
            document.querySelector('[data-logout]')?.addEventListener('click', function () {
                const form = document.createElement('form');
                form.action = this.dataset.logoutAction;
                form.method = 'POST';
                form.hidden = true;

                const token = document.createElement('input');
                token.type = 'hidden';
                token.name = '_token';
                token.value = this.dataset.logoutToken;
                form.appendChild(token);

                document.body.appendChild(form);
                form.submit();
            });
        </script>
    </body>
</html>
