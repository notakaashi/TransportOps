<?php
/**
 * Landing Page
 * Public homepage for non-logged-in users
 */

require_once "auth_helper.php";
secureSessionStart();

$is_logged_in = isset($_SESSION["user_id"]);
$user_profile_data = ["profile_image" => null];
$total_reports = 0;

// Get total reports count for homepage
try {
    require_once "db.php";
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM reports");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_reports = isset($result["count"]) ? (int) $result["count"] : 0;
} catch (Exception $e) {
    $total_reports = 0;
}

if ($is_logged_in) {
    // Redirect logged-in users to their dashboard
    if ($_SESSION["role"] === "Admin") {
        header("Location: admin_dashboard.php");
        exit();
    }
    // Fetch profile image for nav
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
        $stmt->execute([$_SESSION["user_id"]]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row["profile_image"]) {
            $user_profile_data["profile_image"] = $row["profile_image"];
            $_SESSION["profile_image"] = $row["profile_image"];
        }
    } catch (Exception $e) {
        // ignore
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TransportOps — Metro Manila Transit Platform</title>
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
            min-height: 100vh;
            display: flex; flex-direction: column; justify-content: center;
        }
        .hero::before {
            content: '';
            position: absolute; inset: 0;
            background:
                radial-gradient(ellipse at 10% 70%, rgba(91,123,153,0.32) 0%, transparent 55%),
                radial-gradient(ellipse at 88% 12%, rgba(251,192,97,0.15) 0%, transparent 50%);
        }
        .hero-grid {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 48px 48px;
        }
        .hero-orb { position: absolute; border-radius: 50%; pointer-events: none; }
        .hero-orb-1 {
            width: 600px; height: 600px; top: -200px; right: -120px;
            background: radial-gradient(circle, rgba(91,123,153,0.22) 0%, transparent 70%);
            animation: orbPulse 18s ease-in-out infinite alternate;
        }
        .hero-orb-2 {
            width: 380px; height: 380px; bottom: -120px; left: 5%;
            background: radial-gradient(circle, rgba(251,192,97,0.1) 0%, transparent 70%);
            animation: orbPulse 24s ease-in-out infinite alternate-reverse;
        }
        .hero-orb-3 {
            width: 260px; height: 260px; top: 30%; left: 45%;
            background: radial-gradient(circle, rgba(91,123,153,0.1) 0%, transparent 70%);
            animation: orbPulse 30s ease-in-out infinite alternate;
        }
        @keyframes orbPulse {
            from { transform: translate(0,0) scale(1); }
            to   { transform: translate(28px,18px) scale(1.08); }
        }

        .gold-pill {
            display: inline-flex; align-items: center; gap: 0.4rem;
            background: rgba(251,192,97,0.15); border: 1px solid rgba(251,192,97,0.35);
            color: var(--gold); border-radius: 999px;
            padding: 0.3rem 0.9rem; font-size: 0.75rem; font-weight: 600;
            letter-spacing: 0.07em; text-transform: uppercase; margin-bottom: 1.25rem;
        }
        .hero-title {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(2.2rem, 5.5vw, 4rem);
            font-weight: 800; color: #fff;
            line-height: 1.12; letter-spacing: -0.025em;
        }
        .hero-title .gold { color: var(--gold); }
        .hero-subtitle {
            color: #94a3b8; font-size: clamp(0.95rem, 1.5vw, 1.1rem);
            line-height: 1.75; max-width: 540px; margin-top: 1rem;
        }
        .cta-btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            background: var(--gold); color: var(--navy-deep);
            font-weight: 700; font-size: 0.95rem;
            padding: 0.8rem 1.75rem; border-radius: 0.6rem;
            text-decoration: none; transition: all 0.2s;
            box-shadow: 0 4px 20px rgba(251,192,97,0.4);
        }
        .cta-btn:hover { background: var(--gold-dark); transform: translateY(-2px); box-shadow: 0 8px 28px rgba(251,192,97,0.5); }
        .cta-btn-ghost {
            display: inline-flex; align-items: center; gap: 0.5rem;
            background: rgba(255,255,255,0.1); color: #e2e8f0;
            font-weight: 600; font-size: 0.95rem;
            padding: 0.8rem 1.75rem; border-radius: 0.6rem;
            border: 1px solid rgba(255,255,255,0.22);
            text-decoration: none; transition: all 0.2s;
        }
        .cta-btn-ghost:hover { background: rgba(255,255,255,0.18); color: #fff; transform: translateY(-1px); }

        /* ── Scroll-down hint ─────────────────────────────── */
        .scroll-hint {
            position: absolute; bottom: 2.5rem; left: 50%; transform: translateX(-50%);
            display: flex; flex-direction: column; align-items: center; gap: 0.4rem;
            color: #475569; font-size: 0.72rem; letter-spacing: 0.08em; text-transform: uppercase;
            animation: bounceY 2.2s ease-in-out infinite;
        }
        @keyframes bounceY {
            0%,100% { transform: translateX(-50%) translateY(0); }
            50%      { transform: translateX(-50%) translateY(7px); }
        }

        /* ── Stats Strip ──────────────────────────────────── */
        .stats-strip {
            background: rgba(255,255,255,0.78);
            backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(255,255,255,0.95);
            box-shadow: 0 4px 28px rgba(34,51,92,0.08);
            border-radius: 1.25rem;
        }
        .stat-cell { padding: 1.5rem 1rem; text-align: center; }
        .stat-val {
            font-family: 'Poppins', sans-serif;
            font-size: 1.85rem; font-weight: 800; line-height: 1;
        }
        .stat-lbl { font-size: 0.75rem; color: #64748b; font-weight: 500; margin-top: 0.3rem; }

        /* ── Section Headings ─────────────────────────────── */
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
            font-size: clamp(1.5rem, 3vw, 2.1rem);
            font-weight: 800; color: var(--navy);
            letter-spacing: -0.02em; margin-top: 0.3rem;
        }
        .sec-body { font-size: 0.95rem; color: #64748b; line-height: 1.75; max-width: 520px; margin-top: 0.6rem; }

        /* ── Glass Card ───────────────────────────────────── */
        .glass-card {
            background: rgba(255,255,255,0.82);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.95);
            box-shadow: 0 4px 24px rgba(34,51,92,0.07), 0 1px 4px rgba(34,51,92,0.04);
            border-radius: 1.25rem;
        }

        /* ── Feature Cards ────────────────────────────────── */
        .feature-card {
            background: rgba(255,255,255,0.82);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.95);
            border-radius: 1.125rem;
            padding: 1.75rem;
            box-shadow: 0 2px 14px rgba(34,51,92,0.06);
            transition: transform 0.22s, box-shadow 0.22s;
            position: relative; overflow: hidden;
        }
        .feature-card::after {
            content: ''; position: absolute;
            bottom: 0; left: 0; right: 0; height: 3px;
            border-radius: 0 0 1.125rem 1.125rem;
            opacity: 0; transition: opacity 0.22s;
        }
        .feature-card:hover { transform: translateY(-6px); box-shadow: 0 14px 36px rgba(34,51,92,0.13); }
        .feature-card:hover::after { opacity: 1; }
        .fc-gold::after  { background: var(--gold); }
        .fc-navy::after  { background: var(--navy); }
        .fc-slate::after { background: var(--slate); }
        .fc-teal::after  { background: #14b8a6; }

        .feature-icon {
            width: 3rem; height: 3rem; border-radius: 0.875rem;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 1.25rem;
        }
        .fi-gold  { background: rgba(251,192,97,0.18); }
        .fi-navy  { background: rgba(34,51,92,0.1); }
        .fi-slate { background: rgba(91,123,153,0.12); }
        .fi-teal  { background: rgba(20,184,166,0.1); }

        .feature-card h3 { font-size: 1rem; font-weight: 700; color: var(--navy); margin-bottom: 0.5rem; }
        .feature-card p  { font-size: 0.875rem; color: #64748b; line-height: 1.65; }

        /* ── Guest Banner ─────────────────────────────────── */
        .guest-banner {
            background: rgba(34,51,92,0.05);
            border: 1.5px solid rgba(34,51,92,0.12);
            border-radius: 1rem;
            padding: 1.1rem 1.5rem;
        }

        /* ── CTA Banner ───────────────────────────────────── */
        .cta-banner {
            background: linear-gradient(135deg, var(--navy-deep) 0%, var(--navy-mid) 100%);
            border-radius: 1.5rem;
            position: relative; overflow: hidden;
        }
        .cta-banner::before {
            content: '';
            position: absolute; inset: 0;
            background:
                radial-gradient(ellipse at 0% 50%, rgba(91,123,153,0.25) 0%, transparent 55%),
                radial-gradient(ellipse at 100% 20%, rgba(251,192,97,0.12) 0%, transparent 50%);
        }
        .cta-banner-grid {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        /* ── Footer ───────────────────────────────────────── */
        .site-footer {
            background: linear-gradient(135deg, var(--navy-deep) 0%, var(--navy-mid) 100%);
            position: relative; overflow: hidden; margin-top: 5rem;
        }
        .site-footer::before {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(ellipse at 80% 50%, rgba(91,123,153,0.12) 0%, transparent 60%);
        }
        .footer-logo { font-family:'Poppins',sans-serif; font-size:1.2rem; font-weight:800; color:#fff; }
        .footer-logo span { color: var(--gold); }
        .footer-link { color:#94a3b8; text-decoration:none; font-size:0.85rem; transition:color 0.2s; }
        .footer-link:hover { color: var(--gold); }

        /* ── Scroll Reveal ────────────────────────────────── */
        .reveal {
            opacity: 0; transform: translateY(28px);
            transition: opacity 0.65s cubic-bezier(.4,0,.2,1), transform 0.65s cubic-bezier(.4,0,.2,1);
        }
        .reveal.visible { opacity: 1; transform: none; }
        .rd1 { transition-delay: 0.07s; }
        .rd2 { transition-delay: 0.15s; }
        .rd3 { transition-delay: 0.22s; }
        .rd4 { transition-delay: 0.29s; }

        @media (max-width: 640px) {
            .stat-val { font-size: 1.5rem; }
            .hero { min-height: auto; padding-top: 7rem; padding-bottom: 5rem; }
        }
    </style>
</head>
<body>

<!-- ════════════════ FLOATING NAV ════════════════ -->
<nav id="floatingNav" class="fixed top-4 left-1/2 -translate-x-1/2 z-40 glass-nav text-white rounded-2xl w-[calc(100%-2rem)] max-w-7xl">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-14">

            <!-- Brand + Desktop Links -->
            <div class="flex items-center gap-7">
                <a href="index.php" id="brandLink"
                   style="font-family:'Poppins',sans-serif;font-size:1.2rem;font-weight:800;color:#fff;text-decoration:none;white-space:nowrap;letter-spacing:-0.01em;">
                    Transport<span style="color:var(--gold);">Ops</span>
                </a>
                <div class="hidden md:flex gap-1">
                    <a href="index.php"       class="nav-link active">Home</a>
                    <a href="about.php"       class="nav-link">About</a>
                    <a href="reports_map.php" class="nav-link">Reports Map</a>
                    <a href="routes.php"      class="nav-link">Routes</a>
                    <?php if ($is_logged_in): ?>
                    <a href="report.php"      class="nav-link">Submit Report</a>
                    <?php endif; ?>
                </div>
                <!-- Mobile dropdown -->
                <div id="mobileMenu"
                     class="md:hidden hidden absolute top-full left-0 right-0 mt-2 flex flex-col gap-1 px-4 py-3 z-20 rounded-2xl"
                     style="background:rgba(25,40,74,0.97);backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,0.12);box-shadow:0 8px 32px rgba(15,28,54,0.4);">
                    <a href="index.php"       class="nav-link-mobile active">Home</a>
                    <a href="about.php"       class="nav-link-mobile">About</a>
                    <a href="reports_map.php" class="nav-link-mobile">Reports Map</a>
                    <a href="routes.php"      class="nav-link-mobile">Routes</a>
                    <?php if ($is_logged_in): ?>
                    <a href="report.php"      class="nav-link-mobile">Submit Report</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right side: auth -->
            <div class="flex items-center gap-2 sm:gap-3">
                <?php if ($is_logged_in): ?>
                <div class="relative">
                    <button id="profileMenuButton"
                            class="flex items-center gap-2 px-2 py-1.5 rounded-full hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/40">
                        <div class="hidden sm:flex flex-col items-end leading-tight">
                            <span class="text-sm text-white font-medium"><?= htmlspecialchars(
                                $_SESSION["user_name"],
                            ) ?></span>
                            <span class="text-[11px] text-blue-200"><?= htmlspecialchars(
                                $_SESSION["role"] ?? "User",
                            ) ?></span>
                        </div>
                        <div class="flex items-center gap-1">
                            <?php if ($user_profile_data["profile_image"]): ?>
                                <img src="uploads/<?= htmlspecialchars(
                                    $user_profile_data["profile_image"],
                                ) ?>"
                                     alt="Profile" class="h-8 w-8 rounded-full object-cover border-2 border-white/50">
                            <?php else: ?>
                                <div class="h-8 w-8 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0"
                                     style="background:var(--slate);">
                                    <?= strtoupper(
                                        substr(
                                            $_SESSION["user_name"] ?? "U",
                                            0,
                                            1,
                                        ),
                                    ) ?>
                                </div>
                            <?php endif; ?>
                            <svg class="w-4 h-4 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </button>
                    <div id="profileMenu" class="hidden absolute right-0 top-12 w-48 glass-dropdown rounded-xl shadow-xl py-1 z-50">
                        <a href="user_dashboard.php"
                           class="block px-4 py-2 text-sm text-white hover:bg-white/10 mx-1 rounded-lg">Dashboard</a>
                        <a href="profile.php"
                           class="block px-4 py-2 text-sm text-white hover:bg-white/10 mx-1 rounded-lg">View &amp; Edit Profile</a>
                        <a href="public_profile.php?id=<?= $_SESSION[
                            "user_id"
                        ] ?>"
                           class="block px-4 py-2 text-sm text-white hover:bg-white/10 mx-1 rounded-lg">Public Profile</a>
                        <div class="my-1 border-t border-white/10"></div>
                        <a href="logout.php"
                           class="block px-4 py-2 text-sm text-red-300 hover:bg-white/10 mx-1 rounded-lg">Logout</a>
                    </div>
                </div>
                <?php else: ?>
                <a href="register.php" class="nav-link" style="border-color:rgba(255,255,255,0.22);">Register</a>
                <a href="login.php"
                   class="text-sm font-semibold px-4 py-2 rounded-lg"
                   style="background:var(--gold);color:var(--navy-deep);text-decoration:none;transition:background 0.2s;"
                   onmouseover="this.style.background='var(--gold-dark)'"
                   onmouseout="this.style.background='var(--gold)'">Login</a>
                <?php endif; ?>
            </div>

        </div>
    </div>
</nav>

<!-- ════════════════ HERO ════════════════ -->
<section class="hero">
    <div class="hero-grid"></div>
    <div class="hero-orb hero-orb-1"></div>
    <div class="hero-orb hero-orb-2"></div>
    <div class="hero-orb hero-orb-3"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 pt-24 pb-16 sm:py-0">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">

            <!-- Left: Copy -->
            <div>
                <div class="gold-pill">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                    </svg>
                    Metro Manila Transit Platform
                </div>
                <h1 class="hero-title">
                    Smarter Transit,<br>
                    <span class="gold">Better Commutes.</span>
                </h1>
                <p class="hero-subtitle">
                    A crowdsourced public transportation monitoring platform connecting commuters, fleet operators, and city planners with real-time crowding and delay data across Metro Manila.
                </p>
                <div class="flex flex-wrap gap-3 mt-8">
                    <?php if ($is_logged_in): ?>
                    <a href="user_dashboard.php" class="cta-btn">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        Go to Dashboard
                    </a>
                    <a href="report.php" class="cta-btn-ghost">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        Submit Report
                    </a>
                    <?php else: ?>
                    <a href="register.php" class="cta-btn">
                        Get Started Free
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                    </a>
                    <a href="reports_map.php" class="cta-btn-ghost">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                        </svg>
                        View Live Map
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Feature Highlights Card -->
            <div class="hidden lg:block">
                <div class="glass-card p-8" style="background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.15);box-shadow:0 8px 40px rgba(0,0,0,0.25);">
                    <div style="font-size:0.7rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:rgba(251,192,97,0.7);margin-bottom:1.5rem;">Platform Highlights</div>
                    <div class="flex flex-col gap-5">
                        <div class="flex items-start gap-4">
                            <div style="width:2.5rem;height:2.5rem;border-radius:0.65rem;background:rgba(251,192,97,0.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <svg width="18" height="18" fill="none" stroke="var(--gold)" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                            </div>
                            <div>
                                <div style="color:#fff;font-weight:600;font-size:0.9rem;margin-bottom:0.2rem;">Live Route Monitoring</div>
                                <div style="color:#94a3b8;font-size:0.8rem;line-height:1.5;">Track PUV units and crowd levels across Metro Manila routes in real time.</div>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <div style="width:2.5rem;height:2.5rem;border-radius:0.65rem;background:rgba(91,123,153,0.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <svg width="18" height="18" fill="none" stroke="#a5c0d8" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div>
                                <div style="color:#fff;font-weight:600;font-size:0.9rem;margin-bottom:0.2rem;">Peer-Verified Reports</div>
                                <div style="color:#94a3b8;font-size:0.8rem;line-height:1.5;">Crowdsourced data validated through a trust-score system for accuracy.</div>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <div style="width:2.5rem;height:2.5rem;border-radius:0.65rem;background:rgba(20,184,166,0.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <svg width="18" height="18" fill="none" stroke="#5eead4" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </div>
                            <div>
                                <div style="color:#fff;font-weight:600;font-size:0.9rem;margin-bottom:0.2rem;">Admin Analytics</div>
                                <div style="color:#94a3b8;font-size:0.8rem;line-height:1.5;">Rich dashboards for operators to optimize fleet deployment and routes.</div>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <div style="width:2.5rem;height:2.5rem;border-radius:0.65rem;background:rgba(251,192,97,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <svg width="18" height="18" fill="none" stroke="var(--gold)" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 2v3m0 14v3M2 12h3m14 0h3"/></svg>
                            </div>
                            <div>
                                <div style="color:#fff;font-weight:600;font-size:0.9rem;margin-bottom:0.2rem;">GPS Geofencing</div>
                                <div style="color:#94a3b8;font-size:0.8rem;line-height:1.5;">Reports are validated by proximity to ensure on-route accuracy.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Scroll hint -->
    <div class="scroll-hint hidden sm:flex">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
    </div>
</section>

<!-- ════════════════ STATS STRIP ════════════════ -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-6 relative z-10 mb-16">
    <div class="stats-strip reveal">
        <div class="grid grid-cols-2 md:grid-cols-4 divide-x divide-y md:divide-y-0" style="border-color:rgba(34,51,92,0.08);">
            <div class="stat-cell">
                <div class="stat-val" style="color:var(--navy);">Real-Time</div>
                <div class="stat-lbl">Fleet Tracking</div>
            </div>
            <div class="stat-cell">
                <div class="stat-val" style="color:var(--gold-dark);">360°</div>
                <div class="stat-lbl">Route Coverage</div>
            </div>
            <div class="stat-cell">
                <div class="stat-val" style="color:var(--slate);"><?php echo number_format($total_reports); ?></div>
                <div class="stat-lbl">Crowding Reports</div>
            </div>
            <div class="stat-cell">
                <div class="stat-val" style="color:#16a34a;">Trusted</div>
                <div class="stat-lbl">Peer-Verified Data</div>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════ FEATURES ════════════════ -->
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-20">

    <!-- Section Header -->
    <div class="text-center mb-12 reveal">
        <div class="sec-eyebrow justify-center">Platform Features</div>
        <h2 class="sec-heading" style="margin-top:0.5rem;">Everything You Need for<br>Smarter Transit</h2>
        <p class="sec-body mx-auto text-center">From real-time fleet monitoring to peer-verified crowd reports, TransportOps gives everyone in the ecosystem the data they need.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-16">

        <!-- Feature 1 -->
        <div class="feature-card fc-gold reveal rd1">
            <div class="feature-icon fi-gold">
                <svg width="22" height="22" fill="none" stroke="#c87d20" stroke-width="2.2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                </svg>
            </div>
            <h3>Fleet Management</h3>
            <p>Monitor and manage your entire PUV fleet in real-time. Track routes, locations, and operational status across the network.</p>
        </div>

        <!-- Feature 2 -->
        <div class="feature-card fc-slate reveal rd2">
            <div class="feature-icon fi-slate">
                <svg width="22" height="22" fill="none" stroke="#5B7B99" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <h3>Crowdsourcing Heatmap</h3>
            <p>Visualize crowdsourced demand data with interactive heatmaps. Identify high-traffic routes and optimize service deployment.</p>
        </div>

        <!-- Feature 3 -->
        <div class="feature-card fc-navy reveal rd3">
            <div class="feature-icon fi-navy">
                <svg width="22" height="22" fill="none" stroke="#22335C" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <h3>Real-Time Reports</h3>
            <p>Collect and analyze real-time reports from commuters and drivers. Track crowding levels and delays as they happen.</p>
        </div>

        <!-- Feature 4 -->
        <div class="feature-card fc-teal reveal rd4">
            <div class="feature-icon fi-teal">
                <svg width="22" height="22" fill="none" stroke="#0f766e" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <h3>Trust Verification</h3>
            <p>A peer-based trust score system validates reports automatically, ensuring only reliable, on-route data is surfaced.</p>
        </div>

    </div>

    <!-- ── Guest Banner ────────────────────────────── -->
    <?php if (!$is_logged_in): ?>
    <div class="guest-banner reveal flex flex-col sm:flex-row items-center justify-between gap-5 mb-16">
        <div class="flex items-start gap-4">
            <div style="width:2.5rem;height:2.5rem;border-radius:0.65rem;background:rgba(251,192,97,0.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:0.1rem;">
                <svg width="18" height="18" fill="none" stroke="var(--gold-dark)" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p style="font-weight:600;color:var(--navy);font-size:0.9rem;margin-bottom:0.2rem;">Browsing as a Guest</p>
                <p style="font-size:0.85rem;color:#64748b;line-height:1.6;">
                    You can view routes and the live reports map freely.
                    <strong style="color:var(--navy);">Create a free account</strong> to submit reports, earn trust scores, and help improve Metro Manila transit for everyone.
                </p>
            </div>
        </div>
        <div class="flex gap-2 flex-shrink-0">
            <a href="login.php"
               style="font-size:0.85rem;font-weight:600;padding:0.55rem 1.1rem;border-radius:0.6rem;border:1.5px solid rgba(34,51,92,0.25);color:var(--navy);text-decoration:none;transition:all 0.2s;white-space:nowrap;"
               onmouseover="this.style.background='rgba(34,51,92,0.07)'"
               onmouseout="this.style.background='transparent'">Login</a>
            <a href="register.php"
               style="font-size:0.85rem;font-weight:700;padding:0.55rem 1.25rem;border-radius:0.6rem;background:var(--gold);color:var(--navy-deep);text-decoration:none;transition:all 0.2s;white-space:nowrap;box-shadow:0 4px 14px rgba(251,192,97,0.3);"
               onmouseover="this.style.background='var(--gold-dark)'"
               onmouseout="this.style.background='var(--gold)'">Register Free</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── CTA Banner ──────────────────────────────── -->
    <div class="cta-banner reveal">
        <div class="cta-banner-grid"></div>
        <div class="max-w-4xl mx-auto px-8 py-14 text-center relative z-10">
            <div style="display:inline-flex;align-items:center;gap:0.4rem;background:rgba(251,192,97,0.15);border:1px solid rgba(251,192,97,0.3);color:var(--gold);border-radius:999px;padding:0.28rem 0.9rem;font-size:0.72rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;margin-bottom:1rem;">
                Start Today
            </div>
            <h2 style="font-family:'Poppins',sans-serif;font-size:clamp(1.6rem,3.5vw,2.4rem);font-weight:800;color:#fff;letter-spacing:-0.02em;margin-bottom:0.75rem;line-height:1.2;">
                Join the Network.<br>
                <span style="color:var(--gold);">Improve the Commute.</span>
            </h2>
            <p style="color:#94a3b8;font-size:0.975rem;line-height:1.7;max-width:480px;margin:0 auto 2rem;">
                Every report you submit helps thousands of Metro Manila commuters plan better trips. It only takes a few seconds.
            </p>
            <div class="flex flex-wrap gap-3 justify-center">
                <?php if ($is_logged_in): ?>
                <a href="report.php"
                   style="display:inline-flex;align-items:center;gap:0.45rem;background:var(--gold);color:var(--navy-deep);font-weight:700;font-size:0.95rem;padding:0.8rem 1.75rem;border-radius:0.6rem;text-decoration:none;transition:all 0.2s;box-shadow:0 4px 20px rgba(251,192,97,0.4);"
                   onmouseover="this.style.background='var(--gold-dark)'"
                   onmouseout="this.style.background='var(--gold)'">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Submit a Report
                </a>
                <a href="reports_map.php"
                   style="display:inline-flex;align-items:center;gap:0.45rem;background:rgba(255,255,255,0.1);color:#e2e8f0;font-weight:600;font-size:0.95rem;padding:0.8rem 1.75rem;border-radius:0.6rem;border:1px solid rgba(255,255,255,0.2);text-decoration:none;transition:all 0.2s;"
                   onmouseover="this.style.background='rgba(255,255,255,0.18)'"
                   onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                    View Live Map
                </a>
                <?php else: ?>
                <a href="register.php"
                   style="display:inline-flex;align-items:center;gap:0.45rem;background:var(--gold);color:var(--navy-deep);font-weight:700;font-size:0.95rem;padding:0.8rem 1.75rem;border-radius:0.6rem;text-decoration:none;transition:all 0.2s;box-shadow:0 4px 20px rgba(251,192,97,0.4);"
                   onmouseover="this.style.background='var(--gold-dark)'"
                   onmouseout="this.style.background='var(--gold)'">
                    Create Free Account
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
                <a href="login.php"
                   style="display:inline-flex;align-items:center;gap:0.45rem;background:rgba(255,255,255,0.1);color:#e2e8f0;font-weight:600;font-size:0.95rem;padding:0.8rem 1.75rem;border-radius:0.6rem;border:1px solid rgba(255,255,255,0.2);text-decoration:none;transition:all 0.2s;"
                   onmouseover="this.style.background='rgba(255,255,255,0.18)'"
                   onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                    Sign In
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

</main>

<!-- ════════════════ FOOTER ════════════════ -->
<footer class="site-footer">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 relative z-10">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="footer-logo">Transport<span>Ops</span></div>
            <div class="flex flex-wrap gap-5 justify-center">
                <a href="index.php"       class="footer-link" style="color:var(--gold);">Home</a>
                <a href="about.php"       class="footer-link">About</a>
                <a href="reports_map.php" class="footer-link">Reports Map</a>
                <a href="routes.php"      class="footer-link">Routes</a>
                <?php if ($is_logged_in): ?>
                <a href="report.php"      class="footer-link">Submit Report</a>
                <?php else: ?>
                <a href="register.php"    class="footer-link">Register</a>
                <a href="login.php"       class="footer-link">Login</a>
                <?php endif; ?>
            </div>
            <p style="color:#475569;font-size:0.78rem;white-space:nowrap;">
                &copy; <?= date("Y") ?> Transport Ops
            </p>
        </div>
    </div>
</footer>

<!-- ════════════════ SCRIPTS ════════════════ -->
<script>
(function () {
    /* Nav scroll effect */
    var nav = document.getElementById('floatingNav');
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

    /* Mobile menu toggle */
    var brand  = document.getElementById('brandLink');
    var mobile = document.getElementById('mobileMenu');
    if (brand && mobile) {
        brand.addEventListener('click', function (e) {
            if (window.innerWidth < 768) { e.preventDefault(); mobile.classList.toggle('hidden'); }
        });
        document.addEventListener('click', function (ev) {
            if (mobile && !mobile.contains(ev.target) && ev.target !== brand)
                mobile.classList.add('hidden');
        });
    }

    /* Profile dropdown */
    var btn  = document.getElementById('profileMenuButton');
    var menu = document.getElementById('profileMenu');
    if (btn && menu) {
        btn.addEventListener('click', function (e) { e.stopPropagation(); menu.classList.toggle('hidden'); });
        document.addEventListener('click', function () { if (menu) menu.classList.add('hidden'); });
    }

    /* Scroll reveal */
    var revealEls = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) { e.target.classList.add('visible'); io.unobserve(e.target); }
            });
        }, { threshold: 0.08 });
        revealEls.forEach(function (el) { io.observe(el); });
    } else {
        revealEls.forEach(function (el) { el.classList.add('visible'); });
    }
})();
</script>
</body>
</html>
