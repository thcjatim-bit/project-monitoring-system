<x-layouts.app>
    <main class="ui-page"><header class="ui-page__header"><div><p class="ui-page__eyebrow">Master data</p><h1>{{ $label }}</h1><p class="ui-page__subtitle">Master data bersama. Nonaktifkan baris yang sudah memiliki histori, jangan hapus referensinya.</p></div>@if($entity === 'units')<a class="ui-button ui-button--muted" href="{{ route('admin.materials') }}">Lihat Material</a>@endif</header>
    <x-form-errors />
    @if (session('status')) <div class="ui-state ui-state--success" role="status">{{ session('status') }}</div> @endif
    @if (auth()->user()->mitra_id === null && auth()->user()->hasIzin('manage_master_data'))
        <section class="ui-panel"><h2>Tambah {{ $label }}</h2><form class="ui-form" method="POST" action="{{ route('admin.master.store', $entity) }}" data-submit-loading>
            @csrf
            <div class="ui-form__grid"><label>Kode<input name="kode" value="{{ old('kode') }}" required></label><label>Nama<input name="nama" value="{{ old('nama') }}" required></label></div><button class="ui-button" type="submit">Simpan</button>
        </form></section>
    @endif
    @if($records->isEmpty())<div class="ui-state" role="status">Belum ada {{ $label }}. Tambahkan data master untuk melanjutkan workflow.</div>@else<section class="ui-panel"><h2>Daftar {{ $label }}</h2><ul class="ui-list">
        @foreach ($records as $record)
            <li class="ui-list__item">
                @if (auth()->user()->mitra_id === null && auth()->user()->hasIzin('manage_master_data'))
                    <form class="ui-form" method="POST" action="{{ route('admin.master.update', [$entity, $record->id]) }}" data-submit-loading>
                        @csrf @method('PATCH')
                        <div class="ui-form__grid"><label>Kode<input name="kode" value="{{ $record->kode }}" required></label><label>Nama<input name="nama" value="{{ $record->nama }}" required></label></div><button class="ui-button" type="submit">Simpan perubahan</button>
                    </form>
                    @if ($record->aktif)
                        <form method="POST" action="{{ route('admin.master.deactivate', [$entity, $record->id]) }}" style="display:inline">
                            @csrf @method('PATCH') <button>Nonaktifkan</button>
                        </form>
                    @else
                        (nonaktif)
                    @endif
                @else
                    {{ $record->kode }} — {{ $record->nama }} @unless($record->aktif)(nonaktif)@endunless
                @endif
            </li>
        @endforeach
    </ul></section>@endif
    </main>
</x-layouts.app>
