<?php
require_once "auth_helper.php";
secureSessionStart();
require_once "db.php";
require_once "trust_helper.php";
require_once "trust_badge_helper.php";

$is_logged_in = isset($_SESSION["user_id"]);
$user_profile_data = ["profile_image" => null];
$selectedRoute = isset($_GET["route"]) ? $_GET["route"] : "";
$selectedCategory = isset($_GET["category"])
    ? strtolower(trim((string) $_GET["category"]))
    : "";
$allowedCategories = ["tricycle", "jeepney", "rail"];
if (!in_array($selectedCategory, $allowedCategories, true)) {
    $selectedCategory = "";
}
try {
    $pdo = getDBConnection();

    // Get all available routes for filtering
    $availableRoutes = [];
    $hasVehicleCategory = true;
    try {
        if ($selectedCategory !== "") {
            $routesStmt = $pdo->prepare("
                SELECT name as route_name
                FROM route_definitions
                WHERE vehicle_category = ?
                ORDER BY name
            ");
            $routesStmt->execute([$selectedCategory]);
        } else {
            $routesStmt = $pdo->query("
                SELECT name as route_name
                FROM route_definitions
                ORDER BY name
            ");
        }
        $availableRoutes = $routesStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        // Backwards compatibility if vehicle_category doesn't exist yet.
        $hasVehicleCategory = false;
        $routesStmt = $pdo->query("
            SELECT name as route_name
            FROM route_definitions
            ORDER BY name
        ");
        $availableRoutes = $routesStmt->fetchAll(PDO::FETCH_COLUMN);
        $selectedCategory = "";
    }

    // Build reports query with route filter
    // Only show recent reports on the live map (last 1 hour)
    $whereClause = "WHERE r.latitude IS NOT NULL AND r.longitude IS NOT NULL AND r.timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
    $params = [];

    if (!empty($selectedCategory) && $hasVehicleCategory) {
        $whereClause .= " AND rd.vehicle_category = ?";
        $params[] = $selectedCategory;
    }

    if (!empty($selectedRoute)) {
        $whereClause .= " AND rd.name = ?";
        $params[] = $selectedRoute;
    }

    $stmt = $pdo->prepare("
        SELECT r.id, r.crowd_level, r.delay_reason, r.timestamp, r.latitude, r.longitude,
               r.is_verified, r.peer_verifications, r.status,
               u.id as user_id, u.name as user_name, u.trust_score,
               rd.name AS route_name
        FROM reports r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN route_definitions rd ON r.route_definition_id = rd.id
        $whereClause
        ORDER BY r.timestamp DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Enhance reports with trust badge information
    foreach ($reports as &$report) {
        if ($report["user_id"]) {
            $report["trust_score"] = $report["trust_score"] ?? 50.0;
            $report["trust_badge"] = getTrustBadge($report["trust_score"]);
        } else {
            $report["trust_score"] = 50.0;
            $report["trust_badge"] = getTrustBadge(50.0);
        }
    }

    // Fetch profile image for logged-in users only
    if ($is_logged_in) {
        $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
        $stmt->execute([$_SESSION["user_id"]]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row["profile_image"]) {
            $user_profile_data["profile_image"] = $row["profile_image"];
            $_SESSION["profile_image"] = $row["profile_image"];
        }
    }
} catch (PDOException $e) {
    error_log("Reports map error: " . $e->getMessage());
    $reports = [];
    $availableRoutes = [];
    $hasVehicleCategory = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Map — Transport Ops</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Poppins:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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

        /* ── Page Header Strip ────────────────────────────── */
        .page-header {
            position: relative;
            background: linear-gradient(135deg, var(--navy-deep) 0%, var(--navy-mid) 55%, #1a2f5a 100%);
            overflow: hidden;
        }
        .page-header::before {
            content: '';
            position: absolute; inset: 0;
            background:
                radial-gradient(ellipse at 10% 60%, rgba(91,123,153,0.25) 0%, transparent 55%),
                radial-gradient(ellipse at 90% 10%, rgba(251,192,97,0.1) 0%, transparent 50%);
        }
        .header-grid {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 48px 48px;
        }
        .page-badge {
            display: inline-flex; align-items: center; gap: 0.4rem;
            background: rgba(251,192,97,0.15); border: 1px solid rgba(251,192,97,0.35);
            color: var(--gold); border-radius: 999px;
            padding: 0.25rem 0.8rem; font-size: 0.72rem; font-weight: 600;
            letter-spacing: 0.06em; text-transform: uppercase; margin-bottom: 0.6rem;
        }
        .page-title {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(1.5rem, 3vw, 2.1rem);
            font-weight: 800; color: #fff;
            line-height: 1.15; letter-spacing: -0.02em;
        }
        .page-title .gold { color: var(--gold); }
        .page-subtitle { color: #94a3b8; font-size: 0.875rem; line-height: 1.6; margin-top: 0.35rem; }

        /* ── Glass Card ───────────────────────────────────── */
        .glass-card {
            background: rgba(255,255,255,0.82);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.95);
            box-shadow: 0 4px 24px rgba(34,51,92,0.07), 0 1px 4px rgba(34,51,92,0.04);
            border-radius: 1.25rem;
        }

        /* ── Section Heading ──────────────────────────────── */
        .sec-eyebrow {
            font-size: 0.68rem; font-weight: 700; letter-spacing: 0.12em;
            text-transform: uppercase; color: var(--slate);
            display: flex; align-items: center; gap: 0.5rem;
        }
        .sec-eyebrow::before {
            content: ''; display: block; width: 1.1rem; height: 2px;
            background: var(--gold); border-radius: 999px;
        }
        .sec-heading {
            font-family: 'Poppins', sans-serif;
            font-size: 1rem; font-weight: 800; color: var(--navy);
            letter-spacing: -0.01em; margin-top: 0.2rem;
        }

        /* ── Form Select ──────────────────────────────────── */
        .form-select {
            width: 100%;
            padding: 0.6rem 2.2rem 0.6rem 0.9rem;
            background: rgba(255,255,255,0.92);
            border: 1.5px solid rgba(34,51,92,0.15);
            border-radius: 0.6rem;
            font-size: 0.85rem; font-weight: 500;
            color: var(--navy);
            appearance: none; -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2322335C' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.7rem center;
            background-size: 0.9rem;
            cursor: pointer;
            transition: border-color 0.2s, box-shadow 0.2s;
            font-family: 'Inter', sans-serif;
        }
        .form-select:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(251,192,97,0.22);
        }
        .form-label {
            display: block;
            font-size: 0.78rem; font-weight: 600; color: var(--navy);
            margin-bottom: 0.4rem;
        }

        /* ── Legend Badges ────────────────────────────────── */
        .legend-dot {
            width: 11px; height: 11px; border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.8);
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
            flex-shrink: 0;
        }
        .legend-square {
            width: 11px; height: 11px; border-radius: 2px;
            flex-shrink: 0;
        }
        .legend-label { font-size: 0.78rem; color: #475569; font-weight: 500; }

        /* ── Report List Items ────────────────────────────── */
        .report-item {
            background: rgba(255,255,255,0.9);
            border: 1.5px solid rgba(34,51,92,0.08);
            border-radius: 0.75rem;
            padding: 0.85rem 1rem;
            cursor: pointer;
            transition: all 0.18s;
        }
        .report-item:hover {
            border-color: rgba(34,51,92,0.2);
            box-shadow: 0 4px 14px rgba(34,51,92,0.09);
            transform: translateY(-1px);
        }
        .report-item.active {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(251,192,97,0.2);
            background: rgba(251,192,97,0.06);
        }

        /* ── Crowd Badges ─────────────────────────────────── */
        .crowd-badge {
            display: inline-flex; align-items: center; gap: 0.3rem;
            font-size: 0.7rem; font-weight: 700; padding: 0.18rem 0.55rem;
            border-radius: 999px; letter-spacing: 0.03em;
        }
        .crowd-badge::before {
            content: ''; width: 5px; height: 5px;
            border-radius: 50%; flex-shrink: 0;
        }
        .crowd-light    { background: #dcfce7; color: #166534; }
        .crowd-light::before    { background: #16a34a; }
        .crowd-moderate { background: #fef9c3; color: #854d0e; }
        .crowd-moderate::before { background: #ca8a04; }
        .crowd-heavy    { background: #fee2e2; color: #991b1b; }
        .crowd-heavy::before    { background: #dc2626; }
        .verified-badge {
            display: inline-flex; align-items: center; gap: 0.25rem;
            font-size: 0.68rem; font-weight: 700; padding: 0.15rem 0.5rem;
            border-radius: 999px; background: rgba(34,51,92,0.08); color: var(--navy);
        }
        .verified-badge::before {
            content: ''; width: 5px; height: 5px;
            border-radius: 50%; background: var(--gold); flex-shrink: 0;
        }

        /* ── Buttons ──────────────────────────────────────── */
        .btn-location {
            display: flex; align-items: center; justify-content: center; gap: 0.45rem;
            width: 100%;
            background: var(--gold); color: var(--navy-deep);
            font-weight: 700; font-size: 0.825rem;
            padding: 0.65rem 1rem; border-radius: 0.6rem;
            border: none; cursor: pointer;
            box-shadow: 0 4px 14px rgba(251,192,97,0.32);
            transition: all 0.2s; font-family: 'Inter', sans-serif;
        }
        .btn-location:hover { background: var(--gold-dark); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(251,192,97,0.42); }
        .btn-location:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        .btn-navy {
            display: block; width: 100%; text-align: center;
            background: var(--navy); color: #fff;
            font-weight: 700; font-size: 0.825rem;
            padding: 0.65rem 1rem; border-radius: 0.6rem;
            text-decoration: none; transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }
        .btn-navy:hover { background: var(--navy-mid); transform: translateY(-1px); }

        .btn-outline {
            display: block; width: 100%; text-align: center;
            background: transparent; color: var(--navy);
            font-weight: 600; font-size: 0.825rem;
            padding: 0.6rem 1rem; border-radius: 0.6rem;
            border: 1.5px solid rgba(34,51,92,0.25);
            text-decoration: none; transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }
        .btn-outline:hover { background: rgba(34,51,92,0.06); border-color: rgba(34,51,92,0.4); }

        /* ── Divider ──────────────────────────────────────── */
        .panel-divider {
            border: none; border-top: 1px solid rgba(34,51,92,0.08);
            margin: 0.75rem 0;
        }

        /* ── Map ──────────────────────────────────────────── */
        #map {
            position: relative !important;
            z-index: 1 !important;
        }
        .leaflet-container {
            position: relative !important;
            z-index: 1 !important;
        }

        @keyframes pulse {
            0%   { transform: scale(1);   opacity: 1; }
            50%  { transform: scale(1.1); opacity: 0.8; }
            100% { transform: scale(1);   opacity: 1; }
        }
        .verifiable-marker { animation: pulse 2s infinite; }
        .selected-marker   { animation: pulse 2s infinite; }

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

        /* ── Scroll Reveal ────────────────────────────────── */
        .reveal {
            opacity: 0; transform: translateY(20px);
            transition: opacity 0.55s cubic-bezier(.4,0,.2,1), transform 0.55s cubic-bezier(.4,0,.2,1);
        }
        .reveal.visible { opacity: 1; transform: none; }

        /* ── Toast ───────────────────────────────────────── */
        .toast {
            pointer-events: auto;
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            min-width: 280px;
            max-width: 380px;
            padding: 0.85rem 0.95rem;
            border-radius: 0.9rem;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(34,51,92,0.10);
            box-shadow: 0 14px 38px rgba(15,28,54,0.18);
            transform: translateY(-6px);
            opacity: 0;
            transition: transform 180ms ease, opacity 180ms ease;
        }
        .toast.show { transform: translateY(0); opacity: 1; }
        .toast-ico {
            width: 2.05rem; height: 2.05rem;
            border-radius: 0.75rem;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            color: #fff;
            box-shadow: 0 6px 18px rgba(0,0,0,0.18);
        }
        .toast-ico.success { background: linear-gradient(135deg,#16a34a,#22c55e); }
        .toast-ico.error   { background: linear-gradient(135deg,#dc2626,#ef4444); }
        .toast-ico.info    { background: linear-gradient(135deg,#22335C,#5B7B99); }
        .toast-title { font-weight: 800; color: #0f1c36; font-size: 0.86rem; line-height: 1.15; }
        .toast-msg { margin-top: 0.15rem; color: #475569; font-size: 0.8rem; line-height: 1.35; }
        .toast-close {
            margin-left: auto;
            border: none;
            background: transparent;
            cursor: pointer;
            color: #94a3b8;
            padding: 0.15rem;
            border-radius: 0.5rem;
        }
        .toast-close:hover { background: rgba(34,51,92,0.06); color: #334155; }
    </style>
</head>
<body>

<!-- Toasts -->
<div id="toastHost" style="position:fixed;top:1rem;right:1rem;z-index:1000;display:flex;flex-direction:column;gap:0.6rem;pointer-events:none;"></div>

<!-- ════════════════ FLOATING NAV ════════════════ -->
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
                    <a href="reports_map.php" class="nav-link active">Reports Map</a>
                    <a href="routes.php"      class="nav-link">Routes</a>
                </div>
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
                    <a href="reports_map.php" class="nav-link-mobile active">Reports Map</a>
                    <a href="routes.php"      class="nav-link-mobile">Routes</a>
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

<!-- ════════════════ PAGE HEADER ════════════════ -->
<section class="page-header pt-20">
    <div class="header-grid"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 relative z-10">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <div class="page-badge">
                    <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                    </svg>
                    Live Data
                </div>
                <h1 class="page-title">
                    Reports <span class="gold">Map</span>
                </h1>
                <p class="page-subtitle">
                    Real-time crowdsourced crowd and delay reports across Metro Manila routes.
                </p>
            </div>
            <!-- Report count pill -->
            <div style="display:inline-flex;align-items:center;gap:0.5rem;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.18);border-radius:999px;padding:0.4rem 1rem;flex-shrink:0;">
                <span style="width:8px;height:8px;border-radius:50%;background:var(--gold);flex-shrink:0;"></span>
                <span style="color:#cbd5e1;font-size:0.8rem;font-weight:600;"><?= count(
                    $reports,
                ) ?> report<?= count($reports) !== 1 ? "s" : "" ?> loaded</span>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════ MAIN CONTENT ════════════════ -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-5 items-start">

        <!-- ── Map Panel ──────────────────────────── -->
        <div class="lg:col-span-3 glass-card overflow-hidden reveal">
            <!-- Map Header -->
            <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                 style="border-bottom:1px solid rgba(34,51,92,0.08);">
                <div>
                    <div class="sec-eyebrow mb-1">Live Map</div>
                    <p style="font-size:0.82rem;color:#64748b;line-height:1.5;">
                        Tap a marker to see report details.
                        <span style="display:inline-flex;align-items:center;gap:0.25rem;">
                            <span style="width:9px;height:9px;border-radius:50%;border:2px solid #16a34a;display:inline-block;"></span>
                            <span>Green border = verified.</span>
                        </span>
                        Blue circle = your 500m verification radius.
                    </p>
                </div>
                <!-- Live indicator -->
                <div style="display:inline-flex;align-items:center;gap:0.4rem;background:rgba(22,163,74,0.1);border:1px solid rgba(22,163,74,0.25);border-radius:999px;padding:0.3rem 0.8rem;flex-shrink:0;">
                    <span style="width:7px;height:7px;border-radius:50%;background:#16a34a;animation:pulse 2s infinite;"></span>
                    <span style="font-size:0.72rem;font-weight:700;color:#166534;letter-spacing:0.05em;text-transform:uppercase;">Live</span>
                </div>
            </div>
            <div id="map" style="height:560px;"></div>
        </div>

        <!-- ── Sidebar ────────────────────────────── -->
        <div class="flex flex-col gap-4 reveal" style="transition-delay:0.1s;">

            <!-- Route Filter -->
            <div class="glass-card p-4">
                <label for="categoryFilter" class="form-label">Choose Category</label>
                <select id="categoryFilter" class="form-select" <?= !$hasVehicleCategory
                    ? "disabled"
                    : "" ?>>
                    <option value="">All Categories</option>
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
                <?php if (!$hasVehicleCategory): ?>
                    <p style="font-size:0.72rem;color:#94a3b8;line-height:1.4;margin-top:0.5rem;">
                        Category filter is unavailable until you run the latest DB update.
                    </p>
                <?php endif; ?>

                <hr class="panel-divider">

                <label for="routeFilter" class="form-label">Choose Route</label>
                <select id="routeFilter" class="form-select">
                    <option value="">All Routes</option>
                    <?php foreach ($availableRoutes as $route): ?>
                        <option value="<?= htmlspecialchars($route) ?>"
                            <?= $selectedRoute === $route ? "selected" : "" ?>>
                            <?= htmlspecialchars($route) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Legend -->
            <div class="glass-card p-4">
                <div class="sec-eyebrow mb-3">Legend</div>
                <div class="flex flex-col gap-2">
                    <div class="flex items-center gap-2">
                        <span class="legend-dot" style="background:#16a34a;"></span>
                        <span class="legend-label">Light crowd</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="legend-dot" style="background:#ca8a04;"></span>
                        <span class="legend-label">Moderate crowd</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="legend-dot" style="background:#dc2626;"></span>
                        <span class="legend-label">Heavy crowd</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="legend-square" style="border:2px solid #16a34a;background:transparent;"></span>
                        <span class="legend-label">Verified report</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="legend-dot" style="background:#22335C;border-color:white;"></span>
                        <span class="legend-label">Your location</span>
                    </div>
                </div>
            </div>

            <!-- Reports List -->
            <div class="glass-card p-4">
                <div class="sec-eyebrow mb-3">Recent Reports</div>
                <div id="reportsList" class="flex flex-col gap-2 overflow-y-auto" style="max-height:320px;">
                    <!-- Populated by JS -->
                </div>
            </div>

            <!-- Verification Panel -->
            <div class="glass-card p-4">
                <div class="sec-eyebrow mb-3">Verification</div>
                <?php if ($is_logged_in): ?>
                    <p style="font-size:0.78rem;color:#64748b;line-height:1.55;margin-bottom:0.85rem;">
                        <strong style="color:var(--navy);">How to verify:</strong><br>
                        1. Click <em>Enable My Location</em> below.<br>
                        2. Be within 500m of the report.<br>
                        3. Click Verify on the map popup.<br>
                        <em style="color:#94a3b8;">Commuter accounts only.</em>
                    </p>
                    <button id="enableLocationBtn" class="btn-location">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="3"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 2v3m0 14v3M2 12h3m14 0h3"/>
                        </svg>
                        Enable My Location
                    </button>
                    <p id="locationStatus" style="font-size:0.75rem;color:#64748b;margin-top:0.6rem;min-height:1em;line-height:1.5;"></p>
                <?php else: ?>
                    <p style="font-size:0.78rem;color:#64748b;line-height:1.55;margin-bottom:0.85rem;">
                        Want to verify crowd reports and help the community?
                    </p>
                    <a href="login.php" class="btn-navy mb-2">Login to Verify Reports</a>
                    <a href="register.php" class="btn-outline">Create a Free Account</a>
                <?php endif; ?>
            </div>

        </div><!-- /sidebar -->
    </div><!-- /grid -->
</div>

<!-- ════════════════ FOOTER ════════════════ -->
<footer class="site-footer">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 relative z-10">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="footer-logo">Transport<span>Ops</span></div>
            <div class="flex flex-wrap gap-5 justify-center">
                <a href="<?= $is_logged_in
                    ? "user_dashboard.php"
                    : "index.php" ?>" class="footer-link">Home</a>
                <a href="about.php"       class="footer-link">About</a>
                <?php if ($is_logged_in): ?>
                <a href="report.php"      class="footer-link">Submit Report</a>
                <?php endif; ?>
                <a href="reports_map.php" class="footer-link" style="color:var(--gold);">Reports Map</a>
                <a href="routes.php"      class="footer-link">Routes</a>
            </div>
            <p style="color:#475569;font-size:0.78rem;white-space:nowrap;">
                &copy; <?= date("Y") ?> Transport Ops
            </p>
        </div>
    </div>
</footer>

<!-- ════════════════ SCRIPTS ════════════════ -->
<script>
    const reports    = <?= json_encode($reports) ?>;
    const userRole   = <?= json_encode(
        $is_logged_in ? $_SESSION["role"] ?? "" : "guest",
    ) ?>;
    const isLoggedIn = <?= $is_logged_in ? "true" : "false" ?>;
    let selectedReportId = null;
    let reportMarkers    = new Map();

    const map = L.map('map').setView([14.5995, 120.9842], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let userLocation      = null;
    let userLocationMarker = null;
    let verificationCircle = null;

    function getIconColor(crowdLevel) {
        if (crowdLevel === 'Light')    return '#16a34a';
        if (crowdLevel === 'Moderate') return '#ca8a04';
        return '#dc2626';
    }

    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R    = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a    = Math.sin(dLat/2) * Math.sin(dLat/2) +
                     Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                     Math.sin(dLon/2) * Math.sin(dLon/2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function highlightReport(reportId) {
        document.querySelectorAll('.report-item').forEach(function (item) {
            item.classList.remove('active');
        });
        const el = document.querySelector('[data-report-id="' + reportId + '"]');
        if (el) {
            el.classList.add('active');
            el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        if (reportMarkers.has(reportId)) {
            reportMarkers.get(reportId).openPopup();
            const r = reports.find(function (r) { return r.id === reportId; });
            if (r && r.latitude && r.longitude) {
                map.setView([parseFloat(r.latitude), parseFloat(r.longitude)], 14);
            }
        }
    }

    function crowdBadgeClass(level) {
        if (level === 'Light')    return 'crowd-badge crowd-light';
        if (level === 'Moderate') return 'crowd-badge crowd-moderate';
        return 'crowd-badge crowd-heavy';
    }

    function renderReportsList() {
        const list = document.getElementById('reportsList');
        list.innerHTML = '';
        if (reports.length === 0) {
            list.innerHTML = '<p style="font-size:0.82rem;color:#94a3b8;text-align:center;padding:1.5rem 0;">No reports found.</p>';
            return;
        }
        reports.forEach(function (report) {
            const isVerified  = parseInt(report.is_verified, 10) === 1;
            const isSelected  = report.id === selectedReportId;
            const timestamp   = new Date(report.timestamp).toLocaleString();
            const color       = getIconColor(report.crowd_level);

            const el = document.createElement('div');
            el.className = 'report-item' + (isSelected ? ' active' : '');
            el.setAttribute('data-report-id', report.id);

            if (isSelected) {
                el.innerHTML = `
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.5rem;margin-bottom:0.6rem;">
                        <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                            <span style="width:10px;height:10px;border-radius:50%;background:${color};border:2px solid rgba(255,255,255,0.8);box-shadow:0 1px 3px rgba(0,0,0,0.2);flex-shrink:0;"></span>
                            <span style="font-size:0.78rem;font-weight:700;color:var(--navy);">#${report.id}</span>
                            ${isVerified ? '<span class="verified-badge">Verified</span>' : ''}
                        </div>
                        <span style="font-size:0.72rem;color:#94a3b8;white-space:nowrap;">${report.peer_verifications || 0}/3</span>
                    </div>
                    <div style="font-weight:700;font-size:0.88rem;color:var(--navy);margin-bottom:0.4rem;">${report.route_name || 'Unknown Route'}</div>
                    <div style="margin-bottom:0.4rem;"><span class="${crowdBadgeClass(report.crowd_level)}">${report.crowd_level}</span></div>
                    ${report.delay_reason ? `<div style="font-size:0.75rem;color:#64748b;background:rgba(34,51,92,0.04);border-radius:0.4rem;padding:0.4rem 0.6rem;margin-bottom:0.4rem;"><strong>Delay:</strong> ${report.delay_reason}</div>` : ''}
                    <div style="font-size:0.75rem;color:#64748b;line-height:1.6;">
                        <div><strong style="color:var(--navy);">By:</strong> ${report.user_name || 'Anonymous'}</div>
                        <div>${timestamp}</div>
                        <div><strong style="color:var(--navy);">Status:</strong> ${isVerified ? 'Verified ✓' : 'Pending'}</div>
                    </div>
                    ${userRole === 'Commuter' && !isVerified ? `
                        <div style="margin-top:0.6rem;padding-top:0.6rem;border-top:1px solid rgba(34,51,92,0.08);">
                            <button class="verify-on-map-btn" style="width:100%;background:var(--navy);color:#fff;font-size:0.78rem;font-weight:700;padding:0.5rem 0.8rem;border-radius:0.5rem;border:none;cursor:pointer;">
                                📍 View on Map to Verify
                            </button>
                        </div>` : ''}
                `;
            } else {
                el.innerHTML = `
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.5rem;margin-bottom:0.35rem;">
                        <div style="display:flex;align-items:center;gap:0.4rem;">
                            <span style="width:9px;height:9px;border-radius:50%;background:${color};border:2px solid rgba(255,255,255,0.8);box-shadow:0 1px 3px rgba(0,0,0,0.2);flex-shrink:0;"></span>
                            <span style="font-size:0.72rem;font-weight:700;color:var(--navy);">#${report.id}</span>
                            ${isVerified ? '<span class="verified-badge">Verified</span>' : ''}
                        </div>
                        <span style="font-size:0.68rem;color:#94a3b8;">${report.peer_verifications || 0}/3</span>
                    </div>
                    <div style="font-weight:600;font-size:0.82rem;color:var(--navy);margin-bottom:0.3rem;">${report.route_name || 'Unknown Route'}</div>
                    <div style="margin-bottom:0.25rem;"><span class="${crowdBadgeClass(report.crowd_level)}">${report.crowd_level}</span></div>
                    <div style="font-size:0.72rem;color:#94a3b8;">${report.user_name || 'Anonymous'} &bull; ${new Date(report.timestamp).toLocaleDateString()}</div>
                `;
            }

            el.addEventListener('click', function () {
                selectedReportId = report.id;
                highlightReport(report.id);
            });
            list.appendChild(el);
        });
    }

    function addReportMarkers() {
        reportMarkers.forEach(function (m) { map.removeLayer(m); });
        reportMarkers.clear();
        const bounds = [];
        reports.forEach(function (r) {
            if (!r.latitude || !r.longitude) return;
            const lat        = parseFloat(r.latitude);
            const lng        = parseFloat(r.longitude);
            const color      = getIconColor(r.crowd_level);
            const isVerified = parseInt(r.is_verified, 10) === 1;
            const isSelected = r.id === selectedReportId;
            const borderColor = isVerified ? '#16a34a' : (isSelected ? 'var(--gold)' : 'white');
            const iconSize    = isSelected ? 28 : 20;

            const marker = L.marker([lat, lng], {
                icon: L.divIcon({
                    className: isSelected ? 'selected-marker' : 'custom-marker',
                    html: `<div style="background:${color};width:${iconSize}px;height:${iconSize}px;border-radius:50%;border:3px solid ${borderColor};box-shadow:0 2px 6px rgba(0,0,0,0.3);${isSelected ? 'animation:pulse 2s infinite;' : ''}"></div>`,
                    iconSize: [iconSize, iconSize]
                })
            }).addTo(map);

            const ts = new Date(r.timestamp).toLocaleString();
            marker.bindPopup(`
                <div style="font-family:'Inter',sans-serif;font-size:0.82rem;min-width:180px;line-height:1.6;">
                    <div style="font-weight:700;color:#22335C;margin-bottom:0.3rem;">${r.route_name || 'N/A'}</div>
                    <div style="margin-bottom:0.25rem;color:#475569;"><strong>Crowd:</strong> ${r.crowd_level}</div>
                    <div style="color:#475569;"><strong>By:</strong> ${r.user_id ? `<a href="public_profile.php?id=${r.user_id}" style="color:#22335C;font-weight:600;">${r.user_name || 'Unknown'}</a>` : (r.user_name || 'Unknown')}</div>
                    ${r.user_id ? `<span style="display:inline-block;background:rgba(34,51,92,0.08);color:#22335C;font-size:0.7rem;font-weight:700;padding:0.15rem 0.5rem;border-radius:999px;margin:0.2rem 0;">${r.trust_badge.label} (${r.trust_score})</span>` : ''}
                    <div style="color:#64748b;font-size:0.75rem;">${ts}</div>
                    <div style="color:#64748b;"><strong>Verified:</strong> ${isVerified ? '<span style="color:#16a34a;">Yes ✓</span>' : 'No'} (${r.peer_verifications || 0}/3)</div>
                    ${r.delay_reason ? `<div style="color:#64748b;"><strong>Delay:</strong> ${r.delay_reason}</div>` : ''}
                </div>
            `);

            marker.on('click', function () {
                selectedReportId = r.id;
                highlightReport(r.id);
            });
            reportMarkers.set(r.id, marker);
            bounds.push([lat, lng]);
        });
        if (bounds.length > 0 && !selectedReportId) {
            map.fitBounds(bounds, { padding: [40, 40] });
        }
    }

    function updateUserLocationOnMap(lat, lng) {
        if (userLocationMarker) map.removeLayer(userLocationMarker);
        if (verificationCircle)  map.removeLayer(verificationCircle);
        userLocationMarker = L.marker([lat, lng], {
            icon: L.divIcon({
                className: 'user-location-marker',
                html: `<div style="background:#22335C;width:16px;height:16px;border-radius:50%;border:3px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.35);z-index:1000;"></div>`,
                iconSize: [16, 16]
            })
        }).addTo(map);
        verificationCircle = L.circle([lat, lng], {
            radius: 500, color: '#FBC061', fillColor: '#FBC061',
            fillOpacity: 0.08, weight: 2, dashArray: '6, 10'
        }).addTo(map);
        updateReportMarkersWithDistance();
    }

    function updateReportMarkersWithDistance() {
        if (!userLocation) return;
        reportMarkers.forEach(function (m) { map.removeLayer(m); });
        reportMarkers.clear();
        reports.forEach(function (r) {
            if (!r.latitude || !r.longitude) return;
            const lat        = parseFloat(r.latitude);
            const lng        = parseFloat(r.longitude);
            const color      = getIconColor(r.crowd_level);
            const isVerified = parseInt(r.is_verified, 10) === 1;
            const distance   = calculateDistance(userLocation.latitude, userLocation.longitude, lat, lng);
            const canVerify  = distance <= 0.5;
            const borderColor = isVerified ? '#16a34a' : (canVerify ? '#FBC061' : 'white');
            const iconSize    = canVerify ? 24 : 20;

            const marker = L.marker([lat, lng], {
                icon: L.divIcon({
                    className: 'custom-marker',
                    html: `<div style="background:${color};width:${iconSize}px;height:${iconSize}px;border-radius:50%;border:3px solid ${borderColor};box-shadow:0 2px 6px rgba(0,0,0,0.3);"></div>`,
                    iconSize: [iconSize, iconSize]
                })
            }).addTo(map);

            reportMarkers.set(r.id, marker);
            const ts = new Date(r.timestamp).toLocaleString();
            const verifyButton = !isLoggedIn
                ? `<a href="login.php" style="display:inline-block;margin-top:0.4rem;padding:0.3rem 0.7rem;font-size:0.72rem;background:#22335C;color:#fff;border-radius:0.4rem;text-decoration:none;font-weight:700;">Login to verify</a>`
                : (userRole === 'Commuter')
                    ? (canVerify
                        ? `<button data-report-id="${r.id}" class="verify-btn" style="margin-top:0.4rem;padding:0.3rem 0.7rem;font-size:0.72rem;background:#FBC061;color:#0f1c36;border-radius:0.4rem;border:none;cursor:pointer;font-weight:700;">Verify (${distance.toFixed(1)}km away)</button>`
                        : (userLocation
                            ? `<span style="font-size:0.72rem;color:#94a3b8;margin-top:0.3rem;display:block;">Too far (${distance.toFixed(1)}km — must be &lt;0.5km)</span>`
                            : `<span style="font-size:0.72rem;color:#d97706;margin-top:0.3rem;display:block;">Enable location to verify</span>`))
                    : (userRole === 'Admin'
                        ? `<span style="font-size:0.72rem;color:#94a3b8;margin-top:0.3rem;display:block;">Admins cannot verify reports</span>`
                        : `<span style="font-size:0.72rem;color:#94a3b8;margin-top:0.3rem;display:block;">Login as commuter to verify</span>`);

            marker.bindPopup(`
                <div style="font-family:'Inter',sans-serif;font-size:0.82rem;min-width:190px;line-height:1.6;">
                    <div style="font-weight:700;color:#22335C;margin-bottom:0.3rem;">${r.route_name || 'N/A'}</div>
                    <div style="color:#475569;"><strong>Crowd:</strong> ${r.crowd_level}</div>
                    <div style="color:#475569;"><strong>By:</strong> ${r.user_id ? `<a href="public_profile.php?id=${r.user_id}" style="color:#22335C;font-weight:600;">${r.user_name || 'Unknown'}</a>` : (r.user_name || 'Unknown')}</div>
                    ${r.user_id ? `<span style="display:inline-block;background:rgba(34,51,92,0.08);color:#22335C;font-size:0.7rem;font-weight:700;padding:0.15rem 0.5rem;border-radius:999px;">${r.trust_badge.label} (${r.trust_score})</span>` : ''}
                    <div style="color:#64748b;font-size:0.75rem;">${ts}</div>
                    <div><strong>Verified:</strong> ${isVerified ? '<span style="color:#16a34a;">Yes ✓</span>' : 'No'} (${r.peer_verifications || 0}/3)</div>
                    ${r.delay_reason ? `<div style="color:#64748b;"><strong>Delay:</strong> ${r.delay_reason}</div>` : ''}
                    <div style="margin-top:0.3rem;">${canVerify ? '<span style="color:#16a34a;font-weight:600;">✓ Within 500m — can verify</span>' : (userLocation ? '<span style="color:#dc2626;">✗ Too far to verify</span>' : '<span style="color:#d97706;">⚠ Enable location</span>')}</div>
                    ${verifyButton}
                </div>
            `);
        });
    }

    renderReportsList();
    addReportMarkers();

    /* Route filter */
    const categoryFilter = document.getElementById('categoryFilter');
    const routeFilter = document.getElementById('routeFilter');
    if (categoryFilter && !categoryFilter.disabled) {
        categoryFilter.addEventListener('change', function (e) {
            const url = new URL(window.location);
            if (e.target.value) url.searchParams.set('category', e.target.value);
            else url.searchParams.delete('category');
            // Reset route whenever category changes to avoid mismatched selection
            url.searchParams.delete('route');
            window.location.href = url.toString();
        });
    }
    if (routeFilter) {
        routeFilter.addEventListener('change', function (e) {
            const url = new URL(window.location);
            if (e.target.value) url.searchParams.set('route', e.target.value);
            else url.searchParams.delete('route');
            window.location.href = url.toString();
        });
    }

    /* Location / verification */
    const enableLocationBtn = document.getElementById('enableLocationBtn');
    const locationStatus    = document.getElementById('locationStatus');
    if (!isLoggedIn && enableLocationBtn) enableLocationBtn.disabled = true;
    if (enableLocationBtn) {
        enableLocationBtn.addEventListener('click', function () {
            if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
                if (locationStatus) {
                    locationStatus.textContent = 'Location requires HTTPS or localhost.';
                    locationStatus.style.color = '#dc2626';
                }
                return;
            }
            if (!isLoggedIn) { window.location.href = 'login.php'; return; }
            if (!navigator.geolocation) {
                locationStatus.textContent = 'Geolocation not supported.';
                locationStatus.style.color = '#dc2626';
                return;
            }
            locationStatus.textContent = 'Getting your location\u2026';
            locationStatus.style.color = '#64748b';
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    userLocation = { latitude: pos.coords.latitude, longitude: pos.coords.longitude };
                    updateUserLocationOnMap(userLocation.latitude, userLocation.longitude);
                    locationStatus.textContent = '\u2705 Location enabled! Gold circle = 500m radius.';
                    locationStatus.style.color = '#16a34a';
                },
                function (err) {
                    locationStatus.textContent = 'Error: ' + err.message;
                    locationStatus.style.color = '#dc2626';
                }
            );
        });
    }

    function showToast(type, title, message, opts) {
        const host = document.getElementById('toastHost');
        if (!host) return;
        const options = (typeof opts === 'number' || typeof opts === 'undefined')
            ? { timeoutMs: opts }
            : (opts || {});

        const t = document.createElement('div');
        t.className = 'toast';
        const icon = type === 'success'
            ? '✓'
            : (type === 'error' ? '!' : 'i');
        t.innerHTML =
            `<div class="toast-ico ${type}">${icon}</div>` +
            `<div style="min-width:0;">` +
              `<div class="toast-title">${title}</div>` +
              `<div class="toast-msg">${message}</div>` +
            `</div>` +
            `<button class="toast-close" aria-label="Dismiss">` +
              `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>` +
            `</button>`;
        host.appendChild(t);
        const closeBtn = t.querySelector('.toast-close');
        let removed = false;
        const remove = (trigger) => {
            if (removed) return;
            removed = true;
            t.classList.remove('show');
            setTimeout(() => {
                if (t && t.parentNode) t.parentNode.removeChild(t);
                if (trigger === 'close' && typeof options.onClose === 'function') {
                    options.onClose();
                }
            }, 180);
        };
        if (closeBtn) closeBtn.addEventListener('click', () => remove('close'));
        setTimeout(() => t.classList.add('show'), 0);
        const timeoutMs = options.timeoutMs;
        if (typeof timeoutMs === 'number' && timeoutMs > 0) {
            setTimeout(() => remove('timeout'), timeoutMs);
        }
    }

    document.addEventListener('click', async function (e) {
        if (!e.target.classList.contains('verify-btn')) return;
        if (!userLocation) { showToast('info', 'Enable location', 'Turn on your location to verify reports within 500m.', 4500); return; }
        const btn = e.target;
        const reportId = btn.getAttribute('data-report-id');
        try {
            btn.disabled = true;
            btn.style.opacity = '0.85';
            btn.textContent = 'Verifying…';
            const fd = new FormData();
            fd.append('report_id', reportId);
            fd.append('latitude',  userLocation.latitude);
            fd.append('longitude', userLocation.longitude);
            const res  = await fetch('verify_report.php', { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await res.json();
            if (!res.ok || !data.success) {
                showToast('error', 'Verification failed', (data && data.error) ? data.error : 'Please try again.', 5200);
                btn.disabled = false;
                btn.style.opacity = '';
                btn.textContent = 'Verify';
                return;
            }

            const points = (data && typeof data.verifier_points_awarded === 'number') ? data.verifier_points_awarded : 1;
            const pv = (data && typeof data.peer_verifications === 'number') ? data.peer_verifications : 0;
            const fully = !!(data && data.is_verified);
            const scoreText = (data && (data.verifier_new_trust_score !== null && data.verifier_new_trust_score !== undefined))
                ? ` Your trust score is now ${Number(data.verifier_new_trust_score).toFixed(1)}.`
                : '';
            showToast(
                'success',
                'Verified successfully',
                `+${points} trust point for verifying. Verifications: ${pv}/3. Fully verified: ${fully ? 'Yes' : 'No'}.${scoreText}`,
                { timeoutMs: 0, onClose: () => window.location.reload() }
            );

            btn.textContent = fully ? `Verified ✓ (${pv}/3)` : `Verified (${pv}/3)`;
        } catch (err) {
            console.error(err);
            showToast('error', 'Something went wrong', 'An error occurred while verifying. Please try again.', 5200);
            if (e.target) {
                e.target.disabled = false;
                e.target.style.opacity = '';
                e.target.textContent = 'Verify';
            }
        }
    });

    /* Nav + UI */
    (function () {
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

        var btn  = document.getElementById('profileMenuButton');
        var menu = document.getElementById('profileMenu');
        if (btn && menu) {
            btn.addEventListener('click', function (e) { e.stopPropagation(); menu.classList.toggle('hidden'); });
            document.addEventListener('click', function () { if (menu) menu.classList.add('hidden'); });
        }

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

        /* Scroll reveal */
        var revealEls = document.querySelectorAll('.reveal');
        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (e.isIntersecting) { e.target.classList.add('visible'); io.unobserve(e.target); }
                });
            }, { threshold: 0.06 });
            revealEls.forEach(function (el) { io.observe(el); });
        } else {
            revealEls.forEach(function (el) { el.classList.add('visible'); });
        }
    })();
</script>
</body>
</html>
