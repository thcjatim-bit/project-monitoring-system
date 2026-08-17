<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Project Monitoring System</title>
        <style>
            :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #172033; background: #f5f7f9; }
            *, *::before, *::after { box-sizing: border-box; }
            body { margin: 0; min-width: 320px; }
            a { color: #0f6f79; }
            .app-shell__bar { align-items: center; background: #fff; border-bottom: 1px solid #dce4e8; display: flex; gap: 24px; justify-content: space-between; min-height: 64px; padding: 0 24px; }
            .app-shell__brand { color: #15324b; font-size: 1.05rem; font-weight: 800; letter-spacing: -.03em; text-decoration: none; }
            .app-shell__brand span { color: #087f8c; }
            .app-shell__nav { align-items: center; display: flex; flex: 1; flex-wrap: wrap; gap: 8px 16px; }
            .app-shell__nav a { color: #526071; font-size: .88rem; text-decoration: none; }
            .app-shell__nav a:hover, .app-shell__nav a[aria-current="page"] { color: #087f8c; }
            .app-shell__user { color: #687684; font-size: .8rem; white-space: nowrap; }
            .app-shell__content { min-height: calc(100vh - 64px); }
            @media (max-width: 760px) {
                .app-shell__bar { align-items: flex-start; flex-direction: column; gap: 10px; padding: 16px 18px; }
                .app-shell__nav { width: 100%; }
                .app-shell__user { display: none; }
            }
        </style>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <livewire:styles />
    </head>
    <body>
        @auth
            @php
                $authenticatedUser = auth()->user();
                $canViewDashboard = $authenticatedUser->mitra_id === null && $authenticatedUser->hasIzin('read_dashboard');
                $canViewProjects = $authenticatedUser->hasIzin('read_project');
            @endphp
            <header class="app-shell__bar">
                @if ($canViewDashboard)
                    <a class="app-shell__brand" href="{{ route('dashboard') }}">PMS <span>THC</span></a>
                @elseif ($canViewProjects)
                    <a class="app-shell__brand" href="{{ route('projects.index') }}">PMS <span>THC</span></a>
                @else
                    <span class="app-shell__brand">PMS <span>THC</span></span>
                @endif
                <nav class="app-shell__nav" aria-label="Navigasi utama">
                    @if ($canViewDashboard)
                        <a href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>Dashboard</a>
                    @endif
                    @if ($canViewProjects)
                        <a href="{{ route('projects.index') }}" @if (request()->routeIs('projects.*')) aria-current="page" @endif>Project</a>
                    @endif
                    @if (auth()->user()->hasIzin('read_material_request'))
                        <a href="{{ route('material-requests.index') }}">Request Material</a>
                    @endif
                </nav>
                <span class="app-shell__user">{{ auth()->user()->name }}</span>
            </header>
        @endauth
        <div class="app-shell__content">{{ $slot }}</div>

        <livewire:scripts />
    </body>
</html>
