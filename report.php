<?php
/**
 * Commuter Reporting Interface
 * Allows users to submit real-time crowding and delay reports
 */

require_once "auth_helper.php";
secureSessionStart();
require_once "db.php";

// Check if user is logged in - redirect guests with a message
if (!isset($_SESSION["user_id"])) {
    $_SESSION["redirect_after_login"] = "report.php";
    $_SESSION["login_message"] = "You need to be logged in to submit a report.";
    header("Location: login.php");
    exit();
}

$error = "";
$success = "";
$routes_list = [];
$hasVehicleCategory = true;

// Fetch available routes (from route_definitions with at least one stop)
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT rd.id, rd.name, rd.vehicle_category
        FROM route_definitions rd
        INNER JOIN (SELECT route_definition_id FROM route_stops GROUP BY route_definition_id) rs ON rs.route_definition_id = rd.id
        ORDER BY rd.name
    ");
    $routes_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Fetch profile image for nav
    if (isset($_SESSION["user_id"])) {
        $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
        $stmt->execute([$_SESSION["user_id"]]);
        $user_profile_row = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_profile_data = $user_profile_row ?: ["profile_image" => null];
        if ($user_profile_data["profile_image"]) {
            $_SESSION["profile_image"] = $user_profile_data["profile_image"];
        }
    } else {
        $user_profile_data = ["profile_image" => null];
    }
} catch (PDOException $e) {
    // Backwards compatibility if vehicle_category isn't present yet
    try {
        $hasVehicleCategory = false;
        $pdo = getDBConnection();
        $stmt = $pdo->query("
            SELECT rd.id, rd.name
            FROM route_definitions rd
            INNER JOIN (SELECT route_definition_id FROM route_stops GROUP BY route_definition_id) rs ON rs.route_definition_id = rd.id
            ORDER BY rd.name
        ");
        $routes_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($routes_list as &$r) {
            $r["vehicle_category"] = null;
        }
        unset($r);
    } catch (PDOException $e2) {
        error_log("Error fetching routes: " . $e2->getMessage());
    }
    $user_profile_data = ["profile_image" => null];
}

// Haversine distance in km
function distanceKm($lat1, $lng1, $lat2, $lng2)
{
    $earthRadius = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a =
        sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) *
            cos(deg2rad($lat2)) *
            sin($dLng / 2) *
            sin($dLng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

// Process report submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $route_definition_id = isset($_POST["route_definition_id"])
        ? (int) $_POST["route_definition_id"]
        : 0;
    $crowd_level = $_POST["crowd_level"] ?? "";
    $delay_reason = trim($_POST["delay_reason"] ?? "");
    $latitude =
        isset($_POST["latitude"]) && $_POST["latitude"] !== ""
            ? (float) $_POST["latitude"]
            : null;
    $longitude =
        isset($_POST["longitude"]) && $_POST["longitude"] !== ""
            ? (float) $_POST["longitude"]
            : null;

    if ($route_definition_id <= 0 || empty($crowd_level)) {
        $error = "Please select a route and crowd level.";
    } elseif (!in_array($crowd_level, ["Light", "Moderate", "Heavy"])) {
        $error = "Invalid crowd level selected.";
    } elseif ($latitude === null || $longitude === null) {
        $error =
            "Please set your location on the map or use GPS so we can confirm you are on or near the route.";
    } else {
        try {
            $pdo = getDBConnection();

            $stmt = $pdo->prepare(
                "SELECT id, name FROM route_definitions WHERE id = ?",
            );
            $stmt->execute([$route_definition_id]);
            $route = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$route) {
                $error =
                    "Selected route not found. Please select a valid route.";
            } else {
                $stmt = $pdo->prepare(
                    "SELECT latitude, longitude FROM route_stops WHERE route_definition_id = ? ORDER BY stop_order",
                );
                $stmt->execute([$route_definition_id]);
                $stops = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($stops)) {
                    $error =
                        "This route has no stops defined. Report cannot be validated.";
                } else {
                    $minDist = null;
                    foreach ($stops as $stop) {
                        $d = distanceKm(
                            $latitude,
                            $longitude,
                            (float) $stop["latitude"],
                            (float) $stop["longitude"],
                        );
                        if ($minDist === null || $d < $minDist) {
                            $minDist = $d;
                        }
                    }
                    $thresholdKm = 0.5;
                    if ($minDist > $thresholdKm) {
                        $error =
                            "Your location must be on or near the selected route (within about 500 m of a stop) to submit a report. Current distance to nearest stop: " .
                            number_format($minDist * 1000, 0) .
                            " m.";
                    } else {
                        $geofence_validated = 1;
                        $trust_score = 1.0;

                        $stmt = $pdo->prepare("
                            INSERT INTO reports (user_id, route_definition_id, crowd_level, delay_reason, latitude, longitude, geofence_validated, trust_score)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $_SESSION["user_id"],
                            $route_definition_id,
                            $crowd_level,
                            $delay_reason ?: null,
                            $latitude,
                            $longitude,
                            $geofence_validated,
                            $trust_score,
                        ]);

                        // Update user's trust score for submitting a report
                        require_once "trust_helper.php";
                        // Recalculate trust score after submitting (computed from overall history)
                        updateUserTrustScore($_SESSION["user_id"], "Trust score recalculated (report submitted)");

                        $success =
                            "Report submitted successfully! Thank you for your contribution.";
                        $_POST = [];
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Report submission error: " . $e->getMessage());
            $error = "Failed to submit report. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Report — Transport Ops</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Poppins:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="js/osrm-helpers.js"></script>
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
        .hero-orb { position: absolute; border-radius: 50%; pointer-events: none; }
        .hero-orb-1 {
            width: 480px; height: 480px; top: -160px; right: -80px;
            background: radial-gradient(circle, rgba(91,123,153,0.2) 0%, transparent 70%);
            animation: orbPulse 18s ease-in-out infinite alternate;
        }
        .hero-orb-2 {
            width: 300px; height: 300px; bottom: -100px; left: 8%;
            background: radial-gradient(circle, rgba(251,192,97,0.09) 0%, transparent 70%);
            animation: orbPulse 24s ease-in-out infinite alternate-reverse;
        }
        @keyframes orbPulse {
            from { transform: translate(0,0) scale(1); }
            to   { transform: translate(28px,18px) scale(1.08); }
        }

        .page-badge {
            display: inline-flex; align-items: center; gap: 0.4rem;
            background: rgba(251,192,97,0.15); border: 1px solid rgba(251,192,97,0.35);
            color: var(--gold); border-radius: 999px;
            padding: 0.28rem 0.9rem; font-size: 0.75rem; font-weight: 600;
            letter-spacing: 0.06em; text-transform: uppercase; margin-bottom: 1rem;
        }
        .hero-title {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(1.75rem, 4vw, 2.75rem);
            font-weight: 800; color: #fff;
            line-height: 1.15; letter-spacing: -0.02em;
        }
        .hero-title .gold { color: var(--gold); }
        .hero-subtitle {
            color: #94a3b8; font-size: 0.975rem; line-height: 1.7;
            max-width: 520px; margin-top: 0.75rem;
        }

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
            font-size: 1.5rem; font-weight: 800; color: var(--navy);
            letter-spacing: -0.02em; margin-top: 0.25rem; margin-bottom: 0.35rem;
        }

        /* ── Form Elements ────────────────────────────────── */
        .form-label {
            display: block;
            font-size: 0.85rem; font-weight: 600; color: var(--navy);
            margin-bottom: 0.5rem;
        }
        .form-label .opt {
            font-weight: 400; color: var(--slate); font-size: 0.8rem;
        }
        .form-select {
            width: 100%;
            padding: 0.7rem 2.5rem 0.7rem 1rem;
            background: rgba(255,255,255,0.92);
            border: 1.5px solid rgba(34,51,92,0.15);
            border-radius: 0.65rem;
            font-size: 0.9rem; font-weight: 500;
            color: var(--navy);
            appearance: none; -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2322335C' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.8rem center;
            background-size: 1rem;
            cursor: pointer;
            transition: border-color 0.2s, box-shadow 0.2s;
            font-family: 'Inter', sans-serif;
        }
        .form-select:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(251,192,97,0.22);
        }

        /* ── Crowd Level Cards ────────────────────────────── */
        .crowd-card {
            position: relative;
            display: flex; flex-direction: column; justify-content: space-between;
            padding: 1rem 1rem 0.85rem;
            background: rgba(255,255,255,0.92);
            border: 2px solid rgba(34,51,92,0.1);
            border-radius: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            min-height: 88px;
            user-select: none;
        }
        .crowd-card:hover {
            border-color: rgba(34,51,92,0.28);
            box-shadow: 0 4px 16px rgba(34,51,92,0.1);
            transform: translateY(-2px);
        }
        .crowd-card input[type="radio"] {
            position: absolute; opacity: 0; width: 0; height: 0;
        }
        .crowd-card.active-light    { border-color: #16a34a; background: #f0fdf4; box-shadow: 0 0 0 3px rgba(22,163,74,0.15); transform: translateY(-2px); }
        .crowd-card.active-moderate { border-color: #ca8a04; background: #fefce8; box-shadow: 0 0 0 3px rgba(202,138,4,0.15);  transform: translateY(-2px); }
        .crowd-card.active-heavy    { border-color: #dc2626; background: #fef2f2; box-shadow: 0 0 0 3px rgba(220,38,38,0.15);  transform: translateY(-2px); }

        .crowd-dot {
            width: 11px; height: 11px; border-radius: 50%;
            border: 2px solid currentColor; flex-shrink: 0;
            transition: background 0.15s;
        }
        .crowd-card.active-light    .crowd-dot-light    { background: #16a34a; }
        .crowd-card.active-moderate .crowd-dot-moderate { background: #ca8a04; }
        .crowd-card.active-heavy    .crowd-dot-heavy    { background: #dc2626; }

        /* ── Location Panel ───────────────────────────────── */
        .location-panel {
            background: rgba(34,51,92,0.04);
            border: 1.5px solid rgba(34,51,92,0.1);
            border-radius: 0.85rem;
            padding: 1.1rem 1.25rem;
        }

        /* ── Buttons ──────────────────────────────────────── */
        .btn-gps {
            display: inline-flex; align-items: center; gap: 0.5rem;
            background: var(--gold); color: var(--navy-deep);
            font-weight: 700; font-size: 0.875rem;
            padding: 0.65rem 1.25rem; border-radius: 0.6rem;
            border: none; cursor: pointer;
            box-shadow: 0 4px 14px rgba(251,192,97,0.35);
            transition: all 0.2s; font-family: 'Inter', sans-serif;
        }
        .btn-gps:hover { background: var(--gold-dark); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(251,192,97,0.45); }

        .btn-submit {
            display: flex; align-items: center; justify-content: center; gap: 0.5rem;
            width: 100%;
            background: var(--gold); color: var(--navy-deep);
            font-weight: 700; font-size: 0.95rem;
            padding: 0.85rem 1.5rem; border-radius: 0.7rem;
            border: none; cursor: pointer;
            box-shadow: 0 4px 18px rgba(251,192,97,0.38);
            transition: all 0.2s; font-family: 'Inter', sans-serif;
        }
        .btn-submit:hover { background: var(--gold-dark); transform: translateY(-2px); box-shadow: 0 8px 26px rgba(251,192,97,0.48); }

        /* ── Alert Messages ───────────────────────────────── */
        .alert {
            display: flex; align-items: flex-start; gap: 0.65rem;
            padding: 0.9rem 1rem; border-radius: 0.75rem;
            font-size: 0.875rem; font-weight: 500; margin-bottom: 1.25rem;
        }
        .alert svg { flex-shrink: 0; margin-top: 0.05rem; }
        .alert-error   { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }
        .alert-success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }

        /* ── Map Panel ────────────────────────────────────── */
        .map-panel-header {
            padding: 1.1rem 1.5rem;
            border-bottom: 1px solid rgba(34,51,92,0.08);
        }
        #report-route-map {
            position: relative !important;
            z-index: 1 !important;
        }
        .leaflet-container {
            position: relative !important;
            z-index: 1 !important;
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
            opacity: 0; transform: translateY(24px);
            transition: opacity 0.6s cubic-bezier(.4,0,.2,1), transform 0.6s cubic-bezier(.4,0,.2,1);
        }
        .reveal.visible { opacity:1; transform:none; }
        .rd1 { transition-delay: 0.07s; }
        .rd2 { transition-delay: 0.14s; }
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
                    <a href="user_dashboard.php" class="nav-link">Home</a>
                    <a href="about.php"          class="nav-link">About</a>
                    <a href="report.php"         class="nav-link active">Submit Report</a>
                    <a href="reports_map.php"    class="nav-link">Reports Map</a>
                    <a href="routes.php"         class="nav-link">Routes</a>
                </div>
                <div id="mobileMenu"
                     class="md:hidden hidden absolute top-full left-0 right-0 mt-2 flex flex-col gap-1 px-4 py-3 z-20 rounded-2xl"
                     style="background:rgba(25,40,74,0.97);backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,0.12);box-shadow:0 8px 32px rgba(15,28,54,0.4);">
                    <a href="user_dashboard.php" class="nav-link-mobile">Home</a>
                    <a href="about.php"          class="nav-link-mobile">About</a>
                    <a href="report.php"         class="nav-link-mobile active">Submit Report</a>
                    <a href="reports_map.php"    class="nav-link-mobile">Reports Map</a>
                    <a href="routes.php"         class="nav-link-mobile">Routes</a>
                </div>
            </div>

            <!-- Profile -->
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
                        <?php if (
                            !empty($user_profile_data["profile_image"])
                        ): ?>
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
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 relative z-10">
        <div class="page-badge">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            Submit Report
        </div>
        <h1 class="hero-title">
            Report Transport<br>
            <span class="gold">Conditions Now.</span>
        </h1>
        <p class="hero-subtitle">
            Help improve Metro Manila transit by reporting real-time crowding levels and delays on your route. Every report strengthens the network.
        </p>
    </div>
</section>

<!-- ════════════════ MAIN CONTENT ════════════════ -->
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-20">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8 items-start">

        <!-- ── Form Card ───────────────────────────────── -->
        <div class="glass-card p-7 sm:p-8 reveal rd1">

            <div class="sec-eyebrow mb-2">New Report</div>
            <h2 class="sec-heading">Submit Report</h2>
            <p style="color:#64748b;font-size:0.9rem;line-height:1.6;margin-bottom:1.75rem;">
                Help improve transportation services by reporting crowding levels and delays in real-time.
            </p>

            <?php if ($error): ?>
            <div class="alert alert-error">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
                </svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" action="" id="reportForm" class="space-y-6" onsubmit="return validateForm()">

                <!-- Route -->
                <div>
                    <label for="categoryFilter" class="form-label">
                        Choose Category <span class="opt">(required)</span>
                    </label>
                    <select id="categoryFilter" required class="form-select" <?= !$hasVehicleCategory
                        ? "disabled"
                        : "" ?>>
                        <option value="">Please choose a category...</option>
                        <option value="tricycle">Tricycle</option>
                        <option value="jeepney">Jeepney</option>
                        <option value="rail">MRT/LRT</option>
                    </select>
                    <?php if (!$hasVehicleCategory): ?>
                        <p style="font-size:0.8rem;color:#d97706;margin-top:0.4rem;">
                            Category filter is unavailable until the latest DB update is applied.
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Route -->
                <div>
                    <label for="route_definition_id" class="form-label">
                        Choose a Route <span class="opt">(required)</span>
                    </label>
                    <select id="route_definition_id" name="route_definition_id" required class="form-select">
                        <option value="">Please choose a route...</option>
                        <?php foreach ($routes_list as $r): ?>
                            <option value="<?= (int) $r["id"] ?>"
                                    data-category="<?= htmlspecialchars(
                                        (string) ($r["vehicle_category"] ?? ""),
                                    ) ?>"
                                    data-route="<?= htmlspecialchars(
                                        $r["name"],
                                    ) ?>">
                                <?= htmlspecialchars($r["name"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($routes_list)): ?>
                        <p style="font-size:0.8rem;color:#d97706;margin-top:0.4rem;">
                            No routes available. Ask an admin to add routes in Manage Routes.
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Crowd Level -->
                <div>
                    <label class="form-label">Crowd Level</label>
                    <div class="grid grid-cols-3 gap-3">

                        <label class="crowd-card" id="card-light" for="cl-light">
                            <input type="radio" name="crowd_level" value="Light" required id="cl-light">
                            <div class="flex items-center gap-2">
                                <span class="crowd-dot crowd-dot-light" style="color:#16a34a;"></span>
                                <span style="font-weight:700;color:#16a34a;font-size:0.9rem;">Light</span>
                            </div>
                            <div style="font-size:0.75rem;color:#64748b;margin-top:0.5rem;">Comfortable seating</div>
                        </label>

                        <label class="crowd-card" id="card-moderate" for="cl-moderate">
                            <input type="radio" name="crowd_level" value="Moderate" required id="cl-moderate">
                            <div class="flex items-center gap-2">
                                <span class="crowd-dot crowd-dot-moderate" style="color:#ca8a04;"></span>
                                <span style="font-weight:700;color:#ca8a04;font-size:0.9rem;">Moderate</span>
                            </div>
                            <div style="font-size:0.75rem;color:#64748b;margin-top:0.5rem;">Limited seating</div>
                        </label>

                        <label class="crowd-card" id="card-heavy" for="cl-heavy">
                            <input type="radio" name="crowd_level" value="Heavy" required id="cl-heavy">
                            <div class="flex items-center gap-2">
                                <span class="crowd-dot crowd-dot-heavy" style="color:#dc2626;"></span>
                                <span style="font-weight:700;color:#dc2626;font-size:0.9rem;">Heavy</span>
                            </div>
                            <div style="font-size:0.75rem;color:#64748b;margin-top:0.5rem;">Crowded</div>
                        </label>

                    </div>
                </div>

                <!-- Delay Reason -->
                <div>
                    <label for="delay_reason" class="form-label">
                        Reason for Delay <span class="opt">(optional)</span>
                    </label>
                    <select id="delay_reason" name="delay_reason" class="form-select">
                        <option value="">No delay</option>
                        <option value="Traffic jam">Traffic jam / Road congestion</option>
                        <option value="Mechanical issues">Mechanical or vehicle issues</option>
                        <option value="Large number of passengers">Too many passengers</option>
                        <option value="Weather conditions">Bad weather</option>
                        <option value="Accident">Accident on route</option>
                        <option value="Other">Other (please specify below)</option>
                    </select>
                </div>

                <!-- Location -->
                <div class="location-panel">
                    <p style="font-size:0.875rem;color:#334155;font-weight:500;margin-bottom:0.75rem;">
                        <strong style="color:var(--navy);">Report location:</strong>
                        Click on the map to pin where you are (pins snap to the nearest road). Or use GPS below.
                    </p>
                    <button type="button" onclick="getLocation()" class="btn-gps">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="3"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 2v3m0 14v3M2 12h3m14 0h3"/>
                        </svg>
                        Use My Current Location
                    </button>
                    <input type="hidden" id="latitude"  name="latitude">
                    <input type="hidden" id="longitude" name="longitude">
                    <p id="locationStatus" style="font-size:0.8rem;color:#64748b;margin-top:0.6rem;min-height:1.2em;"></p>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn-submit">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Send My Report
                </button>

            </form>
        </div>

        <!-- ── Map Card ────────────────────────────────── -->
        <div class="glass-card overflow-hidden reveal rd2" style="position:sticky;top:5.5rem;">
            <div class="map-panel-header">
                <h3 style="font-family:'Poppins',sans-serif;font-size:1rem;font-weight:700;color:var(--navy);margin-bottom:0.25rem;">
                    Pin your report location
                </h3>
                <p style="font-size:0.82rem;color:#64748b;line-height:1.5;">
                    Select a route to see it on the map. Pin your location (click map or use GPS)&mdash;reports are only accepted when you're on or near the route.
                </p>
            </div>
            <div id="report-route-map" style="height:460px;"></div>
        </div>

    </div>
</main>

<!-- ════════════════ FOOTER ════════════════ -->
<footer class="site-footer">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 relative z-10">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="footer-logo">Transport<span>Ops</span></div>
            <div class="flex flex-wrap gap-5 justify-center">
                <a href="user_dashboard.php" class="footer-link">Home</a>
                <a href="about.php"          class="footer-link">About</a>
                <a href="report.php"         class="footer-link" style="color:var(--gold);">Submit Report</a>
                <a href="reports_map.php"    class="footer-link">Reports Map</a>
                <a href="routes.php"         class="footer-link">Routes</a>
            </div>
            <p style="color:#475569;font-size:0.78rem;white-space:nowrap;">
                &copy; <?= date("Y") ?> Transport Ops
            </p>
        </div>
    </div>
</footer>

<!-- ════════════════ SCRIPTS ════════════════ -->
<script>
    /* ── Crowd Level Card Interaction ── */
    (function () {
        var radios = document.querySelectorAll('input[name="crowd_level"]');
        var cards  = { Light: 'card-light', Moderate: 'card-moderate', Heavy: 'card-heavy' };
        var active = { Light: 'active-light', Moderate: 'active-moderate', Heavy: 'active-heavy' };

        radios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                Object.values(cards).forEach(function (id) {
                    var c = document.getElementById(id);
                    if (c) c.className = 'crowd-card';
                });
                var card = document.getElementById(cards[radio.value]);
                if (card) card.classList.add(active[radio.value]);
            });
        });
    })();

    /* ── GPS / Location ── */
    function getLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(showPosition, showError);
            setStatus('Getting your location\u2026', '');
        } else {
            setStatus('Geolocation is not supported by this browser.', 'red');
        }
    }

    function showPosition(position) {
        var lat = position.coords.latitude;
        var lng = position.coords.longitude;
        document.getElementById('latitude').value  = lat;
        document.getElementById('longitude').value = lng;
        setStatus('Location captured successfully!', 'green');
        if (window.setReportPinOnMap) window.setReportPinOnMap(lat, lng);
    }

    function showError(error) {
        var msg = 'Unable to retrieve your location.';
        if (error.code === error.PERMISSION_DENIED)    msg = 'Location permission denied.';
        if (error.code === error.POSITION_UNAVAILABLE) msg = 'Location information is unavailable.';
        if (error.code === error.TIMEOUT)              msg = 'Location request timed out.';
        setStatus(msg, 'red');
    }

    function setStatus(text, color) {
        var el = document.getElementById('locationStatus');
        if (!el) return;
        el.textContent = text;
        el.style.color = color === 'green' ? '#16a34a' : color === 'red' ? '#dc2626' : '#64748b';
    }

    /* ── Form Validation ── */
    function validateForm() {
        var cat        = document.getElementById('categoryFilter');
        var catVal     = cat && !cat.disabled ? cat.value : '';
        var routeId    = document.getElementById('route_definition_id').value;
        var crowdLevel = document.querySelector('input[name="crowd_level"]:checked');
        var lat        = document.getElementById('latitude').value;
        var lng        = document.getElementById('longitude').value;
        if (cat && !cat.disabled && !catVal) { alert('Please select a category.'); return false; }
        if (!routeId)    { alert('Please select a route.');          return false; }
        if (!crowdLevel) { alert('Please select a crowd level.');     return false; }
        if (!lat || !lng){ alert('Please set your location on the map or use the GPS button.'); return false; }
        return true;
    }

    /* ── Leaflet Map ── */
    (function () {
        var mapEl = document.getElementById('report-route-map');
        if (!mapEl) return;

        var map = L.map('report-route-map').setView([14.5995, 120.9842], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '\u00a9 <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);

        var routeLayer      = null;
        var reportPinMarker = null;

        function setReportPin(lat, lng) {
            document.getElementById('latitude').value  = lat;
            document.getElementById('longitude').value = lng;
            setStatus('Location set. You can drag the pin to adjust.', 'green');
            if (reportPinMarker) {
                reportPinMarker.setLatLng([lat, lng]);
            } else {
                reportPinMarker = L.marker([lat, lng], { draggable: true })
                    .addTo(map)
                    .bindPopup('Report location (drag to move)');
                reportPinMarker.on('dragend', function () {
                    var pos = reportPinMarker.getLatLng();
                    if (typeof snapToRoad === 'function') {
                        snapToRoad(pos.lat, pos.lng, function (sLat, sLng) {
                            if (sLat != null && sLng != null) {
                                reportPinMarker.setLatLng([sLat, sLng]);
                                document.getElementById('latitude').value  = sLat;
                                document.getElementById('longitude').value = sLng;
                            } else {
                                document.getElementById('latitude').value  = pos.lat;
                                document.getElementById('longitude').value = pos.lng;
                            }
                        });
                    } else {
                        document.getElementById('latitude').value  = pos.lat;
                        document.getElementById('longitude').value = pos.lng;
                    }
                });
            }
        }
        window.setReportPinOnMap = setReportPin;

        map.on('click', function (e) {
            setStatus('Snapping to road\u2026', '');
            if (typeof snapToRoad === 'function') {
                snapToRoad(e.latlng.lat, e.latlng.lng, function (lat, lng) {
                    if (lat != null && lng != null) setReportPin(lat, lng);
                    else setReportPin(e.latlng.lat, e.latlng.lng);
                });
            } else {
                setReportPin(e.latlng.lat, e.latlng.lng);
            }
        });

        function drawRoute(routeName) {
            if (routeLayer) { map.removeLayer(routeLayer); routeLayer = null; }
            if (!window.reportPageRoutes || !routeName) return;
            var route = window.reportPageRoutes.find(function (r) { return r.name === routeName; });
            if (!route || !route.stops || route.stops.length === 0) return;
            var waypoints = route.stops.map(function (s) { return [s.latitude, s.longitude]; });

            if (typeof getRouteGeometry === 'function') {
                getRouteGeometry(waypoints, function (roadLatlngs) {
                    var latlngs = (roadLatlngs && roadLatlngs.length) ? roadLatlngs : waypoints;
                    routeLayer = L.layerGroup().addTo(map);
                    L.polyline(latlngs, { color: '#22335C', weight: 5, opacity: 0.85 }).addTo(routeLayer);
                    route.stops.forEach(function (s, i) {
                        L.marker([s.latitude, s.longitude])
                            .bindPopup('<strong>' + (i + 1) + '. ' + (s.stop_name || 'Stop') + '</strong>')
                            .addTo(routeLayer);
                    });
                    map.fitBounds(latlngs, { padding: [30, 30] });
                });
            } else {
                routeLayer = L.layerGroup().addTo(map);
                L.polyline(waypoints, { color: '#22335C', weight: 5, opacity: 0.85 }).addTo(routeLayer);
                route.stops.forEach(function (s, i) {
                    L.marker([s.latitude, s.longitude])
                        .bindPopup('<strong>' + (i + 1) + '. ' + (s.stop_name || 'Stop') + '</strong>')
                        .addTo(routeLayer);
                });
                map.fitBounds(waypoints, { padding: [30, 30] });
            }
        }

        fetch('api_routes_with_stops.php', { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) { window.reportPageRoutes = data.routes || []; })
            .catch(function () { window.reportPageRoutes = []; });

        function filterRoutesByCategory(category) {
            var sel = document.getElementById('route_definition_id');
            if (!sel) return;
            // reset route selection whenever category changes
            sel.value = '';
            // hide/show options
            Array.prototype.forEach.call(sel.options, function (opt) {
                if (!opt.value) return; // keep placeholder
                var optCat = (opt.getAttribute('data-category') || '').toLowerCase();
                opt.hidden = category ? (optCat !== category) : true;
            });
        }

        var categorySel = document.getElementById('categoryFilter');
        if (categorySel && !categorySel.disabled) {
            categorySel.addEventListener('change', function () {
                var cat = (this.value || '').toLowerCase();
                filterRoutesByCategory(cat);
                if (routeLayer) { map.removeLayer(routeLayer); routeLayer = null; }
            });
            // Start with no routes shown until category chosen
            filterRoutesByCategory('');
        }

        document.getElementById('route_definition_id').addEventListener('change', function () {
            var opt = this.options[this.selectedIndex];
            drawRoute(opt ? (opt.getAttribute('data-route') || '') : '');
        });
    })();

    /* ── Nav + UI Interactions ── */
    (function () {
        /* Scroll effect */
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

        /* Profile dropdown */
        var btn  = document.getElementById('profileMenuButton');
        var menu = document.getElementById('profileMenu');
        if (btn && menu) {
            btn.addEventListener('click', function (e) { e.stopPropagation(); menu.classList.toggle('hidden'); });
            document.addEventListener('click', function () { if (menu) menu.classList.add('hidden'); });
        }

        /* Mobile menu */
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
            }, { threshold: 0.08 });
            revealEls.forEach(function (el) { io.observe(el); });
        } else {
            revealEls.forEach(function (el) { el.classList.add('visible'); });
        }
    })();
</script>
</body>
</html>
