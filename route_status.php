<?php
/**
 * Route Status Overview
 * Displays current state of each route and flags units off schedule
 */

session_start();
require_once 'db.php';

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

// Fetch route status data
try {
    $pdo = getDBConnection();
    
    // Get routes with PUV counts and status
    $stmt = $pdo->query("
        SELECT 
            current_route,
            COUNT(*) as puv_count,
            SUM(CASE WHEN crowd_status = 'Light' THEN 1 ELSE 0 END) as light_count,
            SUM(CASE WHEN crowd_status = 'Moderate' THEN 1 ELSE 0 END) as moderate_count,
            SUM(CASE WHEN crowd_status = 'Heavy' THEN 1 ELSE 0 END) as heavy_count
        FROM puv_units
        GROUP BY current_route
        ORDER BY current_route
    ");
    $routes = $stmt->fetchAll();
    
    // Get PUVs with delay reports
    $stmt = $pdo->query("
        SELECT DISTINCT p.current_route, COUNT(DISTINCT r.puv_id) as delayed_puvs
        FROM reports r
        JOIN puv_units p ON r.puv_id = p.id
        WHERE r.delay_reason IS NOT NULL AND r.delay_reason != ''
        AND r.timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        GROUP BY p.current_route
    ");
    $delayed_routes = [];
    while ($row = $stmt->fetch()) {
        $delayed_routes[$row['current_route']] = $row['delayed_puvs'];
    }
    
} catch (PDOException $e) {
    error_log("Route status error: " . $e->getMessage());
    $routes = [];
    $delayed_routes = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Route Status - Transport Operations System</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Real-Time Tracking
                    </a>
                    <a href="route_status.php" 
                       class="flex items-center px-4 py-3 bg-blue-600 rounded-lg hover:bg-blue-700 transition duration-150 shadow-lg">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                        </svg>
                        Route Status
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
                        Add User
                    </a>
                    <a href="add_puv.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Add PUV
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
        <main class="flex-1 overflow-y-auto">
            <div class="p-8">
                <!-- Page Header -->
                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-gray-800">Route Status Overview</h2>
                    <p class="text-gray-600 mt-2">Monitor route performance and identify units off schedule</p>
                </div>

                <!-- Routes Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (empty($routes)): ?>
                        <div class="col-span-full bg-white rounded-lg shadow-md p-8 text-center">
                            <p class="text-gray-500">No routes found. Add PUV units to see route status.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($routes as $route): ?>
                            <?php 
                            $has_delays = isset($delayed_routes[$route['current_route']]);
                            $delayed_count = $has_delays ? $delayed_routes[$route['current_route']] : 0;
                            $status_class = $has_delays ? 'border-red-500' : 'border-green-500';
                            ?>
                            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 <?php echo $status_class; ?>">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-xl font-semibold text-gray-800"><?php echo htmlspecialchars($route['current_route']); ?></h3>
                                    <?php if ($has_delays): ?>
                                        <span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">
                                            Delayed
                                        </span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">
                                            On Time
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">Total PUVs</span>
                                        <span class="font-semibold text-gray-800"><?php echo $route['puv_count']; ?></span>
                                    </div>
                                    
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">Light Crowding</span>
                                        <span class="font-semibold text-green-600"><?php echo $route['light_count']; ?></span>
                                    </div>
                                    
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">Moderate Crowding</span>
                                        <span class="font-semibold text-yellow-600"><?php echo $route['moderate_count']; ?></span>
                                    </div>
                                    
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">Heavy Crowding</span>
                                        <span class="font-semibold text-red-600"><?php echo $route['heavy_count']; ?></span>
                                    </div>
                                    
                                    <?php if ($has_delays): ?>
                                        <div class="pt-3 border-t border-gray-200">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-red-600 font-medium">Delayed PUVs</span>
                                                <span class="font-semibold text-red-600"><?php echo $delayed_count; ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

