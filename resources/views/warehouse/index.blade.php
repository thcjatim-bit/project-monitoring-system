<x-layouts.app>
<main class="ui-page">
    <header class="ui-page__header">
        <div><p class="ui-page__eyebrow">Warehouse</p><h1>Operasional Material</h1><p class="ui-page__subtitle">Catat pergerakan melalui buku transaksi append-only. Saldo Warehouse dibentuk oleh transaksi, bukan diedit langsung.</p></div>
        <div class="ui-page__actions"><a class="ui-button ui-button--muted" href="{{ route('warehouse.transfers.index') }}">Daftar Surat Jalan</a><a class="ui-button ui-button--muted" href="{{ route('warehouse.transit') }}">Lihat Transit</a>@if(auth()->user()->hasIzin('read_master_data'))<a class="ui-button ui-button--muted" href="{{ route('admin.materials') }}">Material</a>@endif</div>
    </header>
    <x-form-errors />
    @if(session('status'))<div class="ui-state ui-state--success" role="status">{{ session('status') }}</div>@endif
    <div class="ui-state ui-state--loading" role="status" aria-live="polite" data-warehouse-loading hidden>Memuat data Warehouse…</div>
    @if($warehouses->isEmpty())
        <div class="ui-state" role="status"><strong>Belum ada Warehouse yang ditugaskan.</strong><br>Operator hanya dapat mencatat transaksi pada Warehouse aktif yang ditugaskan kepadanya.@if(auth()->user()->hasIzin('manage_warehouses')) <a href="{{ route('admin.warehouses') }}">Kelola assignment Warehouse</a>.@endif</div>
    @else
        <section class="ui-grid">
            <article class="ui-panel"><h2>Penerimaan stok</h2><p class="ui-help">Untuk stok biasa isi jumlah. Material ber-SN harus qty 1; drum kabel harus memiliki Drum ID.</p>
                @if($materials->isEmpty())<div class="ui-state">Belum ada Material dengan Unit/Satuan aktif. <a href="{{ route('admin.materials') }}">Kelola Material</a>.</div>@else
                <form class="ui-form" method="POST" action="{{ route('warehouse.stock.receive') }}" data-submit-loading>@csrf
                    <div class="ui-form__grid"><label>Warehouse<select name="warehouse_id" required>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->kode }} — {{ $warehouse->nama }}</option>@endforeach</select></label><label>Material<select name="material_id" data-material-select required><option value="">Pilih Material</option>@foreach($materials as $material)<option value="{{ $material->id }}" data-kind="{{ $material->jenis }}">{{ $material->kode }} — {{ $material->nama }} ({{ $material->unit->nama }})</option>@endforeach</select></label></div>
                    <div class="ui-form__grid"><label>Qty<input type="number" name="qty" min="0.001" step="0.001" required></label><label>Alasan<input name="reason" maxlength="255" required></label></div>
                    <label data-identity="serial_number" hidden>Serial Number<input name="serial_number" maxlength="255"></label><label data-identity="drum_id" hidden>Drum ID<input name="drum_id" maxlength="255"></label>
                    <button class="ui-button" type="submit">Catat penerimaan</button>
                </form>@endif
            </article>
            <article class="ui-panel"><h2>Pengeluaran stok</h2><p class="ui-help">Pengeluaran tidak menghapus transaksi; sistem menambahkan baris delta negatif ke buku transaksi.</p>
                @if($materials->isEmpty())<div class="ui-state">Belum ada Material dengan Unit/Satuan aktif.</div>@else
                <form class="ui-form" method="POST" action="{{ route('warehouse.stock.issue') }}" data-submit-loading>@csrf
                    <div class="ui-form__grid"><label>Warehouse<select name="warehouse_id" required>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->kode }} — {{ $warehouse->nama }}</option>@endforeach</select></label><label>Material<select name="material_id" data-material-select required><option value="">Pilih Material</option>@foreach($materials as $material)<option value="{{ $material->id }}" data-kind="{{ $material->jenis }}">{{ $material->kode }} — {{ $material->nama }} ({{ $material->unit->nama }})</option>@endforeach</select></label></div>
                    <div class="ui-form__grid"><label>Qty<input type="number" name="qty" min="0.001" step="0.001" required></label><label>Alasan<input name="reason" maxlength="255" required></label></div>
                    <label data-identity="serial_number" hidden>Serial Number<input name="serial_number" maxlength="255"></label><label data-identity="drum_id" hidden>Drum ID<input name="drum_id" maxlength="255"></label>
                    <button class="ui-button" type="submit">Catat pengeluaran</button>
                </form>@endif
            </article>
            <article class="ui-panel"><h2>Split drum</h2><p class="ui-help">Split membuat Drum turunan baru dan mencatat pengurangan dari Drum induk. Drum tidak digabung kembali.</p>
                @if($drums->isEmpty())<div class="ui-state">Belum ada Drum tersedia di Warehouse yang ditugaskan.</div>@else
                <form class="ui-form" method="POST" action="{{ route('warehouse.stock.drum-split') }}" data-submit-loading>@csrf
                    <div class="ui-form__grid"><label>Warehouse<select name="warehouse_id" required>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->kode }} — {{ $warehouse->nama }}</option>@endforeach</select></label><label>Drum<select name="drum_id" required>@foreach($drums as $drum)<option value="{{ $drum->drum_id }}">{{ $drum->drum_id }} — sisa {{ \App\Support\QuantityDisplayFormatter::format($drum->sisa) }} {{ $drum->material->unit->nama }}</option>@endforeach</select></label></div>
                    <div class="ui-form__grid"><label>Qty potongan<input type="number" name="qty" min="0.001" step="0.001" required></label><label>Alasan<input name="reason" maxlength="255" required></label></div><button class="ui-button" type="submit">Catat split drum</button>
                </form>@endif
            </article>
            <article class="ui-panel"><h2>Terbitkan Surat Jalan</h2><p class="ui-help">Material yang diterbitkan keluar dari saldo asal dan masuk Transit sampai diterima atau diselesaikan THC.</p>
                @if($materials->isEmpty())<div class="ui-state">Belum ada Material dengan Unit/Satuan aktif.</div>@elseif($destinationWarehouses->isEmpty())<div class="ui-state" role="status">Belum ada Warehouse tujuan aktif yang sesuai dengan tenant Anda.</div>@else
                <form class="ui-form" method="POST" action="{{ route('warehouse.transfers.issue') }}" target="_blank" rel="noopener noreferrer" data-submit-loading data-transfer-form>@csrf
                    <div class="ui-form__grid"><label>Warehouse asal<select name="warehouse_asal_id" required>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->kode }} — {{ $warehouse->nama }}</option>@endforeach</select></label><label>Warehouse tujuan<select name="warehouse_tujuan_id" required>@foreach($destinationWarehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->kode }} — {{ $warehouse->nama }}</option>@endforeach</select></label></div>
                    <div class="ui-form__grid"><label>Tanggal<input type="date" name="tanggal" value="{{ now()->toDateString() }}" required></label><label>Pengirim<input name="pengirim" maxlength="255" required></label></div><div class="ui-form__grid"><label>Sopir<input name="sopir" maxlength="255"></label><label>Plat nomor<input name="plat_nomor" maxlength="255"></label></div>
                    <div class="ui-form"><strong>Item Surat Jalan</strong><div data-transfer-items><div class="ui-list__item" data-transfer-row><div class="ui-form__grid"><label>Material<select name="items[0][material_id]" required><option value="">Pilih Material</option>@foreach($materials as $material)<option value="{{ $material->id }}" data-kind="{{ $material->jenis }}">{{ $material->kode }} — {{ $material->nama }} ({{ $material->unit->nama }})</option>@endforeach</select></label><label>Qty<input type="number" name="items[0][qty]" min="0.001" step="0.001" required></label></div><div class="ui-form__grid"><label data-identity="serial_number" hidden>Serial Number<input name="items[0][serial_number]"></label><label data-identity="drum_id" hidden>Drum ID<input name="items[0][drum_id]"></label></div><button class="ui-button ui-button--muted" type="button" data-remove-item hidden>Hapus item</button></div></div><button class="ui-button ui-button--muted" type="button" data-add-item>Tambah item</button></div>
                    <button class="ui-button" type="submit">Terbitkan Surat Jalan</button>
                </form>@endif
            </article>
        </section>
        <section class="ui-panel ui-panel--wide" style="margin-top:18px"><h2>Pengiriman masuk</h2><p class="ui-help">Surat Jalan terbuka yang menuju Warehouse yang ditugaskan kepada Anda. Buka detail untuk mencatat penerimaan aktual tanpa membuat transaksi ganda.</p>@if($suratJalanMasuk->isEmpty())<div class="ui-state" role="status">Tidak ada pengiriman masuk yang menunggu penerimaan.</div>@else<div class="ui-list">@foreach($suratJalanMasuk as $transfer)<article class="ui-list__item"><div class="ui-inline" style="justify-content:space-between"><div><strong>{{ $transfer->nomor }}</strong><div class="ui-muted">Dari {{ $transfer->origin->kode }} — {{ $transfer->origin->nama }} · Ke {{ $transfer->destination->kode }} — {{ $transfer->destination->nama }}</div><div class="ui-muted">{{ $transfer->tanggal?->format('d M Y') }} · {{ $transfer->items->count() }} item · <x-ui.badge tone="warning" label="Menunggu penerimaan" /></div></div><a class="ui-button ui-button--muted" href="{{ route('warehouse.transfers.show', $transfer) }}">Terima Pengiriman</a></div></article>@endforeach</div>@endif</section>
        <section class="ui-panel ui-panel--wide" style="margin-top:18px"><h2>Saldo Warehouse</h2>@if($stocks->isEmpty())<div class="ui-state">Belum ada saldo Material positif pada Warehouse yang ditugaskan.</div>@else<div class="ui-table-wrap"><table class="ui-table"><thead><tr><th>Warehouse</th><th>Material</th><th>Saldo</th><th>Unit</th></tr></thead><tbody>@foreach($stocks as $stock)<tr><td>{{ $stock->warehouse->kode }} — {{ $stock->warehouse->nama }}</td><td>{{ $stock->material->kode }} — {{ $stock->material->nama }}</td><td>{{ \App\Support\QuantityDisplayFormatter::format($stock->qty) }}</td><td>{{ $stock->material->unit->nama }}</td></tr>@endforeach</tbody></table></div>@endif</section>
        <section class="ui-panel ui-panel--wide" style="margin-top:18px"><h2>Drum tersedia</h2>@if($drums->isEmpty())<div class="ui-state">Belum ada Drum tersedia.</div>@else<div class="ui-table-wrap"><table class="ui-table"><thead><tr><th>Drum ID</th><th>Material</th><th>Warehouse</th><th>Sisa</th></tr></thead><tbody>@foreach($drums as $drum)<tr><td>{{ $drum->drum_id }}</td><td>{{ $drum->material->nama }}</td><td>{{ $drum->lokasi_id }}</td><td>{{ \App\Support\QuantityDisplayFormatter::format($drum->sisa) }} {{ $drum->material->unit->nama }}</td></tr>@endforeach</tbody></table></div>@endif</section>
        <section class="ui-panel ui-panel--wide" style="margin-top:18px"><h2>Aktivitas buku transaksi</h2>@if($transactions->isEmpty())<div class="ui-state">Belum ada transaksi Material.</div>@else<div class="ui-table-wrap"><table class="ui-table"><thead><tr><th>Waktu</th><th>Warehouse</th><th>Material</th><th>Delta</th><th>Jenis</th><th>Alasan</th></tr></thead><tbody>@foreach($transactions as $transaction)<tr><td>{{ $transaction->created_at?->format('d M Y H:i') }}</td><td>{{ $transaction->warehouse->kode }}</td><td>{{ $transaction->material->nama }}</td><td>{{ \App\Support\QuantityDisplayFormatter::format($transaction->qty_delta) }} {{ $transaction->material->unit->nama }}</td><td>{{ $transaction->jenis_transaksi }}</td><td>{{ $transaction->reason }}</td></tr>@endforeach</tbody></table></div>@endif</section>
    @endif
