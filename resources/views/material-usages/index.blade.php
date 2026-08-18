<x-layouts.app>
    <main>
        <h1>Pemakaian Material</h1>

        @if (session('status')) <p>{{ session('status') }}</p> @endif

        @if (auth()->user()->mitra_id !== null && auth()->user()->hasIzin('create_material_usage'))
            <h2>Ajukan Pemakaian Material</h2>
            <form method="POST" action="{{ route('projects.material-usages.store', $projects->first()) }}">
                @csrf
                <label>Project
                    <select name="project_id" required>
                        @foreach ($projects as $project)
                            @if ((int) $project->mitra_id === (int) auth()->user()->mitra_id)
                                <option value="{{ $project->id }}">{{ $project->id_project }} — {{ $project->nama }}</option>
                            @endif
                        @endforeach
                    </select>
                </label>
                <p>Pilih Project pada halaman Project Control Room sebelum mengirim pengajuan.</p>
            </form>
        @endif

        <h2>Daftar Pemakaian</h2>
        <ul>
            @forelse ($usages as $usage)
                <li>
                    <strong>#{{ $usage->id }}</strong> — {{ $usage->status }} — {{ $usage->material?->nama }} {{ $usage->qty }}
                    @if ($usage->project) — {{ $usage->project->id_project }} @endif
                    @if (auth()->user()->mitra_id === null && auth()->user()->hasIzin('approve_material_usage') && $usage->status === 'diajukan')
                        <form method="POST" action="{{ route('material-usages.approve', $usage) }}" style="display:inline">
                            @csrf @method('PATCH') <button type="submit">Setujui</button>
                        </form>
                        <form method="POST" action="{{ route('material-usages.reject', $usage) }}" style="display:inline">
                            @csrf @method('PATCH') <button type="submit">Tolak</button>
                        </form>
                    @endif
                </li>
            @empty
                <li>Belum ada Pemakaian Material.</li>
            @endforelse
        </ul>
    </main>
</x-layouts.app>
