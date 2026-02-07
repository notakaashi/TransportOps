<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT r.id, r.crowd_level, r.delay_reason, r.timestamp, r.latitude, r.longitude,
               r.is_verified, r.peer_verifications,
               u.name as user_name,
               COALESCE(rd.name, p.current_route) AS route_name
        FROM reports r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN route_definitions rd ON r.route_definition_id = rd.id
        LEFT JOIN puv_units p ON r.puv_id = p.id
        WHERE r.latitude IS NOT NULL AND r.longitude IS NOT NULL
        ORDER BY r.timestamp DESC
        LIMIT 100
    ");
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Reports map error: ' . $e->getMessage());
    $reports = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Map - Transport Operations System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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
                        <a href="reports_map.php" class="text-blue-600 hover:text-blue-800 px-3 py-2 rounded-md text-sm font-medium border-b-2 border-blue-600">Reports Map</a>
                        <a href="routes.php" class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">Routes</a>
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
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <div class="lg:col-span-3 bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-2xl font-semibold text-gray-800">Reports Map</h2>
                    <p class="text-sm text-gray-600">Tap a marker to see report details. Green border = verified report.</p>
                </div>
                <div class="h-[500px] lg:h-[600px]" id="map"></div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-4 space-y-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Legend</h3>
                    <div class="space-y-2 text-sm text-gray-700">
                        <div class="flex items-center">
                            <span class="w-4 h-4 rounded-full bg-green-500 border-2 border-white shadow mr-2"></span>
                            <span>Light crowd</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-4 h-4 rounded-full bg-yellow-400 border-2 border-white shadow mr-2"></span>
                            <span>Moderate crowd</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-4 h-4 rounded-full bg-red-500 border-2 border-white shadow mr-2"></span>
                            <span>Heavy crowd</span>
                        </div>
                        <div class="flex items-center mt-2">
                            <span class="w-4 h-4 rounded-full border-2 border-green-500 mr-2"></span>
                            <span>Verified report (3+ verifications)</span>
                        </div>
                    </div>
                </div>

                <div class="border-t pt-4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Verification</h3>
                    <p class="text-sm text-gray-600 mb-3">
                        When you are near a reported location, you can help verify it. 
                        A report becomes fully verified after 3 independent verifications.
                    </p>
                    <button id="enableLocationBtn"
                            class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition text-sm font-medium">
                        Enable My Location for Verification
                    </button>
                    <p id="locationStatus" class="text-xs text-gray-500 mt-2"></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const reports = <?php echo json_encode($reports); ?>;
        const userRole = <?php echo json_encode($_SESSION['role']); ?>;

        const map = L.map('map').setView([14.5995, 120.9842], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        let userLocation = null;

        function getIconColor(crowdLevel) {
            if (crowdLevel === 'Light') return 'green';
            if (crowdLevel === 'Moderate') return 'yellow';
            return 'red';
        }

        function addReportMarkers() {
            const bounds = [];
            reports.forEach(r => {
                if (!r.latitude || !r.longitude) return;
                const lat = parseFloat(r.latitude);
                const lng = parseFloat(r.longitude);
                const color = getIconColor(r.crowd_level);
                const isVerified = parseInt(r.is_verified, 10) === 1;

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
                }).addTo(map);

                const timestamp = new Date(r.timestamp).toLocaleString();

                const verifyButton = (userRole === 'Commuter')
                    ? `<button data-report-id="${r.id}" class="verify-btn mt-2 px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700">
                            Verify this report
                       </button>`
                    : '';

                marker.bindPopup(`
                    <div class="text-sm">
                        <strong>Route:</strong> ${r.route_name || 'N/A'}<br>
                        Crowd: ${r.crowd_level}<br>
                        Reported by: ${r.user_name || 'Unknown'}<br>
                        Time: ${timestamp}<br>
                        Verified: ${isVerified ? 'Yes' : 'No'} (${r.peer_verifications || 0}/3)<br>
                        ${r.delay_reason ? 'Delay: ' + r.delay_reason + '<br>' : ''}
                        ${verifyButton}
                    </div>
                `);

                bounds.push([lat, lng]);
            });

            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [40, 40] });
            }
        }

        addReportMarkers();

        const enableLocationBtn = document.getElementById('enableLocationBtn');
        const locationStatus = document.getElementById('locationStatus');

        if (enableLocationBtn) {
            enableLocationBtn.addEventListener('click', () => {
                if (!navigator.geolocation) {
                    locationStatus.textContent = 'Geolocation is not supported by this browser.';
                    locationStatus.classList.add('text-red-600');
                    return;
                }
                locationStatus.textContent = 'Getting your location...';
                navigator.geolocation.getCurrentPosition(
                    pos => {
                        userLocation = {
                            latitude: pos.coords.latitude,
                            longitude: pos.coords.longitude
                        };
                        locationStatus.textContent = 'Location enabled. You can now verify reports near you.';
                        locationStatus.classList.remove('text-red-600');
                        locationStatus.classList.add('text-green-600');
                    },
                    err => {
                        locationStatus.textContent = 'Unable to get location: ' + err.message;
                        locationStatus.classList.add('text-red-600');
                    }
                );
            });
        }

        document.addEventListener('click', async (e) => {
            if (!e.target.classList.contains('verify-btn')) return;

            if (!userLocation) {
                alert('Please enable your location first using the button on the right.');
                return;
            }

            const reportId = e.target.getAttribute('data-report-id');
            try {
                const formData = new FormData();
                formData.append('report_id', reportId);
                formData.append('latitude', userLocation.latitude);
                formData.append('longitude', userLocation.longitude);

                const res = await fetch('verify_report.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const data = await res.json();
                if (!res.ok || !data.success) {
                    alert(data.error || 'Verification failed.');
                    return;
                }

                alert(
                    `Thank you! Your verification was recorded.\n` +
                    `Total verifications: ${data.peer_verifications}/3\n` +
                    `Fully verified: ${data.is_verified ? 'Yes' : 'No'}`
                );
                window.location.reload();
            } catch (err) {
                console.error(err);
                alert('An error occurred while verifying the report.');
            }
        });
    </script>
</body>
</html>

