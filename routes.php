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
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-8">
                    <a href="index.php" class="text-2xl font-bold text-gray-800">Transport Ops</a>
                    <div class="hidden md:flex space-x-4">
                        <a href="index.php" class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">Home</a>
                        <a href="about.php" class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">About</a>
                        <?php if ($_SESSION['role'] === 'Admin'): ?>
                            <a href="admin_dashboard.php" class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                        <?php else: ?>
                            <a href="user_dashboard.php" class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                        <?php endif; ?>
                        <a href="report.php" class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">Submit Report</a>
                        <a href="reports_map.php" class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">Reports Map</a>
                        <a href="routes.php" class="text-blue-600 hover:text-blue-800 px-3 py-2 rounded-md text-sm font-medium border-b-2 border-blue-600">Routes</a>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-700"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 transition duration-150 font-medium">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-4">
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
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
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
                        L.polyline(latlngs, { color: '#2563eb', weight: 5, opacity: 0.8 }).addTo(routeLayer);
                        route.stops.forEach(function (s, i) {
                            L.marker([s.latitude, s.longitude])
                                .bindPopup('<strong>' + (i + 1) + '. ' + (s.stop_name || 'Stop') + '</strong>')
                                .addTo(routeLayer);
                        });
                        map.fitBounds(latlngs, { padding: [40, 40] });
                    });
                } else {
                    L.polyline(waypoints, { color: '#2563eb', weight: 5, opacity: 0.8 }).addTo(routeLayer);
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
</body>
</html>
