<?php
/**
 * About Page – Redesigned
 * Public Transportation Operations System
 */

require_once "auth_helper.php";
secureSessionStart();
require_once "db.php";
$is_logged_in = isset($_SESSION["user_id"]);
$user_profile_data = ["profile_image" => null];

if ($is_logged_in && isset($_SESSION["user_id"])) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
        $stmt->execute([$_SESSION["user_id"]]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row["profile_image"]) {
            $user_profile_data["profile_image"] = $row["profile_image"];
            $_SESSION["profile_image"] = $row["profile_image"];
        }
    } catch (PDOException $e) {
        error_log("About: profile fetch error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About — Transport Ops</title>
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
            --bg-light:  #edf0f7;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #e8edf8 0%, #f0f4ff 40%, #edf3f0 70%, #f5f0ea 100%);
            min-height: 100vh;
            color: #1e293b;
            overflow-x: hidden;
        }

        /* ── Glassmorphism Nav ────────────────────────────── */
        .glass-nav {
            background: rgba(34, 51, 92, 0.78);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 8px 32px rgba(15,28,54,0.35), 0 2px 8px rgba(0,0,0,0.15);
            transition: background 0.3s, box-shadow 0.3s, top 0.3s;
        }
        .glass-nav.scrolled {
            background: rgba(34, 51, 92, 0.95);
            box-shadow: 0 12px 40px rgba(15,28,54,0.5), 0 4px 12px rgba(0,0,0,0.25);
        }
        .nav-link {
            display: inline-block; padding: 0.45rem 0.9rem; border-radius: 0.5rem;
            font-size: 0.875rem; font-weight: 500; color: #cbd5e1;
            border: 1px solid transparent; text-decoration: none;
            transition: all 0.2s;
        }
        .nav-link:hover { background: rgba(255,255,255,0.14); border-color: rgba(255,255,255,0.22); color: #fff; }
        .nav-link.active { background: rgba(255,255,255,0.22); border-color: rgba(255,255,255,0.3); color: #fff; }
        .nav-link-mobile {
            display: block; padding: 0.5rem 0.9rem; border-radius: 0.5rem;
            font-size: 0.875rem; font-weight: 500; color: #cbd5e1;
            border: 1px solid transparent; text-decoration: none;
            transition: all 0.2s;
        }
        .nav-link-mobile:hover { background: rgba(255,255,255,0.14); border-color: rgba(255,255,255,0.22); color: #fff; }
        .nav-link-mobile.active { background: rgba(255,255,255,0.22); border-color: rgba(255,255,255,0.3); color: #fff; }
        .glass-dropdown {
            background: rgba(25,40,74,0.96); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(255,255,255,0.12); box-shadow: 0 8px 32px rgba(15,28,54,0.45);
        }

        /* ── Hero ─────────────────────────────────────────── */
        .hero-section {
            position: relative;
            background: linear-gradient(135deg, var(--navy-deep) 0%, var(--navy-mid) 50%, #1a2f5a 100%);
            overflow: hidden;
            min-height: 520px;
            display: flex; align-items: center;
        }
        .hero-section::before {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(ellipse at 20% 50%, rgba(91,123,153,0.25) 0%, transparent 60%),
                        radial-gradient(ellipse at 80% 20%, rgba(251,192,97,0.12) 0%, transparent 50%);
        }
        .hero-orb {
            position: absolute; border-radius: 50%; pointer-events: none;
        }
        .hero-orb-1 {
            width: 520px; height: 520px;
            top: -180px; right: -100px;
            background: radial-gradient(circle, rgba(91,123,153,0.18) 0%, transparent 70%);
            animation: orbFloat 18s ease-in-out infinite alternate;
        }
        .hero-orb-2 {
            width: 360px; height: 360px;
            bottom: -120px; left: 10%;
            background: radial-gradient(circle, rgba(251,192,97,0.1) 0%, transparent 70%);
            animation: orbFloat 24s ease-in-out infinite alternate-reverse;
        }
        .hero-orb-3 {
            width: 250px; height: 250px;
            top: 30%; right: 25%;
            background: radial-gradient(circle, rgba(255,255,255,0.04) 0%, transparent 70%);
            animation: orbFloat 14s ease-in-out infinite alternate;
        }
        @keyframes orbFloat {
            from { transform: translate(0, 0) scale(1); }
            to   { transform: translate(30px, 20px) scale(1.07); }
        }
        .hero-grid-pattern {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 50px 50px;
        }
        .gold-pill {
            display: inline-flex; align-items: center; gap: 0.4rem;
            background: rgba(251,192,97,0.15); border: 1px solid rgba(251,192,97,0.35);
            color: var(--gold); border-radius: 999px;
            padding: 0.3rem 0.9rem; font-size: 0.78rem; font-weight: 600;
            letter-spacing: 0.05em; text-transform: uppercase;
            margin-bottom: 1.2rem;
        }
        .hero-title {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(2.2rem, 5vw, 3.6rem);
            font-weight: 800; line-height: 1.1;
            color: #fff; letter-spacing: -0.02em;
        }
        .hero-title .gold-text { color: var(--gold); }
        .hero-subtitle {
            color: #94a3b8; font-size: 1.05rem; line-height: 1.7;
            max-width: 560px; margin-top: 1rem;
        }
        .hero-cta-btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            background: var(--gold); color: var(--navy-deep);
            font-weight: 700; font-size: 0.9rem;
            padding: 0.75rem 1.6rem; border-radius: 0.6rem;
            text-decoration: none; transition: all 0.2s;
            box-shadow: 0 4px 20px rgba(251,192,97,0.35);
        }
        .hero-cta-btn:hover { background: var(--gold-dark); transform: translateY(-2px); box-shadow: 0 8px 28px rgba(251,192,97,0.45); }
        .hero-secondary-btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            background: rgba(255,255,255,0.1); color: #e2e8f0;
            font-weight: 600; font-size: 0.9rem;
            padding: 0.75rem 1.6rem; border-radius: 0.6rem;
            border: 1px solid rgba(255,255,255,0.2);
            text-decoration: none; transition: all 0.2s;
        }
        .hero-secondary-btn:hover { background: rgba(255,255,255,0.18); color: #fff; }

        /* ── Stats Strip ──────────────────────────────────── */
        .stats-strip {
            background: rgba(255,255,255,0.72);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.9);
            box-shadow: 0 4px 30px rgba(34,51,92,0.08), 0 1px 4px rgba(34,51,92,0.04);
        }
        .stat-item { text-align: center; padding: 1.4rem 1rem; }
        .stat-value {
            font-family: 'Poppins', sans-serif;
            font-size: 2rem; font-weight: 800; color: var(--navy);
            line-height: 1;
        }
        .stat-label { font-size: 0.78rem; color: #64748b; font-weight: 500; margin-top: 0.3rem; }
        .stat-divider { width: 1px; background: rgba(34,51,92,0.1); align-self: stretch; margin: 1rem 0; }

        /* ── Section Labels ───────────────────────────────── */
        .section-eyebrow {
            font-size: 0.72rem; font-weight: 700; letter-spacing: 0.12em;
            text-transform: uppercase; color: var(--slate);
            display: flex; align-items: center; gap: 0.5rem;
        }
        .section-eyebrow::before {
            content: ''; display: block; width: 1.5rem; height: 2px;
            background: var(--gold); border-radius: 999px;
        }
        .section-heading {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(1.6rem, 3vw, 2.2rem);
            font-weight: 800; color: var(--navy);
            line-height: 1.2; letter-spacing: -0.02em;
        }
        .section-body { color: #475569; line-height: 1.8; font-size: 0.975rem; }

        /* ── Cards ────────────────────────────────────────── */
        .glass-card {
            background: rgba(255,255,255,0.78);
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
            box-shadow: 0 2px 16px rgba(34,51,92,0.06);
            transition: transform 0.25s, box-shadow 0.25s;
            position: relative; overflow: hidden;
        }
        .feature-card::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(34,51,92,0.02) 0%, transparent 60%);
            pointer-events: none;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 36px rgba(34,51,92,0.13);
        }
        .feature-icon-wrap {
            width: 3rem; height: 3rem; border-radius: 0.875rem;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 1rem; flex-shrink: 0;
        }
        .fi-navy  { background: rgba(34,51,92,0.1); }
        .fi-gold  { background: rgba(251,192,97,0.18); }
        .fi-slate { background: rgba(91,123,153,0.12); }
        .fi-teal  { background: rgba(20,184,166,0.1); }
        .feature-card h3 {
            font-size: 1rem; font-weight: 700; color: var(--navy);
            margin-bottom: 0.5rem;
        }
        .feature-card p { font-size: 0.875rem; color: #64748b; line-height: 1.7; }
        .feature-tag {
            display: inline-block; font-size: 0.68rem; font-weight: 600;
            padding: 0.18rem 0.6rem; border-radius: 999px;
            margin-top: 0.9rem; letter-spacing: 0.04em; text-transform: uppercase;
        }
        .tag-navy  { background: rgba(34,51,92,0.08); color: var(--navy); }
        .tag-gold  { background: rgba(251,192,97,0.2); color: #8a6020; }
        .tag-slate { background: rgba(91,123,153,0.1); color: var(--slate); }
        .tag-teal  { background: rgba(20,184,166,0.1); color: #0f766e; }

        /* ── Overview Block ───────────────────────────────── */
        .overview-accent {
            width: 4px; border-radius: 999px;
            background: linear-gradient(180deg, var(--gold) 0%, var(--slate) 100%);
            flex-shrink: 0; align-self: stretch;
        }

        /* ── Mission Section ──────────────────────────────── */
        .mission-section {
            background: linear-gradient(135deg, var(--navy-deep) 0%, var(--navy-mid) 60%, #1a3060 100%);
            border-radius: 1.5rem;
            position: relative; overflow: hidden;
        }
        .mission-section::before {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(ellipse at 90% 10%, rgba(251,192,97,0.12) 0%, transparent 55%),
                        radial-gradient(ellipse at 10% 90%, rgba(91,123,153,0.2) 0%, transparent 55%);
        }
        .mission-section::after {
            content: ''; position: absolute;
            width: 400px; height: 400px; border-radius: 50%;
            top: -150px; right: -100px;
            background: radial-gradient(circle, rgba(255,255,255,0.04) 0%, transparent 70%);
        }
        .mission-quote {
            border-left: 3px solid var(--gold);
            padding-left: 1.25rem;
            color: #94a3b8; font-size: 0.95rem; line-height: 1.8;
            font-style: italic;
        }

        /* ── Tech Stack ───────────────────────────────────── */
        .tech-pill {
            display: inline-flex; align-items: center; gap: 0.55rem;
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(34,51,92,0.1);
            border-radius: 0.75rem;
            padding: 0.7rem 1.1rem;
            font-size: 0.875rem; font-weight: 600; color: var(--navy);
            box-shadow: 0 2px 8px rgba(34,51,92,0.06);
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
        }
        .tech-pill:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(34,51,92,0.12);
            border-color: var(--gold);
        }
        .tech-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--gold); flex-shrink: 0;
        }

        /* ── CTA Banner ───────────────────────────────────── */
        .cta-banner {
            background: linear-gradient(135deg, rgba(34,51,92,0.07) 0%, rgba(251,192,97,0.08) 100%);
            border: 1px solid rgba(34,51,92,0.1);
            border-radius: 1.25rem;
            text-align: center; padding: 3rem 2rem;
        }

        /* ── Footer ───────────────────────────────────────── */
        .site-footer {
            background: linear-gradient(135deg, var(--navy-deep) 0%, var(--navy-mid) 100%);
            position: relative; overflow: hidden;
        }
        .site-footer::before {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(ellipse at 80% 50%, rgba(91,123,153,0.12) 0%, transparent 60%);
        }
        .footer-logo {
            font-family: 'Poppins', sans-serif;
            font-size: 1.3rem; font-weight: 800; color: #fff;
        }
        .footer-logo span { color: var(--gold); }
        .footer-link { color: #94a3b8; text-decoration: none; font-size: 0.875rem; transition: color 0.2s; }
        .footer-link:hover { color: var(--gold); }
        .footer-divider { border-color: rgba(255,255,255,0.08); }

        /* ── Scroll Reveal ────────────────────────────────── */
        .reveal {
            opacity: 0; transform: translateY(28px);
            transition: opacity 0.65s cubic-bezier(.4,0,.2,1), transform 0.65s cubic-bezier(.4,0,.2,1);
        }
        .reveal.visible { opacity: 1; transform: none; }
        .reveal-delay-1 { transition-delay: 0.08s; }
        .reveal-delay-2 { transition-delay: 0.16s; }
        .reveal-delay-3 { transition-delay: 0.24s; }
        .reveal-delay-4 { transition-delay: 0.32s; }

        /* ── Responsive helpers ───────────────────────────── */
        @media (max-width: 640px) {
            .stat-value { font-size: 1.6rem; }
            .hero-section { min-height: 460px; }
        }
    </style>
</head>
<body>

<!-- ══════════════════ FLOATING NAV ══════════════════ -->
<nav id="floatingNav" class="fixed top-4 left-1/2 -translate-x-1/2 z-40 glass-nav text-white rounded-2xl w-[calc(100%-2rem)] max-w-7xl">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-14">

            <!-- Brand + Desktop Links -->
            <div class="flex items-center gap-8">
                <a href="index.php" id="brandLink"
                   style="font-family:'Poppins',sans-serif; font-size:1.2rem; font-weight:800; color:#fff; text-decoration:none; white-space:nowrap; letter-spacing:-0.01em;">
                    Transport<span style="color:var(--gold);">Ops</span>
                </a>
                <div class="hidden md:flex gap-1">
                    <a href="<?= $is_logged_in
                        ? "user_dashboard.php"
                        : "index.php" ?>" class="nav-link">Home</a>
                    <?php if ($is_logged_in): ?>
                    <a href="report.php" class="nav-link">Submit Report</a>
                    <?php endif; ?>
                    <a href="reports_map.php" class="nav-link">Reports Map</a>
                    <a href="routes.php" class="nav-link">Routes</a>
                    <a href="about.php" class="nav-link active">About</a>
                </div>
                <!-- Mobile dropdown -->
                <div id="mobileMenu" class="md:hidden hidden absolute top-full left-0 right-0 mt-2 flex flex-col gap-1 px-4 py-3 z-20 rounded-2xl"
                     style="background:rgba(25,40,74,0.97);backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,0.12);box-shadow:0 8px 32px rgba(15,28,54,0.4);">
                    <a href="<?= $is_logged_in
                        ? "user_dashboard.php"
                        : "index.php" ?>" class="nav-link-mobile">Home</a>
                    <?php if ($is_logged_in): ?>
                    <a href="report.php" class="nav-link-mobile">Submit Report</a>
                    <?php endif; ?>
                    <a href="reports_map.php" class="nav-link-mobile">Reports Map</a>
                    <a href="routes.php" class="nav-link-mobile">Routes</a>
                    <a href="about.php" class="nav-link-mobile active">About</a>
                </div>
            </div>

            <!-- Right: Auth -->
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
                                     alt="Profile" class="h-8 w-8 rounded-full object-cover border-2 border-white/60">
                            <?php else: ?>
                                <div class="h-8 w-8 rounded-full flex items-center justify-center text-white text-sm font-bold"
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
                        <a href="profile.php" class="block px-4 py-2 text-sm text-white hover:bg-white/10 mx-1 rounded-lg">View &amp; Edit Profile</a>
                        <a href="public_profile.php?id=<?= $_SESSION[
                            "user_id"
                        ] ?>" class="block px-4 py-2 text-sm text-white hover:bg-white/10 mx-1 rounded-lg">Public Profile</a>
                        <div class="my-1 border-t border-white/15"></div>
                        <a href="logout.php" class="block px-4 py-2 text-sm text-red-300 hover:bg-white/10 mx-1 rounded-lg">Logout</a>
                    </div>
                </div>
                <?php else: ?>
                <a href="register.php" class="nav-link" style="border-color:rgba(255,255,255,0.25);">Register</a>
                <a href="login.php" class="text-sm font-semibold px-4 py-2 rounded-lg text-white"
                   style="background:var(--gold);color:var(--navy-deep);"
                   onmouseover="this.style.background='#e8a83e'" onmouseout="this.style.background='var(--gold)'">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- ══════════════════ HERO ══════════════════ -->
<section class="hero-section pt-24">
    <div class="hero-grid-pattern"></div>
    <div class="hero-orb hero-orb-1"></div>
    <div class="hero-orb hero-orb-2"></div>
    <div class="hero-orb hero-orb-3"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full relative z-10 py-20">
        <div class="max-w-2xl">
            <div class="gold-pill">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                </svg>
                About Transport Ops
            </div>
            <h1 class="hero-title">
                Smarter Transit,<br>
                <span class="gold-text">Better Commutes.</span>
            </h1>
            <p class="hero-subtitle">
                A crowdsourced public transportation monitoring platform bridging the gap between fleet-level operations and the real passenger experience across Metro Manila.
            </p>
            <div class="flex flex-wrap gap-3 mt-8">
                <a href="<?= $is_logged_in
                    ? "user_dashboard.php"
                    : "register.php" ?>" class="hero-cta-btn">
                    <?= $is_logged_in
                        ? "Go to Dashboard"
                        : "Get Started Free" ?>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </a>
                <a href="reports_map.php" class="hero-secondary-btn">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                    </svg>
                    View Live Map
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════ STATS STRIP ══════════════════ -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 relative z-10 mb-16">
    <div class="stats-strip rounded-2xl overflow-hidden reveal">
        <div class="grid grid-cols-2 md:grid-cols-4 divide-x divide-y md:divide-y-0" style="divide-color:rgba(34,51,92,0.08);">
            <div class="stat-item">
                <div class="stat-value">Real-Time</div>
                <div class="stat-label">Fleet Tracking</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" style="color:var(--gold);">360°</div>
                <div class="stat-label">Route Coverage</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">Live</div>
                <div class="stat-label">Crowding Reports</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" style="color:var(--slate);">Trusted</div>
                <div class="stat-label">Peer-Verified Data</div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════ MAIN CONTENT ══════════════════ -->
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-24">

    <!-- ── System Overview ──────────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center mb-20">
        <div class="reveal">
            <div class="section-eyebrow mb-3">System Overview</div>
            <h2 class="section-heading mb-5">Built for the real<br>commute experience.</h2>
            <div class="flex gap-4">
                <div class="overview-accent"></div>
                <div class="space-y-4">
                    <p class="section-body">
                        Transport Ops lets commuters and transit supervisors work together to capture on-the-ground conditions in real time. Registered users report crowding levels, wait times, and delay reasons for specific routes; each submission is geofenced and assigned a trust score through peer verification.
                    </p>
                    <p class="section-body">
                        A centralized dashboard aggregates these reports alongside GPS vehicle tracking. With interactive heatmaps and delay trend analysis, operations managers gain actionable insight into congestion hotspots and service disruptions — enabling faster decisions, better schedules, and a more reliable network.
                    </p>
                </div>
            </div>
        </div>
        <div class="reveal reveal-delay-2">
            <div class="glass-card p-6 space-y-4">
                <!-- Mini highlight list -->
                <?php
                $highlights = [
                    [
                        "icon" => "M5 13l4 4L19 7",
                        "label" =>
                            "GPS-validated crowding reports from commuters",
                    ],
                    [
                        "icon" => "M5 13l4 4L19 7",
                        "label" => "Live fleet positions updated in real time",
                    ],
                    [
                        "icon" => "M5 13l4 4L19 7",
                        "label" => "Heatmap analytics for peak demand periods",
                    ],
                    [
                        "icon" => "M5 13l4 4L19 7",
                        "label" =>
                            "Peer-verification trust scoring for data accuracy",
                    ],
                    [
                        "icon" => "M5 13l4 4L19 7",
                        "label" => "Admin alerts & schedule adjustment tools",
                    ],
                ];
                foreach ($highlights as $h): ?>
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center mt-0.5"
                         style="background:rgba(251,192,97,0.18);">
                        <svg width="12" height="12" fill="none" stroke="#e8a83e" stroke-width="2.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="<?= $h[
                                "icon"
                            ] ?>"/>
                        </svg>
                    </div>
                    <span style="font-size:0.9rem;color:#334155;line-height:1.6;"><?= $h[
                        "label"
                    ] ?></span>
                </div>
                <?php endforeach;
                ?>
            </div>
        </div>
    </div>

    <!-- ── Key Features ─────────────────────────────── -->
    <div class="mb-20">
        <div class="text-center mb-10 reveal">
            <div class="section-eyebrow justify-center mb-3">Key Features</div>
            <h2 class="section-heading">Everything you need,<br>all in one platform.</h2>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">

            <!-- Feature 1 -->
            <div class="feature-card reveal reveal-delay-1">
                <div class="feature-icon-wrap fi-navy">
                    <svg width="22" height="22" fill="none" stroke="#22335C" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                    </svg>
                </div>
                <h3>Real-Time Fleet Tracking</h3>
                <p>Operations managers view live GPS positions of all active units. Automated tracking replaces error-prone manual dispatch logs.</p>
                <span class="feature-tag tag-navy">Live GPS</span>
            </div>

            <!-- Feature 2 -->
            <div class="feature-card reveal reveal-delay-2">
                <div class="feature-icon-wrap fi-gold">
                    <svg width="22" height="22" fill="none" stroke="#c87d20" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                    </svg>
                </div>
                <h3>Crowdsourced Reports</h3>
                <p>Commuters submit live crowd-level and delay reports at stops. Geofencing and trust scoring ensure only credible data is used.</p>
                <span class="feature-tag tag-gold">Crowd-Powered</span>
            </div>

            <!-- Feature 3 -->
            <div class="feature-card reveal reveal-delay-3">
                <div class="feature-icon-wrap fi-slate">
                    <svg width="22" height="22" fill="none" stroke="#5B7B99" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <h3>Analytics Dashboard</h3>
                <p>Heatmaps and peak-hour charts highlight high-demand segments. Historical trends help forecast recurring bottlenecks.</p>
                <span class="feature-tag tag-slate">Data Insights</span>
            </div>

            <!-- Feature 4 -->
            <div class="feature-card reveal reveal-delay-4">
                <div class="feature-icon-wrap fi-teal">
                    <svg width="22" height="22" fill="none" stroke="#0f766e" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3>Delay Management</h3>
                <p>Commuters tag delay causes — traffic, mechanical issues, or overcrowding — triggering smart alerts and redeployment recommendations.</p>
                <span class="feature-tag tag-teal">Smart Alerts</span>
            </div>
        </div>
    </div>

    <!-- ── Mission ──────────────────────────────────── -->
    <div class="mission-section p-8 sm:p-12 mb-20 reveal">
        <div class="relative z-10 grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">
            <div>
                <div class="section-eyebrow mb-4" style="color:rgba(251,192,97,0.8);">Our Mission</div>
                <h2 class="section-heading mb-6" style="color:#fff;">Giving passengers<br>a voice in transit.</h2>
                <p class="mb-6" style="color:#94a3b8;line-height:1.8;font-size:0.975rem;">
                    Our mission is to digitize and streamline daily public transit operations by integrating real-time crowding and delay data — improving service reliability and enabling data-driven decisions across Metro Manila's routes.
                </p>
                <a href="<?= $is_logged_in
                    ? "report.php"
                    : "register.php" ?>" class="hero-cta-btn" style="display:inline-flex;">
                    <?= $is_logged_in
                        ? "Submit a Report"
                        : "Join the Platform" ?>
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </a>
            </div>
            <div class="space-y-5">
                <blockquote class="mission-quote">
                    "By combining fleet-level GPS monitoring with ground-level passenger reporting, we aim to reduce commuter uncertainty, minimize missed dispatch windows, and empower both operators and commuters to make better-informed decisions every day."
                </blockquote>
                <div class="grid grid-cols-2 gap-4 mt-6">
                    <div class="rounded-xl p-4" style="background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.1);">
                        <div style="font-family:'Poppins',sans-serif;font-size:1.6rem;font-weight:800;color:var(--gold);">Live</div>
                        <div style="font-size:0.8rem;color:#94a3b8;margin-top:0.2rem;">Crowd Updates</div>
                    </div>
                    <div class="rounded-xl p-4" style="background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.1);">
                        <div style="font-family:'Poppins',sans-serif;font-size:1.6rem;font-weight:800;color:var(--gold);">Smart</div>
                        <div style="font-size:0.8rem;color:#94a3b8;margin-top:0.2rem;">Trust Scoring</div>
                    </div>
                    <div class="rounded-xl p-4" style="background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.1);">
                        <div style="font-family:'Poppins',sans-serif;font-size:1.6rem;font-weight:800;color:var(--gold);">Geo</div>
                        <div style="font-size:0.8rem;color:#94a3b8;margin-top:0.2rem;">Validated Reports</div>
                    </div>
                    <div class="rounded-xl p-4" style="background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.1);">
                        <div style="font-family:'Poppins',sans-serif;font-size:1.6rem;font-weight:800;color:var(--gold);">Fast</div>
                        <div style="font-size:0.8rem;color:#94a3b8;margin-top:0.2rem;">Admin Alerts</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Technology Stack ──────────────────────────── -->
    <div class="mb-20">
        <div class="text-center mb-10 reveal">
            <div class="section-eyebrow justify-center mb-3">Technology Stack</div>
            <h2 class="section-heading">Built on solid<br>foundations.</h2>
            <p class="section-body max-w-xl mx-auto mt-3">
                Reliable, widely-supported web technologies ensuring performance, scalability, and ease of maintenance across all devices.
            </p>
        </div>
        <div class="flex flex-wrap gap-3 justify-center reveal reveal-delay-1">
            <?php
            $techs = [
                ["label" => "PHP", "desc" => "Server-side logic & auth"],
                [
                    "label" => "MySQL / MariaDB",
                    "desc" => "Relational data storage",
                ],
                ["label" => "GPS Geofencing", "desc" => "Location validation"],
                [
                    "label" => "Trust Scoring Engine",
                    "desc" => "Peer-verification algorithm",
                ],
                [
                    "label" => "Leaflet.js",
                    "desc" => "Interactive map rendering",
                ],
                ["label" => "Tailwind CSS", "desc" => "Responsive UI design"],
                ["label" => "Chart.js", "desc" => "Analytics visualization"],
                [
                    "label" => "XAMPP / Apache",
                    "desc" => "Local server environment",
                ],
            ];
            foreach ($techs as $t): ?>
            <div class="tech-pill">
                <span class="tech-dot"></span>
                <div>
                    <div style="font-size:0.875rem;font-weight:700;color:var(--navy);"><?= htmlspecialchars(
                        $t["label"],
                    ) ?></div>
                    <div style="font-size:0.72rem;color:#94a3b8;font-weight:400;"><?= htmlspecialchars(
                        $t["desc"],
                    ) ?></div>
                </div>
            </div>
            <?php endforeach;
            ?>
        </div>
    </div>

    <!-- ── CTA Banner ───────────────────────────────── -->
    <div class="cta-banner reveal">
        <div class="section-eyebrow justify-center mb-4">Ready to explore?</div>
        <h2 class="section-heading mb-3">Start using Transport Ops today.</h2>
        <p class="section-body max-w-lg mx-auto mb-8">
            Join the network of commuters and operators making Metro Manila's public transit smarter, one report at a time.
        </p>
        <div class="flex flex-wrap gap-3 justify-center">
            <?php if ($is_logged_in): ?>
            <a href="user_dashboard.php" class="hero-cta-btn">Go to Dashboard
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                </svg>
            </a>
            <a href="reports_map.php" class="hero-secondary-btn" style="background:rgba(34,51,92,0.08);color:var(--navy);border-color:rgba(34,51,92,0.2);">View Reports Map</a>
            <?php else: ?>
            <a href="register.php" class="hero-cta-btn">Create Free Account
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                </svg>
            </a>
            <a href="login.php" class="hero-secondary-btn" style="background:rgba(34,51,92,0.08);color:var(--navy);border-color:rgba(34,51,92,0.2);">Sign In</a>
            <?php endif; ?>
        </div>
    </div>

</main>

<!-- ══════════════════ FOOTER ══════════════════ -->
<footer class="site-footer">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14 relative z-10">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-10 mb-10">

            <!-- Brand -->
            <div>
                <div class="footer-logo mb-3">Transport<span>Ops</span></div>
                <p style="color:#64748b;font-size:0.875rem;line-height:1.7;max-width:260px;">
                    A crowdsourced public transportation monitoring platform for Metro Manila commuters and operators.
                </p>
            </div>

            <!-- Navigation -->
            <div>
                <div style="font-size:0.75rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:#475569;margin-bottom:1rem;">Navigation</div>
                <div class="flex flex-col gap-2">
                    <a href="<?= $is_logged_in
                        ? "user_dashboard.php"
                        : "index.php" ?>" class="footer-link">Home</a>
                    <a href="about.php" class="footer-link" style="color:var(--gold);">About</a>
                    <a href="reports_map.php" class="footer-link">Reports Map</a>
                    <a href="routes.php" class="footer-link">Routes</a>
                    <?php if ($is_logged_in): ?>
                    <a href="report.php" class="footer-link">Submit Report</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- System Info -->
            <div>
                <div style="font-size:0.75rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:#475569;margin-bottom:1rem;">System</div>
                <div class="flex flex-col gap-2">
                    <span class="footer-link" style="cursor:default;">Metro Manila Coverage</span>
                    <span class="footer-link" style="cursor:default;">Real-Time GPS Tracking</span>
                    <span class="footer-link" style="cursor:default;">Peer-Verified Reports</span>
                    <span class="footer-link" style="cursor:default;">Admin Analytics</span>
                </div>
            </div>
        </div>

        <hr class="footer-divider mb-6">

        <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <p style="color:#475569;font-size:0.8rem;">
                &copy; <?= date(
                    "Y",
                ) ?> Public Transportation Operations System. All rights reserved.
            </p>
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full" style="background:var(--gold);"></span>
                <span style="color:#475569;font-size:0.8rem;">Transport Ops &mdash; Metro Manila</span>
            </div>
        </div>
    </div>
</footer>

<script>
(function () {
    // ── Nav scroll effect ──────────────────────────
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

    // ── Profile dropdown ───────────────────────────
    const btn  = document.getElementById('profileMenuButton');
    const menu = document.getElementById('profileMenu');
    if (btn && menu) {
        btn.addEventListener('click', function (e) { e.stopPropagation(); menu.classList.toggle('hidden'); });
        document.addEventListener('click', function () { if (menu) menu.classList.add('hidden'); });
    }

    // ── Mobile menu toggle (brand tap on mobile) ───
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

    // ── Scroll reveal ──────────────────────────────
    const revealEls = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });
        revealEls.forEach(function (el) { io.observe(el); });
    } else {
        revealEls.forEach(function (el) { el.classList.add('visible'); });
    }
})();
</script>
</body>
</html>
