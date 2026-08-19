<x-layouts.app>
    <main class="ui-page"><header class="ui-page__header"><div><p class="ui-page__eyebrow">Access control</p><h1>Assignment Warehouse</h1><p class="ui-page__subtitle">Operator hanya dapat mencatat transaksi pada Warehouse aktif yang ditugaskan. Menonaktifkan Warehouse tidak menghapus histori stok.</p></div>@if(auth()->user()->hasIzin('operate_warehouse'))<a class="ui-button ui-button--muted" href="{{ route('warehouse.index') }}">Operasional Material</a>@endif</header>
    <x-form-errors />
    @if (session('status')) <div class="ui-state ui-state--success" role="status">{{ session('status') }}</div> @endif
    <section class="ui-panel"><h2>Tambah Warehouse</h2><form class="ui-form" method="POST" action="{{ route('admin.warehouses.create') }}" data-submit-loading>@csrf<div class="ui-form__grid"><label>Kode<input name="kode" value="{{ old('kode') }}" required></label><label>Nama<input name="nama" value="{{ old('nama') }}" required></label></div><label>Pemilik<select name="mitra_id"><option value="">THC</option>@foreach($mitras as $mitra)<option value="{{ $mitra->id }}">{{ $mitra->kode }} — {{ $mitra->nama }}</option>@endforeach</select></label><button class="ui-button" type="submit">Simpan Warehouse</button></form></section>
    @if($warehouses->isEmpty())<div class="ui-state" role="status">Belum ada Warehouse. Buat Warehouse pertama untuk mulai assignment.</div>@else
    @foreach($warehouses as $warehouse)
        <section class="ui-panel" id="warehouse-{{ $warehouse->id }}" style="margin-top:18px"><h2>{{ $warehouse->nama }} <span class="ui-muted">({{ $warehouse->kode }})</span></h2><p class="ui-help">Pemilik: {{ $warehouse->mitra?->nama ?? 'THC' }} · Status: {{ $warehouse->aktif ? 'Aktif' : 'Nonaktif' }}</p>
            <form class="ui-form" method="POST" action="{{ route('admin.warehouses.update', $warehouse) }}" data-submit-loading>
                @csrf @method('PATCH')
                <div class="ui-form__grid"><label>Kode<input name="kode" value="{{ $warehouse->kode }}" required></label><label>Nama<input name="nama" value="{{ $warehouse->nama }}" required></label></div><label>Pemilik<select name="mitra_id"><option value="">THC</option>@foreach($mitras as $mitra)<option value="{{ $mitra->id }}" @selected($warehouse->mitra_id === $mitra->id)>{{ $mitra->nama }}</option>@endforeach</select></label><button class="ui-button" type="submit">Simpan perubahan</button>
            </form>
            @if($warehouse->aktif)
                <div class="ui-form__actions"><form method="POST" action="{{ route('admin.warehouses.deactivate', $warehouse) }}" data-submit-loading>@csrf @method('PATCH')<button class="ui-button ui-button--danger" type="submit">Nonaktifkan</button></form><form class="ui-inline" method="POST" action="{{ route('admin.warehouses.assign', $warehouse) }}" data-submit-loading>@csrf<select name="user_id" required><option value="">Pilih User aktif</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }} · {{ $user->email }}</option>@endforeach</select><button class="ui-button" type="submit">Tugaskan</button></form></div>
            @endif
            @if($warehouse->users->isEmpty())<div class="ui-state">Belum ada User yang ditugaskan.</div>@else<ul class="ui-list">@foreach($warehouse->users as $user)<li class="ui-list__item"><div class="ui-inline"><strong>{{ $user->name }}</strong><span class="ui-muted">{{ $user->email }}</span><form method="POST" action="{{ route('admin.warehouses.unassign', [$warehouse, $user]) }}" data-submit-loading>@csrf @method('DELETE')<button class="ui-button ui-button--muted" type="submit">Hapus assignment</button></form></div></li>@endforeach</ul>@endif
        </section>
    @endforeach
    @endif
    </main>
</x-layouts.app>
