<x-layouts.app>
    <main class="ui-page">
    <header class="ui-page__header"><div><p class="ui-page__eyebrow">Master data</p><h1>Material</h1><p class="ui-page__subtitle">Material selalu memakai Unit/Satuan sebagai master wajib. Hanya Unit aktif yang dapat dipakai untuk Material baru dan dropdown operasional.</p></div><div class="ui-page__actions"><a class="ui-button ui-button--muted" href="{{ route('admin.master.index', 'units') }}">Kelola Unit/Satuan</a>@if(auth()->user()->hasIzin('operate_warehouse'))<a class="ui-button ui-button--muted" href="{{ route('warehouse.index') }}">Buka Operasional</a>@endif</div></header>
    <x-form-errors />
    @if (session('status')) <div class="ui-state ui-state--success" role="status">{{ session('status') }}</div> @endif
    @if (auth()->user()->mitra_id === null && auth()->user()->hasIzin('manage_materials'))
        <section class="ui-panel"><h2>Tambah Material</h2><form class="ui-form" method="POST" action="{{ route('admin.materials.create') }}" data-submit-loading>
            @csrf
            <div class="ui-form__grid"><label>Kode<input name="kode" value="{{ old('kode') }}" required></label><label>Nama<input name="nama" value="{{ old('nama') }}" required></label></div>
            @if ($units->isEmpty())
                <div class="ui-state" role="status"><strong>Belum ada Unit/Satuan aktif.</strong><br>Unit/Satuan aktif wajib tersedia sebelum Material dapat dibuat. <a href="{{ route('admin.master.index', 'units') }}">Buat Unit/Satuan</a>.</div>
            @else
                <div class="ui-form__grid"><label>Unit/Satuan<select name="unit_id" required><option value="">Pilih Unit/Satuan</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected(old('unit_id') == $unit->id)>{{ $unit->kode }} — {{ $unit->nama }}</option>@endforeach</select></label><label>Jenis<select name="jenis"><option value="biasa">Biasa</option><option value="ber_sn">Ber-SN</option><option value="drum_kabel">Drum kabel</option></select></label></div>
                <label>Ambang minimum<input name="ambang_minimum" type="number" min="0" step="0.001" value="{{ old('ambang_minimum') }}" placeholder="Kosong = belum dikonfigurasi"></label><button class="ui-button" type="submit">Simpan Material</button>
            @endif
        </form></section>
    @endif
    <section class="ui-panel"><h2>Daftar Material</h2>@if($materials->isEmpty())<div class="ui-state">Belum ada Material. Tambahkan Material setelah Unit/Satuan aktif tersedia.</div>@else<ul class="ui-list">
        @foreach($materials as $material)
            <li class="ui-list__item" id="material-{{ $material->id }}">
                @if (auth()->user()->mitra_id === null && auth()->user()->hasIzin('manage_materials'))
                    <form class="ui-form" method="POST" action="{{ route('admin.materials.update', $material) }}" data-submit-loading>
                        @csrf @method('PATCH')
                        <div class="ui-form__grid"><label>Kode<input name="kode" value="{{ $material->kode }}" required></label><label>Nama<input name="nama" value="{{ $material->nama }}" required></label></div>
                        @if(!$material->unit->aktif)<span>Unit saat ini {{ $material->unit->nama }} (nonaktif); pilih pengganti aktif.</span>@endif
                        <div class="ui-form__grid"><label>Unit/Satuan<select name="unit_id" required>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected($material->unit_id === $unit->id)>{{ $unit->kode }} — {{ $unit->nama }}</option>@endforeach</select></label><label>Jenis<select name="jenis"><option value="biasa" @selected($material->jenis === 'biasa')>Biasa</option><option value="ber_sn" @selected($material->jenis === 'ber_sn')>Ber-SN</option><option value="drum_kabel" @selected($material->jenis === 'drum_kabel')>Drum kabel</option></select></label></div>
                        <label>Ambang minimum<input name="ambang_minimum" type="number" min="0" step="0.001" value="{{ $material->ambang_minimum }}" placeholder="Kosong = belum dikonfigurasi"></label><button class="ui-button" type="submit">Simpan perubahan</button>
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
    </ul>@endif</section>
    </main>
</x-layouts.app>
