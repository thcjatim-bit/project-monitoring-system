<x-layouts.app>
    <h1>Manajemen Mitra</h1>
    @if (session('status')) <p>{{ session('status') }}</p> @endif
    @if ($errors->any()) <div role="alert"><strong>Periksa isian:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
    <h2>Onboarding Mitra</h2>
    <form method="POST" action="{{ route('admin.mitras.create') }}">
        @csrf
        <input type="hidden" name="form_context" value="create_mitra">
        <label>Kode Mitra <input name="kode" value="{{ old('form_context') === 'create_mitra' ? old('kode') : '' }}" placeholder="Kosongkan untuk MTR-YYMM-NNNN"></label>
        <label>Nama Mitra <input name="nama" value="{{ old('form_context') === 'create_mitra' ? old('nama') : '' }}" placeholder="Nama Mitra" required></label>
        <label>Nama admin-mitra <input name="admin_name" value="{{ old('form_context') === 'create_mitra' ? old('admin_name') : '' }}" placeholder="Nama admin-mitra" required></label>
        <label>Email admin-mitra <input name="admin_email" type="email" value="{{ old('form_context') === 'create_mitra' ? old('admin_email') : '' }}" placeholder="Email admin-mitra" required></label>
        <label>Nomor WhatsApp <input name="no_wa" value="{{ old('form_context') === 'create_mitra' ? old('no_wa') : '' }}" placeholder="628..." required></label>
        <button>Buat Mitra dan Admin</button>
    </form>

    <ul>
        @forelse($mitras as $mitra)
            <li id="mitra-{{ $mitra->id }}">
                <strong>{{ $mitra->nama }}</strong> — {{ $mitra->kode }} — {{ $mitra->aktif ? 'Aktif' : 'Nonaktif' }}
                <div>Dibuat {{ $mitra->created_at->format('d M Y H:i') }}</div>
                <div>Admin-mitra pertama:
                    @if ($mitra->adminMitraPertama)
                        {{ $mitra->adminMitraPertama->name }} · {{ $mitra->adminMitraPertama->email }}
                    @else
                        Belum tersedia
                    @endif
                </div>
                <details><summary>Edit Mitra</summary>
                    <form method="POST" action="{{ route('admin.mitras.update', $mitra) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="form_context" value="mitra_{{ $mitra->id }}">
                        <input name="kode" value="{{ old('form_context') === 'mitra_'.$mitra->id ? old('kode') : $mitra->kode }}" required>
                        <input name="nama" value="{{ old('form_context') === 'mitra_'.$mitra->id ? old('nama') : $mitra->nama }}" required>
                        <button>Simpan Mitra</button>
                    </form>
                </details>
                <form method="POST" action="{{ route('admin.mitras.toggle', $mitra) }}" style="display:inline">@csrf @method('PATCH')<button>{{ $mitra->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                <form method="POST" action="{{ route('admin.mitras.delete', $mitra) }}" style="display:inline">@csrf @method('DELETE')<button>Hapus Mitra</button></form>
            </li>
        @empty
            <li>Belum ada Mitra.</li>
        @endforelse
    </ul>
</x-layouts.app>
