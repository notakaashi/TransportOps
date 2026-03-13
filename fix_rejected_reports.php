<?php
require_once 'db.php';

try {
    $pdo = getDBConnection();
    
    echo "Fixing rejected reports data...\n";
    
    // Get all rejected reports
    $stmt = $pdo->query("
        SELECT r.id, r.status, r.peer_verifications, r.is_verified
        FROM reports r
        WHERE r.status = 'rejected'
    ");
    $rejectedReports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($rejectedReports) . " rejected reports to fix\n";
    
    foreach ($rejectedReports as $report) {
        // Calculate correct verification counts
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN distance_km >= 0 THEN 1 END) AS verification_count,
                COUNT(CASE WHEN distance_km < 0 THEN 1 END) AS rejection_count,
                COUNT(CASE WHEN distance_km = -999 THEN 1 END) AS admin_rejection_count
            FROM report_verifications 
            WHERE report_id = ?
        ");
        $stmt->execute([$report['id']]);
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $verificationCount = (int)($counts['verification_count'] ?? 0);
        $rejectionCount = (int)($counts['rejection_count'] ?? 0);
        $adminRejectionCount = (int)($counts['admin_rejection_count'] ?? 0);
        
        // Calculate net score
        $netScore = $verificationCount - $rejectionCount;
        $nowVerified = $netScore >= 3;
        $nowRejected = $netScore <= -3 || $adminRejectionCount > 0;
        
        // Update the report with correct data
        $newStatus = $nowRejected ? 'rejected' : ($nowVerified ? 'verified' : 'pending');
        
        $stmt = $pdo->prepare("
            UPDATE reports
            SET peer_verifications = ?,
                is_verified = ?,
                status = ?
            WHERE id = ?
        ");
        $stmt->execute([$netScore, $nowVerified ? 1 : 0, $newStatus, $report['id']]);
        
        echo "Report #{$report['id']}: verifications={$verificationCount}, rejections={$rejectionCount}, net={$netScore}, status={$newStatus}\n";
    }
    
    echo "\nFix completed!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
