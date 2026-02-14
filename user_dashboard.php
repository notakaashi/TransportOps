<?php
/**
 * User Dashboard
 * Dashboard for logged-in non-admin users (Driver/Commuter)
 */

session_start();
require_once 'db.php';
require_once 'auth_helper.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if user is still active
checkUserActive();

// Redirect admin users to admin dashboard
if ($_SESSION['role'] === 'Admin') {
    header('Location: admin_dashboard.php');
    exit;
}

// Fetch user's reports and profile image
try {
    $pdo = getDBConnection();
    
    // Always query database for latest profile image
    $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Cache in session for later pages
    if ($user_profile && $user_profile['profile_image']) {
        $_SESSION['profile_image'] = $user_profile['profile_image'];
    }
    
    $stmt = $pdo->prepare("
        SELECT r.id, r.crowd_level, r.delay_reason, r.timestamp, r.trust_score, r.is_verified,
               COALESCE(rd.name, p.current_route) AS route_name
        FROM reports r
        LEFT JOIN route_definitions rd ON r.route_definition_id = rd.id
        LEFT JOIN puv_units p ON r.puv_id = p.id
        WHERE r.user_id = ?
        ORDER BY r.timestamp DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user_reports = $stmt->fetchAll();
    
    // Get total reports count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM reports WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $total_reports = $stmt->fetch()['count'];
} catch (PDOException $e) {
    error_log("User dashboard error: " . $e->getMessage());
    $user_reports = [];
    $total_reports = 0;
    $user_profile = ['profile_image' => null];
}

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
    <title>Dashboard - Transport Operations System</title>
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
</head>
<body class="bg-[#F3F4F6] min-h-screen">
    <div class="flex flex-col md:flex-row min-h-screen">
        <!-- Sidebar -->
        <aside class="w-full md:w-64 bg-gradient-to-b from-gray-800 to-gray-900 text-white flex flex-col shadow-2xl">
            <div class="px-4 py-4 sm:p-6 flex-shrink-0 border-b border-gray-700 md:border-b-0">
                <div id="userNavToggle" class="flex items-center justify-between md:justify-start mb-4 md:mb-8 cursor-pointer md:cursor-default">
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
                <nav id="userNavLinks" class="space-y-1 md:space-y-2 text-sm sm:text-base hidden md:block">
                    <a href="index.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        Home
                    </a>
                    <a href="user_dashboard.php" 
                       class="flex items-center px-4 py-3 bg-blue-600 rounded-lg hover:bg-blue-700 transition duration-150 shadow-lg">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Dashboard
                    </a>
                    <a href="report.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Submit Report
                    </a>
                    <a href="reports_map.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Reports Map
                    </a>
                    <a href="routes.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                        </svg>
                        Routes
                    </a>
                    <a href="about.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        About
                    </a>
                </nav>
            </div>
            <div id="userNavFooter" class="mt-auto p-4 sm:p-6 border-t border-gray-700 hidden md:block">
                <div class="bg-gray-700 rounded-lg p-3 sm:p-4 mb-4">
                    <p class="text-xs text-gray-400 mb-1">Logged in as</p>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <?php if ($user_profile['profile_image']): ?>
                                <img src="uploads/<?php echo htmlspecialchars($user_profile['profile_image']); ?>" 
                                     alt="Profile" 
                                     class="h-8 w-8 rounded-full object-cover border-2 border-white">
                            <?php else: ?>
                                <div class="h-8 w-8 rounded-full bg-[#10B981] flex items-center justify-center text-white text-sm font-semibold">
                                    <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <p class="text-sm font-semibold"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                                <p class="text-xs text-blue-400"><?php echo htmlspecialchars($_SESSION['role']); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button id="userProfileMenuButton"
                                    class="flex items-center gap-2 px-2 py-1.5 rounded-full hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/60">
                                <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                <div id="userProfileMenu"
                     class="hidden absolute right-0 bottom-full mb-2 w-48 bg-white text-gray-800 rounded-lg shadow-lg border border-gray-100 py-1 z-40">
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
                <a href="logout.php" 
                   class="block w-full text-center bg-gradient-to-r from-red-600 to-red-700 text-white py-2 px-4 rounded-md hover:from-red-700 hover:to-red-800 transition duration-150 font-medium shadow-lg">
                    Logout
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 w-full">
            <div class="p-4 sm:p-6 lg:p-8">
        <!-- Welcome Section -->
        <div class="mb-6 sm:mb-8">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-1 sm:mb-2 leading-tight">
                Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!
            </h1>
            <p class="text-sm sm:text-base text-gray-600 max-w-xl leading-relaxed">
                Help improve transportation services by reporting real-time conditions.
            </p>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <a href="report.php" class="bg-white rounded-2xl shadow-md px-4 py-4 sm:px-5 sm:py-5 hover:shadow-lg transition duration-150 border-l-4 border-blue-500">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm sm:text-base font-semibold text-gray-800 mb-0.5">Submit Report</h3>
                        <p class="text-xs sm:text-sm text-gray-600">Report crowding levels and delays.</p>
                    </div>
                    <div class="bg-blue-100 p-2 sm:p-3 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 sm:w-8 sm:h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                    </div>
                </div>
            </a>

            <div class="bg-white rounded-2xl shadow-md px-4 py-4 sm:px-5 sm:py-5 border-l-4 border-green-500">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm sm:text-base font-semibold text-gray-800 mb-0.5">Total Reports</h3>
                        <p class="text-2xl sm:text-3xl font-bold text-gray-800"><?php echo $total_reports; ?></p>
                    </div>
                    <div class="bg-green-100 p-2 sm:p-3 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 sm:w-8 sm:h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-md px-4 py-4 sm:px-5 sm:py-5 border-l-4 border-purple-500">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm sm:text-base font-semibold text-gray-800 mb-0.5">Verified Reports</h3>
                        <p class="text-2xl sm:text-3xl font-bold text-gray-800">
                            <?php echo count(array_filter($user_reports, fn($r) => $r['is_verified'])); ?>
                        </p>
                    </div>
                    <div class="bg-purple-100 p-2 sm:p-3 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 sm:w-8 sm:h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <a href="routes.php" class="bg-white rounded-2xl shadow-md px-4 py-4 sm:px-5 sm:py-5 hover:shadow-lg transition duration-150 border-l-4 border-indigo-500">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm sm:text-base font-semibold text-gray-800 mb-0.5">View Routes</h3>
                        <p class="text-xs sm:text-sm text-gray-600">See routes and stops on the map.</p>
                    </div>
                    <div class="bg-indigo-100 p-2 sm:p-3 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 sm:w-8 sm:h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                        </svg>
                    </div>
                </div>
            </a>
        </div>

        <!-- Recent Reports -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-xl font-semibold text-gray-800">Your Recent Reports</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Route</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Crowd Level</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Delay Reason</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($user_reports)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                    No reports yet. <a href="report.php" class="text-blue-600 hover:text-blue-800">Submit your first report</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($user_reports as $report): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo date('M d, Y H:i', strtotime($report['timestamp'])); ?>
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
                                            <?php echo $report['delay_reason'] ? htmlspecialchars(substr($report['delay_reason'], 0, 30)) : '<span class="text-gray-400">None</span>'; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($report['is_verified']): ?>
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Verified</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        </main>
    </div>

    <script>
        (function () {
            // Mobile menu toggle
            const navToggle = document.getElementById('userNavToggle');
            const navLinks = document.getElementById('userNavLinks');
            const navFooter = document.getElementById('userNavFooter');
            
            if (navToggle && navLinks && navFooter) {
                navToggle.addEventListener('click', function() {
                    navLinks.classList.toggle('hidden');
                    navFooter.classList.toggle('hidden');
                });
            }

            // Profile menu toggle
            const profileBtn = document.getElementById('userProfileMenuButton');
            const profileMenu = document.getElementById('userProfileMenu');
            
            if (profileBtn && profileMenu) {
                profileBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    profileMenu.classList.toggle('hidden');
                });
                
                document.addEventListener('click', function () {
                    if (!profileMenu.classList.contains('hidden')) {
                        profileMenu.classList.add('hidden');
                    }
                });
            }
        })();
    </script>
</body>
</html>

