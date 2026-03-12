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
               r.is_verified, r.peer_verifications, r.status,
               u.id as user_id, u.name as user_name, u.email, u.role, u.trust_score,
               rd.name AS route_name
        FROM reports r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN route_definitions rd ON r.route_definition_id = rd.id
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

        /* ── Mini Calendar ───────────────────────────── */
        .cal-card {
            padding: 0.85rem 1rem 0.75rem;
            margin-bottom: 0;
        }
        .cal-header {
            display: flex; align-items: center;
            justify-content: space-between; margin-bottom: 0.55rem; gap: 0.4rem;
        }
        .cal-month-label {
            font-size: 0.83rem; font-weight: 700; color: #1e293b;
            flex: 1; text-align: center; letter-spacing: 0.01em;
        }
        .cal-nav-btn {
            background: none; border: none; cursor: pointer;
            font-size: 1.2rem; line-height: 1; color: #64748b;
            padding: 0.1rem 0.4rem; border-radius: 6px;
            transition: background 0.15s, color 0.15s;
        }
        .cal-nav-btn:hover { background: #f1f5f9; color: #1e293b; }
        .cal-clear-btn {
            font-size: 0.67rem; font-weight: 600;
            color: #6366f1; background: #eef2ff;
            border: none; border-radius: 999px;
            padding: 0.18rem 0.55rem; cursor: pointer;
            transition: background 0.15s; white-space: nowrap;
        }
        .cal-clear-btn:hover { background: #e0e7ff; }
        .cal-dow-row {
            display: grid; grid-template-columns: repeat(7, 1fr);
            text-align: center; margin-bottom: 0.2rem;
        }
        .cal-dow-row span {
            font-size: 0.59rem; font-weight: 700;
            color: #94a3b8; text-transform: uppercase;
            letter-spacing: 0.04em; padding: 0.1rem 0;
        }
        .cal-grid {
            display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px;
        }
        .cal-day {
            aspect-ratio: 1; display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            border-radius: 7px; font-size: 0.71rem; font-weight: 600;
            cursor: pointer; position: relative;
            transition: background 0.13s; color: #334155; user-select: none;
        }
        .cal-day:hover { background: #f1f5f9; }
        .cal-day.cal-empty { pointer-events: none; }
        .cal-day.cal-today { background: #f0fdf4; color: #16a34a; }
        .cal-day.cal-has-reports { color: #1d4ed8; }
        .cal-day.cal-selected { background: #1d4ed8 !important; color: #fff !important; }
        .cal-day.cal-selected .cal-dot { background: #fff !important; }
        .cal-day.cal-other-month { color: #d1d5db; }
        .cal-day.cal-other-month .cal-dot { background: #d1d5db; }
        .cal-dot {
            width: 5px; height: 5px; border-radius: 50%;
            background: #3b82f6; margin-top: 1px; flex-shrink: 0;
        }
        .cal-selected-info {
            font-size: 0.7rem; color: #6366f1; font-weight: 600;
            text-align: center; margin-top: 0.45rem;
            padding-top: 0.45rem; border-top: 1px solid rgba(34,51,92,0.07);
        }
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

                <!-- Calendar -->
                <div style="padding:1rem 1.375rem 0.5rem;">
                    <div class="admin-card cal-card">
                        <div class="cal-header">
                            <button class="cal-nav-btn" id="calPrevBtn" title="Previous month">&#8249;</button>
                            <span class="cal-month-label" id="calMonthLabel"></span>
                            <button class="cal-nav-btn" id="calNextBtn" title="Next month">&#8250;</button>
                            <button class="cal-clear-btn" id="calClearBtn" style="display:none;" title="Clear date filter">&#x2715; Clear</button>
                        </div>
                        <div class="cal-dow-row">
                            <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span>
                            <span>Thu</span><span>Fri</span><span>Sat</span>
                        </div>
                        <div class="cal-grid" id="calGrid"></div>
                        <div class="cal-selected-info" id="calSelectedInfo" style="display:none;"></div>
                    </div>
                </div>

                <div style="padding:0 1.375rem 1.25rem;">
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
                            <span id="reportsCountLabel" style="font-size:0.75rem;color:#94a3b8;font-weight:500;"><?php echo count(
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
                                            <tr class="report-row"
                                                data-route="<?php echo htmlspecialchars(
                                                    $report["route_name"] ?? "",
                                                ); ?>"
                                                data-date="<?php echo date(
                                                    "Y-m-d",
                                                    strtotime(
                                                        $report["timestamp"],
                                                    ),
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
                                                    <?php 
                                                        $verified = (int) $report["is_verified"] === 1;
                                                        $status = $report['status'];
                                                        $statusClass = 'abadge-unverified';
                                                        if ($status === 'verified') {
                                                            $statusClass = 'abadge-verified';
                                                        } else if ($status === 'rejected') {
                                                            $statusClass = 'abadge-heavy';
                                                        }
                                                    ?>
                                                    <span class="abadge <?php echo $statusClass; ?>">
                                                        <?php echo ucfirst($status); ?>
                                                    </span>
                                                    <div style="font-size:0.7rem;color:#94a3b8;margin-top:2px;">
                                                        Verifications: <?php echo (int) ($report["peer_verifications"] ?? 0); ?>/3
                                                    </div>
                                                    <div style="font-size:0.7rem;color:#94a3b8;margin-top:2px;">
                                                        Rejections: <?php echo (int) ($report["rejections"] ?? 0); ?>/3
                                                    </div>
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

    /* ── Mini Calendar ─────────────────────────────────── */
    (function () {
        /* Build a date → count map from reportsData */
        const reportDateCounts = {};
        reportsData.forEach(function (r) {
            if (!r.timestamp) return;
            // Handle "YYYY-MM-DD HH:MM:SS" from MySQL
            const raw = r.timestamp.replace(' ', 'T');
            const d   = new Date(raw);
            if (isNaN(d)) return;
            const key = d.getFullYear() + '-'
                + String(d.getMonth() + 1).padStart(2, '0') + '-'
                + String(d.getDate()).padStart(2, '0');
            reportDateCounts[key] = (reportDateCounts[key] || 0) + 1;
        });

        const MONTHS = ['January','February','March','April','May','June',
                        'July','August','September','October','November','December'];

        const today     = new Date();
        const todayStr  = today.getFullYear() + '-'
                        + String(today.getMonth() + 1).padStart(2, '0') + '-'
                        + String(today.getDate()).padStart(2, '0');

        let calYear      = today.getFullYear();
        let calMonth     = today.getMonth(); // 0-indexed
        let selectedDate = null;

        function renderCalendar() {
            const grid  = document.getElementById('calGrid');
            const label = document.getElementById('calMonthLabel');
            if (!grid || !label) return;

            label.textContent = MONTHS[calMonth] + ' ' + calYear;

            const firstDow    = new Date(calYear, calMonth, 1).getDay();
            const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();

            let html = '';

            // Leading empty cells
            for (let i = 0; i < firstDow; i++) {
                html += '<div class="cal-day cal-empty"></div>';
            }

            for (let day = 1; day <= daysInMonth; day++) {
                const dateStr = calYear + '-'
                    + String(calMonth + 1).padStart(2, '0') + '-'
                    + String(day).padStart(2, '0');
                const count      = reportDateCounts[dateStr] || 0;
                const isToday    = dateStr === todayStr;
                const isSelected = dateStr === selectedDate;

                let cls = 'cal-day';
                if (isToday)    cls += ' cal-today';
                if (isSelected) cls += ' cal-selected';
                if (count > 0)  cls += ' cal-has-reports';

                const tip = count
                    ? count + ' report' + (count > 1 ? 's' : '')
                    : 'No reports';

                html += `<div class="${cls}" data-date="${dateStr}"
                              title="${tip}"
                              onclick="calSelectDate('${dateStr}')">
                            <span class="cal-day-num">${day}</span>
                            ${count > 0 ? '<div class="cal-dot"></div>' : ''}
                         </div>`;
            }

            grid.innerHTML = html;
        }

        /* ── Public: called by onclick on day cells ── */
        window.calSelectDate = function (dateStr) {
            // Toggle off when clicking the already-selected date
            if (selectedDate === dateStr) {
                window.clearCalendarFilter();
                return;
            }

            selectedDate = dateStr;
            renderCalendar();

            // Show clear button & info bar
            const clearBtn = document.getElementById('calClearBtn');
            const info     = document.getElementById('calSelectedInfo');
            if (clearBtn) clearBtn.style.display = '';
            if (info) {
                const count = reportDateCounts[dateStr] || 0;
                const label = new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', {
                    weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
                });
                info.textContent = label + ' — ' + count + ' report' + (count !== 1 ? 's' : '');
                info.style.display = '';
            }

            // Filter table rows
            let visible = 0;
            document.querySelectorAll('.report-row').forEach(function (row) {
                const match = (row.getAttribute('data-date') || '') === dateStr;
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });

            // Update record count label
            const countLbl = document.getElementById('reportsCountLabel');
            if (countLbl) countLbl.textContent = visible + ' record' + (visible !== 1 ? 's' : '');

            // Filter map markers
            const filtered = reportsData.filter(function (r) {
                if (!r.timestamp) return false;
                const d   = new Date(r.timestamp.replace(' ', 'T'));
                const key = d.getFullYear() + '-'
                    + String(d.getMonth() + 1).padStart(2, '0') + '-'
                    + String(d.getDate()).padStart(2, '0');
                return key === dateStr;
            });
            showReportsOnMap(filtered, true);
        };

        /* ── Public: called by the Clear button ── */
        window.clearCalendarFilter = function (e) {
            if (e) e.stopPropagation();
            selectedDate = null;
            renderCalendar();

            const clearBtn = document.getElementById('calClearBtn');
            const info     = document.getElementById('calSelectedInfo');
            if (clearBtn) clearBtn.style.display = 'none';
            if (info)     info.style.display = 'none';

            // Restore record count
            const countLbl = document.getElementById('reportsCountLabel');
            if (countLbl) countLbl.textContent = reportsData.length + ' records';

            // Re-apply route filter (resets row visibility + map)
            if (typeof applyRouteFilter === 'function') {
                applyRouteFilter();
            } else {
                document.querySelectorAll('.report-row').forEach(function (r) {
                    r.style.display = '';
                });
                showAllReportsOnMap();
            }
        };

        // Wire up the clear button defined in HTML
        const clearBtn = document.getElementById('calClearBtn');
        if (clearBtn) clearBtn.addEventListener('click', window.clearCalendarFilter);

        // Prev / Next month navigation
        document.getElementById('calPrevBtn').addEventListener('click', function () {
            calMonth--;
            if (calMonth < 0) { calMonth = 11; calYear--; }
            renderCalendar();
        });
        document.getElementById('calNextBtn').addEventListener('click', function () {
            calMonth++;
            if (calMonth > 11) { calMonth = 0; calYear++; }
            renderCalendar();
        });

        // Initial render
        renderCalendar();
    })();

    </script>
<?php include "admin_sidebar_js.php"; ?>
</body>
</html>
