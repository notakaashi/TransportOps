<?php
/**
 * Real-Time GPS Tracking Page
 * Displays real-time location of all PUV units on a map
 */

session_start();
require_once 'db.php';

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

// Fetch reports with GPS coordinates for map display
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT r.id, r.latitude as lat, r.longitude as lng, r.crowd_level, r.timestamp,
               p.plate_number, p.vehicle_type, p.current_route
        FROM reports r
        LEFT JOIN puv_units p ON r.puv_id = p.id
        WHERE r.latitude IS NOT NULL AND r.longitude IS NOT NULL
        ORDER BY r.timestamp DESC
        LIMIT 50
    ");
    $reports = $stmt->fetchAll();
    
    // Also fetch all PUVs for the list
    $stmt = $pdo->query("SELECT id, plate_number, vehicle_type, current_route, crowd_status FROM puv_units ORDER BY plate_number");
    $puvs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Tracking error: " . $e->getMessage());
    $reports = [];
    $puvs = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real-Time Tracking - Transport Operations System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
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
                       class="flex items-center px-4 py-3 bg-blue-600 rounded-lg hover:bg-blue-700 transition duration-150 shadow-lg">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Real-Time Tracking
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

        <!-- Main Content -->
        <main class="flex-1 flex flex-col">
            <!-- Header -->
            <div class="bg-white shadow-sm border-b border-gray-200 p-6">
                <h2 class="text-3xl font-bold text-gray-800">Real-Time Tracking</h2>
                <p class="text-gray-600 mt-2">View report locations on the map</p>
            </div>

            <!-- Map Container -->
            <div class="flex-1 relative">
                <div id="map" class="w-full h-full"></div>
                
                <!-- Vehicle List Sidebar -->
                <div class="absolute top-4 right-4 bg-white rounded-lg shadow-lg p-4 w-80 max-h-96 overflow-y-auto">
                    <h3 class="font-semibold text-gray-800 mb-3">Fleet Vehicles (<?php echo count($puvs); ?>)</h3>
                    <div class="space-y-2">
                        <?php if (empty($puvs)): ?>
                            <p class="text-sm text-gray-500">No vehicles in fleet.</p>
                        <?php else: ?>
                            <?php foreach ($puvs as $puv): ?>
                                <div class="p-2 border border-gray-200 rounded hover:bg-gray-50">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="font-medium text-sm text-gray-900"><?php echo htmlspecialchars($puv['plate_number']); ?></div>
                                            <div class="text-xs text-gray-600"><?php echo htmlspecialchars(($puv['vehicle_type'] ?? 'Bus') . ' - ' . $puv['current_route']); ?></div>
                                        </div>
                                        <span class="px-2 py-1 text-xs rounded-full <?php 
                                            echo $puv['crowd_status'] === 'Light' ? 'bg-green-100 text-green-800' : 
                                                ($puv['crowd_status'] === 'Moderate' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); 
                                        ?>">
                                            <?php echo htmlspecialchars($puv['crowd_status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Initialize map
        const map = L.map('map').setView([14.5995, 120.9842], 12); // Default to Manila coordinates
        
        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Reports data from PHP
        const reports = <?php echo json_encode($reports); ?>;
        const markers = {};

        // Add markers for each report
        if (reports.length > 0) {
            const bounds = [];
            reports.forEach(report => {
                if (report.lat && report.lng) {
                    const lat = parseFloat(report.lat);
                    const lng = parseFloat(report.lng);
                    
                    const iconColor = report.crowd_level === 'Light' ? 'green' : 
                                    report.crowd_level === 'Moderate' ? 'yellow' : 'red';
                    
                    const marker = L.marker([lat, lng], {
                        icon: L.divIcon({
                            className: 'custom-marker',
                            html: `<div style="background-color: ${iconColor}; width: 20px; height: 20px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>`,
                            iconSize: [20, 20]
                        })
                    }).addTo(map);
                    
                    const timestamp = new Date(report.timestamp).toLocaleString();
                    marker.bindPopup(`
                        <strong>${report.plate_number || 'Unknown'}</strong><br>
                        Type: ${report.vehicle_type || 'Bus'}<br>
                        Route: ${report.current_route || 'N/A'}<br>
                        Crowd: ${report.crowd_level}<br>
                        Reported: ${timestamp}
                    `);
                    
                    markers[report.id] = marker;
                    bounds.push([lat, lng]);
                }
            });

            // Fit map to show all markers
            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [50, 50] });
            }
        } else {
            // Show message if no reports
            const noDataDiv = document.createElement('div');
            noDataDiv.className = 'absolute inset-0 flex items-center justify-center bg-gray-100 bg-opacity-90 z-50';
            noDataDiv.innerHTML = `
                <div class="text-center p-8 bg-white rounded-lg shadow-lg">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <p class="text-xl font-semibold text-gray-700 mb-2">No Reports Available</p>
                    <p class="text-sm text-gray-500">Reports with GPS coordinates will appear here</p>
                </div>
            `;
            document.getElementById('map').appendChild(noDataDiv);
        }

        // Auto-refresh every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>

