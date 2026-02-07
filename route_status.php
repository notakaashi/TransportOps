<?php
/**
 * Route Status Overview
 * Displays routes from route_definitions with report counts. Select a route to edit or delete.
 */

session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

$routes = [];
$delayed_route_ids = [];
$selected_route_id = isset($_GET['route_id']) ? (int)$_GET['route_id'] : null;

try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->query("SELECT id, name, created_at FROM route_definitions ORDER BY name");
    $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($routes as &$r) {
        $rid = (int)$r['id'];
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as report_count,
                SUM(CASE WHEN crowd_level = 'Light' THEN 1 ELSE 0 END) as light_count,
                SUM(CASE WHEN crowd_level = 'Moderate' THEN 1 ELSE 0 END) as moderate_count,
                SUM(CASE WHEN crowd_level = 'Heavy' THEN 1 ELSE 0 END) as heavy_count
            FROM reports
            WHERE route_definition_id = ?
        ");
        $stmt->execute([$rid]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        $r['report_count'] = (int)($stats['report_count'] ?? 0);
        $r['light_count'] = (int)($stats['light_count'] ?? 0);
        $r['moderate_count'] = (int)($stats['moderate_count'] ?? 0);
        $r['heavy_count'] = (int)($stats['heavy_count'] ?? 0);
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as c FROM reports
            WHERE route_definition_id = ? AND delay_reason IS NOT NULL AND delay_reason != ''
            AND timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$rid]);
        $r['delayed_count'] = (int)$stmt->fetchColumn();
    }
    unset($r);
    
    $stmt = $pdo->query("
        SELECT route_definition_id, COUNT(*) as delayed_count
        FROM reports
        WHERE route_definition_id IS NOT NULL AND delay_reason IS NOT NULL AND delay_reason != ''
        AND timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        GROUP BY route_definition_id
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $delayed_route_ids[(int)$row['route_definition_id']] = (int)$row['delayed_count'];
    }
} catch (PDOException $e) {
    error_log("Route status error: " . $e->getMessage());
    $routes = [];
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
                    <a href="admin_reports.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6a2 2 0 012-2h6m-4-4l4 4-4 4"></path>
                        </svg>
                        Reports
                    </a>
                    <a href="route_status.php" 
                       class="flex items-center px-4 py-3 bg-blue-600 rounded-lg hover:bg-blue-700 transition duration-150 shadow-lg">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    <p class="text-gray-600 mt-2">Monitor routes and report counts. Select a route to edit or delete it.</p>
                </div>

                <!-- Route selector -->
                <div class="mb-6 bg-white rounded-lg shadow-md p-4">
                    <label for="routeSelect" class="block text-sm font-medium text-gray-700 mb-2">Select a route to edit or delete</label>
                    <div class="flex flex-wrap items-center gap-3">
                        <select id="routeSelect" class="px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="">-- Select a route --</option>
                            <?php foreach ($routes as $r): ?>
                                <option value="<?php echo (int)$r['id']; ?>" <?php echo $selected_route_id === (int)$r['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($r['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <a id="editRouteBtn" href="#" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-medium hidden">Edit route</a>
                        <form id="deleteRouteForm" method="POST" action="manage_routes.php" class="inline" onsubmit="return confirm('Delete this route and all its stops? Reports for this route will keep the route name as null.');">
                            <input type="hidden" name="action" value="delete_route">
                            <input type="hidden" name="route_id" id="deleteRouteId" value="">
                            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm font-medium hidden" id="deleteRouteBtn">Delete route</button>
                        </form>
                    </div>
                </div>

                <!-- Routes Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (empty($routes)): ?>
                        <div class="col-span-full bg-white rounded-lg shadow-md p-8 text-center">
                            <p class="text-gray-500">No routes found. <a href="manage_routes.php" class="text-blue-600 hover:underline">Create routes</a> in Manage Routes.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($routes as $route): ?>
                            <?php 
                            $has_delays = !empty($route['delayed_count']);
                            $delayed_count = (int)($route['delayed_count'] ?? 0);
                            $status_class = $has_delays ? 'border-red-500' : 'border-green-500';
                            $is_selected = $selected_route_id === (int)$route['id'];
                            ?>
                            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 <?php echo $status_class; ?> <?php echo $is_selected ? 'ring-2 ring-blue-500' : ''; ?>">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-xl font-semibold text-gray-800"><?php echo htmlspecialchars($route['name']); ?></h3>
                                    <?php if ($has_delays): ?>
                                        <span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">Delayed</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">On Time</span>
                                    <?php endif; ?>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">Total Reports</span>
                                        <span class="font-semibold text-gray-800"><?php echo $route['report_count']; ?></span>
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
                                                <span class="text-sm text-red-600 font-medium">Delays (last hour)</span>
                                                <span class="font-semibold text-red-600"><?php echo $delayed_count; ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-4 pt-3 border-t border-gray-200 flex gap-2">
                                    <a href="manage_routes.php?highlight=<?php echo (int)$route['id']; ?>" class="text-sm text-blue-600 hover:text-blue-800 font-medium">Edit</a>
                                    <form method="POST" action="manage_routes.php" class="inline" onsubmit="return confirm('Delete this route and all its stops?');">
                                        <input type="hidden" name="action" value="delete_route">
                                        <input type="hidden" name="route_id" value="<?php echo (int)$route['id']; ?>">
                                        <button type="submit" class="text-sm text-red-600 hover:text-red-800 font-medium">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script>
        (function() {
            var sel = document.getElementById('routeSelect');
            var editBtn = document.getElementById('editRouteBtn');
            var deleteBtn = document.getElementById('deleteRouteBtn');
            var deleteId = document.getElementById('deleteRouteId');
            if (!sel) return;
            function update() {
                var id = sel.value;
                if (id) {
                    editBtn.href = 'manage_routes.php?highlight=' + id;
                    editBtn.classList.remove('hidden');
                    deleteBtn.classList.remove('hidden');
                    deleteId.value = id;
                } else {
                    editBtn.classList.add('hidden');
                    deleteBtn.classList.add('hidden');
                }
            }
            sel.addEventListener('change', function() {
                if (sel.value) window.location = 'route_status.php?route_id=' + sel.value;
                update();
            });
            update();
        })();
    </script>
</body>
</html>

