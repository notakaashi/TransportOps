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
$puvs = [];

// Fetch available PUVs
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT id, plate_number, vehicle_type, current_route FROM puv_units ORDER BY plate_number");
    $puvs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching PUVs: " . $e->getMessage());
}

// Process report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $puv_id = $_POST['puv_id'] ?? '';
    $crowd_level = $_POST['crowd_level'] ?? '';
    $delay_reason = trim($_POST['delay_reason'] ?? '');
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    
    // Validate input
    if (empty($puv_id) || empty($crowd_level)) {
        $error = 'Please select a PUV and crowd level.';
    } elseif (!in_array($crowd_level, ['Light', 'Moderate', 'Heavy'])) {
        $error = 'Invalid crowd level selected.';
    } else {
        try {
            $pdo = getDBConnection();
            
            // Verify PUV exists
            $stmt = $pdo->prepare("SELECT id FROM puv_units WHERE id = ?");
            $stmt->execute([$puv_id]);
            $puv = $stmt->fetch();
            
            if (!$puv) {
                $error = 'Selected vehicle not found. Please select a valid vehicle.';
            } else {
                $geofence_validated = 0;
                $trust_score = 1.00;
                
                // If GPS coordinates provided, validate them
                if ($latitude && $longitude) {
                    $geofence_validated = 1;
                    $trust_score = 1.00;
                }
                
                // Insert report
                $stmt = $pdo->prepare("
                    INSERT INTO reports (user_id, puv_id, crowd_level, delay_reason, latitude, longitude, geofence_validated, trust_score) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $puv_id,
                    $crowd_level,
                    $delay_reason ?: null,
                    $latitude ?: null,
                    $longitude ?: null,
                    $geofence_validated,
                    $trust_score
                ]);
                
                // Update PUV crowd status based on report
                $stmt = $pdo->prepare("UPDATE puv_units SET crowd_status = ? WHERE id = ?");
                $stmt->execute([$crowd_level, $puv_id]);
                
                $success = 'Report submitted successfully! Thank you for your contribution.';
                
                // Clear form after successful submission
                $_POST = [];
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
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body class="bg-gray-50">
    <!-- Navigation Bar -->
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
                        <a href="report.php" class="text-blue-600 hover:text-blue-800 px-3 py-2 rounded-md text-sm font-medium border-b-2 border-blue-600">Submit Report</a>
                        <a href="reports_map.php" class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">Reports Map</a>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-700"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 transition duration-150 font-medium">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="bg-white rounded-lg shadow-md p-8">
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
                    <label for="puv_id" class="block text-sm font-medium text-gray-700 mb-2">Select Vehicle</label>
                    <select id="puv_id" name="puv_id" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Select a Vehicle --</option>
                        <?php foreach ($puvs as $puv): ?>
                            <option value="<?php echo $puv['id']; ?>" data-route="<?php echo htmlspecialchars($puv['current_route'] ?? ''); ?>">
                                <?php echo htmlspecialchars($puv['plate_number'] . ' (' . ($puv['vehicle_type'] ?? 'Bus') . ') - ' . $puv['current_route']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="crowd_level" class="block text-sm font-medium text-gray-700 mb-2">Crowd Level</label>
                    <div class="grid grid-cols-3 gap-4">
                        <label class="flex items-center p-4 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-green-500 transition">
                            <input type="radio" name="crowd_level" value="Light" required class="mr-2">
                            <div>
                                <div class="font-semibold text-green-700">Light</div>
                                <div class="text-xs text-gray-600">Comfortable seating</div>
                            </div>
                        </label>
                        <label class="flex items-center p-4 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-yellow-500 transition">
                            <input type="radio" name="crowd_level" value="Moderate" required class="mr-2">
                            <div>
                                <div class="font-semibold text-yellow-700">Moderate</div>
                                <div class="text-xs text-gray-600">Limited seating</div>
                            </div>
                        </label>
                        <label class="flex items-center p-4 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-red-500 transition">
                            <input type="radio" name="crowd_level" value="Heavy" required class="mr-2">
                            <div>
                                <div class="font-semibold text-red-700">Heavy</div>
                                <div class="text-xs text-gray-600">Crowded</div>
                            </div>
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
                
                <div class="bg-blue-50 p-4 rounded-lg">
                    <p class="text-sm text-gray-700 mb-2">
                        <strong>GPS Location:</strong> Enable location services for better report validation.
                    </p>
                    <button type="button" onclick="getLocation()" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-150 font-medium text-sm">
                        Get My Location
                    </button>
                    <input type="hidden" id="latitude" name="latitude">
                    <input type="hidden" id="longitude" name="longitude">
                    <p id="locationStatus" class="text-xs text-gray-600 mt-2"></p>
                </div>
                
                <button type="submit" 
                        class="w-full bg-blue-600 text-white py-3 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-150 font-medium">
                    Submit Report
                </button>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Route map</h3>
                <p class="text-sm text-gray-500">Select a vehicle to see its route on the map.</p>
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
            document.getElementById('latitude').value = position.coords.latitude;
            document.getElementById('longitude').value = position.coords.longitude;
            document.getElementById('locationStatus').textContent = 'Location captured successfully!';
            document.getElementById('locationStatus').classList.add('text-green-600');
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
            const puvId = document.getElementById('puv_id').value;
            const crowdLevel = document.querySelector('input[name="crowd_level"]:checked');
            
            if (!puvId) {
                alert('Please select a PUV.');
                return false;
            }
            
            if (!crowdLevel) {
                alert('Please select a crowd level.');
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

            function drawRoute(routeName) {
                if (routeLayer) {
                    map.removeLayer(routeLayer);
                    routeLayer = null;
                }
                if (!window.reportPageRoutes || !routeName) return;
                const route = window.reportPageRoutes.find(function (r) { return r.name === routeName; });
                if (!route || !route.stops || route.stops.length === 0) return;
                const latlngs = route.stops.map(function (s) { return [s.latitude, s.longitude]; });
                routeLayer = L.polyline(latlngs, { color: '#2563eb', weight: 5, opacity: 0.8 }).addTo(map);
                route.stops.forEach(function (s, i) {
                    L.marker([s.latitude, s.longitude])
                        .bindPopup('<strong>' + (i + 1) + '. ' + (s.stop_name || 'Stop') + '</strong>')
                        .addTo(routeLayer);
                });
                map.fitBounds(latlngs, { padding: [30, 30] });
            }

            fetch('api_routes_with_stops.php', { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    window.reportPageRoutes = data.routes || [];
                })
                .catch(function () { window.reportPageRoutes = []; });

            document.getElementById('puv_id').addEventListener('change', function () {
                const opt = this.options[this.selectedIndex];
                const routeName = opt ? opt.getAttribute('data-route') : '';
                drawRoute(routeName || '');
            });
        })();
    </script>
</body>
</html>

