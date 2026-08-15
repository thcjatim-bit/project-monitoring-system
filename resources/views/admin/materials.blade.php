<x-layouts.app>
    <h1>Material</h1>
    @if (session('status')) <p>{{ session('status') }}</p> @endif
    @if (auth()->user()->mitra_id === null && auth()->user()->hasIzin('manage_materials'))
        <form method="POST" action="{{ route('admin.materials.create') }}">
            @csrf
            <input name="kode" placeholder="Kode" required>
            <input name="nama" placeholder="Nama" required>
            <select name="unit_id" required>@foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->nama }}</option>@endforeach</select>
            <select name="jenis"><option value="biasa">Biasa</option><option value="ber_sn">Ber-SN</option><option value="drum_kabel">Drum kabel</option></select>
            <label>Ambang minimum <input name="ambang_minimum" type="number" min="0" step="0.001" placeholder="Kosong = belum dikonfigurasi"></label>
            <button>Simpan</button>
        </form>
    @endif
    <ul>
        @foreach($materials as $material)
            <li id="material-{{ $material->id }}">
                @if (auth()->user()->mitra_id === null && auth()->user()->hasIzin('manage_materials'))
                    <form method="POST" action="{{ route('admin.materials.update', $material) }}">
                        @csrf @method('PATCH')
                        <input name="kode" value="{{ $material->kode }}" required>
                        <input name="nama" value="{{ $material->nama }}" required>
                        @if(!$material->unit->aktif)<span>Unit saat ini {{ $material->unit->nama }} (nonaktif); pilih pengganti aktif.</span>@endif
                        <select name="unit_id" required>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected($material->unit_id === $unit->id)>{{ $unit->nama }}</option>@endforeach</select>
                        <select name="jenis"><option value="biasa" @selected($material->jenis === 'biasa')>Biasa</option><option value="ber_sn" @selected($material->jenis === 'ber_sn')>Ber-SN</option><option value="drum_kabel" @selected($material->jenis === 'drum_kabel')>Drum kabel</option></select>
                        <label>Ambang minimum <input name="ambang_minimum" type="number" min="0" step="0.001" value="{{ $material->ambang_minimum }}" placeholder="Kosong = belum dikonfigurasi"></label>
                        <button>Simpan perubahan</button>
                    </form>
                    @if($material->aktif)
                        <form method="POST" action="{{ route('admin.materials.deactivate', $material) }}" style="display:inline">@csrf @method('PATCH')<button>Nonaktifkan</button></form>
                    @else (nonaktif) @endif
                @else
                    {{ $material->kode }} — {{ $material->nama }} @unless($material->aktif)(nonaktif)@endunless
                @endif
                <div id="material-{{ $material->id }}-stock">
                    <strong>Saldo Warehouse</strong>
                    <ul>
                        @forelse($material->stocks->where('lokasi_tipe', 'warehouse')->where('qty', '>', 0) as $stock)
                            <li>{{ $stock->warehouse?->nama ?? 'Warehouse tidak diketahui' }}: {{ number_format((float) $stock->qty, 3, '.', '') }} {{ $material->unit->nama }}</li>
                        @empty
                            <li>Tidak ada saldo Warehouse.</li>
                        @endforelse
                    </ul>
                </div>
            </li>
        @endforeach
    </ul>
</x-layouts.app>
