{{--
    PROTOTYPE THROWAWAY - Gelombang 3.
    Three variants of a cross-project dashboard, switchable via
    ?variant=portfolio|risks|reports.
    Design question: should Gelombang 3 lead with portfolio health, risk triage,
    or a report/API workspace?
    Mock data is local, read-only, and intentionally has no persistence.
--}}
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prototype Gelombang 3 - Project Monitoring System</title>
    <style>
        :root {
            --ink: #17212b;
            --muted: #687684;
            --line: #d9e0e5;
            --paper: #f3f6f7;
            --white: #fff;
            --navy: #15324b;
            --teal: #087f8c;
            --teal-soft: #dff3ed;
            --amber: #a86314;
            --amber-soft: #fff1d7;
            --red: #b34444;
            --red-soft: #ffe2e1;
            --violet: #6655a9;
            --violet-soft: #eeeafd;
            --shadow: 0 14px 40px rgba(21, 50, 75, .09);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--ink);
            background: var(--paper);
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; }
        button, input, select { font: inherit; }
        button { cursor: pointer; }
        h1, h2, h3, p { margin-top: 0; }
        h1 { margin-bottom: 8px; color: var(--navy); font-size: clamp(28px, 4vw, 46px); letter-spacing: -.065em; line-height: 1.02; }
        h2 { margin-bottom: 16px; color: var(--navy); font-size: 19px; letter-spacing: -.035em; }
        h3 { margin-bottom: 7px; font-size: 14px; }
        .prototype-ribbon { position: fixed; z-index: 50; top: 12px; right: 16px; padding: 7px 11px; border-radius: 999px; color: #4d3200; background: #fdc858; box-shadow: 0 5px 16px rgba(77, 50, 0, .16); font-size: 11px; font-weight: 850; letter-spacing: .06em; text-transform: uppercase; }
        .subtle { color: var(--muted); font-size: 13px; line-height: 1.55; }
        .eyebrow { margin: 0 0 8px; color: var(--teal); font-size: 11px; font-weight: 850; letter-spacing: .12em; text-transform: uppercase; }
        .card { border: 1px solid var(--line); border-radius: 15px; background: var(--white); box-shadow: var(--shadow); }
        .card-pad { padding: 21px; }
        .btn { border: 1px solid var(--line); border-radius: 9px; padding: 10px 14px; color: var(--navy); background: white; font-size: 13px; font-weight: 750; }
        .btn:hover { border-color: var(--teal); color: var(--teal); }
        .btn-primary { border-color: var(--teal); color: white; background: var(--teal); }
        .btn-primary:hover { color: white; background: #056c76; }
        .btn-dark { border-color: #365066; color: white; background: #213a4e; }
        .badge { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 5px 9px; font-size: 11px; font-weight: 850; white-space: nowrap; }
        .badge-green { color: #11664f; background: var(--teal-soft); }
        .badge-amber { color: var(--amber); background: var(--amber-soft); }
        .badge-red { color: var(--red); background: var(--red-soft); }
        .badge-violet { color: #57428f; background: var(--violet-soft); }
        .topbar { min-height: 72px; display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 0 30px; border-bottom: 1px solid var(--line); background: var(--white); }
        .brand { color: var(--navy); font-size: 20px; font-weight: 900; letter-spacing: -.05em; }
        .brand span { color: var(--teal); }
        .topbar-right { display: flex; align-items: center; gap: 14px; color: var(--muted); font-size: 12px; }
        .avatar { width: 34px; height: 34px; display: grid; place-items: center; border-radius: 50%; color: white; background: var(--navy); font-weight: 850; }
        .page { max-width: 1480px; margin: 0 auto; padding: 30px 30px 130px; }
        .topline { display: flex; justify-content: space-between; align-items: end; gap: 20px; margin-bottom: 23px; }
        .topline-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .filter-row { display: flex; flex-wrap: wrap; align-items: center; gap: 7px; margin: 0 0 18px; }
        .filter-label { margin-right: 4px; color: var(--muted); font-size: 12px; font-weight: 700; }
        .filter { border: 1px solid var(--line); border-radius: 999px; padding: 7px 11px; color: var(--muted); background: white; font-size: 11px; font-weight: 750; }
        .filter.active, .filter:hover { border-color: var(--teal); color: var(--teal); background: #eef9f7; }
        .grid { display: grid; gap: 16px; }
        .kpi-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 16px; }
        .kpi-label { color: var(--muted); font-size: 12px; }
        .kpi-value { margin: 7px 0 4px; color: var(--navy); font-size: 31px; font-weight: 900; letter-spacing: -.075em; }
        .kpi-meta { color: var(--muted); font-size: 11px; }
        .delta-up { color: #178367; font-weight: 800; }
        .delta-down { color: var(--red); font-weight: 800; }
        .progress-line { height: 8px; overflow: hidden; border-radius: 99px; background: #e7edef; }
        .progress-line > span { display: block; height: 100%; border-radius: inherit; background: var(--teal); }
        .progress-line.amber > span { background: #d79c38; }
        .progress-line.red > span { background: var(--red); }
        .progress-label { display: flex; justify-content: space-between; gap: 10px; margin: 9px 0 7px; color: var(--muted); font-size: 11px; }
        .progress-label strong { color: var(--navy); }
        .empty { padding: 27px 16px; color: var(--muted); text-align: center; font-size: 13px; }
        .toast { position: fixed; z-index: 60; top: 22px; left: 50%; transform: translate(-50%, -120px); padding: 11px 15px; border-radius: 9px; color: white; background: var(--navy); box-shadow: var(--shadow); font-size: 13px; transition: transform .25s ease; }
        .toast.show { transform: translate(-50%, 0); }
        .prototype-state { position: fixed; z-index: 35; right: 20px; bottom: 84px; max-width: min(430px, calc(100vw - 40px)); padding: 8px 11px; border: 1px solid #c5d3d9; border-radius: 9px; color: #405462; background: rgba(250, 253, 253, .95); box-shadow: 0 8px 22px rgba(21, 50, 75, .12); font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 10px; }
        .variant { display: none; min-height: 100vh; }
        .variant.active { display: block; }

        /* Variant A: portfolio cockpit */
        .variant-a .sidebar-shell { display: grid; grid-template-columns: 218px minmax(0, 1fr); min-height: 100vh; }
        .variant-a .sidebar { padding: 25px 16px; color: #c5d4dc; background: var(--navy); }
        .variant-a .sidebar .brand { padding: 0 12px 35px; color: white; }
        .variant-a .sidebar .brand span { color: #75d6cb; }
        .nav-link { display: flex; align-items: center; gap: 9px; padding: 11px 12px; margin: 2px 0; border-radius: 8px; color: #bdd0d9; font-size: 13px; text-decoration: none; }
        .nav-link.active, .nav-link:hover { color: white; background: rgba(255,255,255,.12); }
        .sidebar-note { margin: 42px 12px 0; padding-top: 16px; border-top: 1px solid rgba(255,255,255,.15); color: #94afbc; font-size: 11px; line-height: 1.55; }
        .portfolio-layout { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(285px, .6fr); gap: 16px; align-items: start; }
        .portfolio-left, .portfolio-right { display: grid; gap: 16px; }
        .chart-card { padding: 21px 21px 13px; }
        .card-head { display: flex; justify-content: space-between; align-items: start; gap: 14px; margin-bottom: 14px; }
        .legend { display: flex; flex-wrap: wrap; gap: 12px; color: var(--muted); font-size: 11px; }
        .legend i { display: inline-block; width: 17px; height: 3px; margin-right: 5px; vertical-align: middle; border-radius: 4px; background: var(--teal); }
        .legend .target { border-top: 2px dashed #a3adb2; background: transparent; }
        .legend .risk { background: #d79c38; }
        svg.chart { display: block; width: 100%; height: 235px; overflow: visible; }
        .chart-grid { stroke: #e8edef; stroke-width: 1; }
        .chart-axis { fill: #8a979f; font-size: 10px; }
        .chart-target { fill: none; stroke: #a3adb2; stroke-width: 2; stroke-dasharray: 5 5; }
        .chart-portfolio { fill: none; stroke: var(--teal); stroke-width: 4; stroke-linecap: round; stroke-linejoin: round; }
        .chart-dot { fill: white; stroke: var(--teal); stroke-width: 3; }
        .health-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .health-table th { padding: 10px 9px; border-bottom: 1px solid var(--line); color: var(--muted); font-size: 10px; letter-spacing: .07em; text-align: left; text-transform: uppercase; }
        .health-table td { padding: 13px 9px; border-bottom: 1px solid #edf1f2; vertical-align: middle; }
        .health-table tr:last-child td { border-bottom: 0; }
        .health-table tr:hover { background: #f8fbfb; }
        .health-table strong { display: block; color: var(--navy); }
        .health-table small { color: var(--muted); font-size: 10px; }
        .table-link { padding: 0; border: 0; color: var(--navy); background: transparent; text-align: left; }
        .table-link:hover strong { color: var(--teal); }
        .risk-stack { display: grid; gap: 9px; }
        .risk-row { display: grid; grid-template-columns: 9px minmax(0, 1fr) auto; gap: 9px; align-items: start; padding: 11px 0; border-bottom: 1px solid #edf1f2; }
        .risk-row:last-child { border-bottom: 0; padding-bottom: 0; }
        .risk-dot { width: 9px; height: 9px; margin-top: 4px; border-radius: 50%; background: #d79c38; box-shadow: 0 0 0 4px #fff4de; }
        .risk-dot.red { background: var(--red); box-shadow: 0 0 0 4px #ffebea; }
        .risk-dot.green { background: var(--teal); box-shadow: 0 0 0 4px #e4f6f0; }
        .risk-row strong { display: block; margin-bottom: 3px; color: var(--navy); font-size: 12px; }
        .risk-row small { color: var(--muted); font-size: 10px; line-height: 1.35; }
        .distribution { display: grid; gap: 12px; }
        .distribution-row { display: grid; grid-template-columns: 84px 1fr 31px; gap: 9px; align-items: center; color: var(--muted); font-size: 11px; }
        .distribution-row .progress-line { height: 7px; }
        .distribution-row strong { color: var(--navy); text-align: right; }
        .activity-strip { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0; }
        .activity-cell { padding: 4px 17px; border-right: 1px solid var(--line); }
        .activity-cell:first-child { padding-left: 0; }
        .activity-cell:last-child { border-right: 0; }
        .activity-cell time { display: block; margin-bottom: 6px; color: var(--muted); font-size: 10px; }
        .activity-cell p { margin: 0; font-size: 12px; line-height: 1.45; }

        /* Variant B: risk desk */
        .variant-b { color: #f3f2ed; background: #1d2427; }
        .variant-b .topbar { border-color: #394247; color: #f3f2ed; background: #202a2d; }
        .variant-b .brand, .variant-b h1, .variant-b h2, .variant-b .risk-title { color: #f3f2ed; }
        .variant-b .brand span { color: #78d6c7; }
        .variant-b .topbar-right { color: #a7b7b6; }
        .variant-b .avatar { color: #1d2427; background: #78d6c7; }
        .risk-desk-page { max-width: 1500px; padding-top: 35px; }
        .risk-desk-page .eyebrow { color: #78d6c7; }
        .risk-desk-page .subtle { color: #a7b7b6; }
        .risk-heading { display: flex; justify-content: space-between; align-items: end; gap: 24px; margin-bottom: 26px; }
        .risk-heading h1 { margin-bottom: 10px; }
        .risk-score { min-width: 178px; padding: 15px 17px; border: 1px solid #465155; border-radius: 12px; background: #273236; }
        .risk-score span { display: block; color: #a7b7b6; font-size: 10px; letter-spacing: .08em; text-transform: uppercase; }
        .risk-score strong { display: block; margin: 4px 0; color: #f3c35b; font-size: 28px; letter-spacing: -.06em; }
        .risk-score small { color: #f3c35b; font-size: 11px; }
        .dark-filters { display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; padding: 10px 0; border-top: 1px solid #394247; border-bottom: 1px solid #394247; }
        .dark-filters .filter { border-color: #465155; color: #a7b7b6; background: transparent; }
        .dark-filters .filter.active, .dark-filters .filter:hover { border-color: #78d6c7; color: #1d2427; background: #78d6c7; }
        .risk-board { display: grid; grid-template-columns: minmax(0, 1fr) 325px; gap: 16px; margin-top: 16px; }
        .triage-panel { padding: 21px; border: 1px solid #394247; border-radius: 14px; background: #202a2d; }
        .triage-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 3px; }
        .triage-head h2 { margin: 0; }
        .triage-count { color: #a7b7b6; font-size: 11px; }
        .risk-item { display: grid; grid-template-columns: 76px minmax(0, 1fr) auto; gap: 15px; align-items: start; padding: 17px 0; border-bottom: 1px solid #394247; }
        .risk-item:last-child { border-bottom: 0; padding-bottom: 0; }
        .risk-item:first-of-type { padding-top: 15px; }
        .risk-item.selected { margin: 0 -10px; padding-right: 10px; padding-left: 10px; border-radius: 8px; background: #2a383a; }
        .risk-level { display: inline-flex; align-items: center; justify-content: center; min-height: 24px; border-radius: 5px; font-size: 10px; font-weight: 900; letter-spacing: .06em; text-transform: uppercase; }
        .risk-level.high { color: #2a1717; background: #f19b8f; }
        .risk-level.watch { color: #342713; background: #f3c35b; }
        .risk-level.low { color: #18352e; background: #78d6c7; }
        .risk-item h3 { margin-bottom: 5px; color: #f3f2ed; font-size: 13px; }
        .risk-item p { margin: 0; color: #a7b7b6; font-size: 12px; line-height: 1.45; }
        .risk-item small { display: block; margin-top: 7px; color: #788b8b; font-size: 10px; }
        .risk-item .btn { border-color: #465155; color: #d9e5df; background: transparent; font-size: 11px; }
        .risk-item .btn:hover { border-color: #78d6c7; color: #78d6c7; }
        .briefing { display: grid; gap: 16px; }
        .briefing-card { padding: 19px; border: 1px solid #394247; border-radius: 14px; background: #202a2d; }
        .briefing-card h2 { margin-bottom: 13px; }
        .briefing-row { display: flex; justify-content: space-between; gap: 15px; padding: 10px 0; border-bottom: 1px solid #394247; color: #a7b7b6; font-size: 11px; }
        .briefing-row:last-child { border-bottom: 0; }
        .briefing-row strong { color: #f3f2ed; text-align: right; }
        .owner-list { display: grid; gap: 10px; }
        .owner { display: flex; align-items: center; gap: 9px; color: #d9e5df; font-size: 11px; }
        .owner span { width: 25px; height: 25px; display: grid; place-items: center; border-radius: 50%; color: #1d2427; background: #b8d4cd; font-size: 9px; font-weight: 900; }
        .owner em { margin-left: auto; color: #a7b7b6; font-size: 10px; font-style: normal; }
        .briefing-card .btn { width: 100%; margin-top: 7px; }

        /* Variant C: report studio */
        .variant-c { background: #edf0f4; }
        .variant-c .topbar { background: #fbfcfd; }
        .studio-page { max-width: 1580px; padding: 22px 22px 130px; }
        .studio-heading { display: flex; justify-content: space-between; align-items: center; gap: 18px; margin: 0 5px 18px; }
        .studio-heading h1 { margin-bottom: 4px; font-size: clamp(25px, 3vw, 36px); }
        .studio-heading .eyebrow { margin-bottom: 5px; color: var(--violet); }
        .studio-shell { display: grid; grid-template-columns: 220px minmax(0, 1fr) 275px; min-height: 700px; overflow: hidden; border: 1px solid #d5dce3; border-radius: 15px; background: #fff; box-shadow: var(--shadow); }
        .studio-nav { padding: 20px 15px; border-right: 1px solid #dce2e8; background: #f7f9fb; }
        .studio-nav h3 { margin: 0 10px 12px; color: var(--muted); font-size: 10px; letter-spacing: .08em; text-transform: uppercase; }
        .studio-nav button { width: 100%; display: flex; justify-content: space-between; gap: 10px; margin: 2px 0; padding: 10px; border: 0; border-radius: 7px; color: var(--muted); background: transparent; text-align: left; font-size: 12px; }
        .studio-nav button:hover, .studio-nav button.active { color: #57428f; background: var(--violet-soft); font-weight: 800; }
        .studio-nav button small { color: inherit; opacity: .7; }
        .nav-divider { margin: 20px 10px 14px; border-top: 1px solid #dce2e8; }
        .studio-main { min-width: 0; padding: 23px; }
        .report-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding-bottom: 16px; border-bottom: 1px solid #e2e6eb; }
        .report-toolbar h2 { margin: 0; }
        .toolbar-controls { display: flex; flex-wrap: wrap; gap: 7px; }
        .select-like { border: 1px solid #d5dce3; border-radius: 7px; padding: 8px 10px; color: var(--muted); background: white; font-size: 11px; }
        .report-canvas { padding-top: 20px; }
        .report-metric-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 17px; }
        .report-metric { padding: 13px; border: 1px solid #e1e6eb; border-radius: 9px; background: #fbfcfd; }
        .report-metric span { display: block; color: var(--muted); font-size: 10px; }
        .report-metric strong { display: block; margin-top: 5px; color: var(--navy); font-size: 22px; letter-spacing: -.06em; }
        .report-chart { padding: 16px; border: 1px solid #e1e6eb; border-radius: 10px; }
        .report-chart-head { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
        .report-chart h3 { margin: 0; color: var(--navy); }
        .report-chart small { color: var(--muted); font-size: 10px; }
        .studio-table { width: 100%; margin-top: 17px; border-collapse: collapse; font-size: 11px; }
        .studio-table th { padding: 9px 7px; border-bottom: 1px solid #dce2e8; color: var(--muted); font-size: 9px; text-align: left; text-transform: uppercase; }
        .studio-table td { padding: 11px 7px; border-bottom: 1px solid #eef1f3; }
        .studio-table td strong { color: var(--navy); }
        .studio-side { padding: 20px 17px; border-left: 1px solid #dce2e8; background: #fbfcfd; }
        .studio-side h3 { margin-bottom: 12px; color: var(--navy); }
        .delivery-card { margin-bottom: 19px; padding-bottom: 19px; border-bottom: 1px solid #dce2e8; }
        .delivery-card:last-child { border-bottom: 0; }
        .delivery-card p { color: var(--muted); font-size: 11px; line-height: 1.5; }
        .delivery-list { display: grid; gap: 8px; }
        .delivery-item { display: flex; align-items: center; gap: 8px; padding: 9px; border: 1px solid #e1e6eb; border-radius: 8px; color: var(--muted); background: white; font-size: 10px; }
        .delivery-item strong { margin-left: auto; color: var(--navy); font-size: 10px; }
        .check { width: 15px; height: 15px; display: grid; place-items: center; border-radius: 50%; color: white; background: var(--teal); font-size: 9px; }
        .api-box { padding: 11px; border-radius: 8px; color: #dfe9ee; background: #1b3040; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 10px; line-height: 1.55; word-break: break-word; }
        .api-box .method { color: #78d6c7; }
        .api-box .path { color: #f3c35b; }
        .studio-side .btn { width: 100%; margin-top: 9px; }

        .switcher { position: fixed; z-index: 45; bottom: 20px; left: 50%; display: flex; align-items: center; gap: 13px; transform: translateX(-50%); padding: 9px 12px; border: 1px solid #33495a; border-radius: 999px; color: white; background: #172b3b; box-shadow: 0 14px 30px rgba(13, 28, 40, .28); }
        .switcher button { width: 29px; height: 29px; border: 0; border-radius: 50%; color: white; background: #315064; font-size: 17px; line-height: 1; }
        .switcher button:hover { background: var(--teal); }
        .switcher-label { min-width: 190px; color: white; text-align: center; font-size: 12px; font-weight: 750; }
        .switcher-label small { display: block; margin-top: 2px; color: #a9bdc9; font-size: 10px; font-weight: 500; }
        @media (max-width: 1100px) {
            .portfolio-layout, .risk-board { grid-template-columns: 1fr; }
            .studio-shell { grid-template-columns: 185px minmax(0, 1fr); }
            .studio-side { grid-column: 1 / -1; display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; border-top: 1px solid #dce2e8; border-left: 0; }
            .delivery-card { margin: 0; padding: 0 16px 0 0; border-right: 1px solid #dce2e8; border-bottom: 0; }
            .delivery-card:last-child { padding-right: 0; border-right: 0; }
        }
        @media (max-width: 850px) {
            .variant-a .sidebar-shell { display: block; }
            .variant-a .sidebar { display: none; }
            .topline, .risk-heading, .studio-heading { align-items: start; flex-direction: column; }
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .activity-strip { grid-template-columns: 1fr; gap: 13px; }
            .activity-cell, .activity-cell:first-child { padding: 0 0 13px; border-right: 0; border-bottom: 1px solid var(--line); }
            .activity-cell:last-child { padding-bottom: 0; border-bottom: 0; }
            .studio-shell { grid-template-columns: 1fr; }
            .studio-nav { display: flex; flex-wrap: wrap; gap: 2px; border-right: 0; border-bottom: 1px solid #dce2e8; }
            .studio-nav h3, .nav-divider { width: 100%; }
            .studio-nav button { width: auto; }
            .studio-main { grid-column: 1; }
        }
        @media (max-width: 560px) {
            .page, .risk-desk-page { padding: 23px 16px 145px; }
            .studio-page { padding: 18px 12px 145px; }
            .topbar { min-height: 64px; padding: 0 16px; }
            .topbar-right span { display: none; }
            .kpi-grid, .report-metric-row { grid-template-columns: 1fr; }
            .card-pad, .chart-card, .triage-panel, .briefing-card, .studio-main, .studio-side { padding: 16px; }
            .health-table { min-width: 610px; }
            .portfolio-left > .card { overflow-x: auto; }
            .risk-item { grid-template-columns: 62px minmax(0, 1fr); }
            .risk-item .btn { grid-column: 2; justify-self: start; }
            .studio-side { display: block; }
            .delivery-card, .delivery-card:last-child { padding: 0 0 17px; margin-bottom: 17px; border-right: 0; border-bottom: 1px solid #dce2e8; }
            .delivery-card:last-child { padding-bottom: 0; margin-bottom: 0; border-bottom: 0; }
            .switcher { bottom: 12px; gap: 8px; }
            .switcher-label { min-width: 144px; }
            .prototype-state { right: 12px; bottom: 72px; max-width: calc(100vw - 24px); }
        }
    </style>
</head>
<body>
    <div class="prototype-ribbon">Prototype - Gelombang 3</div>
    <div class="toast" id="toast" role="status"></div>
    <div class="prototype-state" id="prototype-state" aria-live="polite">state: memuat...</div>

    <section class="variant variant-a" data-variant="portfolio">
        <div class="sidebar-shell">
            <aside class="sidebar">
                <div class="brand">THC<span>/PMS</span></div>
                <a class="nav-link active" href="#">[] &nbsp; Portfolio</a>
                <a class="nav-link" href="#">[] &nbsp; Project</a>
                <a class="nav-link" href="#">[] &nbsp; Gudang</a>
                <a class="nav-link" href="#">[] &nbsp; Mitra</a>
                <a class="nav-link" href="#">[] &nbsp; Laporan</a>
                <div class="sidebar-note">Mode THC<br>Agregasi lintas Mitra<br>Terakhir sinkron: 09:42</div>
            </aside>
            <div>
                <header class="topbar"><div class="brand">Project Monitoring</div><div class="topbar-right"><span>Sabtu, 15 Agustus 2026</span><div class="avatar">TH</div></div></header>
                <main class="page">
                    <div class="topline">
                        <div><p class="eyebrow">Gelombang 3 / Portfolio overview</p><h1>Portfolio Command Center</h1><p class="subtle">Satu pandangan untuk kesehatan 24 Project aktif, risiko material, dan pekerjaan yang perlu keputusan.</p></div>
                        <div class="topline-actions"><button class="btn" data-action="refresh">Refresh data</button><button class="btn btn-primary" data-action="export">Export ringkasan</button></div>
                    </div>
                    <div class="filter-row" data-filter-group="portfolio"><span class="filter-label">Tampilan:</span><button class="filter active" data-filter="Semua Project">Semua Project</button><button class="filter" data-filter="Mitra Nusantara">Mitra Nusantara</button><button class="filter" data-filter="Q3 2026">Q3 2026</button><button class="filter" data-filter="At risk">At risk</button></div>
                    <div class="grid kpi-grid">
                        <div class="card card-pad"><div class="kpi-label">Project aktif</div><div class="kpi-value">24</div><div class="kpi-meta"><span class="delta-up">+3</span> dibanding bulan lalu</div></div>
                        <div class="card card-pad"><div class="kpi-label">Realisasi jasa portofolio</div><div class="kpi-value">68,2%</div><div class="kpi-meta"><span class="delta-up">+4,8%</span> dalam 30 hari</div></div>
                        <div class="card card-pad"><div class="kpi-label">Project perlu perhatian</div><div class="kpi-value" style="color:var(--red)">4</div><div class="kpi-meta"><span class="delta-down">2 melewati threshold SPI</span></div></div>
                        <div class="card card-pad"><div class="kpi-label">Material siap dipakai</div><div class="kpi-value">81%</div><div class="kpi-meta">Rp 8,4 M nilai RAB aktif</div></div>
                    </div>
                    <div class="portfolio-layout">
                        <div class="portfolio-left">
                            <div class="card chart-card">
                                <div class="card-head"><div><h2>Tren portofolio</h2><div class="legend"><span><i></i>Realisasi jasa</span><span><i class="target"></i>Target kumulatif</span><span><i class="risk"></i>Project berisiko</span></div></div><span class="badge badge-green">Q3 · +4,8%</span></div>
                                <svg class="chart" viewBox="0 0 760 235" role="img" aria-label="Tren realisasi jasa portofolio dan target kumulatif"><path class="chart-grid" d="M48 28H740 M48 82H740 M48 136H740 M48 190H740"/><path class="chart-target" d="M48 190 C136 184 188 164 270 145 S400 105 485 78 S625 45 740 28"/><path class="chart-portfolio" d="M48 190 C138 188 192 176 270 155 S397 125 485 102 S614 78 740 68"/><circle class="chart-dot" cx="270" cy="155" r="5"/><circle class="chart-dot" cx="485" cy="102" r="5"/><circle class="chart-dot" cx="740" cy="68" r="5"/><text class="chart-axis" x="8" y="194">0%</text><text class="chart-axis" x="7" y="140">40%</text><text class="chart-axis" x="7" y="86">80%</text><text class="chart-axis" x="8" y="32">100%</text><text class="chart-axis" x="48" y="216">Apr</text><text class="chart-axis" x="260" y="216">Mei</text><text class="chart-axis" x="475" y="216">Jun</text><text class="chart-axis" x="670" y="216">Jul</text></svg>
                            </div>
                            <div class="card card-pad">
                                <div class="card-head"><div><p class="eyebrow">Health matrix</p><h2 style="margin-bottom:0">Project yang perlu dibaca</h2></div><button class="btn" data-action="projects">Lihat semua 24</button></div>
                                <table class="health-table"><thead><tr><th>Project</th><th>Progress</th><th>SPI</th><th>Material</th><th>Status</th></tr></thead><tbody>
                                    <tr><td><button class="table-link" data-project="PRJ-2604-0017"><strong>PRJ-2604-0017</strong><small>FTTH Kediri Barat</small></button></td><td>62,4%</td><td><span class="badge badge-amber">0,94</span></td><td>78%</td><td><span class="badge badge-amber">Waspada</span></td></tr>
                                    <tr><td><button class="table-link" data-project="PRJ-2605-0021"><strong>PRJ-2605-0021</strong><small>Metro Sidoarjo Timur</small></button></td><td>41,8%</td><td><span class="badge badge-red">0,82</span></td><td>59%</td><td><span class="badge badge-red">Risiko tinggi</span></td></tr>
                                    <tr><td><button class="table-link" data-project="PRJ-2606-0034"><strong>PRJ-2606-0034</strong><small>Backbone Malang Selatan</small></button></td><td>74,1%</td><td><span class="badge badge-green">1,03</span></td><td>92%</td><td><span class="badge badge-green">On track</span></td></tr>
                                    <tr><td><button class="table-link" data-project="PRJ-2606-0040"><strong>PRJ-2606-0040</strong><small>FTTH Madiun Kota</small></button></td><td>28,5%</td><td><span class="badge badge-amber">0,91</span></td><td>66%</td><td><span class="badge badge-amber">Waspada</span></td></tr>
                                </tbody></table>
                            </div>
                        </div>
                        <div class="portfolio-right">
                            <div class="card card-pad"><div class="card-head"><div><p class="eyebrow">Decision queue</p><h2 style="margin-bottom:0">Yang membutuhkan perhatian</h2></div><span class="badge badge-red">4 open</span></div><div class="risk-stack"><div class="risk-row"><i class="risk-dot red"></i><div><strong>SPI di bawah 0,9</strong><small>2 Project · owner perlu update recovery plan</small></div><span class="badge badge-red">2</span></div><div class="risk-row"><i class="risk-dot"></i><div><strong>Transit melewati 3 hari</strong><small>SJ-2608-0041 · Kediri Barat</small></div><span class="badge badge-amber">1</span></div><div class="risk-row"><i class="risk-dot"></i><div><strong>Material belum lengkap</strong><small>3 Project · readiness di bawah 70%</small></div><span class="badge badge-amber">3</span></div><div class="risk-row"><i class="risk-dot green"></i><div><strong>Export siap dikirim</strong><small>Laporan mingguan · dibuat 08:55</small></div><span class="badge badge-green">OK</span></div></div><button class="btn btn-primary" style="width:100%;margin-top:16px" data-action="queue">Buka antrean keputusan</button></div>
                            <div class="card card-pad"><p class="eyebrow">Distribusi status</p><h2>24 Project aktif</h2><div class="distribution"><div class="distribution-row"><span>On track</span><div class="progress-line"><span style="width:67%"></span></div><strong>16</strong></div><div class="distribution-row"><span>Waspada</span><div class="progress-line amber"><span style="width:17%"></span></div><strong>4</strong></div><div class="distribution-row"><span>Risiko tinggi</span><div class="progress-line red"><span style="width:8%"></span></div><strong>2</strong></div><div class="distribution-row"><span>Pending</span><div class="progress-line" style="background:#eeeafd"><span style="width:8%;background:var(--violet)"></span></div><strong>2</strong></div></div></div>
                        </div>
                    </div>
                    <div class="card card-pad" style="margin-top:16px"><div class="card-head"><div><p class="eyebrow">Linimasa gabungan</p><h2 style="margin-bottom:0">Perubahan terbaru di portofolio</h2></div><button class="btn" data-action="activity">Buka activity log</button></div><div class="activity-strip"><div class="activity-cell"><time>09:42 · hari ini</time><p><strong>PRJ-2604-0017</strong> menerima 4 foto pekerjaan baru untuk verifikasi.</p></div><div class="activity-cell"><time>08:55 · hari ini</time><p>Export <strong>Portfolio Weekly</strong> selesai dan siap dibagikan.</p></div><div class="activity-cell"><time>16:10 · kemarin</time><p><strong>PRJ-2605-0021</strong> turun ke SPI 0,82. Recovery plan diminta.</p></div></div></div>
                </main>
            </div>
        </div>
    </section>

    <section class="variant variant-b" data-variant="risks">
        <header class="topbar"><div class="brand">THC<span>/PMS</span></div><div class="topbar-right"><span>Risk desk · update 2 menit lalu</span><div class="avatar">TH</div></div></header>
        <main class="page risk-desk-page">
            <div class="risk-heading"><div><p class="eyebrow">Gelombang 3 / Exception management</p><h1>Risk Desk</h1><p class="subtle">Antrean pengecualian lintas Project. Setiap item punya sumber data dan pemilik tindak lanjut.</p></div><div class="risk-score"><span>Portfolio risk score</span><strong>62 / 100</strong><small>Perlu perhatian minggu ini</small></div></div>
            <div class="dark-filters"><div class="filter-row" data-filter-group="risk" style="margin:0"><span class="filter-label" style="color:#a7b7b6">Filter antrean:</span><button class="filter active" data-filter="Semua risiko">Semua risiko</button><button class="filter" data-filter="High">High</button><button class="filter" data-filter="SPI">SPI</button><button class="filter" data-filter="Material">Material</button><button class="filter" data-filter="Transit">Transit</button></div><button class="btn btn-dark" data-action="save-filter">Simpan filter</button></div>
            <div class="risk-board">
                <div class="triage-panel"><div class="triage-head"><h2>Antrean triage</h2><span class="triage-count">7 item · diurutkan berdasarkan urgensi</span></div>
                    <article class="risk-item selected"><span class="risk-level high">HIGH</span><div><h3>SPI 0,82 · Metro Sidoarjo Timur</h3><p>Realisasi tertinggal dari Revised Baseline sebesar 11,4%. Tidak ada recovery plan sejak 12 Agustus.</p><small>PRJ-2605-0021 · Owner: Dimas PM · jatuh tempo 16 Agu</small></div><button class="btn" data-action="open-risk" data-project="PRJ-2605-0021">Buka sumber</button></article>
                    <article class="risk-item"><span class="risk-level high">HIGH</span><div><h3>Transit 4 hari · SJ-2608-0041</h3><p>Material sudah keluar dari Warehouse THC tetapi belum diterima di Warehouse mitra.</p><small>PRJ-2604-0017 · Owner: Operasional Gudang · jatuh tempo hari ini</small></div><button class="btn" data-action="open-transit" data-project="SJ-2608-0041">Buka Surat Jalan</button></article>
                    <article class="risk-item"><span class="risk-level watch">WATCH</span><div><h3>Material readiness 59%</h3><p>Delivery berikutnya berjarak 9 hari, tetapi 18 item RAB belum terpenuhi atau masih Transit.</p><small>PRJ-2605-0021 · Owner: Supply · review 18 Agu</small></div><button class="btn" data-action="open-material" data-project="PRJ-2605-0021">Buka material</button></article>
                    <article class="risk-item"><span class="risk-level watch">WATCH</span><div><h3>TOC mendekat · 3 Project</h3><p>Tanggal TOC dalam 30 hari tanpa progres terverifikasi pada minggu berjalan.</p><small>Portfolio · Owner: PMO · review 19 Agu</small></div><button class="btn" data-action="open-toc">Lihat Project</button></article>
                    <article class="risk-item"><span class="risk-level low">LOW</span><div><h3>4 foto menunggu verifikasi</h3><p>Bukti pekerjaan sudah masuk dan tidak mengubah angka realisasi sebelum diverifikasi THC.</p><small>PRJ-2604-0017 · Owner: THC Admin · review 16 Agu</small></div><button class="btn" data-action="open-evidence">Buka bukti</button></article>
                </div>
                <aside class="briefing"><div class="briefing-card"><p class="eyebrow">Tindakan berikutnya</p><h2>Briefing untuk rapat Senin</h2><div class="briefing-row"><span>Item high</span><strong>2 belum punya owner</strong></div><div class="briefing-row"><span>Material transit</span><strong>Rp 412 jt</strong></div><div class="briefing-row"><span>Project terdampak</span><strong>5 dari 24</strong></div><button class="btn btn-primary" data-action="export-risk">Export briefing</button></div><div class="briefing-card"><p class="eyebrow">Kepemilikan</p><h2>Siapa yang perlu dihubungi</h2><div class="owner-list"><div class="owner"><span>DP</span>Dimas PM <em>2 item</em></div><div class="owner"><span>OG</span>Operasional Gudang <em>1 item</em></div><div class="owner"><span>SP</span>Supply Project <em>2 item</em></div><div class="owner"><span>TA</span>THC Admin <em>2 item</em></div></div></div><div class="briefing-card"><p class="eyebrow">Batas tampilan</p><h2>Read-only by design</h2><p class="subtle">Risk Desk hanya mengarahkan ke sumber. Perubahan status tetap dilakukan di modul pemiliknya.</p><button class="btn" data-action="permissions">Lihat aturan akses</button></div></aside>
            </div>
        </main>
    </section>

    <section class="variant variant-c" data-variant="reports">
        <header class="topbar"><div class="brand">THC<span>/PMS</span></div><div class="topbar-right"><span>Report Studio · read-only</span><div class="avatar">TH</div></div></header>
        <main class="studio-page">
            <div class="studio-heading"><div><p class="eyebrow">Gelombang 3 / Export + API</p><h1>Report Studio</h1><p class="subtle">Susun laporan portofolio, preview hasil agregasi, lalu pilih cara membagikannya.</p></div><div class="topline-actions"><button class="btn" data-action="save-report">Simpan tampilan</button><button class="btn btn-primary" data-action="export-report">Generate export</button></div></div>
            <div class="studio-shell">
                <nav class="studio-nav" aria-label="Report Studio navigation">
                    <h3>Laporan saya</h3>
                    <button class="active" data-tab="weekly"><span>Portfolio Weekly</span><small>24</small></button>
                    <button data-tab="risk"><span>Risk Briefing</span><small>7</small></button>
                    <button data-tab="material"><span>Material Readiness</span><small>18</small></button>
                    <div class="nav-divider"></div>
                    <h3>Data views</h3>
                    <button data-tab="projects"><span>Projects</span><small>view</small></button>
                    <button data-tab="progress"><span>Progress & SPI</span><small>view</small></button>
                    <button data-tab="inventory"><span>Transit & Stock</span><small>view</small></button>
                    <div class="nav-divider"></div>
                    <button data-action="new-report"><span>+ Laporan baru</span></button>
                </nav>
                <div class="studio-main">
                    <div class="report-toolbar"><h2>Portfolio Weekly</h2><div class="toolbar-controls"><button class="select-like" data-action="date-range">01 - 15 Agu 2026</button><button class="select-like" data-action="scope">Semua Mitra</button><button class="select-like" data-action="columns">Pilih kolom</button></div></div>
                    <div class="report-canvas">
                        <div class="report-metric-row"><div class="report-metric"><span>Project aktif</span><strong>24</strong></div><div class="report-metric"><span>Realisasi rata-rata</span><strong>68,2%</strong></div><div class="report-metric"><span>SPI median</span><strong>0,96</strong></div></div>
                        <div class="report-chart"><div class="report-chart-head"><h3>Realisasi jasa per minggu</h3><small>Rupiah bobot jasa · data terverifikasi</small></div><svg class="chart" viewBox="0 0 760 235" role="img" aria-label="Grafik realisasi jasa per minggu"><path class="chart-grid" d="M48 28H740 M48 82H740 M48 136H740 M48 190H740"/><path class="chart-target" d="M48 190 C166 180 212 153 320 132 S490 82 740 31"/><path class="chart-portfolio" d="M48 190 C150 187 223 171 320 150 S486 119 575 93 S668 77 740 68"/><circle class="chart-dot" cx="320" cy="150" r="5"/><circle class="chart-dot" cx="575" cy="93" r="5"/><circle class="chart-dot" cx="740" cy="68" r="5"/><text class="chart-axis" x="8" y="194">0%</text><text class="chart-axis" x="7" y="140">40%</text><text class="chart-axis" x="7" y="86">80%</text><text class="chart-axis" x="8" y="32">100%</text><text class="chart-axis" x="48" y="216">Minggu 1</text><text class="chart-axis" x="260" y="216">Minggu 2</text><text class="chart-axis" x="475" y="216">Minggu 3</text><text class="chart-axis" x="670" y="216">Minggu 4</text></svg></div>
                        <table class="studio-table"><thead><tr><th>Project</th><th>Mitra</th><th>Progress</th><th>SPI</th><th>Material</th><th>Last activity</th></tr></thead><tbody><tr><td><strong>PRJ-2604-0017</strong></td><td>Nusantara Fiber</td><td>62,4%</td><td>0,94</td><td>78%</td><td>09:42</td></tr><tr><td><strong>PRJ-2605-0021</strong></td><td>Jatim Kabel</td><td>41,8%</td><td style="color:var(--red);font-weight:800">0,82</td><td>59%</td><td>kemarin</td></tr><tr><td><strong>PRJ-2606-0034</strong></td><td>Metro Komunika</td><td>74,1%</td><td style="color:#178367;font-weight:800">1,03</td><td>92%</td><td>14 Agu</td></tr><tr><td><strong>PRJ-2606-0040</strong></td><td>Nusantara Fiber</td><td>28,5%</td><td>0,91</td><td>66%</td><td>13 Agu</td></tr></tbody></table>
                    </div>
                </div>
                <aside class="studio-side"><div class="delivery-card"><p class="eyebrow">Delivery</p><h3>Bagikan laporan</h3><p>Hasil preview akan memakai filter dan kolom yang sedang aktif.</p><div class="delivery-list"><div class="delivery-item"><span class="check">✓</span>Excel workbook<strong>XLSX</strong></div><div class="delivery-item"><span class="check">✓</span>PDF ringkas<strong>PDF</strong></div><div class="delivery-item"><span class="check" style="background:var(--violet)">↗</span>Link read-only<strong>URL</strong></div></div><button class="btn btn-primary" data-action="download">Download preview</button></div><div class="delivery-card"><p class="eyebrow">API read-only</p><h3>Endpoint aktif</h3><div class="api-box"><span class="method">GET</span><br><span class="path">/api/v1/projects</span><br>?status=aktif<br>&amp;include=progress,spi</div><button class="btn" data-action="copy-api">Copy endpoint</button><button class="btn" data-action="api-docs">Lihat API contract</button></div><div class="delivery-card"><p class="eyebrow">Terakhir dikirim</p><h3>Scheduled delivery</h3><div class="delivery-row"><div class="briefing-row"><span>Portfolio Weekly</span><strong>Senin · 08:00</strong></div><div class="briefing-row"><span>Risk Briefing</span><strong>Manual</strong></div></div><button class="btn" data-action="schedule">Atur pengiriman</button></div></aside>
            </div>
        </main>
    </section>

    <nav class="switcher" aria-label="Prototype variants"><button id="prev" aria-label="Variant sebelumnya">&lt;</button><div class="switcher-label" id="variant-label">A - Portfolio cockpit<small>Left / Right untuk berganti tampilan</small></div><button id="next" aria-label="Variant berikutnya">&gt;</button></nav>

    <script>
        const variants = [
            { key: 'portfolio', label: 'A - Portfolio cockpit', name: 'Ringkasan kesehatan lintas Project' },
            { key: 'risks', label: 'B - Risk desk', name: 'Antrean triage berbasis pengecualian' },
            { key: 'reports', label: 'C - Report studio', name: 'Export dan API read-only' },
        ];
        const initial = new URLSearchParams(window.location.search).get('variant');
        let index = Math.max(0, variants.findIndex((variant) => variant.key === initial));
        const state = { filter: 'Semua Project', selected: '-', last: 'Prototype dimuat' };
        const toast = document.getElementById('toast');
        const stateNode = document.getElementById('prototype-state');

        function renderState() {
            const variant = variants[index];
            stateNode.textContent = `state: variant=${variant.key} | filter=${state.filter} | selected=${state.selected} | last=${state.last}`;
        }

        function setVariant(nextIndex) {
            index = (nextIndex + variants.length) % variants.length;
            const variant = variants[index];
            document.querySelectorAll('.variant').forEach((node) => node.classList.toggle('active', node.dataset.variant === variant.key));
            document.getElementById('variant-label').innerHTML = `${variant.label}<small>${variant.name}</small>`;
            const url = new URL(window.location.href);
            url.searchParams.set('variant', variant.key);
            window.history.replaceState({}, '', url);
            state.last = `Beralih ke ${variant.label}`;
            renderState();
        }

        function notify(message) {
            state.last = message;
            renderState();
            toast.textContent = `Simulasi: ${message}`;
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

        document.querySelectorAll('[data-filter]').forEach((button) => button.addEventListener('click', () => {
            button.closest('[data-filter-group]').querySelectorAll('[data-filter]').forEach((item) => item.classList.toggle('active', item === button));
            state.filter = button.dataset.filter;
            notify(`Filter diubah ke ${state.filter}`);
        }));

        document.querySelectorAll('[data-tab]').forEach((button) => button.addEventListener('click', () => {
            button.closest('.studio-nav').querySelectorAll('[data-tab]').forEach((item) => item.classList.toggle('active', item === button));
            notify(`Data view ${button.querySelector('span')?.textContent || button.dataset.tab} dipilih`);
        }));

        document.querySelectorAll('[data-project]').forEach((button) => button.addEventListener('click', () => {
            state.selected = button.dataset.project;
            notify(`Sumber ${state.selected} dibuka`);
        }));

        const messages = {
            refresh: 'Data portfolio di-refresh tanpa persistence',
            export: 'Export ringkasan dijadwalkan',
            projects: 'Daftar seluruh Project dibuka',
            queue: 'Antrean keputusan dibuka',
            activity: 'Activity log lintas Project dibuka',
            'save-filter': 'Filter tersimpan di memori prototype',
            'open-risk': 'Detail risiko dan recovery plan dibuka',
            'open-transit': 'Surat Jalan sumber dibuka',
            'open-material': 'Kesiapan material Project dibuka',
            'open-toc': 'Daftar Project dengan TOC dekat dibuka',
            'open-evidence': 'Bukti pekerjaan yang menunggu dibuka',
            'export-risk': 'Briefing risiko diekspor sebagai PDF',
            permissions: 'Matriks Izin Aksi dibuka',
            'save-report': 'Konfigurasi laporan disimpan di memori prototype',
            'export-report': 'Export dibuat dari preview aktif',
            'date-range': 'Pemilih rentang tanggal dibuka',
            scope: 'Pemilih scope Mitra dibuka',
            columns: 'Pemilih kolom dibuka',
            download: 'Preview workbook dan PDF disiapkan',
            'copy-api': 'Endpoint API disalin secara simulasi',
            'api-docs': 'API contract dibuka',
            schedule: 'Pengaturan scheduled delivery dibuka',
            'new-report': 'Canvas laporan baru dibuka',
        };
        document.querySelectorAll('[data-action]').forEach((button) => button.addEventListener('click', () => notify(messages[button.dataset.action] || 'Aksi prototype dijalankan')));
        setVariant(index);
    </script>
</body>
</html>
