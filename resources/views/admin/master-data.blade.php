<x-layouts.app>
    <h1>{{ $label }}</h1>
    @if (session('status')) <p>{{ session('status') }}</p> @endif
    <form method="POST" action="{{ route('admin.master.store', $entity) }}">
        @csrf
        <label>Kode <input name="kode" required></label>
        <label>Nama <input name="nama" required></label>
        <button>Simpan</button>
    </form>
    <ul>
        @foreach ($records as $record)
            <li>
                <form method="POST" action="{{ route('admin.master.update', [$entity, $record->id]) }}">
                    @csrf @method('PATCH')
                    <input name="kode" value="{{ $record->kode }}" required>
                    <input name="nama" value="{{ $record->nama }}" required>
                    <button>Simpan perubahan</button>
                </form>
                @if ($record->aktif)
                    <form method="POST" action="{{ route('admin.master.deactivate', [$entity, $record->id]) }}" style="display:inline">
                        @csrf @method('PATCH') <button>Nonaktifkan</button>
                    </form>
                @else
                    (nonaktif)
                @endif
            </li>
        @endforeach
    </ul>
</x-layouts.app>
