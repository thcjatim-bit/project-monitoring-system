<x-layouts.app>
<main>
    <h1>Project</h1>

    @if ($user->hasIzin('create_project'))
        <a href="{{ route('projects.create') }}">Tambah Project</a>
    @endif

    <ul>
        @forelse ($projects as $project)
            <li>
                <a href="{{ route('projects.show', $project) }}">{{ $project->id_project }} — {{ $project->nama }}</a>
                @if ($user->hasIzin('update_project'))
                    <form method="POST" action="{{ route('projects.update', $project) }}">
                        @csrf
                        @method('PATCH')
                        <label>Nama <input name="nama" value="{{ $project->nama }}" required></label>
                        <button type="submit">Simpan perubahan</button>
                    </form>
                @endif
                @if ($user->hasIzin('delete_project'))
                    <form method="POST" action="{{ route('projects.destroy', $project) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit">Hapus</button>
                    </form>
                @endif
            </li>
        @empty
            <li>Tidak ada Project.</li>
        @endforelse
    </ul>
</main>
</x-layouts.app>
