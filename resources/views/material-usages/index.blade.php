<x-layouts.app>
    <main class="module-page">
        <style>
            .module-page { color: #172033; margin: 0 auto; max-width: 1180px; padding: 32px 22px 68px; }
            .module-page h1 { color: #15324b; letter-spacing: -.04em; margin: 10px 0 7px; }
            .module-page h2 { color: #15324b; font-size: 1.15rem; margin: 26px 0 8px; }
            .module-page p { color: #687684; line-height: 1.5; }
            .module-page__back { color: #687684; font-size: .86rem; text-decoration: none; }
            .module-page__state { background: #f6f8f9; border-radius: 9px; color: #687684; margin-top: 13px; padding: 14px; }
            .module-page__state--error { background: #fef2f2; color: #991b1b; }
            .module-page__form { background: #fff; border: 1px solid #dce4e8; border-radius: 12px; display: grid; gap: 10px; margin-top: 14px; padding: 16px; }
            .module-page__form-grid { display: grid; gap: 10px; grid-template-columns: repeat(4, minmax(0, 1fr)); }
            .module-page label { color: #526071; display: grid; font-size: .76rem; gap: 5px; }
            .module-page input, .module-page select { border: 1px solid #cbd6dc; border-radius: 7px; min-height: 36px; padding: 7px 9px; }
            .module-page__button { background: #087f8c; border: 1px solid #087f8c; border-radius: 8px; color: #fff; cursor: pointer; font-size: .82rem; font-weight: 700; padding: 9px 13px; }
            .module-page__button--muted { background: #fff; border-color: #cbd6dc; color: #15324b; }
            .module-page__list { display: grid; gap: 10px; list-style: none; margin: 14px 0 0; padding: 0; }
            .module-page__item { background: #fff; border: 1px solid #dce4e8; border-radius: 11px; padding: 15px; }
            .module-page__item strong { color: #15324b; }
            .module-page__item small { color: #687684; display: block; margin-top: 4px; }
            .module-page__item a { color: #087f8c; font-weight: 700; }
            .module-page__actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
            @media (max-width: 760px) { .module-page { padding: 24px 16px 50px; } .module-page__form-grid { grid-template-columns: 1fr 1fr; } }
        </style>

        @if ($project)
            <a class="module-page__back" href="{{ route('projects.show', $project) }}">← Kembali ke Project Control Room</a>
            <h1>Pemakaian Material · {{ $project->id_project }}</h1>
            <p>Daftar pengajuan untuk {{ $project->id_project }} — {{ $project->nama }}. Pengajuan berstatus Pending / diajukan belum mengurangi buku stok sampai THC menyetujui.</p>
        @else
            <a class="module-page__back" href="{{ route('projects.index') }}">← Kembali ke daftar Project</a>
            <h1>Pemakaian Material</h1>
            <p>Pengajuan Mitra dari Warehouse ke Project. Pending / diajukan tidak mengurangi stok; keputusan THC menjadi sumber transaksi.</p>
        @endif

        @if (session('status')) <div class="module-page__state" role="status">{{ session('status') }}</div> @endif
        @if ($errors->any()) <div class="module-page__state module-page__state--error" role="alert"><strong>Periksa pengajuan.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif

        @if (auth()->user()->mitra_id !== null && auth()->user()->hasIzin('create_material_usage'))
            <h2>Ajukan Pemakaian Material</h2>
            @forelse ($projects as $formProject)
                <form class="module-page__form" method="POST" action="{{ route('projects.material-usages.store', $formProject) }}">
                    @csrf
                    @if ($project)<input type="hidden" name="return_to_project" value="1">@endif
                    <strong>{{ $formProject->id_project }} — {{ $formProject->nama }}</strong>
                    <div class="module-page__form-grid">
                        <label>Warehouse
                            <select name="warehouse_id" required><option value="">Pilih Warehouse</option>@foreach ($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->nama }}</option>@endforeach</select>
                        </label>
                        <label>Material
                            <select name="material_id" required><option value="">Pilih Material</option>@foreach ($materials as $material)<option value="{{ $material->id }}">{{ $material->kode }} — {{ $material->nama }} ({{ $material->unit?->nama }})</option>@endforeach</select>
                        </label>
                        <label>Qty <input name="qty" type="number" min="0.001" step="0.001" required></label>
                        <label>Catatan <input name="catatan" maxlength="2000"></label>
                    </div>
                    <button class="module-page__button" type="submit">Ajukan Pemakaian</button>
                </form>
            @empty
                <div class="module-page__state">Belum ada Project aktif untuk Mitra ini.</div>
            @endforelse
        @endif

        <h2>Daftar Pemakaian</h2>
        @if ($usages->isEmpty())
            <div class="module-page__state">Belum ada Pemakaian Material untuk konteks ini.</div>
        @else
            <ul class="module-page__list">
                @foreach ($usages as $usage)
                    <li class="module-page__item" id="pemakaian-material-{{ $usage->id }}">
                        <strong>{{ $usage->material?->nama ?? 'Material' }} · {{ number_format((float) $usage->qty, 3, '.', '') }}</strong>
                        <small>{{ $usage->project?->id_project }} · {{ $usage->warehouse?->nama ?? 'Warehouse' }} · Status: {{ $usage->status }} · Diajukan oleh {{ $usage->requester?->name ?? 'User' }}</small>
                        <div class="module-page__actions">
                            <a class="module-page__button module-page__button--muted" href="{{ route('material-usages.show', $usage) }}">Detail Pemakaian</a>
                            @if (auth()->user()->mitra_id !== null && auth()->user()->hasIzin('create_material_usage') && $usage->status === 'diajukan')
                                <form method="POST" action="{{ route('material-usages.cancel', $usage) }}">@csrf @method('PATCH')<button class="module-page__button module-page__button--muted" type="submit">Batalkan</button></form>
                            @endif
                            @if (auth()->user()->mitra_id === null && auth()->user()->hasIzin('approve_material_usage') && $usage->status === 'diajukan')
                                <form method="POST" action="{{ route('material-usages.approve', $usage) }}">@csrf @method('PATCH')<button class="module-page__button" type="submit">Setujui</button></form>
                                <form method="POST" action="{{ route('material-usages.reject', $usage) }}">@csrf @method('PATCH')<button class="module-page__button module-page__button--muted" type="submit">Tolak</button></form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </main>
</x-layouts.app>
