<?php
/**
 * User Dashboard
 * Dashboard for logged-in non-admin users (Driver/Commuter)
 */

require_once 'auth_helper.php';
secureSessionStart();
require_once 'db.php';

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
$user_profile_data = $user_profile ?? ['profile_image' => null];

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
    <!-- Navigation Bar (same as routes.php, report.php) -->
    <nav class="fixed top-0 inset-x-0 z-30 bg-[#1E3A8A] text-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-8">
                    <!-- brand link doubles as mobile menu toggle -->
                    <a href="index.php" id="brandLink" class="brand-font text-xl sm:text-2xl font-bold text-white whitespace-nowrap">Transport Ops</a>
                    <!-- desktop links -->
                    <div class="hidden md:flex space-x-4">
                        <a href="user_dashboard.php" class="bg-blue-500 text-white px-3 py-2 rounded-md text-sm font-medium border-b-2 border-blue-800">Home</a>
                        <a href="about.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">About</a>
                        <a href="report.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Submit Report</a>
                        <a href="reports_map.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Reports Map</a>
                        <a href="routes.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Routes</a>
                    </div>
                    <!-- mobile dropdown menu (hidden by default) -->
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
                                <?php echo htmlspecialchars($_SESSION['role']); ?>
                            </span>
                        </div>
                        <div class="flex items-center gap-1">
                            <?php if (!empty($user_profile_data['profile_image'])): ?>
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

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-8">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-md overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-2xl font-semibold text-gray-800">Dashboard</h2>
                <p class="text-sm text-gray-600">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>! Help improve transportation services by reporting real-time conditions.</p>
            </div>
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
        <div class="bg-white rounded-2xl shadow-md overflow-hidden">
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
    </div>

    <script>
        (function () {
            const btn = document.getElementById('profileMenuButton');
            const menu = document.getElementById('profileMenu');
            const brand = document.getElementById('brandLink');
            const mobile = document.getElementById('mobileMenu');
            if (!btn || !menu) return;
            // profile menu toggle
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                menu.classList.toggle('hidden');
            });
            document.addEventListener('click', function () { menu.classList.add('hidden'); });

            // mobile nav dropdown when brand clicked on small screens
            if (brand && mobile) {
                brand.addEventListener('click', function (e) {
                    if (window.innerWidth < 768) {
                        e.preventDefault();
                        mobile.classList.toggle('hidden');
                    }
                });
                // hide menu when clicking outside
                document.addEventListener('click', function (ev) {
                    if (mobile && !mobile.contains(ev.target) && ev.target !== brand) {
                        mobile.classList.add('hidden');
                    }
                });
            }
        })();
    </script>
</body>
</html>

