<?php
/**
 * Enhanced Routes View - Individual and Combined Route Maps
 */
require_once "auth_helper.php";
secureSessionStart();
require_once "db.php";

$is_logged_in = isset($_SESSION["user_id"]);
$user_profile_data = ["profile_image" => null];

// Get user profile image for navigation (logged-in users only)
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

// Fetch all routes with stops
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT id, name, created_at
        FROM route_definitions
        ORDER BY name
    ");
    $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT id, route_definition_id, stop_name, latitude, longitude, stop_order
        FROM route_stops
        ORDER BY route_definition_id, stop_order
    ");
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
        $r["stops"] = $stopsByRoute[$r["id"]] ?? [];
        usort($r["stops"], function ($a, $b) {
            return $a["stop_order"] - $b["stop_order"];
        });
    }
} catch (PDOException $e) {
    error_log("Routes error: " . $e->getMessage());
    $routes = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Routes - Transport Operations System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="js/osrm-helpers.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --transit-primary-route: #22335C;   /* Navy Blue */
            --transit-secondary-route: #5B7B99; /* Slate Blue */
            --transit-info: #FBC061;            /* Gold/Yellow */
            --transit-foundation: #E8E1D8;      /* Light Gray */
        }
        .brand-font {
            font-family: 'Poppins', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            letter-spacing: 0.02em;
        }
        .bg-transit-foundation {
            background-color: var(--transit-foundation);
            color: #f9fafb;
        }
        .text-transit-primary {
            color: var(--transit-primary-route);
        }
        .bg-transit-primary {
            background-color: var(--transit-primary-route);
        }
        .border-transit-primary {
            border-color: var(--transit-primary-route);
        }
        .text-transit-secondary {
            color: var(--transit-secondary-route);
        }
        .bg-transit-secondary {
            background-color: var(--transit-secondary-route);
        }
        .border-transit-secondary {
            border-color: var(--transit-secondary-route);
        }
        .bg-transit-info {
            background-color: var(--transit-info);
            color: #1f2933;
        }

        /* Glassmorphism styles */
        .glass-nav {
            background: rgba(34, 51, 92, 0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.35), 0 2px 8px 0 rgba(0,0,0,0.15);
            transition: background 0.3s ease, box-shadow 0.3s ease, top 0.3s ease;
        }
        .glass-nav.scrolled {
            background: rgba(34, 51, 92, 0.92);
            box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.5), 0 4px 12px 0 rgba(0,0,0,0.25);
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.25);
        }

        /* Nav link: box only shows on hover or when active (current page) */
        .nav-link {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #e5e7eb;
            border: 1px solid transparent;
            transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
            text-decoration: none;
        }
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-color: rgba(255, 255, 255, 0.25);
            color: #ffffff;
        }
        .nav-link.active {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-color: rgba(255, 255, 255, 0.3);
            color: #ffffff;
        }
        .nav-link-mobile {
            display: block;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #e5e7eb;
            border: 1px solid transparent;
            transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
            text-decoration: none;
        }
        .nav-link-mobile:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.25);
            color: #ffffff;
        }
        .nav-link-mobile.active {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.3);
            color: #ffffff;
        }

        .glass-input {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }

        .glass-input:focus {
            background: rgba(255, 255, 255, 0.2);
            border-color: var(--transit-info);
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.3);
        }
        .route-map {
            height: 300px;
            border-radius: 8px;
            overflow: hidden;
        }
        .combined-map {
            height: 500px;
            border-radius: 8px;
            overflow: hidden;
        }
        .route-card {
            transition: all 0.3s ease;
        }
        .route-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .tab-active {
            border-bottom: 3px solid #10B981;
            color: #10B981;
        }
    </style>
