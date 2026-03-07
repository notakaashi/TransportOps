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
    <title>Reports — Transport Ops</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="js/osrm-helpers.js"></script>
    <?php include "admin_layout_head.php"; ?>
    <style>
        /* Page-specific: full-height split layout */
        body, html { height: 100%; }
        .app-layout { height: 100vh; overflow: hidden; }
        .main-area  { display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .reports-split { flex: 1; display: flex; overflow: hidden; min-height: 0; }
        .reports-list  { width: 50%; overflow-y: auto; border-right: 1px solid rgba(34,51,92,0.08); }
        .reports-map   { flex: 1; position: relative; }
        #report-map    { width: 100%; height: 100%; min-height: 400px; }
        @media (max-width: 1024px) {
            .reports-list { width: 100%; }
            .reports-map  { display: none; }
        }

        /* Page header bar */
        .page-header-bar {
            padding: 1.25rem 1.5rem;
            background: rgba(255,255,255,0.86);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(34,51,92,0.07);
            flex-shrink: 0;
            position: relative; z-index: 5;
        }

        /* Filter row */
        .filter-row {
            display: flex; align-items: center; gap: 0.75rem;
            margin-top: 0.75rem; flex-wrap: wrap;
        }
        .filter-label { font-size: 0.78rem; font-weight: 600; color: #374151; white-space: nowrap; }

        /* View-on-map button */
        .map-btn {
            font-size: 0.75rem; font-weight: 600;
            color: #1d4ed8; background: #dbeafe;
            border: 1px solid #bfdbfe; border-radius: 999px;
            padding: 0.2rem 0.65rem; cursor: pointer;
            transition: all 0.15s; white-space: nowrap;
        }
        .map-btn:hover { background: #bfdbfe; color: #1e3a8a; }

        /* Show-all button */
        .show-all-btn {
            font-size: 0.78rem; font-weight: 600;
            color: #5B7B99; background: transparent;
            border: none; cursor: pointer; padding: 0;
            transition: color 0.15s;
        }
        .show-all-btn:hover { color: #22335C; }
    </style>
</head>
<body>
<?php include "admin_sidebar.php"; ?>

    <!-- ═══ MAIN CONTENT ════════════════════════════════════ -->
    <main class="main-area">

        <!-- Page Header Bar -->
        <div class="page-header-bar">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
                <div>
                    <h1 class="page-title">Reports</h1>
                    <p class="page-subtitle">Browse all crowd reports and inspect locations on the map.</p>
                </div>
                <button id="showAllBtn" class="show-all-btn">Show all on map ↗</button>
            </div>
            <?php if (!empty($routes_with_stops)): ?>
            <div class="filter-row">
                <span class="filter-label">Filter by route:</span>
                <select id="routeFilter" class="aform-select" style="width:auto;min-width:200px;">
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

        <!-- Split: list + map -->
        <div class="reports-split">

            <!-- Reports List -->
            <div class="reports-list">
                <div style="padding:1.25rem 1.375rem;">
                    <div class="admin-card">
                        <div class="admin-card-header">
                            <div class="admin-card-title-wrap">
                                <div class="admin-card-icon aci-blue">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6a2 2 0 012-2h6m-4-4l4 4-4 4"/>
                                    </svg>
                                </div>
                                <span class="admin-card-title">All Reports</span>
                            </div>
                            <span style="font-size:0.75rem;color:#94a3b8;font-weight:500;"><?php echo count(
                                $reports,
                            ); ?> records</span>
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Route</th>
                                        <th>Crowd</th>
                                        <th>Verified</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($reports)): ?>
                                        <tr class="empty-row"><td colspan="5">No reports found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($reports as $report): ?>
                                            <tr class="report-row" data-route="<?php echo htmlspecialchars(
                                                $report["route_name"] ?? "",
                                            ); ?>">
                                                <td style="white-space:nowrap;">
                                                    <div style="font-size:0.82rem;font-weight:600;color:#1e293b;"><?php echo date(
                                                        "M d, Y",
                                                        strtotime(
                                                            $report[
                                                                "timestamp"
                                                            ],
                                                        ),
                                                    ); ?></div>
                                                    <div style="font-size:0.72rem;color:#94a3b8;"><?php echo date(
                                                        "H:i",
                                                        strtotime(
                                                            $report[
                                                                "timestamp"
                                                            ],
                                                        ),
                                                    ); ?></div>
                                                </td>
                                                <td>
                                                    <div style="font-weight:600;color:#334155;font-size:0.835rem;"><?php echo htmlspecialchars(
                                                        $report["route_name"] ??
                                                            "N/A",
                                                    ); ?></div>
                                                    <?php if (
                                                        !empty(
                                                            $report["user_name"]
                                                        )
                                                    ): ?>
                                                    <div style="font-size:0.72rem;color:#94a3b8;"><?php echo htmlspecialchars(
                                                        $report["user_name"],
                                                    ); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $cl =
                                                        $report["crowd_level"];
                                                    $cb =
                                                        $cl === "Light"
                                                            ? "abadge-light"
                                                            : ($cl ===
                                                            "Moderate"
                                                                ? "abadge-moderate"
                                                                : "abadge-heavy");
                                                    ?>
                                                    <span class="abadge <?php echo $cb; ?>">
                                                        <span class="abadge-dot"></span>
                                                        <?php echo htmlspecialchars(
                                                            $cl,
                                                        ); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php $verified =
                                                        (int) $report[
                                                            "is_verified"
                                                        ] === 1; ?>
                                                    <span class="abadge <?php echo $verified
                                                        ? "abadge-verified"
                                                        : "abadge-unverified"; ?>">
                                                        <?php echo $verified
                                                            ? "Verified"
                                                            : "Pending"; ?>
                                                    </span>
                                                    <div style="font-size:0.7rem;color:#94a3b8;margin-top:2px;"><?php echo (int) ($report[
                                                        "peer_verifications"
                                                    ] ?? 0); ?>/3</div>
                                                </td>
                                                <td>
                                                    <?php if (
                                                        $report["latitude"] &&
                                                        $report["longitude"]
                                                    ): ?>
                                                        <button class="view-on-map-btn map-btn" data-report-id="<?php echo $report[
                                                            "id"
                                                        ]; ?>">
                                                            View on map
                                                        </button>
                                                    <?php else: ?>
                                                        <span style="font-size:0.75rem;color:#cbd5e1;">No location</span>
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
            </div>

            <!-- Map Panel -->
            <div class="reports-map">
                <div id="report-map"></div>
            </div>

        </div><!-- /reports-split -->
    </main>
</div><!-- /app-layout -->

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

    </script>
<?php include "admin_sidebar_js.php"; ?>
</body>
</html>
