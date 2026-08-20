<x-layouts.app>
    <x-ui.page>
        <x-ui.page-header eyebrow="Project" title="Project" subtitle="Daftar Project dalam cakupan akses Anda.">
            <x-slot:actions>
                @if ($user->hasIzin('create_project'))<a class="ui-button" href="{{ route('projects.create') }}">Tambah Project</a>@endif
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('status')) <div class="ui-state ui-state--success" role="status">{{ session('status') }}</div> @endif
        @if ($projects->isEmpty())
            <x-ui.empty-state title="Tidak ada Project." />
        @else
            <x-ui.panel>
                <div class="ui-section-head"><div><h2>Daftar Project</h2><p class="ui-help">Buka Project untuk melihat Control Room dan Linimasa Gabungan.</p></div><x-ui.badge tone="neutral" label="{{ $projects->count() }} Project" /></div>
                <x-ui.search target="#project-records [data-ui-searchable]" label="Cari Project" placeholder="Cari ID Project atau nama" />
                <div class="ui-table-wrap"><table class="ui-table" id="project-records"><thead><tr><th>ID Project</th><th>Nama</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
                    @foreach ($projects as $project)
                        <tr data-ui-searchable data-search-text="{{ $project->id_project }} {{ $project->nama }} {{ $project->status_project }}"><td><a href="{{ route('projects.show', $project) }}"><strong>{{ $project->id_project }}</strong></a></td><td>{{ $project->nama }}</td><td><x-ui.badge :tone="$project->status_project === 'selesai' ? 'done' : 'neutral'" :label="$project->status_project === 'selesai' ? 'Selesai' : 'Aktif'" /></td><td><div class="ui-form__actions"><a class="ui-button ui-button--muted" href="{{ route('projects.show', $project) }}">Buka</a>@if ($user->hasIzin('update_project'))<form method="POST" action="{{ route('projects.update', $project) }}" data-submit-loading>@csrf @method('PATCH')<input name="nama" value="{{ $project->nama }}" required><button class="ui-button ui-button--muted" type="submit">Simpan nama</button></form>@endif @if ($user->hasIzin('delete_project'))<form method="POST" action="{{ route('projects.destroy', $project) }}" data-submit-loading>@csrf @method('DELETE')<button class="ui-button ui-button--danger" type="submit">Hapus</button></form>@endif</div></td></tr>
                    @endforeach
                </tbody></table></div>
            </x-ui.panel>
        @endif
    </x-ui.page>
</x-layouts.app>
