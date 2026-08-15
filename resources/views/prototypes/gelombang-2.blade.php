{{--
    PROTOTYPE THROWAWAY — Gelombang 2.
    Three variants of a Project detail page, switchable via ?variant=control|field|ledger.
    Mock data is intentionally local and read-only. This route is not registered in production.
--}}
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prototype Gelombang 2 — Project Monitoring System</title>
    <style>
        :root {
            --ink: #17212b;
            --muted: #687684;
            --line: #d9e0e5;
            --paper: #f4f7f8;
            --white: #fff;
            --navy: #15324b;
            --teal: #087f8c;
            --mint: #dff3ed;
            --amber: #a86314;
            --amber-bg: #fff1d7;
            --red: #b34444;
            --red-bg: #ffe2e1;
            --shadow: 0 14px 40px rgba(21, 50, 75, .09);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--ink);
            background: var(--paper);
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; }
        button, input, textarea { font: inherit; }
        button { cursor: pointer; }
        .prototype-ribbon { position: fixed; z-index: 20; top: 12px; right: 16px; background: #fdc858; color: #4d3200; padding: 7px 11px; border-radius: 999px; font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; box-shadow: 0 5px 16px rgba(77, 50, 0, .16); }
        .shell { min-height: 100vh; }
        .topbar { min-height: 74px; display: flex; align-items: center; justify-content: space-between; padding: 0 30px; background: var(--white); border-bottom: 1px solid var(--line); }
        .brand { color: var(--navy); font-weight: 850; letter-spacing: -.04em; font-size: 20px; }
        .brand span { color: var(--teal); }
        .topbar-right { display: flex; align-items: center; gap: 16px; color: var(--muted); font-size: 13px; }
        .avatar { width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center; background: var(--navy); color: white; font-weight: 800; }
        .page { max-width: 1440px; margin: 0 auto; padding: 30px 30px 120px; }
        .eyebrow { margin: 0 0 8px; color: var(--teal); font-size: 12px; font-weight: 850; letter-spacing: .11em; text-transform: uppercase; }
        h1, h2, h3, p { margin-top: 0; }
        h1 { margin-bottom: 8px; color: var(--navy); font-size: clamp(26px, 4vw, 42px); letter-spacing: -.06em; line-height: 1.02; }
        h2 { margin-bottom: 18px; color: var(--navy); font-size: 20px; letter-spacing: -.03em; }
        h3 { margin-bottom: 7px; font-size: 14px; }
        .subtle { color: var(--muted); font-size: 13px; line-height: 1.55; }
        .topline { display: flex; justify-content: space-between; gap: 25px; align-items: flex-end; margin-bottom: 26px; }
        .topline-actions { display: flex; gap: 9px; flex-wrap: wrap; }
        .btn { border: 1px solid var(--line); border-radius: 9px; background: white; color: var(--navy); padding: 10px 14px; font-size: 13px; font-weight: 750; }
        .btn:hover { border-color: var(--teal); color: var(--teal); }
        .btn-primary { border-color: var(--teal); background: var(--teal); color: white; }
        .btn-primary:hover { color: white; background: #056c76; }
        .badge { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 5px 9px; font-size: 11px; font-weight: 850; }
        .badge-green { color: #11664f; background: var(--mint); }
        .badge-amber { color: var(--amber); background: var(--amber-bg); }
        .badge-red { color: var(--red); background: var(--red-bg); }
        .card { border: 1px solid var(--line); border-radius: 15px; background: var(--white); box-shadow: var(--shadow); }
        .card-pad { padding: 21px; }
        .grid { display: grid; gap: 17px; }
        .grid-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .grid-main { grid-template-columns: minmax(0, 1.55fr) minmax(290px, .75fr); align-items: start; }
        .kpi-label { color: var(--muted); font-size: 12px; }
        .kpi-value { margin: 7px 0 3px; color: var(--navy); font-size: 29px; font-weight: 850; letter-spacing: -.06em; }
        .delta { color: #178367; font-size: 12px; font-weight: 750; }
        .chart-wrap { padding: 22px 22px 15px; }
        .chart-head { display: flex; justify-content: space-between; gap: 15px; align-items: start; margin-bottom: 14px; }
        .legend { display: flex; gap: 12px; flex-wrap: wrap; color: var(--muted); font-size: 11px; }
        .legend i { display: inline-block; width: 18px; height: 3px; margin-right: 5px; vertical-align: middle; background: var(--teal); }
        .legend .plan { background: #9daab2; border-top: 1px dashed #9daab2; }
        .legend .pending { background: #d79c38; border-top: 1px dashed #d79c38; }
        svg.chart { display: block; width: 100%; height: 270px; overflow: visible; }
        .chart-grid { stroke: #e8edef; stroke-width: 1; }
        .chart-axis { fill: #8a979f; font-size: 10px; }
        .chart-plan { fill: none; stroke: #9daab2; stroke-width: 2; stroke-dasharray: 5 5; }
        .chart-actual { fill: none; stroke: var(--teal); stroke-width: 4; stroke-linecap: round; stroke-linejoin: round; }
        .chart-pending { fill: none; stroke: #d79c38; stroke-width: 3; stroke-dasharray: 3 6; stroke-linecap: round; }
        .metric-callout { display: flex; align-items: center; gap: 16px; }
        .metric-ring { width: 82px; height: 82px; border-radius: 50%; display: grid; place-items: center; background: conic-gradient(var(--teal) 0 94%, #e5eef0 94%); position: relative; }
        .metric-ring::after { content: ""; position: absolute; inset: 8px; border-radius: 50%; background: white; }
        .metric-ring strong { z-index: 1; color: var(--navy); font-size: 18px; }
        .timeline { position: relative; padding-left: 21px; }
        .timeline::before { position: absolute; content: ""; left: 5px; top: 5px; bottom: 5px; border-left: 1px solid var(--line); }
        .event { position: relative; margin: 0 0 20px; }
        .event::before { position: absolute; content: ""; left: -20px; top: 4px; width: 9px; height: 9px; border-radius: 50%; border: 2px solid white; background: var(--teal); box-shadow: 0 0 0 1px var(--teal); }
        .event.internal::before { background: var(--amber); box-shadow: 0 0 0 1px var(--amber); }
        .event time { display: block; margin-bottom: 3px; color: var(--muted); font-size: 11px; }
        .event p { margin: 0; font-size: 13px; line-height: 1.45; }
        .step-row { display: flex; gap: 0; overflow: auto; padding: 4px 0 5px; }
        .step { min-width: 105px; flex: 1; position: relative; padding: 28px 7px 0; color: var(--muted); text-align: center; font-size: 11px; }
        .step::before { content: ""; position: absolute; top: 8px; left: 0; right: 0; height: 3px; background: #dbe3e6; }
        .step:first-child::before { left: 50%; }
        .step:last-child::before { right: 50%; }
        .step::after { content: ""; position: absolute; z-index: 1; top: 2px; left: calc(50% - 8px); width: 12px; height: 12px; border-radius: 50%; border: 2px solid white; background: #c6d1d5; box-shadow: 0 0 0 1px #c6d1d5; }
        .step.done { color: var(--navy); font-weight: 750; }
        .step.done::before, .step.active::before { background: var(--teal); }
        .step.done::after, .step.active::after { background: var(--teal); box-shadow: 0 0 0 1px var(--teal); }
        .step.active { color: var(--teal); font-weight: 850; }
        .step small { display: block; margin-top: 5px; color: var(--muted); font-weight: 500; }
        .progress-line { height: 8px; border-radius: 99px; overflow: hidden; background: #e7edef; }
        .progress-line > span { display: block; height: 100%; border-radius: inherit; background: var(--teal); }
        .progress-label { display: flex; justify-content: space-between; gap: 10px; margin: 9px 0 7px; font-size: 12px; }
        .photo-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .photo { min-height: 110px; position: relative; display: flex; align-items: flex-end; padding: 9px; border-radius: 9px; overflow: hidden; color: white; font-size: 11px; font-weight: 750; background: linear-gradient(145deg, #3d7781, #dba762); }
        .photo:nth-child(2) { background: linear-gradient(145deg, #425e77, #cb7c63); }
        .photo:nth-child(3) { background: linear-gradient(145deg, #596c3d, #d6b66c); }
        .photo::after { content: ""; position: absolute; inset: 0; background: linear-gradient(transparent 40%, rgba(0,0,0,.52)); }
        .photo span { z-index: 1; }
        .table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .table th { padding: 11px 10px; color: var(--muted); text-align: left; font-size: 10px; letter-spacing: .06em; text-transform: uppercase; border-bottom: 1px solid var(--line); }
        .table td { padding: 14px 10px; border-bottom: 1px solid #edf1f2; vertical-align: top; }
        .table tr:last-child td { border-bottom: 0; }
        .table strong { color: var(--navy); }
        .field-layout { max-width: 840px; margin: 0 auto; }
        .field-hero { padding: 26px; border-radius: 22px; color: white; background: var(--navy); box-shadow: 0 20px 50px rgba(21, 50, 75, .22); }
        .field-hero h1, .field-hero .subtle { color: white; }
        .field-hero .eyebrow { color: #75d6cb; }
        .field-hero .badge { background: rgba(255,255,255,.14); color: white; }
        .field-status { display: flex; gap: 28px; align-items: end; flex-wrap: wrap; margin-top: 27px; }
        .field-status strong { display: block; font-size: 40px; letter-spacing: -.08em; line-height: 1; }
        .field-status span { color: #b7cbd6; font-size: 12px; }
        .field-body { display: grid; grid-template-columns: 1.2fr .8fr; gap: 17px; margin-top: 17px; }
        .field-body .full { grid-column: 1 / -1; }
        .next-step { display: flex; justify-content: space-between; gap: 12px; align-items: center; padding: 16px; border-radius: 12px; background: #eef8f6; }
        .next-step strong { color: var(--teal); }
        .upload-zone { min-height: 145px; display: grid; place-items: center; text-align: center; border: 1.5px dashed #75b9b5; border-radius: 12px; color: var(--teal); background: #f1fbf9; }
        .upload-zone b { display: block; margin-bottom: 4px; }
        .upload-zone small { color: var(--muted); }
        .comment-box textarea { width: 100%; min-height: 76px; resize: vertical; border: 1px solid var(--line); border-radius: 10px; padding: 11px; color: var(--ink); }
        .comment-footer { display: flex; justify-content: space-between; gap: 10px; margin-top: 10px; align-items: center; }
        .switcher { position: fixed; z-index: 30; bottom: 20px; left: 50%; transform: translateX(-50%); display: flex; align-items: center; gap: 13px; padding: 9px 12px; border: 1px solid #33495a; border-radius: 999px; background: #172b3b; color: white; box-shadow: 0 14px 30px rgba(13, 28, 40, .28); }
        .switcher button { width: 29px; height: 29px; border: 0; border-radius: 50%; color: white; background: #315064; font-size: 17px; line-height: 1; }
        .switcher button:hover { background: var(--teal); }
        .switcher-label { min-width: 154px; text-align: center; font-size: 12px; font-weight: 750; }
        .switcher-label small { display: block; margin-top: 2px; color: #a9bdc9; font-size: 10px; font-weight: 500; }
        .toast { position: fixed; z-index: 40; top: 22px; left: 50%; transform: translate(-50%, -120px); padding: 11px 15px; border-radius: 9px; color: white; background: var(--navy); font-size: 13px; box-shadow: var(--shadow); transition: transform .25s ease; }
        .toast.show { transform: translate(-50%, 0); }
        .variant { display: none; }
        .variant.active { display: block; }
        .variant-a .sidebar-shell { display: grid; grid-template-columns: 214px minmax(0, 1fr); min-height: 100vh; }
        .variant-a .sidebar { padding: 25px 16px; color: #c5d4dc; background: var(--navy); }
        .variant-a .sidebar .brand { color: white; padding: 0 12px 35px; }
        .variant-a .sidebar .brand span { color: #75d6cb; }
        .nav-link { display: flex; align-items: center; gap: 9px; padding: 11px 12px; margin: 2px 0; border-radius: 8px; color: #bdd0d9; font-size: 13px; text-decoration: none; }
        .nav-link.active, .nav-link:hover { color: white; background: rgba(255,255,255,.12); }
        .variant-a .topbar { padding-left: 28px; }
        .variant-c { background: #eef1f1; }
        .variant-c .topbar { background: #f9fbfb; }
        .ledger-header { display: grid; grid-template-columns: 1fr auto; gap: 20px; align-items: start; padding-bottom: 25px; border-bottom: 1px solid var(--line); }
        .ledger-id { color: var(--muted); font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; }
        .ledger-layout { display: grid; grid-template-columns: minmax(0, 1.4fr) 320px; gap: 17px; margin-top: 18px; align-items: start; }
        .feed { padding: 5px 0; }
        .feed-item { display: grid; grid-template-columns: 94px 20px 1fr; gap: 12px; padding: 16px 0; border-bottom: 1px solid var(--line); }
        .feed-item:last-child { border-bottom: 0; }
        .feed-time { color: var(--muted); font-size: 11px; line-height: 1.4; }
        .feed-dot { position: relative; }
        .feed-dot::before { content: ""; position: absolute; top: 2px; left: 4px; width: 10px; height: 10px; border-radius: 50%; background: var(--teal); }
        .feed-dot::after { content: ""; position: absolute; top: 15px; bottom: -35px; left: 8px; width: 1px; background: var(--line); }
        .feed-item:last-child .feed-dot::after { display: none; }
        .feed-item.internal .feed-dot::before { background: var(--amber); }
        .feed-type { margin-bottom: 4px; color: var(--teal); font-size: 10px; font-weight: 850; letter-spacing: .09em; text-transform: uppercase; }
        .feed-item.internal .feed-type { color: var(--amber); }
        .feed-copy { color: var(--ink); font-size: 13px; line-height: 1.5; }
        .feed-copy em { color: var(--muted); }
        .side-list { display: grid; gap: 12px; }
        .side-list > div { display: flex; justify-content: space-between; gap: 12px; padding-bottom: 12px; border-bottom: 1px solid #edf1f2; font-size: 12px; }
        .side-list > div:last-child { border-bottom: 0; padding-bottom: 0; }
        .side-list span { color: var(--muted); }
        @media (max-width: 900px) {
            .grid-main, .ledger-layout, .field-body { grid-template-columns: 1fr; }
            .grid-4 { grid-template-columns: repeat(2, 1fr); }
            .variant-a .sidebar-shell { display: block; }
            .variant-a .sidebar { display: none; }
            .topline { align-items: start; flex-direction: column; }
        }
        @media (max-width: 560px) {
            .page { padding: 22px 16px 110px; }
            .topbar { padding: 0 16px; min-height: 64px; }
            .topbar-right span { display: none; }
            .grid-4, .grid-3 { grid-template-columns: 1fr; }
            .card-pad, .chart-wrap { padding: 16px; }
            .switcher { bottom: 12px; }
            .switcher-label { min-width: 130px; }
            .feed-item { grid-template-columns: 74px 16px 1fr; gap: 7px; }
        }
    </style>
</head>
<body>
    <div class="prototype-ribbon">Prototype · Gelombang 2</div>
    <div class="toast" id="toast" role="status"></div>

    <section class="variant variant-a" data-variant="control">
        <div class="sidebar-shell">
            <aside class="sidebar">
                <div class="brand">THC<span>/PMS</span></div>
                <a class="nav-link" href="#">⌂ &nbsp; Dashboard</a>
                <a class="nav-link active" href="#">▣ &nbsp; Project</a>
                <a class="nav-link" href="#">◈ &nbsp; Material</a>
                <a class="nav-link" href="#">▤ &nbsp; Gudang</a>
                <a class="nav-link" href="#">◎ &nbsp; Mitra</a>
                <a class="nav-link" href="#">⚙ &nbsp; Pengaturan</a>
            </aside>
            <div>
                <header class="topbar"><div class="brand">Project Monitoring</div><div class="topbar-right"><span>Sabtu, 15 Agustus 2026</span><div class="avatar">TH</div></div></header>
                <main class="page">
                    <div class="topline"><div><p class="eyebrow">Project / Ringkasan kendali</p><h1>FTTH Kediri Barat</h1><p class="subtle">PRJ-2604-0017 · Mitra Nusantara Fiber · TOC 30 Sep 2026</p></div><div class="topline-actions"><button class="btn" data-action="export">↧ Export</button><button class="btn btn-primary" data-action="comment">＋ Tambah komentar</button></div></div>
                    <div class="grid grid-4" style="margin-bottom:17px">
                        <div class="card card-pad"><div class="kpi-label">Realisasi jasa</div><div class="kpi-value">62,4%</div><div class="delta">↑ 4,2% minggu ini</div></div>
                        <div class="card card-pad"><div class="kpi-label">SPI terhadap baseline</div><div class="kpi-value">0,94</div><span class="badge badge-amber">● Waspada</span></div>
                        <div class="card card-pad"><div class="kpi-label">Kesiapan material</div><div class="kpi-value">78%</div><div class="delta">42 dari 54 item</div></div>
                        <div class="card card-pad"><div class="kpi-label">Status project</div><div class="kpi-value" style="font-size:23px">Aktif</div><span class="badge badge-green">● On track</span></div>
                    </div>
                    <div class="card" style="margin-bottom:17px"><div class="chart-wrap"><div class="chart-head"><div><h2>Kurva S · bobot rupiah jasa</h2><div class="legend"><span><i class="plan"></i>Baseline</span><span><i></i>Realisasi terverifikasi</span><span><i class="pending"></i>Pending verifikasi</span></div></div><span class="badge badge-amber">Revised baseline</span></div><svg class="chart" viewBox="0 0 760 270" role="img" aria-label="Kurva S baseline, realisasi, dan pending"><path class="chart-grid" d="M48 30H740 M48 87H740 M48 144H740 M48 201H740 M48 230H740"/><path class="chart-plan" d="M48 230 C170 229 205 216 270 187 S390 121 470 78 S610 42 740 30"/><path class="chart-actual" d="M48 230 C160 230 203 224 271 203 S382 160 459 125 S575 93 638 78"/><path class="chart-pending" d="M638 78 C680 67 707 56 740 48"/><text class="chart-axis" x="10" y="234">0%</text><text class="chart-axis" x="8" y="205">25%</text><text class="chart-axis" x="8" y="148">50%</text><text class="chart-axis" x="8" y="91">75%</text><text class="chart-axis" x="8" y="34">100%</text><text class="chart-axis" x="48" y="252">Apr</text><text class="chart-axis" x="215" y="252">Mei</text><text class="chart-axis" x="390" y="252">Jun</text><text class="chart-axis" x="570" y="252">Jul</text><text class="chart-axis" x="715" y="252">Sep</text></svg></div></div>
                    <div class="card card-pad" style="margin-bottom:17px"><h2>Step project</h2><div class="step-row"><div class="step done">Design<small>12 Apr</small></div><div class="step done">Survey<small>20 Apr</small></div><div class="step done">DRM<small>05 Mei</small></div><div class="step done">SPK<small>12 Mei</small></div><div class="step done">Pengadaan<small>28 Mei</small></div><div class="step active">Delivery<small>berjalan</small></div><div class="step">MOS</div><div class="step">Deployment</div><div class="step">Test Comm</div></div></div>
                    <div class="grid grid-main"><div class="card card-pad"><h2>Aktivitas terbaru</h2><div class="timeline"><div class="event"><time>Hari ini · 09:42</time><p><strong>Rina Waspang</strong> mengajukan progres 4 foto pada step Delivery.</p></div><div class="event"><time>Kemarin · 16:10</time><p><strong>THC Admin</strong> memverifikasi progres minggu ke-16.</p></div><div class="event internal"><time>Kemarin · 14:35 · Internal THC</time><p>Transit SJ-2608-0041 sudah 4 hari. Perlu ditindaklanjuti.</p></div></div><button class="btn" style="margin-top:4px" data-action="timeline">Lihat linimasa lengkap →</button></div><div class="card card-pad"><h2>Material</h2><div class="metric-callout"><div class="metric-ring"><strong>78%</strong></div><div><p style="margin:0 0 5px;font-size:13px"><strong>Siap untuk tahap Delivery</strong></p><p class="subtle" style="margin:0">3 item masih dalam Transit</p></div></div><div style="height:22px"></div><div class="progress-label"><span>Request terpenuhi</span><strong>42 / 54</strong></div><div class="progress-line"><span style="width:78%"></span></div><button class="btn" style="margin-top:17px;width:100%" data-action="material">Buka detail material →</button></div></div>
                </main>
            </div>
        </div>
    </section>

    <section class="variant variant-b" data-variant="field">
        <header class="topbar"><div class="brand">THC<span style="color:var(--teal)">/PMS</span></div><div class="topbar-right"><span>Mode lapangan · Rina Waspang</span><div class="avatar">RW</div></div></header>
        <main class="page field-layout">
            <div class="field-hero"><p class="eyebrow">Project aktif · Delivery Material</p><h1>FTTH Kediri Barat</h1><p class="subtle">PRJ-2604-0017 · Update terakhir hari ini, 09:42</p><div class="field-status"><div><strong>62,4%</strong><span>realisasi terverifikasi</span></div><div><strong>4</strong><span>foto pending upload</span></div><span class="badge">SPI 0,94 · Waspada</span></div></div>
            <div class="field-body">
                <div class="card card-pad"><p class="eyebrow">Fokus hari ini</p><h2 style="margin-bottom:12px">Selesaikan Delivery Material</h2><div class="next-step"><div><strong>Langkah berikutnya</strong><p class="subtle" style="margin:4px 0 0">Konfirmasi material tiba di PoP Kediri-07</p></div><button class="btn btn-primary" data-action="step">Mulai →</button></div><div style="height:20px"></div><div class="progress-label"><span>Step 6 dari 11</span><strong>Delivery</strong></div><div class="progress-line"><span style="width:54%"></span></div></div>
                <div class="card card-pad"><p class="eyebrow">Kesiapan material</p><div class="kpi-value" style="font-size:34px">78%</div><p class="subtle" style="margin-bottom:13px">42 / 54 item siap dipakai</p><button class="btn" style="width:100%" data-action="material">Lihat rincian</button></div>
                <div class="card card-pad full"><div style="display:flex;justify-content:space-between;gap:10px;align-items:center"><div><p class="eyebrow">Bukti pekerjaan</p><h2>Foto lapangan</h2></div><span class="badge badge-amber">4 belum diverifikasi</span></div><div class="photo-grid"><div class="photo"><span>PoP Kediri-07 · 09:31</span></div><div class="photo"><span>Penarikan kabel · 09:34</span></div><div class="photo"><span>Rack ODP · 09:38</span></div></div><button class="btn btn-primary" style="width:100%;margin-top:10px" data-action="photo">＋ Tambah foto</button></div>
                <div class="card card-pad full comment-box"><p class="eyebrow">Linimasa project</p><h2>Kirim pembaruan</h2><textarea id="field-comment" placeholder="Tulis catatan untuk tim THC…"></textarea><div class="comment-footer"><label class="subtle"><input type="checkbox" id="internal-note"> Catatan Internal THC</label><button class="btn btn-primary" data-action="send-comment">Kirim komentar</button></div></div>
                <div class="card card-pad full"><h2>Step project</h2><div class="step-row"><div class="step done">Design</div><div class="step done">Survey</div><div class="step done">DRM</div><div class="step done">SPK</div><div class="step done">Pengadaan</div><div class="step active">Delivery</div><div class="step">MOS</div><div class="step">Deployment</div><div class="step">Test Comm</div></div></div>
            </div>
        </main>
    </section>

    <section class="variant variant-c" data-variant="ledger">
        <header class="topbar"><div class="brand">THC<span style="color:var(--teal)">/PMS</span></div><div class="topbar-right"><span>Semua perubahan tercatat</span><div class="avatar">TH</div></div></header>
        <main class="page">
            <div class="ledger-header"><div><p class="eyebrow">Project ledger · Audit trail</p><h1>FTTH Kediri Barat</h1><p class="subtle">Mitra Nusantara Fiber · <span class="ledger-id">PRJ-2604-0017</span></p></div><div class="topline-actions"><span class="badge badge-green">● Aktif</span><button class="btn" data-action="export">↧ Export laporan</button></div></div>
            <div class="ledger-layout"><div class="card card-pad"><div style="display:flex;justify-content:space-between;align-items:center;gap:10px"><h2 style="margin:0">Linimasa gabungan</h2><button class="btn" data-action="filter">☷ Filter</button></div><div class="feed"><div class="feed-item"><div class="feed-time">15 Agu<br>09:42</div><div class="feed-dot"></div><div><div class="feed-type">Progres diajukan · Rina Waspang</div><div class="feed-copy">4 foto ditambahkan untuk <strong>Delivery Material</strong>. Menunggu verifikasi THC.</div><div style="margin-top:9px"><span class="badge badge-amber">Pending verifikasi</span></div></div></div><div class="feed-item"><div class="feed-time">14 Agu<br>16:10</div><div class="feed-dot"></div><div><div class="feed-type">Progres diverifikasi · THC Admin</div><div class="feed-copy">Progres minggu ke-16 disetujui. Realisasi kumulatif menjadi <strong>62,4%</strong>.</div></div></div><div class="feed-item internal"><div class="feed-time">14 Agu<br>14:35</div><div class="feed-dot"></div><div><div class="feed-type">Komentar Internal THC</div><div class="feed-copy">Transit <strong>SJ-2608-0041</strong> sudah 4 hari. Perlu ditindaklanjuti sebelum Delivery ditutup.</div><div style="margin-top:9px"><span class="badge badge-amber">Hanya THC</span></div></div></div><div class="feed-item"><div class="feed-time">12 Agu<br>11:20</div><div class="feed-dot"></div><div><div class="feed-type">Step berpindah · Sistem</div><div class="feed-copy">Step project berpindah dari <strong>Pengadaan Material</strong> ke <strong>Delivery Material</strong>.</div></div></div><div class="feed-item"><div class="feed-time">10 Agu<br>08:15</div><div class="feed-dot"></div><div><div class="feed-type">Foto tersinkron · Sistem</div><div class="feed-copy">3 foto berhasil disalin ke Folder Master Google Drive.</div></div></div></div><button class="btn btn-primary" style="width:100%" data-action="comment">＋ Tambah komentar</button></div>
                <aside class="side-list"><div class="card card-pad"><h2>Ringkasan kinerja</h2><div class="kpi-value">62,4%</div><p class="subtle" style="margin-bottom:15px">Realisasi jasa terverifikasi</p><div class="progress-label"><span>Baseline revised</span><strong>66,4%</strong></div><div class="progress-line"><span style="width:94%"></span></div><p style="margin:12px 0 0"><span class="badge badge-amber">SPI 0,94 · Waspada</span></p></div><div class="card card-pad"><h2>Project facts</h2><div class="side-list"><div><span>TOC</span><strong>30 Sep 2026</strong></div><div><span>Step</span><strong>Delivery Material</strong></div><div><span>Material</span><strong>78% siap</strong></div><div><span>Foto</span><strong>12 tersinkron</strong></div><div><span>Baseline</span><strong>Revised · 02 Jul</strong></div></div></div><div class="card card-pad"><h2>Kurva S</h2><div class="metric-callout"><div class="metric-ring"><strong>94%</strong></div><p class="subtle" style="margin:0">Kinerja terhadap baseline hari ini</p></div><button class="btn" style="width:100%;margin-top:15px" data-action="chart">Buka grafik penuh →</button></div></aside>
            </div>
        </main>
    </section>

    <nav class="switcher" aria-label="Prototype variants"><button id="prev" aria-label="Variant sebelumnya">‹</button><div class="switcher-label" id="variant-label">A — Control room<small>← → untuk berganti tampilan</small></div><button id="next" aria-label="Variant berikutnya">›</button></nav>

    <script>
        const variants = [
            { key: 'control', label: 'A — Control room', name: 'Ringkasan kendali desktop' },
            { key: 'field', label: 'B — Field-first', name: 'Alur kerja mobile' },
            { key: 'ledger', label: 'C — Evidence ledger', name: 'Linimasa audit' },
        ];
        const initial = new URLSearchParams(window.location.search).get('variant');
        let index = Math.max(0, variants.findIndex((variant) => variant.key === initial));
        const toast = document.getElementById('toast');
        function setVariant(nextIndex) {
            index = (nextIndex + variants.length) % variants.length;
            const variant = variants[index];
            document.querySelectorAll('.variant').forEach((node) => node.classList.toggle('active', node.dataset.variant === variant.key));
            document.getElementById('variant-label').innerHTML = `${variant.label}<small>${variant.name}</small>`;
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
        document.getElementById('prev').addEventListener('click', () => setVariant(index - 1));
        document.getElementById('next').addEventListener('click', () => setVariant(index + 1));
        document.addEventListener('keydown', (event) => {
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName) || document.activeElement?.isContentEditable) return;
            if (event.key === 'ArrowLeft') setVariant(index - 1);
            if (event.key === 'ArrowRight') setVariant(index + 1);
        });
        document.querySelectorAll('[data-action]').forEach((button) => button.addEventListener('click', () => {
            const messages = {
                export: 'Simulasi: laporan Excel akan dibuat.',
                comment: 'Simulasi: panel komentar dibuka.',
                'send-comment': 'Simulasi: komentar dicatat di linimasa (tanpa persistence).',
                photo: 'Simulasi: pemilih foto dibuka. Maksimal 10 JPEG.',
                material: 'Simulasi: detail kesiapan material dibuka.',
                timeline: 'Simulasi: linimasa lengkap dibuka.',
                step: 'Simulasi: checklist Delivery dibuka.',
                filter: 'Simulasi: filter linimasa diaktifkan.',
                chart: 'Simulasi: grafik Kurva S diperbesar.',
            };
            notify(messages[button.dataset.action] || 'Simulasi aksi prototype.');
        }));
        setVariant(index);
    </script>
</body>
</html>
