<x-layouts.app>
    <x-ui.page>
        <x-ui.page-header eyebrow="Mitra & User" title="{{ auth()->user()->mitra_id !== null ? 'Kelola User Mitra' : 'Manajemen User' }}" subtitle="Kelola User sesuai cakupan tenant dan capability akun.">
            <x-slot:actions><x-ui.badge tone="neutral" :label="auth()->user()->mitra_id !== null ? 'Capability: manage_mitra_users' : 'Capability: manage_users'" /></x-slot:actions>
        </x-ui.page-header>

        @if (session('status')) <div class="ui-state ui-state--success" role="status">{{ session('status') }}</div> @endif
        <x-form-errors />

        <x-ui.panel>
            <h2>Tambah User</h2>
            <form class="ui-form" method="POST" action="{{ route('admin.users.create') }}" data-submit-loading>
                @csrf
                <input type="hidden" name="form_context" value="create_user">
                <div class="ui-form__grid">
                    <label>Nama<input name="name" value="{{ old('form_context') === 'create_user' ? old('name') : '' }}" placeholder="Nama" required></label>
                    <label>Email<input name="email" type="email" value="{{ old('form_context') === 'create_user' ? old('email') : '' }}" placeholder="Email" required></label>
                </div>
                <div class="ui-form__grid">
                    <label>Nomor WhatsApp<input name="no_wa" value="{{ old('form_context') === 'create_user' ? old('no_wa') : '' }}" placeholder="628..." required></label>
                    @if (auth()->user()->mitra_id === null)
                        <label>Mitra<select name="mitra_id"><option value="">THC</option>@foreach ($mitras as $mitra)<option value="{{ $mitra->id }}" @selected(old('form_context') === 'create_user' && (string) old('mitra_id') === (string) $mitra->id)>{{ $mitra->nama }}</option>@endforeach</select></label>
                    @else
                        <input type="hidden" name="mitra_id" value="{{ auth()->user()->mitra_id }}">
                    @endif
                </div>
                <label>Grup<select name="grup_id" required>@foreach ($grups as $grup)<option value="{{ $grup->id }}" @selected(old('form_context') === 'create_user' && (string) old('grup_id') === (string) $grup->id)>{{ $grup->nama }}</option>@endforeach</select></label>
                <button class="ui-button" type="submit">Buat User</button>
            </form>
        </x-ui.panel>

        @if ($users->isEmpty())
            <x-ui.empty-state title="Belum ada User." />
        @else
            <x-ui.panel>
                <div class="ui-section-head"><div><h2>Daftar User</h2><p class="ui-help">Identitas, scope, status, dan action User dalam satu management list.</p></div><x-ui.badge tone="neutral" label="{{ $users->count() }} User" /></div>
                <x-ui.search target="#user-records [data-ui-searchable]" label="Cari User" placeholder="Cari nama atau email" />
                <div class="ui-table-wrap"><table class="ui-table" id="user-records"><thead><tr><th>User</th><th>Scope</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
                    @foreach ($users as $user)
                        <tr id="user-{{ $user->id }}" data-ui-searchable data-search-text="{{ $user->name }} {{ $user->email }} {{ $user->mitra?->nama }} {{ $user->aktif ? 'Aktif' : 'Nonaktif' }}">
                            <td><strong>{{ $user->name }}</strong><div class="ui-muted">{{ $user->email }}</div></td>
                            <td>{{ $user->mitra?->nama ?? 'THC' }}</td>
                            <td><x-ui.badge :tone="$user->aktif ? 'done' : 'neutral'" :label="$user->aktif ? 'Aktif' : 'Nonaktif'" /></td>
                            <td>
                                <div class="ui-form__actions">
                                    <details>
                                        <summary class="ui-button ui-button--muted">Edit User</summary>
                                        <form class="ui-form" method="POST" action="{{ route('admin.users.update', $user) }}" data-submit-loading>
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="form_context" value="user_{{ $user->id }}">
                                            <label>Nama<input name="name" value="{{ old('form_context') === 'user_'.$user->id ? old('name') : $user->name }}" required></label>
                                            <label>Email<input name="email" type="email" value="{{ old('form_context') === 'user_'.$user->id ? old('email') : $user->email }}" required></label>
                                            <label>Nomor WhatsApp<input name="no_wa" value="{{ old('form_context') === 'user_'.$user->id ? old('no_wa') : $user->no_wa }}" required></label>
                                            @if (auth()->user()->mitra_id === null)
                                                <label>Mitra<select name="mitra_id"><option value="">THC</option>@foreach ($editableMitras as $mitra)<option value="{{ $mitra->id }}" @selected((string) (old('form_context') === 'user_'.$user->id ? old('mitra_id') : $user->mitra_id) === (string) $mitra->id)>{{ $mitra->nama }}</option>@endforeach</select></label>
                                            @else
                                                <input type="hidden" name="mitra_id" value="{{ $user->mitra_id }}">
                                            @endif
                                            @if (auth()->user()->mitra_id !== null && $user->hasIzin('manage_mitra_users'))
                                                <input type="hidden" name="grup_id" value="{{ $user->grup_id }}"><span class="ui-help">Role Admin Mitra tidak dapat diubah dari tenant.</span>
                                            @else
                                                <label>Grup<select name="grup_id" required>@foreach ($grups as $grup)<option value="{{ $grup->id }}" @selected((string) (old('form_context') === 'user_'.$user->id ? old('grup_id') : $user->grup_id) === (string) $grup->id)>{{ $grup->nama }}</option>@endforeach</select></label>
                                            @endif
                                            <button class="ui-button" type="submit">Simpan User</button>
                                        </form>
                                    </details>
                                    <form method="POST" action="{{ route('admin.users.toggle', $user) }}" data-submit-loading>@csrf @method('PATCH')<button class="ui-button ui-button--muted" type="submit">{{ $user->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                                    <form method="POST" action="{{ route('admin.users.reset', $user) }}" data-submit-loading>@csrf<button class="ui-button ui-button--muted" type="submit">Reset kredensial</button></form>
                                    @if (auth()->user()->mitra_id === null)
                                        <form method="POST" action="{{ route('admin.users.delete', $user) }}" data-submit-loading>@csrf @method('DELETE')<button class="ui-button ui-button--danger" type="submit">Hapus User</button></form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody></table></div>
            </x-ui.panel>
        @endif
    </x-ui.page>
</x-layouts.app>
