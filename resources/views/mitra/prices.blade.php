<x-layouts.app>
    <x-ui.page>
        <x-ui.page-header eyebrow="Mitra · Harga Jasa" title="Harga Jasa Mitra" subtitle="Ajukan harga per Pekerjaan Jasa dalam PKS aktif Mitra Anda. Harga baru tidak mengubah RAB yang sudah dibekukan.">
            <x-slot:actions><x-ui.badge tone="neutral" label="Menunggu persetujuan THC" /></x-slot:actions>
        </x-ui.page-header>
        @if (session('status')) <div class="ui-state ui-state--success" role="status">{{ session('status') }}</div> @endif
        <x-form-errors />
        <x-ui.panel>
            <h2>Ajukan Harga Jasa</h2>
            <form class="ui-form" method="POST" action="{{ route('mitra.prices.store') }}" data-submit-loading>
                @csrf
                <div class="ui-form__grid"><label>PKS aktif<select name="pks_id" required><option value="">Pilih PKS</option>@foreach ($pks as $contract)<option value="{{ $contract->id }}">{{ $contract->nomor }} · {{ $contract->tanggal_mulai->format('d M Y') }}</option>@endforeach</select></label><label>Pekerjaan Jasa<select name="pekerjaan_jasa_id" required><option value="">Pilih Pekerjaan Jasa</option>@foreach ($jobs as $job)<option value="{{ $job->id }}">{{ $job->kode }} · {{ $job->nama }}</option>@endforeach</select></label></div>
                <div class="ui-form__grid"><label>Harga (Rp)<input name="harga" type="number" min="0.01" step="0.01" value="{{ old('harga') }}" required></label><label>Berlaku mulai<input name="berlaku_mulai" type="date" value="{{ old('berlaku_mulai', today()->toDateString()) }}" required></label></div>
                <button class="ui-button" type="submit">Ajukan Harga</button>
            </form>
        </x-ui.panel>
        <x-ui.panel>
            <div class="ui-section-head"><div><h2>Riwayat Harga Jasa</h2><p class="ui-help">Harga `diajukan` belum dapat dipakai untuk RAB Jasa.</p></div><x-ui.badge tone="neutral" label="{{ $prices->count() }} Harga" /></div>
            @if ($prices->isEmpty()) <x-ui.empty-state title="Belum ada Harga Jasa Mitra." /> @else
                <div class="ui-table-wrap"><table class="ui-table"><thead><tr><th>Pekerjaan Jasa</th><th>PKS</th><th>Harga</th><th>Berlaku</th><th>Status</th></tr></thead><tbody>@foreach ($prices as $price)<tr><td>{{ $price->pekerjaanJasa?->kode }} · {{ $price->pekerjaanJasa?->nama }}</td><td>{{ $price->pks?->nomor }}</td><td>Rp {{ number_format((float) $price->harga, 2, ',', '.') }}</td><td>{{ $price->berlaku_mulai->format('d M Y') }}</td><td><x-ui.badge :tone="$price->status === 'disetujui' ? 'done' : ($price->status === 'ditolak' ? 'danger' : 'warning')" :label="ucfirst($price->status)" /></td></tr>@endforeach</tbody></table></div>
            @endif
        </x-ui.panel>
    </x-ui.page>
</x-layouts.app>
