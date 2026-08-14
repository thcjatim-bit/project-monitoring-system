<main>
    <h1>Dashboard</h1>

    <nav aria-label="Menu utama">
        @if ($user->hasIzin('read_project'))
            <a href="{{ route('projects.index') }}">Project</a>
        @endif
    </nav>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Keluar</button>
    </form>
</main>
