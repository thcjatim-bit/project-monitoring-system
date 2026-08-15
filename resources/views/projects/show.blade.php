<x-layouts.app>
    @php
        $statusLabel = $project->status === 'selesai' ? 'Selesai' : 'Aktif';
    @endphp
    <main class="control-room">
        <style>
            .control-room { color: #172033; margin: 0 auto; max-width: 1280px; padding: 34px 24px 72px; }
            .control-room__back { color: #687684; font-size: .86rem; text-decoration: none; }
            .control-room__header { align-items: flex-end; display: flex; gap: 24px; justify-content: space-between; margin: 22px 0 28px; }
            .control-room__eyebrow { color: #087f8c; font-size: .76rem; font-weight: 800; letter-spacing: .1em; margin: 0 0 8px; text-transform: uppercase; }
            .control-room h1 { color: #15324b; font-size: clamp(1.8rem, 4vw, 3rem); letter-spacing: -.055em; line-height: 1.05; margin: 0 0 9px; }
            .control-room__subtitle { color: #687684; margin: 0; }
            .control-room__actions { display: flex; flex-wrap: wrap; gap: 8px; }
            .control-room__button { background: #087f8c; border: 1px solid #087f8c; border-radius: 8px; color: #fff; display: inline-block; font-size: .84rem; font-weight: 700; padding: 10px 14px; text-decoration: none; }
            .control-room__button--muted { background: #fff; border-color: #cbd6dc; color: #15324b; }
            .control-room__meta { background: #fff; border: 1px solid #dce4e8; border-radius: 14px; display: grid; gap: 16px; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 18px; padding: 20px; }
            .control-room__meta dt { color: #687684; font-size: .75rem; margin-bottom: 5px; }
            .control-room__meta dd { font-size: .96rem; font-weight: 700; margin: 0; }
            .control-room__badge { border-radius: 999px; display: inline-block; font-size: .74rem; padding: 5px 9px; }
            .control-room__badge--active { background: #dff3ed; color: #11664f; }
            .control-room__badge--done { background: #e8edf1; color: #526071; }
            .control-room__grid { display: grid; gap: 18px; grid-template-columns: minmax(0, 1.5fr) minmax(280px, .8fr); }
            .control-room__panel { background: #fff; border: 1px solid #dce4e8; border-radius: 14px; min-height: 180px; padding: 21px; }
            .control-room__panel h2 { color: #15324b; font-size: 1.08rem; margin: 0 0 8px; }
            .control-room__panel p { color: #687684; line-height: 1.5; margin: 0 0 15px; }
            .control-room__state { align-items: center; background: #f6f8f9; border-radius: 10px; color: #687684; display: flex; min-height: 92px; padding: 16px; }
            @media (max-width: 780px) { .control-room { padding: 24px 16px 50px; } .control-room__header { align-items: flex-start; flex-direction: column; } .control-room__meta { grid-template-columns: repeat(2, minmax(0, 1fr)); } .control-room__grid { grid-template-columns: 1fr; } }
        </style>

        <a class="control-room__back" href="{{ route('projects.index') }}">← Kembali ke daftar Project</a>
        <header class="control-room__header">
            <div>
                <p class="control-room__eyebrow">Project Control Room</p>
                <h1>{{ $project->id_project }}</h1>
                <p class="control-room__subtitle">{{ $project->nama }}</p>
            </div>
            <div class="control-room__actions">
                <a class="control-room__button control-room__button--muted" href="#project-timeline">Linimasa</a>
            </div>
        </header>

        <dl class="control-room__meta">
            <div><dt>Mitra pemilik</dt><dd>{{ $project->mitra->nama }}</dd></div>
            <div><dt>Status Project</dt><dd><span class="control-room__badge {{ $project->status === 'selesai' ? 'control-room__badge--done' : 'control-room__badge--active' }}">{{ $statusLabel }}</span></dd></div>
            <div><dt>TOC</dt><dd>{{ $project->toc?->format('d M Y') ?? 'Belum ditetapkan' }}</dd></div>
            <div><dt>Akses</dt><dd>Read Project</dd></div>
        </dl>

        <section class="control-room__grid" aria-label="Ringkasan Project Control Room">
            <article class="control-room__panel">
                <h2>Ringkasan kendali</h2>
                <p>KPI jasa, Kurva S, Step, aktivitas, dan kesiapan material dirakit dari read model Project.</p>
                <div class="control-room__state" role="status">Data kendali belum memiliki rencana atau progres terverifikasi.</div>
            </article>
            <article class="control-room__panel" id="project-timeline">
                <h2>Linimasa Gabungan</h2>
                <div class="control-room__state" data-dashboard-state="empty">Belum ada aktivitas Project yang dapat ditampilkan.</div>
            </article>
        </section>
    </main>
</x-layouts.app>
