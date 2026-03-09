<?php
/**
 * Public User Profile Page – redesigned to match the Transport Ops glass UI
 * Shows user trust score, badge, statistics, and verified reporting history.
 */

require_once "auth_helper.php";
secureSessionStart();
require_once "db.php";
require_once "trust_helper.php";

$userId    = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$fromAdmin = isset($_GET["admin"]) && $_GET["admin"] == "1";

if ($userId <= 0) { header("Location: index.php"); exit(); }

$profile = getUserPublicProfile($userId);
if (!$profile) { header("Location: index.php"); exit(); }

$user          = $profile["user"];
$stats         = $profile["stats"];
$recentReports = $profile["recent_reports"];
$badge         = $profile["badge"];

$viewerLoggedIn = isset($_SESSION["user_id"]);
$isOwnProfile   = $viewerLoggedIn && (int)$_SESSION["user_id"] === $userId;
$userName       = htmlspecialchars($user["name"] ?? "User");
$userInitial    = strtoupper(substr($user["name"] ?? "U", 0, 1));
$trustScore     = number_format((float)($user["trust_score"] ?? 0), 1);
$memberSince    = date("M j, Y", strtotime($user["created_at"] ?? "now"));

$viewerImage = $_SESSION["profile_image"] ?? null;
$viewerName  = htmlspecialchars($_SESSION["user_name"] ?? "");
$viewerRole  = htmlspecialchars($_SESSION["role"] ?? "");
$viewerId    = (int)($_SESSION["user_id"] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $userName ?> — Public Profile · Transport Ops</title>
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

/* ── Floating Nav ──────────────────────────── */
.glass-nav {
    background: rgba(34,51,92,0.78);
    backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
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

/* ── Hero ──────────────────────────────────── */
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
.hero-orb { position: absolute; border-radius: 50%; pointer-events: none; }
.hero-orb-1 {
    width: 520px; height: 520px; top: -170px; right: -90px;
    background: radial-gradient(circle, rgba(91,123,153,0.18) 0%, transparent 70%);
    animation: orbPulse 18s ease-in-out infinite alternate;
}
.hero-orb-2 {
    width: 300px; height: 300px; bottom: -100px; left: 5%;
    background: radial-gradient(circle, rgba(251,192,97,0.08) 0%, transparent 70%);
    animation: orbPulse 24s ease-in-out infinite alternate-reverse;
}
@keyframes orbPulse {
    from { transform: translate(0,0) scale(1); }
    to   { transform: translate(28px,18px) scale(1.08); }
}
.hero-avatar {
    width: 92px; height: 92px; border-radius: 50%;
    border: 3px solid rgba(251,192,97,0.55);
    object-fit: cover; flex-shrink: 0;
}
.hero-avatar-initials {
    width: 92px; height: 92px; border-radius: 50%;
    border: 3px solid rgba(251,192,97,0.55);
    background: var(--slate);
    display: flex; align-items: center; justify-content: center;
    font-family: 'Poppins', sans-serif;
    font-size: 2.1rem; font-weight: 800; color: #fff; flex-shrink: 0;
}
.hero-name {
    font-family: 'Poppins', sans-serif;
    font-size: clamp(1.9rem, 4vw, 2.7rem);
    font-weight: 800; color: #fff; line-height: 1.12; letter-spacing: -0.025em;
}
.hero-meta { color: #94a3b8; font-size: 0.85rem; margin-top: 0.35rem; }
.hero-trust {
    font-family: 'Poppins', sans-serif;
    font-size: clamp(2.2rem, 5vw, 3.2rem);
    font-weight: 800; color: var(--gold); line-height: 1;
}
.hero-trust-label {
    font-size: 0.68rem; color: rgba(255,255,255,0.45);
    font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase;
}
.badge-wrap {
    display: inline-flex; align-items: center;
    padding: 0.32rem 0.9rem; border-radius: 999px;
    font-size: 0.74rem; font-weight: 700;
    border-width: 1px; border-style: solid;
}
.edit-link {
    display: inline-flex; align-items: center; gap: 0.4rem;
    font-size: 0.82rem; font-weight: 600; color: var(--gold);
    text-decoration: none; opacity: 0.85; transition: opacity 0.2s;
}
.edit-link:hover { opacity: 1; text-decoration: underline; }

/* ── Stats strip ───────────────────────────── */
.stats-strip {
    background: rgba(255,255,255,0.82);
    backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.95);
    box-shadow: 0 4px 28px rgba(34,51,92,0.08);
    border-radius: 1.125rem;
}
.stat-cell { padding: 1.35rem 1rem; text-align: center; }
.stat-val {
    font-family: 'Poppins', sans-serif;
    font-size: 2rem; font-weight: 800; line-height: 1;
}
.stat-lbl {
    font-size: 0.7rem; color: #64748b; font-weight: 700;
    margin-top: 0.3rem; text-transform: uppercase; letter-spacing: 0.07em;
}

/* ── Section headings ──────────────────────── */
.sec-eyebrow {
    font-size: 0.67rem; font-weight: 700; letter-spacing: 0.12em;
    text-transform: uppercase; color: var(--slate);
    display: flex; align-items: center; gap: 0.5rem;
}
.sec-eyebrow::before {
    content: ''; display: block; width: 1.3rem; height: 2px;
    background: var(--gold); border-radius: 999px;
}
.sec-heading {
    font-family: 'Poppins', sans-serif;
    font-size: 1.3rem; font-weight: 800; color: var(--navy);
    letter-spacing: -0.02em;
}

/* ── Glass card ────────────────────────────── */
.glass-card {
    background: rgba(255,255,255,0.82);
    backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.95);
    box-shadow: 0 4px 24px rgba(34,51,92,0.07), 0 1px 4px rgba(34,51,92,0.04);
    border-radius: 1.125rem;
}

/* ── Feed ──────────────────────────────────── */
.feed-item {
    display: flex; align-items: center; gap: 0.85rem;
    padding: 0.9rem 1.25rem;
    border-bottom: 1px solid rgba(34,51,92,0.06);
    transition: background 0.15s;
}
.feed-item:last-child { border-bottom: none; }
.feed-item:hover { background: rgba(34,51,92,0.025); }
.feed-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
.feed-dot-Heavy    { background: #dc2626; box-shadow: 0 0 0 3px rgba(220,38,38,0.15); }
.feed-dot-Moderate { background: #ca8a04; box-shadow: 0 0 0 3px rgba(202,138,4,0.15); }
.feed-dot-Light    { background: #16a34a; box-shadow: 0 0 0 3px rgba(22,163,74,0.15); }
.feed-dot-Unknown  { background: #94a3b8; box-shadow: 0 0 0 3px rgba(148,163,184,0.15); }
.feed-route { font-size: 0.85rem; font-weight: 700; color: var(--navy); }
.feed-meta  { font-size: 0.75rem; color: #64748b; margin-top: 0.15rem; display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap; }
.feed-time  { font-size: 0.72rem; color: #94a3b8; font-weight: 500; margin-left: auto; white-space: nowrap; flex-shrink: 0; padding-left: 0.5rem; }

/* Crowd badges */
.badge {
    display: inline-flex; align-items: center; gap: 0.3rem;
    font-size: 0.68rem; font-weight: 700; padding: 0.18rem 0.58rem;
    border-radius: 999px; letter-spacing: 0.03em;
}
.badge::before { content: ''; width: 5px; height: 5px; border-radius: 50%; flex-shrink: 0; }
.badge-light    { background: #dcfce7; color: #166534; }
.badge-light::before { background: #16a34a; }
.badge-moderate { background: #fef9c3; color: #854d0e; }
.badge-moderate::before { background: #ca8a04; }
.badge-heavy    { background: #fee2e2; color: #991b1b; }
.badge-heavy::before { background: #dc2626; }
.badge-unknown  { background: #f1f5f9; color: #475569; }
.badge-unknown::before { background: #94a3b8; }

.verify-chip {
    display: inline-flex; align-items: center; gap: 0.25rem;
    font-size: 0.68rem; font-weight: 600; color: var(--slate);
}

/* ── Back / ghost buttons ──────────────────── */
.cta-btn-ghost {
    display: inline-flex; align-items: center; gap: 0.45rem;
    background: rgba(34,51,92,0.08); color: var(--navy);
    font-weight: 600; font-size: 0.875rem;
    padding: 0.65rem 1.4rem; border-radius: 0.6rem;
    border: 1px solid rgba(34,51,92,0.13);
    text-decoration: none; transition: all 0.2s;
}
.cta-btn-ghost:hover { background: rgba(34,51,92,0.14); transform: translateY(-1px); }

/* ── Empty state ───────────────────────────── */
.empty-state { text-align: center; padding: 3.5rem 1.5rem; }
.empty-icon {
    width: 56px; height: 56px; border-radius: 1rem;
    background: rgba(34,51,92,0.07);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1rem;
}

/* ── Footer ────────────────────────────────── */
.site-footer {
    background: linear-gradient(135deg, var(--navy-deep) 0%, var(--navy-mid) 100%);
    position: relative; overflow: hidden; margin-top: 5rem;
}
.site-footer::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(ellipse at 80% 50%, rgba(91,123,153,0.12) 0%, transparent 60%);
}
.footer-logo { font-family: 'Poppins', sans-serif; font-size: 1.2rem; font-weight: 800; color: #fff; }
.footer-logo span { color: var(--gold); }
.footer-link { color: #94a3b8; text-decoration: none; font-size: 0.85rem; transition: color 0.2s; }
.footer-link:hover { color: var(--gold); }

/* ── Scroll reveal ─────────────────────────── */
.reveal {
    opacity: 0; transform: translateY(22px);
    transition: opacity 0.6s cubic-bezier(.4,0,.2,1), transform 0.6s cubic-bezier(.4,0,.2,1);
}
.reveal.visible { opacity: 1; transform: none; }
.rd1 { transition-delay: 0.07s; }
.rd2 { transition-delay: 0.14s; }
.rd3 { transition-delay: 0.21s; }

@media (max-width: 640px) {
    .hero-name { font-size: 1.75rem; }
    .stat-val  { font-size: 1.5rem; }
}
</style>
</head>
<body>

<?php if (!$fromAdmin): ?>
<!-- ═══════════ FLOATING NAV ═══════════ -->
<nav id="floatingNav" class="fixed top-4 left-1/2 -translate-x-1/2 z-40 glass-nav text-white rounded-2xl w-[calc(100%-2rem)] max-w-7xl">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-14">

            <div class="flex items-center gap-7">
                <a href="<?= $viewerLoggedIn ? 'user_dashboard.php' : 'index.php' ?>" id="brandLink"
                   style="font-family:'Poppins',sans-serif;font-size:1.2rem;font-weight:800;color:#fff;text-decoration:none;white-space:nowrap;letter-spacing:-0.01em;">
                    Transport<span style="color:var(--gold);">Ops</span>
                </a>
                <div class="hidden md:flex gap-1">
                    <?php if ($viewerLoggedIn): ?>
                    <a href="user_dashboard.php" class="nav-link">Home</a>
                    <?php endif; ?>
                    <a href="about.php"       class="nav-link">About</a>
                    <?php if ($viewerLoggedIn): ?>
                    <a href="report.php"      class="nav-link">Submit Report</a>
                    <a href="reports_map.php" class="nav-link">Reports Map</a>
                    <a href="routes.php"      class="nav-link">Routes</a>
                    <?php endif; ?>
                </div>
                <div id="mobileMenu"
                     class="md:hidden hidden absolute top-full left-0 right-0 mt-2 flex flex-col gap-1 px-4 py-3 z-20 rounded-2xl"
                     style="background:rgba(25,40,74,0.97);backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,0.12);box-shadow:0 8px 32px rgba(15,28,54,0.4);">
                    <?php if ($viewerLoggedIn): ?>
                    <a href="user_dashboard.php" class="nav-link-mobile">Home</a>
                    <?php endif; ?>
                    <a href="about.php"       class="nav-link-mobile">About</a>
                    <?php if ($viewerLoggedIn): ?>
                    <a href="report.php"      class="nav-link-mobile">Submit Report</a>
                    <a href="reports_map.php" class="nav-link-mobile">Reports Map</a>
                    <a href="routes.php"      class="nav-link-mobile">Routes</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="relative flex items-center gap-2">
                <?php if ($viewerLoggedIn): ?>
                <button id="profileMenuButton"
                        class="flex items-center gap-2 px-2 py-1.5 rounded-full hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/40 transition">
                    <div class="hidden sm:flex flex-col items-end leading-tight">
                        <span class="text-sm text-white font-medium"><?= $viewerName ?></span>
                        <span class="text-[11px] text-blue-200"><?= $viewerRole ?></span>
                    </div>
                    <div class="flex items-center gap-1">
                        <?php if ($viewerImage): ?>
                        <img src="uploads/<?= htmlspecialchars($viewerImage) ?>"
                             alt="Profile" class="h-8 w-8 rounded-full object-cover border-2 border-white/50">
                        <?php else: ?>
                        <div class="h-8 w-8 rounded-full flex items-center justify-center text-white text-sm font-bold"
                             style="background:var(--slate);">
                            <?= strtoupper(substr($_SESSION["user_name"] ?? "U", 0, 1)) ?>
                        </div>
                        <?php endif; ?>
                        <svg class="w-4 h-4 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </button>
                <div id="profileMenu" class="hidden absolute right-0 top-12 w-52 glass-dropdown rounded-xl shadow-xl py-1 z-50">
                    <a href="profile.php"
                       class="block px-4 py-2 text-sm text-white hover:bg-white/10 mx-1 rounded-lg">View &amp; Edit Profile</a>
                    <a href="public_profile.php?id=<?= $viewerId ?>"
                       class="block px-4 py-2 text-sm text-white hover:bg-white/10 mx-1 rounded-lg <?= $isOwnProfile ? 'font-semibold' : '' ?>"
                       <?= $isOwnProfile ? 'style="background:rgba(255,255,255,0.1);"' : '' ?>>Public Profile</a>
                    <div class="my-1 border-t border-white/10"></div>
                    <a href="logout.php"
                       class="block px-4 py-2 text-sm text-red-300 hover:bg-white/10 mx-1 rounded-lg">Logout</a>
                </div>
                <?php else: ?>
                <a href="register.php"
                   class="text-white text-sm font-medium px-3 py-2 rounded-md transition"
                   style="background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.25);"
                   onmouseover="this.style.background='rgba(255,255,255,0.18)'"
                   onmouseout="this.style.background='rgba(255,255,255,0.1)'">Register</a>
                <a href="login.php"
                   class="text-white text-sm font-semibold px-4 py-2 rounded-md transition"
                   style="background:rgba(251,192,97,0.22);border:1px solid rgba(251,192,97,0.4);"
                   onmouseover="this.style.background='rgba(251,192,97,0.38)'"
                   onmouseout="this.style.background='rgba(251,192,97,0.22)'">Login</a>
                <?php endif; ?>
            </div>

        </div>
    </div>
</nav>
<?php endif; ?>
<!-- ═══════════════ HERO ═══════════════ -->
<section class="hero <?= $fromAdmin ? 'pt-8' : 'pt-20' ?>">
    <div class="hero-grid"></div>
    <div class="hero-orb hero-orb-1"></div>
    <div class="hero-orb hero-orb-2"></div>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-14 relative z-10">
        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-8">

            <!-- Avatar -->
            <?php if (!empty($user["profile_image"])): ?>
            <img src="uploads/<?= htmlspecialchars($user["profile_image"]) ?>"
                 alt="<?= $userName ?>" class="hero-avatar">
            <?php else: ?>
            <div class="hero-avatar-initials"><?= $userInitial ?></div>
            <?php endif; ?>

            <!-- Info -->
            <div class="flex-1 text-center sm:text-left">

                <!-- Trust badge (dynamic Tailwind classes from trust_helper) -->
                <div class="mb-3">
                    <span class="badge-wrap <?= $badge['bg_color'] . ' ' . $badge['text_color'] . ' ' . $badge['border_color'] ?>">
                        <?= htmlspecialchars($badge['label']) ?>
                    </span>
                </div>

                <h1 class="hero-name"><?= $userName ?></h1>
                <p class="hero-meta">Member since <?= $memberSince ?></p>

                <!-- Trust score -->
                <div class="flex flex-wrap items-baseline gap-2 mt-4 justify-center sm:justify-start">
                    <span class="hero-trust"><?= $trustScore ?></span>
                    <span style="color:rgba(255,255,255,0.4);font-size:1.1rem;font-weight:600;">/ 100</span>
                    <span class="hero-trust-label ml-1">Trust Score</span>
                </div>

                <!-- Action links -->
                <?php if ($isOwnProfile): ?>
                <div class="mt-5">
                    <a href="profile.php" class="edit-link">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Edit My Profile
                    </a>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</section>

<!-- ═══════════════ STATS STRIP ═══════════════ -->
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 -mt-6 relative z-10 mb-10">
    <div class="stats-strip reveal rd1">
        <div class="grid grid-cols-3 divide-x divide-[rgba(34,51,92,0.08)]">

            <div class="stat-cell">
                <div class="stat-val" style="color:var(--navy);"><?= (int)$stats["total_reports"] ?></div>
                <div class="stat-lbl">Reports Submitted</div>
            </div>

            <div class="stat-cell">
                <div class="stat-val" style="color:#16a34a;"><?= (int)$stats["verified_reports"] ?></div>
                <div class="stat-lbl">Peer Verified</div>
            </div>

            <div class="stat-cell">
                <div class="stat-val" style="color:#dc2626;"><?= (int)$stats["rejected_reports"] ?></div>
                <div class="stat-lbl">Rejected</div>
            </div>

        </div>
    </div>
</div>

<!-- ═══════════════ MAIN ═══════════════ -->
<main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">

    <!-- Recent Verified Reports card -->
    <div class="glass-card reveal rd2">
        <div class="p-6" style="border-bottom:1px solid rgba(34,51,92,0.07);">
            <div class="sec-eyebrow mb-1">Activity</div>
            <h2 class="sec-heading">Recent Verified Reports</h2>
        </div>

        <?php if (empty($recentReports)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" style="color:var(--slate);">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
                </svg>
            </div>
            <p style="font-size:.875rem;color:#64748b;">No verified reports yet.</p>
            <p style="font-size:.78rem;color:#94a3b8;margin-top:.3rem;">Peer-verified reports will appear here once submitted.</p>
        </div>
        <?php else: ?>
        <div>
            <?php foreach ($recentReports as $report): ?>
            <?php
                $cl       = strtolower($report["crowd_level"] ?? "unknown");
                $dotClass = "feed-dot-" . ucfirst($cl);
                $badgeCls = "badge-" . $cl;
            ?>
            <div class="feed-item">
                <span class="feed-dot <?= $dotClass ?>"></span>
                <div class="flex-1 min-w-0">
                    <div class="feed-route"><?= htmlspecialchars($report["route_name"] ?? "Unknown Route") ?></div>
                    <div class="feed-meta">
                        <span class="badge <?= $badgeCls ?>"><?= htmlspecialchars(ucfirst($report["crowd_level"] ?? "unknown")) ?></span>
                        <span class="verify-chip">
                            <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <?= (int)$report["verification_count"] ?> verification<?= (int)$report["verification_count"] !== 1 ? "s" : "" ?>
                        </span>
                    </div>
                </div>
                <span class="feed-time"><?= date("M j, Y", strtotime($report["created_at"])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Back / navigation buttons -->
    <div class="mt-8 flex flex-wrap items-center gap-4 reveal rd3">
        <a href="javascript:history.back()" class="cta-btn-ghost">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Go Back
        </a>
        <?php if ($viewerLoggedIn && !$isOwnProfile): ?>
        <a href="reports_map.php" class="cta-btn-ghost">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
            </svg>
            Live Map
        </a>
        <?php endif; ?>
    </div>

</main>

<?php if (!$fromAdmin): ?>
<!-- ═══════════════ FOOTER ═══════════════ -->
<footer class="site-footer">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10 relative z-10">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="footer-logo">Transport<span>Ops</span></div>
            <div class="flex flex-wrap gap-5 justify-center">
                <a href="<?= $viewerLoggedIn ? 'user_dashboard.php' : 'index.php' ?>" class="footer-link">Home</a>
                <a href="about.php"       class="footer-link">About</a>
                <?php if ($viewerLoggedIn): ?>
                <a href="report.php"      class="footer-link">Submit Report</a>
                <?php endif; ?>
                <a href="reports_map.php" class="footer-link">Reports Map</a>
                <a href="routes.php"      class="footer-link">Routes</a>
            </div>
            <p style="color:#475569;font-size:.78rem;white-space:nowrap;">&copy; <?= date("Y") ?> Transport Ops</p>
        </div>
    </div>
</footer>
<?php endif; ?>

<script>
(function () {
    // Nav scroll
    var nav = document.getElementById("floatingNav");
    if (nav) {
        window.addEventListener("scroll", function () {
            if (window.scrollY > 20) { nav.classList.add("scrolled"); nav.style.top = "0.5rem"; }
            else                     { nav.classList.remove("scrolled"); nav.style.top = "1rem"; }
        }, { passive: true });
    }

    // Profile dropdown
    var btn  = document.getElementById("profileMenuButton");
    var menu = document.getElementById("profileMenu");
    if (btn && menu) {
        btn.addEventListener("click", function(e) { e.stopPropagation(); menu.classList.toggle("hidden"); });
        document.addEventListener("click", function() { if (menu) menu.classList.add("hidden"); });
    }

    // Mobile menu
    var brand  = document.getElementById("brandLink");
    var mobile = document.getElementById("mobileMenu");
    if (brand && mobile) {
        brand.addEventListener("click", function(e) {
            if (window.innerWidth < 768) { e.preventDefault(); mobile.classList.toggle("hidden"); }
        });
        document.addEventListener("click", function(ev) {
            if (mobile && !mobile.contains(ev.target) && ev.target !== brand) mobile.classList.add("hidden");
        });
    }

    // Scroll reveal
    var reveals = document.querySelectorAll(".reveal");
    if ("IntersectionObserver" in window) {
        var io = new IntersectionObserver(function(entries) {
            entries.forEach(function(e) {
                if (e.isIntersecting) { e.target.classList.add("visible"); io.unobserve(e.target); }
            });
        }, { threshold: 0.08 });
        reveals.forEach(function(el) { io.observe(el); });
    } else {
        reveals.forEach(function(el) { el.classList.add("visible"); });
    }
})();
</script>
</body>
</html>