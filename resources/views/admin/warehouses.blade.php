<x-layouts.app>
    <h1>Penugasan Warehouse</h1>
    @if (session('status')) <p>{{ session('status') }}</p> @endif
    <form method="POST" action="{{ route('admin.warehouses.create') }}">
        @csrf <input name="kode" placeholder="Kode" required><input name="nama" placeholder="Nama" required>
        <select name="mitra_id"><option value="">THC</option>@foreach($mitras as $mitra)<option value="{{ $mitra->id }}">{{ $mitra->nama }}</option>@endforeach</select>
        <button>Simpan Warehouse</button>
    </form>
    @foreach($warehouses as $warehouse)
        <section id="warehouse-{{ $warehouse->id }}"><h2>{{ $warehouse->nama }} ({{ $warehouse->kode }})</h2>
            <form method="POST" action="{{ route('admin.warehouses.update', $warehouse) }}">
                @csrf @method('PATCH')
                <input name="kode" value="{{ $warehouse->kode }}" required><input name="nama" value="{{ $warehouse->nama }}" required>
                <select name="mitra_id"><option value="">THC</option>@foreach($mitras as $mitra)<option value="{{ $mitra->id }}" @selected($warehouse->mitra_id === $mitra->id)>{{ $mitra->nama }}</option>@endforeach</select>
                <button>Simpan perubahan</button>
            </form>
            @if($warehouse->aktif)
                <form method="POST" action="{{ route('admin.warehouses.deactivate', $warehouse) }}">@csrf @method('PATCH')<button>Nonaktifkan</button></form>
            @else (nonaktif) @endif
            @if($warehouse->aktif)
                <form method="POST" action="{{ route('admin.warehouses.assign', $warehouse) }}">@csrf
                    <select name="user_id">@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select>
                    <button>Tugaskan</button>
                </form>
            @endif
            <ul>@foreach($warehouse->users as $user)<li>{{ $user->name }} <form method="POST" action="{{ route('admin.warehouses.unassign', [$warehouse, $user]) }}" style="display:inline">@csrf @method('DELETE')<button>Hapus</button></form></li>@endforeach</ul>
        </section>
    @endforeach
</x-layouts.app>
