<x-layouts.app>
    <h1>Manajemen User</h1>
    @if (session('status')) <p>{{ session('status') }}</p> @endif
    @if ($errors->any()) <div role="alert"><strong>Periksa isian:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
    <h2>User</h2>
    <form method="POST" action="{{ route('admin.users.create') }}">
        @csrf
        <input type="hidden" name="form_context" value="create_user">
        <input name="name" value="{{ old('form_context') === 'create_user' ? old('name') : '' }}" placeholder="Nama" required>
        <input name="email" type="email" value="{{ old('form_context') === 'create_user' ? old('email') : '' }}" placeholder="Email" required>
        <input name="no_wa" value="{{ old('form_context') === 'create_user' ? old('no_wa') : '' }}" placeholder="628..." required>
        <select name="mitra_id"><option value="">THC</option>@foreach($mitras as $mitra)<option value="{{ $mitra->id }}" @selected(old('form_context') === 'create_user' && (string) old('mitra_id') === (string) $mitra->id)>{{ $mitra->nama }}</option>@endforeach</select>
        <select name="grup_id" required>@foreach($grups as $grup)<option value="{{ $grup->id }}" @selected(old('form_context') === 'create_user' && (string) old('grup_id') === (string) $grup->id)>{{ $grup->nama }}</option>@endforeach</select>
        <button>Buat User</button>
    </form>
    <ul>@foreach($users as $user)<li id="user-{{ $user->id }}">{{ $user->name }} — {{ $user->email }} — {{ $user->aktif ? 'Aktif' : 'Nonaktif' }}
        <details><summary>Edit User</summary>
            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="form_context" value="user_{{ $user->id }}">
                <input name="name" value="{{ old('form_context') === 'user_'.$user->id ? old('name') : $user->name }}" required>
                <input name="email" type="email" value="{{ old('form_context') === 'user_'.$user->id ? old('email') : $user->email }}" required>
                <input name="no_wa" value="{{ old('form_context') === 'user_'.$user->id ? old('no_wa') : $user->no_wa }}" placeholder="628..." required>
                <select name="mitra_id"><option value="">THC</option>@foreach($mitras as $mitra)<option value="{{ $mitra->id }}" @selected((string) (old('form_context') === 'user_'.$user->id ? old('mitra_id') : $user->mitra_id) === (string) $mitra->id)>{{ $mitra->nama }}</option>@endforeach</select>
                <select name="grup_id" required>@foreach($grups as $grup)<option value="{{ $grup->id }}" @selected((string) (old('form_context') === 'user_'.$user->id ? old('grup_id') : $user->grup_id) === (string) $grup->id)>{{ $grup->nama }}</option>@endforeach</select>
                <button>Simpan User</button>
            </form>
        </details>
        <form method="POST" action="{{ route('admin.users.toggle', $user) }}" style="display:inline">@csrf @method('PATCH')<button>{{ $user->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
        <form method="POST" action="{{ route('admin.users.reset', $user) }}" style="display:inline">@csrf<button>Reset kredensial</button></form>
        <form method="POST" action="{{ route('admin.users.delete', $user) }}" style="display:inline">@csrf @method('DELETE')<button>Hapus User</button></form>
    </li>@endforeach</ul>
</x-layouts.app>
