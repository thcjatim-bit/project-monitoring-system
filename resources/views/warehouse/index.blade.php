<x-layouts.app>
<main class="ui-page" data-warehouse-page>
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
            <article class="ui-panel"><h2>Penerimaan stok</h2><p class="ui-help">Untuk material biasa isi jumlah unit utuh. Material ber-SN harus qty 1; drum kabel dihitung per meter utuh dan harus memiliki Drum ID.</p>
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
                    <div class="ui-form__grid"><label>Qty potongan<input type="number" name="qty" data-quantity-kind="drum_kabel" min="1" step="1" required></label><label>Alasan<input name="reason" maxlength="255" required></label></div><button class="ui-button" type="submit">Catat split drum</button>
                </form>@endif
            </article>
            @if($canIssueTransfer)
            <article class="ui-panel"><h2>Terbitkan Surat Jalan</h2><p class="ui-help">Material yang diterbitkan keluar dari saldo asal dan masuk Transit sampai diterima atau diselesaikan THC. Drum kabel boleh dikirim sebagian: qty di bawah sisa melahirkan Drum turunan yang berangkat sementara induknya tinggal di gudang asal. Satu Drum hanya boleh muncul sekali per Surat Jalan.</p>
                @if($materials->isEmpty())<div class="ui-state">Belum ada Material dengan Unit/Satuan aktif.</div>@elseif($destinationWarehouses->isEmpty())<div class="ui-state" role="status">Belum ada Warehouse tujuan aktif yang sesuai dengan tenant Anda.</div>@else
                @php
                    // Render awal dikerjakan server dari payload yang sama dengan yang dipakai JS, supaya
                    // isi dropdown pada muat pertama tidak bergantung pada skrip yang belum sempat jalan.
                    // POST yang ditolak kembali ke halaman ini, jadi render awal berangkat dari old():
                    // konteks gudang/request harus cocok dengan baris yang dipulihkan di bawah.
                    // Gudang awal dan Mitra efektif pada muat pertama dirakit SuratJalanFormQuery lalu
                    // dilayani lewat payload. Blade dan skrip halaman sama-sama membacanya dari sana,
                    // jadi render awal server dan keadaan awal skrip tidak bisa berangkat dari nilai berbeda.
                    $initialOriginId = $transferFormData['initial_origin_id'];
                    $initialDestinationId = $transferFormData['initial_destination_id'];
                    $initialMitraId = $transferFormData['initial_mitra_id'];
                    // Baris item hanya ada di klien setelah baris pertama, jadi tanpa render ulang ini
                    // catatan yang sudah diketik operator tidak punya tempat untuk kembali.
                    $oldItems = collect(old('items', [[]]))->map(fn ($item) => is_array($item) ? $item : []);
                    $oldRequestId = old('material_request_id');
                    $initialRequests = $transferFormData['requests'][(string) $initialDestinationId] ?? [];
                    // Hanya Request terminal milik Mitra yang sedang dilayani yang boleh di-reset.
                    // Request ditolak, belum diputuskan, tidak ditemukan, atau milik Mitra lain tetap
                    // membawa asal-usul old() agar tidak diam-diam berubah menjadi kiriman langsung.
                    $resetOldRequest = $oldRequestId !== null && $oldRequestId !== ''
                        && (string) ($transferFormData['terminal_request_id'] ?? '') === (string) $oldRequestId;
                    // Request ber-Project mengunci Projectnya di klien; render ulang harus memulihkan
                    // kunci itu juga, kalau tidak form kembali dalam bentuk yang prefill tidak pernah buat.
                    $initialRequest = collect($initialRequests)->first(fn (array $candidate): bool => (string) $candidate['id'] === (string) old('material_request_id'));
                    // Request yang tidak lagi muncul di payload tetap dipertahankan sebagai pilihan
                    // otoritatif, kecuali terminal milik Mitra efektif yang memang harus di-reset.
                    // Dengan begitu retry yang tidak berubah tetap mengirim ID Request ke server dan
                    // ditolak oleh validasi status/ownership, bukan diam-diam menjadi kiriman langsung.
                    $preservedRequestId = $resetOldRequest || $initialRequest !== null || $oldRequestId === null || $oldRequestId === ''
                        ? null
                        : (string) $oldRequestId;
                    $preservedRequestLabel = $preservedRequestId === null
                        ? null
                        : 'Request Material #'.$preservedRequestId.' — pilihan sebelumnya tidak lagi tersedia; pilih ulang.';
                    $initialLockedProjectId = $initialRequest['project_id'] ?? null;
                    $initialProjects = collect($transferFormData['projects'])->where('mitra_id', $initialMitraId)->all();
                    $projectKosongLabel = '— Tanpa Project —';
                    $projectTerkunciLabel = 'Gudang THC ke gudang THC — tanpa Project';
                @endphp
                <form class="ui-form" method="POST" action="{{ route('warehouse.transfers.issue') }}" target="_blank" rel="noopener noreferrer" data-submit-loading data-transfer-form>@csrf
                    <div class="ui-form__grid"><label>Warehouse asal<select name="warehouse_asal_id" data-origin-select required>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected($initialOriginId === (int) $warehouse->id)>{{ $warehouse->kode }} — {{ $warehouse->nama }}</option>@endforeach</select></label><label>Warehouse tujuan<select name="warehouse_tujuan_id" data-destination-select required>@foreach($destinationWarehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected($initialDestinationId === (int) $warehouse->id)>{{ $warehouse->kode }} — {{ $warehouse->nama }}</option>@endforeach</select></label></div>
                    <div class="ui-form__grid"><label>Request Material<select name="material_request_id" data-request-select data-empty-label="— Tanpa Request Material —"><option value="">— Tanpa Request Material —</option>@foreach($initialRequests as $initialRequest)<option value="{{ $initialRequest['id'] }}" @selected((string) old('material_request_id') === (string) $initialRequest['id'])>{{ $initialRequest['label'] }}</option>@endforeach @if($preservedRequestId !== null)<option value="{{ $preservedRequestId }}" selected>{{ $preservedRequestLabel }}</option>@endif</select></label><label>Project<select name="project_id" data-project-select data-empty-label="{{ $projectKosongLabel }}" data-locked-label="{{ $projectTerkunciLabel }}" @disabled($initialMitraId === null || $initialLockedProjectId !== null)>@if($initialMitraId === null)<option value="">{{ $projectTerkunciLabel }}</option>@else<option value="">{{ $projectKosongLabel }}</option>@foreach($initialProjects as $initialProject)<option value="{{ $initialProject['id'] }}" @selected((string) old('project_id') === (string) $initialProject['id'])>{{ $initialProject['label'] }}</option>@endforeach @endif</select>@if($initialLockedProjectId !== null)<input type="hidden" name="project_id" value="{{ $initialLockedProjectId }}" data-project-lock>@endif</label></div>
                    @if($resetOldRequest)<p class="ui-state ui-state--warning" role="status" data-request-reset-notice>Request Material yang sebelumnya dipilih sudah tidak dapat dipenuhi. Pilihan direset; baris tetap tersedia sebagai kiriman langsung.</p>@elseif($preservedRequestId !== null)<p class="ui-state ui-state--warning" role="status" data-request-preserved-notice>Request Material yang sebelumnya dipilih dipertahankan agar server dapat memvalidasi ulang. Pilih Request Material lain atau Tanpa Request Material untuk menerbitkan kiriman langsung.</p>@endif
                    <div class="ui-form__grid"><label>Tanggal<input type="date" name="tanggal" required value="{{ old('tanggal', now()->toDateString()) }}"></label><label>Pengirim<input name="pengirim" maxlength="255" required value="{{ old('pengirim') }}"></label></div><div class="ui-form__grid"><label>Sopir<input name="sopir" maxlength="255" value="{{ old('sopir') }}"></label><label>Plat nomor<input name="plat_nomor" maxlength="255" value="{{ old('plat_nomor') }}"></label></div>
                    <div class="ui-form"><strong>Item Surat Jalan</strong><div data-transfer-items>@foreach($oldItems as $index => $oldItem)@php
                        $oldMaterial = $materials->firstWhere('id', (int) ($oldItem['material_id'] ?? 0));
                        $oldAsal = $resetOldRequest
                            ? 'manual'
                            : (array_key_exists('asal', $oldItem) ? (string) $oldItem['asal'] : 'manual');
                        $oldTerkunci = $oldAsal === 'request' && $oldMaterial !== null;
                        $identityOptionsFor = function (string $type) use ($transferFormData, $initialOriginId, $oldMaterial): array {
                            if ($oldMaterial === null) {
                                return [];
                            }

                            return collect($transferFormData['identities'][(string) $initialOriginId][(string) $oldMaterial->id] ?? [])
                                ->where('type', $type)
                                ->map(fn (array $identity): array => [
                                    'value' => $identity['value'],
                                    'label' => $identity['type'] === 'drum'
                                        ? $identity['value'].' — sisa '.\App\Support\QuantityDisplayFormatter::format($identity['sisa'])
                                        : $identity['value'],
                                    'search' => $identity['value'],
                                ])
                                ->values()
                                ->all();
                        };
                        $serialOptions = $identityOptionsFor('sn');
                        $drumOptions = $identityOptionsFor('drum');
                        $serialValues = collect($transferFormData['identities'][(string) $initialOriginId][(string) ($oldMaterial?->id ?? 0)] ?? [])->where('type', 'sn')->pluck('value')->map(fn ($value): string => (string) $value)->all();
                        $drumValues = collect($transferFormData['identities'][(string) $initialOriginId][(string) ($oldMaterial?->id ?? 0)] ?? [])->where('type', 'drum')->pluck('value')->map(fn ($value): string => (string) $value)->all();
                        $serialValue = (string) ($oldItem['serial_number'] ?? '');
                        $drumValue = (string) ($oldItem['drum_id'] ?? '');
                        $serialUnavailable = $oldMaterial?->jenis === 'ber_sn' && $serialValue !== '' && ! in_array($serialValue, $serialValues, true);
                        $drumUnavailable = $oldMaterial?->jenis === 'drum_kabel' && $drumValue !== '' && ! in_array($drumValue, $drumValues, true);
                    @endphp<div class="ui-list__item" data-transfer-row data-row-asal="{{ $oldAsal }}"><div class="ui-form__grid"><label>Material<select name="items[{{ $index }}][material_id]" data-material-select required @disabled($oldTerkunci)><option value="">Pilih Material</option>@foreach($materials as $material)<option value="{{ $material->id }}" data-kind="{{ $material->jenis }}" @selected($oldMaterial?->id === $material->id)>{{ $material->kode }} — {{ $material->nama }} ({{ $material->unit->nama }})</option>@endforeach</select>@if($oldTerkunci)<input type="hidden" name="items[{{ $index }}][material_id]" value="{{ $oldMaterial->id }}">@endif</label><label>Qty<input type="number" name="items[{{ $index }}][qty]" min="0.001" step="0.001" required value="{{ $oldItem['qty'] ?? '' }}"></label></div><div class="ui-form__grid"><label data-identity="serial_number" @if($oldMaterial?->jenis !== 'ber_sn') hidden @endif @if($serialUnavailable) data-identity-unavailable @endif>Serial Number<x-ui.searchable-select name="items[{{ $index }}][serial_number]" id="transfer-item-{{ $index }}-serial-number" :options="$serialOptions" :value="$serialUnavailable ? '' : $serialValue" placeholder="Pilih Serial Number" :required="$oldMaterial?->jenis === 'ber_sn'" data-identity-select />@if($serialUnavailable)<p class="ui-help" data-identity-unavailable-note>Identitas ini sudah tidak tersedia. Pilih ulang.</p>@endif</label><label data-identity="drum_id" @if($oldMaterial?->jenis !== 'drum_kabel') hidden @endif @if($drumUnavailable) data-identity-unavailable @endif>Drum ID<x-ui.searchable-select name="items[{{ $index }}][drum_id]" id="transfer-item-{{ $index }}-drum-id" :options="$drumOptions" :value="$drumUnavailable ? '' : $drumValue" placeholder="Pilih Drum ID" :required="$oldMaterial?->jenis === 'drum_kabel'" data-identity-select />@if($drumUnavailable)<p class="ui-help" data-identity-unavailable-note>Identitas ini sudah tidak tersedia. Pilih ulang.</p>@endif</label></div><label>Catatan<input name="items[{{ $index }}][catatan]" maxlength="1000" data-catatan-input value="{{ $oldItem['catatan'] ?? '' }}"></label><input type="hidden" name="items[{{ $index }}][asal]" value="{{ $oldAsal }}" data-row-origin><button class="ui-button ui-button--muted" type="button" data-remove-item @if($index === 0 && ! $oldTerkunci) hidden @endif>Hapus item</button></div>@endforeach</div><button class="ui-button ui-button--muted" type="button" data-add-item>Tambah item</button></div>
                    <button class="ui-button" type="submit">Terbitkan Surat Jalan</button>
                </form>@endif
                {{-- Kontrak data form request-driven: prefill dan penyaringan berjalan murni di klien, tanpa endpoint JSON baru. --}}
                <script type="application/json" data-transfer-form-data>@json($transferFormData)</script>
            </article>
            @endif
        </section>
        <section class="ui-panel ui-panel--wide" style="margin-top:18px"><h2>Pengiriman masuk</h2><p class="ui-help">Surat Jalan terbuka yang menuju Warehouse yang ditugaskan kepada Anda. Buka detail untuk mencatat penerimaan aktual tanpa membuat transaksi ganda.</p>@if($suratJalanMasuk->isEmpty())<div class="ui-state" role="status">Tidak ada pengiriman masuk yang menunggu penerimaan.</div>@else<div class="ui-list">@foreach($suratJalanMasuk as $transfer)<article class="ui-list__item"><div class="ui-inline" style="justify-content:space-between"><div><strong>{{ $transfer->nomor }}</strong><div class="ui-muted">Dari {{ $transfer->origin->kode }} — {{ $transfer->origin->nama }} · Ke {{ $transfer->destination->kode }} — {{ $transfer->destination->nama }}</div><div class="ui-muted">{{ $transfer->tanggal?->format('d M Y') }} · {{ $transfer->items->count() }} item · <x-ui.badge tone="warning" label="Menunggu penerimaan" /></div></div><a class="ui-button ui-button--muted" href="{{ route('warehouse.transfers.show', $transfer) }}">Terima Pengiriman</a></div></article>@endforeach</div>@endif</section>
        <section class="ui-panel ui-panel--wide" style="margin-top:18px"><h2>Saldo Warehouse</h2>@if($stocks->isEmpty())<div class="ui-state">Belum ada saldo Material positif pada Warehouse yang ditugaskan.</div>@else<div class="ui-table-wrap"><table class="ui-table"><thead><tr><th>Warehouse</th><th>Material</th><th>Saldo</th><th>Unit</th></tr></thead><tbody>@foreach($stocks as $stock)<tr><td>{{ $stock->warehouse->kode }} — {{ $stock->warehouse->nama }}</td><td>{{ $stock->material->kode }} — {{ $stock->material->nama }}</td><td>{{ \App\Support\QuantityDisplayFormatter::format($stock->qty) }}</td><td>{{ $stock->material->unit->nama }}</td></tr>@endforeach</tbody></table></div>@endif</section>
        <section class="ui-panel ui-panel--wide" style="margin-top:18px"><h2>Drum tersedia</h2>@if($drums->isEmpty())<div class="ui-state">Belum ada Drum tersedia.</div>@else<div class="ui-table-wrap"><table class="ui-table"><thead><tr><th>Drum ID</th><th>Material</th><th>Warehouse</th><th>Sisa</th></tr></thead><tbody>@foreach($drums as $drum)<tr><td>{{ $drum->drum_id }}</td><td>{{ $drum->material->nama }}</td><td>{{ $drum->lokasi_id }}</td><td>{{ \App\Support\QuantityDisplayFormatter::format($drum->sisa) }} {{ $drum->material->unit->nama }}</td></tr>@endforeach</tbody></table></div>@endif</section>
        <section class="ui-panel ui-panel--wide" style="margin-top:18px"><h2>Aktivitas buku transaksi</h2>@if($transactions->isEmpty())<div class="ui-state">Belum ada transaksi Material.</div>@else<div class="ui-table-wrap"><table class="ui-table"><thead><tr><th>Waktu</th><th>Warehouse</th><th>Material</th><th>Delta</th><th>Jenis</th><th>Alasan</th></tr></thead><tbody>@foreach($transactions as $transaction)<tr><td>{{ $transaction->created_at?->format('d M Y H:i') }}</td><td>{{ $transaction->warehouse->kode }}</td><td>{{ $transaction->material->nama }}</td><td>{{ \App\Support\QuantityDisplayFormatter::format($transaction->qty_delta) }} {{ $transaction->material->unit->nama }}</td><td>{{ $transaction->jenis_transaksi }}</td><td>{{ $transaction->reason }}</td></tr>@endforeach</tbody></table></div>@endif</section>
    @endif
</main>
</x-layouts.app>
