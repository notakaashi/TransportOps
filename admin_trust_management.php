<?php
/**
 * Admin Trust Management Panel
 * Allows administrators to view and manage user trust scores
 */

require_once 'auth_helper.php';
secureSessionStart();
require_once 'db.php';
require_once 'trust_helper.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

$success = '';
$error = '';

// Handle manual trust score adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'adjust_score') {
        $userId = (int)$_POST['user_id'];
        $newScore = (float)$_POST['new_score'];
        $reason = trim($_POST['reason']);
        
        if (empty($reason)) {
            $error = 'Reason is required for manual adjustments.';
        } elseif ($newScore < 0 || $newScore > 100) {
            $error = 'Trust score must be between 0 and 100.';
        } else {
            if (manuallyAdjustTrustScore($userId, $newScore, $reason, $_SESSION['user_id'])) {
                $success = 'Trust score updated successfully.';
            } else {
                $error = 'Failed to update trust score.';
            }
        }
    } elseif ($_POST['action'] === 'reset_score') {
        $userId = (int)$_POST['user_id'];
        
        if (manuallyAdjustTrustScore($userId, 50.0, 'Reset to default by admin', $_SESSION['user_id'])) {
            $success = 'Trust score reset to default (50).';
        } else {
            $error = 'Failed to reset trust score.';
        }
    }
}

// Get all users sorted by trust score
$users = getAllUsersByTrustScore();

// Handle user selection for detailed view
$selectedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$selectedUser = null;
$trustLogs = [];

if ($selectedUserId > 0) {
    foreach ($users as $user) {
        if ($user['id'] === $selectedUserId) {
            $selectedUser = $user;
            break;
        }
    }
    
    if ($selectedUser) {
        $trustLogs = getTrustScoreLogs($selectedUserId);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trust Management - Admin Dashboard</title>
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
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    <a href="admin_trust_management.php" 
                       class="flex items-center px-4 py-3 bg-blue-600 rounded-lg hover:bg-blue-700 transition duration-150 shadow-lg">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Trust Management
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
                            <span class="px-2 py-1 bg-purple-600 text-white text-xs rounded-full">Admin</span>
                            <a href="logout.php" class="text-red-400 hover:text-red-300 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-x-hidden">
            <!-- Mobile Navigation Toggle -->
            <div class="md:hidden bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between">
                <button id="mobileMenuToggle" class="text-gray-600 hover:text-gray-900">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <h1 class="text-lg font-semibold text-gray-800">Trust Management</h1>
            </div>

            <div class="p-4 sm:p-6 lg:p-8">
                <!-- Page Header -->
                <div class="mb-6">
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Trust Management</h1>
                    <p class="text-gray-600 mt-2">Manage user trust scores and view credibility statistics</p>
                </div>

                <!-- Success/Error Messages -->
                <?php if ($success): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Users Table -->
                <div class="bg-white rounded-2xl shadow-md overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-xl font-semibold text-gray-800">All Users (Sorted by Trust Score)</h2>
                        <p class="text-sm text-gray-600 mt-1">Users with low trust scores are shown first for easy identification</p>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trust Score</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Badge</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($users as $user): ?>
                                    <?php $badge = getTrustBadge($user['trust_score']); ?>
                                    <tr class="<?php echo $user['trust_score'] < 20 ? 'bg-red-50' : ''; ?>">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <?php if (!empty($user['profile_image'])): ?>
                                                    <img class="h-8 w-8 rounded-full" src="uploads/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="">
                                                <?php else: ?>
                                                    <div class="h-8 w-8 rounded-full bg-gray-300 flex items-center justify-center">
                                                        <span class="text-xs font-medium text-gray-600"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="ml-3">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['name']); ?></div>
                                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($user['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-lg font-bold <?php echo $user['trust_score'] < 20 ? 'text-red-600' : ($user['trust_score'] >= 80 ? 'text-green-600' : 'text-gray-600'); ?>">
                                                <?php echo number_format($user['trust_score'], 1); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="<?php echo $badge['bg_color']; ?> <?php echo $badge['text_color']; ?> <?php echo $badge['border_color']; ?> px-2 py-1 rounded-full text-xs font-medium border">
                                                <?php echo $badge['label']; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $user['role'] === 'Admin' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'; ?>">
                                                <?php echo htmlspecialchars($user['role']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <a href="?user_id=<?php echo $user['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">View Details</a>
                                            <a href="public_profile.php?id=<?php echo $user['id']; ?>" class="text-gray-600 hover:text-gray-900">Public Profile</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- User Details Panel -->
                <?php if ($selectedUser): ?>
                    <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h2 class="text-xl font-semibold text-gray-800">User Details: <?php echo htmlspecialchars($selectedUser['name']); ?></h2>
                                <p class="text-sm text-gray-600 mt-1">Trust score history and manual adjustments</p>
                            </div>
                            <a href="?" class="text-gray-600 hover:text-gray-900">✕ Close</a>
                        </div>

                        <!-- Manual Adjustment Form -->
                        <div class="bg-gray-50 rounded-lg p-4 mb-6">
                            <h3 class="text-lg font-medium text-gray-800 mb-4">Manual Trust Score Adjustment</h3>
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="adjust_score">
                                <input type="hidden" name="user_id" value="<?php echo $selectedUser['id']; ?>">
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">New Trust Score (0-100)</label>
                                        <input type="number" name="new_score" min="0" max="100" step="0.1" value="<?php echo $selectedUser['trust_score']; ?>" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Adjustment</label>
                                        <input type="text" name="reason" placeholder="e.g., Good reporting behavior, policy violation, etc." 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                    </div>
                                </div>
                                
                                <div class="flex space-x-3">
                                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        Update Score
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="reset_score">
                                        <input type="hidden" name="user_id" value="<?php echo $selectedUser['id']; ?>">
                                        <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                            Reset to 50
                                        </button>
                                    </form>
                                </div>
                            </form>
                        </div>

                        <!-- Trust Score History -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-800 mb-4">Trust Score History</h3>
                            <?php if (empty($trustLogs)): ?>
                                <p class="text-gray-600">No trust score adjustments recorded for this user.</p>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Old Score</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">New Score</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Change</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Adjusted By</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($trustLogs as $log): ?>
                                                <tr>
                                                    <td class="px-4 py-3 text-sm text-gray-900">
                                                        <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-900"><?php echo number_format($log['old_score'], 1); ?></td>
                                                    <td class="px-4 py-3 text-sm text-gray-900"><?php echo number_format($log['new_score'], 1); ?></td>
                                                    <td class="px-4 py-3 text-sm">
                                                        <span class="font-medium <?php echo $log['new_score'] > $log['old_score'] ? 'text-green-600' : 'text-red-600'; ?>">
                                                            <?php echo $log['new_score'] > $log['old_score'] ? '+' : ''; ?>
                                                            <?php echo number_format($log['new_score'] - $log['old_score'], 1); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-900"><?php echo htmlspecialchars($log['reason']); ?></td>
                                                    <td class="px-4 py-3 text-sm text-gray-900"><?php echo htmlspecialchars($log['adjusted_by_name']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Mobile menu toggle
        const adminNavToggle = document.getElementById('adminNavToggle');
        const adminNavLinks = document.getElementById('adminNavLinks');
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');

        if (adminNavToggle && adminNavLinks) {
            adminNavToggle.addEventListener('click', () => {
                adminNavLinks.classList.toggle('hidden');
            });
        }

        if (mobileMenuToggle && adminNavLinks) {
            mobileMenuToggle.addEventListener('click', () => {
                adminNavLinks.classList.toggle('hidden');
            });
        }
    </script>
</body>
</html>
