<?php /* Admin shared layout head – include inside <head> after page-specific styles */ ?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    /* ── Reset & Base ───────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body {
        font-family: 'Inter', sans-serif;
        background: #edf0f7;
        min-height: 100vh;
        color: #1e293b;
    }

    /* ── Animated Background ────────────────────────── */
    .bg-layer {
        position: fixed; inset: 0; z-index: 0;
        background: linear-gradient(145deg, #e8edf8 0%, #f0f4ff 35%, #edf3f0 65%, #f5f0ea 100%);
        overflow: hidden; pointer-events: none;
    }
    .bg-layer::before {
        content: '';
        position: absolute;
        width: 800px; height: 800px; border-radius: 50%;
        background: radial-gradient(circle, rgba(34,51,92,0.08) 0%, transparent 70%);
        top: -300px; left: -200px;
        animation: bgFloat1 20s ease-in-out infinite alternate;
    }
    .bg-layer::after {
        content: '';
        position: absolute;
        width: 600px; height: 600px; border-radius: 50%;
        background: radial-gradient(circle, rgba(91,123,153,0.07) 0%, transparent 70%);
        bottom: -200px; right: -100px;
        animation: bgFloat2 25s ease-in-out infinite alternate;
    }
    @keyframes bgFloat1 { from{transform:translate(0,0) scale(1)} to{transform:translate(80px,50px) scale(1.08)} }
    @keyframes bgFloat2 { from{transform:translate(0,0) scale(1)} to{transform:translate(-60px,-40px) scale(1.06)} }

    /* ── App Layout ─────────────────────────────────── */
    .app-layout { position: relative; z-index: 1; display: flex; min-height: 100vh; }

    /* ── Sidebar ────────────────────────────────────── */
    .sidebar {
        width: 256px; flex-shrink: 0;
        background: linear-gradient(180deg, #0f1c36 0%, #19284a 45%, #1d3055 100%);
        position: fixed; top: 0; left: 0; bottom: 0;
        display: flex; flex-direction: column;
        z-index: 40;
        box-shadow: 4px 0 30px rgba(0,0,0,0.25);
        transition: transform 0.3s cubic-bezier(.4,0,.2,1);
        overflow: hidden;
    }
    .sidebar::before {
        content: '';
        position: absolute; top: 0; left: 0; right: 0; height: 220px;
        background: radial-gradient(ellipse at 50% 0%, rgba(251,192,97,0.09) 0%, transparent 70%);
        pointer-events: none;
    }

    /* Brand */
    .sidebar-brand {
        padding: 1.5rem 1.375rem 1.25rem;
        border-bottom: 1px solid rgba(255,255,255,0.07);
        display: flex; align-items: center; gap: 0.75rem;
        position: relative; z-index: 1; flex-shrink: 0;
    }
    .brand-icon {
        width: 2.5rem; height: 2.5rem;
        background: linear-gradient(135deg, #FBC061 0%, #e09820 100%);
        border-radius: 0.625rem;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        box-shadow: 0 4px 16px rgba(251,192,97,0.4);
    }
    .brand-name { font-size: 0.975rem; font-weight: 800; color: #fff; letter-spacing: -0.02em; line-height: 1.1; }
    .brand-tag  { font-size: 0.62rem; color: rgba(255,255,255,0.38); font-weight: 500; letter-spacing: 0.07em; text-transform: uppercase; margin-top: 2px; }

    /* Nav */
    .sidebar-nav {
        flex: 1; padding: 0.875rem 0.875rem 0.5rem;
        overflow-y: auto; position: relative; z-index: 1;
    }
    .sidebar-nav::-webkit-scrollbar { width: 3px; }
    .sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 999px; }

    .nav-section-label {
        font-size: 0.6rem; font-weight: 700; letter-spacing: 0.13em;
        text-transform: uppercase; color: rgba(255,255,255,0.25);
        padding: 0.75rem 0.625rem 0.3rem;
    }
    .nav-link {
        display: flex; align-items: center; gap: 0.75rem;
        padding: 0.6rem 0.75rem; border-radius: 0.6rem;
        font-size: 0.845rem; font-weight: 500;
        color: rgba(255,255,255,0.58);
        text-decoration: none;
        transition: all 0.18s ease;
        margin-bottom: 2px; border: 1px solid transparent;
    }
    .nav-link:hover {
        background: rgba(255,255,255,0.08);
        color: rgba(255,255,255,0.9);
        transform: translateX(3px);
    }
    .nav-link.active {
        background: linear-gradient(135deg, rgba(251,192,97,0.18) 0%, rgba(91,123,153,0.22) 100%);
        color: #fbd07c;
        border-color: rgba(251,192,97,0.22);
        box-shadow: 0 2px 10px rgba(251,192,97,0.1);
    }
    .nav-link.active .nav-ico { color: #FBC061; }
    .nav-ico { width: 1.1rem; height: 1.1rem; flex-shrink: 0; transition: color 0.18s; opacity: 0.85; }
    .nav-link:hover .nav-ico { opacity: 1; }

    /* Sidebar Footer */
    .sidebar-footer {
        padding: 0.875rem; border-top: 1px solid rgba(255,255,255,0.07);
        position: relative; z-index: 1; flex-shrink: 0;
    }
    .user-info-card {
        background: rgba(255,255,255,0.06);
        border: 1px solid rgba(255,255,255,0.09);
        border-radius: 0.75rem;
        padding: 0.65rem 0.75rem;
        display: flex; align-items: center; gap: 0.625rem;
        margin-bottom: 0.625rem;
    }
    .user-ava {
        width: 2.2rem; height: 2.2rem; border-radius: 50%;
        background: linear-gradient(135deg, #5B7B99 0%, #22335C 100%);
        display: flex; align-items: center; justify-content: center;
        font-size: 0.7rem; font-weight: 800; color: #fff;
        flex-shrink: 0; letter-spacing: 0.02em;
    }
    .user-name       { font-size: 0.8rem; font-weight: 600; color: #fff; line-height: 1.2; }
    .user-role-label { font-size: 0.65rem; color: rgba(255,255,255,0.4); margin-top: 1px; }
    .logout-link {
        display: flex; align-items: center; justify-content: center; gap: 0.45rem;
        width: 100%; padding: 0.55rem;
        border-radius: 0.6rem;
        font-size: 0.8rem; font-weight: 600;
        color: rgba(255,110,110,0.82);
        background: rgba(220,38,38,0.08);
        border: 1px solid rgba(220,38,38,0.16);
        text-decoration: none;
        transition: all 0.18s ease;
    }
    .logout-link:hover { background: rgba(220,38,38,0.16); color: #ff7070; border-color: rgba(220,38,38,0.32); }

    /* Mobile hamburger */
    .hamburger-btn {
        display: none;
        position: fixed; top: 1rem; left: 1rem; z-index: 50;
        background: #19284a; border: 1px solid rgba(255,255,255,0.12);
        border-radius: 0.5rem; padding: 0.5rem; cursor: pointer; color: #fff;
        box-shadow: 0 4px 16px rgba(0,0,0,0.3);
        align-items: center; justify-content: center;
    }
    .sidebar-overlay {
        display: none; position: fixed; inset: 0;
        background: rgba(5,15,35,0.55); z-index: 35; backdrop-filter: blur(3px);
    }
    .sidebar-overlay.show { display: block; }

    @media (max-width: 768px) {
        .sidebar { transform: translateX(-100%); }
        .sidebar.open { transform: translateX(0); }
        .hamburger-btn { display: flex; }
        .main-area { margin-left: 0 !important; }
    }

    /* ── Main Area ──────────────────────────────────── */
    .main-area { margin-left: 256px; flex: 1; min-width: 0; }

    /* ── Page Header ────────────────────────────────── */
    .page-header {
        display: flex; align-items: center;
        justify-content: space-between;
        flex-wrap: wrap; gap: 1rem;
    }
    .page-title {
        font-size: 1.6rem; font-weight: 800;
        background: linear-gradient(135deg, #1a2a4c 0%, #4a6a99 100%);
        -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        letter-spacing: -0.025em; line-height: 1.15;
    }
    .page-subtitle { font-size: 0.845rem; color: #7a8aaa; margin-top: 0.2rem; font-weight: 400; }

    /* ── Shared Content Cards ───────────────────────── */
    .glass-card {
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(15px);
        -webkit-backdrop-filter: blur(15px);
        border: 1px solid rgba(255, 255, 255, 0.30);
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.18);
        border-radius: 1.125rem;
    }
    .admin-card {
        background: rgba(255,255,255,0.86);
        backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255,255,255,0.96);
        border-radius: 1.125rem;
        box-shadow: 0 4px 24px rgba(34,51,92,0.07), 0 1px 4px rgba(34,51,92,0.04);
        overflow: hidden;
    }
    .admin-card-header {
        padding: 1rem 1.375rem;
        border-bottom: 1px solid rgba(34,51,92,0.07);
        background: linear-gradient(135deg, rgba(34,51,92,0.03) 0%, rgba(255,255,255,0.5) 100%);
        display: flex; align-items: center; justify-content: space-between;
        gap: 0.75rem; flex-wrap: wrap;
    }
    .admin-card-title-wrap { display: flex; align-items: center; gap: 0.6rem; }
    .admin-card-icon {
        width: 1.75rem; height: 1.75rem; border-radius: 0.45rem;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;

        /* ── Metric Cards ───────────────────────────────── */
        .metric-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(34, 51, 92, 0.08), 0 1px 4px rgba(34, 51, 92, 0.04);
            border: 1px solid rgba(34, 51, 92, 0.06);
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(34, 51, 92, 0.12), 0 2px 8px rgba(34, 51, 92, 0.06);
        }
        .metric-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            position: relative;
        }
        .metric-icon::before {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: 0.75rem;
            background: linear-gradient(135deg, transparent, rgba(255,255,255,0.1));
            pointer-events: none;
        }
        .metric-green { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .metric-red { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        .metric-purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; }
        .metric-blue { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
        .metric-content {
            text-align: center;
        }
        .metric-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1;
            margin-bottom: 0.25rem;
        }
        .metric-label {
            font-size: 0.875rem;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        .metric-change {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            display: inline-block;
        }
        .metric-change.positive { background: #dcfce7; color: #166534; }
        .metric-change.negative { background: #fee2e2; color: #dc2626; }
        .metric-change.neutral { background: #f3f4f6; color: #6b7280; }
        .admin-card-title { font-size: 0.925rem; font-weight: 700; color: #1e293b; }
        .admin-card-body  { padding: 1.25rem 1.375rem; }
        background: linear-gradient(90deg, rgba(34,51,92,0.04) 0%, rgba(91,123,153,0.025) 100%);
    }
    .admin-table thead th {
        padding: 0.7rem 1.25rem; text-align: left;
        font-size: 0.67rem; font-weight: 700; letter-spacing: 0.09em;
        text-transform: uppercase; color: #64748b; white-space: nowrap;
    }
    .admin-table tbody tr { border-top: 1px solid rgba(34,51,92,0.055); transition: background 0.13s ease; }
    .admin-table tbody tr:first-child { border-top: none; }
    .admin-table tbody tr:hover { background: rgba(34,51,92,0.033); }
    .admin-table td { padding: 0.825rem 1.25rem; vertical-align: middle; font-size: 0.855rem; color: #334155; }
    .admin-table .empty-row td { padding: 2.5rem; text-align: center; color: #94a3b8; font-size: 0.855rem; }

    /* ── Shared Badges ──────────────────────────────── */
    .abadge {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 0.22rem 0.65rem; border-radius: 999px;
        font-size: 0.7rem; font-weight: 700; letter-spacing: 0.025em;
        white-space: nowrap; border: 1px solid transparent;
    }
    .abadge-dot { width: 5px; height: 5px; border-radius: 50%; flex-shrink: 0; }
    .abadge-light    { background: #dcfce7; color: #15803d; border-color: #bbf7d0; }
    .abadge-light    .abadge-dot { background: #22c55e; }
    .abadge-moderate { background: #fef9c3; color: #92400e; border-color: #fde68a; }
    .abadge-moderate .abadge-dot { background: #f59e0b; }
    .abadge-heavy    { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }
    .abadge-heavy    .abadge-dot { background: #ef4444; }
    .abadge-unknown  { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
    .abadge-admin    { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
    .abadge-driver   { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
    .abadge-commuter { background: #f8fafc; color: #475569; border-color: #e2e8f0; }
    .abadge-active   { background: #dcfce7; color: #15803d; border-color: #bbf7d0; }
    .abadge-inactive { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }
    .abadge-verified   { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
    .abadge-unverified { background: #fef9c3; color: #92400e; border-color: #fde68a; }

    /* ── Avatars ────────────────────────────────────── */
    .aava {
        width: 2rem; height: 2rem; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.65rem; font-weight: 800; color: #fff;
        flex-shrink: 0; letter-spacing: 0.02em;
    }
    .aava-admin    { background: linear-gradient(135deg,#ef4444,#b91c1c); }
    .aava-driver   { background: linear-gradient(135deg,#3b82f6,#1d4ed8); }
    .aava-commuter { background: linear-gradient(135deg,#64748b,#334155); }

    /* ── Shared Buttons ─────────────────────────────── */
    .abtn {
        display: inline-flex; align-items: center; gap: 0.4rem;
        padding: 0.45rem 1.1rem; border-radius: 0.5rem;
        font-size: 0.78rem; font-weight: 600;
        text-decoration: none; border: none; cursor: pointer;
        transition: all 0.18s ease; white-space: nowrap;
    }
    .abtn-primary {
        background: linear-gradient(135deg, #22335C 0%, #2e4a80 100%);
        color: #fff;
        box-shadow: 0 2px 10px rgba(34,51,92,0.25);
    }
    .abtn-primary:hover { box-shadow: 0 4px 16px rgba(34,51,92,0.38); transform: translateY(-1px); color: #fff; }
    .abtn-danger {
        background: linear-gradient(135deg, #7f1d1d 0%, #dc2626 100%);
        color: #fff;
        box-shadow: 0 2px 10px rgba(220,38,38,0.22);
    }
    .abtn-danger:hover { box-shadow: 0 4px 14px rgba(220,38,38,0.38); transform: translateY(-1px); color: #fff; }
    .abtn-success {
        background: linear-gradient(135deg, #14532d 0%, #16a34a 100%);
        color: #fff;
        box-shadow: 0 2px 10px rgba(22,163,74,0.22);
    }
    .abtn-success:hover { box-shadow: 0 4px 14px rgba(22,163,74,0.38); transform: translateY(-1px); color: #fff; }
    .abtn-ghost {
        background: rgba(255,255,255,0.8);
        color: #475569;
        border: 1px solid rgba(34,51,92,0.14);
    }
    .abtn-ghost:hover { background: #fff; color: #22335C; }
    .abtn-sm { padding: 0.3rem 0.75rem; font-size: 0.72rem; }

    /* ── Form Controls ──────────────────────────────── */
    .aform-label {
        display: block; font-size: 0.78rem; font-weight: 600; color: #374151; margin-bottom: 0.35rem;
    }
    .aform-input, .aform-select, .aform-textarea {
        width: 100%;
        padding: 0.55rem 0.875rem;
        border: 1px solid rgba(34,51,92,0.18);
        border-radius: 0.5rem;
        font-size: 0.855rem;
        font-family: 'Inter', sans-serif;
        color: #1e293b;
        background: rgba(255,255,255,0.9);
        transition: border-color 0.15s, box-shadow 0.15s;
        outline: none;
    }
    .aform-input:focus, .aform-select:focus, .aform-textarea:focus {
        border-color: #5B7B99;
        box-shadow: 0 0 0 3px rgba(91,123,153,0.12);
    }

    /* ── Alert / Flash messages ─────────────────────── */
    .alert {
        padding: 0.75rem 1rem;
        border-radius: 0.625rem;
        font-size: 0.845rem; font-weight: 500;
        display: flex; align-items: center; gap: 0.5rem;
        margin-bottom: 1rem;
    }
    .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .alert-warn    { background: #fef9c3; color: #92400e; border: 1px solid #fde68a; }

    /* ── Scrollbar ──────────────────────────────────── */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: rgba(34,51,92,0.18); border-radius: 999px; }
    ::-webkit-scrollbar-thumb:hover { background: rgba(34,51,92,0.32); }
</style>
