<x-layouts.app>
    <main class="module-page">
        <style>
            .module-page { color: #172033; margin: 0 auto; max-width: 880px; padding: 32px 22px 68px; }
            .module-page__back { color: #687684; font-size: .86rem; text-decoration: none; }
            .module-page h1 { color: #15324b; letter-spacing: -.04em; margin: 18px 0 7px; }
            .module-page h2 { color: #15324b; font-size: 1.1rem; margin: 25px 0 8px; }
            .module-page p { color: #687684; line-height: 1.5; }
            .module-page__card { background: #fff; border: 1px solid #dce4e8; border-radius: 13px; margin-top: 18px; padding: 19px; }
            .module-page__facts { display: grid; gap: 14px; grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .module-page__facts dt { color: #687684; font-size: .74rem; }
            .module-page__facts dd { font-weight: 700; margin: 4px 0 0; }
            .module-page__button { background: #087f8c; border: 1px solid #087f8c; border-radius: 8px; color: #fff; display: inline-block; font-size: .82rem; font-weight: 700; padding: 9px 13px; text-decoration: none; }
            .module-page__button--muted { background: #fff; border-color: #cbd6dc; color: #15324b; }
            .module-page__actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; }
            @media (max-width: 650px) { .module-page { padding: 24px 16px 50px; } .module-page__facts { grid-template-columns: 1fr 1fr; } }
        </style>

        <a class="module-page__back" href="{{ route('projects.material-usages.index', $usage->project) }}">← Kembali ke Pemakaian Material Project</a>
        <h1>Detail Pemakaian Material</h1>
        <p>{{ $usage->project?->id_project }} — {{ $usage->project?->nama }}</p>
        <section class="module-page__card">
            <dl class="module-page__facts">
                <div><dt>Status approval</dt><dd>{{ $usage->status }}</dd></div>
                <div><dt>Material</dt><dd>{{ $usage->material?->nama ?? 'Material' }}</dd></div>
                <div><dt>Qty</dt><dd>{{ number_format((float) $usage->qty, 3, '.', '') }} {{ $usage->material?->unit?->nama }}</dd></div>
                <div><dt>Warehouse asal</dt><dd>{{ $usage->warehouse?->nama ?? 'Warehouse' }}</dd></div>
                <div><dt>Diajukan oleh</dt><dd>{{ $usage->requester?->name ?? 'User' }}</dd></div>
                <div><dt>Diputuskan oleh</dt><dd>{{ $usage->decider?->name ?? 'Belum diputuskan' }}</dd></div>
            </dl>
            @if ($usage->catatan)<h2>Catatan pengajuan</h2><p>{{ $usage->catatan }}</p>@endif
            @if ($usage->decision_note)<h2>Catatan keputusan</h2><p>{{ $usage->decision_note }}</p>@endif
            <div class="module-page__actions">
                @if (auth()->user()->mitra_id !== null && auth()->user()->hasIzin('create_material_usage') && $usage->status === 'diajukan')
                    <form method="POST" action="{{ route('material-usages.cancel', $usage) }}">@csrf @method('PATCH')<button class="module-page__button module-page__button--muted" type="submit">Batalkan Pengajuan</button></form>
                @endif
                @if (auth()->user()->mitra_id === null && auth()->user()->hasIzin('approve_material_usage') && $usage->status === 'diajukan')
                    <form method="POST" action="{{ route('material-usages.approve', $usage) }}">@csrf @method('PATCH')<button class="module-page__button" type="submit">Setujui</button></form>
                    <form method="POST" action="{{ route('material-usages.reject', $usage) }}">@csrf @method('PATCH')<button class="module-page__button module-page__button--muted" type="submit">Tolak</button></form>
                @endif
            </div>
        </section>
    </main>
</x-layouts.app>
