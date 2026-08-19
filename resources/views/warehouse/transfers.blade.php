<x-layouts.app>
<main class="ui-page">
    <header class="ui-page__header"><div><p class="ui-page__eyebrow">Warehouse</p><h1>Surat Jalan</h1><p class="ui-page__subtitle">Pantau dokumen perpindahan, status Transit, penerimaan sebagian, retur, dan penyelesaian selisih.</p></div><div class="ui-page__actions">@if (! $readOnlyTransit)<a class="ui-button" href="{{ route('warehouse.index') }}">Operasional Material</a><a class="ui-button ui-button--muted" href="{{ route('warehouse.transit') }}">Transit</a>@endif</div></header>
    <x-form-errors />
    @if(session('status'))<div class="ui-state ui-state--success" role="status">{{ session('status') }}</div>@endif
    <div class="ui-state ui-state--loading" role="status" aria-live="polite" data-transfers-loading hidden>Memuat daftar Surat Jalan…</div>
    @if($transfers->isEmpty())
        <div class="ui-state" role="status"><strong>{{ $readOnlyTransit ? 'Belum ada Surat Jalan untuk Mitra Anda.' : 'Belum ada Surat Jalan yang terkait Warehouse tugas Anda.' }}</strong>@if (! $readOnlyTransit)<br>Terbitkan Surat Jalan dari halaman Operasional Material setelah memiliki dua Warehouse aktif yang ditugaskan.@endif</div>
    @else
        <section class="ui-panel"><div class="ui-table-wrap"><table class="ui-table"><thead><tr><th>Nomor</th><th>Tanggal</th><th>Rute</th><th>Item</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
        @foreach($transfers as $transfer)
            <tr><td><a href="{{ route('warehouse.transfers.show', $transfer) }}">{{ $transfer->nomor }}</a>@if($transfer->retur_dari_id)<div class="ui-muted">Retur dari #{{ $transfer->retur_dari_id }}</div>@endif</td><td>{{ $transfer->tanggal?->format('d M Y') }}</td><td>{{ $transfer->origin->kode }} → {{ $transfer->destination->kode }}</td><td>{{ $transfer->items->count() }} item</td><td><span class="ui-badge ui-badge--{{ $transfer->status === 'terbit' ? 'pending' : ($transfer->status === 'diterima' ? 'done' : 'cancelled') }}">{{ ucfirst($transfer->status) }}</span></td><td class="ui-inline"><a href="{{ route('warehouse.transfers.show', $transfer) }}">Buka detail</a><a href="{{ route('warehouse.transfers.print', $transfer) }}">Cetak</a></td></tr>
        @endforeach
        </tbody></table></div></section>
    @endif
</main>
</x-layouts.app>
