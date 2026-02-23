<?php
/**
 * Commuter Reporting Interface
 * Allows users to submit real-time crowding and delay reports
 */

session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';
$routes_list = [];

// Fetch available routes (from route_definitions with at least one stop)
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT rd.id, rd.name
        FROM route_definitions rd
        INNER JOIN (SELECT route_definition_id FROM route_stops GROUP BY route_definition_id) rs ON rs.route_definition_id = rd.id
        ORDER BY rd.name
    ");
    $routes_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Fetch profile image for nav
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_profile_row = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_profile_data = $user_profile_row ?: ['profile_image' => null];
        if ($user_profile_data['profile_image']) {
            $_SESSION['profile_image'] = $user_profile_data['profile_image'];
        }
    } else {
        $user_profile_data = ['profile_image' => null];
    }
} catch (PDOException $e) {
    error_log("Error fetching routes: " . $e->getMessage());
    $user_profile_data = ['profile_image' => null];
}

// Haversine distance in km
function distanceKm($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2)*sin($dLat/2) + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLng/2)*sin($dLng/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earthRadius * $c;
}

// Process report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $route_definition_id = isset($_POST['route_definition_id']) ? (int)$_POST['route_definition_id'] : 0;
    $crowd_level = $_POST['crowd_level'] ?? '';
    $delay_reason = trim($_POST['delay_reason'] ?? '');
    $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
    $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
    
    if ($route_definition_id <= 0 || empty($crowd_level)) {
        $error = 'Please select a route and crowd level.';
    } elseif (!in_array($crowd_level, ['Light', 'Moderate', 'Heavy'])) {
        $error = 'Invalid crowd level selected.';
    } elseif ($latitude === null || $longitude === null) {
        $error = 'Please set your location on the map or use GPS so we can confirm you are on or near the route.';
    } else {
        try {
            $pdo = getDBConnection();
            
            $stmt = $pdo->prepare("SELECT id, name FROM route_definitions WHERE id = ?");
            $stmt->execute([$route_definition_id]);
            $route = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$route) {
                $error = 'Selected route not found. Please select a valid route.';
            } else {
                $stmt = $pdo->prepare("SELECT latitude, longitude FROM route_stops WHERE route_definition_id = ? ORDER BY stop_order");
                $stmt->execute([$route_definition_id]);
                $stops = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($stops)) {
                    $error = 'This route has no stops defined. Report cannot be validated.';
                } else {
                    $minDist = null;
                    foreach ($stops as $stop) {
                        $d = distanceKm($latitude, $longitude, (float)$stop['latitude'], (float)$stop['longitude']);
                        if ($minDist === null || $d < $minDist) $minDist = $d;
                    }
                    $thresholdKm = 0.5;
                    if ($minDist > $thresholdKm) {
                        $error = 'Your location must be on or near the selected route (within about 500 m of a stop) to submit a report. Current distance to nearest stop: ' . number_format($minDist * 1000, 0) . ' m.';
                    } else {
                        $geofence_validated = 1;
                        $trust_score = 1.00;
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO reports (user_id, route_definition_id, puv_id, crowd_level, delay_reason, latitude, longitude, geofence_validated, trust_score)
                            VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $_SESSION['user_id'],
                            $route_definition_id,
                            $crowd_level,
                            $delay_reason ?: null,
                            $latitude,
                            $longitude,
                            $geofence_validated,
                            $trust_score
                        ]);
                        
                        $success = 'Report submitted successfully! Thank you for your contribution.';
                        $_POST = [];
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Report submission error: " . $e->getMessage());
            $error = 'Failed to submit report. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Report - Transport Operations System</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="js/osrm-helpers.js"></script>
</head>
<body class="bg-[#F3F4F6]">
    <!-- Navigation Bar -->
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
                        <a href="report.php" class="bg-blue-500 text-white px-3 py-2 rounded-md text-sm font-medium border-b-2 border-blue-800">Submit Report</a>
                        <a href="reports_map.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Reports Map</a>
                        <a href="routes.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Routes</a>
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
                            <?php if (!empty($user_profile_data['profile_image'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($user_profile_data['profile_image']); ?>"
                                     alt="Profile"
                                     class="h-8 w-8 rounded-full object-cover border-2 border-white">
                            <?php else: ?>
                                <div class="h-8 w-8 rounded-full bg-[#10B981] flex items-center justify-center text-white text-sm font-semibold">
                                    <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
                                </div>
                            <?php endif; ?>
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

    <!-- Main Content -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8 items-start">
        <div class="bg-white rounded-2xl shadow-md p-6 sm:p-8">
            <h2 class="text-3xl font-bold text-gray-800 mb-6">Submit Report</h2>
            <p class="text-gray-600 mb-6">Help improve transportation services by reporting crowding levels and delays in real-time.</p>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="reportForm" class="space-y-6" onsubmit="return validateForm()">
                <div>
                    <label for="route_definition_id" class="block text-sm font-medium text-gray-700 mb-2">Select Route</label>
                    <select id="route_definition_id" name="route_definition_id" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Select a Route --</option>
                        <?php foreach ($routes_list as $r): ?>
                            <option value="<?php echo (int)$r['id']; ?>" data-route="<?php echo htmlspecialchars($r['name']); ?>">
                                <?php echo htmlspecialchars($r['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($routes_list)): ?>
                        <p class="text-sm text-amber-600 mt-1">No routes available. Ask an admin to add routes in Manage Routes.</p>
                    <?php endif; ?>
                </div>
                
                <div>
                    <label for="crowd_level" class="block text-sm font-medium text-gray-700 mb-2">Crowd Level</label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                        <label class="flex flex-col justify-between p-3 sm:p-4 border-2 border-gray-300 rounded-xl cursor-pointer hover:border-green-500 transition min-h-[80px] sm:min-h-[96px]">
                            <div class="flex items-center">
                                <input type="radio" name="crowd_level" value="Light" required class="mr-2">
                                <span class="font-semibold text-green-700">Light</span>
                            </div>
                            <div class="mt-1 text-xs text-gray-600">Comfortable seating</div>
                        </label>
                        <label class="flex flex-col justify-between p-3 sm:p-4 border-2 border-gray-300 rounded-xl cursor-pointer hover:border-yellow-500 transition min-h-[80px] sm:min-h-[96px]">
                            <div class="flex items-center">
                                <input type="radio" name="crowd_level" value="Moderate" required class="mr-2">
                                <span class="font-semibold text-yellow-700">Moderate</span>
                            </div>
                            <div class="mt-1 text-xs text-gray-600">Limited seating</div>
                        </label>
                        <label class="flex flex-col justify-between p-3 sm:p-4 border-2 border-gray-300 rounded-xl cursor-pointer hover:border-red-500 transition min-h-[80px] sm:min-h-[96px]">
                            <div class="flex items-center">
                                <input type="radio" name="crowd_level" value="Heavy" required class="mr-2">
                                <span class="font-semibold text-red-700">Heavy</span>
                            </div>
                            <div class="mt-1 text-xs text-gray-600">Crowded</div>
                        </label>
                    </div>
                </div>
                
                <div>
                    <label for="delay_reason" class="block text-sm font-medium text-gray-700 mb-2">Delay Reason (Optional)</label>
                    <select id="delay_reason" name="delay_reason"
                            class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- No delay --</option>
                        <option value="Traffic jam">Traffic jam</option>
                        <option value="Mechanical issues">Mechanical issues</option>
                        <option value="Large number of passengers">Large number of passengers</option>
                        <option value="Weather conditions">Weather conditions</option>
                        <option value="Accident">Accident</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="bg-blue-50 p-4 rounded-xl">
                    <p class="text-sm text-gray-700 mb-2">
                        <strong>Report location:</strong> Click on the map to pin where you are (pins snap to the nearest road). Or use GPS below.
                    </p>
                    <button type="button" onclick="getLocation()" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-150 font-medium text-sm min-h-[48px]">
                        Use my current location (GPS)
                    </button>
                    <input type="hidden" id="latitude" name="latitude">
                    <input type="hidden" id="longitude" name="longitude">
                    <p id="locationStatus" class="text-xs text-gray-600 mt-2"></p>
                </div>
                
                <button type="submit" 
                        class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-150 font-medium min-h-[48px]">
                    Submit Report
                </button>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-md overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Pin your report location</h3>
                <p class="text-sm text-gray-500">Select a route to see it on the map. Pin your location (click map or use GPS)—reports are only accepted when you're on or near the route.</p>
            </div>
            <div class="h-[400px] lg:h-[500px]" id="report-route-map"></div>
        </div>
        </div>
    </div>

    <script>
        function getLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(showPosition, showError);
                document.getElementById('locationStatus').textContent = 'Getting location...';
            } else {
                document.getElementById('locationStatus').textContent = 'Geolocation is not supported by this browser.';
            }
        }

        function showPosition(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            document.getElementById('locationStatus').textContent = 'Location captured successfully!';
            document.getElementById('locationStatus').classList.add('text-green-600');
            if (window.setReportPinOnMap) window.setReportPinOnMap(lat, lng);
        }

        function showError(error) {
            let message = 'Unable to retrieve your location.';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    message = 'User denied the request for Geolocation.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    message = 'Location information is unavailable.';
                    break;
                case error.TIMEOUT:
                    message = 'The request to get user location timed out.';
                    break;
            }
            document.getElementById('locationStatus').textContent = message;
            document.getElementById('locationStatus').classList.add('text-red-600');
        }
        
        function validateForm() {
            const routeId = document.getElementById('route_definition_id').value;
            const crowdLevel = document.querySelector('input[name="crowd_level"]:checked');
            const lat = document.getElementById('latitude').value;
            const lng = document.getElementById('longitude').value;
            
            if (!routeId) {
                alert('Please select a route.');
                return false;
            }
            if (!crowdLevel) {
                alert('Please select a crowd level.');
                return false;
            }
            if (!lat || !lng) {
                alert('Please set your location on the map or use the GPS button.');
                return false;
            }
            return true;
        }

        (function () {
            const mapEl = document.getElementById('report-route-map');
            if (!mapEl) return;
            const map = L.map('report-route-map').setView([14.5995, 120.9842], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);
            let routeLayer = null;
            let reportPinMarker = null;

            function setReportPin(lat, lng) {
                document.getElementById('latitude').value = lat;
                document.getElementById('longitude').value = lng;
                document.getElementById('locationStatus').textContent = 'Location set. You can drag the pin to adjust.';
                document.getElementById('locationStatus').classList.add('text-green-600');
                if (reportPinMarker) {
                    reportPinMarker.setLatLng([lat, lng]);
                } else {
                    reportPinMarker = L.marker([lat, lng], { draggable: true })
                        .addTo(map)
                        .bindPopup('Report location (drag to move)');
                    reportPinMarker.on('dragend', function () {
                        var pos = reportPinMarker.getLatLng();
                        if (typeof snapToRoad === 'function') {
                            snapToRoad(pos.lat, pos.lng, function (snapLat, snapLng) {
                                if (snapLat != null && snapLng != null) {
                                    reportPinMarker.setLatLng([snapLat, snapLng]);
                                    document.getElementById('latitude').value = snapLat;
                                    document.getElementById('longitude').value = snapLng;
                                } else {
                                    document.getElementById('latitude').value = pos.lat;
                                    document.getElementById('longitude').value = pos.lng;
                                }
                            });
                        } else {
                            document.getElementById('latitude').value = pos.lat;
                            document.getElementById('longitude').value = pos.lng;
                        }
                    });
                }
            }
            window.setReportPinOnMap = setReportPin;

            map.on('click', function (e) {
                document.getElementById('locationStatus').textContent = 'Snapping to road…';
                document.getElementById('locationStatus').classList.remove('text-green-600', 'text-red-600');
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
                if (routeLayer) {
                    map.removeLayer(routeLayer);
                    routeLayer = null;
                }
                if (!window.reportPageRoutes || !routeName) return;
                const route = window.reportPageRoutes.find(function (r) { return r.name === routeName; });
                if (!route || !route.stops || route.stops.length === 0) return;
                const waypoints = route.stops.map(function (s) { return [s.latitude, s.longitude]; });
                function drawWithRoads() {
                    if (typeof getRouteGeometry === 'function') {
                        getRouteGeometry(waypoints, function (roadLatlngs) {
                            var latlngs = roadLatlngs && roadLatlngs.length ? roadLatlngs : waypoints;
                            routeLayer = L.layerGroup().addTo(map);
                            L.polyline(latlngs, { color: '#10B981', weight: 5, opacity: 0.9 }).addTo(routeLayer);
                            route.stops.forEach(function (s, i) {
                                L.marker([s.latitude, s.longitude])
                                    .bindPopup('<strong>' + (i + 1) + '. ' + (s.stop_name || 'Stop') + '</strong>')
                                    .addTo(routeLayer);
                            });
                            map.fitBounds(latlngs, { padding: [30, 30] });
                        });
                    } else {
                        routeLayer = L.polyline(waypoints, { color: '#10B981', weight: 5, opacity: 0.9 }).addTo(map);
                        route.stops.forEach(function (s, i) {
                            L.marker([s.latitude, s.longitude])
                                .bindPopup('<strong>' + (i + 1) + '. ' + (s.stop_name || 'Stop') + '</strong>')
                                .addTo(routeLayer);
                        });
                        map.fitBounds(waypoints, { padding: [30, 30] });
                    }
                }
                drawWithRoads();
            }

            fetch('api_routes_with_stops.php', { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    window.reportPageRoutes = data.routes || [];
                })
                .catch(function () { window.reportPageRoutes = []; });

            document.getElementById('route_definition_id').addEventListener('change', function () {
                const opt = this.options[this.selectedIndex];
                const routeName = opt ? opt.getAttribute('data-route') : '';
                drawRoute(routeName || '');
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

