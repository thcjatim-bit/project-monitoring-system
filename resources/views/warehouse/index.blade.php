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
            @if($canIssueTransfer)
            <article class="ui-panel"><h2>Terbitkan Surat Jalan</h2><p class="ui-help">Material yang diterbitkan keluar dari saldo asal dan masuk Transit sampai diterima atau diselesaikan THC. Drum kabel boleh dikirim sebagian: qty di bawah sisa melahirkan Drum turunan yang berangkat sementara induknya tinggal di gudang asal. Satu Drum hanya boleh muncul sekali per Surat Jalan.</p>
                @if($materials->isEmpty())<div class="ui-state">Belum ada Material dengan Unit/Satuan aktif.</div>@elseif($destinationWarehouses->isEmpty())<div class="ui-state" role="status">Belum ada Warehouse tujuan aktif yang sesuai dengan tenant Anda.</div>@else
                @php
                    // Render awal dikerjakan server dari payload yang sama dengan yang dipakai JS, supaya
                    // isi dropdown pada muat pertama tidak bergantung pada skrip yang belum sempat jalan.
                    // POST yang ditolak kembali ke halaman ini, jadi render awal berangkat dari old():
                    // konteks gudang/request harus cocok dengan baris yang dipulihkan di bawah.
                    $initialOrigin = $warehouses->firstWhere('id', (int) old('warehouse_asal_id')) ?? $warehouses->first();
                    $initialDestination = $destinationWarehouses->firstWhere('id', (int) old('warehouse_tujuan_id')) ?? $destinationWarehouses->first();
                    $initialMitraId = $initialOrigin?->mitra_id ?? $initialDestination?->mitra_id;
                    // Baris item hanya ada di klien setelah baris pertama, jadi tanpa render ulang ini
                    // catatan yang sudah diketik operator tidak punya tempat untuk kembali.
                    $oldItems = collect(old('items', [[]]))->map(fn ($item) => is_array($item) ? $item : [])->values();
                    $initialRequests = $transferFormData['requests'][(string) $initialDestination?->id] ?? [];
                    // Request ber-Project mengunci Projectnya di klien; render ulang harus memulihkan
                    // kunci itu juga, kalau tidak form kembali dalam bentuk yang prefill tidak pernah buat.
                    $initialRequest = collect($initialRequests)->first(fn (array $candidate): bool => (string) $candidate['id'] === (string) old('material_request_id'));
                    $initialLockedProjectId = $initialRequest['project_id'] ?? null;
                    $initialProjects = collect($transferFormData['projects'])->where('mitra_id', $initialMitraId)->all();
                    $projectKosongLabel = '— Tanpa Project —';
                    $projectTerkunciLabel = 'Gudang THC ke gudang THC — tanpa Project';
                @endphp
                <form class="ui-form" method="POST" action="{{ route('warehouse.transfers.issue') }}" target="_blank" rel="noopener noreferrer" data-submit-loading data-transfer-form>@csrf
                    <div class="ui-form__grid"><label>Warehouse asal<select name="warehouse_asal_id" data-origin-select required>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected($initialOrigin?->id === $warehouse->id)>{{ $warehouse->kode }} — {{ $warehouse->nama }}</option>@endforeach</select></label><label>Warehouse tujuan<select name="warehouse_tujuan_id" data-destination-select required>@foreach($destinationWarehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected($initialDestination?->id === $warehouse->id)>{{ $warehouse->kode }} — {{ $warehouse->nama }}</option>@endforeach</select></label></div>
                    <div class="ui-form__grid"><label>Request Material<select name="material_request_id" data-request-select data-empty-label="— Tanpa Request Material —"><option value="">— Tanpa Request Material —</option>@foreach($initialRequests as $initialRequest)<option value="{{ $initialRequest['id'] }}" @selected((string) old('material_request_id') === (string) $initialRequest['id'])>{{ $initialRequest['label'] }}</option>@endforeach</select></label><label>Project<select name="project_id" data-project-select data-empty-label="{{ $projectKosongLabel }}" data-locked-label="{{ $projectTerkunciLabel }}" @disabled($initialMitraId === null || $initialLockedProjectId !== null)>@if($initialMitraId === null)<option value="">{{ $projectTerkunciLabel }}</option>@else<option value="">{{ $projectKosongLabel }}</option>@foreach($initialProjects as $initialProject)<option value="{{ $initialProject['id'] }}" @selected((string) old('project_id') === (string) $initialProject['id'])>{{ $initialProject['label'] }}</option>@endforeach @endif</select>@if($initialLockedProjectId !== null)<input type="hidden" name="project_id" value="{{ $initialLockedProjectId }}" data-project-lock>@endif</label></div>
                    <div class="ui-form__grid"><label>Tanggal<input type="date" name="tanggal" required value="{{ old('tanggal', now()->toDateString()) }}"></label><label>Pengirim<input name="pengirim" maxlength="255" required value="{{ old('pengirim') }}"></label></div><div class="ui-form__grid"><label>Sopir<input name="sopir" maxlength="255" value="{{ old('sopir') }}"></label><label>Plat nomor<input name="plat_nomor" maxlength="255" value="{{ old('plat_nomor') }}"></label></div>
                    <div class="ui-form"><strong>Item Surat Jalan</strong><div data-transfer-items>@foreach($oldItems as $index => $oldItem)@php
                        $oldMaterial = $materials->firstWhere('id', (int) ($oldItem['material_id'] ?? 0));
                        $oldAsal = ($oldItem['asal'] ?? 'manual') === 'request' ? 'request' : 'manual';
                        $oldTerkunci = $oldAsal === 'request' && $oldMaterial !== null;
                    @endphp<div class="ui-list__item" data-transfer-row data-row-asal="{{ $oldAsal }}"><div class="ui-form__grid"><label>Material<select name="items[{{ $index }}][material_id]" required @disabled($oldTerkunci)><option value="">Pilih Material</option>@foreach($materials as $material)<option value="{{ $material->id }}" data-kind="{{ $material->jenis }}" @selected($oldMaterial?->id === $material->id)>{{ $material->kode }} — {{ $material->nama }} ({{ $material->unit->nama }})</option>@endforeach</select>@if($oldTerkunci)<input type="hidden" name="items[{{ $index }}][material_id]" value="{{ $oldMaterial->id }}">@endif</label><label>Qty<input type="number" name="items[{{ $index }}][qty]" min="0.001" step="0.001" required value="{{ $oldItem['qty'] ?? '' }}"></label></div>{{-- Cat awal kotak identitas mengikuti toggleIdentity(); begitu skrip jalan, ia yang jadi pemiliknya. --}}<div class="ui-form__grid"><label data-identity="serial_number" @if($oldMaterial?->jenis !== 'ber_sn') hidden @endif>Serial Number<input name="items[{{ $index }}][serial_number]" @required($oldMaterial?->jenis === 'ber_sn') value="{{ $oldItem['serial_number'] ?? '' }}"></label><label data-identity="drum_id" @if($oldMaterial?->jenis !== 'drum_kabel') hidden @endif>Drum ID<input name="items[{{ $index }}][drum_id]" @required($oldMaterial?->jenis === 'drum_kabel') value="{{ $oldItem['drum_id'] ?? '' }}"></label></div><label>Catatan<input name="items[{{ $index }}][catatan]" maxlength="1000" data-catatan-input value="{{ $oldItem['catatan'] ?? '' }}"></label><input type="hidden" name="items[{{ $index }}][asal]" value="{{ $oldAsal }}" data-row-origin><button class="ui-button ui-button--muted" type="button" data-remove-item @if($index === 0 && ! $oldTerkunci) hidden @endif>Hapus item</button></div>@endforeach</div><button class="ui-button ui-button--muted" type="button" data-add-item>Tambah item</button></div>
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
    // Satu selector untuk halaman maupun baris klon, supaya baris tidak pernah terikat separuh.
    const bindSelects = (root) => root.querySelectorAll('[data-material-select], [data-transfer-row] select').forEach(bindIdentity);
    bindSelects(document);
    // Indeks klon melanjutkan baris yang sudah dirender server — old() bisa memulihkan lebih dari satu.
    const items = document.querySelector('[data-transfer-items]');
    let nextTransferIndex = items?.querySelectorAll('[data-transfer-row]').length ?? 1;
    // Klon diambil dari salinan bersih baris pertama, bukan dari baris pertama yang hidup: baris itu
    // boleh dinonaktifkan saat prefill mengambil alih, dan klon tidak boleh ikut mewarisi keadaannya.
    const rowTemplate = items?.querySelector('[data-transfer-row]')?.cloneNode(true) ?? null;
    // Semua baris baru adalah klon baris pertama, jadi indeks dan binding-nya tidak pernah dirakit tangan.
    const buildRow = (asal) => {
        const source = rowTemplate; const index = nextTransferIndex++; const row = source.cloneNode(true);
        row.querySelectorAll('[name]').forEach((input) => { input.name = input.name.replace(/items\[0\]/g, `items[${index}]`); input.value = ''; });
        // Baris pertama bisa saja baris prefill yang dipulihkan old(), lengkap dengan material terkunci.
        // Klon selalu baris ketikan baru, jadi kunci itu dilepas alih-alih diwariskan.
        row.querySelector('input[type="hidden"][name$="[material_id]"]')?.remove();
        row.querySelectorAll('select, input').forEach((field) => { field.disabled = false; });
        // Asal-usul baris dibawa hidden input, bukan sekadar atribut DOM, supaya bertahan melewati repopulasi old().
        const asalInput = row.querySelector('[data-row-origin]'); if (asalInput) asalInput.value = asal;
        row.dataset.rowAsal = asal;
        row.querySelector('[data-remove-item]').hidden = false; items.append(row); bindSelects(row);
        return { row, index };
    };
    document.querySelector('[data-add-item]')?.addEventListener('click', () => buildRow('manual'));
    items?.addEventListener('click', (event) => { if (event.target.matches('[data-remove-item]')) event.target.closest('[data-transfer-row]').remove(); });
    // Form request-driven: penyaringan dan prefill berjalan murni di klien di atas payload yang diserialisasi Blade.
    const transferForm = document.querySelector('[data-transfer-form]');
    const formDataScript = document.querySelector('[data-transfer-form-data]');
    const formData = formDataScript ? JSON.parse(formDataScript.textContent) : null;
    if (formData && transferForm) {
        const originSelect = transferForm.querySelector('[data-origin-select]');
        const destinationSelect = transferForm.querySelector('[data-destination-select]');
        const requestSelect = transferForm.querySelector('[data-request-select]');
        const projectSelect = transferForm.querySelector('[data-project-select]');
        const option = (value, label) => { const created = document.createElement('option'); created.value = String(value); created.textContent = label; return created; };
        // Mitra Surat Jalan ditentukan gudang asal, dan jatuh ke gudang tujuan bila asal milik THC.
        const effectiveMitra = () => formData.warehouse_mitra[originSelect.value] ?? formData.warehouse_mitra[destinationSelect.value] ?? null;
        let currentMitra = effectiveMitra();
        // Gudang tujuan adalah filter wajib; Project mempersempit daftar tapi request tanpa Project tetap muncul.
        const availableRequests = () => (formData.requests[destinationSelect.value] ?? []).filter((request) =>
            projectSelect.value === '' || request.project_id === null || String(request.project_id) === projectSelect.value);
        // Project yang dibawa request adalah pinjaman: nilainya milik request, bukan pilihan operator.
        // Nilai pra-kunci disimpan saat mengunci, bukan ditebak saat melepas — hanya itu yang bisa
        // membedakan "operator belum memilih apa-apa" dari "operator memilih Project ini sendiri".
        // Kunci hasil render Blade (old()) tidak punya nilai pra-kunci, jadi jatuh ke kosong.
        let projectSebelumKunci = null;
        const renderProjects = () => {
            const mitraId = effectiveMitra();
            // Daftar Project ditulis ulang karena Mitra berganti, jadi nilai pra-kunci yang
            // tersimpan menunjuk Mitra lama dan tidak boleh dipulihkan ke daftar yang baru.
            projectSebelumKunci = null;
            projectSelect.replaceChildren();
            projectSelect.disabled = mitraId === null;
            if (mitraId === null) { projectSelect.append(option('', projectSelect.dataset.lockedLabel)); return; }
            projectSelect.append(option('', projectSelect.dataset.emptyLabel));
            formData.projects.filter((project) => project.mitra_id === mitraId).forEach((project) => projectSelect.append(option(project.id, project.label)));
        };
        const renderRequests = () => {
            requestSelect.replaceChildren(option('', requestSelect.dataset.emptyLabel));
            availableRequests().forEach((request) => requestSelect.append(option(request.id, request.label)));
        };
        // Baris pertama selalu ada sebagai baris ketikan operator, dan field-nya wajib diisi. Begitu
        // prefill mengisi form, baris itu tinggal hampa tapi tetap menahan submit; dinonaktifkan
        // supaya lepas dari validasi HTML dan tidak ikut terkirim sebagai item kosong.
        // Baris pertama dicari ulang setiap kali, bukan disimpan sekali: baris pertama hasil old()
        // bisa saja baris prefill yang ikut terbuang, dan penggantinya yang harus dilayani.
        const firstRow = () => items.querySelector('[data-transfer-row]');
        const firstRowIsEmpty = () => [...firstRow().querySelectorAll('select, input:not([type="hidden"])')]
            .every((field) => field.value === '');
        const idleFirstRow = (idle) => {
            const row = firstRow();
            row.hidden = idle;
            row.querySelectorAll('select, input').forEach((field) => { field.disabled = idle; });
        };
        // Form selalu menyisakan satu baris untuk diketik operator; tanpa itu tidak ada yang bisa
        // diisi dan Surat Jalan tidak bisa terbit. Baris pertama hasil old() bisa berupa baris
        // prefill, jadi baris itu pun bisa ikut terbuang.
        const ensureTypingRow = () => { if (firstRow() === null) buildRow('manual'); };
        // Membuang baris prefill selalu berarti baris pertama dibutuhkan kembali, jadi keduanya satu langkah.
        const dropPrefillRows = () => {
            items.querySelectorAll('[data-row-asal="request"]').forEach((row) => row.remove());
            transferForm.querySelector('[data-fraction-notice]')?.remove();
            ensureTypingRow();
            idleFirstRow(false);
        };
        // Baris prefill juga pergi satu per satu lewat tombolnya sendiri, bukan hanya lewat ganti
        // pilihan. Baris prefill terakhir yang dibuang berarti prefill habis, dan itu ditempuh lewat
        // jalur yang sama supaya "prefill habis" tidak pernah berarti dua hal berbeda. Membuang baris
        // ketikan operator bukan itu — peringatan pecahan bisa hidup tanpa satu pun baris prefill —
        // jadi yang dijaga hanya barisnya. Terdaftar sebelum penandaan menyimpang, supaya baris
        // pertama sudah pulih saat penandaan menghitung ulang.
        items.addEventListener('click', (event) => {
            if (! event.target.matches('[data-remove-item]')) return;
            const prefillHabis = event.target.closest('[data-transfer-row]')?.dataset.rowAsal === 'request'
                && items.querySelector('[data-row-asal="request"]') === null;
            if (prefillHabis) { dropPrefillRows(); return; }
            ensureTypingRow();
        });
        const unlockProject = () => {
            const lock = transferForm.querySelector('[data-project-lock]');
            if (lock !== null) {
                lock.remove();
                projectSelect.value = projectSebelumKunci ?? '';
                projectSebelumKunci = null;
            }
            projectSelect.disabled = effectiveMitra() === null;
        };
        const lockProject = (projectId) => {
            projectSebelumKunci = projectSelect.value;
            projectSelect.value = String(projectId); projectSelect.disabled = true;
            // Select yang disabled tidak ikut terkirim, jadi nilai terkuncinya dibawa hidden input.
            const lock = document.createElement('input');
            lock.type = 'hidden'; lock.name = 'project_id'; lock.value = String(projectId); lock.setAttribute('data-project-lock', '');
            projectSelect.after(lock);
        };
        const prefillRow = (item, qty) => {
            const { row, index } = buildRow('request');
            const materialSelect = row.querySelector('select');
            materialSelect.value = String(item.material_id); materialSelect.disabled = true;
            // Material baris prefill dikunci; select disabled tidak terkirim, hidden input yang membawanya.
            const locked = document.createElement('input');
            locked.type = 'hidden'; locked.name = 'items[' + index + '][material_id]'; locked.value = String(item.material_id);
            materialSelect.after(locked);
            row.querySelector('input[type="number"]').value = String(qty);
            materialSelect.dispatchEvent(new Event('change'));
        };
        // Sisa pecahan pada material ber-SN adalah data cacat dari hulu (ADR-0025), bukan penyimpangan
        // Surat Jalan: mengirim 2 dari sisa 2,5 tetap kirim bertahap, dan server tidak menandainya apa
        // pun saat terbit. Karena itu peringatannya punya salurannya sendiri di tingkat form, terpisah
        // dari markRow — memakai ulang saluran penyimpangan akan membuat layar dan server beda kesimpulan.
        // Pasangan klien dari QuantityDisplayFormatter::format(): pemisah ribuan titik, koma desimal,
        // tiga angka di belakang, nol di ekor dibuang — supaya angka yang sama tidak tampil dua gaya
        // di halaman yang sama.
        const angka = (nilai) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 }).format(nilai);
        // Nama materialnya diambil dari daftar opsi yang sudah dirender Blade; payload request hanya membawa id.
        const materialLabel = (materialId) => rowTemplate?.querySelector('option[value="' + materialId + '"]')?.textContent ?? 'material #' + materialId;
        const reportFractions = (request, pecahan) => {
            transferForm.querySelector('[data-fraction-notice]')?.remove();
            if (pecahan.length === 0) return;
            const notice = document.createElement('div');
            notice.className = 'ui-state ui-state--warning';
            notice.setAttribute('data-fraction-notice', ''); notice.setAttribute('role', 'status');
            // Angkanya, sebabnya, dan siapa yang membetulkan — operator Surat Jalan bukan pihak yang salah.
            notice.replaceChildren(...pecahan.map(({ item, barisan }) => {
                const kalimat = document.createElement('p');
                kalimat.textContent = 'Request #' + request.id + ' mencatat ' + angka(item.sisa) + ' ' + materialLabel(item.material_id)
                    + ' — material ber-Serial Number tidak bisa pecahan. '
                    + (barisan === 0 ? 'Tidak ada pcs yang dapat ter-prefill' : barisan + ' pcs ter-prefill')
                    + '; sisa ' + angka(item.sisa - barisan) + ' tidak dapat dikirim dan perlu dibetulkan pada Request Material.';
                return kalimat;
            }));
            items.before(notice);
        };
        // Penyimpangan ditandai, tidak diblokir (ADR-0024). Klasifikasinya sengaja meniru
        // SuratJalanService::classifyRequestDeviations(): diukur per material atas seluruh baris
        // dengan toleransi yang sama, supaya peringatan di layar dan penilaian server saat terbit
        // tidak mungkin berbeda kesimpulan.
        const QTY_TOLERANCE = 0.0005;
        const DEVIATING_CLASS = 'ui-list__item--deviating';
        // Material baris prefill dibawa hidden input karena selectnya dikunci disabled.
        const rowMaterial = (row) => row.querySelector('input[type="hidden"][name$="[material_id]"]')?.value
            ?? row.querySelector('select')?.value ?? '';
        const rowQty = (row) => Number.parseFloat(row.querySelector('input[type="number"]')?.value ?? '') || 0;
        let nextNoteId = 0;
        const deviationNote = (row) => {
            const existing = row.querySelector('[data-deviation-note]');
            if (existing) return existing;
            const note = document.createElement('p');
            note.className = 'ui-help'; note.setAttribute('data-deviation-note', ''); note.setAttribute('role', 'status');
            // Catatan penandaan sekaligus jadi deskripsi field catatan, jadi ia butuh id sendiri.
            note.id = 'deviation-note-' + (nextNoteId += 1);
            row.append(note);
            return note;
        };
        // Panduan, bukan penghakiman: field catatan tidak pernah diberi `required`. Penyimpangan
        // diputuskan server saat terbit, dan baris yang klien kira patuh bisa saja tidak patuh.
        const CATATAN_PLACEHOLDER = 'Wajib: alasan penyimpangan baris ini';
        const guideCatatan = (row, note) => {
            const catatan = row.querySelector('[data-catatan-input]');
            if (!catatan) return;
            if (note === null) { catatan.removeAttribute('aria-describedby'); catatan.placeholder = ''; return; }
            catatan.setAttribute('aria-describedby', note.id); catatan.placeholder = CATATAN_PLACEHOLDER;
        };
        const markRow = (row, jenis, sisa) => {
            row.classList.toggle(DEVIATING_CLASS, jenis !== null);
            if (jenis === null) {
                delete row.dataset.deviation; row.querySelector('[data-deviation-note]')?.remove(); guideCatatan(row, null);
                return;
            }
            row.dataset.deviation = jenis;
            // Warna saja tidak cukup: alasannya harus terbaca, dan sisa harus disebut angkanya.
            const note = deviationNote(row);
            note.textContent = (jenis === 'material_asing'
                ? 'Menyimpang: material ini tidak ada di Request Material yang dipilih.'
                : 'Menyimpang: total qty material ini melebihi sisa Request Material (sisa ' + sisa + ').')
                + ' Isi Catatan baris ini — baris menyimpang tanpa catatan tidak bisa terbit.';
            guideCatatan(row, note);
        };
        const markDeviations = () => {
            const request = availableRequests().find((candidate) => String(candidate.id) === requestSelect.value);
            const sisa = new Map((request?.items ?? []).map((item) => [String(item.material_id), item.sisa]));
            const total = new Map();
            const baris = [...items.querySelectorAll('[data-transfer-row]')];
            // Baris yang disembunyikan tidak ikut terkirim, jadi ia tidak menambah total dan tidak pernah ditandai.
            baris.filter((row) => !row.hidden).forEach((row) => {
                const material = rowMaterial(row);
                if (material !== '') total.set(material, (total.get(material) ?? 0) + rowQty(row));
            });
            baris.forEach((row) => {
                const material = rowMaterial(row);
                if (request === undefined || row.hidden || material === '') { markRow(row, null); return; }
                if (!sisa.has(material)) { markRow(row, 'material_asing'); return; }
                markRow(row, total.get(material) > sisa.get(material) + QTY_TOLERANCE ? 'qty_melebihi' : null, sisa.get(material));
            });
        };
        items.addEventListener('input', markDeviations);
        items.addEventListener('change', markDeviations);
        // Tambah dan hapus baris tidak memicu input maupun change, jadi keduanya menandai ulang sendiri.
        // Keduanya terdaftar setelah penangan yang benar-benar mengubah baris, jadi selalu berjalan sesudahnya.
        document.querySelector('[data-add-item]')?.addEventListener('click', markDeviations);
        items.addEventListener('click', (event) => { if (event.target.matches('[data-remove-item]')) markDeviations(); });
        const applyRequest = () => {
            dropPrefillRows(); unlockProject();
            const request = availableRequests().find((candidate) => String(candidate.id) === requestSelect.value);
            if (!request) return;
            if (request.project_id !== null) lockProject(request.project_id);
            // Qty prefill adalah sisa, material bersisa 0 tidak muncul, dan baris ber-SN dipecah satu baris per pcs.
            const pecahan = [];
            request.items.filter((item) => item.sisa > 0).forEach((item) => {
                const berSn = item.jenis === 'ber_sn';
                // Floor, bukan round: sisa 2,5 pcs ber-SN berarti dua pcs yang benar-benar ada.
                // Membulatkan ke atas menaikkan total qty diam-diam dan justru melahirkan penyimpangan.
                const barisan = berSn ? Math.floor(item.sisa) : 1;
                // Yang dibuang floor tidak boleh lenyap tanpa jejak: ia dilaporkan di tingkat form.
                if (berSn && ! Number.isInteger(item.sisa)) pecahan.push({ item, barisan });
                for (let pcs = 0; pcs < barisan; pcs += 1) prefillRow(item, berSn ? 1 : item.sisa);
            });
            reportFractions(request, pecahan);
            if (items.querySelector('[data-row-asal="request"]') !== null && firstRowIsEmpty()) idleFirstRow(true);
            markDeviations();
        };
        // Melepas kunci lebih dulu, baru menyusun ulang daftar: penyaring request membaca
        // projectSelect, jadi selama Project masih pinjaman request lama daftar yang tersusun
        // lebih sempit daripada yang berhak dilihat operator.
        const resetRequest = () => { unlockProject(); renderRequests(); requestSelect.value = ''; dropPrefillRows(); markDeviations(); };
        destinationSelect.addEventListener('change', () => {
            // Ganti gudang tujuan me-reset Project hanya bila Mitra efektif berubah.
            if (effectiveMitra() !== currentMitra) { currentMitra = effectiveMitra(); renderProjects(); }
            resetRequest();
        });
        originSelect.addEventListener('change', () => {
            if (effectiveMitra() === currentMitra) return;
            currentMitra = effectiveMitra(); renderProjects(); resetRequest();
        });
        projectSelect.addEventListener('change', () => {
            const previous = requestSelect.value;
            renderRequests();
            if (previous !== '' && requestSelect.querySelector('option[value="' + previous + '"]') !== null) { requestSelect.value = previous; return; }
            requestSelect.value = ''; dropPrefillRows(); markDeviations();
        });
        requestSelect.addEventListener('change', applyRequest);
        // Baris hasil old() sudah ada sebelum ada event apa pun; tanpa ini penolakan server
        // mengembalikan baris menyimpang tanpa penandaan maupun panduan catatannya.
        markDeviations();
    }
    const RESTORE_SUBMIT_AFTER_MS = 1000;
    const NAVIGATES_THIS_PAGE = ['', '_self', '_parent', '_top'];
    document.querySelectorAll('[data-submit-loading]').forEach((form) => form.addEventListener('submit', () => {
        const buttons = [...form.querySelectorAll('button[type="submit"]')];
        buttons.forEach((button) => { button.disabled = true; button.dataset.originalLabel = button.textContent; button.textContent = 'Ops…'; });
        // Halaman ini akan berpindah, jadi tombol sengaja dibiarkan terkunci sampai halaman lenyap.
        if (NAVIGATES_THIS_PAGE.includes(form.target.toLowerCase())) return;
        setTimeout(() => buttons.forEach((button) => { button.disabled = false; button.textContent = button.dataset.originalLabel ?? button.textContent; }), RESTORE_SUBMIT_AFTER_MS);
    }));
})();
</script>
</x-layouts.app>
