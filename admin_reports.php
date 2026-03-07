<?php
require_once "auth_helper.php";
secureSessionStart();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: admin_login.php");
    exit();
}
if ($_SESSION["role"] !== "Admin") {
    header("Location: login.php");
    exit();
}

$reports = [];
$routes_with_stops = [];
$focus_report_id = isset($_GET["focus"]) ? (int) $_GET["focus"] : 0;

try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT r.id, r.crowd_level, r.delay_reason, r.timestamp, r.latitude, r.longitude,
               r.is_verified, r.peer_verifications,
               u.name AS user_name, u.email AS user_email,
               COALESCE(rd.name, p.current_route) AS route_name
        FROM reports r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN route_definitions rd ON r.route_definition_id = rd.id
        LEFT JOIN puv_units p ON r.puv_id = p.id
        ORDER BY r.timestamp DESC
        LIMIT 200
    ");
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    try {
        $stmt = $pdo->query(
            "SELECT id, name FROM route_definitions ORDER BY name",
        );
        $routeDefs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->query(
            "SELECT route_definition_id, stop_name, latitude, longitude, stop_order FROM route_stops ORDER BY route_definition_id, stop_order",
        );
        $stops = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stopsByRoute = [];
        foreach ($stops as $s) {
            $rid = $s["route_definition_id"];
            if (!isset($stopsByRoute[$rid])) {
                $stopsByRoute[$rid] = [];
            }
            $stopsByRoute[$rid][] = [
                "stop_name" => $s["stop_name"],
                "latitude" => (float) $s["latitude"],
                "longitude" => (float) $s["longitude"],
                "stop_order" => (int) $s["stop_order"],
            ];
        }
        foreach ($routeDefs as $r) {
            $r["stops"] = $stopsByRoute[$r["id"]] ?? [];
            usort($r["stops"], function ($a, $b) {
                return $a["stop_order"] - $b["stop_order"];
            });
            $routes_with_stops[] = $r;
        }
    } catch (PDOException $e) {
        // route_definitions may not exist yet
    }
} catch (PDOException $e) {
    error_log("Admin reports error: " . $e->getMessage());
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
    <script src="js/osrm-helpers.js"></script>
    <style>
        :root {
            --transit-primary-route: #22335C;   /* Navy Blue */
            --transit-secondary-route: #5B7B99; /* Slate Blue */
            --transit-info: #FBC061;            /* Gold/Yellow */
            --transit-foundation: #E8E1D8;      /* Light Gray */
        }

        /* Glassmorphism styles (aligned with user pages) */
        .glass-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.30);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.18);
        }
        .glass-sidebar {
            background: rgba(34, 51, 92, 0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.35), 0 2px 8px 0 rgba(0,0,0,0.15);
            transition: box-shadow 0.3s ease;
        }
    </style>
