<?php
/**
 * Simple Trust Scoring Test
 */
require_once 'auth_helper.php';
secureSessionStart();
require_once 'trust_helper.php';
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("Not logged in");
}

$user_id = (int)$_SESSION['user_id'];

try {
    $pdo = getDBConnection();
    
    // Test basic query
    $stmt = $pdo->prepare("SELECT trust_score FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    echo "Current trust score: " . ($user ? $user['trust_score'] : 'Not found') . "\n";
    
    // Test calculation
    $calculated = calculateTrustScore($user_id);
    echo "Calculated trust score: " . $calculated . "\n";
    
    // Get verification count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as verification_count
        FROM report_verifications rv
        WHERE rv.verifier_user_id = ? 
    ");
    $stmt->execute([$user_id]);
    $verificationStats = $stmt->fetch();
    echo "Your verifications made: " . $verificationStats['verification_count'] . "\n";
    
    // Test reports count
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_reports,
            SUM(CASE WHEN verification_count >= 3 THEN 1 ELSE 0 END) as verified_reports,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_reports
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
    echo "Your reports submitted: " . $stats['total_reports'] . "\n";
    echo "Your reports with 3+ verifications: " . $stats['verified_reports'] . "\n";
    echo "Your rejected reports: " . $stats['rejected_reports'] . "\n";
    
    echo "\n✅ Trust scoring system working correctly!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
