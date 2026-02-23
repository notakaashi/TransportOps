<?php
/**
 * Admin Dashboard
 * Displays fleet overview, statistics, and management tools
 * Restricted to Admin role only
 */

session_start();
require_once 'db.php';
require_once 'auth_helper.php';

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id'])) {
    header('Location: admin_login.php');
    exit;
}
if ($_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

// Check if admin is still active
checkAdminActive();

// Fetch statistics from database
$total_reports = 0;
$active_delays = 0;
$total_users = 0;
$total_routes = 0;
$recent_reports = [];
$users_data = [];
$delay_trends = [];
$peak_hours = [];

try {
    $pdo = getDBConnection();
    
    // Get total reports count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM reports");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_reports = isset($result['count']) ? (int)$result['count'] : 0;
    
    // Get active delays count (reports with delay_reason)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM reports WHERE delay_reason IS NOT NULL AND delay_reason != ''");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $active_delays = isset($result['count']) ? (int)$result['count'] : 0;
    
    // Get total users count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_users = isset($result['count']) ? (int)$result['count'] : 0;
    
    // Get routes count from route_definitions
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM route_definitions");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_routes = isset($result['count']) ? (int)$result['count'] : 0;
    } catch (PDOException $e) {
        $total_routes = 0;
    }
    
    // Get recent reports (last 10)
    $stmt = $pdo->query("
        SELECT r.id, r.crowd_level, r.delay_reason, r.timestamp, r.latitude, r.longitude,
               u.name as user_name, u.role as user_role,
               COALESCE(rd.name, p.current_route) AS route_name
        FROM reports r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN route_definitions rd ON r.route_definition_id = rd.id
        LEFT JOIN puv_units p ON r.puv_id = p.id
        ORDER BY r.timestamp DESC
        LIMIT 10
    ");
    $recent_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all users for management
    $stmt = $pdo->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC");
    $users_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Delay trend analysis - get delay reasons count for last 7 days
    $stmt = $pdo->query("
        SELECT delay_reason, COUNT(*) as count
        FROM reports
        WHERE delay_reason IS NOT NULL AND delay_reason != ''
        AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY delay_reason
        ORDER BY count DESC
        LIMIT 5
    ");
    $delay_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Peak hour analysis - crowding by hour
    $stmt = $pdo->query("
        SELECT HOUR(timestamp) as hour, 
               SUM(CASE WHEN crowd_level = 'Heavy' THEN 1 ELSE 0 END) as heavy_count,
               COUNT(*) as total_reports
        FROM reports
        WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY HOUR(timestamp)
        ORDER BY hour
    ");
    $peak_hours = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    // Variables already initialized above with default values
}

/**
 * Get status badge color based on crowd status
 */
function getStatusBadge($status) {
    switch ($status) {
        case 'Light':
            return 'bg-green-100 text-green-800 border-green-300';
        case 'Moderate':
            return 'bg-yellow-100 text-yellow-800 border-yellow-300';
        case 'Heavy':
            return 'bg-red-100 text-red-800 border-red-300';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-300';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Transport Operations System</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
                       class="flex items-center px-4 py-3 bg-blue-600 rounded-lg hover:bg-blue-700 transition duration-150 shadow-lg">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Dashboard
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
                </nav>
            </div>
            <div id="adminNavFooter" class="mt-auto p-4 sm:p-6 border-t border-gray-700 hidden md:block">
                <div class="bg-gray-700 rounded-lg p-3 sm:p-4 mb-4">
                    <p class="text-xs text-gray-400 mb-1">Logged in as</p>
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                        <div class="flex items-center gap-2">
                            <button id="adminProfileMenuButton"
                                    class="flex items-center gap-2 px-2 py-1.5 rounded-full hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/60">
                                <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <p class="text-xs text-blue-400 mt-1"><?php echo htmlspecialchars($_SESSION['role']); ?></p>
                </div>
                <div id="adminProfileMenu"
                     class="hidden absolute right-0 bottom-full mb-2 w-48 bg-white text-gray-800 rounded-lg shadow-lg border border-gray-100 py-1 z-40">
                    <a href="profile.php"
                       class="block px-3 py-2 text-sm hover:bg-gray-50">
                        View &amp; Edit Profile
                    </a>
                    <div class="my-1 border-t border-gray-100"></div>
                    <a href="admin_dashboard.php"
                       class="block px-3 py-2 text-sm hover:bg-gray-50">
                        Admin Dashboard
                    </a>
                    <div class="my-1 border-t border-gray-100"></div>
                    <a href="logout.php"
                       class="block px-3 py-2 text-sm text-red-600 hover:bg-red-50">
                        Logout
                    </a>
                </div>
                <a href="logout.php" 
                   class="block w-full text-center bg-gradient-to-r from-red-600 to-red-700 text-white py-2 px-4 rounded-md hover:from-red-700 hover:to-red-800 transition duration-150 font-medium shadow-lg">
                    Logout
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 w-full">
            <div class="p-4 sm:p-6 lg:p-8">
                <!-- Page Header -->
                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-gray-800">Dashboard</h2>
                    <p class="text-gray-600 mt-2">Monitor routes and reports</p>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 lg:gap-6 mb-8">
                    <!-- Total Reports Card -->
                    <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium">Total Reports</p>
                                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo number_format($total_reports); ?></p>
                            </div>
                            <div class="bg-green-100 p-3 rounded-full">
                                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Active Delays Card -->
                    <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-red-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium">Active Delays</p>
                                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo number_format($active_delays); ?></p>
                            </div>
                            <div class="bg-red-100 p-3 rounded-full">
                                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Total Users Card -->
                    <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-purple-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium">Total Users</p>
                                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo number_format($total_users); ?></p>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Total Routes Card -->
                    <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-indigo-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium">Routes</p>
                                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo number_format($total_routes); ?></p>
                            </div>
                            <div class="bg-indigo-100 p-3 rounded-full">
                                <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Reports Table -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-xl font-semibold text-gray-800 flex items-center">
                            Recent Reports
                            <span id="report-notification-badge" class="ml-3 hidden px-2 py-1 text-xs font-semibold rounded-full bg-red-600 text-white">
                                New
                            </span>
                        </h3>
                        <span id="report-notification-count" class="text-sm text-gray-500"></span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Route</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Crowd Level</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Delay Reason</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($recent_reports)): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                            No reports found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_reports as $report): ?>
                                        <tr class="hover:bg-gray-50 cursor-pointer transition duration-150 report-row"
                                            data-report="<?php echo htmlspecialchars(json_encode($report)); ?>"
                                            title="Click to view details">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    <?php echo date('M d, Y H:i', strtotime($report['timestamp'])); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    <?php echo htmlspecialchars($report['user_name'] ?? 'N/A'); ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <?php echo htmlspecialchars($report['user_role'] ?? ''); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($report['route_name'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full border <?php echo getStatusBadge($report['crowd_level']); ?>">
                                                    <?php echo htmlspecialchars($report['crowd_level']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-600">
                                                    <?php if ($report['delay_reason']): ?>
                                                        <?php echo htmlspecialchars(substr($report['delay_reason'], 0, 50)); ?>
                                                        <?php if (strlen($report['delay_reason']) > 50): ?>...<?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-gray-400">None</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Analytics Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <!-- Delay Trend Analysis -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-xl font-semibold text-gray-800">Delay Trend Analysis (Last 7 Days)</h3>
                        </div>
                        <div class="p-6">
                            <?php if (empty($delay_trends)): ?>
                                <p class="text-gray-500 text-center py-4">No delay data available.</p>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($delay_trends as $trend): ?>
                                        <div>
                                            <div class="flex justify-between items-center mb-1">
                                                <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($trend['delay_reason']); ?></span>
                                                <span class="text-sm font-semibold text-gray-900"><?php echo $trend['count']; ?> occurrences</span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                <div class="bg-red-600 h-2 rounded-full" style="width: <?php echo min(100, ($trend['count'] / max(array_column($delay_trends, 'count'))) * 100); ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Peak Hour Analytics -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-xl font-semibold text-gray-800">Peak Hour Crowding Analysis</h3>
                        </div>
                        <div class="p-6">
                            <?php if (empty($peak_hours)): ?>
                                <p class="text-gray-500 text-center py-4">No peak hour data available.</p>
                            <?php else: ?>
                                <div class="space-y-3">
                                    <?php foreach ($peak_hours as $hour): ?>
                                        <div>
                                            <div class="flex justify-between items-center mb-1">
                                                <span class="text-sm font-medium text-gray-700"><?php echo date('g A', mktime($hour['hour'], 0, 0)); ?></span>
                                                <span class="text-sm text-gray-600"><?php echo $hour['heavy_count']; ?> / <?php echo $hour['total_reports']; ?> heavy</span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                <div class="bg-orange-600 h-2 rounded-full" style="width: <?php echo $hour['total_reports'] > 0 ? ($hour['heavy_count'] / $hour['total_reports']) * 100 : 0; ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Users Management Table -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-xl font-semibold text-gray-800">User Management</h3>
                        <a href="user_management.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-150 font-medium text-sm">
                            Manage Users
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registered</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($users_data)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                            No users found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users_data as $user): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($user['name']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-600">
                                                    <?php echo htmlspecialchars($user['email']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php
                                                $roleColors = [
                                                    'Admin' => 'bg-red-100 text-red-800 border-red-300',
                                                    'Driver' => 'bg-blue-100 text-blue-800 border-blue-300',
                                                    'Commuter' => 'bg-gray-100 text-gray-800 border-gray-300'
                                                ];
                                                $roleColor = $roleColors[$user['role']] ?? 'bg-gray-100 text-gray-800 border-gray-300';
                                                ?>
                                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full border <?php echo $roleColor; ?>">
                                                    <?php echo htmlspecialchars($user['role']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-600">
                                                    <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Report detail modal -->
    <div id="reportModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true" role="dialog">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div id="reportModalBackdrop" class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div>
            <div class="relative inline-block w-full max-w-lg p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-xl">
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-xl font-semibold text-gray-900">Report details</h3>
                    <button type="button" id="reportModalClose" class="text-gray-400 hover:text-gray-600 rounded-lg focus:ring-2 focus:ring-gray-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div id="reportModalBody" class="space-y-3 text-sm text-gray-700"></div>
                <div class="mt-6 flex gap-3">
                    <a id="reportModalViewOnMap" href="#" class="inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">View on map</a>
                    <button type="button" id="reportModalCloseBtn" class="inline-flex justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">Close</button>
                </div>
            </div>
        </div>
    </div>
}</script>
<script>
    (function () {
        let lastReportTimestamp = <?php
            $latest = !empty($recent_reports) ? $recent_reports[0]['timestamp'] : null;
            echo $latest ? json_encode($latest) : 'null';
        ?>;
        let notificationAudio;

        function initAudio() {
            try {
                notificationAudio = new Audio('https://actions.google.com/sounds/v1/alarms/beep_short.ogg');
            } catch (e) {
                notificationAudio = null;
            }
        }

        function playNotificationSound() {
            if (!notificationAudio) return;
            notificationAudio.currentTime = 0;
            notificationAudio.play().catch(() => {});
        }

        async function checkNewReports() {
            try {
                const params = lastReportTimestamp ? '?since=' + encodeURIComponent(lastReportTimestamp) : '';
                const response = await fetch('admin_notifications.php' + params, { credentials: 'same-origin' });
                if (!response.ok) return;

                const data = await response.json();
                const newCount = data.new_count || 0;
                const latest = data.latest_timestamp || null;

                const badge = document.getElementById('report-notification-badge');
                const countLabel = document.getElementById('report-notification-count');

                if (newCount > 0) {
                    if (badge) badge.classList.remove('hidden');
                    if (countLabel) {
                        countLabel.textContent = newCount + ' new report' + (newCount > 1 ? 's' : '');
                    }
                    playNotificationSound();
                } else {
                    if (countLabel && !countLabel.textContent) {
                        countLabel.textContent = '';
                    }
                }

                if (latest) {
                    lastReportTimestamp = latest;
                }
            } catch (e) {
                console.error('Notification check failed', e);
            }
        }

        initAudio();
        setInterval(checkNewReports, 15000);
    })();

    (function () {
        const modal = document.getElementById('reportModal');
        const body = document.getElementById('reportModalBody');
        const viewOnMapLink = document.getElementById('reportModalViewOnMap');
        const closeBtn = document.getElementById('reportModalClose');
        const closeBtn2 = document.getElementById('reportModalCloseBtn');
        const backdrop = document.getElementById('reportModalBackdrop');

        function openModal(report) {
            const r = report;
            const time = r.timestamp ? new Date(r.timestamp).toLocaleString() : 'N/A';
            body.innerHTML = [
                '<p><strong>Time:</strong> ' + (time) + '</p>',
                '<p><strong>Reported by:</strong> ' + (r.user_name || 'N/A') + ' <span class="text-gray-500">(' + (r.user_role || '') + ')</span></p>',
                '<p><strong>Route:</strong> ' + (r.route_name || 'N/A') + '</p>',
                '<p><strong>Crowd level:</strong> <span class="px-2 py-0.5 rounded border ' + (r.crowd_level === 'Heavy' ? 'bg-red-100 text-red-800' : r.crowd_level === 'Moderate' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') + '">' + (r.crowd_level || '') + '</span></p>',
                r.delay_reason ? '<p><strong>Delay reason:</strong> ' + escapeHtml(r.delay_reason) + '</p>' : '',
                (r.latitude && r.longitude) ? '<p class="text-gray-500"><strong>Location:</strong> ' + parseFloat(r.latitude).toFixed(5) + ', ' + parseFloat(r.longitude).toFixed(5) + '</p>' : ''
            ].join('');
            viewOnMapLink.href = (r.latitude && r.longitude) ? 'admin_reports.php?focus=' + r.id : 'admin_reports.php';
            viewOnMapLink.style.visibility = (r.latitude && r.longitude) ? 'visible' : 'hidden';
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
        function closeModal() {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }

        document.querySelectorAll('.report-row').forEach(function (row) {
            row.addEventListener('click', function () {
                try {
                    const data = this.getAttribute('data-report');
                    if (data) openModal(JSON.parse(data));
                } catch (e) { console.error(e); }
            });
        });
        closeBtn.addEventListener('click', closeModal);
        closeBtn2.addEventListener('click', closeModal);
        backdrop.addEventListener('click', closeModal);
    })();

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

    // Admin Profile Menu Toggle
    (function () {
        const btn = document.getElementById('adminProfileMenuButton');
        const menu = document.getElementById('adminProfileMenu');
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
</html>