</head>
<body class="bg-[var(--transit-foundation)] min-h-screen">
    <nav id="floatingNav" class="fixed top-4 left-1/2 -translate-x-1/2 z-30 glass-nav text-white rounded-2xl w-[calc(100%-2rem)] max-w-7xl">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-14">
                <div class="flex items-center space-x-8">
                    <a href="index.php" id="brandLink" class="brand-font text-xl sm:text-2xl font-bold text-white whitespace-nowrap">Transport Ops</a>
                    <div class="hidden md:flex space-x-4">
                        <a href="<?php echo $is_logged_in
                            ? "user_dashboard.php"
                            : "index.php"; ?>" class="nav-link">Home</a>
                        <a href="about.php" class="nav-link">About</a>
                        <?php if ($is_logged_in): ?>
                            <a href="report.php" class="nav-link">Submit Report</a>
                        <?php endif; ?>
                        <a href="reports_map.php" class="nav-link">Reports Map</a>
                        <a href="routes.php" class="nav-link active">Routes</a>
                    </div>
                    <div id="mobileMenu" class="md:hidden hidden absolute top-full left-0 right-0 mt-2 text-white flex flex-col space-y-1 px-4 py-3 z-20 rounded-2xl" style="background: rgba(34,51,92,0.95); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 8px 32px 0 rgba(31,38,135,0.4);">
                        <a href="<?php echo $is_logged_in
                            ? "user_dashboard.php"
                            : "index.php"; ?>" class="nav-link-mobile">Home</a>
                        <a href="about.php" class="nav-link-mobile">About</a>
                        <?php if ($is_logged_in): ?>
                            <a href="report.php" class="nav-link-mobile">Submit Report</a>
                        <?php endif; ?>
                        <a href="reports_map.php" class="nav-link-mobile">Reports Map</a>
                        <a href="routes.php" class="nav-link-mobile active">Routes</a>
                    </div>
                </div>
                <div class="relative flex items-center gap-2 sm:gap-3">
                    <?php if ($is_logged_in): ?>
                        <button id="profileMenuButton"
                                class="flex items-center gap-2 px-2 py-1.5 rounded-full hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/60">
                            <div class="hidden sm:flex flex-col items-end leading-tight">
                                <span class="text-xs sm:text-sm text-white font-medium">
                                    <?php echo htmlspecialchars(
                                        $_SESSION["user_name"],
                                    ); ?>
                                </span>
                                <span class="text-[11px] text-blue-100">
                                    <?php echo htmlspecialchars(
                                        $_SESSION["role"],
                                    ); ?>
                                </span>
                            </div>
                            <div class="flex items-center gap-1">
                                <?php if (
                                    $user_profile_data["profile_image"]
                                ): ?>
                                    <img src="uploads/<?php echo htmlspecialchars(
                                        $user_profile_data["profile_image"],
                                    ); ?>"
                                         alt="Profile"
                                         class="h-8 w-8 rounded-full object-cover border-2 border-white">
                                <?php else: ?>
                                    <div class="h-8 w-8 rounded-full bg-[#10B981] flex items-center justify-center text-white text-sm font-semibold">
                                        <?php echo strtoupper(
                                            substr(
                                                $_SESSION["user_name"] ?? "U",
                                                0,
                                                1,
                                            ),
                                        ); ?>
                                    </div>
                                <?php endif; ?>
                                <svg class="w-4 h-4 text-blue-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </button>
                        <div id="profileMenu"
                             class="hidden absolute right-0 top-11 w-48 rounded-lg shadow-lg py-1 z-40"
                             style="background: rgba(34,51,92,0.92); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 8px 32px 0 rgba(31,38,135,0.4);">
                            <a href="profile.php" class="block px-3 py-2 text-sm text-white hover:bg-white/10 rounded-sm mx-1">View &amp; Edit Profile</a>
                            <a href="public_profile.php?id=<?php echo $_SESSION[
                                "user_id"
                            ]; ?>" class="block px-3 py-2 text-sm text-white hover:bg-white/10 rounded-sm mx-1">View Public Profile</a>
                            <div class="my-1 border-t border-white/20"></div>
                            <a href="logout.php" class="block px-3 py-2 text-sm text-red-300 hover:bg-white/10 rounded-sm mx-1">Logout</a>
                        </div>
                    <?php else: ?>
                        <a href="register.php"
                           class="text-white px-3 py-2 rounded-md text-sm font-medium whitespace-nowrap transition duration-150"
                           style="background: rgba(255,255,255,0.1); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3); box-shadow: 0 4px 16px 0 rgba(31,38,135,0.2);"
                           onmouseover="this.style.background='rgba(255,255,255,0.2)'"
                           onmouseout="this.style.background='rgba(255,255,255,0.1)'">Register</a>
                        <a href="login.php"
                           class="text-white px-4 py-2 rounded-md font-medium whitespace-nowrap transition duration-150"
                           style="background: rgba(16,185,129,0.25); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid rgba(16,185,129,0.5); box-shadow: 0 4px 16px 0 rgba(16,185,129,0.2);"
                           onmouseover="this.style.background='rgba(16,185,129,0.45)'"
                           onmouseout="this.style.background='rgba(16,185,129,0.25)'">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-24 sm:pt-28 pb-6">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-md overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-white/20">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-semibold text-gray-800">Transport Routes</h2>
                        <p class="text-sm text-gray-600">View individual routes or see all routes combined on one map</p>
                    </div>
                    <?php if (
                        $is_logged_in &&
                        $_SESSION["role"] === "Admin"
                    ): ?>
                    <a href="manage_routes.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Create New Route
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- View Tabs -->
        <div class="bg-white rounded-2xl shadow-md overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-white/20">
                <button id="individualTab" class="flex-1 px-6 py-3 text-center font-medium tab-active transition-colors">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                    </svg>
                    Individual Routes
                </button>
                <button id="combinedTab" class="flex-1 px-6 py-3 text-center font-medium text-gray-500 hover:text-gray-700 transition-colors">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Combined View
                </button>
            </div>
        </div>

        <!-- Individual Routes View -->
        <div id="individualView" class="space-y-6">
            <?php if (empty($routes)): ?>
                <div class="glass-card rounded-2xl shadow-md p-8 text-center">
                    <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Routes Available</h3>
                    <p class="text-gray-500 mb-6">There are currently no routes defined in the system.</p>
                    <a href="manage_routes.php" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition-colors inline-flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Create Your First Route
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($routes as $route): ?>
                    <div class="glass-card rounded-2xl shadow-md overflow-hidden route-card">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-800"><?php echo htmlspecialchars(
                                        $route["name"],
                                    ); ?></h3>
                                    <p class="text-sm text-gray-500">
                                        <?php echo count(
                                            $route["stops"],
                                        ); ?> stops •
                                        Created <?php echo date(
                                            "M j, Y",
                                            strtotime($route["created_at"]),
                                        ); ?>
                                    </p>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="px-3 py-1 bg-green-100 text-green-800 text-sm font-medium rounded-full">
                                        Active
                                    </span>
                                </div>
                            </div>

                            <?php if (!empty($route["stops"])): ?>
                                <div class="mb-4">
                                    <div class="flex flex-wrap gap-2">
                                        <?php foreach (
                                            $route["stops"]
                                            as $stop
                                        ): ?>
                                            <span class="px-3 py-1 bg-blue-50 text-blue-700 text-sm rounded-full">
                                                <?php echo htmlspecialchars(
                                                    $stop["stop_name"],
                                                ); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="route-map" id="map-<?php echo $route[
                                    "id"
                                ]; ?>"></div>

                                <div class="mt-4">
                                    <div class="text-sm text-gray-600">
                                        <span class="font-medium">Route:</span>
                                        <?php echo implode(
                                            " → ",
                                            array_map(function ($s) {
                                                return htmlspecialchars(
                                                    $s["stop_name"],
                                                );
                                            }, $route["stops"]),
                                        ); ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500">
                                    <svg class="w-12 h-12 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    <p>No stops defined for this route</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Combined View -->
        <div id="combinedView" class="hidden">
            <div class="glass-card rounded-2xl shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-white/20">
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">All Routes Combined</h3>
                    <p class="text-sm text-gray-600">View all transport routes on a single map</p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <?php foreach ($routes as $route): ?>
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" class="route-toggle" data-route-id="<?php echo $route[
                                    "id"
                                ]; ?>" checked>
                                <span class="px-3 py-1 bg-gray-100 text-gray-700 text-sm rounded-full">
                                    <?php echo htmlspecialchars(
                                        $route["name"],
                                    ); ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="combined-map" id="combinedMap"></div>
            </div>
        </div>
    </div>

    <script>
        // Route data from PHP
        const routesData = <?php echo json_encode($routes); ?>;
        let individualMaps = {};
        let combinedMap = null;
        let combinedLayers = {};

        // Color palette for different routes
        const routeColors = [
            '#10B981', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6',
            '#EC4899', '#14B8A6', '#F97316', '#6366F1', '#84CC16'
        ];

        function getRouteColor(index) {
            return routeColors[index % routeColors.length];
        }

        // Initialize individual route maps
        function initializeIndividualMaps() {
            routesData.forEach(function(route, index) {
                if (!route.stops || route.stops.length === 0) return;

                const mapId = 'map-' + route.id;
                const mapElement = document.getElementById(mapId);
                if (!mapElement) return;

                // Create map centered on route stops
                const waypoints = route.stops.map(function(s) { return [s.latitude, s.longitude]; });
                const center = waypoints[0];

                const map = L.map(mapId).setView(center, 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap'
                }).addTo(map);

                const routeLayer = L.layerGroup().addTo(map);
                const color = getRouteColor(index);

                // Draw route
                if (typeof getRouteGeometry === 'function') {
                    getRouteGeometry(waypoints, function(roadLatlngs) {
                        const latlngs = roadLatlngs && roadLatlngs.length ? roadLatlngs : waypoints;
                        L.polyline(latlngs, { color: color, weight: 4, opacity: 0.8 }).addTo(routeLayer);

                        // Add stops
                        route.stops.forEach(function(stop, i) {
                            L.marker([stop.latitude, stop.longitude])
                                .bindPopup('<strong>' + (i + 1) + '. ' + (stop.stop_name || 'Stop') + '</strong>')
                                .addTo(routeLayer);
                        });

                        map.fitBounds(latlngs, { padding: [20, 20] });
                    });
                } else {
                    L.polyline(waypoints, { color: color, weight: 4, opacity: 0.8 }).addTo(routeLayer);
                    route.stops.forEach(function(stop, i) {
                        L.marker([stop.latitude, stop.longitude])
                            .bindPopup('<strong>' + (i + 1) + '. ' + (stop.stop_name || 'Stop') + '</strong>')
                            .addTo(routeLayer);
                    });
                    map.fitBounds(waypoints, { padding: [20, 20] });
                }

                individualMaps[route.id] = { map: map, layer: routeLayer, color: color };
            });
        }

        // Initialize combined map
        function initializeCombinedMap() {
            combinedMap = L.map('combinedMap').setView([14.5995, 120.9842], 11);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap'
            }).addTo(combinedMap);

            routesData.forEach(function(route, index) {
                if (!route.stops || route.stops.length === 0) return;

                const waypoints = route.stops.map(function(s) { return [s.latitude, s.longitude]; });
                const color = getRouteColor(index);

                const routeLayer = L.layerGroup();

                // Draw route
                if (typeof getRouteGeometry === 'function') {
                    getRouteGeometry(waypoints, function(roadLatlngs) {
                        const latlngs = roadLatlngs && roadLatlngs.length ? roadLatlngs : waypoints;
                        L.polyline(latlngs, { color: color, weight: 3, opacity: 0.7 }).addTo(routeLayer);

                        // Add stops
                        route.stops.forEach(function(stop, i) {
                            L.marker([stop.latitude, stop.longitude])
                                .bindPopup('<strong>' + route.name + '</strong><br>' +
                                          (i + 1) + '. ' + (stop.stop_name || 'Stop'))
                                .addTo(routeLayer);
                        });
                    });
                } else {
                    L.polyline(waypoints, { color: color, weight: 3, opacity: 0.7 }).addTo(routeLayer);
                    route.stops.forEach(function(stop, i) {
                        L.marker([stop.latitude, stop.longitude])
                            .bindPopup('<strong>' + route.name + '</strong><br>' +
                                      (i + 1) + '. ' + (stop.stop_name || 'Stop'))
                            .addTo(routeLayer);
                    });
                }

                routeLayer.addTo(combinedMap);
                combinedLayers[route.id] = routeLayer;
            });

            // Fit map to show all routes
            const allBounds = [];
            routesData.forEach(function(route) {
                if (route.stops && route.stops.length > 0) {
                    route.stops.forEach(function(stop) {
                        allBounds.push([stop.latitude, stop.longitude]);
                    });
                }
            });

            if (allBounds.length > 0) {
                combinedMap.fitBounds(allBounds, { padding: [40, 40] });
            }
        }

        // Toggle route visibility on combined map
        function toggleRoute(routeId, visible) {
            if (combinedLayers[routeId]) {
                if (visible) {
                    combinedLayers[routeId].addTo(combinedMap);
                } else {
                    combinedMap.removeLayer(combinedLayers[routeId]);
                }
            }
        }

        // Focus on specific route
        function focusRoute(routeId) {
            const tabButton = document.getElementById('combinedTab');
            tabButton.click();

            setTimeout(function() {
                if (individualMaps[routeId]) {
                    const map = individualMaps[routeId].map;
                    map.invalidateSize();
                    const waypoints = routesData.find(r => r.id === routeId).stops.map(s => [s.latitude, s.longitude]);
                    if (waypoints.length > 0) {
                        map.fitBounds(waypoints, { padding: [40, 40] });
                    }
                }
            }, 100);
        }

        // Tab switching
        document.getElementById('individualTab').addEventListener('click', function() {
            document.getElementById('individualView').classList.remove('hidden');
            document.getElementById('combinedView').classList.add('hidden');
            this.classList.add('tab-active');
            document.getElementById('combinedTab').classList.remove('tab-active');

            // Initialize maps if not already done
            if (Object.keys(individualMaps).length === 0) {
                setTimeout(initializeIndividualMaps, 100);
            }
        });

        document.getElementById('combinedTab').addEventListener('click', function() {
            document.getElementById('individualView').classList.add('hidden');
            document.getElementById('combinedView').classList.remove('hidden');
            this.classList.add('tab-active');
            document.getElementById('individualTab').classList.remove('tab-active');

            // Initialize combined map if not already done
            if (!combinedMap) {
                setTimeout(initializeCombinedMap, 100);
            }
        });

        // Route toggle listeners
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('route-toggle')) {
                const routeId = parseInt(e.target.dataset.routeId);
                toggleRoute(routeId, e.target.checked);
            }
        });

        // Initialize individual maps on page load
        setTimeout(initializeIndividualMaps, 100);
    </script>

    <script>
        (function () {
            // Floating nav scroll effect
            const floatingNav = document.getElementById('floatingNav');
            if (floatingNav) {
                window.addEventListener('scroll', function () {
                    if (window.scrollY > 20) {
                        floatingNav.classList.add('scrolled');
                        floatingNav.style.top = '0.5rem';
                    } else {
                        floatingNav.classList.remove('scrolled');
                        floatingNav.style.top = '1rem';
                    }
                });
            }

            // Profile menu toggle
            const btn = document.getElementById('profileMenuButton');
            const menu = document.getElementById('profileMenu');
            if (btn && menu) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    menu.classList.toggle('hidden');
                });
                document.addEventListener('click', function () {
                    if (!menu.classList.contains('hidden')) {
                        menu.classList.add('hidden');
                    }
                });
            }

            // Mobile menu toggle
            const brand = document.getElementById('brandLink');
            const mobile = document.getElementById('mobileMenu');
            if (brand && mobile) {
                brand.addEventListener('click', function (e) {
                    if (window.innerWidth < 768) {
                        e.preventDefault();
                        mobile.classList.toggle('hidden');
                    }
                });
                document.addEventListener('click', function (ev) {
                    if (mobile && !mobile.contains(ev.target) && ev.target !== brand) {
                        mobile.classList.add('hidden');
                    }
                });
            }
        })();
    </script>
</body>
</html>
