<?php
/**
 * Test Trust Score Without Login
 */
require_once 'trust_helper.php';
require_once 'db.php';

echo "<h1>🧪 Trust Score Test (No Login Required)</h1>";

try {
    $pdo = getDBConnection();
    
    // Test with a known user ID (change as needed)
    $testUserId = 1; // Change this to test different users
    
    echo "<h2>Testing User ID: " . $testUserId . "</h2>";
    
    // Get current database score
    $stmt = $pdo->prepare("SELECT trust_score FROM users WHERE id = ?");
    $stmt->execute([$testUserId]);
    $user = $stmt->fetch();
    
    echo "<h3>Current Database Score: " . ($user ? number_format($user['trust_score'], 2) : 'Not found') . "</h3>";
    
    // Calculate fresh score
    $calculatedScore = calculateTrustScore($testUserId);
    echo "<h3>Fresh Calculated Score: " . number_format($calculatedScore, 2) . "</h3>";
    
    // Get detailed breakdown
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
    $stmt->execute([$testUserId]);
    $stats = $stmt->fetch();
    
    // Get verification count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as verification_count
        FROM report_verifications rv
        WHERE rv.verifier_user_id = ? 
    ");
    $stmt->execute([$testUserId]);
    $verificationStats = $stmt->fetch();
    
    echo "<div style='background: #f5f5f5; padding: 20px; margin: 20px; border-radius: 8px;'>";
    echo "<h4>📊 Score Breakdown:</h4>";
    echo "<p><strong>Base Score:</strong> 50</p>";
    echo "<p><strong>Reports Submitted:</strong> " . $stats['total_reports'] . " × 5 = " . ($stats['total_reports'] * 5) . " points</p>";
    echo "<p><strong>Reports with 3+ Verifications:</strong> " . $stats['verified_reports'] . " × 10 = " . ($stats['verified_reports'] * 10) . " points</p>";
    echo "<p><strong>Verifications Made:</strong> " . $verificationStats['verification_count'] . " × 1 = " . ($verificationStats['verification_count']) . " points</p>";
    echo "<p><strong>Rejected Reports:</strong> " . $stats['rejected_reports'] . " × -10 = " . ($stats['rejected_reports'] * -10) . " points</p>";
    echo "<p><strong>Expired Reports:</strong> " . $stats['expired_reports'] . " × -2 = " . ($stats['expired_reports'] * -2) . " points</p>";
    
    $total = 50 + ($stats['total_reports'] * 5) + ($stats['verified_reports'] * 10) + $verificationStats['verification_count'] + ($stats['rejected_reports'] * -10) + ($stats['expired_reports'] * -2);
    echo "<hr>";
    echo "<p><strong>Total Score:</strong> " . number_format($total, 2) . "</p>";
    echo "</div>";
    
    if (abs(($user ? $user['trust_score'] : 50) - $calculatedScore) > 0.1) {
        echo "<h3 style='color: red;'>❌ Score mismatch!</h3>";
        echo "<p>Database: " . number_format(($user ? $user['trust_score'] : 50), 2) . "</p>";
        echo "<p>Calculated: " . number_format($calculatedScore, 2) . "</p>";
        
        // Show what the score should be after update
        echo "<h4>🔄 After Database Sync:</h4>";
        echo "<p>The score should be updated to: " . number_format($calculatedScore, 2) . "</p>";
    } else {
        echo "<h3 style='color: green;'>✅ Scores match!</h3>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>