</main>
<script>
(() => {
    const identityScope = (select) => select.closest('[data-transfer-row]') ?? select.closest('form');
    const toggleIdentity = (select) => {
        const kind = select.options[select.selectedIndex]?.dataset.kind ?? '';
        identityScope(select)?.querySelectorAll('[data-identity]').forEach((field) => {
            const visible = (field.dataset.identity === 'serial_number' && kind === 'ber_sn') || (field.dataset.identity === 'drum_id' && kind === 'drum_kabel');
            field.hidden = !visible;
            field.querySelectorAll('input').forEach((input) => input.toggleAttribute('required', visible));
        });
    };
    const bindIdentity = (select) => { select.addEventListener('change', () => toggleIdentity(select)); toggleIdentity(select); };
    document.querySelectorAll('[data-material-select], [data-transfer-row] select').forEach(bindIdentity);
    const items = document.querySelector('[data-transfer-items]'); let nextTransferIndex = 1;
    document.querySelector('[data-add-item]')?.addEventListener('click', () => {
        const source = items.querySelector('[data-transfer-row]'); const index = nextTransferIndex++; const row = source.cloneNode(true);
        row.querySelectorAll('[name]').forEach((input) => { input.name = input.name.replace(/items\[0\]/g, `items[${index}]`); input.value = ''; });
        row.querySelector('[data-remove-item]').hidden = false; items.append(row); bindIdentity(row.querySelector('select'));
    });
    items?.addEventListener('click', (event) => { if (event.target.matches('[data-remove-item]')) event.target.closest('[data-transfer-row]').remove(); });
    document.querySelectorAll('[data-submit-loading]').forEach((form) => form.addEventListener('submit', () => { form.querySelectorAll('button[type="submit"]').forEach((button) => { button.disabled = true; button.dataset.originalLabel = button.textContent; button.textContent = 'Ops…'; }); }));
})();
</script>
</x-layouts.app>
