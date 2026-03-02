<?php
require_once 'auth_helper.php';
secureSessionStart();
require_once 'db.php';
require_once 'trust_helper.php';
require_once 'trust_badge_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_profile_data = ['profile_image' => null];
$selectedRoute = isset($_GET['route']) ? $_GET['route'] : '';
try {
    $pdo = getDBConnection();
    
    // Get all available routes for filtering
    $routesStmt = $pdo->query("
        SELECT DISTINCT COALESCE(rd.name, p.current_route) as route_name
        FROM reports r
        LEFT JOIN route_definitions rd ON r.route_definition_id = rd.id
        LEFT JOIN puv_units p ON r.puv_id = p.id
        WHERE r.latitude IS NOT NULL AND r.longitude IS NOT NULL
        AND (rd.name IS NOT NULL OR p.current_route IS NOT NULL)
        ORDER BY route_name
    ");
    $availableRoutes = $routesStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Build reports query with route filter
    $whereClause = "WHERE r.latitude IS NOT NULL AND r.longitude IS NOT NULL";
    $params = [];
    
    if (!empty($selectedRoute)) {
        $whereClause .= " AND (rd.name = ? OR p.current_route = ?)";
        $params[] = $selectedRoute;
        $params[] = $selectedRoute;
    }
    
    $stmt = $pdo->prepare("
        SELECT r.id, r.crowd_level, r.delay_reason, r.timestamp, r.latitude, r.longitude,
               r.is_verified, r.peer_verifications, r.status,
               u.id as user_id, u.name as user_name, u.trust_score,
               COALESCE(rd.name, p.current_route) AS route_name
        FROM reports r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN route_definitions rd ON r.route_definition_id = rd.id
        LEFT JOIN puv_units p ON r.puv_id = p.id
        $whereClause
        ORDER BY r.timestamp DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Enhance reports with trust badge information
    foreach ($reports as &$report) {
        if ($report['user_id']) {
            $report['trust_score'] = $report['trust_score'] ?? 50.0;
            $report['trust_badge'] = getTrustBadge($report['trust_score']);
        } else {
            $report['trust_score'] = 50.0;
            $report['trust_badge'] = getTrustBadge(50.0);
        }
    }
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
    <style>.brand-font { font-family: 'Poppins', system-ui, sans-serif; letter-spacing: 0.02em; }
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }
        .verifiable-marker { animation: pulse 2s infinite; }
        .selected-marker { animation: pulse 2s infinite; }
    </style>
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
                    <p class="text-sm text-gray-600">Tap a marker to see report details. Green border = verified report. Blue circle = your 500m verification radius.</p>
                </div>
                <div class="h-[500px] lg:h-[600px]" id="map"></div>
            </div>

            <div class="bg-white rounded-2xl shadow-md p-4 space-y-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Recent Reports</h3>
                    
                    <!-- Route Filter -->
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Filter by Route</label>
                        <select id="routeFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                            <option value="">All Routes</option>
                            <?php foreach ($availableRoutes as $route): ?>
                                <option value="<?php echo htmlspecialchars($route); ?>" <?php echo $selectedRoute === $route ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($route); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Legend -->
                    <div class="space-y-1 text-xs text-gray-700">
                        <div class="flex items-center">
                            <span class="w-3 h-3 rounded-full bg-green-500 border border-white shadow mr-2"></span>
                            <span>Light crowd</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-3 h-3 rounded-full bg-yellow-400 border border-white shadow mr-2"></span>
                            <span>Moderate crowd</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-3 h-3 rounded-full bg-red-500 border border-white shadow mr-2"></span>
                            <span>Heavy crowd</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-3 h-3 rounded border-2 border-green-500 mr-2"></span>
                            <span>Verified report</span>
                        </div>
                    </div>
                </div>
                
                <!-- Reports List -->
                <div class="flex-1 overflow-y-auto p-4 max-h-96">
                    <div id="reportsList" class="space-y-3">
                        <!-- Reports will be populated by JavaScript -->
                    </div>
                </div>
                
                <div class="border-t pt-4">
                    <h3 class="text-sm font-semibold text-gray-800 mb-2">Verification</h3>
                    <p class="text-xs text-gray-600 mb-3">
                        When you are near a reported location, you can help verify it. 
                        A report becomes fully verified after 3 independent verifications.
                    </p>
                    <button id="enableLocationBtn"
                            class="w-full bg-blue-600 text-white px-3 py-2 rounded-md hover:bg-blue-700 transition text-xs font-medium">
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
        let selectedReportId = null;
        let reportMarkers = new Map(); // Store markers by report ID

        const map = L.map('map').setView([14.5995, 120.9842], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        let userLocation = null;
        let userLocationMarker = null;
        let verificationCircle = null;

        function getIconColor(crowdLevel) {
            if (crowdLevel === 'Light') return 'green';
            if (crowdLevel === 'Moderate') return 'yellow';
            return 'red';
        }

        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371; // Earth's radius in km
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                    Math.sin(dLon/2) * Math.sin(dLon/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c; // Distance in km
        }

        function highlightReport(reportId) {
            // Remove previous highlight
            document.querySelectorAll('.report-item').forEach(item => {
                item.classList.remove('ring-2', 'ring-blue-500', 'bg-blue-50');
            });
            
            // Add highlight to selected report
            const selectedElement = document.querySelector(`[data-report-id="${reportId}"]`);
            if (selectedElement) {
                selectedElement.classList.add('ring-2', 'ring-blue-500', 'bg-blue-50');
                selectedElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            
            // Highlight marker on map
            if (reportMarkers.has(reportId)) {
                const marker = reportMarkers.get(reportId);
                marker.openPopup();
                
                // Pan map to marker
                const report = reports.find(r => r.id === reportId);
                if (report && report.latitude && report.longitude) {
                    map.setView([parseFloat(report.latitude), parseFloat(report.longitude)], 14);
                }
            }
        }

        function renderReportsList() {
            const reportsList = document.getElementById('reportsList');
            reportsList.innerHTML = '';
            
            if (reports.length === 0) {
                reportsList.innerHTML = '<p class="text-gray-500 text-sm text-center py-4">No reports found.</p>';
                return;
            }
            
            reports.forEach(report => {
                const isVerified = parseInt(report.is_verified, 10) === 1;
                const crowdColor = getIconColor(report.crowd_level);
                const timestamp = new Date(report.timestamp).toLocaleString();
                const isSelected = report.id === selectedReportId;
                
                const reportElement = document.createElement('div');
                reportElement.className = `report-item bg-white border border-gray-200 rounded-lg p-3 hover:shadow-md transition-shadow cursor-pointer ${isSelected ? 'ring-2 ring-blue-500 bg-blue-50' : ''}`;
                reportElement.setAttribute('data-report-id', report.id);
                
                // Expanded view when selected
                if (isSelected) {
                    reportElement.innerHTML = `
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center">
                                <span class="w-4 h-4 rounded-full bg-${crowdColor}-500 border border-white shadow mr-2"></span>
                                <span class="text-sm font-bold text-gray-900">#${report.id}</span>
                                ${isVerified ? '<span class="ml-2 px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full font-medium">Verified</span>' : ''}
                            </div>
                            <span class="text-sm font-medium text-gray-500">${report.peer_verifications || 0}/3 verifications</span>
                        </div>
                        <div class="space-y-2">
                            <div class="text-base font-bold text-gray-900">${report.route_name || 'Unknown Route'}</div>
                            <div class="text-sm text-gray-600">
                                <span class="inline-block px-2 py-1 bg-${crowdColor}-100 text-${crowdColor}-800 rounded font-medium">
                                    ${report.crowd_level} Crowd
                                </span>
                            </div>
                            ${report.delay_reason ? `
                                <div class="text-sm text-gray-700 bg-gray-50 p-2 rounded">
                                    <span class="font-medium">Delay Reason:</span> ${report.delay_reason}
                                </div>
                            ` : ''}
                            <div class="text-sm text-gray-600 space-y-1">
                                <div class="flex items-center justify-between">
                                    <span><strong>Reported by:</strong> ${report.user_name || 'Anonymous'}</span>
                                    ${report.user_id ? `<span class="inline-block ${report.trust_badge.bg_color} ${report.trust_badge.text_color} ${report.trust_badge.border_color} px-2 py-1 rounded-full text-xs font-medium border">${report.trust_badge.label} (${report.trust_score})</span>` : ''}
                                </div>
                                <div><strong>Time:</strong> ${timestamp}</div>
                                ${report.latitude && report.longitude ? `
                                    <div><strong>Location:</strong> ${parseFloat(report.latitude).toFixed(4)}, ${parseFloat(report.longitude).toFixed(4)}</div>
                                ` : ''}
                                <div><strong>Status:</strong> ${isVerified ? 'Verified' : 'Pending verification'}</div>
                            </div>
                            ${userRole === 'Commuter' && !isVerified ? `
                                <div class="mt-3 pt-3 border-t border-gray-200">
                                    <button class="verify-on-map-btn w-full bg-blue-600 text-white px-3 py-2 rounded-md hover:bg-blue-700 transition text-sm font-medium">
                                        📍 View on Map to Verify
                                    </button>
                                </div>
                            ` : ''}
                        </div>
                    `;
                } else {
                    // Compact view when not selected
                    reportElement.innerHTML = `
                        <div class="flex items-start justify-between mb-2">
                            <div class="flex items-center">
                                <span class="w-3 h-3 rounded-full bg-${crowdColor}-500 border border-white shadow mr-2"></span>
                                <span class="text-xs font-medium text-gray-900">#${report.id}</span>
                                ${isVerified ? '<span class="ml-2 px-2 py-0.5 bg-green-100 text-green-800 text-xs rounded-full">Verified</span>' : ''}
                            </div>
                            <span class="text-xs text-gray-500">${report.peer_verifications || 0}/3</span>
                        </div>
                        <div class="space-y-1">
                            <div class="text-sm font-medium text-gray-900">${report.route_name || 'Unknown Route'}</div>
                            <div class="text-xs text-gray-600">
                                <span class="inline-block px-2 py-0.5 bg-${crowdColor}-100 text-${crowdColor}-800 rounded">
                                    ${report.crowd_level}
                                </span>
                            </div>
                            ${report.delay_reason ? `<div class="text-xs text-gray-700 italic">${report.delay_reason}</div>` : ''}
                            <div class="text-xs text-gray-500">
                                <div>${report.user_name || 'Anonymous'}</div>
                                <div>${timestamp}</div>
                            </div>
                        </div>
                    `;
                }
                
                reportElement.addEventListener('click', () => {
                    selectedReportId = report.id;
                    highlightReport(report.id);
                });
                
                reportsList.appendChild(reportElement);
            });
        }

        function addReportMarkers() {
            // Clear existing markers
            reportMarkers.forEach(marker => {
                map.removeLayer(marker);
            });
            reportMarkers.clear();
            
            const bounds = [];
            reports.forEach(r => {
                if (!r.latitude || !r.longitude) return;
                const lat = parseFloat(r.latitude);
                const lng = parseFloat(r.longitude);
                const color = getIconColor(r.crowd_level);
                const isVerified = parseInt(r.is_verified, 10) === 1;
                const isSelected = r.id === selectedReportId;

                // Highlight based on verification eligibility
                const borderColor = isVerified ? 'green' : (isSelected ? '#3B82F6' : 'white');
                const iconSize = isSelected ? 28 : 20; // Larger if selected

                const marker = L.marker([lat, lng], {
                    icon: L.divIcon({
                        className: isSelected ? 'selected-marker' : 'custom-marker',
                        html: `
                            <div style="
                                background-color: ${color};
                                width: ${iconSize}px;
                                height: ${iconSize}px;
                                border-radius: 50%;
                                border: 3px solid ${borderColor};
                                box-shadow: 0 2px 4px rgba(0,0,0,0.3);
                                ${isSelected ? 'animation: pulse 2s infinite;' : ''}
                            "></div>
                        `,
                        iconSize: [iconSize, iconSize]
                    })
                }).addTo(map);

                const timestamp = new Date(r.timestamp).toLocaleString();

                marker.bindPopup(`
                    <div class="text-sm">
                        <strong>Route:</strong> ${r.route_name || 'N/A'}<br>
                        Crowd: ${r.crowd_level}<br>
                        <strong>Reported by:</strong> ${r.user_id ? `<a href="public_profile.php?id=${r.user_id}" class="text-blue-600 hover:text-blue-800 underline">${r.user_name || 'Unknown'}</a>` : (r.user_name || 'Unknown')}<br>
                        ${r.user_id ? `<span class="inline-block ${r.trust_badge.bg_color} ${r.trust_badge.text_color} ${r.trust_badge.border_color} px-2 py-1 rounded-full text-xs font-medium border">${r.trust_badge.label} (${r.trust_score})</span><br>` : ''}
                        Time: ${timestamp}<br>
                        Verified: ${isVerified ? 'Yes' : 'No'} (${r.peer_verifications || 0}/3)<br>
                        ${r.delay_reason ? 'Delay: ' + r.delay_reason + '<br>' : ''}
                    </div>
                `);

                marker.on('click', () => {
                    selectedReportId = r.id;
                    highlightReport(r.id);
                });

                reportMarkers.set(r.id, marker);
                bounds.push([lat, lng]);
            });

            if (bounds.length > 0 && !selectedReportId) {
                map.fitBounds(bounds, { padding: [40, 40] });
            }
        }

        function updateUserLocationOnMap(lat, lng) {
            // Remove old user location marker and circle
            if (userLocationMarker) {
                map.removeLayer(userLocationMarker);
            }
            if (verificationCircle) {
                map.removeLayer(verificationCircle);
            }

            // Add user location marker
            userLocationMarker = L.marker([lat, lng], {
                icon: L.divIcon({
                    className: 'user-location-marker',
                    html: `
                        <div style="
                            background-color: #3B82F6;
                            width: 16px;
                            height: 16px;
                            border-radius: 50%;
                            border: 3px solid white;
                            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
                            z-index: 1000;
                        "></div>
                    `,
                    iconSize: [16, 16]
                })
            }).addTo(map);

            // Add verification circle (500m radius)
            verificationCircle = L.circle([lat, lng], {
                radius: 500, // 500 meters
                color: '#3B82F6',
                fillColor: '#3B82F6',
                fillOpacity: 0.1,
                weight: 2,
                dashArray: '5, 10'
            }).addTo(map);

            // Update report markers with distance information
            updateReportMarkersWithDistance();
        }

        function updateReportMarkersWithDistance() {
            if (!userLocation) return;

            // Clear existing markers
            map.eachLayer(layer => {
                if (layer instanceof L.Marker && layer !== userLocationMarker) {
                    map.removeLayer(layer);
                }
            });

            const bounds = [];
            reports.forEach(r => {
                if (!r.latitude || !r.longitude) return;
                const lat = parseFloat(r.latitude);
                const lng = parseFloat(r.longitude);
                const color = getIconColor(r.crowd_level);
                const isVerified = parseInt(r.is_verified, 10) === 1;

                // Calculate distance
                const distance = calculateDistance(userLocation.latitude, userLocation.longitude, lat, lng);
                const canVerify = distance <= 0.5; // Within 500m

                // Highlight based on verification eligibility
                const borderColor = isVerified ? 'green' : (canVerify ? '#3B82F6' : 'white');
                const iconSize = canVerify ? 24 : 20; // Larger if can verify

                const marker = L.marker([lat, lng], {
                    icon: L.divIcon({
                        className: canVerify ? 'verifiable-marker' : 'custom-marker',
                        html: `
                            <div style="
                                background-color: ${color};
                                width: ${iconSize}px;
                                height: ${iconSize}px;
                                border-radius: 50%;
                                border: 3px solid ${borderColor};
                                box-shadow: 0 2px 4px rgba(0,0,0,0.3);
                            "></div>
                        `,
                        iconSize: [iconSize, iconSize]
                    })
                }).addTo(map);

                const timestamp = new Date(r.timestamp).toLocaleString();

                const verifyButton = (userRole === 'Commuter' && canVerify)
                    ? `<button data-report-id="${r.id}" class="verify-btn mt-2 px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700">
                            Verify this report (${distance.toFixed(1)}km away)
                       </button>`
                    : (canVerify ? `<span class="text-xs text-blue-600 font-medium mt-2 block">${distance.toFixed(1)}km away</span>` : '');

                marker.bindPopup(`
                    <div class="text-sm">
                        <strong>Route:</strong> ${r.route_name || 'N/A'}<br>
                        Crowd: ${r.crowd_level}<br>
                        <strong>Reported by:</strong> ${r.user_id ? `<a href="public_profile.php?id=${r.user_id}" class="text-blue-600 hover:text-blue-800 underline">${r.user_name || 'Unknown'}</a>` : (r.user_name || 'Unknown')}<br>
                        ${r.user_id ? `<span class="inline-block ${r.trust_badge.bg_color} ${r.trust_badge.text_color} ${r.trust_badge.border_color} px-2 py-1 rounded-full text-xs font-medium border">${r.trust_badge.label} (${r.trust_score})</span><br>` : ''}
                        Time: ${timestamp}<br>
                        Verified: ${isVerified ? 'Yes' : 'No'} (${r.peer_verifications || 0}/3)<br>
                        ${r.delay_reason ? 'Delay: ' + r.delay_reason + '<br>' : ''}
                        ${canVerify ? '<span class="text-green-600 font-medium">✓ You can verify this report (within 500m)</span>' : ''}
                        ${verifyButton}
                    </div>
                `);

                bounds.push([lat, lng]);
            });

            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [40, 40] });
            }
        }

        // Initialize the page
        renderReportsList();
        addReportMarkers();

        // Route filter functionality
        const routeFilter = document.getElementById('routeFilter');
        if (routeFilter) {
            routeFilter.addEventListener('change', (e) => {
                const selectedRoute = e.target.value;
                const url = new URL(window.location);
                if (selectedRoute) {
                    url.searchParams.set('route', selectedRoute);
                } else {
                    url.searchParams.delete('route');
                }
                window.location.href = url.toString();
            });
        }

        const enableLocationBtn = document.getElementById('enableLocationBtn');
        const locationStatus = document.getElementById('locationStatus');

        if (enableLocationBtn) {
            enableLocationBtn.addEventListener('click', () => {
                // Check for secure connection requirement
                if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
                    if (locationStatus) {
                        locationStatus.textContent = 'Location requires HTTPS. Please use a secure connection or access via localhost.';
                        locationStatus.classList.add('text-red-600');
                    }
                    return;
                }
                
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
                        updateUserLocationOnMap(userLocation.latitude, userLocation.longitude);
                        locationStatus.textContent = 'Location enabled. Blue circle shows 500m verification radius.';
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

