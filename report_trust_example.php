<?php
/**
 * Example: How to integrate trust badges into report displays
 * This shows how to modify existing report pages to show trust information
 */

require_once 'auth_helper.php';
secureSessionStart();
require_once 'db.php';
require_once 'trust_helper.php';
require_once 'trust_badge_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get sample reports with user trust information
try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT 
            r.id,
            r.crowd_level,
            r.delay_reason,
            r.comments,
            r.created_at,
            r.status,
            u.id as user_id,
            u.name as user_name,
            u.trust_score,
            rd.route_name,
            (SELECT COUNT(*) FROM report_verifications rv WHERE rv.report_id = r.id AND rv.is_verified = 1) as verification_count
        FROM reports r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN route_definitions rd ON r.route_id = rd.id
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $reports = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Report trust example error: " . $e->getMessage());
    $reports = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trust Badge Integration Example</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#F3F4F6] min-h-screen p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Trust Badge Integration Example</h1>
        
        <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Recent Reports with Trust Information</h2>
            
            <?php if (empty($reports)): ?>
                <p class="text-gray-600">No reports found.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($reports as $report): ?>
                        <?php echo renderReportWithTrust($report); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="bg-blue-50 rounded-2xl p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">How to Integrate Trust Badges</h3>
            <div class="space-y-3 text-sm">
                <p><strong>1. Include the helper files:</strong></p>
                <code class="block bg-gray-100 p-2 rounded">require_once 'trust_helper.php';<br>require_once 'trust_badge_helper.php';</code>
                
                <p><strong>2. Modify your report query to include user trust_score:</strong></p>
                <code class="block bg-gray-100 p-2 rounded">SELECT r.*, u.trust_score FROM reports r JOIN users u ON r.user_id = u.id</code>
                
                <p><strong>3. Use the renderReportWithTrust() function:</strong></p>
                <code class="block bg-gray-100 p-2 rounded">&lt;?php echo renderReportWithTrust($report); ?&gt;</code>
                
                <p><strong>4. For individual badges, use renderTrustBadge():</strong></p>
                <code class="block bg-gray-100 p-2 rounded">&lt;?php echo renderTrustBadge($user['trust_score']); ?&gt;</code>
            </div>
        </div>
        
        <div class="mt-6 text-center">
            <a href="user_dashboard.php" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>
