<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

$reports = [];
$routes_with_stops = [];
$focus_report_id = isset($_GET['focus']) ? (int)$_GET['focus'] : 0;

try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT r.id, r.crowd_level, r.delay_reason, r.timestamp, r.latitude, r.longitude,
               r.is_verified, r.peer_verifications,
               u.name AS user_name, u.email AS user_email,
               p.plate_number, p.vehicle_type, p.current_route
        FROM reports r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN puv_units p ON r.puv_id = p.id
        ORDER BY r.timestamp DESC
        LIMIT 200
    ");
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    try {
        $stmt = $pdo->query("SELECT id, name FROM route_definitions ORDER BY name");
        $routeDefs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->query("SELECT route_definition_id, stop_name, latitude, longitude, stop_order FROM route_stops ORDER BY route_definition_id, stop_order");
        $stops = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stopsByRoute = [];
        foreach ($stops as $s) {
            $rid = $s['route_definition_id'];
            if (!isset($stopsByRoute[$rid])) $stopsByRoute[$rid] = [];
            $stopsByRoute[$rid][] = ['stop_name' => $s['stop_name'], 'latitude' => (float)$s['latitude'], 'longitude' => (float)$s['longitude'], 'stop_order' => (int)$s['stop_order']];
        }
        foreach ($routeDefs as $r) {
            $r['stops'] = $stopsByRoute[$r['id']] ?? [];
            usort($r['stops'], function ($a, $b) { return $a['stop_order'] - $b['stop_order']; });
            $routes_with_stops[] = $r;
        }
    } catch (PDOException $e) {
        // route_definitions may not exist yet
    }
} catch (PDOException $e) {
    error_log('Admin reports error: ' . $e->getMessage());
    $reports = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Transport Operations System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50">
    <div class="flex h-screen overflow-hidden">
        <aside class="w-64 bg-gradient-to-b from-gray-800 to-gray-900 text-white flex flex-col shadow-2xl">
            <div class="p-6 flex-shrink-0">
                <div class="flex items-center mb-8">
                    <div class="bg-blue-600 p-2 rounded-lg mr-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold">Transport Ops</h1>
                </div>
                <nav class="space-y-2">
                    <a href="admin_dashboard.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Fleet Overview
                    </a>
                    <a href="tracking.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Real-Time Tracking
                    </a>
                    <a href="admin_reports.php" 
                       class="flex items-center px-4 py-3 bg-blue-600 rounded-lg hover:bg-blue-700 transition duration-150 shadow-lg">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6a2 2 0 012-2h6m-4-4l4 4-4 4"></path>
                        </svg>
                        Reports
                    </a>
                    <a href="route_status.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                        </svg>
                        Route Status
                    </a>
                    <a href="manage_routes.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                        </svg>
                        Manage Routes
                    </a>
                    <a href="heatmap.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Crowdsourcing Heatmap
                    </a>
                    <a href="user_management.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        User Management
                    </a>
                    <a href="add_puv.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Add Vehicle
                    </a>
                </nav>
            </div>
            <div class="mt-auto p-6 border-t border-gray-700">
                <div class="bg-gray-700 rounded-lg p-4 mb-4">
                    <p class="text-xs text-gray-400 mb-1">Logged in as</p>
                    <p class="text-sm font-semibold"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                    <p class="text-xs text-blue-400 mt-1"><?php echo htmlspecialchars($_SESSION['role']); ?></p>
                </div>
                <a href="logout.php" 
                   class="block w-full text-center bg-gradient-to-r from-red-600 to-red-700 text-white py-2 px-4 rounded-md hover:from-red-700 hover:to-red-800 transition duration-150 font-medium shadow-lg">
                    Logout
                </a>
            </div>
        </aside>

        <main class="flex-1 flex flex-col">
            <div class="bg-white shadow-sm border-b border-gray-200 p-6">
                <h2 class="text-3xl font-bold text-gray-800">Reports</h2>
                <p class="text-gray-600 mt-2">Browse all reports and inspect a single report on the map. Select a route to see it drawn on the map.</p>
                <?php if (!empty($routes_with_stops)): ?>
                <div class="mt-4 flex items-center gap-3">
                    <label for="routeFilter" class="text-sm font-medium text-gray-700">Filter by route:</label>
                    <select id="routeFilter" class="px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                        <option value="">All reports</option>
                        <?php foreach ($routes_with_stops as $rd): ?>
                            <option value="<?php echo htmlspecialchars($rd['name']); ?>"><?php echo htmlspecialchars($rd['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <div class="flex-1 flex overflow-hidden">
                <div class="w-full lg:w-1/2 overflow-y-auto">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xl font-semibold text-gray-800">All Reports</h3>
                            <button id="showAllBtn" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                                Show all on map
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Vehicle</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Crowd</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Verified</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($reports)): ?>
                                        <tr>
                                            <td colspan="5" class="px-4 py-4 text-center text-gray-500">
                                                No reports found.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($reports as $report): ?>
                                            <tr class="report-row" data-route="<?php echo htmlspecialchars($report['current_route'] ?? ''); ?>">
                                                <td class="px-4 py-2 whitespace-nowrap">
                                                    <?php echo date('M d, Y H:i', strtotime($report['timestamp'])); ?>
                                                </td>
                                                <td class="px-4 py-2 whitespace-nowrap">
                                                    <div class="font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($report['plate_number'] ?? 'N/A'); ?>
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        <?php echo htmlspecialchars(($report['vehicle_type'] ?? 'Bus') . ' - ' . ($report['current_route'] ?? '')); ?>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-2 whitespace-nowrap">
                                                    <span class="px-2 py-1 text-xs rounded-full border
                                                        <?php 
                                                            echo $report['crowd_level'] === 'Light' ? 'bg-green-50 text-green-800 border-green-300' :
                                                                ($report['crowd_level'] === 'Moderate' ? 'bg-yellow-50 text-yellow-800 border-yellow-300' : 'bg-red-50 text-red-800 border-red-300');
                                                        ?>">
                                                        <?php echo htmlspecialchars($report['crowd_level']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-2 whitespace-nowrap">
                                                    <?php $verified = (int)$report['is_verified'] === 1; ?>
                                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                                        <?php echo $verified ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                        <?php echo $verified ? 'Yes' : 'No'; ?>
                                                    </span>
                                                    <div class="text-xs text-gray-500">
                                                        <?php echo (int)($report['peer_verifications'] ?? 0); ?>/3 verifications
                                                    </div>
                                                </td>
                                                <td class="px-4 py-2 whitespace-nowrap">
                                                    <?php if ($report['latitude'] && $report['longitude']): ?>
                                                        <button 
                                                            class="view-on-map-btn text-blue-600 hover:text-blue-800 text-xs font-medium"
                                                            data-report-id="<?php echo $report['id']; ?>">
                                                            View on map
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-xs text-gray-400">No location</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="hidden lg:block lg:w-1/2 border-l border-gray-200">
                    <div class="h-full" id="report-map"></div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const reportsData = <?php echo json_encode($reports); ?>;
        const routesWithStops = <?php echo json_encode($routes_with_stops); ?>;
        const focusReportId = <?php echo $focus_report_id; ?>;

        const map = L.map('report-map').setView([14.5995, 120.9842], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        const markersLayer = L.layerGroup().addTo(map);
        let routeLayer = null;

        function getIconColor(crowdLevel) {
            if (crowdLevel === 'Light') return 'green';
            if (crowdLevel === 'Moderate') return 'yellow';
            return 'red';
        }

        function addMarkerForReport(report, singleFocus = false) {
            if (!report.latitude || !report.longitude) return;
            const lat = parseFloat(report.latitude);
            const lng = parseFloat(report.longitude);
            const color = getIconColor(report.crowd_level);
            const isVerified = parseInt(report.is_verified, 10) === 1;
            const borderColor = isVerified ? 'green' : 'white';

            const marker = L.marker([lat, lng], {
                icon: L.divIcon({
                    className: 'custom-marker',
                    html: `
                        <div style="
                            background-color: ${color};
                            width: 20px;
                            height: 20px;
                            border-radius: 50%;
                            border: 3px solid ${borderColor};
                            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
                        "></div>
                    `,
                    iconSize: [20, 20]
                })
            }).addTo(markersLayer);

            const timestamp = new Date(report.timestamp).toLocaleString();
            marker.bindPopup(`
                <div class="text-sm">
                    <strong>${report.plate_number || 'Unknown'} (${report.vehicle_type || 'Bus'})</strong><br>
                    Route: ${report.current_route || 'N/A'}<br>
                    Crowd: ${report.crowd_level}<br>
                    Verified: ${isVerified ? 'Yes' : 'No'} (${report.peer_verifications || 0}/3)<br>
                    Reported by: ${report.user_name || 'Unknown'}<br>
                    Time: ${timestamp}<br>
                    ${report.delay_reason ? 'Delay: ' + report.delay_reason + '<br>' : ''}
                </div>
            `);

            if (singleFocus) {
                marker.openPopup();
                map.setView([lat, lng], 15);
            }

            return marker;
        }

        function drawRouteOnMap(routeName) {
            if (routeLayer) {
                map.removeLayer(routeLayer);
                routeLayer = null;
            }
            const route = routesWithStops.find(r => r.name === routeName);
            if (!route || !route.stops || route.stops.length === 0) return;
            const latlngs = route.stops.map(s => [s.latitude, s.longitude]);
            routeLayer = L.polyline(latlngs, { color: '#2563eb', weight: 5, opacity: 0.8 }).addTo(map);
            route.stops.forEach((s, i) => {
                L.marker([s.latitude, s.longitude])
                    .bindPopup('<strong>' + (i + 1) + '. ' + (s.stop_name || 'Stop') + '</strong>')
                    .addTo(routeLayer);
            });
        }

        function showReportsOnMap(reports, fitBounds) {
            markersLayer.clearLayers();
            const bounds = [];
            (reports || reportsData).forEach(r => {
                if (!r.latitude || !r.longitude) return;
                const marker = addMarkerForReport(r);
                if (marker) {
                    const latLng = marker.getLatLng();
                    bounds.push([latLng.lat, latLng.lng]);
                }
            });
            if (fitBounds !== false && bounds.length > 0) {
                map.fitBounds(bounds, { padding: [40, 40] });
            }
        }

        function showAllReportsOnMap() {
            showReportsOnMap(reportsData, true);
        }

        function focusReportOnMap(reportId) {
            const report = reportsData.find(r => parseInt(r.id, 10) === parseInt(reportId, 10));
            if (!report) {
                alert('Report not found.');
                return;
            }
            if (!report.latitude || !report.longitude) {
                alert('This report has no location data.');
                return;
            }
            markersLayer.clearLayers();
            addMarkerForReport(report, true);
        }

        function applyRouteFilter() {
            const routeName = document.getElementById('routeFilter') ? document.getElementById('routeFilter').value : '';
            document.querySelectorAll('.report-row').forEach(row => {
                const rowRoute = row.getAttribute('data-route') || '';
                row.style.display = (!routeName || rowRoute === routeName) ? '' : 'none';
            });
            if (routeName) {
                const filtered = reportsData.filter(r => (r.current_route || '') === routeName);
                showReportsOnMap(filtered, true);
                drawRouteOnMap(routeName);
                const route = routesWithStops.find(r => r.name === routeName);
                if (route && route.stops && route.stops.length > 0) {
                    const latlngs = route.stops.map(s => [s.latitude, s.longitude]);
                    map.fitBounds(latlngs, { padding: [50, 50] });
                }
            } else {
                if (routeLayer) {
                    map.removeLayer(routeLayer);
                    routeLayer = null;
                }
                showAllReportsOnMap();
            }
        }

        if (reportsData.length > 0) {
            showAllReportsOnMap();
        }
        if (focusReportId) {
            focusReportOnMap(focusReportId);
        }

        const routeFilterEl = document.getElementById('routeFilter');
        if (routeFilterEl) {
            routeFilterEl.addEventListener('change', applyRouteFilter);
        }

        document.getElementById('showAllBtn').addEventListener('click', function () {
            if (routeFilterEl) routeFilterEl.value = '';
            if (routeLayer) {
                map.removeLayer(routeLayer);
                routeLayer = null;
            }
            showAllReportsOnMap();
        });

        document.querySelectorAll('.view-on-map-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                const reportId = this.getAttribute('data-report-id');
                focusReportOnMap(reportId);
            });
        });
    </script>
</body>
</html>