</head>
<body class="bg-[var(--transit-foundation)]">
    <div class="min-h-screen">
        <aside id="adminSidebar" class="fixed top-4 inset-x-4 md:inset-x-auto md:left-4 md:w-64 md:h-[calc(100vh-2rem)] glass-sidebar text-white flex flex-col z-30 rounded-2xl shadow-2xl">
            <div class="px-4 py-4 sm:p-6 flex-shrink-0 border-b border-[#475569] md:border-b-0">
                <div id="adminNavToggle" class="flex items-center justify-between md:justify-start mb-4 md:mb-8 cursor-pointer md:cursor-default">
                    <div class="bg-[#5B7B99] p-2 rounded-lg mr-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-bold">Transport Ops</h1>
                    <svg class="w-5 h-5 text-gray-300 md:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </div>
                <nav id="adminNavLinks" class="space-y-1 md:space-y-2 text-sm sm:text-base hidden md:block">
                    <a href="admin_dashboard.php"
                       class="flex items-center px-4 py-3 hover:bg-[#475569] rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-[#E8E1D8]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Dashboard
                    </a>
                    <a href="admin_reports.php"
                       class="flex items-center px-4 py-3 bg-[#5B7B99] text-white rounded-lg hover:bg-[#4a6a89] transition duration-150 shadow-lg">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6a2 2 0 012-2h6m-4-4l4 4-4 4"></path>
                        </svg>
                        Reports
                    </a>
                    <a href="admin_trust_management.php"
                       class="flex items-center px-4 py-3 hover:bg-[#475569] rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-[#E8E1D8]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Trust Management
                    </a>
                    <a href="route_status.php"
                       class="flex items-center px-4 py-3 hover:bg-[#475569] rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-[#E8E1D8]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                        </svg>
                        Route Status
                    </a>
                    <a href="manage_routes.php"
                       class="flex items-center px-4 py-3 hover:bg-[#475569] rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-[#E8E1D8]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                        </svg>
                        Manage Routes
                    </a>
                    <a href="heatmap.php"
                       class="flex items-center px-4 py-3 hover:bg-[#475569] rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-[#E8E1D8]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Crowdsourcing Heatmap
                    </a>
                    <a href="user_management.php"
                       class="flex items-center px-4 py-3 hover:bg-[#475569] rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-[#E8E1D8]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        User Management
                    </a>
                </nav>
            </div>
            <div id="adminNavFooter" class="mt-auto p-4 sm:p-6 border-t border-[#475569] hidden md:block">
                <div class="bg-[#475569] rounded-lg p-3 sm:p-4 mb-4">
                    <p class="text-xs text-gray-400 mb-1">Logged in as</p>
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold"><?php echo htmlspecialchars(
                            $_SESSION["user_name"],
                        ); ?></p>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-1 bg-[#5B7B99] text-white text-xs rounded-full">Admin</span>
                            <a href="logout.php" class="text-red-400 hover:text-red-300 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l-4-4m0 0l4-4m-4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <main class="w-full md:ml-72 pt-24 md:pt-0 flex flex-col">
            <div class="glass-card shadow-sm p-4 sm:p-6 rounded-b-2xl md:rounded-none border-b border-white/20">
                <h2 class="text-3xl font-bold text-[#1e3a8a]">Reports</h2>
                <p class="text-[#475569] mt-2">Browse all reports and inspect a single report on the map. Select a route to see it drawn on the map.</p>
                <?php if (!empty($routes_with_stops)): ?>
                <div class="mt-4 flex items-center gap-3">
                    <label for="routeFilter" class="text-sm font-medium text-[#1e3a8a]">Filter by route:</label>
                    <select id="routeFilter" class="px-3 py-2 border border-[#d1d5db] rounded-md focus:ring-[#fbbf24] focus:border-[#fbbf24] text-sm">
                        <option value="">All reports</option>
                        <?php foreach ($routes_with_stops as $rd): ?>
                            <option value="<?php echo htmlspecialchars(
                                $rd["name"],
                            ); ?>"><?php echo htmlspecialchars(
    $rd["name"],
); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <div class="flex-1 flex overflow-hidden">
                <div class="w-full lg:w-1/2 overflow-y-auto">
                    <div class="p-4 sm:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xl font-semibold text-[#1e3a8a]">All Reports</h3>
                            <button id="showAllBtn" class="text-sm text-[#1e3a8a] hover:text-[#fbbf24] font-medium">
                                Show all on map
                            </button>
                        </div>
                        <div class="glass-card rounded-2xl overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-white/30">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium text-[#475569] uppercase tracking-wider">Time</th>
                                        <th class="px-4 py-2 text-left font-medium text-[#475569] uppercase tracking-wider">Route</th>
                                        <th class="px-4 py-2 text-left font-medium text-[#475569] uppercase tracking-wider">Crowd</th>
                                        <th class="px-4 py-2 text-left font-medium text-[#475569] uppercase tracking-wider">Verified</th>
                                        <th class="px-4 py-2 text-left font-medium text-[#475569] uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white/70 divide-y divide-gray-200">
                                    <?php if (empty($reports)): ?>
                                        <tr>
                                            <td colspan="5" class="px-4 py-4 text-center text-[#475569]">
                                                No reports found.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($reports as $report): ?>
                                            <tr class="report-row" data-route="<?php echo htmlspecialchars(
                                                $report["route_name"] ?? "",
                                            ); ?>">
                                                <td class="px-4 py-2 whitespace-nowrap">
                                                    <?php echo date(
                                                        "M d, Y H:i",
                                                        strtotime(
                                                            $report[
                                                                "timestamp"
                                                            ],
                                                        ),
                                                    ); ?>
                                                </td>
                                                <td class="px-4 py-2 whitespace-nowrap">
                                                    <div class="font-medium text-[#1e3a8a]">
                                                        <?php echo htmlspecialchars(
                                                            $report[
                                                                "route_name"
                                                            ] ?? "N/A",
                                                        ); ?>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-2 whitespace-nowrap">
                                                    <span class="px-2 py-1 text-xs rounded-full border
                                                        <?php echo $report[
                                                            "crowd_level"
                                                        ] === "Light"
                                                            ? "bg-green-50 text-green-800 border-green-300"
                                                            : ($report[
                                                                "crowd_level"
                                                            ] === "Moderate"
                                                                ? "bg-yellow-50 text-yellow-800 border-yellow-300"
                                                                : "bg-red-50 text-red-800 border-red-300"); ?>">
                                                        <?php echo htmlspecialchars(
                                                            $report[
                                                                "crowd_level"
                                                            ],
                                                        ); ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-2 whitespace-nowrap">
                                                    <?php $verified =
                                                        (int) $report[
                                                            "is_verified"
                                                        ] === 1; ?>
                                                    <span class="px-2 py-1 text-xs font-semibold rounded-full
                                                        <?php echo $verified
                                                            ? "bg-green-100 text-green-800"
                                                            : "bg-yellow-100 text-yellow-800"; ?>">
                                                        <?php echo $verified
                                                            ? "Yes"
                                                            : "No"; ?>
                                                    </span>
                                                    <div class="text-xs text-[#475569]">
                                                        <?php echo (int) ($report[
                                                            "peer_verifications"
                                                        ] ??
                                                            0); ?>/3 verifications
                                                    </div>
                                                </td>
                                                <td class="px-4 py-2 whitespace-nowrap">
                                                    <?php if (
                                                        $report["latitude"] &&
                                                        $report["longitude"]
                                                    ): ?>
                                                        <button
                                                            class="view-on-map-btn text-[#1e3a8a] hover:text-[#fbbf24] text-xs font-medium"
                                                            data-report-id="<?php echo $report[
                                                                "id"
                                                            ]; ?>">
                                                            View on map
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-xs text-[#475569]">No location</span>
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

                <div class="hidden lg:block lg:w-1/2 border-l border-[#e5e7eb]">
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
                    <strong>Route:</strong> ${report.route_name || 'N/A'}<br>
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
            const waypoints = route.stops.map(s => [s.latitude, s.longitude]);
            routeLayer = L.layerGroup().addTo(map);
            if (typeof getRouteGeometry === 'function') {
                getRouteGeometry(waypoints, function (roadLatlngs) {
                    const latlngs = roadLatlngs && roadLatlngs.length ? roadLatlngs : waypoints;
                    L.polyline(latlngs, { color: '#2563eb', weight: 5, opacity: 0.8 }).addTo(routeLayer);
                    route.stops.forEach((s, i) => {
                        L.marker([s.latitude, s.longitude])
                            .bindPopup('<strong>' + (i + 1) + '. ' + (s.stop_name || 'Stop') + '</strong>')
                            .addTo(routeLayer);
                    });
                });
            } else {
                L.polyline(waypoints, { color: '#2563eb', weight: 5, opacity: 0.8 }).addTo(routeLayer);
                route.stops.forEach((s, i) => {
                    L.marker([s.latitude, s.longitude])
                        .bindPopup('<strong>' + (i + 1) + '. ' + (s.stop_name || 'Stop') + '</strong>')
                        .addTo(routeLayer);
                });
            }
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
                const filtered = reportsData.filter(r => (r.route_name || '') === routeName);
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

        // Mobile sidebar toggle
        (function () {
            const toggle = document.getElementById('adminNavToggle');
            const links = document.getElementById('adminNavLinks');
            const footer = document.getElementById('adminNavFooter');
            if (!toggle || !links || !footer) return;
            toggle.addEventListener('click', function () {
                if (window.innerWidth >= 768) return;
                links.classList.toggle('hidden');
                footer.classList.toggle('hidden');
            });
            // Close mobile menu when clicking outside
            document.addEventListener('click', function (ev) {
                if (window.innerWidth >= 768) return;
                const sidebar = document.getElementById('adminSidebar');
                if (sidebar && !sidebar.contains(ev.target)) {
                    links.classList.add('hidden');
                    footer.classList.add('hidden');
                }
            });
        })();
    </script>
</body>
</html>

