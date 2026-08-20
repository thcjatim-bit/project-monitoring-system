<x-layouts.app>
    <x-ui.page>
        <x-ui.page-header eyebrow="Mitra & User" title="Manajemen Mitra" subtitle="Onboarding membuat entitas Mitra dan Admin Mitra pertama dalam satu alur.">
            <x-slot:actions><x-ui.badge tone="neutral" label="THC only" /></x-slot:actions>
        </x-ui.page-header>

        @if (session('status')) <div class="ui-state ui-state--success" role="status">{{ session('status') }}</div> @endif
        <x-form-errors />

        <x-ui.panel>
            <h2>Onboarding Mitra</h2>
            <form class="ui-form" method="POST" action="{{ route('admin.mitras.create') }}" data-submit-loading>
                @csrf
                <input type="hidden" name="form_context" value="create_mitra">
                <div class="ui-form__grid"><label>Kode Mitra<input name="kode" value="{{ old('form_context') === 'create_mitra' ? old('kode') : '' }}" placeholder="Kosongkan untuk MTR-YYMM-NNNN"></label><label>Nama Mitra<input name="nama" value="{{ old('form_context') === 'create_mitra' ? old('nama') : '' }}" placeholder="Nama Mitra" required></label></div>
                <div class="ui-form__grid"><label>Nama admin-mitra<input name="admin_name" value="{{ old('form_context') === 'create_mitra' ? old('admin_name') : '' }}" placeholder="Nama admin-mitra" required></label><label>Email admin-mitra<input name="admin_email" type="email" value="{{ old('form_context') === 'create_mitra' ? old('admin_email') : '' }}" placeholder="Email admin-mitra" required></label></div>
                <label>Nomor WhatsApp<input name="no_wa" value="{{ old('form_context') === 'create_mitra' ? old('no_wa') : '' }}" placeholder="628..." required></label>
                <button class="ui-button" type="submit">Buat Mitra dan Admin</button>
            </form>
        </x-ui.panel>

        @if($mitras->isEmpty())
            <x-ui.empty-state title="Belum ada Mitra." />
        @else
            <x-ui.panel>
                <div class="ui-section-head"><div><h2>Daftar Mitra</h2><p class="ui-help">Status, kode, dan Admin Mitra pertama untuk setiap tenant.</p></div><x-ui.badge tone="neutral" label="{{ $mitras->count() }} Mitra" /></div>
                <x-ui.search target="#mitra-records [data-ui-searchable]" label="Cari Mitra" placeholder="Cari kode atau nama Mitra" />
                <div class="ui-table-wrap"><table class="ui-table" id="mitra-records"><thead><tr><th>Mitra</th><th>Kode</th><th>Admin Mitra pertama</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
                    @foreach($mitras as $mitra)
                        <tr id="mitra-{{ $mitra->id }}" data-ui-searchable data-search-text="{{ $mitra->nama }} {{ $mitra->kode }} {{ $mitra->adminMitraPertama?->name }} {{ $mitra->aktif ? 'Aktif' : 'Nonaktif' }}">
                            <td><strong>{{ $mitra->nama }}</strong><div class="ui-muted">Dibuat {{ $mitra->created_at->format('d M Y H:i') }}</div></td><td>{{ $mitra->kode }}</td><td>@if ($mitra->adminMitraPertama){{ $mitra->adminMitraPertama->name }}<div class="ui-muted">{{ $mitra->adminMitraPertama->email }}</div>@else<span class="ui-muted">Belum tersedia</span>@endif</td><td><x-ui.badge :tone="$mitra->aktif ? 'done' : 'neutral'" :label="$mitra->aktif ? 'Aktif' : 'Nonaktif'" /></td>
                            <td><div class="ui-form__actions"><details><summary class="ui-button ui-button--muted">Edit</summary><form class="ui-form" method="POST" action="{{ route('admin.mitras.update', $mitra) }}" data-submit-loading>@csrf @method('PATCH')<input type="hidden" name="form_context" value="mitra_{{ $mitra->id }}"><label>Kode<input name="kode" value="{{ old('form_context') === 'mitra_'.$mitra->id ? old('kode') : $mitra->kode }}" required></label><label>Nama<input name="nama" value="{{ old('form_context') === 'mitra_'.$mitra->id ? old('nama') : $mitra->nama }}" required></label><button class="ui-button" type="submit">Simpan Mitra</button></form></details><form method="POST" action="{{ route('admin.mitras.toggle', $mitra) }}" data-submit-loading>@csrf @method('PATCH')<button class="ui-button ui-button--muted" type="submit">{{ $mitra->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button></form><form method="POST" action="{{ route('admin.mitras.delete', $mitra) }}" data-submit-loading>@csrf @method('DELETE')<button class="ui-button ui-button--danger" type="submit">Hapus Mitra</button></form></div></td>
                        </tr>
                    @endforeach
                </tbody></table></div>
            </x-ui.panel>
        @endif
    </x-ui.page>
</x-layouts.app>
