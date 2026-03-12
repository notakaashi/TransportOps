<?php
/**
 * Enhanced Routes View – redesigned to match the Transport Ops glass UI
 */
require_once "auth_helper.php";
secureSessionStart();
require_once "db.php";

$is_logged_in = isset($_SESSION["user_id"]);
$user_profile_data = ["profile_image" => null];

if ($is_logged_in) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
        $stmt->execute([$_SESSION["user_id"]]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $user_profile_data = $row;
            if ($row["profile_image"]) {
                $_SESSION["profile_image"] = $row["profile_image"];
            }
        }
    } catch (Exception $e) {
        $user_profile_data = ["profile_image" => null];
    }
}

// Fetch routes with stops
try {
    $pdo = getDBConnection();
    $allowedCategories = ["tricycle", "jeepney", "rail"];
    $selectedCategory = isset($_GET["category"])
        ? strtolower(trim((string) $_GET["category"]))
        : "all";
    if ($selectedCategory !== "all" && !in_array($selectedCategory, $allowedCategories, true)) {
        $selectedCategory = "all";
    }

    try {
        if ($selectedCategory !== "all") {
            $stmt = $pdo->prepare(
                "SELECT id, name, vehicle_category, created_at
                 FROM route_definitions
                 WHERE vehicle_category = ?
                 ORDER BY name",
            );
            $stmt->execute([$selectedCategory]);
        } else {
            $stmt = $pdo->query(
                "SELECT id, name, vehicle_category, created_at FROM route_definitions ORDER BY name",
            );
        }
        $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Backwards compatibility if vehicle_category isn't present yet.
        $stmt = $pdo->query(
            "SELECT id, name, created_at FROM route_definitions ORDER BY name",
        );
        $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($routes as &$r) {
            $r["vehicle_category"] = null;
        }
        unset($r);
        $selectedCategory = "all";
    }

    $stmt = $pdo->query(
        "SELECT id, route_definition_id, stop_name, latitude, longitude, stop_order FROM route_stops ORDER BY route_definition_id, stop_order",
    );
    $stops = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stopsByRoute = [];
    foreach ($stops as $s) {
        $rid = $s["route_definition_id"];
        if (!isset($stopsByRoute[$rid])) {
            $stopsByRoute[$rid] = [];
        }
        $stopsByRoute[$rid][] = [
            "id" => (int) $s["id"],
            "stop_name" => $s["stop_name"],
            "latitude" => (float) $s["latitude"],
            "longitude" => (float) $s["longitude"],
            "stop_order" => (int) $s["stop_order"],
        ];
    }

    foreach ($routes as &$r) {
        $r["id"] = (int) $r["id"];
        $r["vehicle_category"] = $r["vehicle_category"] ?? null;
        $r["stops"] = $stopsByRoute[$r["id"]] ?? [];
        usort($r["stops"], fn($a, $b) => $a["stop_order"] - $b["stop_order"]);
    }
    unset($r);
} catch (PDOException $e) {
    error_log("Routes error: " . $e->getMessage());
    $routes = [];
    $selectedCategory = "all";
}

$totalStops = array_sum(array_map(fn($r) => count($r["stops"]), $routes));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Routes — Transport Ops</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="js/osrm-helpers.js"></script>
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

/* ── Floating Nav ───────────────────────────────── */
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

