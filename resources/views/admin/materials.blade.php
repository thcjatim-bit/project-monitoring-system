<x-layouts.app>
    <x-ui.page>
        <x-ui.page-header eyebrow="Master data" title="Material" subtitle="Material selalu memakai Unit/Satuan sebagai master wajib. Saldo Warehouse dibaca dari buku transaksi dan tidak diedit langsung.">
            <x-slot:actions>
                <a class="ui-button ui-button--muted" href="{{ route('admin.master.index', 'units') }}">Lihat Unit/Satuan</a>
                @if(auth()->user()->hasIzin('operate_warehouse'))<a class="ui-button ui-button--muted" href="{{ route('warehouse.index') }}">Buka Operasional</a>@endif
            </x-slot:actions>
        </x-ui.page-header>

        <x-form-errors />
        @if (session('status')) <div class="ui-state ui-state--success" role="status">{{ session('status') }}</div> @endif

        @if (auth()->user()->mitra_id === null && auth()->user()->hasIzin('manage_materials'))
            <x-ui.panel>
                <h2>Tambah Material</h2>
                <form class="ui-form" method="POST" action="{{ route('admin.materials.create') }}" data-submit-loading>
                    @csrf
                    <div class="ui-form__grid"><label>Kode<input name="kode" value="{{ old('kode') }}" required></label><label>Nama<input name="nama" value="{{ old('nama') }}" required></label></div>
                    @if ($units->isEmpty())
                        <x-ui.empty-state title="Belum ada Unit/Satuan aktif.">
                            Unit/Satuan aktif wajib tersedia sebelum Material dapat dibuat. <a href="{{ route('admin.master.index', 'units') }}">Buat Unit/Satuan</a>.
                        </x-ui.empty-state>
                    @else
                        <div class="ui-form__grid"><label>Unit/Satuan<select name="unit_id" required><option value="">Pilih Unit/Satuan</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected(old('unit_id') == $unit->id)>{{ $unit->kode }} — {{ $unit->nama }}</option>@endforeach</select></label><label>Jenis<select name="jenis"><option value="biasa">Biasa</option><option value="ber_sn">Ber-SN</option><option value="drum_kabel">Drum kabel</option></select></label></div>
                        <label>Ambang minimum<input name="ambang_minimum" type="number" min="0" step="0.001" value="{{ old('ambang_minimum') }}" placeholder="Kosong = belum dikonfigurasi"></label><button class="ui-button" type="submit">Simpan Material</button>
                    @endif
                </form>
            </x-ui.panel>
        @endif

        @if($materials->isEmpty())
            <x-ui.empty-state title="Belum ada Material.">Tambahkan Material setelah Unit/Satuan aktif tersedia.</x-ui.empty-state>
        @else
            <x-ui.panel>
                <div class="ui-section-head"><div><h2>Daftar Material</h2><p class="ui-help">Kode, tracking, Unit/Satuan, status, dan saldo Warehouse yang tersedia.</p></div><x-ui.badge tone="neutral" label="{{ auth()->user()->mitra_id === null && auth()->user()->hasIzin('manage_materials') ? 'Kelola' : 'Read-only' }}" /></div>
                <x-ui.search target="#material-records [data-ui-searchable]" label="Cari Material" placeholder="Cari kode atau nama Material" />
                <div class="ui-table-wrap"><table class="ui-table" id="material-records"><thead><tr><th>Kode</th><th>Nama</th><th>Unit/Satuan</th><th>Tracking</th><th>Status</th><th>Saldo Warehouse</th></tr></thead><tbody>
                    @foreach($materials as $material)
                        <tr id="material-{{ $material->id }}" data-ui-searchable data-search-text="{{ $material->kode }} {{ $material->nama }} {{ $material->unit->nama }} {{ $material->jenis }} {{ $material->aktif ? 'Aktif' : 'Nonaktif' }}">
                            @if (auth()->user()->mitra_id === null && auth()->user()->hasIzin('manage_materials'))
                                <td colspan="6">
                                    <form class="ui-form" method="POST" action="{{ route('admin.materials.update', $material) }}" data-submit-loading>
                                        @csrf @method('PATCH')
                                        <div class="ui-form__grid"><label>Kode<input name="kode" value="{{ $material->kode }}" required></label><label>Nama<input name="nama" value="{{ $material->nama }}" required></label></div>
                                        @if(!$material->unit->aktif)<span class="ui-help">Unit saat ini {{ $material->unit->nama }} (nonaktif); pilih pengganti aktif.</span>@endif
                                        <div class="ui-form__grid"><label>Unit/Satuan<select name="unit_id" required>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected($material->unit_id === $unit->id)>{{ $unit->kode }} — {{ $unit->nama }}</option>@endforeach</select></label><label>Jenis<select name="jenis"><option value="biasa" @selected($material->jenis === 'biasa')>Biasa</option><option value="ber_sn" @selected($material->jenis === 'ber_sn')>Ber-SN</option><option value="drum_kabel" @selected($material->jenis === 'drum_kabel')>Drum kabel</option></select></label></div>
                                        <label>Ambang minimum<input name="ambang_minimum" type="number" min="0" step="0.001" value="{{ $material->ambang_minimum }}" placeholder="Kosong = belum dikonfigurasi"></label><button class="ui-button" type="submit">Simpan perubahan</button>
                                    </form>
                                    @if($material->aktif)<form class="ui-form__actions" method="POST" action="{{ route('admin.materials.deactivate', $material) }}" data-submit-loading>@csrf @method('PATCH')<x-ui.badge tone="done" label="Aktif" /><button class="ui-button ui-button--danger" type="submit">Nonaktifkan</button></form>@else<x-ui.badge tone="neutral" label="Nonaktif" />@endif
                                    <div class="ui-stock-summary"><strong>Saldo Warehouse</strong>@forelse($material->stocks->where('lokasi_tipe', 'warehouse')->where('qty', '>', 0) as $stock)<span>{{ $stock->warehouse?->nama ?? 'Warehouse tidak diketahui' }}: {{ number_format((float) $stock->qty, 3, '.', '') }} {{ $material->unit->nama }}</span>@empty<span class="ui-muted">Tidak ada saldo Warehouse.</span>@endforelse</div>
                                </td>
                            @else
                                <td><strong>{{ $material->kode }}</strong></td><td><strong>{{ $material->nama }}</strong></td><td>{{ $material->unit->nama }}</td><td>{{ ucfirst(str_replace('_', ' ', $material->jenis)) }}</td><td><x-ui.badge :tone="$material->aktif ? 'done' : 'neutral'" :label="$material->aktif ? 'Aktif' : 'Nonaktif'" /><span class="ui-muted"> Read-only</span></td><td><div class="ui-stock-summary">@forelse($material->stocks->where('lokasi_tipe', 'warehouse')->where('qty', '>', 0) as $stock)<span>{{ $stock->warehouse?->nama ?? 'Warehouse tidak diketahui' }}: {{ number_format((float) $stock->qty, 3, '.', '') }} {{ $material->unit->nama }}</span>@empty<span class="ui-muted">Tidak ada saldo Warehouse.</span>@endforelse</div></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody></table></div>
            </x-ui.panel>
        @endif
    </x-ui.page>
</x-layouts.app>
