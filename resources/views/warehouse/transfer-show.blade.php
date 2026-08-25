<x-layouts.app>
@php
    $isThc = auth()->user()->mitra_id === null;
    $remaining = fn ($item) => max(0, (float) $item->qty - (float) $item->qty_diterima);
    $returnable = fn ($item) => max(0, (float) $item->qty_diterima - (float) $item->qty_diretur);
    $returnableItems = $suratJalan->items->filter(fn ($item) => $returnable($item) > 0);
@endphp
<main class="ui-page">
    <header class="ui-page__header">
        <div>
            <p class="ui-page__eyebrow">Detail Surat Jalan</p>
            <h1>{{ $suratJalan->nomor }}</h1>
            <p class="ui-page__subtitle">{{ $suratJalan->asal->kode }} — {{ $suratJalan->asal->nama }} → {{ $suratJalan->tujuan->kode }} — {{ $suratJalan->tujuan->nama }}</p>
        </div>
        <div class="ui-page__actions">
            <a class="ui-button ui-button--muted" href="{{ route('warehouse.transfers.index') }}">Kembali</a>
            <a class="ui-button ui-button--muted" href="{{ route('warehouse.transfers.print', $suratJalan) }}" target="_blank" rel="noopener noreferrer">Cetak Surat Jalan</a>
        </div>
    </header>
    <x-form-errors />
    @if(session('status'))<div class="ui-state ui-state--success" role="status">{{ session('status') }}</div>@endif

    <section class="ui-grid">
        <article class="ui-panel">
            <h2>Status dokumen</h2>
            <dl>
                <dt class="ui-muted">Status</dt>
                <dd><span class="ui-badge ui-badge--{{ $suratJalan->status === 'terbit' ? 'pending' : ($suratJalan->status === 'diterima' ? 'done' : 'cancelled') }}">{{ ucfirst($suratJalan->status) }}</span></dd>
                <dt class="ui-muted">Tanggal</dt><dd>{{ $suratJalan->tanggal?->format('d M Y') }}</dd>
                @if($suratJalan->project !== null)
                    <dt class="ui-muted">Project</dt><dd>{{ $suratJalan->project->id_project }} — {{ $suratJalan->project->nama }}</dd>
                @endif
                @if($suratJalan->materialRequest !== null)
                    <dt class="ui-muted">Request Material</dt><dd><a href="{{ route('material-requests.show', $suratJalan->materialRequest) }}">Request Material #{{ $suratJalan->materialRequest->id }}</a></dd>
                @endif
                <dt class="ui-muted">Pengirim</dt><dd>{{ $suratJalan->pengirim }}</dd>
                <dt class="ui-muted">Sopir / Plat</dt><dd>{{ $suratJalan->sopir ?: '—' }} / {{ $suratJalan->plat_nomor ?: '—' }}</dd>
            </dl>
        </article>
        <article class="ui-panel">
            <h2>Catatan kontrol</h2>
            <p class="ui-help">Transit bukan stok Warehouse tujuan. Penerimaan, pembatalan, retur, dan koreksi selalu membuat jejak append-only.</p>
            @if($suratJalan->transit_resolution)
                <div class="ui-state">Penyelesaian Transit: {{ str_replace('_', ' ', $suratJalan->transit_resolution) }}</div>
            @elseif($suratJalan->status === 'terbit')
                <div class="ui-state ui-state--loading">Masih ada material yang berada dalam Transit.</div>
            @endif
        </article>
    </section>

    <section class="ui-panel ui-panel--wide" style="margin-top:18px">
        <h2>Item Material</h2>
        <div class="ui-table-wrap"><table class="ui-table"><thead><tr><th>Material</th><th>Identitas</th><th>Diterbitkan</th><th>Diterima</th><th>Diretur</th><th>Sisa Transit</th><th>Catatan</th></tr></thead><tbody>
        @foreach($suratJalan->items as $item)
            <tr><td>{{ $item->material->kode }} — {{ $item->material->nama }}<div class="ui-muted">{{ $item->material->unit->nama }}</div>
                @if($item->jenis_penyimpangan === 'material_asing')
                    <x-ui.badge tone="warning" label="Material di luar request" />
                @elseif($item->jenis_penyimpangan === 'qty_melebihi')
                    <x-ui.badge tone="warning" label="Qty melebihi sisa" />
                @endif
            </td><td>{{ $item->serialNumber?->serial_number ?? $item->drum?->drum_id ?? '—' }}</td><td>{{ \App\Support\QuantityDisplayFormatter::format($item->qty) }}</td><td>{{ \App\Support\QuantityDisplayFormatter::format($item->qty_diterima) }}</td><td>{{ \App\Support\QuantityDisplayFormatter::format($item->qty_diretur) }}</td><td>{{ \App\Support\QuantityDisplayFormatter::format($remaining($item)) }}</td><td>{{ $item->catatan ?? '-' }}</td></tr>
        @endforeach
        </tbody></table></div>
    </section>

    @if($canReceive && $suratJalan->items->contains(fn ($item) => $remaining($item) > 0))
        <section class="ui-panel" style="margin-top:18px">
            <h2>Terima Surat Jalan</h2><p class="ui-help">Isi qty yang benar-benar diterima. Penerimaan sebagian meninggalkan sisa di Transit.</p>
            <form class="ui-form" method="POST" action="{{ route('warehouse.transfers.receive', $suratJalan) }}" data-submit-loading>@csrf
                @foreach($suratJalan->items as $index => $item)
                    @if($remaining($item) > 0)
                        <label>{{ $item->material->nama }} — qty tersisa {{ \App\Support\QuantityDisplayFormatter::format($remaining($item)) }}<input type="hidden" name="items[{{ $index }}][surat_jalan_item_id]" value="{{ $item->id }}"><input type="number" name="items[{{ $index }}][qty]" min="1" max="{{ $remaining($item) }}" step="1" value="{{ \App\Support\QuantityDisplayFormatter::formatInput($remaining($item)) }}" required></label>
                    @endif
                @endforeach
                <button class="ui-button" type="submit">Terima Material</button>
            </form>
        </section>
    @endif

    @if($isThc && auth()->user()->hasIzin('operate_warehouse') && $canManageAsal && $suratJalan->status === 'terbit')
        <section class="ui-panel" style="margin-top:18px">
            <h2>Kelola Transit</h2><p class="ui-help">Batalkan hanya tersedia jika belum ada penerimaan. Selisih sebagian dapat diselesaikan sebagai hilang atau kembali ke asal.</p>
            @if($suratJalan->items->every(fn ($item) => (float) $item->qty_diterima === 0.0))
                <form method="POST" action="{{ route('warehouse.transfers.cancel', $suratJalan) }}" data-submit-loading>@csrf<button class="ui-button ui-button--danger" type="submit">Batalkan Surat Jalan</button></form>
            @endif
            <form class="ui-form" method="POST" action="{{ route('warehouse.transfers.resolve', $suratJalan) }}" data-submit-loading>@csrf
                <label>Penyelesaian selisih<select name="resolution" required><option value="kembali_ke_asal">Kembalikan ke Warehouse asal</option><option value="hilang_dalam_perjalanan">Catat hilang dalam perjalanan</option></select></label>
                <button class="ui-button ui-button--muted" type="submit">Selesaikan Transit</button>
            </form>
        </section>
    @endif

    @if($isThc && auth()->user()->hasIzin('operate_warehouse') && $canManageTujuan && $suratJalan->status === 'diterima')
        <section class="ui-panel" style="margin-top:18px">
            <h2>Retur Material</h2><p class="ui-help">Retur selalu diterbitkan sebagai Surat Jalan baru arah sebaliknya.</p>
            @if($returnableItems->isEmpty())
                <div class="ui-state" role="status">Semua Material pada Surat Jalan ini sudah diretur.</div>
            @else
            <form class="ui-form" method="POST" action="{{ route('warehouse.transfers.return', $suratJalan) }}" data-submit-loading>@csrf
                <div class="ui-form__grid"><label>Tanggal<input type="date" name="tanggal" value="{{ now()->toDateString() }}" required></label><label>Pengirim<input name="pengirim" required></label></div>
                <div class="ui-form__grid"><label>Sopir<input name="sopir"></label><label>Plat nomor<input name="plat_nomor"></label></div>
                @foreach($suratJalan->items as $index => $item)
                    @if($returnable($item) > 0)
                        <label>{{ $item->material->nama }} — tersedia {{ \App\Support\QuantityDisplayFormatter::format($returnable($item)) }}<input type="hidden" name="items[{{ $index }}][surat_jalan_item_id]" value="{{ $item->id }}"><input type="number" name="items[{{ $index }}][qty]" min="1" max="{{ $returnable($item) }}" step="1" value="{{ \App\Support\QuantityDisplayFormatter::formatInput($returnable($item)) }}" required></label>
                    @endif
                @endforeach
                <button class="ui-button" type="submit">Terbitkan Retur</button>
            </form>
            @endif
        </section>
    @endif

    @if($isThc && auth()->user()->hasIzin('operate_warehouse') && $canManageTujuan)
        <section class="ui-panel ui-panel--wide" style="margin-top:18px">
            <h2>Koreksi Buku Transaksi</h2><p class="ui-help">Koreksi tidak mengubah baris asli. Sistem menambahkan pembalikan dan nilai koreksi dengan alasan.</p>
            @forelse($transactions->where('jenis_transaksi', 'receipt')->whereNull('koreksi_dari_id') as $transaction)
                <div class="ui-list__item"><div class="ui-inline"><strong>{{ $transaction->material->nama }}</strong><span>{{ \App\Support\QuantityDisplayFormatter::format($transaction->qty_delta) }} {{ $transaction->material->unit->nama }}</span><span class="ui-muted">{{ $transaction->warehouse->nama }}</span></div>
                    <form class="ui-form" method="POST" action="{{ route('warehouse.material-transactions.correct', $transaction) }}" data-submit-loading>@csrf
                        <div class="ui-form__grid"><label>Qty koreksi<input type="number" name="qty_delta" step="1" value="{{ \App\Support\QuantityDisplayFormatter::formatInput($transaction->qty_delta) }}" required></label><label>Alasan<input name="reason" maxlength="1000" required></label></div>
                        <button class="ui-button ui-button--muted" type="submit">Simpan koreksi</button>
                    </form>
                </div>
            @empty
                <div class="ui-state">Belum ada transaksi penerimaan yang dapat dikoreksi.</div>
            @endforelse
        </section>
    @endif
</main>
</x-layouts.app>
