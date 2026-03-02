<?php
/**
 * Test Trust Scoring System
 * Tests the new trust scoring logic
 */
require_once 'auth_helper.php';
secureSessionStart();
require_once 'trust_helper.php';
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trust Scoring Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-8">🧪 Trust Scoring System Test</h1>
        
        <?php
        // Test current trust score
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT trust_score FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if ($user) {
                echo "<div class='bg-white rounded-lg shadow p-6 mb-6'>";
                echo "<h2 class='text-xl font-semibold mb-4'>Current Trust Score</h2>";
                echo "<p class='text-2xl font-bold text-blue-600'>" . number_format($user['trust_score'], 1) . "</p>";
                echo "</div>";
            }
            
            // Test trust score calculation
            $calculatedScore = calculateTrustScore($user_id);
            echo "<div class='bg-white rounded-lg shadow p-6 mb-6'>";
            echo "<h2 class='text-xl font-semibold mb-4'>Calculated Trust Score</h2>";
            echo "<p class='text-2xl font-bold text-green-600'>" . number_format($calculatedScore, 1) . "</p>";
            
            if ($user && abs($user['trust_score'] - $calculatedScore) > 0.1) {
                echo "<p class='text-yellow-600 mt-2'>⚠️ Score mismatch detected! Updating...</p>";
                updateUserTrustScore($user_id, 'Score synchronization update');
            } else {
                echo "<p class='text-green-600 mt-2'>✅ Scores are synchronized</p>";
            }
            echo "</div>";
            
            // Show scoring breakdown
            echo "<div class='bg-white rounded-lg shadow p-6 mb-6'>";
            echo "<h2 class='text-xl font-semibold mb-4'>📊 Scoring Breakdown</h2>";
            
            // Get detailed stats
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_reports,
                    SUM(CASE WHEN verification_count >= 3 THEN 1 ELSE 0 END) as verified_reports,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_reports,
                    SUM(CASE WHEN is_verified = 0 AND timestamp < DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as expired_reports
                FROM (
                    SELECT 
                        r.*,
                        (SELECT COUNT(*) FROM report_verifications rv WHERE rv.report_id = r.id) as verification_count
                    FROM reports r 
                    WHERE r.user_id = ?
                ) as user_reports
            ");
            $stmt->execute([$user_id]);
            $stats = $stmt->fetch();
            
            // Get verification count
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as verification_count
                FROM report_verifications rv
                WHERE rv.verifier_user_id = ? 
            ");
            $stmt->execute([$user_id]);
            $verificationStats = $stmt->fetch();
            
            echo "<div class='space-y-2'>";
            echo "<p><strong>Base Score:</strong> 50</p>";
            echo "<p><strong>Reports Submitted:</strong> " . $stats['total_reports'] . " × 5 = " . ($stats['total_reports'] * 5) . " points</p>";
            echo "<p><strong>Reports with 3+ Verifications:</strong> " . $stats['verified_reports'] . " × 10 = " . ($stats['verified_reports'] * 10) . " points</p>";
            echo "<p><strong>Verifications Made:</strong> " . $verificationStats['verification_count'] . " × 1 = " . ($verificationStats['verification_count']) . " points</p>";
            echo "<p><strong>Rejected Reports:</strong> " . $stats['rejected_reports'] . " × -10 = " . ($stats['rejected_reports'] * -10) . " points</p>";
            echo "<p><strong>Expired Reports:</strong> " . $stats['expired_reports'] . " × -2 = " . ($stats['expired_reports'] * -2) . " points</p>";
            
            $total = 50 + ($stats['total_reports'] * 5) + ($stats['verified_reports'] * 10) + $verificationStats['verification_count'] + ($stats['rejected_reports'] * -10) + ($stats['expired_reports'] * -2);
            echo "<hr class='my-3'>";
            echo "<p class='font-bold text-lg'>Total: " . $total . "</p>";
            echo "</div>";
            echo "</div>";
            
            // Test manual score update
            echo "<div class='bg-white rounded-lg shadow p-6 mb-6'>";
            echo "<h2 class='text-xl font-semibold mb-4'>🔄 Manual Score Update Test</h2>";
            
            $result = updateUserTrustScore($user_id, 'Manual test update');
            if ($result) {
                echo "<p class='text-green-600'>✅ Manual score update successful</p>";
            } else {
                echo "<p class='text-red-600'>❌ Manual score update failed</p>";
            }
            echo "</div>";
            
            // Show recent trust score logs
            echo "<div class='bg-white rounded-lg shadow p-6'>";
            echo "<h2 class='text-xl font-semibold mb-4'>📝 Recent Trust Score Logs</h2>";
            
            $logs = getTrustScoreLogs($user_id);
            if (!empty($logs)) {
                echo "<div class='space-y-2'>";
                foreach (array_slice($logs, 0, 10) as $log) {
                    echo "<div class='border-l-4 border-blue-500 pl-4'>";
                    echo "<p class='text-sm'><strong>" . htmlspecialchars($log['reason']) . "</strong></p>";
                    echo "<p class='text-xs text-gray-600'>" . $log['old_score'] . " → " . $log['new_score'] . " (" . date('M j, g:i A', strtotime($log['created_at'])) . ")</p>";
                    echo "</div>";
                }
                echo "</div>";
            } else {
                echo "<p class='text-gray-500'>No trust score logs found</p>";
            }
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded'>";
            echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage());
            echo "</div>";
        }
        ?>
        
        <div class="mt-8">
            <a href="user_dashboard.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">← Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
