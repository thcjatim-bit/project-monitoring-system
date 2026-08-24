{{--
    PROTOTYPE THROWAWAY - Issue #148.

    Design question:
    Which foundation feels most intuitive and least expensive to adapt to PMS
    when the same operational read models are shown on Dashboard Mitra,
    Project Control Room, and Portfolio Cockpit?

    Candidates:
    - Flowbite components: local, component-first, existing App shell retained.
    - TailAdmin composition: template-first, spacious dashboard composition.

    This file uses no Flowbite/TailAdmin code or external asset. It is only a
    visual comparison with static fixtures. URL examples:
    /prototype/issue-148-ui?variant=flowbite&screen=mitra
    /prototype/issue-148-ui?variant=tailadmin&screen=project&persona=mitra
--}}
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prototype #148 - Fondasi UI Dashboard PMS</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #172b3a;
            --muted: #657683;
            --line: #d9e2e6;
            --paper: #f3f7f8;
            --white: #fff;
            --navy: #123249;
            --navy-2: #1b4760;
            --teal: #087f8c;
            --teal-soft: #dff3ef;
            --amber: #a15d0c;
            --amber-soft: #fff2d7;
            --red: #b43b3b;
            --red-soft: #ffe5e2;
            --slate-soft: #eef3f5;
            --shadow: 0 12px 32px rgb(18 50 73 / 8%);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--paper);
            color: var(--ink);
        }

        * { box-sizing: border-box; }
        html { min-width: 320px; }
        body { margin: 0; min-height: 100vh; background: var(--paper); }
        body.drawer-open { overflow: hidden; }
        button, input, select { font: inherit; }
        button { cursor: pointer; }
        button:focus-visible, a:focus-visible, select:focus-visible {
            outline: 3px solid #f3bf55;
            outline-offset: 2px;
        }
        a { color: inherit; }
        h1, h2, h3, p { margin-top: 0; }
        h1 { margin-bottom: 8px; font-size: clamp(27px, 4vw, 44px); letter-spacing: -.065em; line-height: 1.02; }
        h2 { margin-bottom: 7px; font-size: 18px; letter-spacing: -.035em; }
        h3 { margin-bottom: 6px; font-size: 14px; }
        table { border-collapse: collapse; }

        .prototype-ribbon {
            position: fixed;
            z-index: 80;
            top: 12px;
            right: 16px;
            padding: 7px 11px;
            border-radius: 999px;
            color: #4d3200;
            background: #fdc858;
            box-shadow: 0 5px 16px rgb(77 50 0 / 16%);
            font-size: 11px;
            font-weight: 850;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .app-shell {
            display: grid;
            grid-template-columns: 236px minmax(0, 1fr);
            min-height: 100vh;
        }

        .sidebar {
            padding: 24px 16px;
            color: #c5d6de;
            background: var(--navy);
        }

        .brand {
            display: block;
            padding: 0 10px 25px;
            color: var(--white);
            font-size: 20px;
            font-weight: 900;
            letter-spacing: -.06em;
            text-decoration: none;
        }
        .brand span { color: #72d4c7; }
        .candidate-note { margin: -12px 10px 22px; color: #8eabb8; font-size: 11px; line-height: 1.45; }
        .sidebar-label { margin: 0 10px 7px; color: #7f9eac; font-size: 10px; font-weight: 850; letter-spacing: .11em; text-transform: uppercase; }
        .side-nav { display: grid; gap: 3px; }
        .side-nav button {
            display: flex;
            align-items: center;
            gap: 9px;
            min-height: 42px;
            width: 100%;
            padding: 9px 10px;
            border: 0;
            border-radius: 8px;
            color: #bdd0d9;
            background: transparent;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
        }
        .side-nav button:hover,
        .side-nav button[aria-current="page"] { color: var(--white); background: rgb(255 255 255 / 13%); }
        .side-nav button[aria-current="page"] { box-shadow: inset 3px 0 #72d4c7; }
        .side-nav .nav-mark { display: grid; width: 23px; height: 23px; place-items: center; border: 1px solid currentColor; border-radius: 6px; font-size: 10px; }
        .sidebar-foot {
            margin: 42px 10px 0;
            padding-top: 16px;
            border-top: 1px solid rgb(255 255 255 / 15%);
            color: #8eabb8;
            font-size: 11px;
            line-height: 1.55;
        }
        .sidebar-foot strong { display: block; margin-bottom: 4px; color: #dce9ed; }

        .workspace { min-width: 0; }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            min-height: 72px;
            padding: 0 30px;
            border-bottom: 1px solid var(--line);
            background: var(--white);
        }
        .breadcrumb { display: flex; flex-wrap: wrap; gap: 7px; align-items: center; color: var(--muted); font-size: 12px; }
        .breadcrumb strong { color: var(--ink); }
        .topbar-right { display: flex; align-items: center; gap: 13px; color: var(--muted); font-size: 11px; }
        .avatar { display: grid; width: 34px; height: 34px; place-items: center; border-radius: 50%; color: var(--white); background: var(--navy); font-size: 12px; font-weight: 850; }
        .topbar-right strong { display: block; color: var(--ink); font-size: 12px; }
        .topbar-right small { display: block; margin-top: 2px; }

        .main { max-width: 1480px; margin: 0 auto; padding: 29px 30px 150px; }
        .intro {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 25px;
            margin-bottom: 18px;
        }
        .eyebrow { margin-bottom: 8px; color: var(--teal); font-size: 10px; font-weight: 900; letter-spacing: .13em; text-transform: uppercase; }
        .intro p { max-width: 780px; margin-bottom: 0; color: var(--muted); font-size: 13px; line-height: 1.55; }
        .intro-side { flex: 0 0 auto; min-width: 215px; padding: 12px 14px; border: 1px dashed #b8cbd0; border-radius: 10px; color: #45616b; background: #f8fcfc; font-size: 11px; line-height: 1.45; }
        .intro-side strong { display: block; margin-bottom: 3px; color: var(--ink); }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px 16px;
            margin-bottom: 19px;
            padding: 11px 13px;
            border: 1px solid var(--line);
            border-radius: 11px;
            background: var(--white);
            box-shadow: 0 5px 17px rgb(18 50 73 / 4%);
        }
        .toolbar-group { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
        .toolbar-label { margin-right: 2px; color: var(--muted); font-size: 10px; font-weight: 850; letter-spacing: .06em; text-transform: uppercase; }
        .toolbar button {
            min-height: 34px;
            padding: 7px 10px;
            border: 1px solid var(--line);
            border-radius: 7px;
            color: var(--ink);
            background: var(--white);
            font-size: 11px;
            font-weight: 750;
        }
        .toolbar button:hover, .toolbar button[aria-pressed="true"] { border-color: var(--teal); color: var(--teal); background: var(--teal-soft); }
        .toolbar .state-button[data-state="loading"][aria-pressed="true"] { border-color: #5893ae; color: #155e75; background: #e4f4fb; }
        .toolbar .state-button[data-state="empty"][aria-pressed="true"] { border-color: #9cabb2; color: #4b5e69; background: #edf2f4; }
        .toolbar .state-button[data-state="error"][aria-pressed="true"] { border-color: #d7847b; color: var(--red); background: var(--red-soft); }
        .viewport-readout { margin-left: auto; color: var(--muted); font-size: 11px; white-space: nowrap; }
        .viewport-readout strong { color: var(--ink); }

        .screen-section { min-width: 0; }
        .screen-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; margin-bottom: 18px; }
        .screen-head p { margin: 0; color: var(--muted); font-size: 12px; line-height: 1.5; }
        .screen-head-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 7px; }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 8px 12px;
            border: 1px solid var(--line);
            border-radius: 7px;
            color: var(--ink);
            background: var(--white);
            font-size: 11px;
            font-weight: 800;
            text-decoration: none;
        }
        .button:hover { border-color: var(--teal); color: var(--teal); }
        .button-primary { border-color: var(--teal); color: var(--white); background: var(--teal); }
        .button-primary:hover { color: var(--white); background: #066d76; }
        .button[disabled] { cursor: not-allowed; opacity: .55; }
        .button[disabled]:hover { border-color: var(--line); color: var(--ink); }

        .candidate-pill { display: inline-flex; align-items: center; gap: 7px; margin-bottom: 10px; padding: 5px 8px; border-radius: 6px; color: #3c5964; background: var(--slate-soft); font-size: 10px; }
        .candidate-pill .marker { width: 7px; height: 7px; border-radius: 50%; background: var(--teal); }
        .candidate-pill strong { color: var(--ink); }

        .panel { min-width: 0; padding: 19px; border: 1px solid var(--line); border-radius: 12px; background: var(--white); box-shadow: var(--shadow); }
        .panel + .panel { margin-top: 16px; }
        .panel-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; margin-bottom: 14px; }
        .panel-head h2 { margin-bottom: 3px; }
        .panel-head p { margin: 0; color: var(--muted); font-size: 11px; line-height: 1.45; }
        .panel-count { color: var(--muted); font-size: 11px; white-space: nowrap; }
        .panel-note { margin: 9px 0 0; color: var(--muted); font-size: 10px; line-height: 1.45; }

        .badge { display: inline-flex; align-items: center; gap: 5px; min-height: 24px; padding: 4px 8px; border-radius: 999px; font-size: 10px; font-weight: 850; white-space: nowrap; }
        .badge::before { width: 6px; height: 6px; border-radius: 50%; background: currentColor; content: ""; }
        .badge-red { color: var(--red); background: var(--red-soft); }
        .badge-amber { color: var(--amber); background: var(--amber-soft); }
        .badge-green { color: #147362; background: var(--teal-soft); }
        .badge-slate { color: #596c76; background: var(--slate-soft); }
        .badge-blue { color: #216888; background: #e2f2fa; }

        .metric-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 11px; margin-bottom: 16px; }
        .metric { min-width: 0; padding: 15px; border: 1px solid var(--line); border-radius: 10px; background: var(--white); }
        .metric-label { display: block; color: var(--muted); font-size: 10px; line-height: 1.35; }
        .metric-value { display: block; margin: 7px 0 4px; color: var(--navy); font-size: 27px; font-weight: 900; letter-spacing: -.06em; }
        .metric-meta { display: block; color: var(--muted); font-size: 10px; }
        .metric-meta.good { color: #147362; font-weight: 750; }
        .metric-meta.warn { color: var(--amber); font-weight: 750; }
        .metric-meta.danger { color: var(--red); font-weight: 750; }

        .two-column { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(280px, .65fr); gap: 16px; align-items: start; }
        .two-column > * { min-width: 0; }
        .three-column { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }

        .table-wrap { overflow-x: auto; }
        .data-table { width: 100%; min-width: 640px; font-size: 11px; }
        .data-table th { padding: 9px 8px; border-bottom: 1px solid var(--line); color: var(--muted); font-size: 9px; letter-spacing: .08em; text-align: left; text-transform: uppercase; }
        .data-table td { padding: 12px 8px; border-bottom: 1px solid #edf1f2; vertical-align: middle; }
        .data-table tr:last-child td { border-bottom: 0; }
        .data-table tr:hover td { background: #f8fbfb; }
        .data-table strong { display: block; color: var(--ink); }
        .data-table small { display: block; margin-top: 3px; color: var(--muted); font-size: 10px; }
        .empty-state { padding: 18px 8px; color: var(--muted); text-align: center; font-size: 11px; }
        .project-link { padding: 0; border: 0; color: var(--navy); background: transparent; text-align: left; font-size: 11px; font-weight: 850; }
        .project-link:hover { color: var(--teal); }
        .text-right { text-align: right; }
        .progress-line { height: 7px; overflow: hidden; border-radius: 99px; background: #e6edef; }
        .progress-line span { display: block; height: 100%; border-radius: inherit; background: var(--teal); }
        .progress-line.amber span { background: #d59b36; }
        .progress-line.red span { background: var(--red); }
        .progress-label { display: flex; justify-content: space-between; gap: 8px; margin-bottom: 6px; color: var(--muted); font-size: 10px; }
        .progress-label strong { color: var(--ink); }

        .activity-list, .decision-list, .step-list, .readiness-list { display: grid; gap: 9px; list-style: none; margin: 0; padding: 0; }
        .activity-item { padding: 11px 12px; border-left: 3px solid var(--teal); background: #f7fbfb; }
        .activity-item strong { display: block; margin-bottom: 3px; font-size: 11px; }
        .activity-item p { margin: 0; color: var(--muted); font-size: 10px; line-height: 1.45; }
        .activity-item time { display: block; margin-top: 5px; color: #8a9aa1; font-size: 9px; }
        .decision-item { padding: 12px; border-left: 4px solid #d99d37; background: #fffaf0; }
        .decision-item.high { border-left-color: var(--red); background: #fff5f3; }
        .decision-item h3 { margin-bottom: 4px; font-size: 12px; }
        .decision-item p { margin: 0; color: var(--muted); font-size: 10px; line-height: 1.45; }
        .decision-meta { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 7px; }
        .mini-tag { display: inline-flex; padding: 4px 7px; border-radius: 5px; color: #45616b; background: #eaf1f2; font-size: 9px; font-weight: 800; }
        .mini-tag.high { color: var(--red); background: var(--red-soft); }

        .risk-filter { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
        .risk-filter button { padding: 7px 9px; border: 1px solid var(--line); border-radius: 999px; color: var(--muted); background: var(--white); font-size: 10px; font-weight: 800; }
        .risk-filter button:hover, .risk-filter button[aria-pressed="true"] { border-color: var(--teal); color: var(--teal); background: var(--teal-soft); }
        .risk-filter-label { align-self: center; margin-right: 2px; color: var(--muted); font-size: 10px; font-weight: 800; }

        .chart { width: 100%; height: 198px; }
        .chart-grid { stroke: #e6edef; stroke-width: 1; }
        .chart-target { fill: none; stroke: #9aabb1; stroke-width: 2; stroke-dasharray: 5 5; }
        .chart-actual { fill: none; stroke: var(--teal); stroke-width: 4; stroke-linecap: round; stroke-linejoin: round; }
        .chart-axis { fill: #8a9aa1; font-size: 10px; }
        .chart-dot { fill: var(--white); stroke: var(--teal); stroke-width: 3; }
        .legend { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 10px; color: var(--muted); font-size: 10px; }
        .legend i { display: inline-block; width: 16px; height: 3px; margin-right: 4px; vertical-align: middle; border-radius: 4px; background: var(--teal); }
        .legend i.target { height: 0; border-top: 2px dashed #9aabb1; background: transparent; }

        .project-hero { display: grid; grid-template-columns: minmax(0, 1fr) minmax(210px, .42fr); gap: 18px; align-items: center; margin-bottom: 16px; padding: 20px; border: 1px solid var(--line); border-radius: 12px; background: var(--white); box-shadow: var(--shadow); }
        .project-id { margin-bottom: 8px; color: var(--teal); font-size: 10px; font-weight: 900; letter-spacing: .1em; }
        .project-hero h2 { margin-bottom: 5px; font-size: 23px; }
        .project-hero p { margin-bottom: 9px; color: var(--muted); font-size: 11px; }
        .project-hero-side { padding-left: 18px; border-left: 1px solid var(--line); }
        .project-hero-side strong { display: block; margin: 5px 0; color: var(--navy); font-size: 34px; letter-spacing: -.07em; }
        .project-hero-side small { color: var(--muted); font-size: 10px; }
        .step-list { position: relative; }
        .step-list::before { position: absolute; top: 10px; bottom: 10px; left: 8px; width: 1px; background: #c7d7db; content: ""; }
        .step-item { position: relative; display: grid; grid-template-columns: 17px minmax(0, 1fr) auto; gap: 9px; align-items: start; }
        .step-dot { z-index: 1; width: 17px; height: 17px; border: 3px solid var(--white); border-radius: 50%; background: #b9cbd0; box-shadow: 0 0 0 1px #b9cbd0; }
        .step-item.done .step-dot { background: var(--teal); box-shadow: 0 0 0 1px var(--teal); }
        .step-item.current .step-dot { background: #e4a13d; box-shadow: 0 0 0 1px #e4a13d; }
        .step-item strong { display: block; font-size: 11px; }
        .step-item small { display: block; margin-top: 3px; color: var(--muted); font-size: 10px; line-height: 1.35; }
        .step-status { color: var(--muted); font-size: 9px; white-space: nowrap; }
        .step-status.done { color: #147362; }
        .step-status.current { color: var(--amber); font-weight: 800; }

        .state-banner { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 14px; padding: 12px 14px; border: 1px solid #b9d7e4; border-radius: 9px; color: #155e75; background: #e8f6fb; font-size: 11px; line-height: 1.45; }
        .state-banner strong { display: block; margin-bottom: 2px; }
        .state-banner.empty { border-color: #cbd6da; color: #4b5e69; background: #f0f4f5; }
        .state-banner.error { border-color: #e8aaa4; color: #8b2e2e; background: var(--red-soft); }
        .state-banner.loading { border-color: #b9d7e4; color: #155e75; background: #e8f6fb; }
        .state-banner .state-icon { flex: 0 0 auto; display: grid; width: 22px; height: 22px; place-items: center; border-radius: 50%; color: currentColor; background: rgb(255 255 255 / 55%); font-weight: 900; }

        .walkthrough {
            margin-top: 21px;
            padding: 17px;
            border: 1px dashed #aec5cb;
            border-radius: 12px;
            background: #f8fcfc;
        }
        .walkthrough-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
        .walkthrough h2 { margin-bottom: 3px; font-size: 15px; }
        .walkthrough p { margin: 0; color: var(--muted); font-size: 10px; line-height: 1.45; }
        .walkthrough-status { color: #147362; font-size: 10px; font-weight: 850; white-space: nowrap; }
        .walkthrough-steps { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 7px; }
        .walkthrough-step { min-height: 70px; padding: 9px; border: 1px solid #d6e3e5; border-radius: 8px; color: var(--ink); background: var(--white); text-align: left; }
        .walkthrough-step:hover { border-color: var(--teal); }
        .walkthrough-step[aria-current="step"] { border-color: var(--teal); box-shadow: inset 0 3px var(--teal); }
        .walkthrough-step strong { display: block; margin-bottom: 4px; color: var(--teal); font-size: 10px; }
        .walkthrough-step span { display: block; color: var(--muted); font-size: 10px; line-height: 1.35; }
        .walkthrough-note { margin-top: 11px !important; color: #45616b !important; }

        .floating-switcher {
            position: fixed;
            z-index: 70;
            bottom: 18px;
            left: 50%;
            display: flex;
            align-items: center;
            gap: 9px;
            transform: translateX(-50%);
            padding: 8px 10px;
            border: 1px solid #344b5a;
            border-radius: 999px;
            color: #eaf5f6;
            background: #102a3b;
            box-shadow: 0 12px 35px rgb(16 42 59 / 25%);
        }
        .floating-switcher button { display: grid; min-width: 34px; height: 34px; place-items: center; border: 1px solid rgb(255 255 255 / 20%); border-radius: 50%; color: #eaf5f6; background: transparent; font-size: 18px; }
        .floating-switcher button:hover { border-color: #72d4c7; color: #72d4c7; }
        .switcher-label { min-width: 230px; text-align: center; }
        .switcher-label strong { display: block; color: white; font-size: 11px; }
        .switcher-label small { display: block; margin-top: 2px; color: #a9c0c8; font-size: 9px; }

        .drawer-backdrop { position: fixed; z-index: 90; inset: 0; background: rgb(6 25 36 / 48%); }
        .drawer-backdrop[hidden] { display: none; }
        .drawer {
            position: absolute;
            top: 0;
            right: 0;
            display: flex;
            flex-direction: column;
            width: min(430px, 100%);
            height: 100%;
            padding: 24px;
            color: var(--ink);
            background: var(--white);
            box-shadow: -12px 0 35px rgb(6 25 36 / 18%);
        }
        .drawer-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding-bottom: 15px; border-bottom: 1px solid var(--line); }
        .drawer-head h2 { margin-bottom: 4px; }
        .drawer-head p { margin: 0; color: var(--muted); font-size: 10px; }
        .drawer-close { display: grid; width: 34px; height: 34px; place-items: center; border: 1px solid var(--line); border-radius: 7px; color: var(--ink); background: var(--white); font-size: 18px; }
        .drawer-body { flex: 1; overflow-y: auto; padding-top: 18px; }
        .drawer-body dl { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 0; }
        .drawer-body dt { color: var(--muted); font-size: 10px; }
        .drawer-body dd { margin: 3px 0 0; font-size: 12px; font-weight: 800; }
        .drawer-callout { margin-top: 19px; padding: 12px; border-left: 3px solid var(--teal); color: #45616b; background: #f1f8f8; font-size: 11px; line-height: 1.45; }
        .drawer-foot { padding-top: 16px; border-top: 1px solid var(--line); }

        /* Candidate B intentionally has a different visual grammar and information hierarchy. */
        .candidate-tailadmin { --ink: #1b2431; --muted: #667085; --line: #e6eaf0; --paper: #f8fafc; --navy: #1e40af; --teal: #2563eb; --teal-soft: #e8efff; --shadow: 0 7px 22px rgb(16 24 40 / 5%); }
        .candidate-tailadmin .app-shell { grid-template-columns: 256px minmax(0, 1fr); }
        .candidate-tailadmin .sidebar { padding: 27px 18px; color: #667085; border-right: 1px solid #e6eaf0; background: #fff; }
        .candidate-tailadmin .brand { color: #1d2939; }
        .candidate-tailadmin .brand span { color: #2563eb; }
        .candidate-tailadmin .candidate-note { color: #98a2b3; }
        .candidate-tailadmin .sidebar-label { color: #98a2b3; }
        .candidate-tailadmin .side-nav button { color: #667085; }
        .candidate-tailadmin .side-nav button:hover, .candidate-tailadmin .side-nav button[aria-current="page"] { color: #1d4ed8; background: #eff4ff; }
        .candidate-tailadmin .side-nav button[aria-current="page"] { box-shadow: none; }
        .candidate-tailadmin .side-nav .nav-mark { border-color: #d0d5dd; }
        .candidate-tailadmin .sidebar-foot { border-color: #e6eaf0; color: #98a2b3; }
        .candidate-tailadmin .sidebar-foot strong { color: #344054; }
        .candidate-tailadmin .topbar { border-bottom-color: #e6eaf0; background: #fff; }
        .candidate-tailadmin .avatar { color: #1e40af; background: #dbe7ff; }
        .candidate-tailadmin .topbar-right strong { color: #1d2939; }
        .candidate-tailadmin .main { padding-top: 35px; }
        .candidate-tailadmin .intro-side { border-style: solid; color: #475467; background: #fff; }
        .candidate-tailadmin .toolbar { border-color: #e6eaf0; box-shadow: none; }
        .candidate-tailadmin .toolbar button:hover, .candidate-tailadmin .toolbar button[aria-pressed="true"] { border-color: #84a9ff; color: #1d4ed8; background: #eff4ff; }
        .candidate-tailadmin .candidate-pill { color: #475467; background: #f2f4f7; }
        .candidate-tailadmin .candidate-pill .marker { background: #2563eb; }
        .candidate-tailadmin .panel, .candidate-tailadmin .metric, .candidate-tailadmin .project-hero { border-color: #e6eaf0; border-radius: 14px; box-shadow: var(--shadow); }
        .candidate-tailadmin .metric { padding: 19px; }
        .candidate-tailadmin .metric-value { font-size: 31px; color: #1d2939; }
        .candidate-tailadmin .panel-head h2, .candidate-tailadmin .project-hero h2 { color: #1d2939; }
        .candidate-tailadmin .data-table th { color: #98a2b3; }
        .candidate-tailadmin .data-table td { padding-top: 15px; padding-bottom: 15px; }
        .candidate-tailadmin .data-table tr:hover td { background: #f9fafb; }
        .candidate-tailadmin .project-hero { grid-template-columns: minmax(0, 1fr) minmax(230px, .48fr); padding: 25px; }
        .candidate-tailadmin .project-hero-side { border-left: 0; border-top: 1px solid #e6eaf0; padding: 15px 0 0; }
        .candidate-tailadmin .project-hero-side strong { color: #1d2939; }
        .candidate-tailadmin .walkthrough { border-color: #bfd1ff; background: #f6f8ff; }
        .candidate-tailadmin .walkthrough-step[aria-current="step"] { border-color: #84a9ff; box-shadow: inset 0 3px #2563eb; }
        .candidate-tailadmin .walkthrough-step strong { color: #2563eb; }
        .candidate-tailadmin .activity-item { border-left-color: #2563eb; background: #f4f7ff; }
        .candidate-tailadmin .decision-item { border-left-color: #f79009; background: #fffaeb; }
        .candidate-tailadmin .decision-item.high { border-left-color: #f04438; background: #fff6f5; }

        /* TailAdmin-style pages use different hierarchy: overview cards and queue first. */
        .tail-overview { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(280px, .85fr); gap: 16px; margin-bottom: 16px; }
        .tail-welcome { padding: 23px; border-radius: 14px; color: #fff; background: linear-gradient(120deg, #1d4ed8, #4f7de9); box-shadow: 0 10px 25px rgb(37 99 235 / 18%); }
        .tail-welcome .eyebrow { color: #dbe7ff; }
        .tail-welcome h2 { margin-bottom: 8px; color: #fff; font-size: 24px; }
        .tail-welcome p { max-width: 510px; margin-bottom: 16px; color: #e8efff; font-size: 11px; line-height: 1.5; }
        .tail-welcome .button { border-color: rgb(255 255 255 / 35%); color: #fff; background: rgb(255 255 255 / 12%); }
        .tail-welcome .button:hover { border-color: #fff; background: rgb(255 255 255 / 20%); }
        .tail-side-summary { display: grid; gap: 10px; grid-template-columns: 1fr 1fr; }
        .tail-side-stat { padding: 15px; border: 1px solid #e6eaf0; border-radius: 14px; background: #fff; }
        .tail-side-stat strong { display: block; margin: 5px 0 3px; color: #1d2939; font-size: 25px; letter-spacing: -.06em; }
        .tail-side-stat span { color: #667085; font-size: 10px; }
        .tail-queue { display: grid; gap: 10px; }
        .tail-queue-item { display: grid; grid-template-columns: 10px minmax(0, 1fr) auto; gap: 10px; align-items: start; padding: 12px 0; border-bottom: 1px solid #eef0f3; }
        .tail-queue-item:last-child { border-bottom: 0; }
        .tail-queue-item .queue-dot { width: 10px; height: 10px; margin-top: 4px; border-radius: 50%; background: #f79009; }
        .tail-queue-item.high .queue-dot { background: #f04438; }
        .tail-queue-item strong { display: block; font-size: 11px; }
        .tail-queue-item p { margin: 3px 0 0; color: #667085; font-size: 10px; line-height: 1.4; }
        .tail-queue-item small { color: #98a2b3; font-size: 9px; white-space: nowrap; }
        .tail-progress-card { padding: 18px; border: 1px solid #e6eaf0; border-radius: 14px; background: #fff; }
        .tail-progress-card h3 { margin-bottom: 12px; color: #344054; }
        .tail-progress-row { display: grid; grid-template-columns: 95px minmax(0, 1fr) 40px; gap: 10px; align-items: center; margin-top: 12px; color: #667085; font-size: 10px; }
        .tail-progress-row strong { color: #344054; text-align: right; }
        .tail-progress-row .progress-line { height: 9px; }
        .tail-timeline { display: grid; gap: 0; }
        .tail-timeline-item { display: grid; grid-template-columns: 23px minmax(0, 1fr); gap: 11px; padding-bottom: 16px; }
        .tail-timeline-marker { position: relative; display: grid; width: 23px; height: 23px; place-items: center; border-radius: 50%; color: #1d4ed8; background: #eff4ff; font-size: 10px; font-weight: 900; }
        .tail-timeline-item:not(:last-child) .tail-timeline-marker::after { position: absolute; top: 23px; left: 11px; width: 1px; height: 26px; background: #dbe7ff; content: ""; }
        .tail-timeline-item strong { display: block; font-size: 11px; }
        .tail-timeline-item p { margin: 3px 0 0; color: #667085; font-size: 10px; line-height: 1.4; }

        @media (max-width: 1050px) {
            .app-shell, .candidate-tailadmin .app-shell { grid-template-columns: 1fr; }
            .sidebar { display: flex; align-items: center; gap: 17px; padding: 12px 18px; }
            .brand { flex: 0 0 auto; padding: 0; }
            .candidate-note, .sidebar-label, .sidebar-foot { display: none; }
            .side-nav { display: flex; flex: 1; gap: 4px; overflow-x: auto; }
            .side-nav button { flex: 0 0 auto; width: auto; min-height: 38px; white-space: nowrap; }
            .side-nav .nav-mark { display: none; }
            .topbar { padding: 0 22px; }
            .main { padding: 25px 22px 150px; }
        }
        @media (max-width: 800px) {
            .metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .two-column, .tail-overview { grid-template-columns: 1fr; }
            .three-column { grid-template-columns: 1fr; }
            .project-hero { grid-template-columns: 1fr; }
            .project-hero-side { padding: 14px 0 0; border-top: 1px solid var(--line); border-left: 0; }
            .walkthrough-steps { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 560px) {
            .prototype-ribbon { top: 8px; right: 8px; font-size: 9px; }
            .topbar { min-height: 61px; padding: 0 14px; }
            .topbar-right > div { display: none; }
            .main { padding: 21px 14px 145px; }
            .intro, .screen-head, .panel-head { display: block; }
            .intro-side { margin-top: 14px; }
            .screen-head-actions { justify-content: flex-start; margin-top: 12px; }
            .toolbar { align-items: stretch; }
            .toolbar-group { align-items: stretch; }
            .viewport-readout { width: 100%; margin-left: 0; }
            .metric-grid { gap: 8px; }
            .metric { padding: 12px; }
            .metric-value { font-size: 24px; }
            .panel { padding: 15px; }
            .walkthrough-steps { grid-template-columns: 1fr 1fr; }
            .switcher-label { min-width: 165px; }
            .switcher-label small { display: none; }
            .floating-switcher { bottom: 10px; }
        }
    </style>
</head>
<body class="candidate-flowbite">
    <div class="prototype-ribbon">Throwaway · #148</div>

    <div class="app-shell" id="app-shell">
        <aside class="sidebar" aria-label="Prototype App shell">
            <a class="brand" href="#top">PMS <span>prototype</span></a>
            <p class="candidate-note" id="candidate-note">Komponen lokal, App shell PMS dipertahankan.</p>
            <div class="sidebar-label">Permukaan operasi</div>
            <nav class="side-nav" id="side-nav" aria-label="Permukaan prototype"></nav>
            <div class="sidebar-foot">
                <strong>Fixture read model</strong>
                Statis dan read-only. Tidak memanggil query, authorization, atau asset eksternal.
            </div>
        </aside>

        <div class="workspace">
            <header class="topbar">
                <div class="breadcrumb" aria-label="Breadcrumb">
                    <span>Prototype</span><span aria-hidden="true">/</span><strong id="breadcrumb-current">Dashboard Mitra</strong>
                </div>
                <div class="topbar-right">
                    <div>
                        <strong id="persona-name">Rina Pratama</strong>
                        <small id="persona-role">User THC · lintas Mitra</small>
                    </div>
                    <span class="avatar" id="persona-avatar" aria-hidden="true">RP</span>
                </div>
            </header>

            <main class="main" id="top">
                <section class="intro" aria-labelledby="prototype-title">
                    <div>
                        <p class="eyebrow">Perbandingan fondasi visual · data statis</p>
                        <h1 id="prototype-title">UI dashboard yang terasa tepat untuk operasi harian?</h1>
                        <p>Bandingkan kepadatan informasi, hierarki baca, navigasi, status risiko, tabel, responsive behavior, dan beban adaptasi Blade/Livewire pada tiga layar yang sama.</p>
                    </div>
                    <div class="intro-side">
                        <strong>Yang sedang diuji</strong>
                        Bukan fitur atau lisensi template. Ini hanya komposisi visual: komponen lokal Flowbite versus susunan dashboard TailAdmin.
                    </div>
                </section>

                <section class="toolbar" aria-label="Kontrol prototype">
                    <div class="toolbar-group" role="group" aria-label="Persona">
                        <span class="toolbar-label">Persona</span>
                        <button type="button" data-persona="thc" aria-pressed="true">User THC</button>
                        <button type="button" data-persona="mitra" aria-pressed="false">User Mitra</button>
                    </div>
                    <div class="toolbar-group" role="group" aria-label="State preview">
                        <span class="toolbar-label">State</span>
                        <button type="button" class="state-button" data-ui-state="ready" data-state="ready" aria-pressed="true">Ready</button>
                        <button type="button" class="state-button" data-ui-state="loading" data-state="loading" aria-pressed="false">Loading</button>
                        <button type="button" class="state-button" data-ui-state="empty" data-state="empty" aria-pressed="false">Empty</button>
                        <button type="button" class="state-button" data-ui-state="error" data-state="error" aria-pressed="false">Error</button>
                    </div>
                    <span class="viewport-readout">Viewport: <strong id="viewport-size">—</strong> · resize browser untuk menguji responsive</span>
                </section>

                <section id="screen-root" class="screen-section" aria-live="polite"></section>

                <section class="walkthrough" aria-labelledby="walkthrough-title">
                    <div class="walkthrough-head">
                        <div>
                            <h2 id="walkthrough-title">Walkthrough acceptance</h2>
                            <p>Jalankan tugas operasional yang sama pada kedua kandidat. Perhatikan apa yang terbaca dulu dan siapa yang boleh melakukan action.</p>
                        </div>
                        <span class="walkthrough-status" id="walkthrough-status">0/4 selesai</span>
                    </div>
                    <div class="walkthrough-steps">
                        <button type="button" class="walkthrough-step" data-walk-step="0" aria-current="step">
                            <strong>01 · Temukan</strong>
                            <span>Temukan Project yang perlu perhatian.</span>
                        </button>
                        <button type="button" class="walkthrough-step" data-walk-step="1">
                            <strong>02 · Buka detail</strong>
                            <span>Buka detail Project dari ringkasan.</span>
                        </button>
                        <button type="button" class="walkthrough-step" data-walk-step="2">
                            <strong>03 · Filter merah</strong>
                            <span>Filter status risiko tanpa kehilangan konteks.</span>
                        </button>
                        <button type="button" class="walkthrough-step" data-walk-step="3">
                            <strong>04 · Cek action</strong>
                            <span>Bandingkan action User THC dan User Mitra.</span>
                        </button>
                    </div>
                    <p class="walkthrough-note" id="walkthrough-note">Mulai dari langkah 01. Pada setiap langkah, pindah layar bila perlu dan nilai apakah tujuan langsung terlihat.</p>
                </section>
            </main>
        </div>
    </div>

    <div class="drawer-backdrop" id="drawer-backdrop" hidden>
        <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="drawer-title">
            <div class="drawer-head">
                <div>
                    <h2 id="drawer-title">Detail Project</h2>
                    <p id="drawer-subtitle">Tautan baca ke modul pemilik data.</p>
                </div>
                <button type="button" class="drawer-close" id="drawer-close" aria-label="Tutup detail">×</button>
            </div>
            <div class="drawer-body" id="drawer-body"></div>
            <div class="drawer-foot">
                <button type="button" class="button button-primary" id="drawer-owner-link">Buka modul pemilik data</button>
            </div>
        </aside>
    </div>

    <nav class="floating-switcher" aria-label="Ganti kandidat fondasi">
        <button type="button" id="candidate-prev" aria-label="Kandidat sebelumnya">←</button>
        <div class="switcher-label">
            <strong id="candidate-label">A · Flowbite components</strong>
            <small>← → untuk mengganti kandidat · tersimpan di URL</small>
        </div>
        <button type="button" id="candidate-next" aria-label="Kandidat berikutnya">→</button>
    </nav>

    <script>
        (() => {
            const DATA = {
                candidates: {
                    flowbite: {
                        key: 'A',
                        name: 'Flowbite components',
                        note: 'Komponen lokal, App shell PMS dipertahankan.',
                        description: 'Component-first: shell permission-aware, panel padat, tabel operasional, dan interaksi kecil yang mudah dibungkus menjadi x-ui.*.'
                    },
                    tailadmin: {
                        key: 'B',
                        name: 'TailAdmin composition',
                        note: 'Template-first, dashboard composition lebih lapang.',
                        description: 'Template-first: hero, metric card, queue, dan whitespace lebih dominan; shell dan Livewire tetap harus diadaptasi.'
                    }
                },
                screens: {
                    mitra: { label: 'Dashboard Mitra', short: 'Mitra' },
                    project: { label: 'Project Control Room', short: 'Project' },
                    portfolio: { label: 'Portfolio Cockpit', short: 'Portfolio' }
                },
                personas: {
                    thc: { name: 'Rina Pratama', role: 'User THC', scope: 'lintas Mitra', initials: 'RP', canManage: true },
                    mitra: { name: 'Dimas Saputra', role: 'User Mitra', scope: 'Mitra Nusantara', initials: 'DS', canManage: false }
                },
                projects: [
                    { id: 'PRJ-24017', name: 'FTTH Surabaya Barat', mitra: 'Mitra Nusantara', pop: 'SBY-BRT-04', progress: 78, risk: 'red', riskLabel: 'Merah', status: 'Berjalan', toc: '18 Sep 2026', reason: '2 step tertinggal' },
                    { id: 'PRJ-24021', name: 'Metro Ethernet Gresik', mitra: 'Mitra Nusantara', pop: 'GRS-02', progress: 62, risk: 'amber', riskLabel: 'Kuning', status: 'Berjalan', toc: '30 Sep 2026', reason: 'Material transit' },
                    { id: 'PRJ-24031', name: 'Backhaul Lamongan', mitra: 'Mitra Pesisir', pop: 'LMG-01', progress: 91, risk: 'green', riskLabel: 'Hijau', status: 'Berjalan', toc: '08 Sep 2026', reason: 'Sesuai baseline' },
                    { id: 'PRJ-24044', name: 'FTTH Sidoarjo Timur', mitra: 'Mitra Nusantara', pop: 'SDO-TMR-03', progress: 34, risk: 'amber', riskLabel: 'Kuning', status: 'Berjalan', toc: '21 Okt 2026', reason: 'Menunggu surat jalan' },
                    { id: 'PRJ-24052', name: 'Akses Fiber Mojokerto', mitra: 'Mitra Pesisir', pop: 'MJK-05', progress: 15, risk: 'slate', riskLabel: 'N/A', status: 'Perencanaan', toc: '06 Nov 2026', reason: 'Baseline belum tersedia' }
                ],
                activities: [
                    { title: 'Surat Jalan SJ-260824-018 disetujui', meta: 'Material · 18 menit lalu', tone: 'green' },
                    { title: 'Step Instalasi dimundurkan', meta: 'PRJ-24017 · 1 jam lalu', tone: 'amber' },
                    { title: 'Komentar baru pada PRJ-24021', meta: 'Linimasa Project · 3 jam lalu', tone: 'blue' }
                ],
                decisions: [
                    { title: 'PRJ-24017 melewati target Step Instalasi', text: 'Baca detail progres dan putuskan tindak lanjut dengan Mitra Nusantara.', risk: 'high', label: 'Progress' },
                    { title: 'Material PRJ-24021 masih transit', text: 'Pastikan status Surat Jalan sebelum menjanjikan jadwal berikutnya.', risk: 'watch', label: 'Material' },
                    { title: 'Baseline PRJ-24052 belum tersedia', text: 'SPI tidak dihitung; tampilkan N/A dan tautkan ke perencanaan.', risk: 'watch', label: 'Baseline' }
                ],
                steps: [
                    { label: 'Survey', date: '12 Agu 2026', state: 'done' },
                    { label: 'Design', date: '15 Agu 2026', state: 'done' },
                    { label: 'Material', date: '22 Agu 2026', state: 'done' },
                    { label: 'Instalasi', date: 'target 27 Agu 2026', state: 'current' },
                    { label: 'Uji terima', date: 'target 08 Sep 2026', state: 'next' }
                ]
            };

            const state = {
                candidate: 'flowbite',
                screen: 'mitra',
                persona: 'thc',
                uiState: 'ready',
                riskFilter: 'all',
                walkStep: 0,
                walkDone: []
            };
            let lastDrawerTrigger = null;

            const qs = (selector, parent = document) => parent.querySelector(selector);
            const qsa = (selector, parent = document) => Array.from(parent.querySelectorAll(selector));
            const readParam = (name, allowed, fallback) => {
                const value = new URLSearchParams(window.location.search).get(name);
                return allowed.includes(value) ? value : fallback;
            };

            state.candidate = readParam('variant', ['flowbite', 'tailadmin'], 'flowbite');
            state.screen = readParam('screen', ['mitra', 'project', 'portfolio'], 'mitra');
            state.persona = readParam('persona', ['thc', 'mitra'], 'thc');

            function updateUrl() {
                const params = new URLSearchParams(window.location.search);
                params.set('variant', state.candidate);
                params.set('screen', state.screen);
                params.set('persona', state.persona);
                window.history.replaceState({}, '', window.location.pathname + '?' + params.toString());
            }

            function statusBadge(project) {
                const classes = { red: 'badge-red', amber: 'badge-amber', green: 'badge-green', slate: 'badge-slate' };
                return '<span class="badge ' + classes[project.risk] + '">' + project.riskLabel + '</span>';
            }

            function visibleProjects() {
                if (state.persona === 'thc') return DATA.projects;
                return DATA.projects.filter((project) => project.mitra === 'Mitra Nusantara');
            }

            function projectRows(options = {}) {
                const list = visibleProjects().filter((project) => options.risk ? project.risk === options.risk : true);
                if (!list.length) return '<tr><td colspan="6"><div class="empty-state">Tidak ada Project dalam cakupan dan filter ini.</div></td></tr>';
                return list.map((project) => {
                    return '<tr data-project-row data-risk="' + project.risk + '">' +
                        '<td><button type="button" class="project-link" data-open-project="' + project.id + '">' + project.name + '</button><small>' + project.id + ' · ' + project.mitra + '</small></td>' +
                        '<td>' + project.pop + '</td>' +
                        '<td><div class="progress-label"><span>' + project.status + '</span><strong>' + project.progress + '%</strong></div><div class="progress-line ' + (project.risk === 'red' ? 'red' : project.risk === 'amber' ? 'amber' : '') + '"><span style="width:' + project.progress + '%"></span></div></td>' +
                        '<td>' + statusBadge(project) + '</td>' +
                        '<td>' + project.toc + '<small>' + project.reason + '</small></td>' +
                        '<td class="text-right"><button type="button" class="button" data-open-project="' + project.id + '">Detail</button></td>' +
                        '</tr>';
                }).join('');
            }

            function compactProjectCards() {
                return visibleProjects().slice(0, 3).map((project) => {
                    return '<article class="panel">' +
                        '<div class="panel-head"><div><h3>' + project.name + '</h3><p>' + project.id + ' · ' + project.pop + '</p></div>' + statusBadge(project) + '</div>' +
                        '<div class="progress-label"><span>Progress terverifikasi</span><strong>' + project.progress + '%</strong></div>' +
                        '<div class="progress-line ' + (project.risk === 'red' ? 'red' : project.risk === 'amber' ? 'amber' : '') + '"><span style="width:' + project.progress + '%"></span></div>' +
                        '<p class="panel-note">' + project.reason + ' · TOC ' + project.toc + '</p>' +
                        '<p style="margin:12px 0 0"><button type="button" class="button" data-open-project="' + project.id + '">Buka detail Project</button></p>' +
                        '</article>';
                }).join('');
            }

            function flowbiteScreenMitra() {
                const persona = DATA.personas[state.persona];
                return '<div class="candidate-pill"><span class="marker"></span><span>Eksperimen A</span><strong>Flowbite components</strong><span>· shell tetap milik PMS</span></div>' +
                    '<div class="screen-head"><div><p class="eyebrow">Ringkasan tenant</p><h1>Dashboard Mitra</h1><p>Ringkasan Project, material, dan aktivitas yang berada dalam cakupan ' + persona.scope + '.</p></div><div class="screen-head-actions"><button type="button" class="button button-primary" data-action="project">Buka daftar Project</button><button type="button" class="button" data-action="refresh">Refresh read model</button></div></div>' +
                    '<div class="metric-grid">' +
                        '<div class="metric"><span class="metric-label">Project aktif</span><strong class="metric-value">' + visibleProjects().length + '</strong><span class="metric-meta good">+1 dibanding bulan lalu</span></div>' +
                        '<div class="metric"><span class="metric-label">Progress rata-rata</span><strong class="metric-value">64%</strong><span class="metric-meta">terverifikasi</span></div>' +
                        '<div class="metric"><span class="metric-label">Material siap</span><strong class="metric-value">81%</strong><span class="metric-meta good">3 dari 4 kebutuhan</span></div>' +
                        '<div class="metric"><span class="metric-label">Perlu perhatian</span><strong class="metric-value">2</strong><span class="metric-meta danger">1 risiko merah</span></div>' +
                    '</div>' +
                    '<div class="two-column"><section class="panel"><div class="panel-head"><div><h2>Project dalam cakupan</h2><p>Tabel padat untuk scanning cepat dan tautan ke pemilik data.</p></div><span class="panel-count">' + visibleProjects().length + ' Project</span></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Project</th><th>PoP</th><th>Progress</th><th>Risiko</th><th>TOC</th><th>Aksi</th></tr></thead><tbody>' + projectRows() + '</tbody></table></div></section>' +
                    '<div><section class="panel"><div class="panel-head"><div><h2>Kesiapan Material</h2><p>Status ringkas, bukan ledger baru.</p></div></div><ul class="readiness-list"><li><div class="progress-label"><span>Sudah diterima</span><strong>81%</strong></div><div class="progress-line"><span style="width:81%"></span></div></li><li><div class="progress-label"><span>Transit</span><strong>12%</strong></div><div class="progress-line amber"><span style="width:12%"></span></div></li><li><div class="progress-label"><span>Belum diminta</span><strong>7%</strong></div><div class="progress-line"><span style="width:7%;background:#9aabb1"></span></div></li></ul><p class="panel-note">Material Transit tidak dihitung sebagai tersedia.</p></section><section class="panel"><div class="panel-head"><div><h2>Aktivitas terbaru</h2><p>Linimasa ringkas.</p></div></div><ul class="activity-list">' + DATA.activities.map((item) => '<li class="activity-item"><strong>' + item.title + '</strong><p>' + item.meta + '</p></li>').join('') + '</ul></section></div></div>';
            }

            function flowbiteScreenProject() {
                const project = DATA.projects[0];
                return '<div class="candidate-pill"><span class="marker"></span><span>Eksperimen A</span><strong>Flowbite components</strong><span>· detail memakai panel + tabel</span></div>' +
                    '<div class="screen-head"><div><p class="eyebrow">Detail satu Project</p><h1>Project Control Room</h1><p>Ruang baca operasional untuk ' + project.id + '. Informasi domain tetap ditautkan ke modul pemilik data.</p></div><div class="screen-head-actions"><button type="button" class="button" data-action="back">← Kembali ke Dashboard</button><button type="button" class="button button-primary" data-open-project="' + project.id + '">Buka drawer detail</button></div></div>' +
                    '<section class="project-hero"><div><div class="project-id">' + project.id + ' · ' + project.mitra + '</div><h2>' + project.name + '</h2><p>PoP ' + project.pop + ' · TOC ' + project.toc + ' · ' + statusBadge(project) + '</p><div class="progress-label"><span>Progress jasa terverifikasi</span><strong>' + project.progress + '%</strong></div><div class="progress-line red"><span style="width:' + project.progress + '%"></span></div></div><div class="project-hero-side"><span class="metric-label">Decision Queue</span><strong>2 item</strong><small>1 perlu keputusan THC · 1 menunggu update Mitra</small></div></section>' +
                    '<div class="two-column"><div><section class="panel"><div class="panel-head"><div><h2>Linimasa Step</h2><p>Fase dapat dilompati atau dimundurkan; tanggal aktual tetap milik Project.</p></div><button type="button" class="button" data-action="timeline">Buka linimasa</button></div><ol class="step-list">' + DATA.steps.map((step) => '<li class="step-item ' + step.state + '"><span class="step-dot"></span><div><strong>' + step.label + '</strong><small>' + step.date + '</small></div><span class="step-status ' + (step.state === 'done' ? 'done' : step.state === 'current' ? 'current' : '') + '">' + (step.state === 'done' ? 'Selesai' : step.state === 'current' ? 'Berjalan' : 'Berikutnya') + '</span></li>').join('') + '</ol></section><section class="panel"><div class="panel-head"><div><h2>Material terkait Project</h2><p>Status ringkas dari modul Warehouse.</p></div><span class="panel-count">read-only</span></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Material</th><th>Diminta</th><th>Diterima</th><th>Lokasi</th><th>Status</th></tr></thead><tbody><tr><td><strong>Kabel FO 48 Core</strong><small>MAT-FO-048</small></td><td>12 drum</td><td>12 drum</td><td>Project</td><td>' + '<span class="badge badge-green">Siap dipasang</span>' + '</td></tr><tr><td><strong>Closure Joint</strong><small>MAT-CJ-012</small></td><td>8 pcs</td><td>5 pcs</td><td>Transit</td><td><span class="badge badge-amber">Sebagian</span></td></tr></tbody></table></div></section></div><div><section class="panel"><div class="panel-head"><div><h2>Risiko & keputusan</h2><p>Status bukan sekadar warna.</p></div></div><ol class="decision-list"><li class="decision-item high"><div class="decision-meta"><span class="mini-tag high">Merah</span><span class="mini-tag">Progress</span></div><h3>Instalasi tertinggal 3 hari</h3><p>Perlu pembacaan detail Step dan komentar terbaru.</p></li><li class="decision-item"><div class="decision-meta"><span class="mini-tag">Material</span></div><h3>Closure masih transit</h3><p>Tautkan ke Surat Jalan, jangan menghitungnya sebagai stok tersedia.</p></li></ol></section><section class="panel"><div class="panel-head"><div><h2>Action tersedia</h2><p>Sesuai persona ' + DATA.personas[state.persona].role + '.</p></div></div><div class="three-column"><button type="button" class="button" data-action="comment">Komentar</button><button type="button" class="button" ' + (DATA.personas[state.persona].canManage ? '' : 'disabled') + ' data-action="move-step">Pindah Step</button><button type="button" class="button" data-action="open-source">Sumber data</button></div><p class="panel-note">' + (DATA.personas[state.persona].canManage ? 'Action pengelolaan tampil untuk User THC.' : 'Pindah Step disembunyikan/nonaktif karena User Mitra tidak memiliki capability tersebut.') + '</p></section></div></div>';
            }

            function flowbiteScreenPortfolio() {
                const risk = state.riskFilter === 'all' ? null : state.riskFilter;
                return '<div class="candidate-pill"><span class="marker"></span><span>Eksperimen A</span><strong>Flowbite components</strong><span>· queue dan matrix diprioritaskan</span></div>' +
                    '<div class="screen-head"><div><p class="eyebrow">Antrean lintas Project</p><h1>Portfolio Cockpit</h1><p>Jawaban read-only untuk pertanyaan: apa yang perlu dibaca atau diputuskan sekarang?</p></div><div class="screen-head-actions"><button type="button" class="button" data-action="export">Unduh snapshot</button><button type="button" class="button button-primary" data-action="project">Buka Project</button></div></div>' +
                    '<div class="metric-grid"><div class="metric"><span class="metric-label">Project dalam scope</span><strong class="metric-value">' + visibleProjects().length + '</strong><span class="metric-meta">filter aktif · ' + DATA.personas[state.persona].scope + '</span></div><div class="metric"><span class="metric-label">SPI median</span><strong class="metric-value">0,92</strong><span class="metric-meta warn">di bawah baseline</span></div><div class="metric"><span class="metric-label">Risiko merah</span><strong class="metric-value">1</strong><span class="metric-meta danger">PRJ-24017</span></div><div class="metric"><span class="metric-label">Decision Queue</span><strong class="metric-value">3</strong><span class="metric-meta">sumber authorized</span></div></div>' +
                    '<div class="two-column"><div><section class="panel"><div class="panel-head"><div><h2>Health Matrix</h2><p>Filter mempersempit daftar tanpa mengubah ownership data.</p></div><span class="panel-count">as of 24 Agu 2026</span></div><div class="risk-filter" role="group" aria-label="Filter status risiko"><span class="risk-filter-label">Risiko</span><button type="button" data-risk-filter="all" aria-pressed="' + (state.riskFilter === 'all') + '">Semua</button><button type="button" data-risk-filter="red" aria-pressed="' + (state.riskFilter === 'red') + '">Merah</button><button type="button" data-risk-filter="amber" aria-pressed="' + (state.riskFilter === 'amber') + '">Kuning</button><button type="button" data-risk-filter="green" aria-pressed="' + (state.riskFilter === 'green') + '">Hijau</button></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Project</th><th>PoP</th><th>Progress</th><th>Risiko</th><th>TOC</th><th>Aksi</th></tr></thead><tbody>' + projectRows({ risk: risk }) + '</tbody></table></div></section><section class="panel"><div class="panel-head"><div><h2>Tren realisasi jasa</h2><p>Realisasi terverifikasi dibanding target kumulatif.</p></div><span class="panel-count">Apr–Sep 2026</span></div><div class="legend"><span><i></i>Realisasi terverifikasi</span><span><i class="target"></i>Target kumulatif</span></div><svg class="chart" viewBox="0 0 760 198" role="img" aria-label="Grafik garis realisasi dan target kumulatif"><path class="chart-grid" d="M42 24H744 M42 70H744 M42 116H744 M42 162H744"></path><path class="chart-target" d="M42 162 C170 159 222 136 325 104 S505 53 744 26"></path><path class="chart-actual" d="M42 162 C165 160 229 148 325 126 S505 95 658 76"></path><circle class="chart-dot" cx="658" cy="76" r="5"></circle><text class="chart-axis" x="8" y="166">0%</text><text class="chart-axis" x="4" y="120">50%</text><text class="chart-axis" x="4" y="28">100%</text><text class="chart-axis" x="42" y="185">Apr</text><text class="chart-axis" x="276" y="185">Jun</text><text class="chart-axis" x="505" y="185">Agu</text><text class="chart-axis" x="716" y="185">Sep</text></svg></section></div><div><section class="panel"><div class="panel-head"><div><h2>Decision Queue</h2><p>Yang perlu dibaca atau diputuskan.</p></div><span class="panel-count">3 item</span></div><ol class="decision-list">' + DATA.decisions.map((item) => '<li class="decision-item ' + (item.risk === 'high' ? 'high' : '') + '"><div class="decision-meta"><span class="mini-tag ' + (item.risk === 'high' ? 'high' : '') + '">' + (item.risk === 'high' ? 'Merah' : 'Kuning') + '</span><span class="mini-tag">' + item.label + '</span></div><h3>' + item.title + '</h3><p>' + item.text + '</p></li>').join('') + '</ol></section><section class="panel"><div class="panel-head"><div><h2>Status tampilan</h2><p>State komponen menjadi eksplisit saat data tidak siap.</p></div></div><div class="three-column"><div><span class="badge badge-green">Hijau</span><p class="panel-note">Sesuai</p></div><div><span class="badge badge-amber">Kuning</span><p class="panel-note">Perlu baca</p></div><div><span class="badge badge-red">Merah</span><p class="panel-note">Perlu keputusan</p></div></div></section></div></div>';
            }

            function tailadminScreenMitra() {
                const persona = DATA.personas[state.persona];
                return '<div class="candidate-pill"><span class="marker"></span><span>Eksperimen B</span><strong>TailAdmin composition</strong><span>· hero + card overview</span></div>' +
                    '<div class="screen-head"><div><p class="eyebrow">Ringkasan tenant</p><h1>Dashboard Mitra</h1><p>Overview lebih lapang; detail operasional berada satu klik di bawah hero.</p></div><div class="screen-head-actions"><button type="button" class="button" data-action="refresh">Refresh read model</button></div></div>' +
                    '<div class="tail-overview"><section class="tail-welcome"><p class="eyebrow">Selamat datang kembali</p><h2>' + persona.name + '</h2><p>Cakupan saat ini: <strong>' + persona.scope + '</strong>. Mulai dari Project yang memiliki status risiko atau perubahan material terbaru.</p><button type="button" class="button" data-action="project">Lihat semua Project</button></section><div class="tail-side-summary"><div class="tail-side-stat"><span>Project aktif</span><strong>' + visibleProjects().length + '</strong><span>+1 bulan ini</span></div><div class="tail-side-stat"><span>Perlu perhatian</span><strong>2</strong><span>1 merah</span></div><div class="tail-side-stat"><span>Progress rata-rata</span><strong>64%</strong><span>terverifikasi</span></div><div class="tail-side-stat"><span>Material siap</span><strong>81%</strong><span>3 dari 4 kebutuhan</span></div></div></div>' +
                    '<div class="two-column"><section class="panel"><div class="panel-head"><div><h2>Project yang sedang berjalan</h2><p>Card/list lebih mudah dipindai, tetapi tabel detail memerlukan layar berikutnya.</p></div><span class="panel-count">' + visibleProjects().length + ' Project</span></div><div class="tail-queue">' + visibleProjects().slice(0, 4).map((project) => '<div class="tail-queue-item ' + (project.risk === 'red' ? 'high' : '') + '"><span class="queue-dot"></span><div><strong>' + project.name + '</strong><p>' + project.id + ' · ' + project.reason + '</p></div><div>' + statusBadge(project) + '<p style="margin-top:6px;text-align:right"><button type="button" class="button" data-open-project="' + project.id + '">Detail</button></p></div></div>').join('') + '</div></section><div><section class="tail-progress-card"><h3>Kesiapan Material</h3><div class="tail-progress-row"><span>Sudah diterima</span><div class="progress-line"><span style="width:81%"></span></div><strong>81%</strong></div><div class="tail-progress-row"><span>Transit</span><div class="progress-line amber"><span style="width:12%"></span></div><strong>12%</strong></div><div class="tail-progress-row"><span>Belum diminta</span><div class="progress-line"><span style="width:7%;background:#98a2b3"></span></div><strong>7%</strong></div></section><section class="panel"><div class="panel-head"><div><h2>Aktivitas</h2><p>Feed singkat di sisi kanan.</p></div></div><ul class="activity-list">' + DATA.activities.map((item) => '<li class="activity-item"><strong>' + item.title + '</strong><p>' + item.meta + '</p></li>').join('') + '</ul></section></div></div>';
            }

            function tailadminScreenProject() {
                const project = DATA.projects[0];
                return '<div class="candidate-pill"><span class="marker"></span><span>Eksperimen B</span><strong>TailAdmin composition</strong><span>· hero + timeline cards</span></div>' +
                    '<div class="screen-head"><div><p class="eyebrow">Detail satu Project</p><h1>Project Control Room</h1><p>Ringkasan visual lebih mudah dipresentasikan; detail status perlu disiplin komponen tambahan.</p></div><div class="screen-head-actions"><button type="button" class="button" data-action="back">← Kembali</button><button type="button" class="button button-primary" data-open-project="' + project.id + '">Preview drawer</button></div></div>' +
                    '<section class="project-hero"><div><div class="project-id">' + project.id + ' · ' + project.mitra + '</div><h2>' + project.name + '</h2><p>PoP ' + project.pop + ' · TOC ' + project.toc + ' · ' + statusBadge(project) + '</p><div class="progress-label"><span>Progress jasa terverifikasi</span><strong>' + project.progress + '%</strong></div><div class="progress-line red"><span style="width:' + project.progress + '%"></span></div></div><div class="project-hero-side"><span class="metric-label">Kesehatan Project</span><strong>Perlu baca</strong><small>Overview tetap read-only dan menautkan ke sumber.</small></div></section>' +
                    '<div class="two-column"><div><section class="panel"><div class="panel-head"><div><h2>Milestone Project</h2><p>Timeline visual untuk membaca posisi saat ini.</p></div><span class="panel-count">4/11 step</span></div><div class="tail-timeline">' + DATA.steps.map((step, index) => '<div class="tail-timeline-item"><span class="tail-timeline-marker">' + (step.state === 'done' ? '✓' : index + 1) + '</span><div><strong>' + step.label + ' <span class="badge ' + (step.state === 'done' ? 'badge-green' : step.state === 'current' ? 'badge-amber' : 'badge-slate') + '">' + (step.state === 'done' ? 'Selesai' : step.state === 'current' ? 'Berjalan' : 'Berikutnya') + '</span></strong><p>' + step.date + '</p></div></div>').join('') + '</div></section><section class="panel"><div class="panel-head"><div><h2>Material readiness</h2><p>List ringkas; ledger tetap di Warehouse.</p></div></div><div class="tail-progress-row"><span>Kabel FO 48 Core</span><div class="progress-line"><span style="width:100%"></span></div><strong>12/12</strong></div><div class="tail-progress-row"><span>Closure Joint</span><div class="progress-line amber"><span style="width:62%"></span></div><strong>5/8</strong></div></section></div><div><section class="panel"><div class="panel-head"><div><h2>Next action</h2><p>Primary action dibaca dari konteks persona.</p></div></div><div class="tail-queue"><div class="tail-queue-item high"><span class="queue-dot"></span><div><strong>Review keterlambatan instalasi</strong><p>PRJ-24017 · sumber: Step</p></div><button type="button" class="button" data-action="timeline">Baca</button></div><div class="tail-queue-item"><span class="queue-dot"></span><div><strong>Periksa Closure transit</strong><p>SJ-260824-018 · sumber: Warehouse</p></div><button type="button" class="button" data-action="open-source">Baca</button></div></div></section><section class="panel"><div class="panel-head"><div><h2>Action tersedia</h2><p>Action domain tidak dimiliki kartu.</p></div></div><button type="button" class="button button-primary" data-action="comment">Tambah komentar</button><button type="button" class="button" style="margin-left:6px" ' + (DATA.personas[state.persona].canManage ? '' : 'disabled') + ' data-action="move-step">Pindah Step</button><p class="panel-note">' + (DATA.personas[state.persona].canManage ? 'User THC melihat action pengelolaan sesuai Izin Aksi.' : 'User Mitra dapat membaca dan berkomentar; Pindah Step tidak tersedia.') + '</p></section></div></div>';
            }

            function tailadminScreenPortfolio() {
                const risk = state.riskFilter === 'all' ? null : state.riskFilter;
                const filtered = visibleProjects().filter((project) => risk ? project.risk === risk : true);
                return '<div class="candidate-pill"><span class="marker"></span><span>Eksperimen B</span><strong>TailAdmin composition</strong><span>· decision queue di depan</span></div>' +
                    '<div class="screen-head"><div><p class="eyebrow">Antrean lintas Project</p><h1>Portfolio Cockpit</h1><p>Overview mengambil ruang pertama; matrix menjadi tabel lanjutan setelah queue dibaca.</p></div><div class="screen-head-actions"><button type="button" class="button" data-action="export">Export snapshot</button></div></div>' +
                    '<div class="metric-grid"><div class="metric"><span class="metric-label">Project dalam scope</span><strong class="metric-value">' + visibleProjects().length + '</strong><span class="metric-meta">as of 24 Agu 2026</span></div><div class="metric"><span class="metric-label">SPI median</span><strong class="metric-value">0,92</strong><span class="metric-meta warn">di bawah baseline</span></div><div class="metric"><span class="metric-label">Risiko merah</span><strong class="metric-value">1</strong><span class="metric-meta danger">perlu keputusan</span></div><div class="metric"><span class="metric-label">Queue terbuka</span><strong class="metric-value">3</strong><span class="metric-meta">authorized sources</span></div></div>' +
                    '<section class="panel"><div class="panel-head"><div><h2>Decision Queue</h2><p>Primary work surface untuk menemukan Project perlu perhatian.</p></div><span class="panel-count">3 item</span></div><div class="tail-queue">' + DATA.decisions.map((item) => '<div class="tail-queue-item ' + (item.risk === 'high' ? 'high' : '') + '"><span class="queue-dot"></span><div><strong>' + item.title + '</strong><p>' + item.text + '</p></div><span class="mini-tag ' + (item.risk === 'high' ? 'high' : '') + '">' + (item.risk === 'high' ? 'Merah' : 'Kuning') + '</span></div>').join('') + '</div></section><div class="two-column"><section class="panel"><div class="panel-head"><div><h2>Health Matrix</h2><p>Table tetap diperlukan untuk membandingkan banyak Project.</p></div></div><div class="risk-filter" role="group" aria-label="Filter status risiko"><span class="risk-filter-label">Risiko</span><button type="button" data-risk-filter="all" aria-pressed="' + (state.riskFilter === 'all') + '">Semua</button><button type="button" data-risk-filter="red" aria-pressed="' + (state.riskFilter === 'red') + '">Merah</button><button type="button" data-risk-filter="amber" aria-pressed="' + (state.riskFilter === 'amber') + '">Kuning</button><button type="button" data-risk-filter="green" aria-pressed="' + (state.riskFilter === 'green') + '">Hijau</button></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Project</th><th>Progress</th><th>Risiko</th><th>TOC</th><th>Aksi</th></tr></thead><tbody>' + (filtered.length ? filtered.map((project) => '<tr><td><strong>' + project.name + '</strong><small>' + project.id + ' · ' + project.mitra + '</small></td><td><div class="progress-label"><span>Progress</span><strong>' + project.progress + '%</strong></div><div class="progress-line ' + (project.risk === 'red' ? 'red' : project.risk === 'amber' ? 'amber' : '') + '"><span style="width:' + project.progress + '%"></span></div></td><td>' + statusBadge(project) + '</td><td>' + project.toc + '</td><td><button type="button" class="button" data-open-project="' + project.id + '">Detail</button></td></tr>').join('') : '<tr><td colspan="5"><div class="empty-state">Tidak ada Project pada filter ini.</div></td></tr>') + '</tbody></table></div></section><section class="panel"><div class="panel-head"><div><h2>Tren portofolio</h2><p>Chart ringkas dengan legend dan periode.</p></div></div><div class="legend"><span><i></i>Realisasi</span><span><i class="target"></i>Target</span></div><svg class="chart" viewBox="0 0 500 198" role="img" aria-label="Grafik tren portofolio"><path class="chart-grid" d="M30 24H488 M30 70H488 M30 116H488 M30 162H488"></path><path class="chart-target" d="M30 162 C110 159 178 129 250 101 S385 49 488 25"></path><path class="chart-actual" d="M30 162 C110 159 178 145 250 126 S385 95 451 76"></path><circle class="chart-dot" cx="451" cy="76" r="5"></circle><text class="chart-axis" x="30" y="185">Apr</text><text class="chart-axis" x="174" y="185">Jun</text><text class="chart-axis" x="321" y="185">Agu</text><text class="chart-axis" x="457" y="185">Sep</text></svg></section></div>';
            }

            function screenMarkup() {
                if (state.candidate === 'flowbite') {
                    if (state.screen === 'mitra') return flowbiteScreenMitra();
                    if (state.screen === 'project') return flowbiteScreenProject();
                    return flowbiteScreenPortfolio();
                }
                if (state.screen === 'mitra') return tailadminScreenMitra();
                if (state.screen === 'project') return tailadminScreenProject();
                return tailadminScreenPortfolio();
            }

            function stateBanner() {
                if (state.uiState === 'ready') return '';
                const copy = {
                    loading: ['Loading', 'Read model sedang dimuat. Skeleton atau aria-busy perlu tetap mempertahankan konteks layar.'],
                    empty: ['Empty', 'Tidak ada data yang cocok. Gunakan empty state yang menjelaskan cakupan dan langkah berikutnya.'],
                    error: ['Error', 'Read model gagal dimuat. Tampilkan pesan yang dapat ditindaklanjuti tanpa mengubah query atau ownership.']
                };
                const item = copy[state.uiState];
                return '<div class="state-banner ' + state.uiState + '" role="' + (state.uiState === 'error' ? 'alert' : 'status') + '" aria-live="polite"><span class="state-icon">' + (state.uiState === 'loading' ? '…' : state.uiState === 'empty' ? '—' : '!') + '</span><div><strong>' + item[0] + ' state preview</strong>' + item[1] + '</div></div>';
            }

            function renderNav() {
                const nav = qs('#side-nav');
                nav.innerHTML = Object.entries(DATA.screens).map(([key, screen]) => {
                    return '<button type="button" data-screen="' + key + '" aria-current="' + (state.screen === key ? 'page' : 'false') + '"><span class="nav-mark">' + (key === 'mitra' ? 'M' : key === 'project' ? 'P' : 'C') + '</span><span>' + screen.label + '</span></button>';
                }).join('');
            }

            function renderShell() {
                const candidate = DATA.candidates[state.candidate];
                const persona = DATA.personas[state.persona];
                document.body.className = 'candidate-' + state.candidate;
                qs('#candidate-note').textContent = candidate.note;
                qs('#candidate-label').textContent = candidate.key + ' · ' + candidate.name;
                qs('#breadcrumb-current').textContent = DATA.screens[state.screen].label;
                qs('#persona-name').textContent = persona.name;
                qs('#persona-role').textContent = persona.role + ' · ' + persona.scope;
                qs('#persona-avatar').textContent = persona.initials;
                qsa('[data-persona]').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.persona === state.persona)));
                qsa('[data-ui-state]').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.uiState === state.uiState)));
                renderNav();
                qs('#screen-root').innerHTML = stateBanner() + screenMarkup();
                qs('#viewport-size').textContent = window.innerWidth + 'px';
                renderWalkthrough();
            }

            function renderWalkthrough() {
                const doneCount = state.walkDone.length;
                qs('#walkthrough-status').textContent = doneCount + '/4 selesai';
                qsa('[data-walk-step]').forEach((button) => {
                    const index = Number(button.dataset.walkStep);
                    button.setAttribute('aria-current', index === state.walkStep ? 'step' : 'false');
                    button.dataset.completed = state.walkDone.includes(index) ? 'true' : 'false';
                });
                const messages = [
                    'Mulai dari langkah 01. Pada setiap langkah, pindah layar bila perlu dan nilai apakah tujuan langsung terlihat.',
                    'Langkah 01 selesai. Sekarang buka detail Project dan lihat apakah konteks tetap terbawa ke layar berikutnya.',
                    'Langkah 02 selesai. Sekarang filter status Merah; perhatikan apakah filter tetap terbaca bersama cakupan persona.',
                    'Langkah 03 selesai. Sekarang ganti persona ke User Mitra dan cek action mana yang hilang atau nonaktif.',
                    'Walkthrough selesai. Catat kandidat yang membuat empat tugas ini paling cepat dipahami.'
                ];
                qs('#walkthrough-note').textContent = messages[Math.min(doneCount, 4)];
            }

            function markWalkthrough(index) {
                if (!state.walkDone.includes(index)) state.walkDone.push(index);
                state.walkStep = Math.min(index + 1, 3);
                if (index === 0) state.screen = 'portfolio';
                if (index === 1) state.screen = 'project';
                if (index === 2) { state.screen = 'portfolio'; state.riskFilter = 'red'; }
                if (index === 3) { state.screen = 'project'; state.persona = 'mitra'; }
                updateUrl();
                renderShell();
            }

            function showNotice(message) {
                const existing = qs('#prototype-notice');
                if (existing) existing.remove();
                const notice = document.createElement('div');
                notice.id = 'prototype-notice';
                notice.className = 'state-banner';
                notice.style.position = 'fixed';
                notice.style.zIndex = '75';
                notice.style.right = '18px';
                notice.style.bottom = '77px';
                notice.style.maxWidth = 'min(420px, calc(100vw - 36px))';
                notice.innerHTML = '<span class="state-icon">i</span><div><strong>Prototype action</strong>' + message + '</div>';
                document.body.appendChild(notice);
                window.setTimeout(() => notice.remove(), 2800);
            }

            function openDrawer(projectId, trigger) {
                const project = DATA.projects.find((item) => item.id === projectId) || DATA.projects[0];
                lastDrawerTrigger = trigger || null;
                qs('#drawer-title').textContent = project.name;
                qs('#drawer-subtitle').textContent = project.id + ' · tautan baca ke modul pemilik data';
                qs('#drawer-body').innerHTML = '<dl><div><dt>Mitra</dt><dd>' + project.mitra + '</dd></div><div><dt>PoP</dt><dd>' + project.pop + '</dd></div><div><dt>Progress</dt><dd>' + project.progress + '% terverifikasi</dd></div><div><dt>Risiko</dt><dd>' + statusBadge(project) + '</dd></div><div><dt>Status</dt><dd>' + project.status + '</dd></div><div><dt>TOC</dt><dd>' + project.toc + '</dd></div></dl><div class="drawer-callout">Project Control Room membaca data Project, Step, Material, dan Linimasa dari modul pemilik masing-masing. Drawer ini hanya mendemonstrasikan hierarki, bukan jalur mutasi baru.</div>';
                qs('#drawer-backdrop').hidden = false;
                qs('#drawer-close').focus();
                document.body.classList.add('drawer-open');
            }

            function closeDrawer() {
                qs('#drawer-backdrop').hidden = true;
                document.body.classList.remove('drawer-open');
                if (lastDrawerTrigger && document.body.contains(lastDrawerTrigger)) lastDrawerTrigger.focus();
            }

            document.addEventListener('click', (event) => {
                const screenButton = event.target.closest('[data-screen]');
                if (screenButton) {
                    state.screen = screenButton.dataset.screen;
                    state.riskFilter = 'all';
                    updateUrl();
                    renderShell();
                    return;
                }
                const personaButton = event.target.closest('[data-persona]');
                if (personaButton) {
                    state.persona = personaButton.dataset.persona;
                    updateUrl();
                    renderShell();
                    showNotice('Persona diubah menjadi ' + DATA.personas[state.persona].role + '. Cakupan: ' + DATA.personas[state.persona].scope + '.');
                    return;
                }
                const uiStateButton = event.target.closest('[data-ui-state]');
                if (uiStateButton) {
                    state.uiState = uiStateButton.dataset.uiState;
                    renderShell();
                    return;
                }
                const riskButton = event.target.closest('[data-risk-filter]');
                if (riskButton) {
                    state.riskFilter = riskButton.dataset.riskFilter;
                    renderShell();
                    showNotice('Filter status risiko: ' + (state.riskFilter === 'all' ? 'Semua' : state.riskFilter === 'red' ? 'Merah' : state.riskFilter === 'amber' ? 'Kuning' : 'Hijau') + '.');
                    return;
                }
                const walkButton = event.target.closest('[data-walk-step]');
                if (walkButton) {
                    markWalkthrough(Number(walkButton.dataset.walkStep));
                    return;
                }
                const projectButton = event.target.closest('[data-open-project]');
                if (projectButton) {
                    openDrawer(projectButton.dataset.openProject, projectButton);
                    return;
                }
                const actionButton = event.target.closest('[data-action]');
                if (actionButton) {
                    if (actionButton.disabled) return;
                    const action = actionButton.dataset.action;
                    if (action === 'project') state.screen = 'portfolio';
                    if (action === 'back') state.screen = 'mitra';
                    updateUrl();
                    renderShell();
                    showNotice(action === 'refresh' ? 'Read model tetap statis di prototype; di aplikasi nyata state loading/error perlu diuji.' : action === 'export' ? 'Export hanya tautan ke modul/reporting pemilik data; tidak ada mutasi dari cockpit.' : action === 'move-step' ? 'Action Pindah Step tersedia untuk User THC pada modul pemilik Project.' : 'Navigasi read-only diperagakan; sumber data tetap modul pemilik.');
                }
            });

            qs('#candidate-prev').addEventListener('click', () => {
                state.candidate = state.candidate === 'flowbite' ? 'tailadmin' : 'flowbite';
                updateUrl();
                renderShell();
            });
            qs('#candidate-next').addEventListener('click', () => {
                state.candidate = state.candidate === 'flowbite' ? 'tailadmin' : 'flowbite';
                updateUrl();
                renderShell();
            });
            qs('#drawer-close').addEventListener('click', closeDrawer);
            qs('#drawer-owner-link').addEventListener('click', () => showNotice('Tautan modul pemilik data diperagakan saja; drawer prototype tidak melakukan mutasi.'));
            qs('#drawer-backdrop').addEventListener('click', (event) => {
                if (event.target === qs('#drawer-backdrop')) closeDrawer();
            });
            document.addEventListener('keydown', (event) => {
                const target = event.target;
                const isTyping = target.matches('input, textarea, select, [contenteditable="true"]');
                if (event.key === 'Escape' && !qs('#drawer-backdrop').hidden) closeDrawer();
                if (isTyping) return;
                if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                    event.preventDefault();
                    state.candidate = state.candidate === 'flowbite' ? 'tailadmin' : 'flowbite';
                    updateUrl();
                    renderShell();
                }
            });
            window.addEventListener('resize', () => {
                qs('#viewport-size').textContent = window.innerWidth + 'px';
            });

            renderShell();
        })();
    </script>
</body>
</html>
