{{--
    PROTOTYPE THROWAWAY — Gelombang 1.
    Question: should daily work centre on command, warehouse operations, or governance?
    Three read-only mock variants are switchable via ?variant=command|warehouse|governance.
    This route is intentionally not registered in production.
--}}
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prototype Gelombang 1 — Project Monitoring System</title>
    <style>
        :root {
            --ink: #17212b;
            --muted: #6d7b87;
            --line: #dbe3e7;
            --paper: #f4f7f8;
            --white: #fff;
            --navy: #15324b;
            --teal: #087f8c;
            --teal-soft: #dff3ef;
            --blue-soft: #e9f1fb;
            --amber: #a86314;
            --amber-soft: #fff1d7;
            --red: #b34444;
            --red-soft: #ffe4e2;
            --purple: #624b9b;
            --purple-soft: #efe9ff;
            --shadow: 0 14px 40px rgba(21, 50, 75, .09);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--ink);
            background: var(--paper);
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; }
        button, input, select { font: inherit; }
        button { cursor: pointer; }
        .prototype-ribbon { position: fixed; z-index: 20; top: 12px; right: 16px; background: #fdc858; color: #4d3200; padding: 7px 11px; border-radius: 999px; font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; box-shadow: 0 5px 16px rgba(77, 50, 0, .16); }
        .variant { display: none; min-height: 100vh; padding-bottom: 112px; }
        .variant.active { display: block; }
        .topbar { min-height: 72px; display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 0 30px; background: var(--white); border-bottom: 1px solid var(--line); }
        .brand { color: var(--navy); font-weight: 850; letter-spacing: -.05em; font-size: 20px; }
        .brand span { color: var(--teal); }
        .topbar-right, .inline { display: flex; align-items: center; gap: 12px; }
        .topbar-right { color: var(--muted); font-size: 13px; }
        .avatar { width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center; background: var(--navy); color: white; font-weight: 800; }
        .page { max-width: 1440px; margin: 0 auto; padding: 30px 30px 0; }
        .eyebrow { margin: 0 0 8px; color: var(--teal); font-size: 12px; font-weight: 850; letter-spacing: .11em; text-transform: uppercase; }
        h1, h2, h3, p { margin-top: 0; }
        h1 { margin-bottom: 8px; color: var(--navy); font-size: clamp(27px, 4vw, 43px); letter-spacing: -.06em; line-height: 1.03; }
        h2 { margin-bottom: 17px; color: var(--navy); font-size: 19px; letter-spacing: -.035em; }
        h3 { margin-bottom: 7px; font-size: 14px; }
        .subtle { color: var(--muted); font-size: 13px; line-height: 1.55; }
        .muted { color: var(--muted); }
        .card { border: 1px solid var(--line); border-radius: 15px; background: var(--white); box-shadow: var(--shadow); }
        .card-pad { padding: 21px; }
        .btn { border: 1px solid var(--line); border-radius: 9px; background: white; color: var(--navy); padding: 10px 14px; font-size: 13px; font-weight: 750; }
        .btn:hover { border-color: var(--teal); color: var(--teal); }
        .btn-primary { border-color: var(--teal); background: var(--teal); color: white; }
        .btn-primary:hover { color: white; background: #056c76; }
        .badge { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 5px 9px; font-size: 11px; font-weight: 850; white-space: nowrap; }
        .badge-green { color: #11664f; background: var(--teal-soft); }
        .badge-blue { color: #28598d; background: var(--blue-soft); }
        .badge-amber { color: var(--amber); background: var(--amber-soft); }
        .badge-red { color: var(--red); background: var(--red-soft); }
        .badge-purple { color: var(--purple); background: var(--purple-soft); }
        .grid { display: grid; gap: 17px; }
        .grid-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .grid-main { grid-template-columns: minmax(0, 1.55fr) minmax(290px, .75fr); align-items: start; }
        .section-head { display: flex; align-items: end; justify-content: space-between; gap: 16px; margin-bottom: 17px; }
        .section-head h2 { margin-bottom: 0; }
        .hero { display: flex; align-items: end; justify-content: space-between; gap: 25px; margin-bottom: 27px; }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 9px; }
        .kpi { padding: 19px; }
        .kpi-label { color: var(--muted); font-size: 12px; }
        .kpi-value { margin: 8px 0 4px; color: var(--navy); font-size: 30px; font-weight: 850; letter-spacing: -.06em; }
        .kpi-foot { color: var(--muted); font-size: 12px; }
        .delta-up { color: #178367; font-weight: 750; }
        .delta-warn { color: var(--amber); font-weight: 750; }
        .progress-label { display: flex; justify-content: space-between; gap: 10px; color: var(--muted); font-size: 12px; }
        .progress-line { height: 8px; margin-top: 7px; overflow: hidden; border-radius: 999px; background: #edf1f3; }
        .progress-line span { display: block; height: 100%; border-radius: inherit; background: var(--teal); }
        .progress-line.amber span { background: #e1a138; }
        .progress-line.red span { background: #d46c67; }
        .list { display: grid; gap: 0; }
        .list-row { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 13px 0; border-bottom: 1px solid var(--line); }
        .list-row:first-child { padding-top: 0; }
        .list-row:last-child { padding-bottom: 0; border-bottom: 0; }
        .list-title { margin-bottom: 4px; color: var(--navy); font-size: 13px; font-weight: 800; }
        .list-meta { color: var(--muted); font-size: 11px; }
        .dot { width: 9px; height: 9px; flex: 0 0 auto; border-radius: 50%; background: var(--teal); }
        .dot.amber { background: #e1a138; }
        .dot.red { background: #d46c67; }
        .dot.blue { background: #5b8fc6; }
        .dot.purple { background: #846ac2; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th { padding: 0 12px 11px; color: var(--muted); font-size: 10px; font-weight: 850; letter-spacing: .08em; text-align: left; text-transform: uppercase; }
        td { padding: 13px 12px; border-top: 1px solid var(--line); vertical-align: middle; }
        td:first-child, th:first-child { padding-left: 0; }
        td:last-child, th:last-child { padding-right: 0; text-align: right; }
        .code { color: var(--navy); font-weight: 850; letter-spacing: .02em; }
        .filter-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 18px; }
        .search { min-width: 210px; border: 1px solid var(--line); border-radius: 9px; padding: 10px 12px; color: var(--ink); background: #fbfcfc; }
        .search:focus { outline: 2px solid var(--teal-soft); border-color: var(--teal); }
        .side-nav { background: var(--navy); color: #d7e5ed; }
        .side-nav .brand { color: white; }
        .side-nav .brand span { color: #64d4d4; }
        .side-nav .nav-label { margin: 27px 0 8px; color: #7ea0b5; font-size: 10px; font-weight: 850; letter-spacing: .13em; text-transform: uppercase; }
        .side-nav a { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin: 2px -10px; padding: 10px; border-radius: 8px; color: #d7e5ed; font-size: 13px; text-decoration: none; }
        .side-nav a.active, .side-nav a:hover { background: #214760; color: white; }
        .side-nav .count { min-width: 20px; border-radius: 999px; padding: 3px 6px; color: #123245; background: #f6c968; font-size: 10px; font-weight: 850; text-align: center; }
        .command-shell { display: grid; grid-template-columns: 225px minmax(0, 1fr); min-height: calc(100vh - 72px); }
        .command-shell .side-nav { padding: 27px 20px; }
        .command-shell .page { width: 100%; padding-top: 30px; }
        .signal-card { position: relative; overflow: hidden; }
        .signal-card::before { position: absolute; top: 0; bottom: 0; left: 0; width: 4px; background: var(--teal); content: ""; }
        .signal-card.warn::before { background: #e1a138; }
        .signal-card.danger::before { background: #d46c67; }
        .signal-card .card-pad { padding-left: 25px; }
        .signal-icon { width: 35px; height: 35px; display: grid; place-items: center; border-radius: 10px; color: var(--teal); background: var(--teal-soft); font-weight: 900; }
        .signal-card.warn .signal-icon { color: var(--amber); background: var(--amber-soft); }
        .signal-card.danger .signal-icon { color: var(--red); background: var(--red-soft); }
        .signal-row { display: flex; align-items: start; gap: 11px; }
        .warehouse-top { padding: 26px 30px 0; background: var(--navy); color: white; }
        .warehouse-top .topline { max-width: 1380px; margin: 0 auto; padding-bottom: 26px; }
        .warehouse-top h1 { color: white; }
        .warehouse-top .eyebrow { color: #75d8d2; }
        .warehouse-top .subtle { color: #b8ced8; }
        .warehouse-tabs { display: flex; gap: 5px; max-width: 1380px; margin: 0 auto; }
        .warehouse-tabs button { border: 0; border-bottom: 3px solid transparent; padding: 13px 15px; color: #b8ced8; background: transparent; font-size: 13px; font-weight: 750; }
        .warehouse-tabs button.active, .warehouse-tabs button:hover { border-bottom-color: #75d8d2; color: white; }
        .warehouse-page { max-width: 1440px; margin: 0 auto; padding: 27px 30px 0; }
        .warehouse-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 23px; }
        .warehouse-kpi { display: flex; align-items: center; gap: 13px; padding: 15px 17px; border: 1px solid var(--line); border-radius: 12px; background: var(--white); }
        .warehouse-kpi strong { display: block; margin-bottom: 2px; color: var(--navy); font-size: 22px; letter-spacing: -.05em; }
        .warehouse-kpi span { color: var(--muted); font-size: 11px; }
        .warehouse-icon { width: 36px; height: 36px; display: grid; place-items: center; border-radius: 10px; color: var(--navy); background: var(--blue-soft); font-weight: 900; }
        .warehouse-icon.teal { color: var(--teal); background: var(--teal-soft); }
        .warehouse-icon.amber { color: var(--amber); background: var(--amber-soft); }
        .warehouse-icon.red { color: var(--red); background: var(--red-soft); }
        .warehouse-main { display: grid; grid-template-columns: minmax(0, 1.7fr) minmax(300px, .75fr); align-items: start; gap: 17px; }
        .warehouse-main .card { box-shadow: 0 8px 24px rgba(21, 50, 75, .06); }
        .stock-id { display: flex; align-items: center; gap: 9px; }
        .stock-id .mini { width: 30px; height: 30px; display: grid; place-items: center; border-radius: 8px; color: var(--teal); background: var(--teal-soft); font-size: 11px; font-weight: 900; }
        .timeline { display: grid; gap: 19px; }
        .timeline-row { position: relative; display: grid; grid-template-columns: 62px 15px minmax(0, 1fr); gap: 10px; }
        .timeline-row:not(:last-child)::after { position: absolute; top: 20px; bottom: -20px; left: 69px; width: 1px; background: var(--line); content: ""; }
        .timeline-time { color: var(--muted); font-size: 11px; line-height: 1.4; text-align: right; }
        .timeline-node { position: relative; z-index: 1; width: 15px; height: 15px; margin-top: 1px; border: 3px solid white; border-radius: 50%; background: var(--teal); box-shadow: 0 0 0 1px var(--teal); }
        .timeline-node.amber { background: #e1a138; box-shadow: 0 0 0 1px #e1a138; }
        .timeline-node.red { background: #d46c67; box-shadow: 0 0 0 1px #d46c67; }
        .timeline-copy strong { display: block; margin-bottom: 3px; color: var(--navy); font-size: 12px; }
        .timeline-copy span { color: var(--muted); font-size: 11px; line-height: 1.45; }
        .governance-shell { min-height: calc(100vh - 72px); background: #f0f2f8; }
        .governance-header { padding: 31px 30px 22px; border-bottom: 1px solid #dfe2ed; background: linear-gradient(120deg, #32294f, #624b9b); color: white; }
        .governance-header-inner { max-width: 1380px; margin: 0 auto; }
        .governance-header h1 { color: white; }
        .governance-header .eyebrow { color: #decfff; }
        .governance-header .subtle { color: #e2dff0; }
        .governance-nav { display: flex; flex-wrap: wrap; gap: 9px; margin-top: 20px; }
        .governance-nav span { border: 1px solid rgba(255,255,255,.22); border-radius: 999px; padding: 7px 11px; color: #eee9ff; font-size: 11px; }
        .governance-nav span.active { border-color: white; color: #332950; background: white; }
        .governance-page { max-width: 1440px; margin: 0 auto; padding: 25px 30px 0; }
        .governance-layout { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(330px, .8fr); gap: 17px; align-items: start; }
        .matrix { display: grid; grid-template-columns: minmax(160px, 1.1fr) repeat(4, minmax(65px, .6fr)); align-items: center; }
        .matrix > div { min-height: 47px; display: flex; align-items: center; padding: 10px 8px; border-bottom: 1px solid var(--line); color: var(--muted); font-size: 11px; }
        .matrix > div:nth-child(-n+5) { min-height: 37px; border-bottom: 0; color: var(--purple); font-size: 10px; font-weight: 850; letter-spacing: .06em; text-transform: uppercase; }
        .matrix > div:nth-child(5n + 1) { padding-left: 0; color: var(--navy); font-size: 12px; font-weight: 800; }
        .matrix > div:not(:nth-child(5n + 1)) { justify-content: center; text-align: center; }
        .check { width: 20px; height: 20px; display: grid; place-items: center; border-radius: 6px; color: #247461; background: var(--teal-soft); font-size: 13px; font-weight: 900; }
        .check.off { color: #a9b1bb; background: #f0f2f3; }
        .master-list { display: grid; gap: 12px; }
        .master-item { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding-bottom: 12px; border-bottom: 1px solid var(--line); }
        .master-item:last-child { padding-bottom: 0; border-bottom: 0; }
        .master-mark { width: 34px; height: 34px; display: grid; place-items: center; border-radius: 9px; color: var(--purple); background: var(--purple-soft); font-size: 11px; font-weight: 900; }
        .master-item strong { display: block; margin-bottom: 3px; color: var(--navy); font-size: 12px; }
        .master-item span { color: var(--muted); font-size: 11px; }
        .empty-note { padding: 12px; border-radius: 10px; color: var(--purple); background: var(--purple-soft); font-size: 12px; line-height: 1.45; }
        .state-strip { position: fixed; z-index: 12; right: 18px; bottom: 83px; max-width: min(520px, calc(100vw - 36px)); padding: 9px 13px; border: 1px solid rgba(21,50,75,.12); border-radius: 10px; color: var(--navy); background: rgba(255,255,255,.94); box-shadow: 0 8px 26px rgba(21,50,75,.11); font-size: 11px; }
        .state-strip strong { margin-right: 7px; color: var(--teal); }
        .switcher { position: fixed; z-index: 15; bottom: 17px; left: 50%; display: flex; align-items: center; gap: 12px; transform: translateX(-50%); padding: 8px; border: 1px solid #294862; border-radius: 999px; color: white; background: #17354d; box-shadow: 0 12px 30px rgba(13, 35, 51, .26); }
        .switcher button { width: 34px; height: 34px; border: 0; border-radius: 50%; color: white; background: #2c566f; font-size: 24px; line-height: 1; }
        .switcher button:hover { background: var(--teal); }
        .switcher-label { min-width: 185px; color: #f5fbfc; font-size: 12px; font-weight: 800; text-align: center; }
        .switcher-label small { display: block; margin-top: 2px; color: #a9c3cf; font-size: 10px; font-weight: 500; }
        .toast { position: fixed; z-index: 30; right: 20px; top: 20px; max-width: 320px; padding: 12px 15px; border-radius: 10px; color: white; background: #15324b; box-shadow: var(--shadow); font-size: 12px; opacity: 0; transform: translateY(-8px); pointer-events: none; transition: .2s ease; }
        .toast.show { opacity: 1; transform: translateY(0); }
        @media (max-width: 1000px) {
            .grid-4, .warehouse-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .grid-main, .warehouse-main, .governance-layout { grid-template-columns: 1fr; }
            .command-shell { grid-template-columns: 1fr; }
            .command-shell .side-nav { display: none; }
            .governance-layout { gap: 17px; }
        }
        @media (max-width: 650px) {
            .topbar, .page, .warehouse-top, .warehouse-page, .governance-header, .governance-page { padding-left: 16px; padding-right: 16px; }
            .topbar { min-height: 64px; }
            .topbar-right > span { display: none; }
            .hero, .section-head, .filter-bar { align-items: start; flex-direction: column; }
            .grid-4, .grid-3, .warehouse-kpis { grid-template-columns: 1fr; }
            .warehouse-tabs { overflow-x: auto; }
            .warehouse-tabs button { white-space: nowrap; }
            .search { width: 100%; }
            .switcher { width: calc(100vw - 28px); justify-content: space-between; }
            .switcher-label { min-width: 0; flex: 1; }
            .state-strip { right: 14px; bottom: 82px; }
            .matrix { min-width: 520px; }
            .matrix-wrap { overflow-x: auto; }
        }
    </style>
</head>
<body>
    <div class="prototype-ribbon">Prototype · Gelombang 1</div>
    <div class="toast" id="toast" role="status" aria-live="polite"></div>

    <section class="variant active" data-variant="command" data-state="4 alert penting · 7 approval tertunda · 2 gudang aktif · stok sehat 92%">
        <header class="topbar">
            <div class="brand">THC<span>/</span>PMS</div>
            <div class="topbar-right"><span>Sabtu, 15 Agustus 2026</span><span class="avatar">TA</span><strong style="color:var(--navy)">THC Admin</strong></div>
        </header>
        <div class="command-shell">
            <aside class="side-nav">
                <div class="brand">THC<span>/</span>PMS</div>
                <div class="nav-label">Workspace</div>
                <a class="active" href="#" data-action="noop">Command center <span class="count">4</span></a>
                <a href="#" data-action="noop">Request Material <span class="count">3</span></a>
                <a href="#" data-action="noop">Surat Jalan <span class="count">2</span></a>
                <div class="nav-label">Data</div>
                <a href="#" data-action="noop">Stok Material</a>
                <a href="#" data-action="noop">Master Data</a>
                <a href="#" data-action="noop">User &amp; Grup</a>
                <div class="nav-label">Sistem</div>
                <a href="#" data-action="noop">Audit log</a>
                <a href="#" data-action="noop">Pengaturan</a>
            </aside>
            <main class="page">
                <div class="hero">
                    <div><p class="eyebrow">Varian A · Command center</p><h1>Selamat pagi, THC.</h1><p class="subtle" style="max-width:580px;margin-bottom:0">Satu layar untuk melihat hal yang membutuhkan keputusan, sebelum operasional gudang terhambat.</p></div>
                    <div class="hero-actions"><button class="btn" data-action="export">Export ringkasan</button><button class="btn btn-primary" data-action="onboard">＋ Onboarding Mitra</button></div>
                </div>
                <div class="grid grid-4" style="margin-bottom:21px">
                    <div class="card kpi"><div class="kpi-label">Request perlu keputusan</div><div class="kpi-value">3</div><div class="kpi-foot"><span class="delta-warn">▲ 1</span> sejak kemarin</div></div>
                    <div class="card kpi"><div class="kpi-label">Material dalam transit</div><div class="kpi-value">Rp 184 jt</div><div class="kpi-foot"><span class="delta-warn">2 surat jalan</span> lewat SLA</div></div>
                    <div class="card kpi"><div class="kpi-label">Stok kritis</div><div class="kpi-value">7 item</div><div class="kpi-foot"><span class="delta-up">92%</span> katalog sehat</div></div>
                    <div class="card kpi"><div class="kpi-label">User aktif</div><div class="kpi-value">24</div><div class="kpi-foot"><span class="delta-up">+2 mitra</span> bulan ini</div></div>
                </div>
                <div class="grid grid-main">
                    <div class="grid" style="gap:17px">
                        <section class="card card-pad"><div class="section-head"><div><h2>Yang membutuhkan perhatian</h2><p class="subtle" style="margin-bottom:0">Urutan berdasarkan risiko operasional, bukan waktu masuk.</p></div><button class="btn" data-action="all-alerts">Lihat semua</button></div><div class="grid grid-3">
                            <div class="card signal-card warn"><div class="card-pad"><div class="signal-row"><div class="signal-icon">!</div><div><h3>SJ-2608-0041</h3><p class="subtle" style="margin-bottom:10px">Transit 4 hari menuju Gudang Sidoarjo.</p><span class="badge badge-amber">Perlu resolusi</span></div></div></div></div>
                            <div class="card signal-card danger"><div class="card-pad"><div class="signal-row"><div class="signal-icon">↓</div><div><h3>Kabel FO 48C</h3><p class="subtle" style="margin-bottom:10px">Saldo 8% dari batas minimum di G-TMK.</p><span class="badge badge-red">Stok kritis</span></div></div></div></div>
                            <div class="card signal-card"><div class="card-pad"><div class="signal-row"><div class="signal-icon">✓</div><div><h3>3 approval mitra</h3><p class="subtle" style="margin-bottom:10px">Harga jasa menunggu keputusan THC.</p><span class="badge badge-blue">Review hari ini</span></div></div></div></div>
                        </div></section>
                        <section class="card card-pad"><div class="section-head"><div><h2>Aktivitas lintas operasional</h2><p class="subtle" style="margin-bottom:0">Perubahan terakhir dari user THC dan Mitra.</p></div><button class="btn" data-action="filter">Filter</button></div><div class="list">
                            <div class="list-row"><div class="inline"><span class="dot amber"></span><div><div class="list-title">Request Material MR-2608-0087 diajukan</div><div class="list-meta">PT Nusantara Kabel · 12 menit lalu</div></div></div><span class="badge badge-amber">Menunggu</span></div>
                            <div class="list-row"><div class="inline"><span class="dot"></span><div><div class="list-title">Surat Jalan SJ-2608-0042 diterima</div><div class="list-meta">Gudang THC → Gudang Gresik · 38 menit lalu</div></div></div><span class="badge badge-green">Selesai</span></div>
                            <div class="list-row"><div class="inline"><span class="dot blue"></span><div><div class="list-title">User baru dibuat untuk PT Sinar Jaya</div><div class="list-meta">Oleh THC Admin · 1 jam lalu</div></div></div><span class="badge badge-blue">User</span></div>
                            <div class="list-row"><div class="inline"><span class="dot purple"></span><div><div class="list-title">Master pekerjaan jasa diperbarui</div><div class="list-meta">Penarikan kabel 24C · 2 jam lalu</div></div></div><span class="badge badge-purple">Master</span></div>
                        </div></section>
                    </div>
                    <aside class="grid" style="gap:17px">
                        <section class="card card-pad"><h2>Kesiapan gudang</h2><div class="list"><div class="list-row"><div><div class="list-title">Gudang THC Waru</div><div class="list-meta">6 petugas · 1 transit aktif</div></div><strong style="color:#178367">96%</strong></div><div class="list-row"><div><div class="list-title">Gudang Mitra Sidoarjo</div><div class="list-meta">3 petugas · 4 transit aktif</div></div><strong style="color:var(--amber)">87%</strong></div></div></section>
                        <section class="card card-pad"><h2>Distribusi pekerjaan hari ini</h2><div style="margin-bottom:16px"><div class="progress-label"><span>Request Material</span><strong>12</strong></div><div class="progress-line"><span style="width:76%"></span></div></div><div style="margin-bottom:16px"><div class="progress-label"><span>Surat Jalan</span><strong>8</strong></div><div class="progress-line amber"><span style="width:51%"></span></div></div><div><div class="progress-label"><span>Master &amp; akses</span><strong>5</strong></div><div class="progress-line"><span style="width:34%"></span></div></div></section>
                        <section class="card card-pad"><h2>Keputusan desain yang diuji</h2><p class="subtle" style="margin-bottom:14px">Apakah angka agregat membantu THC memilih tindakan berikutnya, atau justru menyembunyikan detail gudang?</p><button class="btn btn-primary" style="width:100%" data-action="note">Catat preferensi</button></section>
                    </aside>
                </div>
            </main>
        </div>
    </section>

    <section class="variant" data-variant="warehouse" data-state="gudang aktif: Waru · 1.248 saldo · 2 transit terlambat · 6 transaksi hari ini">
        <header class="topbar">
            <div class="brand">THC<span>/</span>PMS</div>
            <div class="topbar-right"><span>Mode operasional gudang</span><span class="avatar">RW</span><strong style="color:var(--navy)">Rina · Petugas Gudang</strong></div>
        </header>
        <div class="warehouse-top">
            <div class="topline">
                <div><p class="eyebrow">Varian B · Warehouse desk</p><h1>Gudang THC Waru</h1><p class="subtle" style="margin-bottom:0">Semua yang petugas butuhkan untuk mencatat barang masuk, keluar, dan transit.</p></div>
                <div class="hero-actions"><button class="btn" data-action="scan">▣ Scan QR</button><button class="btn btn-primary" data-action="receive">＋ Barang masuk</button></div>
            </div>
            <div class="warehouse-tabs"><button class="active" data-action="stock">Saldo stok</button><button data-action="inbound">Barang masuk</button><button data-action="outbound">Barang keluar</button><button data-action="transit">Transit <span class="badge badge-amber" style="margin-left:4px;padding:3px 6px">2</span></button></div>
        </div>
        <main class="warehouse-page">
            <div class="warehouse-kpis"><div class="warehouse-kpi"><div class="warehouse-icon teal">▦</div><div><strong>1.248</strong><span>SKU punya saldo</span></div></div><div class="warehouse-kpi"><div class="warehouse-icon amber">→</div><div><strong>6</strong><span>Transaksi hari ini</span></div></div><div class="warehouse-kpi"><div class="warehouse-icon red">!</div><div><strong>2</strong><span>Transit lewat SLA</span></div></div><div class="warehouse-kpi"><div class="warehouse-icon">◷</div><div><strong>98,7%</strong><span>Saldo tersinkron</span></div></div></div>
            <div class="warehouse-main">
                <section class="card card-pad"><div class="section-head"><div><h2>Saldo stok</h2><p class="subtle" style="margin-bottom:0">Waru · saldo dihitung dari buku transaksi.</p></div><button class="btn" data-action="reconcile">Rekonsiliasi</button></div><div class="filter-bar"><input class="search" type="search" placeholder="Cari material, SN, atau drum…" aria-label="Cari saldo stok"><div class="inline"><span class="badge badge-blue">Semua jenis</span><span class="badge badge-green">Sehat</span></div></div><div class="table-wrap"><table><thead><tr><th>Material</th><th>Jenis</th><th>Saldo</th><th>Lokasi</th><th>Status</th><th>Aksi</th></tr></thead><tbody><tr><td><div class="stock-id"><span class="mini">FO</span><div><div class="code">Kabel FO 48C</div><div class="muted">MAT-00048 · meter</div></div></div></td><td>Biasa</td><td><strong>12.480 m</strong></td><td>Rak A-04</td><td><span class="badge badge-green">Sehat</span></td><td><button class="btn" data-action="detail">Detail</button></td></tr><tr><td><div class="stock-id"><span class="mini">SN</span><div><div class="code">ODP 24 Port</div><div class="muted">MAT-00112 · pcs</div></div></div></td><td>Ber-SN</td><td><strong>84 pcs</strong></td><td>Rak B-01</td><td><span class="badge badge-green">Sehat</span></td><td><button class="btn" data-action="detail">Detail</button></td></tr><tr><td><div class="stock-id"><span class="mini">DR</span><div><div class="code">Drum Kabel FO 96C</div><div class="muted">DRM-00042 · 1.020 m</div></div></div></td><td>Drum</td><td><strong>2 drum</strong></td><td>Area Drum</td><td><span class="badge badge-amber">Pantau</span></td><td><button class="btn" data-action="detail">Detail</button></td></tr><tr><td><div class="stock-id"><span class="mini">FO</span><div><div class="code">Kabel FO 24C</div><div class="muted">MAT-00024 · meter</div></div></div></td><td>Biasa</td><td><strong>320 m</strong></td><td>Rak A-02</td><td><span class="badge badge-red">Kritis</span></td><td><button class="btn" data-action="issue">Keluarkan</button></td></tr></tbody></table></div></section>
                <aside class="grid" style="gap:17px"><section class="card card-pad"><div class="section-head"><h2>Jalur cepat</h2><span class="badge badge-blue">6 hari ini</span></div><div class="grid" style="grid-template-columns:1fr 1fr;gap:9px"><button class="btn" style="padding:16px 10px;text-align:left" data-action="receive"><strong style="display:block;color:var(--navy)">＋ Terima</strong><span class="muted" style="font-size:11px">Barang masuk</span></button><button class="btn" style="padding:16px 10px;text-align:left" data-action="issue"><strong style="display:block;color:var(--navy)">↑ Keluarkan</strong><span class="muted" style="font-size:11px">Ke project</span></button><button class="btn" style="padding:16px 10px;text-align:left" data-action="split"><strong style="display:block;color:var(--navy)">✂ Potong drum</strong><span class="muted" style="font-size:11px">Buat turunan</span></button><button class="btn" style="padding:16px 10px;text-align:left" data-action="transfer"><strong style="display:block;color:var(--navy)">→ Surat jalan</strong><span class="muted" style="font-size:11px">Pindah gudang</span></button></div></section><section class="card card-pad"><h2>Transit perlu dilihat</h2><div class="timeline"><div class="timeline-row"><div class="timeline-time">4 hari</div><span class="timeline-node red"></span><div class="timeline-copy"><strong>SJ-2608-0041 · ke Sidoarjo</strong><span>12 drum · penerima belum konfirmasi</span></div></div><div class="timeline-row"><div class="timeline-time">2 hari</div><span class="timeline-node amber"></span><div class="timeline-copy"><strong>SJ-2608-0038 · ke Gresik</strong><span>84 ODP · menunggu scan penerimaan</span></div></div><div class="timeline-row"><div class="timeline-time">Hari ini</div><span class="timeline-node"></span><div class="timeline-copy"><strong>SJ-2608-0042 · dari THC pusat</strong><span>Material sudah diterima lengkap</span></div></div></div><button class="btn" style="width:100%;margin-top:18px" data-action="transit">Buka semua transit</button></section></aside>
            </div>
        </main>
    </section>

    <section class="variant" data-variant="governance" data-state="24 user aktif · 6 grup · 8 entitas master · 3 approval harga jasa">
        <header class="topbar">
            <div class="brand">THC<span>/</span>PMS</div>
            <div class="topbar-right"><span>Administrasi &amp; governance</span><span class="avatar">TA</span><strong style="color:var(--navy)">THC Admin</strong></div>
        </header>
        <div class="governance-shell">
            <header class="governance-header"><div class="governance-header-inner"><p class="eyebrow">Varian C · Governance cockpit</p><h1>Atur sistem sekali, aman setiap hari.</h1><p class="subtle" style="max-width:650px;margin-bottom:0">Akses user, grup, master data, dan approval dikumpulkan dalam satu ruang kerja THC.</p><div class="governance-nav"><span class="active">Ringkasan</span><span>User &amp; Grup</span><span>Master Data</span><span>Harga Jasa</span><span>Gudang</span><span>Audit</span></div></div></header>
            <main class="governance-page"><div class="governance-layout"><div class="grid" style="gap:17px"><section class="card card-pad"><div class="section-head"><div><h2>Matriks akses aktif</h2><p class="subtle" style="margin-bottom:0">Preview izin berdasarkan Grup. Centang hanya simulasi.</p></div><button class="btn btn-primary" data-action="new-group">＋ Grup baru</button></div><div class="matrix-wrap"><div class="matrix"><div>Grup</div><div>Dashboard</div><div>Gudang</div><div>Master</div><div>Approve</div><div>THC Admin</div><div><span class="check">✓</span></div><div><span class="check">✓</span></div><div><span class="check">✓</span></div><div><span class="check">✓</span></div><div>Petugas Gudang</div><div><span class="check">✓</span></div><div><span class="check">✓</span></div><div><span class="check off">–</span></div><div><span class="check off">–</span></div><div>Admin Mitra</div><div><span class="check">✓</span></div><div><span class="check off">–</span></div><div><span class="check off">–</span></div><div><span class="check">✓</span></div><div>Waspang Mitra</div><div><span class="check">✓</span></div><div><span class="check off">–</span></div><div><span class="check off">–</span></div><div><span class="check off">–</span></div></div></div></section><section class="card card-pad"><div class="section-head"><div><h2>Approval yang tertunda</h2><p class="subtle" style="margin-bottom:0">Keputusan THC yang berdampak ke Mitra.</p></div><span class="badge badge-amber">3 pending</span></div><div class="list"><div class="list-row"><div><div class="list-title">Harga Penarikan Kabel 48C</div><div class="list-meta">PT Nusantara Kabel · diajukan 2 jam lalu</div></div><button class="btn" data-action="review">Review</button></div><div class="list-row"><div><div class="list-title">Harga Terminasi ODP</div><div class="list-meta">PT Sinar Jaya · diajukan kemarin</div></div><button class="btn" data-action="review">Review</button></div><div class="list-row"><div><div class="list-title">Onboarding Mitra Baru</div><div class="list-meta">CV Jaringan Timur · data lengkap</div></div><button class="btn" data-action="review">Review</button></div></div></section></div><aside class="grid" style="gap:17px"><section class="card card-pad"><div class="section-head"><h2>Master data</h2><button class="btn" data-action="master">Kelola</button></div><div class="master-list"><div class="master-item"><div class="inline"><span class="master-mark">MAT</span><div><strong>Material</strong><span>248 aktif · 12 nonaktif</span></div></div><span class="badge badge-green">Sinkron</span></div><div class="master-item"><div class="inline"><span class="master-mark">UNT</span><div><strong>Unit</strong><span>9 aktif · 0 nonaktif</span></div></div><span class="badge badge-green">Sinkron</span></div><div class="master-item"><div class="inline"><span class="master-mark">POP</span><div><strong>PoP</strong><span>138 aktif · 4 nonaktif</span></div></div><span class="badge badge-blue">Perlu cek</span></div><div class="master-item"><div class="inline"><span class="master-mark">JS</span><div><strong>Pekerjaan Jasa</strong><span>42 aktif · 3 nonaktif</span></div></div><span class="badge badge-green">Sinkron</span></div></div></section><section class="card card-pad"><h2>Onboarding Mitra</h2><p class="subtle">Satu alur membuat Mitra, PKS, dan user admin-mitra; password awal dikirim otomatis melalui WA gateway.</p><div class="empty-note">2 onboarding selesai bulan ini · 1 menunggu validasi nomor WA.</div><button class="btn btn-primary" style="width:100%;margin-top:14px" data-action="onboard">＋ Mulai onboarding</button></section><section class="card card-pad"><h2>Keputusan desain yang diuji</h2><p class="subtle" style="margin-bottom:14px">Apakah pengelolaan akses dan master sebaiknya menjadi cockpit tersendiri, atau cukup muncul sebagai tugas di command center?</p><button class="btn" style="width:100%" data-action="note">Catat preferensi</button></section></aside></div></main>
        </div>
    </section>

    <div class="state-strip"><strong>State mock:</strong><span id="state-value">4 alert penting · 7 approval tertunda · 2 gudang aktif · stok sehat 92%</span></div>
    <nav class="switcher" aria-label="Prototype variants"><button id="prev" aria-label="Variant sebelumnya">‹</button><div class="switcher-label" id="variant-label">A — Command center<small>← → untuk berganti tampilan</small></div><button id="next" aria-label="Variant berikutnya">›</button></nav>

    <script>
        const variants = [
            { key: 'command', label: 'A — Command center', name: 'Ringkasan keputusan THC' },
            { key: 'warehouse', label: 'B — Warehouse desk', name: 'Meja operasional gudang' },
            { key: 'governance', label: 'C — Governance cockpit', name: 'Akses, master, dan approval' },
        ];
        const initial = new URLSearchParams(window.location.search).get('variant');
        let index = Math.max(0, variants.findIndex((variant) => variant.key === initial));
        const toast = document.getElementById('toast');
        function setVariant(nextIndex) {
            index = (nextIndex + variants.length) % variants.length;
            const variant = variants[index];
            document.querySelectorAll('.variant').forEach((node) => node.classList.toggle('active', node.dataset.variant === variant.key));
            document.getElementById('variant-label').innerHTML = `${variant.label}<small>${variant.name}</small>`;
            document.getElementById('state-value').textContent = document.querySelector(`[data-variant="${variant.key}"]`).dataset.state;
            const url = new URL(window.location.href);
            url.searchParams.set('variant', variant.key);
            window.history.replaceState({}, '', url);
        }
        function notify(message) {
            toast.textContent = message;
            toast.classList.add('show');
            window.clearTimeout(window.__toastTimer);
            window.__toastTimer = window.setTimeout(() => toast.classList.remove('show'), 2300);
        }
        const messages = {
            'all-alerts': 'Simulasi: daftar seluruh alert dibuka.',
            export: 'Simulasi: ringkasan Gelombang 1 diekspor.',
            onboard: 'Simulasi: wizard onboarding Mitra dibuka.',
            filter: 'Simulasi: filter aktivitas dibuka.',
            note: 'Simulasi: preferensi desain dicatat sementara di memori.',
            scan: 'Simulasi: kamera scanner QR dibuka.',
            receive: 'Simulasi: formulir barang masuk dibuka.',
            stock: 'Simulasi: tab saldo stok dipilih.',
            inbound: 'Simulasi: tab barang masuk dipilih.',
            outbound: 'Simulasi: tab barang keluar dipilih.',
            transit: 'Simulasi: daftar transit dibuka.',
            reconcile: 'Simulasi: rekonsiliasi stok dibuka.',
            detail: 'Simulasi: buku transaksi material dibuka.',
            issue: 'Simulasi: formulir pengeluaran material dibuka.',
            split: 'Simulasi: alur potong drum dibuka.',
            transfer: 'Simulasi: formulir Surat Jalan dibuka.',
            'new-group': 'Simulasi: editor Grup dan Izin Aksi dibuka.',
            review: 'Simulasi: detail approval dibuka.',
            master: 'Simulasi: katalog master data dibuka.',
            noop: 'Simulasi: navigasi prototype tidak mengubah data.',
        };
        document.getElementById('prev').addEventListener('click', () => setVariant(index - 1));
        document.getElementById('next').addEventListener('click', () => setVariant(index + 1));
        document.addEventListener('keydown', (event) => {
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName) || document.activeElement?.isContentEditable) return;
            if (event.key === 'ArrowLeft') setVariant(index - 1);
            if (event.key === 'ArrowRight') setVariant(index + 1);
        });
        document.querySelectorAll('[data-action]').forEach((element) => element.addEventListener('click', (event) => {
            event.preventDefault();
            notify(messages[element.dataset.action] || 'Simulasi aksi prototype.');
        }));
        setVariant(index);
    </script>
</body>
</html>
