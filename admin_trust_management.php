<?php
/**
 * Admin Trust Management Panel
 * Allows administrators to view and manage user trust scores
 */

require_once "auth_helper.php";
secureSessionStart();
require_once "db.php";
require_once "trust_helper.php";

// Check if user is admin
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "Admin") {
    header("Location: login.php");
    exit();
}

$success = "";
$error = "";

// Handle manual trust score adjustment
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    if ($_POST["action"] === "adjust_score") {
        $userId = (int) $_POST["user_id"];
        $newScore = (float) $_POST["new_score"];
        $reason = trim($_POST["reason"]);

        if (empty($reason)) {
            $error = "Reason is required for manual adjustments.";
        } elseif ($newScore < 0 || $newScore > 100) {
            $error = "Trust score must be between 0 and 100.";
        } else {
            if (
                manuallyAdjustTrustScore(
                    $userId,
                    $newScore,
                    $reason,
                    $_SESSION["user_id"],
                )
            ) {
                $success = "Trust score updated successfully.";
            } else {
                $error = "Failed to update trust score.";
            }
        }
    } elseif ($_POST["action"] === "reset_score") {
        $userId = (int) $_POST["user_id"];

        if (
            manuallyAdjustTrustScore(
                $userId,
                50.0,
                "Reset to default by admin",
                $_SESSION["user_id"],
            )
        ) {
            $success = "Trust score reset to default (50).";
        } else {
            $error = "Failed to reset trust score.";
        }
    } elseif ($_POST["action"] === "reject_report") {
        $reportId = (int) $_POST["report_id"];

        try {
            $pdo = getDBConnection();

            // Get report details
            $stmt = $pdo->prepare(
                "SELECT user_id, status FROM reports WHERE id = ?",
            );
            $stmt->execute([$reportId]);
            $report = $stmt->fetch();

            if (!$report) {
                $error = "Report not found.";
            } elseif ($report["status"] !== "pending") {
                $error = "Only pending reports can be rejected.";
            } else {
                // Update report status to rejected
                $stmt = $pdo->prepare(
                    "UPDATE reports SET status = 'rejected' WHERE id = ?",
                );
                $stmt->execute([$reportId]);

                // Apply trust score penalty
                $reporterId = $report["user_id"];
                if (
                    updateUserTrustScore(
                        $reporterId,
                        "Report rejected by admin",
                    )
                ) {
                    $success =
                        "Report rejected successfully and trust score updated.";
                } else {
                    $error =
                        "Report rejected but failed to update trust score.";
                }
            }
        } catch (PDOException $e) {
            error_log("Error rejecting report: " . $e->getMessage());
            $error = "Failed to reject report.";
        }
    }
}

// Get all users sorted by trust score
$users = getAllUsersByTrustScore();

// Get users with recently rejected reports (last 7 days)
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT DISTINCT r.user_id, r.id as report_id, r.timestamp as rejection_time
        FROM reports r
        WHERE r.status = 'rejected' 
        AND r.timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY r.timestamp DESC
    ");
    $recentlyRejectedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create a lookup array for quick access
    $rejectedUserLookup = [];
    foreach ($recentlyRejectedUsers as $rejected) {
        if (!isset($rejectedUserLookup[$rejected['user_id']])) {
            $rejectedUserLookup[$rejected['user_id']] = [];
        }
        $rejectedUserLookup[$rejected['user_id']][] = $rejected;
    }
} catch (PDOException $e) {
    error_log("Error fetching recently rejected users: " . $e->getMessage());
    $recentlyRejectedUsers = [];
    $rejectedUserLookup = [];
}

// Handle user selection for detailed view
$selectedUserId = isset($_GET["user_id"]) ? (int) $_GET["user_id"] : 0;
$selectedUser = null;
$trustLogs = [];

