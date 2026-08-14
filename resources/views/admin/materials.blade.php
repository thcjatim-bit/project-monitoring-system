<x-layouts.app>
    <h1>Material</h1>
    @if (session('status')) <p>{{ session('status') }}</p> @endif
    <form method="POST" action="{{ route('admin.materials.create') }}">
        @csrf
        <input name="kode" placeholder="Kode" required>
        <input name="nama" placeholder="Nama" required>
        <select name="unit_id" required>@foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->nama }}</option>@endforeach</select>
        <select name="jenis"><option value="biasa">Biasa</option><option value="ber_sn">Ber-SN</option><option value="drum_kabel">Drum kabel</option></select>
        <button>Simpan</button>
    </form>
    <ul>
        @foreach($materials as $material)
            <li>
                <form method="POST" action="{{ route('admin.materials.update', $material) }}">
                    @csrf @method('PATCH')
                    <input name="kode" value="{{ $material->kode }}" required>
                    <input name="nama" value="{{ $material->nama }}" required>
                    <select name="unit_id" required>
                        @if(!$material->unit->aktif)<option value="{{ $material->unit->id }}" selected>{{ $material->unit->nama }} (nonaktif)</option>@endif
                        @foreach($units as $unit)<option value="{{ $unit->id }}" @selected($material->unit_id === $unit->id)>{{ $unit->nama }}</option>@endforeach
                    </select>
                    <select name="jenis"><option value="biasa" @selected($material->jenis === 'biasa')>Biasa</option><option value="ber_sn" @selected($material->jenis === 'ber_sn')>Ber-SN</option><option value="drum_kabel" @selected($material->jenis === 'drum_kabel')>Drum kabel</option></select>
                    <button>Simpan perubahan</button>
                </form>
                @if($material->aktif)
                    <form method="POST" action="{{ route('admin.materials.deactivate', $material) }}" style="display:inline">@csrf @method('PATCH')<button>Nonaktifkan</button></form>
                @else (nonaktif) @endif
            </li>
        @endforeach
    </ul>
</x-layouts.app>
