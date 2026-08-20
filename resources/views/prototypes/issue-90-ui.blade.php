{{--
    PROTOTYPE THROWAWAY - Issue #90.
    Question: which reusable management-page composition best supports the
    shared UI foundation across dashboard, master, project, mitra, user, and
    warehouse surfaces without changing permissions or domain behavior?
    Three read-only mock variants are switchable via ?variant=a|b|c.
    This route is intentionally not registered in production.
--}}
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prototype #90 - Shared UI Foundation</title>
    <style>
        :root {
            --ink: #18243a;
            --muted: #6f7d91;
            --line: #dfe5ed;
            --canvas: #f4f6fa;
            --surface: #fff;
            --navy: #18243a;
            --navy-2: #243553;
            --indigo: #4d5bd5;
            --indigo-soft: #eef0ff;
            --cyan: #4bc7d6;
            --green: #16845f;
            --green-soft: #e7f7ef;
            --amber: #b56a12;
            --amber-soft: #fff3df;
            --red: #c04d59;
            --red-soft: #fff0f1;
            --shadow: 0 12px 32px rgb(24 36 58 / 7%);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--ink);
            background: var(--canvas);
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; min-width: 320px; background: var(--canvas); }
        button, input, select { font: inherit; }
        button { cursor: pointer; }
        .prototype-ribbon { position: fixed; z-index: 30; top: 12px; right: 16px; padding: 7px 11px; border-radius: 999px; color: #4d3200; background: #fdc858; box-shadow: 0 5px 16px rgb(77 50 0 / 16%); font-size: 11px; font-weight: 850; letter-spacing: .06em; text-transform: uppercase; }
        .variant { display: none; min-height: 100vh; padding-bottom: 104px; }
        .variant.active { display: block; }
        .brand { color: var(--navy); font-size: 18px; font-weight: 900; letter-spacing: -.06em; }
        .brand span { color: var(--indigo); }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 18px; min-height: 72px; padding: 0 30px; border-bottom: 1px solid var(--line); background: rgb(255 255 255 / 94%); }
        .topbar-meta, .identity, .inline { display: flex; align-items: center; gap: 11px; }
        .topbar-meta { color: var(--muted); font-size: 12px; }
        .avatar { display: grid; width: 34px; height: 34px; place-items: center; border-radius: 11px; color: white; background: var(--indigo); font-size: 11px; font-weight: 900; }
        .page { max-width: 1420px; margin: 0 auto; padding: 30px; }
        .eyebrow { margin: 0 0 8px; color: var(--indigo); font-size: 11px; font-weight: 900; letter-spacing: .12em; text-transform: uppercase; }
        h1, h2, h3, p { margin-top: 0; }
        h1 { margin-bottom: 8px; color: var(--navy); font-size: clamp(28px, 4vw, 42px); letter-spacing: -.06em; line-height: 1.04; }
        h2 { margin-bottom: 7px; color: var(--navy); font-size: 18px; letter-spacing: -.03em; }
        h3 { margin-bottom: 5px; font-size: 13px; }
        .subtle { color: var(--muted); font-size: 13px; line-height: 1.55; }
        .muted { color: var(--muted); }
        .card { border: 1px solid var(--line); border-radius: 16px; background: var(--surface); box-shadow: var(--shadow); }
        .card-pad { padding: 20px; }
        .btn { min-height: 37px; padding: 8px 12px; border: 1px solid var(--line); border-radius: 9px; color: var(--navy); background: #fff; font-size: 12px; font-weight: 800; }
        .btn:hover { border-color: var(--indigo); color: var(--indigo); }
        .btn-primary { border-color: var(--indigo); color: #fff; background: var(--indigo); }
        .btn-primary:hover { color: #fff; background: #3f4dc3; }
        .btn-danger { border-color: #f2c8cc; color: var(--red); background: var(--red-soft); }
        .btn-link { padding: 3px 0; border: 0; color: var(--indigo); background: transparent; }
        .badge { display: inline-flex; align-items: center; gap: 5px; padding: 5px 9px; border-radius: 999px; font-size: 10px; font-weight: 900; white-space: nowrap; }
        .badge::before { width: 5px; height: 5px; border-radius: 50%; background: currentColor; content: ""; }
        .badge-green { color: var(--green); background: var(--green-soft); }
        .badge-amber { color: var(--amber); background: var(--amber-soft); }
        .badge-gray { color: var(--muted); background: #eef1f5; }
        .badge-indigo { color: var(--indigo); background: var(--indigo-soft); }
        .section-head { display: flex; align-items: end; justify-content: space-between; gap: 18px; margin-bottom: 15px; }
        .section-head h2 { margin-bottom: 0; }
        .filter-row { display: flex; align-items: center; flex-wrap: wrap; gap: 9px; }
        .search { min-width: 230px; min-height: 38px; padding: 8px 11px; border: 1px solid var(--line); border-radius: 9px; color: var(--ink); background: #fbfcfe; }
        .search:focus, input:focus, select:focus { border-color: var(--indigo); outline: 0; box-shadow: 0 0 0 3px var(--indigo-soft); }
        .context-tabs { display: flex; gap: 4px; padding: 4px; border: 1px solid var(--line); border-radius: 10px; background: #f6f8fb; }
        .context-tabs button { padding: 7px 10px; border: 0; border-radius: 7px; color: var(--muted); background: transparent; font-size: 11px; font-weight: 800; }
        .context-tabs button.active, .context-tabs button:hover { color: var(--indigo); background: #fff; box-shadow: 0 2px 7px rgb(24 36 58 / 8%); }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th { padding: 0 10px 10px; color: var(--muted); font-size: 10px; letter-spacing: .08em; text-align: left; text-transform: uppercase; }
        td { padding: 13px 10px; border-top: 1px solid var(--line); vertical-align: middle; }
        th:first-child, td:first-child { padding-left: 0; }
        th:last-child, td:last-child { padding-right: 0; text-align: right; }
        .code { color: var(--navy); font-weight: 900; letter-spacing: .02em; }
        .row-title { margin-bottom: 3px; color: var(--navy); font-weight: 850; }
        .row-meta { color: var(--muted); font-size: 11px; }
        .empty-state { padding: 28px 18px; border: 1px dashed #cbd4e1; border-radius: 12px; color: var(--muted); background: #fafbfe; text-align: center; }
        .empty-state strong { display: block; margin-bottom: 4px; color: var(--navy); font-size: 13px; }
        .state-strip { position: fixed; z-index: 20; right: 18px; bottom: 82px; max-width: min(600px, calc(100vw - 36px)); padding: 9px 13px; border: 1px solid rgb(24 36 58 / 12%); border-radius: 10px; color: var(--navy); background: rgb(255 255 255 / 95%); box-shadow: var(--shadow); font-size: 11px; }
        .state-strip strong { margin-right: 7px; color: var(--indigo); }
        .switcher { position: fixed; z-index: 25; bottom: 16px; left: 50%; display: flex; align-items: center; gap: 11px; transform: translateX(-50%); padding: 8px; border: 1px solid #2f4160; border-radius: 999px; color: #fff; background: #1c2b45; box-shadow: 0 12px 30px rgb(13 35 51 / 26%); }
        .switcher button { display: grid; width: 34px; height: 34px; place-items: center; border: 0; border-radius: 50%; color: #fff; background: #344b70; font-size: 22px; }
        .switcher button:hover { background: var(--indigo); }
        .switcher-label { min-width: 220px; color: #f5fbfc; font-size: 12px; font-weight: 850; text-align: center; }
        .switcher-label small { display: block; margin-top: 2px; color: #adbed5; font-size: 10px; font-weight: 500; }
        .toast { position: fixed; z-index: 40; top: 18px; right: 20px; max-width: 320px; padding: 12px 15px; border-radius: 10px; color: white; background: var(--navy); box-shadow: var(--shadow); font-size: 12px; opacity: 0; transform: translateY(-8px); pointer-events: none; transition: .2s ease; }
        .toast.show { opacity: 1; transform: translateY(0); }

        /* Variant A: conventional reusable workspace + management table. */
        .a-shell { display: grid; grid-template-columns: 236px minmax(0, 1fr); min-height: 100vh; }
        .a-sidebar { display: flex; flex-direction: column; padding: 20px 13px; color: #d9e2f5; background: var(--navy); }
        .a-sidebar .brand { padding: 0 9px 20px; color: white; border-bottom: 1px solid rgb(255 255 255 / 12%); }
        .a-sidebar .brand span { color: #b9c4ff; }
        .nav-label { margin: 23px 9px 7px; color: #8193ae; font-size: 10px; font-weight: 900; letter-spacing: .1em; text-transform: uppercase; }
        .nav-link { display: flex; width: 100%; align-items: center; gap: 8px; padding: 10px 9px; border: 0; border-radius: 9px; color: #b8c4d9; background: transparent; font-size: 12px; font-weight: 750; text-align: left; }
        .nav-link.active, .nav-link:hover { color: #fff; background: rgb(185 196 255 / 16%); }
        .nav-icon { display: grid; width: 22px; height: 22px; place-items: center; border-radius: 7px; color: #cbd4ff; background: rgb(185 196 255 / 12%); font-size: 9px; font-weight: 900; }
        .a-sidebar footer { margin-top: auto; padding: 18px 9px 3px; color: #99a9c2; font-size: 10px; }
        .a-sidebar footer strong { display: block; margin-bottom: 3px; color: #eef3ff; font-size: 12px; }
        .a-main { min-width: 0; background: var(--canvas); }
        .a-page { max-width: 1240px; }
        .a-layout { display: grid; grid-template-columns: minmax(0, 1fr) 288px; gap: 17px; align-items: start; }
        .a-panel { overflow: hidden; }
        .a-panel-head { padding: 20px 20px 15px; border-bottom: 1px solid var(--line); }
        .a-panel-body { padding: 18px 20px 20px; }
        .contract-list { display: grid; gap: 12px; margin: 18px 0 0; padding: 0; list-style: none; }
        .contract-list li { display: flex; align-items: start; gap: 9px; color: var(--muted); font-size: 11px; line-height: 1.45; }
        .contract-list li::before { display: grid; width: 18px; height: 18px; flex: 0 0 auto; place-items: center; border-radius: 6px; color: var(--indigo); background: var(--indigo-soft); content: "✓"; font-weight: 900; }
        .capability-box { margin-top: 17px; padding: 13px; border-radius: 10px; background: #f8f9fc; }
        .capability-box strong { display: block; margin-bottom: 4px; font-size: 12px; }
        .capability-box span { color: var(--muted); font-size: 11px; line-height: 1.45; }

        /* Variant B: list/detail inspector, prioritising edit/read state. */
        .b-canvas { min-height: 100vh; background: #eef2f8; }
        .b-topbar { background: var(--navy); border-bottom-color: #304463; }
        .b-topbar .brand { color: white; }
        .b-topbar .brand span { color: #b9c4ff; }
        .b-topbar .topbar-meta { color: #b8c4d9; }
        .b-page { max-width: 1510px; }
        .b-heading { display: flex; align-items: end; justify-content: space-between; gap: 20px; margin-bottom: 20px; }
        .b-heading .eyebrow { color: #6977de; }
        .b-layout { display: grid; grid-template-columns: 320px minmax(0, 1fr); min-height: 600px; overflow: hidden; border: 1px solid var(--line); border-radius: 17px; background: #fff; box-shadow: var(--shadow); }
        .b-list { border-right: 1px solid var(--line); background: #fbfcff; }
        .b-list-head { padding: 18px; border-bottom: 1px solid var(--line); }
        .b-list-head .search { width: 100%; min-width: 0; margin-top: 12px; }
        .record-list { display: grid; gap: 3px; padding: 9px; }
        .record { display: flex; align-items: start; justify-content: space-between; gap: 10px; padding: 13px 11px; border: 1px solid transparent; border-radius: 11px; background: transparent; text-align: left; }
        .record:hover, .record.selected { border-color: #d8dcff; background: var(--indigo-soft); }
        .record strong { display: block; margin-bottom: 3px; color: var(--navy); font-size: 12px; }
        .record small { color: var(--muted); font-size: 10px; }
        .b-detail { padding: 25px; }
        .detail-head { display: flex; align-items: start; justify-content: space-between; gap: 16px; padding-bottom: 21px; border-bottom: 1px solid var(--line); }
        .detail-head h2 { margin-bottom: 4px; font-size: 25px; letter-spacing: -.05em; }
        .detail-actions { display: flex; flex-wrap: wrap; justify-content: end; gap: 8px; }
        .detail-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-top: 22px; }
        .field { display: grid; gap: 6px; }
        .field label { color: var(--muted); font-size: 10px; font-weight: 900; letter-spacing: .08em; text-transform: uppercase; }
        .field input, .field select { width: 100%; min-height: 39px; padding: 8px 10px; border: 1px solid var(--line); border-radius: 8px; color: var(--ink); background: #fff; }
        .field input[readonly] { color: var(--muted); background: #f8f9fc; }
        .field-help { color: var(--muted); font-size: 11px; line-height: 1.45; }
        .read-state { margin-top: 22px; padding: 15px; border: 1px solid #dbe8e1; border-radius: 11px; background: #f4fbf7; }
        .read-state strong { display: block; margin-bottom: 3px; color: var(--green); font-size: 12px; }
        .read-state span { color: #4e7664; font-size: 11px; }
        .edit-state { margin-top: 22px; padding: 15px; border: 1px solid #d8dcff; border-radius: 11px; background: #f7f7ff; }
        .edit-state strong { display: block; margin-bottom: 3px; color: var(--indigo); font-size: 12px; }
        .edit-state span { color: #5c659e; font-size: 11px; }

        /* Variant C: command-centre overview with the management queue as one signal. */
        .c-canvas { min-height: 100vh; background: #f1f3f8; }
        .c-hero { padding: 31px 30px 23px; color: white; background: linear-gradient(120deg, #222d4b, #3b4a78); }
        .c-hero-inner { max-width: 1420px; margin: 0 auto; }
        .c-hero .eyebrow { color: #b9c4ff; }
        .c-hero h1 { color: white; }
        .c-hero .subtle { color: #d8e0f5; }
        .c-nav { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 21px; }
        .c-nav button { padding: 7px 11px; border: 1px solid rgb(255 255 255 / 20%); border-radius: 999px; color: #d8e0f5; background: transparent; font-size: 11px; font-weight: 800; }
        .c-nav button.active, .c-nav button:hover { color: var(--navy); background: white; }
        .c-page { max-width: 1420px; }
        .signal-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
        .signal { padding: 17px; border: 1px solid var(--line); border-radius: 13px; background: #fff; }
        .signal-label { color: var(--muted); font-size: 11px; }
        .signal-value { display: block; margin: 8px 0 3px; color: var(--navy); font-size: 28px; font-weight: 900; letter-spacing: -.06em; }
        .signal-note { color: var(--muted); font-size: 10px; }
        .signal-note.good { color: var(--green); font-weight: 800; }
        .c-layout { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(280px, .7fr); gap: 17px; align-items: start; }
        .queue { padding: 20px; }
        .queue-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
        .queue-row { display: grid; grid-template-columns: minmax(0, 1.4fr) 120px 105px 90px; align-items: center; gap: 12px; padding: 14px 0; border-top: 1px solid var(--line); }
        .queue-row > div { min-width: 0; }
        .queue-head { padding-top: 0; border-top: 0; color: var(--muted); font-size: 10px; font-weight: 900; letter-spacing: .08em; text-transform: uppercase; }
        .queue-row:not(.queue-head) { font-size: 12px; }
        .queue-row:not(.queue-head) > :last-child { text-align: right; }
        .c-side { display: grid; gap: 17px; }
        .side-note { padding: 19px; }
        .side-note p { margin-bottom: 13px; }
        .mini-list { display: grid; gap: 11px; margin: 0; padding: 0; list-style: none; }
        .mini-list li { display: flex; align-items: center; justify-content: space-between; gap: 9px; color: var(--muted); font-size: 11px; }
        .mini-list strong { color: var(--navy); }
        .contract-chip { display: inline-flex; padding: 5px 8px; border-radius: 7px; color: var(--indigo); background: var(--indigo-soft); font-size: 10px; font-weight: 900; }

        @media (max-width: 1050px) {
            .a-shell { grid-template-columns: 72px minmax(0, 1fr); }
            .a-sidebar .brand { justify-content: center; padding-right: 0; padding-left: 0; border-bottom: 0; font-size: 0; }
            .a-sidebar .brand::first-letter { font-size: 18px; }
            .nav-label, .nav-link span:not(.nav-icon), .a-sidebar footer { display: none; }
            .nav-link { justify-content: center; }
            .a-layout, .c-layout { grid-template-columns: 1fr; }
            .c-side { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 760px) {
            .topbar { padding: 0 17px; }
            .topbar-meta > span, .identity > div { display: none; }
            .page { padding: 23px 16px; }
            .a-shell { display: block; }
            .a-sidebar { display: block; min-height: auto; padding: 11px 12px; }
            .a-sidebar .brand { display: block; padding: 0 5px 10px; font-size: 16px; }
            .a-sidebar .brand::first-letter { font-size: inherit; }
            .a-sidebar nav { display: flex; gap: 6px; overflow-x: auto; }
            .nav-label { display: none; }
            .nav-link { flex: 0 0 auto; justify-content: start; white-space: nowrap; }
            .nav-link span:not(.nav-icon) { display: inline; }
            .a-layout, .b-layout { display: block; }
            .a-panel { margin-bottom: 17px; }
            .b-list { border-right: 0; border-bottom: 1px solid var(--line); }
            .record-list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .b-detail { padding: 20px 16px; }
            .b-heading, .detail-head, .section-head { align-items: start; flex-direction: column; }
            .detail-actions { justify-content: start; }
            .detail-grid { grid-template-columns: 1fr; }
            .signal-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .queue { padding: 16px; }
            .queue-row { grid-template-columns: minmax(0, 1fr) 90px; }
            .queue-row > :nth-child(3), .queue-row > :nth-child(4) { display: none; }
            .queue-head { display: none; }
            .c-side { grid-template-columns: 1fr; }
            .switcher-label { min-width: 155px; }
            .state-strip { right: 10px; bottom: 77px; }
        }
        @media (max-width: 460px) {
            .record-list, .signal-grid { grid-template-columns: 1fr; }
            .filter-row { align-items: stretch; flex-direction: column; }
            .search { width: 100%; }
            .context-tabs { width: 100%; overflow-x: auto; }
            .context-tabs button { flex: 0 0 auto; }
            .switcher-label { min-width: 120px; font-size: 10px; }
            .switcher-label small { display: none; }
        }
    </style>
</head>
<body>
    <div class="prototype-ribbon">Prototype - Issue #90</div>

    <section class="variant active" data-variant="a">
        <div class="a-shell">
            <aside class="a-sidebar">
                <div class="brand">PMS<span>/THC</span></div>
                <nav aria-label="Variant A navigation">
                    <div class="nav-label">Pusat kerja</div>
                    <div class="nav-link"><span class="nav-icon">CC</span><span>Command Center</span></div>
                    <div class="nav-link"><span class="nav-icon">PF</span><span>Portfolio</span></div>
                    <div class="nav-link"><span class="nav-icon">PRJ</span><span>Project</span></div>
                    <div class="nav-label">Master data</div>
                    <button class="nav-link active" data-context="unit"><span class="nav-icon">UNT</span><span>Unit</span></button>
                    <button class="nav-link" data-context="pop"><span class="nav-icon">POP</span><span>PoP</span></button>
                    <button class="nav-link" data-context="material"><span class="nav-icon">MAT</span><span>Material</span></button>
                    <button class="nav-link" data-context="mitra"><span class="nav-icon">MIT</span><span>Mitra</span></button>
                </nav>
                <footer><strong>Admin Mitra</strong>Read-only master reference</footer>
            </aside>
            <div class="a-main">
                <header class="topbar"><div><div class="brand">Workspace <span>User Mitra</span></div></div><div class="topbar-meta"><span>Scope data milik Mitra</span><div class="avatar">AB</div><button class="btn">Keluar</button></div></header>
                <main class="page a-page">
                    <div class="section-head"><div><p class="eyebrow">Master data / shared management page</p><h1 data-page-title>Unit</h1><p class="subtle" data-page-description>Referensi satuan yang dipakai oleh Material dan alur operasional.</p></div><div class="inline"><button class="btn" data-action="export">Export view</button><button class="btn btn-primary" data-action="capability">Lihat capability</button></div></div>
                    <div class="context-tabs" data-context-tabs><button class="active" data-context="unit">Unit</button><button data-context="pop">PoP</button><button data-context="material">Material</button><button data-context="mitra">Mitra</button></div>
                    <div class="a-layout" style="margin-top:17px">
                        <section class="card a-panel"><div class="a-panel-head"><div class="section-head"><div><h2>Daftar <span data-page-title>Unit</span></h2><p class="subtle" style="margin-bottom:0"><span data-count>3 Unit</span> dalam scope yang diizinkan.</p></div><span class="badge badge-gray">Read-only</span></div><div class="filter-row"><input class="search" data-search placeholder="Cari kode atau nama" aria-label="Cari kode atau nama"><button class="btn" data-action="filter">Filter status: Semua</button></div></div><div class="a-panel-body"><div class="table-wrap"><table><thead><tr><th>Kode</th><th>Nama</th><th>Detail</th><th>Status</th><th>Aksi</th></tr></thead><tbody class="dataset-rows"></tbody></table></div><div class="empty-state" hidden data-empty><strong>Belum ada data yang cocok.</strong>Coba ubah kata kunci pencarian.</div></div></section>
                        <aside><section class="card card-pad"><p class="eyebrow">UI contract</p><h2>Management page</h2><p class="subtle">Satu fondasi untuk master, project, mitra, user, dan warehouse.</p><ul class="contract-list"><li>PageHeader + actions</li><li>Search/filter + responsive list</li><li>Field + helper + validation state</li><li>StatusBadge + EmptyState</li><li>Capability-driven actions</li></ul></section><section class="card card-pad capability-box"><strong>Admin Mitra · read-only</strong><span>UI tidak menambahkan hak edit. Action menampilkan hasil capability backend.</span></section></aside>
                    </div>
                </main>
            </div>
        </div>
    </section>

    <section class="variant" data-variant="b">
        <div class="b-canvas">
            <header class="topbar b-topbar"><div class="brand">PMS<span>/THC</span></div><div class="topbar-meta"><span>Inspector pattern / edit-read state</span><div class="avatar">AB</div><button class="btn">Keluar</button></div></header>
            <main class="page b-page">
                <div class="b-heading"><div><p class="eyebrow">Master data / selected record</p><h1>Reference inspector</h1><p class="subtle">List dan detail berada dalam satu alur agar read state, edit state, dan field feedback mudah dibandingkan.</p></div><span class="badge badge-indigo">Capability: read_master_data</span></div>
                <div class="context-tabs" data-context-tabs><button class="active" data-context="unit">Unit</button><button data-context="pop">PoP</button><button data-context="material">Material</button><button data-context="mitra">Mitra</button></div>
                <div class="b-layout" style="margin-top:17px"><section class="b-list"><div class="b-list-head"><div class="section-head"><div><h2 data-page-title>Unit</h2><p class="subtle" style="margin-bottom:0"><span data-count>3 Unit</span></p></div><span class="badge badge-gray">Read-only</span></div><input class="search" data-search placeholder="Cari kode atau nama" aria-label="Cari kode atau nama"></div><div class="record-list dataset-records"></div><div class="empty-state" hidden data-empty><strong>Tidak ada hasil</strong>Gunakan kata kunci lain.</div></section><section class="b-detail"><div class="detail-head"><div><p class="eyebrow">Selected record</p><h2 data-selected-name>Batang</h2><p class="subtle" style="margin-bottom:0"><span class="code" data-selected-code>UNT-001</span> · <span data-selected-status>Aktif</span></p></div><div class="detail-actions"><button class="btn" data-action="toggle-edit">Simulasi edit state</button><button class="btn btn-primary" data-action="capability">Cek capability</button></div></div><div class="detail-grid"><div class="field"><label>Kode</label><input data-detail-code value="UNT-001" readonly><span class="field-help">Kode master diterbitkan backend dan tidak diubah dari UI.</span></div><div class="field"><label>Nama</label><input data-detail-name value="Batang" readonly></div><div class="field"><label>Status</label><select data-detail-status disabled><option>Aktif</option><option>Nonaktif</option></select></div><div class="field"><label>Scope</label><input value="Shared master / authorized read" readonly></div></div><div class="read-state" data-read-state><strong>Read state aktif</strong><span>Action edit tidak ditampilkan karena capability write tidak diberikan kepada Admin Mitra.</span></div><div class="edit-state" data-edit-state hidden><strong>Edit state hanya simulasi</strong><span>Prototype membuka field untuk menguji hierarchy, tetapi tidak mengirim mutation dan tidak mengubah permission.</span></div></section></div>
            </main>
        </div>
    </section>

    <section class="variant" data-variant="c">
        <div class="c-canvas">
            <header class="c-hero"><div class="c-hero-inner"><div class="topbar" style="padding:0;min-height:0;border:0;background:transparent"><div class="brand" style="color:#fff">PMS<span>/THC</span></div><div class="topbar-meta" style="color:#d8e0f5"><span>Command Center / governance view</span><div class="avatar" style="background:#6775e2">AB</div></div></div><p class="eyebrow" style="margin-top:38px">Shared foundation / operational overview</p><h1>Master data command</h1><p class="subtle">Management list sebagai salah satu signal operasional, dengan scope dan capability tetap terlihat.</p><nav class="c-nav" aria-label="Variant C navigation"><button class="active" data-context="unit">Unit</button><button data-context="pop">PoP</button><button data-context="material">Material</button><button data-context="mitra">Mitra</button></nav></div></header>
            <main class="page c-page"><div class="signal-grid"><div class="signal"><span class="signal-label">Data set aktif</span><strong class="signal-value" data-metric-count>3</strong><span class="signal-note" data-page-title>Unit dalam scope</span></div><div class="signal"><span class="signal-label">Status aktif</span><strong class="signal-value">92%</strong><span class="signal-note good">+4% dari periode lalu</span></div><div class="signal"><span class="signal-label">Needs attention</span><strong class="signal-value">02</strong><span class="signal-note">Record perlu ditinjau</span></div><div class="signal"><span class="signal-label">Capability</span><strong class="signal-value" style="font-size:21px">Read</strong><span class="signal-note">Backend authoritative</span></div></div><div class="c-layout"><section class="card queue"><div class="queue-toolbar"><div><p class="eyebrow">Management queue</p><h2><span data-page-title>Unit</span> yang tersedia</h2></div><div class="filter-row"><input class="search" data-search placeholder="Cari..." aria-label="Cari data"><button class="btn" data-action="filter">Semua status</button></div></div><div class="queue-row queue-head"><div>Record</div><div>Scope</div><div>Status</div><div>Aksi</div></div><div class="dataset-queue"></div><div class="empty-state" hidden data-empty><strong>Queue kosong</strong>Belum ada record untuk filter ini.</div></section><aside class="c-side"><section class="card side-note"><p class="eyebrow">At a glance</p><h2>Design language yang sama</h2><p class="subtle">PageHeader, Card, StatusBadge, Search, EmptyState, dan action hierarchy tetap dikenali saat konteks berubah.</p><button class="btn btn-primary" data-action="contract">Review UI contract</button></section><section class="card side-note"><p class="eyebrow">Action boundary</p><ul class="mini-list"><li><span>Read master</span><strong class="contract-chip">Allowed</strong></li><li><span>Edit master</span><strong class="contract-chip" style="color:var(--red);background:var(--red-soft)">Not granted</strong></li><li><span>Tenant stock</span><strong class="contract-chip">Scoped</strong></li></ul></section></aside></div></main>
        </div>
    </section>

    <div class="state-strip" id="prototype-state"><strong>state</strong> loading...</div>
    <div class="toast" id="prototype-toast" role="status"></div>
    <nav class="switcher" aria-label="Prototype variants"><button id="previous" aria-label="Variant sebelumnya">‹</button><div class="switcher-label" id="variant-label">A - Workspace table<small>← → untuk berganti tampilan</small></div><button id="next" aria-label="Variant berikutnya">›</button></nav>

    <script>
        const variants = [
            { key: 'a', label: 'A - Workspace table', name: 'Sidebar + management list' },
            { key: 'b', label: 'B - Reference inspector', name: 'List/detail + edit/read state' },
            { key: 'c', label: 'C - Master data command', name: 'Signals + dense management queue' },
        ];
        const datasets = {
            unit: {
                title: 'Unit', description: 'Referensi satuan yang dipakai oleh Material dan alur operasional.', count: '3 Unit', rows: [
                    { code: 'UNT-001', name: 'Batang', detail: 'Material reference', status: 'Aktif', tone: 'green' },
                    { code: 'UNT-002', name: 'Pcs', detail: 'Material reference', status: 'Aktif', tone: 'green' },
                    { code: 'UNT-003', name: 'Meter', detail: 'Material reference', status: 'Aktif', tone: 'green' },
                ],
            },
            pop: {
                title: 'PoP', description: 'Point of Presence yang tersedia sebagai referensi Project.', count: '2 PoP', rows: [
                    { code: 'POP-001', name: 'PoP Rembige', detail: 'Shared project reference', status: 'Aktif', tone: 'green' },
                    { code: 'POP-002', name: 'PoP Mataram', detail: 'Shared project reference', status: 'Nonaktif', tone: 'gray' },
                ],
            },
            material: {
                title: 'Material', description: 'Master Material dan metadata tracking yang diizinkan untuk dibaca.', count: '4 Material', rows: [
                    { code: 'MAT-001', name: 'Kabel FO 12C', detail: 'Meter · Qty ledger', status: 'Aktif', tone: 'green' },
                    { code: 'MAT-002', name: 'Splitter 1:8', detail: 'Pcs · Serial', status: 'Aktif', tone: 'green' },
                    { code: 'MAT-003', name: 'Kabel FO 24C', detail: 'Meter · Drum', status: 'Aktif', tone: 'green' },
                    { code: 'MAT-004', name: 'Closure ODP', detail: 'Pcs · Qty ledger', status: 'Nonaktif', tone: 'gray' },
                ],
            },
            mitra: {
                title: 'Mitra', description: 'Daftar Mitra yang terlihat sesuai scope dan capability akun.', count: '2 Mitra', rows: [
                    { code: 'MTR-2608-0001', name: 'PT Nusantara Fiber', detail: 'Admin utama · read scope', status: 'Aktif', tone: 'green' },
                    { code: 'MTR-2608-0002', name: 'Jatim Kabel', detail: 'Admin utama · read scope', status: 'Aktif', tone: 'green' },
                ],
            },
        };
        let currentIndex = Math.max(0, variants.findIndex((variant) => variant.key === new URLSearchParams(window.location.search).get('variant')));
        let context = 'unit';
        let selected = 0;
        let editing = false;
        let lastAction = 'Prototype dimuat';
        const stateNode = document.getElementById('prototype-state');
        const toastNode = document.getElementById('prototype-toast');

        function activeVariant() { return variants[currentIndex].key; }
        function activeNodes() { return document.querySelector(`.variant[data-variant="${activeVariant()}"]`); }
        function dataset() { return datasets[context]; }
        function renderRows() {
            const root = activeNodes();
            const data = dataset();
            const query = root.querySelector('[data-search]')?.value.trim().toLowerCase() || '';
            const rows = data.rows.filter((row) => `${row.code} ${row.name} ${row.detail} ${row.status}`.toLowerCase().includes(query));
            const table = root.querySelector('.dataset-rows');
            if (table) table.innerHTML = rows.map((row) => `<tr><td><span class="code">${row.code}</span></td><td><div class="row-title">${row.name}</div></td><td><span class="row-meta">${row.detail}</span></td><td><span class="badge badge-${row.tone === 'green' ? 'green' : 'gray'}">${row.status}</span></td><td><button class="btn btn-link" data-record="${row.code}">Lihat</button></td></tr>`).join('');
            const records = root.querySelector('.dataset-records');
            if (records) records.innerHTML = rows.map((row, index) => `<button class="record ${index === selected ? 'selected' : ''}" data-record="${row.code}"><span><strong>${row.name}</strong><small>${row.code} · ${row.detail}</small></span><span class="badge badge-${row.tone === 'green' ? 'green' : 'gray'}">${row.status}</span></button>`).join('');
            const queue = root.querySelector('.dataset-queue');
            if (queue) queue.innerHTML = rows.map((row) => `<div class="queue-row"><div><div class="row-title">${row.name}</div><div class="row-meta"><span class="code">${row.code}</span> · ${row.detail}</div></div><div class="row-meta">Authorized</div><div><span class="badge badge-${row.tone === 'green' ? 'green' : 'gray'}">${row.status}</span></div><div><button class="btn btn-link" data-record="${row.code}">Open</button></div></div>`).join('');
            root.querySelectorAll('[data-empty]').forEach((node) => node.hidden = rows.length !== 0);
            root.querySelectorAll('[data-metric-count]').forEach((node) => node.textContent = data.count.split(' ')[0]);
            const activeRow = dataset().rows[selected] || dataset().rows[0];
            if (activeRow) {
                root.querySelector('[data-selected-name]')?.replaceChildren(document.createTextNode(activeRow.name));
                root.querySelector('[data-selected-code]')?.replaceChildren(document.createTextNode(activeRow.code));
                root.querySelector('[data-selected-status]')?.replaceChildren(document.createTextNode(activeRow.status));
                root.querySelector('[data-detail-code]')?.setAttribute('value', activeRow.code);
                root.querySelector('[data-detail-name]')?.setAttribute('value', activeRow.name);
            }
        }
        function renderContext() {
            const data = dataset();
            document.querySelectorAll('.variant.active [data-page-title]').forEach((node) => node.textContent = data.title);
            document.querySelectorAll('.variant.active [data-page-description]').forEach((node) => node.textContent = data.description);
            document.querySelectorAll('.variant.active [data-count]').forEach((node) => node.textContent = data.count);
            document.querySelectorAll('.variant.active [data-context]').forEach((node) => node.classList.toggle('active', node.dataset.context === context));
            renderRows();
        }
        function renderState() {
            const query = activeNodes().querySelector('[data-search]')?.value.trim() || '-';
            stateNode.innerHTML = `<strong>state</strong> variant=${activeVariant()} | context=${context} | search=${query} | selected=${dataset().rows[selected]?.code || '-'} | edit=${editing ? 'simulated' : 'read'} | last=${lastAction}`;
        }
        function setVariant(next) {
            currentIndex = (next + variants.length) % variants.length;
            document.querySelectorAll('.variant').forEach((node) => node.classList.toggle('active', node.dataset.variant === activeVariant()));
            const variant = variants[currentIndex];
            document.getElementById('variant-label').innerHTML = `${variant.label}<small>${variant.name}</small>`;
            const url = new URL(window.location.href);
            url.searchParams.set('variant', variant.key);
            window.history.replaceState({}, '', url);
            lastAction = `Beralih ke ${variant.label}`;
            renderContext();
            renderState();
        }
        function notify(message) {
            lastAction = message;
            renderState();
            toastNode.textContent = `Simulasi: ${message}`;
            toastNode.classList.add('show');
            window.clearTimeout(window.__prototypeToast);
            window.__prototypeToast = window.setTimeout(() => toastNode.classList.remove('show'), 2200);
        }
        document.getElementById('previous').addEventListener('click', () => setVariant(currentIndex - 1));
        document.getElementById('next').addEventListener('click', () => setVariant(currentIndex + 1));
        document.addEventListener('keydown', (event) => {
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName) || document.activeElement?.isContentEditable) return;
            if (event.key === 'ArrowLeft') setVariant(currentIndex - 1);
            if (event.key === 'ArrowRight') setVariant(currentIndex + 1);
        });
        document.addEventListener('input', (event) => {
            if (event.target.matches('[data-search]')) { renderRows(); renderState(); }
        });
        document.addEventListener('click', (event) => {
            const contextButton = event.target.closest('[data-context]');
            if (contextButton) { context = contextButton.dataset.context; selected = 0; editing = false; renderContext(); renderState(); return; }
            const recordButton = event.target.closest('[data-record]');
            if (recordButton) { selected = Math.max(0, dataset().rows.findIndex((row) => row.code === recordButton.dataset.record)); renderRows(); notify(`Record ${recordButton.dataset.record} dipilih`); return; }
            const actionButton = event.target.closest('[data-action]');
            if (!actionButton) return;
            if (actionButton.dataset.action === 'toggle-edit') {
                editing = !editing;
                const root = activeNodes();
                root.querySelector('[data-read-state]')?.toggleAttribute('hidden', editing);
                root.querySelector('[data-edit-state]')?.toggleAttribute('hidden', !editing);
                root.querySelectorAll('[data-detail-name], [data-detail-status]').forEach((node) => node.disabled = !editing);
                notify(editing ? 'Edit state dibuka (simulasi, tanpa mutation)' : 'Kembali ke read state');
            } else if (actionButton.dataset.action === 'capability') notify('Capability backend: read-only pada konteks ini');
            else if (actionButton.dataset.action === 'export') notify('Export view disiapkan (simulasi)');
            else if (actionButton.dataset.action === 'filter') notify('Filter status dibuka (simulasi)');
            else if (actionButton.dataset.action === 'contract') notify('UI contract dibuka (simulasi)');
        });
        setVariant(currentIndex);
    </script>
</body>
</html>
