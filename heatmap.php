<?php
/**
 * Crowdsourcing Heatmap Page
 * Displays crowdsourced demand map with legend
 * Restricted to Admin role only
 */

session_start();
require_once 'db.php';

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id'])) {
    header('Location: admin_login.php');
    exit;
}
if ($_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

// Fetch reports with GPS coordinates for heatmap
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT r.id, r.crowd_level, r.latitude, r.longitude, r.timestamp,
               p.plate_number, p.current_route
        FROM reports r
        LEFT JOIN puv_units p ON r.puv_id = p.id
        WHERE r.latitude IS NOT NULL AND r.longitude IS NOT NULL
        ORDER BY r.timestamp DESC
        LIMIT 100
    ");
    $reports = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Heatmap error: " . $e->getMessage());
    $reports = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crowdsourcing Heatmap - Transport Operations System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50">
    <div class="flex flex-col md:flex-row min-h-screen">
        <!-- Sidebar -->
        <aside class="w-full md:w-64 bg-gradient-to-b from-gray-800 to-gray-900 text-white flex flex-col shadow-2xl">
            <div class="px-4 py-4 sm:p-6 flex-shrink-0 border-b border-gray-700 md:border-b-0">
                <div id="adminNavToggle" class="flex items-center justify-between md:justify-start mb-4 md:mb-8 cursor-pointer md:cursor-default">
                    <div class="bg-blue-600 p-2 rounded-lg mr-3">
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
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Fleet Overview
                    </a>
                    <a href="admin_reports.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                       class="flex items-center px-4 py-3 bg-blue-600 rounded-lg hover:bg-blue-700 transition duration-150 shadow-lg">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                </nav>
            </div>
            <div id="adminNavFooter" class="mt-auto p-4 sm:p-6 border-t border-gray-700 hidden md:block">
                <div class="bg-gray-700 rounded-lg p-3 sm:p-4 mb-4">
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

        <!-- Main Content -->
        <main class="flex-1 flex flex-col w-full">
            <!-- Header -->
            <div class="bg-white shadow-sm border-b border-gray-200 p-6">
                <h2 class="text-3xl font-bold text-gray-800">Crowdsourcing Heatmap</h2>
                <p class="text-gray-600 mt-2">Visualize crowdsourced demand and crowding levels across routes</p>
            </div>

            <!-- Map Container and Legend -->
            <div class="flex-1 flex overflow-hidden">
                <!-- Map -->
                <div class="flex-1 relative">
                    <div id="map" class="w-full h-full"></div>
                </div>

                <!-- Sidebar Legend -->
                <div class="w-full md:w-80 bg-white shadow-2xl border-l-0 md:border-l-4 border-blue-500 p-4 sm:p-6 overflow-y-auto">
                    <div class="flex items-center mb-6">
                        <div class="bg-blue-100 p-2 rounded-lg mr-3">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800">Crowding Levels</h3>
                    </div>
                    
                    <div class="space-y-4">
                        <!-- Light Crowding -->
                        <div class="border-l-4 border-green-500 pl-4 py-4 bg-gradient-to-r from-green-50 to-green-100 rounded-r-lg shadow-md hover:shadow-lg transition duration-300">
                            <div class="flex items-center mb-2">
                                <div class="w-5 h-5 bg-green-500 rounded-full mr-3 shadow-md"></div>
                                <h4 class="font-bold text-gray-800 text-lg">Light</h4>
                            </div>
                            <p class="text-sm text-gray-700 leading-relaxed">
                                Low passenger density. Comfortable seating available. Optimal travel conditions.
                            </p>
                        </div>

                        <!-- Moderate Crowding -->
                        <div class="border-l-4 border-yellow-500 pl-4 py-4 bg-gradient-to-r from-yellow-50 to-yellow-100 rounded-r-lg shadow-md hover:shadow-lg transition duration-300">
                            <div class="flex items-center mb-2">
                                <div class="w-5 h-5 bg-yellow-500 rounded-full mr-3 shadow-md"></div>
                                <h4 class="font-bold text-gray-800 text-lg">Moderate</h4>
                            </div>
                            <p class="text-sm text-gray-700 leading-relaxed">
                                Moderate passenger density. Limited seating available. Standing room available.
                            </p>
                        </div>

                        <!-- Heavy Crowding -->
                        <div class="border-l-4 border-red-500 pl-4 py-4 bg-gradient-to-r from-red-50 to-red-100 rounded-r-lg shadow-md hover:shadow-lg transition duration-300">
                            <div class="flex items-center mb-2">
                                <div class="w-5 h-5 bg-red-500 rounded-full mr-3 shadow-md animate-pulse"></div>
                                <h4 class="font-bold text-gray-800 text-lg">Heavy</h4>
                            </div>
                            <p class="text-sm text-gray-700 leading-relaxed">
                                High passenger density. No seating available. Crowded conditions. May experience delays.
                            </p>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="mt-8 pt-6 border-t-2 border-gray-300">
                        <div class="flex items-center mb-4">
                            <div class="bg-indigo-100 p-2 rounded-lg mr-3">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h4 class="font-bold text-gray-800 text-lg">About This Map</h4>
                        </div>
                        <div class="bg-blue-50 rounded-lg p-4 mb-4 border border-blue-200">
                            <p class="text-sm text-gray-700 mb-3 leading-relaxed">
                                This heatmap visualizes real-time crowdsourced data from commuters and drivers, 
                                helping identify high-demand routes and optimize fleet operations.
                            </p>
                            <p class="text-sm text-gray-700 leading-relaxed">
                                Data is collected from user reports and automatically updated to reflect current 
                                transportation conditions across the network.
                            </p>
                        </div>
                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <span>Last updated: <?php echo date('M d, Y H:i'); ?></span>
                            <span class="flex items-center">
                                <div class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></div>
                                Live
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Initialize map
        const map = L.map('map').setView([14.5995, 120.9842], 12);
        
        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Reports data from PHP
        const reports = <?php echo json_encode($reports); ?>;
        
        if (reports.length === 0) {
            // Show message if no reports
            const noDataDiv = document.createElement('div');
            noDataDiv.className = 'absolute inset-0 flex items-center justify-center bg-gray-100 bg-opacity-90 z-50';
            noDataDiv.innerHTML = `
                <div class="text-center p-8 bg-white rounded-lg shadow-lg">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                    </svg>
                    <p class="text-xl font-semibold text-gray-700 mb-2">No Reports Available</p>
                    <p class="text-sm text-gray-500">Reports with GPS coordinates will appear here</p>
                </div>
            `;
            document.getElementById('map').appendChild(noDataDiv);
        } else {
            // Add markers for each report
            const bounds = [];
            
            reports.forEach(report => {
                if (report.latitude && report.longitude) {
                    const lat = parseFloat(report.latitude);
                    const lng = parseFloat(report.longitude);
                    
                    // Determine marker color based on crowd level
                    let markerColor = 'gray';
                    if (report.crowd_level === 'Light') {
                        markerColor = 'green';
                    } else if (report.crowd_level === 'Moderate') {
                        markerColor = 'yellow';
                    } else if (report.crowd_level === 'Heavy') {
                        markerColor = 'red';
                    }
                    
                    const marker = L.marker([lat, lng], {
                        icon: L.divIcon({
                            className: 'custom-marker',
                            html: `<div style="background-color: ${markerColor}; width: 20px; height: 20px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>`,
                            iconSize: [20, 20]
                        })
                    }).addTo(map);
                    
                    const timestamp = new Date(report.timestamp).toLocaleString();
                    marker.bindPopup(`
                        <strong>${report.plate_number || 'Unknown'}</strong><br>
                        Route: ${report.current_route || 'N/A'}<br>
                        Crowd Level: <strong>${report.crowd_level}</strong><br>
                        Reported: ${timestamp}
                    `);
                    
                    bounds.push([lat, lng]);
                }
            });
            
            // Fit map to show all markers
            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [50, 50] });
            }
        }

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
        })();
    </script>
</body>
</html>

