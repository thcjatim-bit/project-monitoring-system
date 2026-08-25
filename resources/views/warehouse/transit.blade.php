<x-layouts.app>
<main class="ui-page">
    <header class="ui-page__header"><div><p class="ui-page__eyebrow">Warehouse</p><h1>Material dalam Transit</h1><p class="ui-page__subtitle">Material di bawah masih berada dalam perjalanan dan belum dihitung sebagai stok Warehouse tujuan.</p></div><div class="ui-page__actions">@if (! $readOnlyTransit)<a class="ui-button" href="{{ route('warehouse.index') }}">Operasional Material</a>@endif<a class="ui-button ui-button--muted" href="{{ route('warehouse.transfers.index') }}">Daftar Surat Jalan</a></div></header>
    <x-form-errors />
    @if(session('status'))<div class="ui-state ui-state--success" role="status">{{ session('status') }}</div>@endif
    <div class="ui-state ui-state--loading" role="status" aria-live="polite" data-transit-loading hidden>Memuat data Transit…</div>
    <section class="ui-panel ui-panel--wide"><div class="ui-table-wrap"><table class="ui-table">
        <thead><tr><th>Surat Jalan</th><th>Rute</th><th>Material</th><th>Sisa Transit</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        @forelse($stocks as $stock)
            @php($transfer = $stock['suratJalan'])
            <tr>
                <td><a href="{{ route('warehouse.transfers.show', $transfer) }}">{{ $transfer?->nomor }}</a></td>
                <td>{{ $transfer?->asal?->kode }} — {{ $transfer?->asal?->nama }} → {{ $transfer?->tujuan?->kode }} — {{ $transfer?->tujuan?->nama }}</td>
                <td>{{ $stock['material']->kode }} — {{ $stock['material']->nama }}<div class="ui-muted">{{ $stock['material']->unit?->nama }}</div></td>
                <td>{{ \App\Support\QuantityDisplayFormatter::format($stock['qty']) }}</td>
                <td><x-ui.badge :tone="$stock['transit_label'] === 'Sebagian diterima' ? 'warning' : 'info'" :label="$stock['transit_label']" /></td>
                <td class="ui-inline"><a class="ui-button ui-button--muted" href="{{ route('warehouse.transfers.show', $transfer) }}">Detail</a><a class="ui-button ui-button--muted" href="{{ route('warehouse.transfers.print', $transfer) }}" target="_blank" rel="noopener noreferrer">Cetak</a></td>
            </tr>
        @empty
            <tr><td colspan="6"><div class="ui-state" role="status">Tidak ada Material dalam Transit.</div></td></tr>
        @endforelse
        </tbody>
    </table></div></section>
</main>
</x-layouts.app>
