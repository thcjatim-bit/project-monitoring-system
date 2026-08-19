<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PROTOTYPE #78 · Bahasa desain PMS</title>
    <style>
        :root {
            --ink: #172033;
            --muted: #667085;
            --line: #dbe2eb;
            --surface: #ffffff;
            --canvas: #f2f5f9;
            --accent: #4656d8;
            --accent-strong: #3042c2;
            --accent-soft: #edf0ff;
            --success: #16845f;
            --success-soft: #e9f8f0;
            --warning: #b56a12;
            --warning-soft: #fff3df;
            --danger: #c04d59;
            --danger-soft: #fff0f1;
            --info: #2d73a8;
            --info-soft: #eaf5ff;
            --shadow: 0 14px 38px rgba(28, 45, 72, .08);
            --radius: 16px;
            --space: 8px;
            --display: "Plus Jakarta Sans", "Segoe UI", sans-serif;
            --body: "Segoe UI", system-ui, sans-serif;
        }

        body[data-variant="a"] {
            --accent: #4656d8;
            --accent-strong: #3042c2;
            --accent-soft: #edf0ff;
            --canvas: #f2f5f9;
            --radius: 16px;
        }

        body[data-variant="b"] {
            --accent: #d96b3b;
            --accent-strong: #ad4c24;
            --accent-soft: #fff0e8;
            --canvas: #f7f1eb;
            --radius: 7px;
            --shadow: 5px 5px 0 rgba(23, 32, 51, .09);
        }

        body[data-variant="c"] {
            --accent: #087f78;
            --accent-strong: #05625e;
            --accent-soft: #e3f7f3;
            --canvas: #edf8f6;
            --radius: 24px;
            --shadow: 0 12px 28px rgba(8, 127, 120, .12);
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            color: var(--ink);
            background: var(--canvas);
            font-family: var(--body);
            line-height: 1.45;
        }
        body, button, input, select, textarea { font-size: 14px; }
        button, input, select, textarea { font: inherit; }
        button { cursor: pointer; }
        a { color: inherit; }
        [hidden] { display: none !important; }
        h1, h2, h3, p { margin-top: 0; }
        h1, h2, h3, strong { letter-spacing: -.02em; }
        h1 { margin-bottom: 8px; font-size: clamp(26px, 3vw, 42px); line-height: 1.08; }
        h2 { margin-bottom: 6px; font-size: 18px; }
        h3 { margin-bottom: 4px; font-size: 14px; }
        p { color: var(--muted); }
        code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }

        .prototype-ribbon {
            position: fixed;
            z-index: 40;
            top: 14px;
            right: 18px;
            padding: 7px 11px;
            border: 1px solid #f2cb63;
            border-radius: 999px;
            color: #5d4204;
            background: #ffe19a;
            box-shadow: 0 8px 18px rgba(93, 66, 4, .14);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .prototype-header {
            display: flex;
            align-items: center;
            gap: 24px;
            min-height: 74px;
            padding: 18px max(22px, calc((100vw - 1440px) / 2));
            border-bottom: 1px solid rgba(100, 117, 139, .18);
            background: rgba(255, 255, 255, .88);
            backdrop-filter: blur(14px);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 235px;
            text-decoration: none;
        }
        .brand-mark {
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 11px;
            color: #fff;
            background: var(--accent);
            font-weight: 900;
            box-shadow: 0 8px 16px color-mix(in srgb, var(--accent) 22%, transparent);
        }
        .brand-copy { display: grid; gap: 1px; }
        .brand-copy strong { font-size: 14px; }
        .brand-copy small { color: var(--muted); font-size: 11px; }
        .prototype-question { flex: 1; min-width: 160px; color: var(--muted); font-size: 12px; }
        .prototype-question strong { color: var(--ink); }
        .role-switch {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: #fff;
        }
        .role-switch button {
            border: 0;
            border-radius: 999px;
            padding: 7px 12px;
            color: var(--muted);
            background: transparent;
            font-size: 12px;
            font-weight: 800;
        }
        .role-switch button.is-active { color: #fff; background: var(--accent); }

        .prototype-canvas {
            width: min(1440px, calc(100% - 40px));
            margin: 0 auto;
            padding: 44px 0 160px;
        }
        .prototype-intro {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(280px, .8fr);
            gap: 24px;
            align-items: end;
            margin-bottom: 26px;
        }
        .eyebrow {
            margin-bottom: 10px;
            color: var(--accent-strong);
            font-size: 11px;
            font-weight: 900;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .intro-note {
            margin: 0;
            padding: 16px 18px;
            border: 1px solid color-mix(in srgb, var(--accent) 22%, var(--line));
            border-radius: var(--radius);
            background: color-mix(in srgb, var(--accent-soft) 65%, #fff);
            color: var(--ink);
            font-size: 13px;
        }
        .intro-note strong { display: block; margin-bottom: 4px; }
        .coverage-row, .tag-row, .button-row, .status-row { display: flex; flex-wrap: wrap; gap: 8px; }
        .coverage-row { margin-top: 18px; }
        .coverage-chip, .tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 9px;
            border: 1px solid var(--line);
            border-radius: 999px;
            color: #506074;
            background: rgba(255, 255, 255, .76);
            font-size: 11px;
            font-weight: 750;
        }
        .coverage-chip::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: var(--accent); }

        .prototype-state {
            position: fixed;
            z-index: 35;
            right: 20px;
            bottom: 82px;
            max-width: min(430px, calc(100vw - 40px));
            padding: 8px 11px;
            border: 1px solid #cbd7e5;
            border-radius: 9px;
            color: #405462;
            background: rgba(250, 253, 253, .96);
            box-shadow: 0 8px 22px rgba(21, 50, 75, .12);
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 10px;
        }
        .prototype-state strong { color: var(--accent-strong); }

        .prototype-switcher {
            position: fixed;
            z-index: 45;
            right: 50%;
            bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            transform: translateX(50%);
            padding: 8px 10px;
            border: 1px solid #24324b;
            border-radius: 999px;
            color: #fff;
            background: #1c2940;
            box-shadow: 0 14px 32px rgba(17, 31, 54, .3);
        }
        .prototype-switcher button {
            border: 0;
            color: #fff;
            background: transparent;
        }
        .switch-arrow {
            display: grid;
            width: 30px;
            height: 30px;
            place-items: center;
            border-radius: 50%;
            background: rgba(255, 255, 255, .12) !important;
            font-size: 18px;
        }
        .switch-arrow:hover { background: rgba(255, 255, 255, .22) !important; }
        .switch-label { min-width: 190px; text-align: center; font-size: 12px; font-weight: 800; }
        .switch-label small { display: block; color: #a8b9ce; font-size: 10px; font-weight: 500; }

        .variant { animation: reveal .24s ease-out; }
        @keyframes reveal { from { opacity: .2; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
        .surface, .lab-surface {
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: var(--surface);
            box-shadow: var(--shadow);
        }
        .surface-pad, .lab-surface { padding: 20px; }
        .muted { color: var(--muted); }
        .tiny { color: var(--muted); font-size: 11px; }
        .section-label { margin: 0 0 12px; color: var(--muted); font-size: 11px; font-weight: 850; letter-spacing: .08em; text-transform: uppercase; }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 38px;
            padding: 9px 14px;
            border: 1px solid transparent;
            border-radius: 10px;
            color: #fff;
            background: var(--accent);
            font-weight: 800;
            text-decoration: none;
        }
        .button:hover { background: var(--accent-strong); }
        .button.secondary { color: var(--accent-strong); border-color: color-mix(in srgb, var(--accent) 28%, var(--line)); background: var(--accent-soft); }
        .button.ghost { color: var(--muted); border-color: var(--line); background: #fff; }
        .button.danger { color: var(--danger); border-color: #f2c8cc; background: var(--danger-soft); }
        .button.small { min-height: 32px; padding: 6px 10px; font-size: 12px; }
        .icon-dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
        .badge {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 850;
            white-space: nowrap;
        }
        .badge-green { color: var(--success); background: var(--success-soft); }
        .badge-amber { color: var(--warning); background: var(--warning-soft); }
        .badge-red { color: var(--danger); background: var(--danger-soft); }
        .badge-blue { color: var(--info); background: var(--info-soft); }
        .badge-neutral { color: #576579; background: #eef1f5; }
        .table-wrap { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; min-width: 560px; }
        .table th, .table td { padding: 12px 10px; border-bottom: 1px solid #edf0f4; text-align: left; vertical-align: middle; }
        .table th { color: var(--muted); font-size: 10px; font-weight: 850; letter-spacing: .08em; text-transform: uppercase; }
        .table td { color: #344054; font-size: 12px; }
        .table tr:last-child td { border-bottom: 0; }
        .table .primary-cell { color: var(--ink); font-weight: 800; }
        .kpi-card { padding: 16px; border: 1px solid var(--line); border-radius: var(--radius); background: var(--surface); box-shadow: var(--shadow); }
        .kpi-card .kpi-label { color: var(--muted); font-size: 11px; font-weight: 750; }
        .kpi-card .kpi-value { display: block; margin: 5px 0 2px; font-size: 28px; line-height: 1; letter-spacing: -.05em; }
        .kpi-card .kpi-meta { color: var(--muted); font-size: 11px; }
        .kpi-card .kpi-meta.positive { color: var(--success); }
        .kpi-card .kpi-meta.warning { color: var(--warning); }
        .progress { height: 7px; overflow: hidden; border-radius: 999px; background: #edf1f5; }
        .progress span { display: block; height: 100%; border-radius: inherit; background: var(--accent); }
        .divider { height: 1px; margin: 16px 0; background: var(--line); }
        .field { display: grid; gap: 6px; margin-bottom: 13px; }
        .field label { color: #445269; font-size: 12px; font-weight: 800; }
        .field input, .field select, .field textarea, .search-input {
            width: 100%;
            min-height: 40px;
            padding: 9px 11px;
            border: 1px solid #cdd6e2;
            border-radius: 9px;
            outline: 0;
            color: var(--ink);
            background: #fff;
        }
        .field textarea { min-height: 76px; resize: vertical; }
        .field input:focus, .field select:focus, .field textarea:focus, .search-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
        .error-inline { display: flex; gap: 5px; align-items: flex-start; color: var(--danger); font-size: 11px; }
        .error-inline::before { content: "!"; display: inline-grid; flex: 0 0 16px; width: 16px; height: 16px; place-items: center; border-radius: 50%; color: #fff; background: var(--danger); font-size: 10px; font-weight: 900; }
        .search-select { position: relative; }
        .select-options { position: absolute; z-index: 8; top: calc(100% + 5px); right: 0; left: 0; display: none; max-height: 170px; overflow: auto; padding: 5px; border: 1px solid var(--line); border-radius: 10px; background: #fff; box-shadow: var(--shadow); }
        .search-select.is-open .select-options { display: grid; gap: 2px; }
        .select-option { width: 100%; padding: 9px 10px; border: 0; border-radius: 7px; color: var(--ink); background: #fff; text-align: left; font-size: 12px; }
        .select-option:hover { background: var(--accent-soft); }
        .select-option[hidden] { display: none; }
        .select-empty { padding: 10px; color: var(--muted); font-size: 11px; }

        /* Variant A: decision-first workspace with a permanent sidebar. */
        .a-shell { display: grid; grid-template-columns: 230px minmax(0, 1fr); gap: 18px; }
        .a-sidebar { display: flex; flex-direction: column; min-height: 720px; padding: 18px 12px; border: 1px solid #d6deea; border-radius: var(--radius); background: #18243a; color: #f3f6fc; box-shadow: var(--shadow); }
        .a-sidebar-head { display: flex; align-items: center; gap: 9px; padding: 2px 8px 20px; border-bottom: 1px solid rgba(255, 255, 255, .12); }
        .a-sidebar-head .brand-mark { width: 29px; height: 29px; border-radius: 9px; color: #18243a; background: #b9c4ff; box-shadow: none; }
        .a-sidebar-head strong { font-size: 13px; }
        .a-sidebar nav { display: grid; gap: 4px; padding-top: 18px; }
        .a-sidebar nav button { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 10px 9px; border: 0; border-radius: 9px; color: #b8c4d9; background: transparent; text-align: left; font-size: 12px; font-weight: 700; }
        .a-sidebar nav button:hover, .a-sidebar nav button.is-active { color: #fff; background: rgba(185, 196, 255, .16); }
        .a-sidebar nav button .nav-count { min-width: 20px; padding: 2px 5px; border-radius: 999px; color: #18243a; background: #f4c36d; text-align: center; font-size: 10px; }
        .a-sidebar-foot { margin-top: auto; padding: 13px 10px 4px; color: #99a9c2; font-size: 11px; }
        .a-sidebar-foot strong { display: block; margin-bottom: 3px; color: #eef3ff; font-size: 12px; }
        .a-workspace { min-width: 0; }
        .a-page-head { display: flex; justify-content: space-between; gap: 18px; align-items: flex-start; margin: 4px 0 22px; }
        .a-page-head p { max-width: 650px; margin-bottom: 0; }
        .a-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
        .a-kpis .kpi-card:first-child { border-top: 3px solid var(--accent); }
        .a-main-grid { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(280px, .8fr); gap: 18px; }
        .a-activity { display: grid; gap: 13px; margin-top: 12px; }
        .activity-row { display: flex; gap: 10px; align-items: flex-start; }
        .activity-dot { flex: 0 0 9px; width: 9px; height: 9px; margin-top: 5px; border-radius: 50%; background: var(--accent); }
        .activity-row strong { display: block; font-size: 12px; }
        .activity-row span { color: var(--muted); font-size: 11px; }
        .a-lower-grid { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(260px, .9fr); gap: 18px; margin-top: 18px; }
        .module-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin-top: 14px; }
        .module-item { display: grid; gap: 3px; padding: 12px; border: 1px solid var(--line); border-radius: 11px; color: var(--ink); background: #fbfcfe; text-decoration: none; }
        .module-item:hover { border-color: var(--accent); background: var(--accent-soft); }
        .module-item strong { font-size: 12px; }
        .module-item span { color: var(--muted); font-size: 10px; }
        .attention-box { padding: 14px; border-radius: 12px; background: var(--warning-soft); }
        .attention-box strong { display: block; margin-bottom: 3px; color: #7f4a09; }
        .attention-box p { margin-bottom: 12px; color: #94601d; font-size: 12px; }

        /* Variant B: dense, top-navigation module board with an inspector pane. */
        .b-board { padding: 20px; border: 1px solid #d8cabe; border-radius: var(--radius); background: #fffdfb; box-shadow: var(--shadow); }
        .b-topline { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding-bottom: 16px; border-bottom: 2px solid #1c2940; }
        .b-topline h2 { margin-bottom: 0; font-size: 22px; }
        .b-tabs { display: flex; flex-wrap: wrap; gap: 4px; margin: 18px 0; }
        .b-tabs button { padding: 8px 11px; border: 1px solid transparent; border-radius: 5px; color: #667085; background: transparent; font-size: 11px; font-weight: 850; }
        .b-tabs button.is-active, .b-tabs button:hover { color: #fff; border-color: #1c2940; background: #1c2940; }
        .b-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; margin-bottom: 18px; }
        .b-summary .kpi-card { border-radius: 5px; box-shadow: none; }
        .b-summary .kpi-card .kpi-value { font-size: 26px; }
        .b-board-grid { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(300px, .75fr); gap: 12px; align-items: start; }
        .b-panel { border: 1px solid #decfc3; border-radius: 5px; background: #fff; }
        .b-panel-head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; padding: 14px; border-bottom: 1px solid #eadfd7; }
        .b-panel-head h3 { margin-bottom: 2px; font-size: 15px; }
        .b-panel-body { padding: 14px; }
        .b-panel .table th, .b-panel .table td { border-color: #f0e7e0; }
        .b-inspector { position: sticky; top: 12px; }
        .b-inspector .field input, .b-inspector .field select, .b-inspector .field textarea { border-radius: 5px; }
        .b-ribbon { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; margin-top: 12px; }
        .b-ribbon button { min-height: 74px; padding: 10px; border: 1px solid #decfc3; border-radius: 5px; color: #344054; background: #fff; text-align: left; }
        .b-ribbon button:hover { border-color: var(--accent); background: var(--accent-soft); }
        .b-ribbon strong, .b-ribbon span { display: block; }
        .b-ribbon strong { margin-bottom: 4px; font-size: 12px; }
        .b-ribbon span { color: var(--muted); font-size: 10px; }
        .b-callout { display: flex; gap: 10px; align-items: flex-start; margin-top: 12px; padding: 12px; border-left: 4px solid var(--accent); background: #fff5ef; }
        .b-callout strong { display: block; margin-bottom: 2px; }
        .b-callout p { margin-bottom: 0; font-size: 11px; }

        /* Variant C: mobile-first field flow, with a persistent primary action. */
        .c-frame { max-width: 1120px; margin: 0 auto; padding: 16px; border: 1px solid #c7e5df; border-radius: 30px; background: #f8fffd; box-shadow: var(--shadow); }
        .c-topbar { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 6px 4px 18px; }
        .c-topbar .brand-mark { width: 38px; height: 38px; border-radius: 14px; }
        .c-profile { display: flex; align-items: center; gap: 9px; }
        .avatar { display: grid; width: 34px; height: 34px; place-items: center; border-radius: 50%; color: #fff; background: #d77c58; font-size: 12px; font-weight: 900; }
        .c-profile strong, .c-profile span { display: block; }
        .c-profile strong { font-size: 12px; }
        .c-profile span { color: var(--muted); font-size: 10px; }
        .c-hero { display: flex; justify-content: space-between; gap: 18px; align-items: center; padding: 22px; border-radius: 24px; color: #fff; background: linear-gradient(130deg, #087f78, #36a991); }
        .c-hero h2 { margin-bottom: 5px; font-size: 25px; }
        .c-hero p { max-width: 550px; margin-bottom: 0; color: rgba(255, 255, 255, .8); }
        .c-hero .button { color: #087f78; background: #fff; }
        .c-hero .button:hover { background: #e7fffa; }
        .c-feed-layout { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(300px, .9fr); gap: 16px; margin-top: 16px; }
        .c-feed, .c-drawer { padding: 18px; border: 1px solid #cfe9e3; border-radius: 22px; background: #fff; }
        .c-feed-head { display: flex; justify-content: space-between; align-items: end; gap: 12px; margin-bottom: 14px; }
        .c-feed-head h3 { margin-bottom: 2px; font-size: 16px; }
        .c-timeline { display: grid; gap: 10px; }
        .c-timeline-item { display: grid; grid-template-columns: 25px minmax(0, 1fr) auto; gap: 10px; align-items: start; padding: 12px; border: 1px solid #e2f0ed; border-radius: 16px; }
        .c-timeline-icon { display: grid; width: 25px; height: 25px; place-items: center; border-radius: 9px; color: #087f78; background: #e3f7f3; font-size: 11px; font-weight: 900; }
        .c-timeline-item strong, .c-timeline-item span { display: block; }
        .c-timeline-item strong { font-size: 12px; }
        .c-timeline-item span { margin-top: 2px; color: var(--muted); font-size: 10px; }
        .c-action-card { padding: 15px; border-radius: 18px; background: #f2fbf8; }
        .c-action-card + .c-action-card { margin-top: 10px; }
        .c-action-card .button { width: 100%; margin-top: 11px; }
        .c-action-top { display: flex; justify-content: space-between; gap: 12px; align-items: start; }
        .c-action-top strong { font-size: 13px; }
        .c-action-top p { margin: 3px 0 0; font-size: 11px; }
        .c-mini-kpis { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-top: 16px; }
        .c-mini-kpi { padding: 12px; border: 1px solid #d6ece7; border-radius: 16px; background: #fff; }
        .c-mini-kpi strong, .c-mini-kpi span { display: block; }
        .c-mini-kpi strong { font-size: 19px; }
        .c-mini-kpi span { color: var(--muted); font-size: 10px; }
        .c-bottom-nav { display: grid; grid-template-columns: repeat(5, 1fr); gap: 5px; margin-top: 16px; padding: 8px; border: 1px solid #cfe9e3; border-radius: 18px; background: #fff; }
        .c-bottom-nav button { padding: 8px 4px; border: 0; border-radius: 12px; color: var(--muted); background: transparent; font-size: 10px; font-weight: 800; }
        .c-bottom-nav button.is-active, .c-bottom-nav button:hover { color: #087f78; background: #e3f7f3; }

        /* Shared component lab and resolution notes. */
        .component-lab { margin-top: 32px; padding-top: 28px; border-top: 1px dashed #b8c6d5; }
        .component-lab-head { display: flex; justify-content: space-between; gap: 16px; align-items: end; margin-bottom: 16px; }
        .component-lab-head p { max-width: 620px; margin-bottom: 0; font-size: 12px; }
        .lab-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-top: 14px; }
        .lab-surface h3 { font-size: 15px; }
        .lab-surface > p { font-size: 11px; }
        .lab-kpi { box-shadow: none; border-color: color-mix(in srgb, var(--accent) 22%, var(--line)); }
        .lab-kpi .kpi-value { color: var(--accent-strong); }
        .state-stage { display: grid; gap: 10px; padding: 14px; border: 1px dashed #cfd8e3; border-radius: 12px; background: #fafbfd; }
        .state-controls { display: flex; gap: 4px; margin-bottom: 10px; }
        .state-controls button { padding: 6px 8px; border: 1px solid var(--line); border-radius: 7px; color: var(--muted); background: #fff; font-size: 10px; font-weight: 800; }
        .state-controls button.is-active { color: var(--accent-strong); border-color: color-mix(in srgb, var(--accent) 40%, var(--line)); background: var(--accent-soft); }
        .demo-state { display: none; min-height: 92px; place-items: center; padding: 14px; border-radius: 10px; text-align: center; }
        .demo-state.is-active { display: grid; }
        .demo-state strong, .demo-state span { display: block; }
        .demo-state strong { margin-bottom: 3px; font-size: 12px; }
        .demo-state span { color: var(--muted); font-size: 11px; }
        .state-empty { background: #f0f3f7; }
        .state-loading { background: var(--accent-soft); }
        .state-loading::before { content: ""; width: 22px; height: 22px; margin-bottom: 6px; border: 3px solid color-mix(in srgb, var(--accent) 18%, transparent); border-top-color: var(--accent); border-radius: 50%; animation: spin .8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .state-error { background: var(--danger-soft); }
        .state-error strong { color: var(--danger); }
        .coverage-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 9px; margin-top: 14px; }
        .coverage-card { padding: 12px; border: 1px solid var(--line); border-radius: 11px; background: rgba(255, 255, 255, .7); }
        .coverage-card strong, .coverage-card span { display: block; }
        .coverage-card strong { margin-bottom: 3px; font-size: 12px; }
        .coverage-card span { color: var(--muted); font-size: 10px; }
        .resolution { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(300px, .9fr); gap: 14px; margin-top: 14px; padding: 18px; border: 1px solid #cbd6e3; border-radius: var(--radius); background: #fff; box-shadow: var(--shadow); }
        .resolution h2 { color: var(--accent-strong); }
        .resolution p { font-size: 12px; }
        .resolution-list { display: grid; gap: 8px; margin: 12px 0 0; padding: 0; list-style: none; }
        .resolution-list li { padding-left: 18px; position: relative; color: #4b5a6e; font-size: 12px; }
        .resolution-list li::before { content: "→"; position: absolute; left: 0; color: var(--accent); font-weight: 900; }
        .token-table { width: 100%; border-collapse: collapse; }
        .token-table td { padding: 8px 0; border-bottom: 1px solid #edf0f4; color: #4b5a6e; font-size: 11px; }
        .token-table tr:last-child td { border-bottom: 0; }
        .token-table td:first-child { width: 35%; color: var(--muted); font-weight: 800; }
        .swatch { display: inline-block; width: 12px; height: 12px; margin-right: 4px; vertical-align: -2px; border: 1px solid rgba(0,0,0,.08); border-radius: 4px; }
        .modal-backdrop { position: fixed; z-index: 60; inset: 0; display: none; place-items: center; padding: 20px; background: rgba(17, 31, 54, .45); }
        .modal-backdrop.is-open { display: grid; }
        .modal { width: min(420px, 100%); padding: 22px; border-radius: 18px; background: #fff; box-shadow: 0 20px 60px rgba(17, 31, 54, .28); }
        .modal h2 { margin-bottom: 8px; }
        .modal p { font-size: 12px; }
        .modal .button-row { justify-content: flex-end; margin-top: 18px; }

        @media (max-width: 1050px) {
            .prototype-header { padding-right: 22px; padding-left: 22px; }
            .prototype-question { display: none; }
            .a-shell { grid-template-columns: 190px minmax(0, 1fr); }
            .a-main-grid, .a-lower-grid, .b-board-grid, .c-feed-layout, .resolution { grid-template-columns: 1fr; }
            .b-inspector { position: static; }
            .c-frame { max-width: none; }
        }
        @media (max-width: 760px) {
            .prototype-ribbon { top: 9px; right: 9px; font-size: 9px; }
            .prototype-header { display: grid; grid-template-columns: 1fr auto; gap: 12px; min-height: 92px; padding: 14px 16px; }
            .brand { min-width: 0; }
            .role-switch { justify-self: end; }
            .prototype-canvas { width: min(100% - 24px, 620px); padding-top: 28px; }
            .prototype-intro { display: block; }
            .intro-note { margin-top: 16px; }
            .prototype-state { right: 12px; bottom: 72px; max-width: calc(100vw - 24px); }
            .prototype-switcher { bottom: 10px; gap: 6px; padding: 6px 7px; }
            .switch-label { min-width: 150px; font-size: 11px; }
            .a-shell { display: block; }
            .a-sidebar { min-height: auto; margin-bottom: 14px; padding: 10px; }
            .a-sidebar-head { padding-bottom: 10px; border-bottom: 0; }
            .a-sidebar nav { display: flex; overflow-x: auto; padding-top: 4px; }
            .a-sidebar nav button { flex: 0 0 auto; white-space: nowrap; }
            .a-sidebar-foot { display: none; }
            .a-page-head { display: block; }
            .a-page-head .button { margin-top: 13px; }
            .a-kpis, .b-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .module-list, .b-ribbon, .coverage-grid, .lab-grid, .c-mini-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .b-board { padding: 13px; }
            .b-topline { display: block; }
            .b-topline .button { margin-top: 12px; }
            .b-tabs { overflow-x: auto; flex-wrap: nowrap; }
            .b-tabs button { flex: 0 0 auto; }
            .c-frame { padding: 10px; border-radius: 24px; }
            .c-hero { display: block; padding: 18px; }
            .c-hero .button { width: 100%; margin-top: 15px; }
            .c-feed, .c-drawer { padding: 13px; }
            .c-timeline-item { grid-template-columns: 25px minmax(0, 1fr); }
            .c-timeline-item .badge { grid-column: 2; justify-self: start; }
            .component-lab-head { display: block; }
            .component-lab-head p { margin-top: 7px; }
            .resolution { padding: 14px; }
        }
        @media (max-width: 430px) {
            .prototype-header { grid-template-columns: 1fr; }
            .role-switch { justify-self: start; }
            .a-kpis, .b-summary, .module-list, .b-ribbon, .coverage-grid, .lab-grid, .c-mini-kpis { grid-template-columns: 1fr; }
            .switch-label { min-width: 125px; }
        }
    </style>
</head>
<body data-variant="a">
    <div class="prototype-ribbon">PROTOTYPE · #78 · tanpa persistence</div>

    <header class="prototype-header">
        <a class="brand" href="?variant=a" aria-label="Kembali ke variasi A">
            <span class="brand-mark">P</span>
            <span class="brand-copy">
                <strong>PMS · Bahasa desain</strong>
                <small>Eksplorasi HITL untuk THC &amp; Mitra</small>
            </span>
        </a>
        <div class="prototype-question">
            <strong>Pertanyaan #78:</strong> bahasa visual bersama apa yang membuat menu operasional tetap terasa satu sistem?
        </div>
        <div class="role-switch" aria-label="Simulasi role">
            <button type="button" data-role="thc" class="is-active">THC</button>
            <button type="button" data-role="mitra">Mitra</button>
        </div>
    </header>

    <div class="prototype-state" id="prototype-state" aria-live="polite">state: memuat...</div>

    <main class="prototype-canvas">
        <section class="prototype-intro">
            <div>
                <div class="eyebrow">Tiga arah visual · satu kosakata</div>
                <h1>Bagaimana PMS terasa saat dipakai setiap hari?</h1>
                <p>Bandingkan hierarki, kepadatan, dan cara berpindah konteks. Semua data di bawah adalah mock-data dalam memori prototype; tombol hanya mensimulasikan feedback UI.</p>
                <div class="coverage-row" aria-label="Cakupan keluarga menu">
                    <span class="coverage-chip">Project</span>
                    <span class="coverage-chip">Mitra / User</span>
                    <span class="coverage-chip">Material / Unit</span>
                    <span class="coverage-chip">Warehouse</span>
                    <span class="coverage-chip">Request Material</span>
                    <span class="coverage-chip">Pemakaian Material</span>
                    <span class="coverage-chip">Rekon Material</span>
                    <span class="coverage-chip">Dashboard Mitra</span>
                </div>
            </div>
            <aside class="intro-note">
                <strong>Petunjuk review cepat</strong>
                Gunakan tombol ←/→ atau tombol di bar bawah. Ganti role THC/Mitra, buka aksi, cari Material, dan lihat bagaimana empty/loading/error tetap membawa konteks.
            </aside>
        </section>

        <!-- Variant A: a decision-first workspace. -->
        <section class="variant" id="variant-a" data-variant-key="a">
            <div class="a-shell">
                <aside class="a-sidebar" aria-label="Navigasi variasi A">
                    <div class="a-sidebar-head">
                        <span class="brand-mark">P</span>
                        <strong data-role-text data-thc="Ruang Kendali THC" data-mitra="Ruang Kerja Mitra">Ruang Kendali THC</strong>
                    </div>
                    <nav>
                        <button type="button" class="is-active" data-menu="Ringkasan">Ringkasan <span class="nav-count">4</span></button>
                        <button type="button" data-menu="Project">Project <span class="nav-count">18</span></button>
                        <button type="button" data-menu="Mitra & User">Mitra &amp; User</button>
                        <button type="button" data-menu="Material & Unit">Material &amp; Unit</button>
                        <button type="button" data-menu="Warehouse">Warehouse <span class="nav-count">2</span></button>
                        <button type="button" data-menu="Rekon Material">Rekon Material <span class="nav-count">3</span></button>
                    </nav>
                    <div class="a-sidebar-foot">
                        <strong data-role-text data-thc="Rina · User THC" data-mitra="Dimas · User Mitra">Rina · User THC</strong>
                        <span data-role-text data-thc="Cakupan: seluruh Mitra" data-mitra="Cakupan: PT Nusantara Kabel">Cakupan: seluruh Mitra</span>
                    </div>
                </aside>

                <div class="a-workspace">
                    <div class="a-page-head">
                        <div>
                            <div class="eyebrow">A · Ruang Kendali</div>
                            <h2 data-role-text data-thc="Selamat pagi, Rina" data-mitra="Selamat pagi, Dimas">Selamat pagi, Rina</h2>
                            <p data-role-text data-thc="Mulai dari hal yang membutuhkan keputusan THC hari ini." data-mitra="Mulai dari pekerjaan Project dan Pemakaian Material Anda hari ini.">Mulai dari hal yang membutuhkan keputusan THC hari ini.</p>
                        </div>
                        <button type="button" class="button" data-action="Membuka antrean keputusan"><span class="icon-dot"></span> Buka antrean</button>
                    </div>

                    <div class="a-kpis">
                        <article class="kpi-card"><span class="kpi-label">Project aktif</span><strong class="kpi-value">18</strong><span class="kpi-meta positive">+2 bulan ini</span></article>
                        <article class="kpi-card"><span class="kpi-label">Request Material</span><strong class="kpi-value">4</strong><span class="kpi-meta warning">menunggu keputusan</span></article>
                        <article class="kpi-card"><span class="kpi-label">Transit</span><strong class="kpi-value">2</strong><span class="kpi-meta warning">perlu perhatian</span></article>
                        <article class="kpi-card"><span class="kpi-label">Rekon Material</span><strong class="kpi-value">3</strong><span class="kpi-meta">menunggu approve</span></article>
                    </div>

                    <div class="a-main-grid">
                        <section class="surface surface-pad">
                            <div class="section-label">Fokus pertama</div>
                            <h2>Antrean yang perlu keputusan</h2>
                            <p class="tiny">Ringkasan mengarahkan Anda ke modul pemilik alur; tidak ada mutasi dari dashboard.</p>
                            <div class="table-wrap">
                                <table class="table">
                                    <thead><tr><th>Item</th><th>Pemilik</th><th>Usia</th><th>Status</th></tr></thead>
                                    <tbody>
                                        <tr><td class="primary-cell">MR-2608-0087 · Kabel FO 48C</td><td>PT Nusantara Kabel</td><td>2 jam</td><td><span class="badge badge-amber">diajukan</span></td></tr>
                                        <tr><td class="primary-cell">PM-2608-014 · PRJ-2607-0042</td><td>PT Sinar Jaya</td><td>5 jam</td><td><span class="badge badge-blue">Pemakaian</span></td></tr>
                                        <tr><td class="primary-cell">REK-2608-0009 · PRJ-2606-0021</td><td>PT Lintas Data</td><td>1 hari</td><td><span class="badge badge-amber">menunggu</span></td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="button-row" style="margin-top: 14px;"><button type="button" class="button secondary small" data-action="Menampilkan semua antrean">Lihat semua antrean</button><button type="button" class="button ghost small" data-action="Membuka filter antrean">Filter</button></div>
                        </section>

                        <section class="surface surface-pad">
                            <div class="section-label">Aktivitas lintas menu</div>
                            <h2>Terbaru</h2>
                            <div class="a-activity">
                                <div class="activity-row"><span class="activity-dot"></span><div><strong>Surat Jalan SJ-2608-0018 diterima</strong><span>Warehouse Mitra · 12 menit lalu</span></div></div>
                                <div class="activity-row"><span class="activity-dot" style="background:#d96b3b"></span><div><strong>Project PRJ-2607-0042 pindah Step</strong><span>Step 07 · 35 menit lalu</span></div></div>
                                <div class="activity-row"><span class="activity-dot" style="background:#16845f"></span><div><strong>Stok Material diperbarui</strong><span>Unit: meter · 1 jam lalu</span></div></div>
                                <div class="activity-row"><span class="activity-dot" style="background:#b56a12"></span><div><strong>User Mitra baru diaktifkan</strong><span>PT Lintas Data · 2 jam lalu</span></div></div>
                            </div>
                        </section>
                    </div>

                    <div class="a-lower-grid">
                        <section class="surface surface-pad">
                            <div class="section-label">Jalan masuk data</div>
                            <h2>Keluarga menu</h2>
                            <div class="module-list">
                                <button type="button" class="module-item" data-menu="Project"><strong>Project</strong><span>Control room, Step, RAB Jasa</span></button>
                                <button type="button" class="module-item" data-menu="Mitra & User"><strong>Mitra &amp; User</strong><span>Onboarding, Grup, Izin Aksi</span></button>
                                <button type="button" class="module-item" data-menu="Material & Unit"><strong>Material &amp; Unit</strong><span>Master, SN, drum, saldo</span></button>
                                <button type="button" class="module-item" data-menu="Warehouse"><strong>Warehouse</strong><span>Surat Jalan, Transit, stok</span></button>
                                <button type="button" class="module-item" data-menu="Request Material"><strong>Request Material</strong><span>Permintaan dan pengiriman bertahap</span></button>
                                <button type="button" class="module-item" data-menu="Dashboard Mitra"><strong>Dashboard Mitra</strong><span>Ringkasan pekerjaan milik Mitra</span></button>
                            </div>
                        </section>
                        <section class="surface surface-pad">
                            <div class="section-label">Peringatan konteks</div>
                            <div class="attention-box">
                                <strong>2 Transit melewati batas waktu</strong>
                                <p>Transit bukan stok Warehouse. Buka modul Surat Jalan untuk menyelesaikan selisih.</p>
                                <button type="button" class="button small" data-action="Membuka Transit">Buka Transit</button>
                            </div>
                            <div class="divider"></div>
                            <p class="tiny">Aturan yang ingin dibawa: kartu adalah pintu masuk; pemilik alur tetap menangani mutasi dan validasi.</p>
                        </section>
                    </div>
                    <div class="button-row" style="margin-top: 16px;"><button type="button" class="button secondary small" data-select-design="a">Tandai A sebagai kandidat</button></div>
                </div>
            </div>
        </section>

        <!-- Variant B: a dense top-nav board with module tabs and a form inspector. -->
        <section class="variant" id="variant-b" data-variant-key="b" hidden>
            <div class="b-board">
                <div class="b-topline">
                    <div>
                        <div class="eyebrow">B · Papan Modul</div>
                        <h2 data-role-text data-thc="Semua pekerjaan dalam satu papan" data-mitra="Pekerjaan yang perlu Anda selesaikan">Semua pekerjaan dalam satu papan</h2>
                    </div>
                    <button type="button" class="button" data-action="Membuat item baru">+ Buat item</button>
                </div>
                <div class="b-tabs" role="tablist" aria-label="Keluarga menu">
                    <button type="button" class="is-active" data-menu="Project">Project</button>
                    <button type="button" data-menu="Mitra & User">Mitra &amp; User</button>
                    <button type="button" data-menu="Material & Unit">Material &amp; Unit</button>
                    <button type="button" data-menu="Warehouse">Warehouse</button>
                    <button type="button" data-menu="Request Material">Request Material</button>
                    <button type="button" data-menu="Pemakaian Material">Pemakaian Material</button>
                    <button type="button" data-menu="Rekon Material">Rekon Material</button>
                </div>

                <div class="b-summary">
                    <article class="kpi-card"><span class="kpi-label">Project aktif</span><strong class="kpi-value">18</strong><span class="kpi-meta">6 perlu perhatian</span></article>
                    <article class="kpi-card"><span class="kpi-label">Stok siap pakai</span><strong class="kpi-value">74%</strong><span class="kpi-meta positive">+8% minggu ini</span></article>
                    <article class="kpi-card"><span class="kpi-label">Approval THC</span><strong class="kpi-value">07</strong><span class="kpi-meta warning">antrian aktif</span></article>
                    <article class="kpi-card"><span class="kpi-label">Mitra aktif</span><strong class="kpi-value">12</strong><span class="kpi-meta">28 User</span></article>
                </div>

                <div class="b-board-grid">
                    <section class="b-panel">
                        <div class="b-panel-head"><div><h3>Worklist Project</h3><span class="tiny">Data padat untuk dipindai cepat</span></div><button type="button" class="button ghost small" data-action="Mengurutkan tabel">Urutkan</button></div>
                        <div class="b-panel-body table-wrap">
                            <table class="table">
                                <thead><tr><th>Project</th><th>Mitra</th><th>Progress jasa</th><th>Material</th><th>Risiko</th></tr></thead>
                                <tbody>
                                    <tr><td class="primary-cell">PRJ-2607-0042<br><span class="tiny">Menara Selatan</span></td><td>PT Sinar Jaya</td><td><div style="min-width:90px"><div class="tiny">68%</div><div class="progress"><span style="width:68%"></span></div></div></td><td><span class="badge badge-green">siap</span></td><td><span class="badge badge-amber">SPI 0,91</span></td></tr>
                                    <tr><td class="primary-cell">PRJ-2606-0021<br><span class="tiny">Ring Barat</span></td><td>PT Lintas Data</td><td><div style="min-width:90px"><div class="tiny">42%</div><div class="progress"><span style="width:42%; background:#d96b3b"></span></div></div></td><td><span class="badge badge-red">kurang</span></td><td><span class="badge badge-red">SPI 0,78</span></td></tr>
                                    <tr><td class="primary-cell">PRJ-2608-0005<br><span class="tiny">Kawasan Timur</span></td><td>PT Nusantara Kabel</td><td><div style="min-width:90px"><div class="tiny">16%</div><div class="progress"><span style="width:16%; background:#16845f"></span></div></div></td><td><span class="badge badge-blue">Transit</span></td><td><span class="badge badge-neutral">normal</span></td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="b-callout"><div><strong>Bahasa yang diuji</strong><p>Status risiko diletakkan dekat baris sumber; label tidak hanya mengandalkan warna.</p></div></div>
                    </section>

                    <aside class="b-panel b-inspector">
                        <div class="b-panel-head"><div><h3>Ajukan Request Material</h3><span class="tiny">Form selalu terlihat di samping worklist</span></div><span class="badge badge-blue">simulasi</span></div>
                        <div class="b-panel-body">
                            <form data-demo-form="request">
                                <div class="field"><label for="b-project">Project</label><select id="b-project"><option>PRJ-2608-0005 · Kawasan Timur</option><option>PRJ-2607-0042 · Menara Selatan</option></select></div>
                                <div class="field"><label for="b-material-search">Material</label><div class="search-select" data-search-select><input class="search-input" id="b-material-search" autocomplete="off" placeholder="Cari Material atau Unit…"><div class="select-options"><button type="button" class="select-option" data-option="Kabel FO 48C · meter">Kabel FO 48C <span class="tiny">· meter</span></button><button type="button" class="select-option" data-option="Closure 48 Core · pcs">Closure 48 Core <span class="tiny">· pcs</span></button><button type="button" class="select-option" data-option="Drum Kabel ADSS · drum">Drum Kabel ADSS <span class="tiny">· drum</span></button><div class="select-empty" hidden>Tidak ada Material yang cocok.</div></div></div></div>
                                <div class="field"><label for="b-qty">Qty</label><input id="b-qty" value="0" inputmode="numeric"><span class="error-inline">Qty harus lebih besar dari 0.</span></div>
                                <div class="field"><label for="b-note">Catatan</label><textarea id="b-note" placeholder="Opsional">Pekerjaan Step 04 dimulai Senin.</textarea></div>
                                <button type="submit" class="button" style="width:100%">Simulasikan pengajuan</button>
                            </form>
                        </div>
                    </aside>
                </div>

                <div class="b-ribbon" aria-label="Contoh keluarga menu">
                    <button type="button" data-menu="Mitra & User"><strong>Mitra &amp; User</strong><span>12 Mitra · 28 User</span></button>
                    <button type="button" data-menu="Material & Unit"><strong>Material &amp; Unit</strong><span>Master data bersama</span></button>
                    <button type="button" data-menu="Warehouse"><strong>Warehouse</strong><span>4 lokasi · 2 Transit</span></button>
                    <button type="button" data-menu="Dashboard Mitra"><strong>Dashboard Mitra</strong><span>Scope satu Mitra</span></button>
                </div>
                <div class="button-row" style="margin-top: 16px;"><button type="button" class="button secondary small" data-select-design="b">Tandai B sebagai kandidat</button></div>
            </div>
        </section>

        <!-- Variant C: a mobile-first field flow with a large primary action. -->
        <section class="variant" id="variant-c" data-variant-key="c" hidden>
            <div class="c-frame">
                <div class="c-topbar">
                    <div class="c-profile"><span class="brand-mark">P</span><div><strong data-role-text data-thc="Pusat THC" data-mitra="Dashboard Mitra">Pusat THC</strong><span data-role-text data-thc="Ringkas, lalu putuskan" data-mitra="Pekerjaan di lapangan">Ringkas, lalu putuskan</span></div></div>
                    <div class="avatar" data-role-text data-thc="RT" data-mitra="DN">RT</div>
                </div>
                <section class="c-hero">
                    <div><div class="eyebrow" style="color:#baf7e9">C · Alur Lapangan</div><h2 data-role-text data-thc="Halo, Rina" data-mitra="Halo, Dimas">Halo, Rina</h2><p data-role-text data-thc="Satu tindakan utama, lalu kembali ke konteks Project." data-mitra="Selesaikan pekerjaan penting tanpa kehilangan konteks Project.">Satu tindakan utama, lalu kembali ke konteks Project.</p></div>
                    <button type="button" class="button" data-action="Membuka tindakan utama">Mulai tindakan</button>
                </section>

                <div class="c-mini-kpis">
                    <div class="c-mini-kpi"><strong>04</strong><span>perlu dicek</span></div>
                    <div class="c-mini-kpi"><strong>08</strong><span>Project aktif</span></div>
                    <div class="c-mini-kpi"><strong>92%</strong><span>kesiapan</span></div>
                </div>

                <div class="c-feed-layout">
                    <section class="c-feed">
                        <div class="c-feed-head"><div><h3>Langkah hari ini</h3><span class="tiny" data-role-text data-thc="Antrean keputusan THC" data-mitra="Pekerjaan Project Anda">Antrean keputusan THC</span></div><span class="badge badge-green">tersinkron 2 mnt</span></div>
                        <div class="c-timeline">
                            <div class="c-timeline-item"><div class="c-timeline-icon">01</div><div><strong>Periksa Request Material</strong><span>MR-2608-0087 · Kabel FO 48C · 240 meter</span></div><span class="badge badge-amber">diajukan</span></div>
                            <div class="c-timeline-item"><div class="c-timeline-icon">02</div><div><strong>Catat Pemakaian Material</strong><span>PRJ-2607-0042 · 3 jenis Material</span></div><span class="badge badge-blue">hari ini</span></div>
                            <div class="c-timeline-item"><div class="c-timeline-icon">03</div><div><strong>Review Rekon Material</strong><span>REK-2608-0009 · sisa dan waste</span></div><span class="badge badge-amber">menunggu</span></div>
                            <div class="c-timeline-item"><div class="c-timeline-icon">04</div><div><strong>Cek Surat Jalan di Transit</strong><span>SJ-2608-0018 · Warehouse Mitra</span></div><span class="badge badge-red">terlambat</span></div>
                        </div>
                    </section>
                    <aside class="c-drawer">
                        <div class="section-label">Kartu tindakan</div>
                        <div class="c-action-card"><div class="c-action-top"><div><strong>Dashboard Mitra</strong><p>Ringkasan progres dan Material milik Mitra.</p></div><span class="badge badge-green">aktif</span></div><div class="progress" style="margin-top:12px"><span style="width:72%"></span></div><button type="button" class="button small" data-action="Membuka Dashboard Mitra">Buka Dashboard Mitra</button></div>
                        <div class="c-action-card"><div class="c-action-top"><div><strong>Konfirmasi Rekon</strong><p>Pastikan sisa, retur, dan waste sudah jelas.</p></div><span class="badge badge-amber">3</span></div><button type="button" class="button secondary small" data-confirm="REK-2608-0009">Review dan konfirmasi</button></div>
                        <div class="c-action-card"><div class="c-action-top"><div><strong>Material &amp; Unit</strong><p>Cari saldo berdasarkan lokasi.</p></div><span class="badge badge-blue">read</span></div><button type="button" class="button ghost small" data-action="Membuka Material & Unit">Lihat saldo</button></div>
                    </aside>
                </div>
                <nav class="c-bottom-nav" aria-label="Navigasi bawah">
                    <button type="button" class="is-active" data-menu="Ringkasan">⌂<br>Beranda</button>
                    <button type="button" data-menu="Project">▣<br>Project</button>
                    <button type="button" data-menu="Material & Unit">◈<br>Material</button>
                    <button type="button" data-menu="Warehouse">↔<br>Warehouse</button>
                    <button type="button" data-menu="Mitra & User">•••<br>Lainnya</button>
                </nav>
                <div class="button-row" style="margin-top: 16px;"><button type="button" class="button secondary small" data-select-design="c">Tandai C sebagai kandidat</button></div>
            </div>
        </section>

        <section class="component-lab" id="component-lab">
            <div class="component-lab-head">
                <div><div class="eyebrow">Komponen bersama</div><h2>Yang harus terasa sama di semua menu</h2></div>
                <p>Lab ini sengaja diletakkan di bawah setiap variasi. Perhatikan label, status, feedback, dan konteks—bukan hanya warna atau bentuk kartu.</p>
            </div>
            <div class="coverage-grid">
                <div class="coverage-card"><strong>Project</strong><span>ID, Mitra, Status Project, Step</span></div>
                <div class="coverage-card"><strong>Mitra &amp; User</strong><span>owner, role, Izin Aksi, aktif</span></div>
                <div class="coverage-card"><strong>Material &amp; Unit</strong><span>jenis Material dan satuan</span></div>
                <div class="coverage-card"><strong>Warehouse</strong><span>lokasi, stok, Surat Jalan, Transit</span></div>
                <div class="coverage-card"><strong>Request Material</strong><span>ajukan → kirim bertahap</span></div>
                <div class="coverage-card"><strong>Pemakaian Material</strong><span>ajukan → approve THC</span></div>
                <div class="coverage-card"><strong>Rekon Material</strong><span>keluar, terpasang, sisa, waste</span></div>
                <div class="coverage-card"><strong>Dashboard Mitra</strong><span>scope data milik Mitra</span></div>
            </div>

            <div class="lab-grid">
                <section class="lab-surface">
                    <div class="section-label">KPI card + status badge</div>
                    <h3>Ringkasan yang bisa ditindaklanjuti</h3>
                    <p>Angka selalu ditemani unit, status, atau jalan masuk.</p>
                    <article class="kpi-card lab-kpi"><span class="kpi-label">Pemakaian Material</span><strong class="kpi-value">07</strong><span class="kpi-meta warning">3 menunggu approve THC</span></article>
                    <div class="status-row" style="margin-top: 12px;"><span class="badge badge-green">disetujui</span><span class="badge badge-amber">diajukan</span><span class="badge badge-red">ditolak</span><span class="badge badge-blue">Transit</span></div>
                </section>
                <section class="lab-surface">
                    <div class="section-label">Table</div>
                    <h3>Identitas tetap terlihat saat padat</h3>
                    <p>Baris dapat menjadi pintu ke modul pemilik.</p>
                    <div class="table-wrap"><table class="table"><thead><tr><th>Project</th><th>Mitra</th><th>Status</th></tr></thead><tbody><tr><td class="primary-cell">PRJ-2607-0042</td><td>PT Sinar Jaya</td><td><span class="badge badge-green">aktif</span></td></tr><tr><td class="primary-cell">PRJ-2606-0021</td><td>PT Lintas Data</td><td><span class="badge badge-neutral">selesai</span></td></tr></tbody></table></div>
                </section>
                <section class="lab-surface">
                    <div class="section-label">Form + searchable select</div>
                    <h3>Input dengan istilah domain lengkap</h3>
                    <p>Simulasi memilih Material, tanpa menulis ke database.</p>
                    <form data-demo-form="component">
                        <div class="field"><label for="lab-material-search">Material / Unit</label><div class="search-select" data-search-select><input class="search-input" id="lab-material-search" autocomplete="off" placeholder="Ketik untuk mencari…"><div class="select-options"><button type="button" class="select-option" data-option="Kabel FO 48C · meter">Kabel FO 48C <span class="tiny">· meter</span></button><button type="button" class="select-option" data-option="Closure 48 Core · pcs">Closure 48 Core <span class="tiny">· pcs</span></button><button type="button" class="select-option" data-option="Drum Kabel ADSS · drum">Drum Kabel ADSS <span class="tiny">· drum</span></button><div class="select-empty" hidden>Tidak ada Material yang cocok.</div></div></div></div>
                        <div class="field"><label for="lab-qty">Qty</label><input id="lab-qty" value="0"><span class="error-inline">Qty harus lebih besar dari 0.</span></div>
                        <button type="submit" class="button small">Validasi simulasi</button>
                    </form>
                </section>
            </div>

            <div class="lab-grid">
                <section class="lab-surface">
                    <div class="section-label">Empty / loading / error</div>
                    <h3>State tidak menghapus konteks</h3>
                    <p>Ganti state untuk melihat feedback panel yang sama.</p>
                    <div class="state-controls"><button type="button" class="is-active" data-state="empty">Kosong</button><button type="button" data-state="loading">Memuat</button><button type="button" data-state="error">Error</button></div>
                    <div class="state-stage">
                        <div class="demo-state state-empty is-active" data-state-panel="empty"><div><strong>Belum ada Rekon Material</strong><span>Rekon baru akan muncul setelah ada material keluar ke Project.</span></div></div>
                        <div class="demo-state state-loading" data-state-panel="loading"><div><strong>Memuat data Project…</strong><span>Konteks halaman tetap terlihat selama data dibaca.</span></div></div>
                        <div class="demo-state state-error" data-state-panel="error"><div><strong>Data belum dapat dimuat</strong><span>Coba lagi atau buka modul sumbernya.</span></div></div>
                    </div>
                </section>
                <section class="lab-surface">
                    <div class="section-label">Confirmation action</div>
                    <h3>Aksi berisiko perlu konfirmasi</h3>
                    <p>Feedback menjelaskan apa yang akan terjadi dan siapa pemilik keputusan.</p>
                    <div class="attention-box"><strong>Approve Rekon Material?</strong><p>REK-2608-0009 akan menjadi dasar pencocokan Project dan tidak dihapus setelah disetujui.</p><button type="button" class="button small" data-confirm="REK-2608-0009">Buka konfirmasi</button></div>
                </section>
                <section class="lab-surface">
                    <div class="section-label">Role &amp; scope</div>
                    <h3>THC dan Mitra bukan sekadar tema</h3>
                    <p data-role-text data-thc="User THC melihat lintas Mitra sesuai Izin Aksi." data-mitra="User Mitra hanya melihat data milik Mitranya, sesuai isolasi mitra.">User THC melihat lintas Mitra sesuai Izin Aksi.</p>
                    <div class="tag-row"><span class="tag">RLS</span><span class="tag">Izin Aksi</span><span class="tag">nonaktif ≠ hapus</span></div>
                    <button type="button" class="button ghost small" style="margin-top:14px" data-action="Menjelaskan batas role">Lihat batas role</button>
                </section>
            </div>

            <div class="resolution" id="resolution">
                <div>
                    <div class="eyebrow">Resolution · Variant A dipilih</div>
                    <h2><span class="selection-label">A — Ruang Kendali</span> menjadi bahasa bersama terpilih</h2>
                    <p>Keputusan memilih A karena hierarki keputusan cocok untuk THC, tetap menyediakan jalan masuk ke modul operasional, dan dapat diturunkan menjadi Dashboard Mitra tanpa mengubah kosakata. B tetap menjadi pembanding untuk pemindaian tabel; C tetap menjadi referensi layar kecil dan tindakan lapangan.</p>
                    <ul class="resolution-list">
                        <li><strong>Variasi terpilih:</strong> <span class="selection-label">A — Ruang Kendali</span>. Tombol kandidat di atas hanya dipertahankan untuk membandingkan referensi, bukan mengubah keputusan.</li>
                        <li><strong>Aturan komponen:</strong> satu primary action per konteks; status selalu memakai teks + warna; empty/loading/error mempertahankan identitas halaman; form memakai istilah domain lengkap.</li>
                        <li><strong>Scope:</strong> semua angka mock-data; prototype tidak membuat route production, mutation, session, atau persistence.</li>
                    </ul>
                </div>
                <div>
                    <div class="section-label">Token yang diuji</div>
                    <table class="token-table"><tr><td>Warna</td><td><span class="swatch" style="background:#172033"></span>ink #172033 · <span class="swatch" style="background:#4656d8"></span>accent aktif · amber untuk perhatian</td></tr><tr><td>Typography</td><td>Segoe UI/system sans; heading rapat, label kecil eksplisit</td></tr><tr><td>Spacing</td><td>4 / 8 / 12 / 16 / 24 / 32 px; 8 px sebagai unit dasar</td></tr><tr><td>Shape</td><td>A 16 px, B 7 px, C 24 px untuk menguji karakter—bukan aturan produksi</td></tr><tr><td>Artefak</td><td><code>resources/views/prototypes/ui-language.blade.php</code><br><code>/prototype/ui-language?variant=a|b|c</code></td></tr></table>
                </div>
            </div>
        </section>
    </main>

    <nav class="prototype-switcher" aria-label="Pilih variasi prototype">
        <button type="button" class="switch-arrow" data-switch="previous" aria-label="Variasi sebelumnya">←</button>
        <div class="switch-label" id="switch-label">A — Ruang Kendali<small>← / → untuk membandingkan</small></div>
        <button type="button" class="switch-arrow" data-switch="next" aria-label="Variasi berikutnya">→</button>
    </nav>

    <div class="modal-backdrop" id="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
        <div class="modal">
            <div class="eyebrow">Konfirmasi prototype</div>
            <h2 id="confirm-title">Approve Rekon Material?</h2>
            <p id="confirm-copy">Aksi ini hanya simulasi. Dalam aplikasi nyata, keputusan mengikuti authorization, validasi, dan alur pemilik Rekon Material.</p>
            <div class="button-row"><button type="button" class="button ghost" data-modal-close>Batal</button><button type="button" class="button" data-modal-confirm>Konfirmasi simulasi</button></div>
        </div>
    </div>

    <script>
        (() => {
            const variantOrder = ['a', 'b', 'c'];
            const variantMeta = {
                a: { label: 'A — Ruang Kendali', subtitle: 'decision-first workspace' },
                b: { label: 'B — Papan Modul', subtitle: 'dense module board' },
                c: { label: 'C — Alur Lapangan', subtitle: 'mobile-first field flow' },
            };
            const roleMeta = {
                thc: { label: 'THC', scope: 'lintas Mitra' },
                mitra: { label: 'Mitra', scope: 'milik Mitra sendiri' },
            };
            const stateNode = document.getElementById('prototype-state');
            const switchLabel = document.getElementById('switch-label');
            const modal = document.getElementById('confirm-modal');
            let currentVariant = new URLSearchParams(window.location.search).get('variant');
            let currentRole = 'thc';
            let selectedDesign = 'a';
            let selectedMenu = 'Ringkasan';
            let pendingConfirmation = 'REK-2608-0009';

            if (!variantMeta[currentVariant]) currentVariant = 'a';

            const setState = (message) => {
                stateNode.innerHTML = `<strong>state:</strong> variant=${currentVariant} · role=${currentRole} · menu=${selectedMenu} · kandidat=${selectedDesign.toUpperCase()} · ${message}`;
            };

            const setRoleText = () => {
                document.querySelectorAll('[data-role-text]').forEach((node) => {
                    const value = node.dataset[currentRole];
                    if (value) node.textContent = value;
                });
                document.querySelectorAll('[data-role]').forEach((button) => button.classList.toggle('is-active', button.dataset.role === currentRole));
            };

            const setVariant = (variant, announce = true) => {
                currentVariant = variantMeta[variant] ? variant : 'a';
                document.body.dataset.variant = currentVariant;
                document.querySelectorAll('.variant').forEach((section) => section.hidden = section.dataset.variantKey !== currentVariant);
                switchLabel.innerHTML = `${variantMeta[currentVariant].label}<small>${variantMeta[currentVariant].subtitle} · ← / →</small>`;
                const params = new URLSearchParams(window.location.search);
                params.set('variant', currentVariant);
                window.history.replaceState({}, '', `${window.location.pathname}?${params.toString()}${window.location.hash}`);
                setRoleText();
                setState(announce ? 'variasi diganti' : 'siap untuk review');
            };

            const setMenu = (menu, source) => {
                selectedMenu = menu;
                document.querySelectorAll('[data-menu]').forEach((button) => {
                    const sameMenu = button.dataset.menu === menu;
                    if (button.closest('.a-sidebar, .b-tabs, .c-bottom-nav')) button.classList.toggle('is-active', sameMenu);
                });
                setState(`${source || 'navigasi'} → ${menu}`);
            };

            const notify = (message) => setState(message);

            document.querySelectorAll('[data-switch]').forEach((button) => button.addEventListener('click', () => {
                const currentIndex = variantOrder.indexOf(currentVariant);
                const offset = button.dataset.switch === 'next' ? 1 : -1;
                setVariant(variantOrder[(currentIndex + offset + variantOrder.length) % variantOrder.length]);
            }));

            document.querySelectorAll('[data-role]').forEach((button) => button.addEventListener('click', () => {
                currentRole = button.dataset.role;
                setRoleText();
                notify(`role diganti ke ${roleMeta[currentRole].label} · scope ${roleMeta[currentRole].scope}`);
            }));

            document.querySelectorAll('[data-menu]').forEach((button) => button.addEventListener('click', () => setMenu(button.dataset.menu, 'menu')));
            document.querySelectorAll('[data-action]').forEach((button) => button.addEventListener('click', () => notify(`aksi simulasi: ${button.dataset.action}`)));

            document.querySelectorAll('[data-select-design]').forEach((button) => button.addEventListener('click', () => {
                selectedDesign = button.dataset.selectDesign;
                document.querySelectorAll('.selection-label').forEach((node) => node.textContent = variantMeta[selectedDesign].label);
                notify(`kandidat desain ditandai: ${variantMeta[selectedDesign].label}`);
            }));

            document.querySelectorAll('[data-search-select]').forEach((wrapper) => {
                const input = wrapper.querySelector('input');
                const options = [...wrapper.querySelectorAll('.select-option')];
                const empty = wrapper.querySelector('.select-empty');
                const filter = () => {
                    const term = input.value.toLowerCase().trim();
                    let visible = 0;
                    options.forEach((option) => {
                        const match = option.textContent.toLowerCase().includes(term);
                        option.hidden = !match;
                        if (match) visible += 1;
                    });
                    empty.hidden = visible !== 0;
                    wrapper.classList.add('is-open');
                };
                input.addEventListener('focus', filter);
                input.addEventListener('input', filter);
                options.forEach((option) => option.addEventListener('click', () => {
                    input.value = option.dataset.option;
                    wrapper.classList.remove('is-open');
                    notify(`Material dipilih: ${option.dataset.option}`);
                }));
            });

            document.addEventListener('click', (event) => {
                document.querySelectorAll('[data-search-select]').forEach((wrapper) => {
                    if (!wrapper.contains(event.target)) wrapper.classList.remove('is-open');
                });
            });

            document.querySelectorAll('[data-state]').forEach((button) => button.addEventListener('click', () => {
                const state = button.dataset.state;
                document.querySelectorAll('[data-state]').forEach((item) => item.classList.toggle('is-active', item === button));
                document.querySelectorAll('[data-state-panel]').forEach((panel) => panel.classList.toggle('is-active', panel.dataset.statePanel === state));
                notify(`component state: ${state}`);
            }));

            document.querySelectorAll('[data-demo-form]').forEach((form) => form.addEventListener('submit', (event) => {
                event.preventDefault();
                notify(`validasi ${form.dataset.demoForm}: mock-data tidak disimpan`);
            }));

            const openModal = (reference) => {
                pendingConfirmation = reference || 'REK-2608-0009';
                document.getElementById('confirm-title').textContent = `Approve ${pendingConfirmation}?`;
                modal.classList.add('is-open');
                notify(`konfirmasi dibuka untuk ${pendingConfirmation}`);
            };
            const closeModal = () => modal.classList.remove('is-open');
            document.querySelectorAll('[data-confirm]').forEach((button) => button.addEventListener('click', () => openModal(button.dataset.confirm)));
            document.querySelector('[data-modal-close]').addEventListener('click', closeModal);
            document.querySelector('[data-modal-confirm]').addEventListener('click', () => { closeModal(); notify(`konfirmasi simulasi untuk ${pendingConfirmation} selesai; tidak ada data berubah`); });
            modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(); });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') closeModal();
                if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
                if (event.target.closest('input, textarea, select, [contenteditable="true"]')) return;
                const currentIndex = variantOrder.indexOf(currentVariant);
                const offset = event.key === 'ArrowRight' ? 1 : -1;
                setVariant(variantOrder[(currentIndex + offset + variantOrder.length) % variantOrder.length]);
            });

            setVariant(currentVariant, false);
        })();
    </script>
</body>
</html>
