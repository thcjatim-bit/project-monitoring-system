<x-layouts.app>
    <h1>Manajemen User</h1>
    @if (session('status')) <p>{{ session('status') }}</p> @endif
    <h2>Onboarding Mitra</h2>
    <form method="POST" action="{{ route('admin.mitras.create') }}">
        @csrf
        <input name="kode" placeholder="Kode Mitra" required>
        <input name="nama" placeholder="Nama Mitra" required>
        <input name="admin_name" placeholder="Nama admin-mitra" required>
        <input name="admin_email" type="email" placeholder="Email admin-mitra" required>
        <input name="no_wa" placeholder="628..." required>
        <button>Buat Mitra dan Admin</button>
    </form>
    <h2>User</h2>
    <form method="POST" action="{{ route('admin.users.create') }}">
        @csrf
        <input name="name" placeholder="Nama" required>
        <input name="email" type="email" placeholder="Email" required>
        <input name="no_wa" placeholder="628..." required>
        <select name="mitra_id"><option value="">THC</option>@foreach(\App\Models\Mitra::where('aktif', true)->get() as $mitra)<option value="{{ $mitra->id }}">{{ $mitra->nama }}</option>@endforeach</select>
        <select name="grup_id" required>@foreach($grups as $grup)<option value="{{ $grup->id }}">{{ $grup->nama }}</option>@endforeach</select>
        <button>Buat User</button>
    </form>
    <ul>@foreach($users as $user)<li>{{ $user->name }} — {{ $user->email }} — {{ $user->aktif ? 'Aktif' : 'Nonaktif' }}
        <form method="POST" action="{{ route('admin.users.toggle', $user) }}" style="display:inline">@csrf @method('PATCH')<button>{{ $user->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
        <form method="POST" action="{{ route('admin.users.reset', $user) }}" style="display:inline">@csrf<button>Reset kredensial</button></form>
    </li>@endforeach</ul>
</x-layouts.app>