/* ── Hero ───────────────────────────────────────── */
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
    width: 520px; height: 520px; top: -180px; right: -100px;
    background: radial-gradient(circle, rgba(91,123,153,0.18) 0%, transparent 70%);
    animation: orbPulse 18s ease-in-out infinite alternate;
}
.hero-orb-2 {
    width: 320px; height: 320px; bottom: -110px; left: 6%;
    background: radial-gradient(circle, rgba(251,192,97,0.08) 0%, transparent 70%);
    animation: orbPulse 24s ease-in-out infinite alternate-reverse;
}
@keyframes orbPulse {
    from { transform: translate(0,0) scale(1); }
    to   { transform: translate(28px,18px) scale(1.08); }
}
.page-eyebrow {
    display: inline-flex; align-items: center; gap: 0.45rem;
    background: rgba(251,192,97,0.13); border: 1px solid rgba(251,192,97,0.32);
    color: var(--gold); border-radius: 999px;
    padding: 0.28rem 0.9rem; font-size: 0.72rem; font-weight: 700;
    letter-spacing: 0.08em; text-transform: uppercase;
}
.hero-title {
    font-family: 'Poppins', sans-serif;
    font-size: clamp(2rem, 5vw, 3.2rem);
    font-weight: 800; color: #fff;
    line-height: 1.12; letter-spacing: -0.025em;
}
.hero-title .gold { color: var(--gold); }
.hero-subtitle { color: #94a3b8; font-size: 1rem; line-height: 1.7; max-width: 520px; }
.hero-stat {
    font-size: 0.9rem; color: rgba(255,255,255,0.6); font-weight: 500;
}
.hero-stat strong { color: #fff; font-weight: 800; font-family: 'Poppins', sans-serif; font-size: 1.05rem; margin-right: 0.2rem; }

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
.admin-link {
    display: inline-flex; align-items: center; gap: 0.4rem;
    font-size: 0.82rem; font-weight: 600; color: var(--gold);
    text-decoration: none; opacity: 0.85; transition: opacity 0.2s;
}
.admin-link:hover { opacity: 1; text-decoration: underline; }

/* ── Section headings ───────────────────────────── */
.sec-eyebrow {
    font-size: 0.68rem; font-weight: 700; letter-spacing: 0.12em;
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

/* ── Glass cards ────────────────────────────────── */
.glass-card {
    background: rgba(255,255,255,0.82);
    backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.95);
    box-shadow: 0 4px 24px rgba(34,51,92,0.07), 0 1px 4px rgba(34,51,92,0.04);
    border-radius: 1.125rem;
}

/* ── Tab buttons ────────────────────────────────── */
.tab-bar {
    display: inline-flex; gap: 0.35rem;
    padding: 0.35rem; background: rgba(255,255,255,0.82);
    backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.95);
    box-shadow: 0 4px 24px rgba(34,51,92,0.07);
    border-radius: 0.85rem;
}
.tab-btn {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.6rem 1.4rem; border-radius: 0.6rem;
    font-size: 0.875rem; font-weight: 600;
    border: 1px solid transparent; cursor: pointer;
    transition: all 0.2s; background: transparent;
    color: #64748b; white-space: nowrap;
}
.tab-btn.tab-active {
    background: var(--navy); color: #fff;
    box-shadow: 0 4px 14px rgba(34,51,92,0.22);
    border-color: transparent;
}
.tab-btn:hover:not(.tab-active) {
    background: rgba(34,51,92,0.07);
    color: var(--navy);
}

/* ── Route cards ────────────────────────────────── */
.route-card { transition: transform 0.25s, box-shadow 0.25s; }
.route-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 14px 40px rgba(34,51,92,0.13), 0 2px 8px rgba(34,51,92,0.06);
}
.route-header {
    padding: 1.4rem 1.5rem;
    border-bottom: 1px solid rgba(34,51,92,0.07);
    display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;
}
.route-name-row { display: flex; align-items: center; gap: 0.65rem; }
.route-dot {
    width: 11px; height: 11px; border-radius: 50%; flex-shrink: 0;
    box-shadow: 0 0 0 3px rgba(255,255,255,0.85);
}
.route-title {
    font-family: 'Poppins', sans-serif;
    font-size: 1.1rem; font-weight: 800; color: var(--navy);
    letter-spacing: -0.02em;
}
.route-meta { font-size: 0.78rem; color: #94a3b8; margin-top: 0.2rem; font-weight: 500; }
.route-badge {
    display: inline-flex; align-items: center; gap: 0.3rem;
    font-size: 0.7rem; font-weight: 700; padding: 0.22rem 0.65rem;
    border-radius: 999px; letter-spacing: 0.04em;
    background: rgba(251,192,97,0.16); color: #92600a;
    border: 1px solid rgba(251,192,97,0.35);
    white-space: nowrap; flex-shrink: 0;
}
.route-badge::before {
    content: ''; width: 5px; height: 5px; border-radius: 50%;
    background: var(--gold-dark); flex-shrink: 0;
}

/* Stop pills */
.stop-pill {
    display: inline-flex; align-items: center; gap: 0.3rem;
    padding: 0.25rem 0.7rem; border-radius: 999px;
    font-size: 0.73rem; font-weight: 600;
    background: rgba(34,51,92,0.07); color: var(--navy);
    border: 1px solid rgba(34,51,92,0.1);
    transition: background 0.15s;
}
.stop-pill:hover { background: rgba(34,51,92,0.12); }
.stop-num {
    font-size: 0.63rem; font-weight: 800;
    background: var(--navy); color: #fff;
    border-radius: 50%; width: 15px; height: 15px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.route-path {
    font-size: 0.8rem; color: #64748b; line-height: 1.5;
}
.route-path strong { color: var(--navy); font-weight: 700; }
.route-map    { height: 300px; border-radius: 0.75rem; overflow: hidden; }
.combined-map { height: 520px; border-radius: 0 0 1.125rem 1.125rem; overflow: hidden; }

/* ── Toggle labels (combined view) ─────────────── */
.toggle-label {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.38rem 0.85rem 0.38rem 0.5rem; border-radius: 999px;
    font-size: 0.78rem; font-weight: 600; color: #475569;
    background: rgba(34,51,92,0.06); border: 1px solid rgba(34,51,92,0.1);
    cursor: pointer; transition: all 0.18s; user-select: none;
}
.toggle-label:hover { background: rgba(34,51,92,0.1); color: var(--navy); }
.toggle-label.toggle-active { background: rgba(34,51,92,0.1); color: var(--navy); border-color: rgba(34,51,92,0.2); }
.route-color-dot {
    width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
    box-shadow: 0 0 0 2px rgba(255,255,255,0.9);
}
.check-icon { opacity: 0; transition: opacity 0.18s; color: var(--navy); }
.toggle-label.toggle-active .check-icon { opacity: 1; }

/* ── Empty state ─────────────────────────────────*/
.empty-state { text-align: center; padding: 4rem 1.5rem; }
.empty-icon {
    width: 60px; height: 60px; border-radius: 1rem;
    background: rgba(34,51,92,0.07);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.1rem;
}

/* ── Footer ─────────────────────────────────────── */
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

/* ── Scroll reveal ───────────────────────────────── */
.reveal {
    opacity: 0; transform: translateY(22px);
    transition: opacity 0.6s cubic-bezier(.4,0,.2,1), transform 0.6s cubic-bezier(.4,0,.2,1);
}
.reveal.visible { opacity: 1; transform: none; }
.rd1 { transition-delay: 0.07s; }
.rd2 { transition-delay: 0.14s; }
.rd3 { transition-delay: 0.21s; }
.rd4 { transition-delay: 0.28s; }

@media (max-width: 640px) {
    .hero-title { font-size: 2rem; }
    .route-header { flex-direction: column; gap: 0.75rem; }
    .tab-btn { padding: 0.55rem 1rem; font-size: 0.8rem; }
}
</style>
</head>
<body>

<!-- ═══════════════ FLOATING NAV ═══════════════ -->
<nav id="floatingNav" class="fixed top-4 left-1/2 -translate-x-1/2 z-40 glass-nav text-white rounded-2xl w-[calc(100%-2rem)] max-w-7xl">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-14">

            <div class="flex items-center gap-7">
                <a href="<?= $is_logged_in
                    ? "user_dashboard.php"
                    : "index.php" ?>" id="brandLink"
                   style="font-family:'Poppins',sans-serif;font-size:1.2rem;font-weight:800;color:#fff;text-decoration:none;white-space:nowrap;letter-spacing:-0.01em;">
                    Transport<span style="color:var(--gold);">Ops</span>
                </a>
                <div class="hidden md:flex gap-1">
                    <a href="<?= $is_logged_in
                        ? "user_dashboard.php"
                        : "index.php" ?>" class="nav-link">Home</a>
                    <a href="about.php"       class="nav-link">About</a>
                    <?php if ($is_logged_in): ?>
                    <a href="report.php"      class="nav-link">Submit Report</a>
                    <?php endif; ?>
                    <a href="reports_map.php" class="nav-link">Reports Map</a>
                    <a href="routes.php"      class="nav-link active">Routes</a>
                </div>
                <!-- Mobile menu -->
                <div id="mobileMenu"
                     class="md:hidden hidden absolute top-full left-0 right-0 mt-2 flex flex-col gap-1 px-4 py-3 z-20 rounded-2xl"
                     style="background:rgba(25,40,74,0.97);backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,0.12);box-shadow:0 8px 32px rgba(15,28,54,0.4);">
                    <a href="<?= $is_logged_in
                        ? "user_dashboard.php"
                        : "index.php" ?>" class="nav-link-mobile">Home</a>
                    <a href="about.php"       class="nav-link-mobile">About</a>
                    <?php if ($is_logged_in): ?>
                    <a href="report.php"      class="nav-link-mobile">Submit Report</a>
                    <?php endif; ?>
                    <a href="reports_map.php" class="nav-link-mobile">Reports Map</a>
                    <a href="routes.php"      class="nav-link-mobile active">Routes</a>
                </div>
            </div>

            <!-- Right: profile / auth buttons -->
            <div class="relative flex items-center gap-2">
                <?php if ($is_logged_in): ?>
                <button id="profileMenuButton"
                        class="flex items-center gap-2 px-2 py-1.5 rounded-full hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/40 transition">
                    <div class="hidden sm:flex flex-col items-end leading-tight">
                        <span class="text-sm text-white font-medium"><?= htmlspecialchars(
                            $_SESSION["user_name"] ?? "",
                        ) ?></span>
                        <span class="text-[11px] text-blue-200"><?= htmlspecialchars(
                            $_SESSION["role"] ?? "",
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
                                substr($_SESSION["user_name"] ?? "U", 0, 1),
                            ) ?>
                        </div>
                        <?php endif; ?>
                        <svg class="w-4 h-4 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </button>
                <div id="profileMenu"
                     class="hidden absolute right-0 top-12 w-48 glass-dropdown rounded-xl shadow-xl py-1 z-50">
                    <a href="profile.php"
                       class="block px-4 py-2 text-sm text-white hover:bg-white/10 mx-1 rounded-lg">View &amp; Edit Profile</a>
                    <a href="public_profile.php?id=<?= $_SESSION["user_id"] ?>"
                       class="block px-4 py-2 text-sm text-white hover:bg-white/10 mx-1 rounded-lg">Public Profile</a>
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

<!-- ═══════════════ HERO ═══════════════ -->
<section class="hero pt-20">
    <div class="hero-grid"></div>
    <div class="hero-orb hero-orb-1"></div>
    <div class="hero-orb hero-orb-2"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 relative z-10">
        <div class="page-eyebrow mb-5">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>

            </svg>
            Metro Manila Transit Network
        </div>
        <h1 class="hero-title mt-3">Transport <span class="gold">Routes</span></h1>
        <p class="hero-subtitle mt-4">Browse every transit corridor, stop sequence, and coverage area across the Metro Manila network.</p>
        <div class="flex flex-wrap items-center gap-6 mt-5">
            <span class="hero-stat"><strong><?= count($routes) ?></strong> Routes</span>
            <span class="hero-stat"><strong><?= $totalStops ?></strong> Stops</span>
        </div>
        <div class="flex flex-wrap gap-3 mt-6">
            <a href="reports_map.php" class="cta-btn">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                Reports Map
            </a>
            <?php if ($is_logged_in): ?>
            <a href="report.php" class="cta-btn-ghost">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Submit Report
            </a>
            <?php endif; ?>
        </div>
        <?php if ($is_logged_in && $_SESSION["role"] === "Admin"): ?>
        <div class="mt-5">
            <a href="manage_routes.php" class="admin-link">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Manage Routes &rarr;
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ======== MAIN CONTENT ======== -->
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">

    <!-- Category filter -->
    <div class="flex items-center justify-between flex-wrap gap-3 mb-6 reveal">
        <div class="flex items-center gap-2">
            <span class="sec-eyebrow">Category</span>
            <select id="categoryFilter"
                class="px-3 py-2 rounded-xl border border-gray-200 bg-white/80 text-sm font-semibold"
                style="backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);">
                <option value="all" <?= $selectedCategory === "all"
                    ? "selected"
                    : "" ?>>All</option>
                <option value="tricycle" <?= $selectedCategory === "tricycle"
                    ? "selected"
                    : "" ?>>Tricycle</option>
                <option value="jeepney" <?= $selectedCategory === "jeepney"
                    ? "selected"
                    : "" ?>>Jeepney</option>
                <option value="rail" <?= $selectedCategory === "rail"
                    ? "selected"
                    : "" ?>>MRT/LRT</option>
            </select>
        </div>
        <p class="text-sm" style="color:#94a3b8;">
            Showing <strong style="color:var(--navy);"><?= count(
                $routes,
            ) ?></strong> route<?= count($routes) !== 1 ? "s" : "" ?>
        </p>
    </div>

    <!-- Tab bar -->
    <div class="flex items-center justify-between flex-wrap gap-4 mb-8 reveal">
        <div class="tab-bar">
            <button id="individualTab" class="tab-btn tab-active">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                Individual Routes
            </button>
            <button id="combinedTab" class="tab-btn">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Combined View
            </button>
        </div>
        <p class="text-sm" style="color:#94a3b8;"><?= count($routes) ?> route<?= count($routes) !== 1 ? "s" : "" ?> &bull; <?= $totalStops ?> total stop<?= $totalStops !== 1 ? "s" : "" ?></p>
    </div>

    <!-- Individual Routes View -->
    <div id="individualView" class="space-y-6">
        <?php if (empty($routes)): ?>
        <div class="glass-card empty-state reveal">
            <div class="empty-icon">
                <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" style="color:var(--slate);"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
            </div>
            <h3 style="font-family:Poppins,sans-serif;font-size:1.1rem;font-weight:800;color:var(--navy);margin-bottom:.5rem;">No Routes Yet</h3>
            <p style="color:#64748b;font-size:.875rem;margin-bottom:1.5rem;">No transit routes have been defined in the system.</p>
            <?php if ($is_logged_in && $_SESSION["role"] === "Admin"): ?>
            <a href="manage_routes.php" class="cta-btn" style="display:inline-flex;">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Create First Route
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <?php foreach ($routes as $i => $route): ?>
        <div class="glass-card route-card reveal rd<?= ($i % 4) + 1 ?> overflow-hidden">

            <!-- Route header -->
            <div class="route-header">
                <div>
                    <div class="route-name-row">
                        <span class="route-dot" data-index="<?= $i ?>"></span>
                        <h3 class="route-title"><?= htmlspecialchars($route["name"]) ?></h3>
                    </div>
                    <p class="route-meta mt-1">
                        <?= count($route["stops"]) ?> stop<?= count($route["stops"]) !== 1 ? "s" : "" ?>
                        &bull; Created <?= date("M j, Y", strtotime($route["created_at"])) ?>
                    </p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <span class="route-badge">Active</span>
                    <?php if ($is_logged_in && $_SESSION["role"] === "Admin"): ?>
                    <a href="manage_routes.php" class="admin-link" title="Manage routes">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Edit
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($route["stops"])): ?>
            <!-- Stop pills -->
            <div class="px-5 pt-4 pb-2">
                <p class="sec-eyebrow mb-2">Stops</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($route["stops"] as $j => $stop): ?>
                    <span class="stop-pill">
                        <span class="stop-num"><?= $j + 1 ?></span>
                        <?= htmlspecialchars($stop["stop_name"]) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <!-- Map -->
            <div class="px-5 pb-4 pt-3">
                <div class="route-map" id="map-<?= $route["id"] ?>"></div>
            </div>
            <!-- Route path -->
            <div class="px-5 pb-5">
                <p class="route-path">
                    <strong>Route:</strong>
                    <?= implode(" &rarr; ", array_map(fn($s) => htmlspecialchars($s["stop_name"]), $route["stops"])) ?>
                </p>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding:2.5rem 1.5rem;">
                <div class="empty-icon">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" style="color:var(--slate);">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <p style="color:#94a3b8;font-size:.85rem;">No stops defined for this route.</p>
            </div>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Combined View -->
    <div id="combinedView" class="hidden">
        <div class="glass-card reveal">
            <div class="p-6" style="border-bottom:1px solid rgba(34,51,92,0.07);">
                <div class="sec-eyebrow mb-1">Network Overview</div>
                <h2 class="sec-heading mb-1">All Routes Combined</h2>
                <p style="color:#64748b;font-size:.85rem;margin-bottom:1.25rem;">Toggle individual routes on or off and explore the full network on one map.</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($routes as $i => $route): ?>
                    <label class="toggle-label toggle-active">
                        <input type="checkbox" class="route-toggle" style="position:absolute;opacity:0;width:0;height:0;" data-route-id="<?= $route["id"] ?>" data-index="<?= $i ?>" checked>
                        <span class="route-color-dot" data-index="<?= $i ?>"></span>
                        <span><?= htmlspecialchars($route["name"]) ?></span>
                        <svg class="check-icon" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="combined-map" id="combinedMap"></div>
        </div>
    </div>

</main>

<!-- ======== FOOTER ======== -->
<footer class="site-footer">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 relative z-10">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="footer-logo">Transport<span>Ops</span></div>
            <div class="flex flex-wrap gap-5 justify-center">
                <a href="<?= $is_logged_in ? 'user_dashboard.php' : 'index.php' ?>" class="footer-link">Home</a>
                <a href="about.php"       class="footer-link">About</a>
                <?php if ($is_logged_in): ?>
                <a href="report.php"      class="footer-link">Submit Report</a>
                <?php endif; ?>
                <a href="reports_map.php" class="footer-link">Reports Map</a>
                <a href="routes.php"      class="footer-link" style="color:var(--gold);">Routes</a>
            </div>
            <p style="color:#475569;font-size:.78rem;white-space:nowrap;">&copy; <?= date("Y") ?> Transport Ops</p>
        </div>
    </div>
</footer>

<!-- ======== SCRIPTS ======== -->
<script>
    const routesData = <?= json_encode($routes) ?>;
    const selectedCategory = <?= json_encode($selectedCategory) ?>;
    let individualMaps = {};
    let combinedMap    = null;
    let combinedLayers = {};

    const routeColors = [
        "#10B981","#3B82F6","#F59E0B","#EF4444","#8B5CF6",
        "#EC4899","#14B8A6","#F97316","#6366F1","#84CC16"
    ];
    function getRouteColor(i) { return routeColors[i % routeColors.length]; }

    // Colorise every [data-index] element (route dots + combined color dots)
    document.querySelectorAll("[data-index]").forEach(function(el) {
        el.style.backgroundColor = getRouteColor(parseInt(el.dataset.index));
    });

    // Toggle-label interactivity
    document.querySelectorAll(".toggle-label").forEach(function(label) {
        var cb = label.querySelector(".route-toggle");
        cb.addEventListener("change", function() {
            label.classList.toggle("toggle-active", cb.checked);
            var rid = parseInt(cb.dataset.routeId);
            if (combinedLayers[rid]) {
                cb.checked ? combinedLayers[rid].addTo(combinedMap) : combinedMap.removeLayer(combinedLayers[rid]);
            }
        });
    });

    // Individual maps
    function initializeIndividualMaps() {
        routesData.forEach(function(route, index) {
            if (!route.stops || route.stops.length === 0) return;
            if (!document.getElementById("map-" + route.id)) return;

            var waypoints = route.stops.map(function(s){ return [s.latitude, s.longitude]; });
            var map  = L.map("map-" + route.id).setView(waypoints[0], 13);
            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { attribution: "&copy; OpenStreetMap" }).addTo(map);

            var layer = L.layerGroup().addTo(map);
            var color = getRouteColor(index);

            function drawRoute(latlngs) {
                L.polyline(latlngs, { color: color, weight: 4, opacity: 0.85 }).addTo(layer);
                route.stops.forEach(function(stop, i) {
                    L.marker([stop.latitude, stop.longitude])
                        .bindPopup("<strong>" + (i + 1) + ". " + (stop.stop_name || "Stop") + "</strong>")
                        .addTo(layer);
                });
                map.fitBounds(latlngs, { padding: [24, 24] });
            }

            if (typeof getRouteGeometry === "function") {
                getRouteGeometry(waypoints, function(road) { drawRoute(road && road.length ? road : waypoints); });
            } else {
                drawRoute(waypoints);
            }
            individualMaps[route.id] = { map: map, layer: layer, color: color };
        });
    }

    // Combined map
    function initializeCombinedMap() {
        combinedMap = L.map("combinedMap").setView([14.5995, 120.9842], 11);
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { attribution: "&copy; OpenStreetMap" }).addTo(combinedMap);

        routesData.forEach(function(route, index) {
            if (!route.stops || route.stops.length === 0) return;
            var waypoints = route.stops.map(function(s){ return [s.latitude, s.longitude]; });
            var color = getRouteColor(index);
            var layer = L.layerGroup();

            function drawCombined(latlngs) {
                L.polyline(latlngs, { color: color, weight: 3, opacity: 0.75 }).addTo(layer);
                route.stops.forEach(function(stop, i) {
                    L.marker([stop.latitude, stop.longitude])
                        .bindPopup("<strong>" + route.name + "</strong><br>" + (i + 1) + ". " + (stop.stop_name || "Stop"))
                        .addTo(layer);
                });
            }

            if (typeof getRouteGeometry === "function") {
                getRouteGeometry(waypoints, function(road) { drawCombined(road && road.length ? road : waypoints); });
            } else {
                drawCombined(waypoints);
            }
            layer.addTo(combinedMap);
            combinedLayers[route.id] = layer;
        });

        var allPts = [];
        routesData.forEach(function(r) { (r.stops || []).forEach(function(s) { allPts.push([s.latitude, s.longitude]); }); });
        if (allPts.length > 0) combinedMap.fitBounds(allPts, { padding: [40, 40] });
    }

    // Tab switching
    document.getElementById("individualTab").addEventListener("click", function() {
        document.getElementById("individualView").classList.remove("hidden");
        document.getElementById("combinedView").classList.add("hidden");
        this.classList.add("tab-active");
        document.getElementById("combinedTab").classList.remove("tab-active");
        if (Object.keys(individualMaps).length === 0) setTimeout(initializeIndividualMaps, 100);
    });

    document.getElementById("combinedTab").addEventListener("click", function() {
        document.getElementById("individualView").classList.add("hidden");
        document.getElementById("combinedView").classList.remove("hidden");
        this.classList.add("tab-active");
        document.getElementById("individualTab").classList.remove("tab-active");
        if (!combinedMap) setTimeout(initializeCombinedMap, 100);
    });

    // Boot
    setTimeout(initializeIndividualMaps, 150);

    // Category filter (reload with query param)
    (function () {
        var sel = document.getElementById("categoryFilter");
        if (!sel) return;
        sel.addEventListener("change", function () {
            var val = sel.value || "all";
            var url = new URL(window.location.href);
            if (val === "all") url.searchParams.delete("category");
            else url.searchParams.set("category", val);
            window.location.href = url.toString();
        });
    })();
</script>

<script>
(function () {
    // Nav scroll shrink
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

    // Mobile menu (brand tap)
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