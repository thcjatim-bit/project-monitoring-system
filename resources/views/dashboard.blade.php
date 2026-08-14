<main>
    <h1>Dashboard</h1>

    <nav aria-label="Menu utama">
        @if ($user->hasIzin('read_project'))
            <a href="{{ route('projects.index') }}">Project</a>
        @endif
        @if ($user->hasIzin('manage_users'))
            <a href="{{ route('admin.users') }}">User</a>
        @endif
        @if ($user->mitra_id === null && $user->hasIzin('manage_warehouses'))
            <a href="{{ route('admin.warehouses') }}">Warehouse</a>
        @endif
        @if ($user->mitra_id === null && $user->hasIzin('manage_materials'))
            <a href="{{ route('admin.materials') }}">Material</a>
        @endif
        @if ($user->mitra_id === null && $user->hasIzin('manage_master_data'))
            <a href="{{ route('admin.master.index', 'units') }}">Unit</a>
            <a href="{{ route('admin.master.index', 'pops') }}">PoP</a>
            <a href="{{ route('admin.master.index', 'pekerjaan-jasa') }}">Pekerjaan Jasa</a>
        @endif
    </nav>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Keluar</button>
    </form>
</main>
