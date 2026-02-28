<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_profile_data = ['profile_image' => null];
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
    // Fetch profile image for nav
    $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['profile_image']) {
        $user_profile_data['profile_image'] = $row['profile_image'];
        $_SESSION['profile_image'] = $row['profile_image'];
    }
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <style>.brand-font { font-family: 'Poppins', system-ui, sans-serif; letter-spacing: 0.02em; }</style>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body class="bg-[#F3F4F6] min-h-screen">
    <nav class="fixed top-0 inset-x-0 z-30 bg-[#1E3A8A] text-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-8">
                    <a href="index.php" id="brandLink" class="brand-font text-xl sm:text-2xl font-bold text-white whitespace-nowrap">Transport Ops</a>
                    <div class="hidden md:flex space-x-4">
                        <a href="user_dashboard.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Home</a>
                        <a href="about.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">About</a>
                        <a href="report.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Submit Report</a>
                        <a href="reports_map.php" class="bg-blue-500 text-white px-3 py-2 rounded-md text-sm font-medium border-b-2 border-blue-800">Reports Map</a>
                        <a href="routes.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Routes</a>
                    </div>
                    <div id="mobileMenu" class="md:hidden hidden absolute top-16 left-0 right-0 bg-[#1E3A8A] text-white flex flex-col space-y-1 px-4 py-2 z-20">
                        <a href="user_dashboard.php" class="block px-3 py-2 rounded-md text-sm font-medium">Home</a>
                        <a href="about.php" class="block px-3 py-2 rounded-md text-sm font-medium">About</a>
                        <a href="report.php" class="block px-3 py-2 rounded-md text-sm font-medium">Submit Report</a>
                        <a href="reports_map.php" class="block px-3 py-2 rounded-md text-sm font-medium">Reports Map</a>
                        <a href="routes.php" class="block px-3 py-2 rounded-md text-sm font-medium">Routes</a>
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
                                <?php echo htmlspecialchars($_SESSION['role'] ?? 'User'); ?>
                            </span>
                        </div>
                        <div class="flex items-center gap-1">
                            <?php if ($user_profile_data['profile_image']): ?>
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
                        <a href="profile.php" class="block px-3 py-2 text-sm hover:bg-gray-50">View &amp; Edit Profile</a>
                        <div class="my-1 border-t border-gray-100"></div>
                        <a href="logout.php" class="block px-3 py-2 text-sm text-red-600 hover:bg-red-50">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-6">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <div class="lg:col-span-3 bg-white rounded-2xl shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-2xl font-semibold text-gray-800">Reports Map</h2>
                    <p class="text-sm text-gray-600">Tap a marker to see report details. Green border = verified report.</p>
                </div>
                <div class="h-[500px] lg:h-[600px]" id="map"></div>
            </div>

            <div class="bg-white rounded-2xl shadow-md p-4 space-y-4">
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

        // Profile menu toggle
        (function () {
            const btn = document.getElementById('profileMenuButton');
            const menu = document.getElementById('profileMenu');
            if (!btn || !menu) return;
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                menu.classList.toggle('hidden');
            });
            document.addEventListener('click', function () {
                menu.classList.add('hidden');
            });
        })();
    </script>
</body>
</html>

