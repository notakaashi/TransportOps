<?php
/**
 * User Dashboard — Redesigned
 * Dashboard for logged-in non-admin users (Driver / Commuter)
 */

require_once "auth_helper.php";
secureSessionStart();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

checkUserActive();

if ($_SESSION["role"] === "Admin") {
    header("Location: admin_dashboard.php");
    exit();
}

$user_reports = [];
$total_reports = 0;
$verified_count = 0;
$pending_count = 0;
$this_week = 0;
$user_profile = ["profile_image" => null];
$trending_issues = [];
$community_feed = [];

try {
    $pdo = getDBConnection();

    $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
    $stmt->execute([$_SESSION["user_id"]]);
    $user_profile = $stmt->fetch(PDO::FETCH_ASSOC) ?? ["profile_image" => null];
    if ($user_profile["profile_image"]) {
        $_SESSION["profile_image"] = $user_profile["profile_image"];
    }

    $stmt = $pdo->prepare("
        SELECT r.id, r.crowd_level, r.delay_reason, r.timestamp, r.latitude, r.longitude,
               r.is_verified, r.peer_verifications, r.status,
               u.name as user_name, u.role as user_role,
               rd.name AS route_name
        FROM reports r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN route_definitions rd ON r.route_definition_id = rd.id
        WHERE r.user_id = ?
        ORDER BY r.timestamp DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION["user_id"]]);
    $user_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) as c FROM reports WHERE user_id = ?",
    );
    $stmt->execute([$_SESSION["user_id"]]);
    $total_reports = (int) ($stmt->fetch()["c"] ?? 0);

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) as c FROM reports WHERE user_id = ? AND is_verified = 1",
    );
    $stmt->execute([$_SESSION["user_id"]]);
    $verified_count = (int) ($stmt->fetch()["c"] ?? 0);

    $pending_count = $total_reports - $verified_count;

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as c FROM reports
        WHERE user_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$_SESSION["user_id"]]);
    $this_week = (int) ($stmt->fetch()["c"] ?? 0);

    // Community Pulse — trending issues (last 30 days)
    $stmt = $pdo->prepare("
        SELECT crowd_level, delay_reason, COUNT(*) AS report_count
        FROM reports
        WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY crowd_level, delay_reason
        ORDER BY report_count DESC
        LIMIT 6
    ");
    $stmt->execute();
    $trending_issues = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Community Pulse — recent community activity feed (any user)
    $stmt = $pdo->prepare("
        SELECT r.crowd_level, r.delay_reason, r.timestamp,
               rd.name AS route_name
        FROM reports r
        LEFT JOIN route_definitions rd ON r.route_definition_id = rd.id
        ORDER BY r.timestamp DESC
        LIMIT 5
    ");
    $stmt->execute();
    $community_feed = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("User dashboard error: " . $e->getMessage());
}

$user_name = htmlspecialchars($_SESSION["user_name"] ?? "User");
$user_role = htmlspecialchars($_SESSION["role"] ?? "Commuter");
$first_name = explode(" ", trim($_SESSION["user_name"] ?? "User"))[0];

function crowdBadgeClass(string $level): string
{
    return match ($level) {
        "Light" => "badge-light",
        "Moderate" => "badge-moderate",
        "Heavy" => "badge-heavy",
        default => "badge-unknown",
    };
}

function timeAgo(string $timestamp): string
{
    $seconds = time() - strtotime($timestamp);
    if ($seconds < 0) {
        return "just now";
    }
    if ($seconds < 60) {
        return "just now";
    }
    if ($seconds < 3600) {
        return floor($seconds / 60) . "m ago";
    }
    if ($seconds < 86400) {
        return floor($seconds / 3600) . "h ago";
    }
    if ($seconds < 604800) {
        return floor($seconds / 86400) . "d ago";
    }
    return date("M d", strtotime($timestamp));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Transport Ops</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Poppins:wght@700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }

        :root {
            --navy:      #22335C;
            --navy-deep: #0f1c36;
            --navy-mid:  #19284a;
            --slate:     #5B7B99;
            --gold:      #FBC061;
            --gold-dark: #e8a83e;
            --cream:     #E8E1D8;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #e8edf8 0%, #f0f4ff 40%, #edf3f0 70%, #f5f0ea 100%);
            min-height: 100vh;
            color: #1e293b;
        }

        /* ── Floating Nav ─────────────────────────────────── */
        .glass-nav {
            background: rgba(34,51,92,0.78);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 8px 32px rgba(15,28,54,0.35), 0 2px 8px rgba(0,0,0,0.15);
            transition: background 0.3s, box-shadow 0.3s, top 0.3s;
        }
        .glass-nav.scrolled {
            background: rgba(34,51,92,0.96);
            box-shadow: 0 12px 40px rgba(15,28,54,0.5), 0 4px 12px rgba(0,0,0,0.25);
        }
        .nav-link {
            display: inline-block; padding: 0.45rem 0.9rem; border-radius: 0.5rem;
            font-size: 0.875rem; font-weight: 500; color: #cbd5e1;
            border: 1px solid transparent; text-decoration: none; transition: all 0.2s;
        }
        .nav-link:hover  { background: rgba(255,255,255,0.14); border-color: rgba(255,255,255,0.22); color: #fff; }
        .nav-link.active { background: rgba(255,255,255,0.22); border-color: rgba(255,255,255,0.3);  color: #fff; }
        .nav-link-mobile {
            display: block; padding: 0.5rem 0.9rem; border-radius: 0.5rem;
            font-size: 0.875rem; font-weight: 500; color: #cbd5e1;
            border: 1px solid transparent; text-decoration: none; transition: all 0.2s;
        }
        .nav-link-mobile:hover  { background: rgba(255,255,255,0.14); border-color: rgba(255,255,255,0.22); color: #fff; }
        .nav-link-mobile.active { background: rgba(255,255,255,0.22); border-color: rgba(255,255,255,0.3);  color: #fff; }
        .glass-dropdown {
            background: rgba(25,40,74,0.97);
            backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(255,255,255,0.12);
            box-shadow: 0 8px 32px rgba(15,28,54,0.45);
        }

        /* ── Hero ─────────────────────────────────────────── */
        .hero {
            position: relative;
            background: linear-gradient(135deg, var(--navy-deep) 0%, var(--navy-mid) 55%, #1a2f5a 100%);
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute; inset: 0;
            background:
                radial-gradient(ellipse at 15% 60%, rgba(91,123,153,0.28) 0%, transparent 55%),
                radial-gradient(ellipse at 85% 15%, rgba(251,192,97,0.13) 0%, transparent 50%);
        }
        .hero-grid {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 48px 48px;
        }
        .hero-orb {
            position: absolute; border-radius: 50%; pointer-events: none;
        }
        .hero-orb-1 {
            width: 480px; height: 480px;
            top: -160px; right: -80px;
            background: radial-gradient(circle, rgba(91,123,153,0.2) 0%, transparent 70%);
            animation: orbPulse 18s ease-in-out infinite alternate;
        }
        .hero-orb-2 {
            width: 300px; height: 300px;
            bottom: -100px; left: 8%;
            background: radial-gradient(circle, rgba(251,192,97,0.09) 0%, transparent 70%);
            animation: orbPulse 24s ease-in-out infinite alternate-reverse;
        }
        @keyframes orbPulse {
            from { transform: translate(0,0) scale(1); }
            to   { transform: translate(28px,18px) scale(1.08); }
        }
        .role-badge {
            display: inline-flex; align-items: center; gap: 0.4rem;
            background: rgba(251,192,97,0.15); border: 1px solid rgba(251,192,97,0.35);
            color: var(--gold); border-radius: 999px;
            padding: 0.28rem 0.85rem; font-size: 0.75rem; font-weight: 600;
            letter-spacing: 0.06em; text-transform: uppercase;
        }
        .hero-title {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(1.8rem, 4vw, 2.9rem);
            font-weight: 800; color: #fff;
            line-height: 1.15; letter-spacing: -0.02em;
        }
        .hero-title .gold { color: var(--gold); }
        .hero-subtitle { color: #94a3b8; font-size: 0.975rem; line-height: 1.7; max-width: 480px; }
        .hero-avatar {
            width: 72px; height: 72px; border-radius: 50%;
            border: 3px solid rgba(251,192,97,0.5);
            object-fit: cover; flex-shrink: 0;
        }
        .hero-avatar-initials {
            width: 72px; height: 72px; border-radius: 50%;
            border: 3px solid rgba(251,192,97,0.5);
            background: var(--slate);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Poppins', sans-serif;
            font-size: 1.6rem; font-weight: 800; color: #fff;
            flex-shrink: 0;
        }
        .cta-btn {
            display: inline-flex; align-items: center; gap: 0.45rem;
            background: var(--gold); color: var(--navy-deep);
            font-weight: 700; font-size: 0.875rem;
            padding: 0.65rem 1.4rem; border-radius: 0.55rem;
            text-decoration: none; transition: all 0.2s;
            box-shadow: 0 4px 18px rgba(251,192,97,0.35);
        }
        .cta-btn:hover { background: var(--gold-dark); transform: translateY(-2px); box-shadow: 0 8px 26px rgba(251,192,97,0.45); }
        .cta-btn-ghost {
            display: inline-flex; align-items: center; gap: 0.45rem;
            background: rgba(255,255,255,0.1); color: #e2e8f0;
            font-weight: 600; font-size: 0.875rem;
            padding: 0.65rem 1.4rem; border-radius: 0.55rem;
            border: 1px solid rgba(255,255,255,0.2);
            text-decoration: none; transition: all 0.2s;
        }
        .cta-btn-ghost:hover { background: rgba(255,255,255,0.18); color: #fff; }

        /* ── Stats Strip ──────────────────────────────────── */
        .stats-strip {
            background: rgba(255,255,255,0.75);
            backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(255,255,255,0.92);
            box-shadow: 0 4px 28px rgba(34,51,92,0.08);
            border-radius: 1.25rem;
        }
        .stat-cell { padding: 1.3rem 1rem; text-align: center; }
        .stat-val {
            font-family: 'Poppins', sans-serif;
            font-size: 2rem; font-weight: 800; line-height: 1;
        }
        .stat-lbl { font-size: 0.75rem; color: #64748b; font-weight: 500; margin-top: 0.25rem; }
        .stat-sub { font-size: 0.7rem; color: #94a3b8; margin-top: 0.1rem; }

        /* ── Glass Cards ──────────────────────────────────── */
        .glass-card {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.95);
            box-shadow: 0 4px 24px rgba(34,51,92,0.07), 0 1px 4px rgba(34,51,92,0.04);
            border-radius: 1.125rem;
        }

        /* ── Action Cards ─────────────────────────────────── */
        .action-card {
            background: rgba(255,255,255,0.82);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.95);
            border-radius: 1.125rem;
            padding: 1.4rem;
            box-shadow: 0 2px 14px rgba(34,51,92,0.06);
            transition: transform 0.22s, box-shadow 0.22s;
            text-decoration: none; color: inherit; display: block;
            position: relative; overflow: hidden;
        }
        .action-card::after {
            content: ''; position: absolute;
            bottom: 0; left: 0; right: 0; height: 3px;
            border-radius: 0 0 1.125rem 1.125rem;
            opacity: 0; transition: opacity 0.22s;
        }
        .action-card:hover { transform: translateY(-5px); box-shadow: 0 12px 34px rgba(34,51,92,0.13); }
        .action-card:hover::after { opacity: 1; }
        .ac-gold::after  { background: var(--gold); }
        .ac-navy::after  { background: var(--navy); }
        .ac-slate::after { background: var(--slate); }
        .ac-teal::after  { background: #14b8a6; }
        .action-icon {
            width: 2.75rem; height: 2.75rem; border-radius: 0.75rem;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 1rem; flex-shrink: 0;
        }
        .ai-gold  { background: rgba(251,192,97,0.18); }
        .ai-navy  { background: rgba(34,51,92,0.1); }
        .ai-slate { background: rgba(91,123,153,0.12); }
        .ai-teal  { background: rgba(20,184,166,0.1); }
        .action-card h3 { font-size: 0.925rem; font-weight: 700; color: var(--navy); margin-bottom: 0.3rem; }
        .action-card p  { font-size: 0.8rem; color: #64748b; line-height: 1.5; }
        .action-arrow {
            position: absolute; top: 1.2rem; right: 1.2rem;
            color: #cbd5e1; transition: color 0.2s, transform 0.2s;
        }
        .action-card:hover .action-arrow { color: var(--gold); transform: translate(2px,-2px); }

        /* ── Section headings ─────────────────────────────── */
        .sec-eyebrow {
            font-size: 0.7rem; font-weight: 700; letter-spacing: 0.12em;
            text-transform: uppercase; color: var(--slate);
            display: flex; align-items: center; gap: 0.5rem;
        }
        .sec-eyebrow::before {
            content: ''; display: block; width: 1.3rem; height: 2px;
            background: var(--gold); border-radius: 999px;
        }
        .sec-heading {
            font-family: 'Poppins', sans-serif;
            font-size: 1.35rem; font-weight: 800; color: var(--navy);
            letter-spacing: -0.02em;
        }

        /* ── Table ────────────────────────────────────────── */
        .reports-table { width: 100%; border-collapse: collapse; }
        .reports-table thead th {
            padding: 0.75rem 1.25rem;
            font-size: 0.68rem; font-weight: 700;
            letter-spacing: 0.08em; text-transform: uppercase;
            color: var(--slate); text-align: left;
            border-bottom: 1px solid rgba(34,51,92,0.08);
            background: rgba(34,51,92,0.025);
        }
        .reports-table tbody tr {
            border-bottom: 1px solid rgba(34,51,92,0.06);
            transition: background 0.15s;
        }
        .reports-table tbody tr:last-child { border-bottom: none; }
        .reports-table tbody tr:hover { background: rgba(34,51,92,0.03); }
        .reports-table td { padding: 1rem 1.25rem; font-size: 0.875rem; vertical-align: middle; }
        .route-name { font-weight: 600; color: var(--navy); }
        .ts-date { font-size: 0.8rem; font-weight: 600; color: #334155; }
        .ts-time { font-size: 0.72rem; color: #94a3b8; margin-top: 0.1rem; }

        /* ── Badges ───────────────────────────────────────── */
        .badge {
            display: inline-flex; align-items: center; gap: 0.3rem;
            font-size: 0.72rem; font-weight: 700; padding: 0.22rem 0.65rem;
            border-radius: 999px; letter-spacing: 0.03em;
        }
        .badge::before {
            content: ''; width: 6px; height: 6px;
            border-radius: 50%; flex-shrink: 0;
        }
        .badge-light    { background: #dcfce7; color: #166534; }
        .badge-light::before { background: #16a34a; }
        .badge-moderate { background: #fef9c3; color: #854d0e; }
        .badge-moderate::before { background: #ca8a04; }
        .badge-heavy    { background: #fee2e2; color: #991b1b; }
        .badge-heavy::before { background: #dc2626; }
        .badge-unknown  { background: #f1f5f9; color: #475569; }
        .badge-unknown::before { background: #94a3b8; }
        .badge-verified { background: rgba(34,51,92,0.08); color: var(--navy); }
        .badge-verified::before { background: var(--gold); }
        .badge-pending  { background: rgba(251,192,97,0.15); color: #92600a; }
        .badge-pending::before  { background: var(--gold-dark); }

        /* ── Empty state ──────────────────────────────────── */
        .empty-state { text-align: center; padding: 3.5rem 1rem; }
        .empty-icon {
            width: 56px; height: 56px; border-radius: 1rem;
            background: rgba(34,51,92,0.07);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
        }

        /* ── Footer ───────────────────────────────────────── */
        .site-footer {
            background: linear-gradient(135deg, var(--navy-deep) 0%, var(--navy-mid) 100%);
            position: relative; overflow: hidden; margin-top: 4rem;
        }
        .site-footer::before {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(ellipse at 80% 50%, rgba(91,123,153,0.12) 0%, transparent 60%);
        }
        .footer-logo { font-family:'Poppins',sans-serif; font-size:1.2rem; font-weight:800; color:#fff; }
        .footer-logo span { color: var(--gold); }
        .footer-link { color:#94a3b8; text-decoration:none; font-size:0.85rem; transition:color 0.2s; }
        .footer-link:hover { color: var(--gold); }

        /* ── Scroll reveal ────────────────────────────────── */
        .reveal {
            opacity: 0; transform: translateY(24px);
            transition: opacity 0.6s cubic-bezier(.4,0,.2,1), transform 0.6s cubic-bezier(.4,0,.2,1);
        }
        .reveal.visible { opacity:1; transform:none; }
        .rd1 { transition-delay: 0.07s; }
        .rd2 { transition-delay: 0.14s; }
        .rd3 { transition-delay: 0.21s; }
        .rd4 { transition-delay: 0.28s; }

        @media (max-width: 640px) {
            .stat-val { font-size: 1.55rem; }
        }

        /* ── Community Pulse ──────────────────────────────── */
        .pulse-pill {
            display: inline-flex; align-items: center; gap: 0.35rem;
            padding: 0.3rem 0.8rem; border-radius: 999px;
            font-size: 0.72rem; font-weight: 600;
            border: 1px solid transparent; white-space: nowrap;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .pulse-pill:hover { transform: translateY(-1px); box-shadow: 0 3px 10px rgba(0,0,0,0.08); }
        .pulse-pill-heavy    { background: #fee2e2; color: #991b1b; border-color: rgba(220,38,38,0.2); }
        .pulse-pill-moderate { background: #fef9c3; color: #854d0e; border-color: rgba(202,138,4,0.2); }
        .pulse-pill-light    { background: #dcfce7; color: #166534; border-color: rgba(22,163,74,0.2); }
        .pulse-pill-unknown  { background: #f1f5f9; color: #475569; border-color: rgba(100,116,139,0.2); }
        .pulse-count {
            background: rgba(0,0,0,0.1); border-radius: 999px;
            padding: 0.07rem 0.4rem; font-size: 0.65rem; font-weight: 700;
        }
        .feed-item {
            display: flex; align-items: center; gap: 0.85rem;
            padding: 0.85rem 1.25rem;
            border-bottom: 1px solid rgba(34,51,92,0.06);
            transition: background 0.15s;
        }
        .feed-item:last-child { border-bottom: none; }
        .feed-item:hover { background: rgba(34,51,92,0.03); }
        .feed-dot {
            width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0;
        }
        .feed-dot-Heavy    { background: #dc2626; box-shadow: 0 0 0 3px rgba(220,38,38,0.15); }
        .feed-dot-Moderate { background: #ca8a04; box-shadow: 0 0 0 3px rgba(202,138,4,0.15); }
        .feed-dot-Light    { background: #16a34a; box-shadow: 0 0 0 3px rgba(22,163,74,0.15); }
        .feed-dot-unknown  { background: #94a3b8; box-shadow: 0 0 0 3px rgba(148,163,184,0.15); }
        .feed-route { font-size: 0.83rem; font-weight: 600; color: var(--navy); }
        .feed-meta  { font-size: 0.76rem; color: #64748b; margin-top: 0.2rem; display:flex; align-items:center; gap:0.4rem; flex-wrap:wrap; }
        .feed-time  { font-size: 0.72rem; color: #94a3b8; font-weight: 500; margin-left: auto; white-space: nowrap; flex-shrink: 0; padding-left: 0.5rem; }
        .live-dot {
            display: inline-block;
            width: 7px; height: 7px; border-radius: 50%; background: #22c55e;
            box-shadow: 0 0 0 0 rgba(34,197,94,0.4);
            animation: livePulse 2s infinite;
        }
        @keyframes livePulse {
            0%   { box-shadow: 0 0 0 0   rgba(34,197,94,0.4); }
            70%  { box-shadow: 0 0 0 6px rgba(34,197,94,0);   }
            100% { box-shadow: 0 0 0 0   rgba(34,197,94,0);   }
        }
        .trending-bar {
            padding: 0.9rem 1.25rem;
            border-bottom: 1px solid rgba(34,51,92,0.07);
            display: flex; align-items: center; gap: 0.6rem;
            flex-wrap: wrap; background: rgba(34,51,92,0.018);
        }
        .trending-label {
            font-size: 0.67rem; font-weight: 700;
            letter-spacing: 0.1em; text-transform: uppercase;
            color: var(--slate); flex-shrink: 0;
        }
    </style>
</head>
<body>

<!-- ════════════════ FLOATING NAV ════════════════ -->
<nav id="floatingNav" class="fixed top-4 left-1/2 -translate-x-1/2 z-40 glass-nav text-white rounded-2xl w-[calc(100%-2rem)] max-w-7xl">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-14">

            <div class="flex items-center gap-7">
                <a href="user_dashboard.php" id="brandLink"
                   style="font-family:'Poppins',sans-serif;font-size:1.2rem;font-weight:800;color:#fff;text-decoration:none;white-space:nowrap;letter-spacing:-0.01em;">
                    Transport<span style="color:var(--gold);">Ops</span>
                </a>
                <div class="hidden md:flex gap-1">
                    <a href="user_dashboard.php" class="nav-link active">Home</a>
                    <a href="about.php"          class="nav-link">About</a>
                    <a href="report.php"         class="nav-link">Submit Report</a>
                    <a href="reports_map.php"    class="nav-link">Reports Map</a>
                    <a href="routes.php"         class="nav-link">Routes</a>
                </div>
                <div id="mobileMenu" class="md:hidden hidden absolute top-full left-0 right-0 mt-2 flex flex-col gap-1 px-4 py-3 z-20 rounded-2xl"
                     style="background:rgba(25,40,74,0.97);backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,0.12);box-shadow:0 8px 32px rgba(15,28,54,0.4);">
                    <a href="user_dashboard.php" class="nav-link-mobile active">Home</a>
                    <a href="about.php"          class="nav-link-mobile">About</a>
                    <a href="report.php"         class="nav-link-mobile">Submit Report</a>
                    <a href="reports_map.php"    class="nav-link-mobile">Reports Map</a>
                    <a href="routes.php"         class="nav-link-mobile">Routes</a>
                </div>
            </div>

            <!-- Profile -->
            <div class="relative">
                <button id="profileMenuButton"
                        class="flex items-center gap-2 px-2 py-1.5 rounded-full hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/40">
                    <div class="hidden sm:flex flex-col items-end leading-tight">
                        <span class="text-sm text-white font-medium"><?= $user_name ?></span>
                        <span class="text-[11px] text-blue-200"><?= $user_role ?></span>
                    </div>
                    <div class="flex items-center gap-1">
                        <?php if ($user_profile["profile_image"]): ?>
                            <img src="uploads/<?= htmlspecialchars(
                                $user_profile["profile_image"],
                            ) ?>"
                                 alt="Profile" class="h-8 w-8 rounded-full object-cover border-2 border-white/50">
                        <?php else: ?>
                            <div class="h-8 w-8 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0"
                                 style="background:var(--slate);">
                                <?= strtoupper(
                                    substr($_SESSION["user_name"] ?? "U", 0, 1),
                                ) ?>
                            </div>
                        <?php endif; ?>
                        <svg class="w-4 h-4 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </button>
                <div id="profileMenu" class="hidden absolute right-0 top-12 w-48 glass-dropdown rounded-xl shadow-xl py-1 z-50">
                    <a href="profile.php"
                       class="block px-4 py-2 text-sm text-white hover:bg-white/10 mx-1 rounded-lg">View &amp; Edit Profile</a>
                    <a href="public_profile.php?id=<?= $_SESSION["user_id"] ?>"
                       class="block px-4 py-2 text-sm text-white hover:bg-white/10 mx-1 rounded-lg">Public Profile</a>
                    <div class="my-1 border-t border-white/10"></div>
                    <a href="logout.php"
                       class="block px-4 py-2 text-sm text-red-300 hover:bg-white/10 mx-1 rounded-lg">Logout</a>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- ════════════════ HERO ════════════════ -->
<section class="hero pt-20">
    <div class="hero-grid"></div>
    <div class="hero-orb hero-orb-1"></div>
    <div class="hero-orb hero-orb-2"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14 relative z-10">
        <div class="flex flex-col sm:flex-row sm:items-center gap-5">
            <!-- Avatar -->
            <?php if ($user_profile["profile_image"]): ?>
                <img src="uploads/<?= htmlspecialchars(
                    $user_profile["profile_image"],
                ) ?>"
                     alt="Avatar" class="hero-avatar">
            <?php else: ?>
                <div class="hero-avatar-initials">
                    <?= strtoupper(
                        substr($_SESSION["user_name"] ?? "U", 0, 1),
                    ) ?>
                </div>
            <?php endif; ?>

            <!-- Greeting -->
            <div>
                <div class="role-badge mb-3">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 8v4l3 3"/>
                    </svg>
                    <?= $user_role ?>
                </div>
                <h1 class="hero-title">
                    Welcome back,<br>
                    <span class="gold"><?= htmlspecialchars(
                        $first_name,
                    ) ?>.</span>
                </h1>
                <p class="hero-subtitle mt-2">
                    Help improve Metro Manila transit by submitting real-time crowding and delay reports. Every report counts.
                </p>
                <div class="flex flex-wrap gap-3 mt-6">
                    <a href="report.php" class="cta-btn">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        Submit Report
                    </a>
                    <a href="reports_map.php" class="cta-btn-ghost">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                        </svg>
                        Live Map
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════ STATS STRIP ════════════════ -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-6 relative z-10 mb-12">
    <div class="stats-strip reveal">
        <div class="grid grid-cols-2 md:grid-cols-4 divide-x divide-y md:divide-y-0" style="--tw-divide-opacity:1;border-color:rgba(34,51,92,0.08);">
            <div class="stat-cell">
                <div class="stat-val" style="color:var(--navy);"><?= $total_reports ?></div>
                <div class="stat-lbl">Total Reports</div>
                <div class="stat-sub">all time</div>
            </div>
            <div class="stat-cell">
                <div class="stat-val" style="color:#16a34a;"><?= $verified_count ?></div>
                <div class="stat-lbl">Verified</div>
                <div class="stat-sub">peer-confirmed</div>
            </div>
            <div class="stat-cell">
                <div class="stat-val" style="color:var(--gold-dark);"><?= $pending_count ?></div>
                <div class="stat-lbl">Pending</div>
                <div class="stat-sub">awaiting review</div>
            </div>
            <div class="stat-cell">
                <div class="stat-val" style="color:var(--slate);"><?= $this_week ?></div>
                <div class="stat-lbl">This Week</div>
                <div class="stat-sub">last 7 days</div>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════ MAIN CONTENT ════════════════ -->
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-20">

    <!-- ── Quick Actions ──────────────────────────── -->
    <div class="mb-12">
        <div class="flex items-center gap-3 mb-6 reveal">
            <div class="sec-eyebrow">Quick Actions</div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

            <!-- Submit Report -->
            <a href="report.php" class="action-card ac-gold reveal rd1">
                <svg class="action-arrow w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M17 7H7M17 7v10"/>
                </svg>
                <div class="action-icon ai-gold">
                    <svg width="20" height="20" fill="none" stroke="#c87d20" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                </div>
                <h3>Submit Report</h3>
                <p>Report crowd levels, delays, and disruptions on your route right now.</p>
            </a>

            <!-- Live Map -->
            <a href="reports_map.php" class="action-card ac-navy reveal rd2">
                <svg class="action-arrow w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M17 7H7M17 7v10"/>
                </svg>
                <div class="action-icon ai-navy">
                    <svg width="20" height="20" fill="none" stroke="#22335C" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                    </svg>
                </div>
                <h3>Reports Map</h3>
                <p>View live crowdsourced reports pinned on an interactive Metro Manila map.</p>
            </a>

            <!-- Routes -->
            <a href="routes.php" class="action-card ac-slate reveal rd3">
                <svg class="action-arrow w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M17 7H7M17 7v10"/>
                </svg>
                <div class="action-icon ai-slate">
                    <svg width="20" height="20" fill="none" stroke="#5B7B99" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h3>Browse Routes</h3>
                <p>Explore all active PUV routes, stops, and schedules across the network.</p>
            </a>

            <!-- Profile -->
            <a href="profile.php" class="action-card ac-teal reveal rd4">
                <svg class="action-arrow w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M17 7H7M17 7v10"/>
                </svg>
                <div class="action-icon ai-teal">
                    <svg width="20" height="20" fill="none" stroke="#0f766e" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <h3>My Profile</h3>
                <p>Update your profile, view your public page, and manage your account settings.</p>
            </a>
        </div>
    </div>

    <!-- ── Community Pulse ──────────────────────────── -->
    <div class="mb-12 reveal">
        <div class="flex items-center justify-between mb-5">
            <div>
                <div class="sec-eyebrow mb-1">
                    <span class="live-dot"></span>
                    Live Network
                </div>
                <h2 class="sec-heading">Community Pulse</h2>
            </div>
            <a href="reports_map.php"
               style="display:inline-flex;align-items:center;gap:0.4rem;font-size:0.8rem;font-weight:600;color:var(--navy);text-decoration:none;padding:0.45rem 1rem;border-radius:0.55rem;background:rgba(34,51,92,0.07);border:1px solid rgba(34,51,92,0.1);transition:all 0.2s;"
               onmouseover="this.style.background='var(--gold)';this.style.color='var(--navy-deep)';this.style.borderColor='var(--gold)'"
               onmouseout="this.style.background='rgba(34,51,92,0.07)';this.style.color='var(--navy)';this.style.borderColor='rgba(34,51,92,0.1)'">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                </svg>
                View Map
            </a>
        </div>

        <div class="glass-card overflow-hidden">

            <!-- Trending tags bar -->
            <?php if (!empty($trending_issues)): ?>
            <div class="trending-bar">
                <span class="trending-label">Trending</span>
                <?php foreach ($trending_issues as $issue):

                    $pillClass = match ($issue["crowd_level"]) {
                        "Heavy" => "pulse-pill-heavy",
                        "Moderate" => "pulse-pill-moderate",
                        "Light" => "pulse-pill-light",
                        default => "pulse-pill-unknown",
                    };
                    $label = $issue["delay_reason"]
                        ? htmlspecialchars(
                            $issue["crowd_level"] .
                                " · " .
                                $issue["delay_reason"],
                        )
                        : htmlspecialchars($issue["crowd_level"] . " Crowd");
                    ?>
                <span class="pulse-pill <?= $pillClass ?>">
                    <?= $label ?>
                    <span class="pulse-count"><?= $issue[
                        "report_count"
                    ] ?></span>
                </span>
                <?php
                endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Recent community activity feed -->
            <?php if (!empty($community_feed)): ?>
            <?php foreach ($community_feed as $item):

                $feedRoute = htmlspecialchars(
                    $item["route_name"] ?? "Unknown Route",
                );
                $feedDot = "feed-dot-" . ($item["crowd_level"] ?? "unknown");
                $feedDelay = $item["delay_reason"]
                    ? htmlspecialchars(substr($item["delay_reason"], 0, 40)) .
                        (strlen($item["delay_reason"]) > 40 ? "…" : "")
                    : null;
                ?>
            <div class="feed-item">
                <span class="feed-dot <?= $feedDot ?>"></span>
                <div style="flex:1;min-width:0;">
                    <div class="feed-route"><?= $feedRoute ?></div>
                    <div class="feed-meta">
                        <span class="badge <?= crowdBadgeClass(
                            $item["crowd_level"],
                        ) ?>"><?= htmlspecialchars(
    $item["crowd_level"],
) ?></span>
                        <?php if ($feedDelay): ?>
                        <span style="color:#cbd5e1;">·</span>
                        <span><?= $feedDelay ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <span class="feed-time"><?= timeAgo(
                    $item["timestamp"],
                ) ?></span>
            </div>
            <?php
            endforeach; ?>
            <?php else: ?>
            <div class="empty-state" style="padding:2.5rem 1rem;">
                <div class="empty-icon">
                    <svg width="24" height="24" fill="none" stroke="#5B7B99" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <p style="font-weight:600;color:#334155;margin-bottom:0.4rem;">No community activity yet</p>
                <p style="font-size:0.875rem;color:#94a3b8;">Reports from commuters across the network will appear here.</p>
            </div>
            <?php endif; ?>

        </div><!-- /.glass-card (community pulse) -->
    </div><!-- /.community pulse -->

    <!-- ── Recent Reports ─────────────────────────── -->
    <div class="reveal">
        <div class="flex items-center justify-between mb-5">
            <div>
                <div class="sec-eyebrow mb-1">Activity</div>
                <h2 class="sec-heading">Your Recent Reports</h2>
            </div>
            <?php if (!empty($user_reports)): ?>
            <a href="report.php"
               style="display:inline-flex;align-items:center;gap:0.4rem;font-size:0.8rem;font-weight:600;color:var(--navy);text-decoration:none;padding:0.45rem 1rem;border-radius:0.55rem;background:rgba(34,51,92,0.07);border:1px solid rgba(34,51,92,0.1);transition:all 0.2s;"
               onmouseover="this.style.background='var(--gold)';this.style.color='var(--navy-deep)';this.style.borderColor='var(--gold)'"
               onmouseout="this.style.background='rgba(34,51,92,0.07)';this.style.color='var(--navy)';this.style.borderColor='rgba(34,51,92,0.1)'">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                New Report
            </a>
            <?php endif; ?>
        </div>

        <div class="glass-card overflow-hidden">
            <?php if (empty($user_reports)): ?>
            <!-- Empty state -->
            <div class="empty-state">
                <div class="empty-icon">
                    <svg width="26" height="26" fill="none" stroke="#5B7B99" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <p style="font-weight:600;color:#334155;margin-bottom:0.4rem;">No reports yet</p>
                <p style="font-size:0.875rem;color:#94a3b8;margin-bottom:1.25rem;">Be the first to report conditions on your route.</p>
                <a href="report.php" class="cta-btn" style="font-size:0.825rem;padding:0.55rem 1.2rem;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Submit your first report
                </a>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th>Date &amp; Time</th>
                            <th>Route</th>
                            <th>Crowd Level</th>
                            <th>Delay Reason</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($user_reports as $r): ?>
                        <tr>
                            <td>
                                <div class="ts-date"><?= date(
                                    "M d, Y",
                                    strtotime($r["timestamp"]),
                                ) ?></div>
                                <div class="ts-time"><?= date(
                                    "H:i",
                                    strtotime($r["timestamp"]),
                                ) ?></div>
                            </td>
                            <td>
                                <span class="route-name"><?= htmlspecialchars(
                                    $r["route_name"] ?? "N/A",
                                ) ?></span>
                            </td>
                            <td>
                                <span class="badge <?= crowdBadgeClass(
                                    $r["crowd_level"],
                                ) ?>">
                                    <?= htmlspecialchars($r["crowd_level"]) ?>
                                </span>
                            </td>
                            <td style="color:#64748b;font-size:0.85rem;max-width:180px;">
                                <?php if ($r["delay_reason"]): ?>
                                    <?= htmlspecialchars(
                                        substr($r["delay_reason"], 0, 35),
                                    ) .
                                        (strlen($r["delay_reason"]) > 35
                                            ? "…"
                                            : "") ?>
                                <?php else: ?>
                                    <span style="color:#cbd5e1;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r["is_verified"]): ?>
                                    <span class="badge badge-verified">Verified</span>
                                <?php else: ?>
                                    <span class="badge badge-pending">Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</main>

<!-- ════════════════ FOOTER ════════════════ -->
<footer class="site-footer">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 relative z-10">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="footer-logo">Transport<span>Ops</span></div>
            <div class="flex flex-wrap gap-5 justify-center">
                <a href="user_dashboard.php" class="footer-link" style="color:var(--gold);">Home</a>
                <a href="about.php"          class="footer-link">About</a>
                <a href="report.php"         class="footer-link">Submit Report</a>
                <a href="reports_map.php"    class="footer-link">Reports Map</a>
                <a href="routes.php"         class="footer-link">Routes</a>
            </div>
            <p style="color:#475569;font-size:0.78rem;white-space:nowrap;">
                &copy; <?= date("Y") ?> Transport Ops
            </p>
        </div>
    </div>
</footer>

<script>
(function () {
    // Nav scroll effect
    const nav = document.getElementById('floatingNav');
    if (nav) {
        window.addEventListener('scroll', function () {
            if (window.scrollY > 20) {
                nav.classList.add('scrolled');
                nav.style.top = '0.5rem';
            } else {
                nav.classList.remove('scrolled');
                nav.style.top = '1rem';
            }
        }, { passive: true });
    }

    // Profile dropdown
    const btn  = document.getElementById('profileMenuButton');
    const menu = document.getElementById('profileMenu');
    if (btn && menu) {
        btn.addEventListener('click', function (e) { e.stopPropagation(); menu.classList.toggle('hidden'); });
        document.addEventListener('click', function () { if (menu) menu.classList.add('hidden'); });
    }

    // Mobile menu (brand tap)
    const brand  = document.getElementById('brandLink');
    const mobile = document.getElementById('mobileMenu');
    if (brand && mobile) {
        brand.addEventListener('click', function (e) {
            if (window.innerWidth < 768) { e.preventDefault(); mobile.classList.toggle('hidden'); }
        });
        document.addEventListener('click', function (ev) {
            if (mobile && !mobile.contains(ev.target) && ev.target !== brand)
                mobile.classList.add('hidden');
        });
    }

    // Scroll reveal
    const revealEls = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) { e.target.classList.add('visible'); io.unobserve(e.target); }
            });
        }, { threshold: 0.1 });
        revealEls.forEach(function (el) { io.observe(el); });
    } else {
        revealEls.forEach(function (el) { el.classList.add('visible'); });
    }
})();
</script>
</body>
</html>
