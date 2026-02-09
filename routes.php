<?php
/**
 * Commuter: View routes and stops on the map
 */
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
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
            --transit-primary-route: #ff1744;    /* Bright Red */
            --transit-secondary-route: #4169e1;  /* Royal Blue */
            --transit-info: #facc15;             /* Vivid Yellow */
            --transit-foundation: #050505;       /* Matte Black */
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
    </style>
</head>
<body class="bg-[#F3F4F6] min-h-screen">
    <nav class="fixed top-0 inset-x-0 z-30 bg-[#1E3A8A] text-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-8">
                    <a href="index.php" class="brand-font text-xl sm:text-2xl font-bold text-white whitespace-nowrap">Transport Ops</a>
                    <div class="hidden md:flex space-x-4">
                        <a href="index.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Home</a>
                        <a href="about.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">About</a>
                        <?php if ($_SESSION['role'] === 'Admin'): ?>
                            <a href="admin_dashboard.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                        <?php else: ?>
                            <a href="user_dashboard.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                        <?php endif; ?>
                        <a href="report.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Submit Report</a>
                        <a href="reports_map.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Reports Map</a>
                        <a href="routes.php" class="text-white px-3 py-2 rounded-md text-sm font-medium border-b-2 border-[#10B981]">Routes</a>
                    </div>
                </div>
                <div class="relative flex items-center gap-2 sm:gap-3">
                    <button id="profileMenuButton"
                            class="flex items-center gap-2 px-2 py-1.5 rounded-full hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/60">
                        <div class="hidden sm:flex flex-col items-end leading-tight">
                            <span class="text-xs sm:text-sm text-white font-medium">
                                <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                            </span>
                            <span class="text-[11px] text-blue-100">
                                <?php echo htmlspecialchars($_SESSION['role']); ?>
                            </span>
                        </div>
                        <div class="flex items-center gap-1">
                            <div class="h-8 w-8 rounded-full bg-[#10B981] flex items-center justify-center text-white text-sm font-semibold">
                                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
                            </div>
                            <svg class="w-4 h-4 text-blue-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </button>
                    <div id="profileMenu"
                         class="hidden absolute right-0 top-11 w-44 bg-white text-gray-800 rounded-lg shadow-lg border border-gray-100 py-1 z-40">
                        <a href="profile.php"
                           class="block px-3 py-2 text-sm hover:bg-gray-50">
                            View &amp; Edit Profile
                        </a>
                        <div class="my-1 border-t border-gray-100"></div>
                        <a href="logout.php"
                           class="block px-3 py-2 text-sm text-red-600 hover:bg-red-50">
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-6">
        <div class="bg-white rounded-2xl shadow-md overflow-hidden mb-4">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-2xl font-semibold text-gray-800">Routes &amp; Stops</h2>
                <p class="text-sm text-gray-600">Select a route to see it on the map with all stops. Routes follow actual roads.</p>
            </div>
            <div class="p-4 flex flex-wrap items-center gap-4">
                <label for="routeSelect" class="text-sm font-medium text-gray-700">Select route</label>
                <select id="routeSelect" class="px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    <option value="">-- Choose a route --</option>
                </select>
                <p id="routeStopsInfo" class="text-sm text-gray-500 hidden"></p>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-md overflow-hidden">
            <div id="map" class="w-full h-[500px] lg:h-[600px]"></div>
        </div>
    </div>

    <script>
        (function () {
            var map = L.map('map').setView([14.5995, 120.9842], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);
            var routeLayer = null;
            var routesData = [];

            function drawRoute(route) {
                if (routeLayer) {
                    map.removeLayer(routeLayer);
                    routeLayer = null;
                }
                if (!route || !route.stops || route.stops.length === 0) return;
                var waypoints = route.stops.map(function (s) { return [s.latitude, s.longitude]; });
                routeLayer = L.layerGroup().addTo(map);
                if (typeof getRouteGeometry === 'function') {
                    getRouteGeometry(waypoints, function (roadLatlngs) {
                        var latlngs = roadLatlngs && roadLatlngs.length ? roadLatlngs : waypoints;
                        L.polyline(latlngs, { color: '#10B981', weight: 5, opacity: 0.9 }).addTo(routeLayer);
                        route.stops.forEach(function (s, i) {
                            L.marker([s.latitude, s.longitude])
                                .bindPopup('<strong>' + (i + 1) + '. ' + (s.stop_name || 'Stop') + '</strong>')
                                .addTo(routeLayer);
                        });
                        map.fitBounds(latlngs, { padding: [40, 40] });
                    });
                } else {
                    L.polyline(waypoints, { color: '#10B981', weight: 5, opacity: 0.9 }).addTo(routeLayer);
                    route.stops.forEach(function (s, i) {
                        L.marker([s.latitude, s.longitude])
                            .bindPopup('<strong>' + (i + 1) + '. ' + (s.stop_name || 'Stop') + '</strong>')
                            .addTo(routeLayer);
                    });
                    map.fitBounds(waypoints, { padding: [40, 40] });
                }
            }

            fetch('api_routes_with_stops.php', { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    routesData = data.routes || [];
                    var sel = document.getElementById('routeSelect');
                    routesData.forEach(function (r) {
                        var opt = document.createElement('option');
                        opt.value = r.name;
                        opt.textContent = r.name + (r.stops && r.stops.length ? ' (' + r.stops.length + ' stops)' : '');
                        sel.appendChild(opt);
                    });
                })
                .catch(function () {
                    document.getElementById('routeSelect').innerHTML = '<option value="">Failed to load routes</option>';
                });

            document.getElementById('routeSelect').addEventListener('change', function () {
                var name = this.value;
                var info = document.getElementById('routeStopsInfo');
                if (!name) {
                    if (routeLayer) {
                        map.removeLayer(routeLayer);
                        routeLayer = null;
                    }
                    info.classList.add('hidden');
                    return;
                }
                var route = routesData.find(function (r) { return r.name === name; });
                if (route) {
                    drawRoute(route);
                    info.classList.remove('hidden');
                    info.textContent = route.stops && route.stops.length
                        ? 'Stops: ' + route.stops.map(function (s) { return s.stop_name; }).join(' → ')
                        : 'No stops defined.';
                }
            });
        })();
    </script>
<script>
    (function () {
        const btn = document.getElementById('profileMenuButton');
        const menu = document.getElementById('profileMenu');
        if (!btn || !menu) return;
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            menu.classList.toggle('hidden');
        });
        document.addEventListener('click', function () {
            if (!menu.classList.contains('hidden')) {
                menu.classList.add('hidden');
            }
        });
    })();
</script>
</body>
</html>