if ($selectedUserId > 0) {
    foreach ($users as $user) {
        if ($user["id"] === $selectedUserId) {
            $selectedUser = $user;
            break;
        }
    }

    if ($selectedUser) {
        // Mark notifications as read for this user
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("
                UPDATE admin_notifications 
                SET is_read = 1 
                WHERE user_id = ? 
                AND (type = 'report_rejection' OR type = 'admin_rejection')
                AND is_read = 0
            ");
            $stmt->execute([$selectedUserId]);
        } catch (PDOException $e) {
            error_log("Error marking notifications as read: " . $e->getMessage());
        }
        
        $trustLogs = getTrustScoreLogs($selectedUserId);

        // Get user's reports for verification management
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("
                SELECT r.*, rd.name as route_name, u.name as user_name
                FROM reports r
                LEFT JOIN route_definitions rd ON r.route_definition_id = rd.id
                LEFT JOIN users u ON r.user_id = u.id
                WHERE r.user_id = ?
                ORDER BY r.timestamp DESC
                LIMIT 20
            ");
            $stmt->execute([$selectedUserId]);
            $userReports = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching user reports: " . $e->getMessage());
            $userReports = [];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trust Management — Transport Ops</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include "admin_layout_head.php"; ?>
    <style>
        .main-area { padding: 2rem 2rem 3rem; overflow-y: auto; }
        @media (max-width: 768px) { .main-area { padding: 5rem 1rem 2rem; } }
    </style>
</head>
<body>
<?php include "admin_sidebar.php"; ?>

    <!-- ═══ MAIN CONTENT ════════════════════════════════════ -->
    <main class="main-area">

        <!-- Page Header -->
        <div style="margin-bottom:1.75rem;">
            <h1 class="page-title">Trust Management</h1>
            <p class="page-subtitle">Manage user trust scores, view credibility statistics, and reject invalid reports.</p>
        </div>

        <!-- Flash Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Users Table -->
                <div class="glass-card rounded-2xl overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b border-white/20">
                        <h2 class="text-xl font-semibold text-gray-800">All Users (Sorted by Trust Score)</h2>
                        <p class="text-sm text-gray-600 mt-1">Users with low trust scores are shown first for easy identification</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-white/30">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trust Score</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Badge</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white/70 divide-y divide-gray-200">
                                <?php foreach ($users as $user): ?>
                                    <?php $badge = getTrustBadge(
                                        $user["trust_score"],
                                    ); ?>
                                    <tr class="<?php echo $user["trust_score"] <
                                    20
                                        ? "bg-red-50"
                                        : ""; ?>">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center">
                                                    <?php if (
                                                        !empty(
                                                            $user["profile_image"]
                                                        )
                                                    ): ?>
                                                        <img class="h-8 w-8 rounded-full" src="uploads/<?php echo htmlspecialchars(
                                                            $user["profile_image"],
                                                        ); ?>" alt="">
                                                    <?php else: ?>
                                                        <div class="h-8 w-8 rounded-full bg-gray-300 flex items-center justify-center">
                                                            <span class="text-xs font-medium text-gray-600"><?php echo strtoupper(
                                                                substr(
                                                                    $user["name"],
                                                                    0,
                                                                    1,
                                                                ),
                                                            ); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="ml-3">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars(
                                                            $user["name"],
                                                        ); ?></div>
                                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars(
                                                            $user["email"],
                                                        ); ?></div>
                                                    </div>
                                                </div>
                                                <?php if (isset($rejectedUserLookup[$user["id"]])): ?>
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800" title="Recently rejected reports">
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                        </svg>
                                                        Rejected Reports
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-lg font-bold <?php echo $user[
                                                "trust_score"
                                            ] < 20
                                                ? "text-red-600"
                                                : ($user["trust_score"] >= 80
                                                    ? "text-green-600"
                                                    : "text-gray-600"); ?>">
                                                <?php echo number_format(
                                                    $user["trust_score"],
                                                    1,
                                                ); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="<?php echo $badge[
                                                "bg_color"
                                            ]; ?> <?php echo $badge[
     "text_color"
 ]; ?> <?php echo $badge[
     "border_color"
 ]; ?> px-2 py-1 rounded-full text-xs font-medium border">
                                                <?php echo $badge["label"]; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $user[
                                                "role"
                                            ] === "Admin"
                                                ? "bg-purple-100 text-purple-800"
                                                : "bg-gray-100 text-gray-800"; ?>">
                                                <?php echo htmlspecialchars(
                                                    $user["role"],
                                                ); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $user[
                                                "is_active"
                                            ]
                                                ? "bg-green-100 text-green-800"
                                                : "bg-red-100 text-red-800"; ?>">
                                                <?php echo $user["is_active"]
                                                    ? "Active"
                                                    : "Inactive"; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <a href="?user_id=<?php echo $user[
                                                "id"
                                            ]; ?>" class="text-blue-600 hover:text-blue-900 mr-3">View Details</a>
                                            <a href="public_profile.php?id=<?php echo $user[
                                                "id"
                                            ]; ?>&admin=1" class="text-gray-600 hover:text-gray-900">Public Profile</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- User Details Panel -->
                <?php if ($selectedUser): ?>
                    <div class="glass-card rounded-2xl p-6 mb-6">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h2 class="text-xl font-semibold text-gray-800">User Details: <?php echo htmlspecialchars(
                                    $selectedUser["name"],
                                ); ?></h2>
                                <p class="text-sm text-gray-600 mt-1">Trust score history and manual adjustments</p>
                            </div>
                            <a href="?" class="text-gray-600 hover:text-gray-900">✕ Close</a>
                        </div>

                        <!-- Manual Adjustment Form -->
                        <div class="bg-white/30 rounded-lg p-4 mb-6">
                            <h3 class="text-lg font-medium text-gray-800 mb-4">Manual Trust Score Adjustment</h3>
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="adjust_score">
                                <input type="hidden" name="user_id" value="<?php echo $selectedUser[
                                    "id"
                                ]; ?>">

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">New Trust Score (0-100)</label>
                                        <input type="number" name="new_score" min="0" max="100" step="0.1" value="<?php echo $selectedUser[
                                            "trust_score"
                                        ]; ?>"
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
                                        <input type="hidden" name="user_id" value="<?php echo $selectedUser[
                                            "id"
                                        ]; ?>">
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
                                        <thead class="bg-white/30">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Old Score</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">New Score</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Change</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Adjusted By</th>
                                            </tr>
                                        </thead>
                                <tbody>
                                            <?php foreach (
                                                $trustLogs
                                                as $log
                                            ): ?>
                                                <tr>
                                                    <td class="px-4 py-3 text-sm text-gray-900">
                                                        <?php echo date(
                                                            "M j, Y g:i A",
                                                            strtotime(
                                                                $log[
                                                                    "created_at"
                                                                ],
                                                            ),
                                                        ); ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-900"><?php echo number_format(
                                                        $log["old_score"],
                                                        1,
                                                    ); ?></td>
                                                    <td class="px-4 py-3 text-sm text-gray-900"><?php echo number_format(
                                                        $log["new_score"],
                                                        1,
                                                    ); ?></td>
                                                    <td class="px-4 py-3 text-sm">
                                                        <span class="font-medium <?php echo $log[
                                                            "new_score"
                                                        ] > $log["old_score"]
                                                            ? "text-green-600"
                                                            : "text-red-600"; ?>">
                                                            <?php echo $log[
                                                                "new_score"
                                                            ] >
                                                            $log["old_score"]
                                                                ? "+"
                                                                : ""; ?>
                                                            <?php echo number_format(
                                                                $log[
                                                                    "new_score"
                                                                ] -
                                                                    $log[
                                                                        "old_score"
                                                                    ],
                                                                1,
                                                            ); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-900"><?php echo htmlspecialchars(
                                                        $log["reason"],
                                                    ); ?></td>
                                                    <td class="px-4 py-3 text-sm text-gray-900"><?php echo htmlspecialchars(
                                                        $log[
                                                            "adjusted_by_name"
                                                        ],
                                                    ); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Report Verification Management -->
                        <div id="reports">
                            <h3 class="text-lg font-medium text-gray-800 mb-4">
                                Report Verification Management
                                <?php if (isset($rejectedUserLookup[$selectedUser["id"]])): ?>
                                    <span class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        <?php echo count($rejectedUserLookup[$selectedUser["id"]]); ?> recently rejected
                                    </span>
                                <?php endif; ?>
                            </h3>
                            <?php if (empty($userReports)): ?>
                                <p class="text-gray-600">No reports found for this user.</p>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-white/30">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Report ID</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Route</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Crowd Level</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Verifications</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                            </tr>
                                            <tbody class="bg-white/70 divide-y divide-gray-200">
                                            <?php foreach (
                                                $userReports
                                                as $report
                                            ): ?>
                                                <?php 
                                                $isRecentlyRejected = false;
                                                if (isset($rejectedUserLookup[$selectedUser["id"]])) {
                                                    foreach ($rejectedUserLookup[$selectedUser["id"]] as $rejected) {
                                                        if ($rejected['report_id'] == $report['id']) {
                                                            $isRecentlyRejected = true;
                                                            break;
                                                        }
                                                    }
                                                }
                                                ?>
                                                <tr class="<?php echo $report[
                                                    "status"
                                                ] === "rejected"
                                                    ? "bg-red-50"
                                                    : ""; ?> <?php echo $isRecentlyRejected ? "border-l-4 border-red-500" : ""; ?>">
                                                    <td class="px-4 py-3 text-sm text-gray-900">
                                                        #<?php echo $report[
                                                            "id"
                                                        ]; ?>
                                                        <?php if ($isRecentlyRejected): ?>
                                                            <span class="ml-2 text-xs text-red-600 font-medium">Recently Rejected</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-900"><?php echo htmlspecialchars(
                                                        $report["route_name"] ?:
                                                        "N/A",
                                                    ); ?></td>
                                                    <td class="px-4 py-3 text-sm">
                                                        <span class="px-2 py-1 rounded-full text-xs font-medium
                                                            <?php
                                                            $crowdColors = [
                                                                "Light" =>
                                                                    "bg-green-100 text-green-800",
                                                                "Moderate" =>
                                                                    "bg-yellow-100 text-yellow-800",
                                                                "Heavy" =>
                                                                    "bg-red-100 text-red-800",
                                                            ];
                                                            echo $crowdColors[
                                                                $report[
                                                                    "crowd_level"
                                                                ]
                                                            ] ??
                                                                "bg-gray-100 text-gray-800";
                                                            ?>">
                                                            <?php echo htmlspecialchars(
                                                                $report[
                                                                    "crowd_level"
                                                                ],
                                                            ); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-900"><?php echo date(
                                                        "M j, Y g:i A",
                                                        strtotime(
                                                            $report[
                                                                "timestamp"
                                                            ],
                                                        ),
                                                    ); ?></td>
                                                    <td class="px-4 py-3 text-sm">
                                                        <span class="px-2 py-1 rounded-full text-xs font-medium
                                                            <?php
                                                            $statusColors = [
                                                                "pending" =>
                                                                    "bg-yellow-100 text-yellow-800",
                                                                "verified" =>
                                                                    "bg-green-100 text-green-800",
                                                                "rejected" =>
                                                                    "bg-red-100 text-red-800",
                                                            ];
                                                            echo $statusColors[
                                                                $report[
                                                                    "status"
                                                                ]
                                                            ] ??
                                                                "bg-gray-100 text-gray-800";
                                                            ?>">
                                                            <?php echo ucfirst(
                                                                $report[
                                                                    "status"
                                                                ] ?:
                                                                "Unknown",
                                                            ); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-900"><?php echo $report[
                                                        "peer_verifications"
                                                    ] ?:
                                                        0; ?>/3</td>
                                                    <td class="px-4 py-3 text-sm">
                                                        <?php if (
                                                            $report[
                                                                "status"
                                                            ] === "pending"
                                                        ): ?>
                                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to reject this report?');">
                                                                <input type="hidden" name="action" value="reject_report">
                                                                <input type="hidden" name="report_id" value="<?php echo $report[
                                                                    "id"
                                                                ]; ?>">
                                                                <button type="submit" class="px-3 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700">
                                                                    Reject
                                                                </button>
                                                            </form>
                                                        <?php elseif (
                                                            $report[
                                                                "status"
                                                            ] === "rejected"
                                                        ): ?>
                                                            <button onclick="confirm('This report is already rejected.')" class="px-3 py-1 text-xs bg-gray-400 text-white rounded cursor-not-allowed" disabled>
                                                                Already Rejected
                                                            </button>
                                                        <?php else: ?>
                                                            <button onclick="confirm('This report is verified and cannot be rejected.')" class="px-3 py-1 text-xs bg-gray-400 text-white rounded cursor-not-allowed" disabled>
                                                                Verified
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

    </main>
</div><!-- /app-layout -->

<?php include "admin_sidebar_js.php"; ?>
</body>
</html>
