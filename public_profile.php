<?php
/**
 * Public User Profile Page
 * Shows user's trust score, badge, and reporting history
 */

require_once 'auth_helper.php';
secureSessionStart();
require_once 'db.php';
require_once 'trust_helper.php';

// Get user ID from URL parameter
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($userId <= 0) {
    header('Location: index.php');
    exit;
}

// Get user's public profile
$profile = getUserPublicProfile($userId);

if (!$profile) {
    header('Location: index.php');
    exit;
}

$user = $profile['user'];
$stats = $profile['stats'];
$recentReports = $profile['recent_reports'];
$badge = $profile['badge'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['name']); ?> - Public Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <style>
        .brand-font {
            font-family: 'Poppins', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            letter-spacing: 0.02em;
        }
    </style>
</head>
<body class="bg-[#F3F4F6] min-h-screen">
    <!-- Navigation Bar -->
    <nav class="fixed top-0 inset-x-0 z-30 bg-[#1E3A8A] text-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-8">
                    <a href="index.php" class="brand-font text-xl sm:text-2xl font-bold text-white whitespace-nowrap">Transport Ops</a>
                    <div class="hidden md:flex space-x-4">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="user_dashboard.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Home</a>
                        <?php endif; ?>
                        <a href="about.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">About</a>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="report.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Submit Report</a>
                            <a href="reports_map.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Reports Map</a>
                            <a href="routes.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Routes</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="relative flex items-center gap-2 sm:gap-3">
                    <?php if (isset($_SESSION['user_id'])): ?>
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
                    <?php else: ?>
                        <a href="register.php" class="text-white border border-white/40 hover:bg-white hover:text-[#1E3A8A] px-2 sm:px-3 py-2 rounded-md text-sm font-medium whitespace-nowrap transition duration-150">Register</a>
                        <a href="login.php" class="bg-[#10B981] text-white px-3 sm:px-4 py-2 rounded-md hover:bg-[#059669] transition duration-150 font-medium whitespace-nowrap">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-12">
        <!-- Profile Header -->
        <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
            <div class="flex items-center space-x-6">
                <!-- Profile Image -->
                <div class="flex-shrink-0">
                    <?php if (!empty($user['profile_image'])): ?>
                        <img src="uploads/<?php echo htmlspecialchars($user['profile_image']); ?>" 
                             alt="<?php echo htmlspecialchars($user['name']); ?>"
                             class="h-24 w-24 rounded-full object-cover border-4 border-gray-200">
                    <?php else: ?>
                        <div class="h-24 w-24 rounded-full bg-[#10B981] flex items-center justify-center text-white text-3xl font-bold border-4 border-gray-200">
                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- User Info -->
                <div class="flex-1">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($user['name']); ?></h1>
                    
                    <!-- Trust Badge -->
                    <div class="flex items-center space-x-4 mb-3">
                        <span class="<?php echo $badge['bg_color']; ?> <?php echo $badge['text_color']; ?> <?php echo $badge['border_color']; ?> px-3 py-1 rounded-full text-sm font-medium border">
                            <?php echo $badge['label']; ?>
                        </span>
                        <span class="text-2xl font-bold text-gray-700">
                            <?php echo number_format($user['trust_score'], 1); ?>/100
                        </span>
                    </div>
                    
                    <!-- Member Since -->
                    <p class="text-gray-600 text-sm">
                        Member since <?php echo date('F j, Y', strtotime($user['created_at'])); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-2xl shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Total Reports</h3>
                <p class="text-3xl font-bold text-blue-600"><?php echo $stats['total_reports']; ?></p>
                <p class="text-gray-600 text-sm mt-1">Reports submitted</p>
            </div>
            
            <div class="bg-white rounded-2xl shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Verified Reports</h3>
                <p class="text-3xl font-bold text-green-600"><?php echo $stats['verified_reports']; ?></p>
                <p class="text-gray-600 text-sm mt-1">Peer-verified reports</p>
            </div>
            
            <div class="bg-white rounded-2xl shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Rejected Reports</h3>
                <p class="text-3xl font-bold text-red-600"><?php echo $stats['rejected_reports']; ?></p>
                <p class="text-gray-600 text-sm mt-1">Rejected reports</p>
            </div>
        </div>

        <!-- Recent Verified Reports -->
        <div class="bg-white rounded-2xl shadow-md p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Recent Verified Reports</h2>
            
            <?php if (empty($recentReports)): ?>
                <div class="text-center py-8">
                    <div class="text-gray-400 text-lg mb-2">No verified reports yet</div>
                    <p class="text-gray-600">This user hasn't had any reports verified by peers yet.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($recentReports as $report): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="font-semibold text-gray-800">
                                        <?php echo htmlspecialchars($report['route_name'] ?? 'Unknown Route'); ?>
                                    </h4>
                                    <p class="text-gray-600 text-sm mt-1">
                                        Crowd Level: 
                                        <span class="font-medium">
                                            <?php echo htmlspecialchars($report['crowd_level']); ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <div class="flex items-center space-x-2 text-sm text-gray-500">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        <span><?php echo $report['verification_count']; ?> verifications</span>
                                    </div>
                                    <p class="text-gray-500 text-xs mt-1">
                                        <?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Back Button -->
        <div class="mt-6 text-center">
            <a href="javascript:history.back()" class="inline-flex items-center px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Go Back
            </a>
        </div>
    </div>

    <script>
        (function () {
            const btn = document.getElementById('profileMenuButton');
            const menu = document.getElementById('profileMenu');
            if (!btn || !menu) return;
            // profile menu toggle
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                menu.classList.toggle('hidden');
            });
            document.addEventListener('click', function () { menu.classList.add('hidden'); });
        })();
    </script>
</body>
</html>
