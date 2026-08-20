<x-layouts.app>
    <x-ui.page>
        <x-ui.page-header eyebrow="Master data" :title="$label" subtitle="Master data bersama. Nonaktifkan baris yang sudah memiliki histori, jangan hapus referensinya.">
            <x-slot:actions>
                @if($entity === 'units')<a class="ui-button ui-button--muted" href="{{ route('admin.materials') }}">Lihat Material</a>@endif
            </x-slot:actions>
        </x-ui.page-header>
    <x-form-errors />
    @if (session('status')) <div class="ui-state ui-state--success" role="status">{{ session('status') }}</div> @endif
    @if (auth()->user()->mitra_id === null && auth()->user()->hasIzin('manage_master_data'))
        <x-ui.panel><h2>Tambah {{ $label }}</h2><form class="ui-form" method="POST" action="{{ route('admin.master.store', $entity) }}" data-submit-loading>
            @csrf
            <div class="ui-form__grid"><label>Kode<input name="kode" value="{{ old('kode') }}" required></label><label>Nama<input name="nama" value="{{ old('nama') }}" required></label></div><button class="ui-button" type="submit">Simpan</button>
        </form></x-ui.panel>
    @endif
    @if($records->isEmpty())
        <x-ui.empty-state title="Belum ada {{ $label }}." message="Tambahkan data master untuk melanjutkan workflow." />
    @else
        <x-ui.panel>
            <div class="ui-section-head"><div><h2>Daftar {{ $label }}</h2><p class="ui-help">Kode, nama, dan status {{ $label }} dalam cakupan Anda.</p></div><x-ui.badge tone="neutral" label="{{ auth()->user()->mitra_id === null && auth()->user()->hasIzin('manage_master_data') ? 'Kelola' : 'Read-only' }}" /></div>
            <x-ui.search target="#master-records [data-ui-searchable]" />
            <div class="ui-table-wrap"><table class="ui-table" id="master-records"><thead><tr><th>Kode</th><th>Nama</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
        @foreach ($records as $record)
            <tr data-ui-searchable data-search-text="{{ $record->kode }} {{ $record->nama }} {{ $record->aktif ? 'Aktif' : 'Nonaktif' }}">
                @if (auth()->user()->mitra_id === null && auth()->user()->hasIzin('manage_master_data'))
                    <td colspan="4"><form class="ui-form" method="POST" action="{{ route('admin.master.update', [$entity, $record->id]) }}" data-submit-loading>
                        @csrf @method('PATCH')
                        <div class="ui-form__grid"><label>Kode<input name="kode" value="{{ $record->kode }}" required></label><label>Nama<input name="nama" value="{{ $record->nama }}" required></label></div><button class="ui-button" type="submit">Simpan perubahan</button>
                    </form><div class="ui-form__actions"><x-ui.badge :tone="$record->aktif ? 'done' : 'neutral'" :label="$record->aktif ? 'Aktif' : 'Nonaktif'" /> @if ($record->aktif)<form method="POST" action="{{ route('admin.master.deactivate', [$entity, $record->id]) }}" data-submit-loading>@csrf @method('PATCH') <button class="ui-button ui-button--danger" type="submit">Nonaktifkan</button></form>@endif</div></td>
                @else
                    <td><strong>{{ $record->kode }}</strong></td><td>{{ $record->nama }}</td><td><x-ui.badge :tone="$record->aktif ? 'done' : 'neutral'" :label="$record->aktif ? 'Aktif' : 'Nonaktif'" /></td><td><span class="ui-muted">Read-only</span></td>
                @endif
            </tr>
        @endforeach
            </tbody></table></div>
        </x-ui.panel>
    @endif
    </x-ui.page>
</x-layouts.app>
