<?php
/**
 * Debug Trust Score Issue
 */
require_once 'auth_helper.php';
secureSessionStart();
require_once 'trust_helper.php';
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("Not logged in");
}

$user_id = (int)$_SESSION['user_id'];

echo "<h1>🔍 Trust Score Debug</h1>";

try {
    $pdo = getDBConnection();
    
    // Get current database score
    $stmt = $pdo->prepare("SELECT trust_score FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $dbScore = (float)$user['trust_score'];
    
    echo "<h2>Current Database Score: " . number_format($dbScore, 2) . "</h2>";
    
    // Calculate fresh score
    $calculatedScore = calculateTrustScore($user_id);
    echo "<h2>Fresh Calculated Score: " . number_format($calculatedScore, 2) . "</h2>";
    
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
    
    echo "<h3>📊 Manual Calculation:</h3>";
    echo "<p>Base: 50</p>";
    echo "<p>Reports (" . $stats['total_reports'] . ") × 5 = " . ($stats['total_reports'] * 5) . "</p>";
    echo "<p>Verified (" . $stats['verified_reports'] . ") × 10 = " . ($stats['verified_reports'] * 10) . "</p>";
    echo "<p>Rejected (" . $stats['rejected_reports'] . ") × -10 = " . ($stats['rejected_reports'] * -10) . "</p>";
    echo "<p>Expired (" . $stats['expired_reports'] . ") × -2 = " . ($stats['expired_reports'] * -2) . "</p>";
    echo "<p>Verifications (" . $verificationStats['verification_count'] . ") × 1 = " . ($verificationStats['verification_count']) . "</p>";
    
    $manualTotal = 50 + ($stats['total_reports'] * 5) + ($stats['verified_reports'] * 10) + ($verificationStats['verification_count']) + ($stats['rejected_reports'] * -10) + ($stats['expired_reports'] * -2);
    echo "<p><strong>Manual Total: " . number_format($manualTotal, 2) . "</strong></p>";
    
    echo "<h3>🔄 Update Test:</h3>";
    $result = updateUserTrustScore($user_id, 'Debug test');
    if ($result) {
        echo "<p style='color: green;'>✅ Update successful</p>";
        
        // Check updated score
        $stmt = $pdo->prepare("SELECT trust_score FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $updatedUser = $stmt->fetch();
        $newDbScore = (float)$updatedUser['trust_score'];
        
        echo "<p>Database score after update: " . number_format($newDbScore, 2) . "</p>";
        
        if (abs($newDbScore - $calculatedScore) > 0.1) {
            echo "<p style='color: red;'>❌ Score mismatch after update!</p>";
        } else {
            echo "<p style='color: green;'>✅ Score matches calculation!</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Update failed</p>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>
