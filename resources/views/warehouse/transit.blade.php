<x-layouts.app>
<main class="ui-page">
    <header class="ui-page__header"><div><p class="ui-page__eyebrow">Warehouse</p><h1>Material dalam Transit</h1><p class="ui-page__subtitle">Material di bawah masih berada dalam perjalanan dan belum dihitung sebagai stok Warehouse tujuan.</p></div><div class="ui-page__actions"><a class="ui-button" href="{{ route('warehouse.index') }}">Operasional Material</a><a class="ui-button ui-button--muted" href="{{ route('warehouse.transfers.index') }}">Daftar Surat Jalan</a></div></header>
    <x-form-errors />
    @if(session('status'))<div class="ui-state ui-state--success" role="status">{{ session('status') }}</div>@endif
    <div class="ui-state ui-state--loading" role="status" aria-live="polite" data-transit-loading hidden>Memuat data Transit…</div>
    <section class="ui-panel ui-panel--wide"><div class="ui-table-wrap"><table class="ui-table">
        <thead><tr><th>Surat Jalan</th><th>Material</th><th>Warehouse asal</th><th>Qty Transit</th><th>Aksi</th></tr></thead>
        <tbody>
        @forelse($stocks as $stock)
            <tr>
                <td><a href="{{ route('warehouse.transfers.show', $stock->lokasi_id) }}">SJ #{{ $stock->lokasi_id }}</a></td>
                <td>{{ $stock->material->kode }} — {{ $stock->material->nama }}</td>
                <td>{{ $stock->warehouse->kode }} — {{ $stock->warehouse->nama }}</td>
                <td>{{ $stock->qty }} {{ $stock->material->unit?->nama }}</td>
                <td><a href="{{ route('warehouse.transfers.print', $stock->lokasi_id) }}">Cetak</a></td>
            </tr>
        @empty
            <tr><td colspan="5"><div class="ui-state" role="status">Tidak ada Material dalam Transit.</div></td></tr>
        @endforelse
        </tbody>
    </table></div></section>
</main>
</x-layouts.app>
