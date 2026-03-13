<?php
require_once 'auth_helper.php';
secureSessionStart();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Commuter') {
    http_response_code(403);
    echo json_encode(['error' => 'Only commuters can reject reports.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$reportId = isset($input['report_id']) ? (int)$input['report_id'] : 0;

if ($reportId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing report_id.']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Load report and original reporter
    $stmt = $pdo->prepare("
        SELECT id, user_id, latitude, longitude, peer_verifications, is_verified, status
        FROM reports
        WHERE id = ?
    ");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        http_response_code(404);
        echo json_encode(['error' => 'Report not found.']);
        exit;
    }

    if ((int)$report['user_id'] === (int)$_SESSION['user_id']) {
        http_response_code(400);
        echo json_encode(['error' => 'You cannot reject your own report.']);
        exit;
    }

    // Check if user has already verified this report (can't reject if already verified)
    $stmt = $pdo->prepare("
        SELECT id FROM report_verifications
        WHERE report_id = ? AND verifier_user_id = ?
    ");
    $stmt->execute([$reportId, $_SESSION['user_id']]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['error' => 'You have already verified this report and cannot reject it.']);
        exit;
    }

    // Prevent duplicate rejection by same user (using report_verifications table with negative verification)
    $stmt = $pdo->prepare("
        SELECT id FROM report_verifications
        WHERE report_id = ? AND verifier_user_id = ? AND distance_km < 0
    ");
    $stmt->execute([$reportId, $_SESSION['user_id']]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['error' => 'You have already rejected this report.']);
        exit;
    }

    // Require original report to have coordinates for distance check (same as verification)
    if ($report['latitude'] === null || $report['longitude'] === null) {
        http_response_code(400);
        echo json_encode(['error' => 'This report has no location data to reject.']);
        exit;
    }

    // For rejection, we don't need user location - just record the rejection
    // Use -0.5 to indicate rejection (distinct from admin -999)
    $distanceKm = -0.5;

    $pdo->beginTransaction();

    // Insert rejection record in the same table as verifications (with negative distance)
    $stmt = $pdo->prepare("
        INSERT INTO report_verifications (report_id, verifier_user_id, latitude, longitude, distance_km)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$reportId, $_SESSION['user_id'], null, null, $distanceKm]);

    // Recompute verifications and rejections from source-of-truth table
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN distance_km >= 0 THEN 1 END) AS verification_count,
            COUNT(CASE WHEN distance_km < 0 THEN 1 END) AS rejection_count,
            COUNT(CASE WHEN distance_km = -999 THEN 1 END) AS admin_rejection_count
        FROM report_verifications 
        WHERE report_id = ?
    ");
    $stmt->execute([$reportId]);
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $verificationCount = (int)($counts['verification_count'] ?? 0);
    $rejectionCount = (int)($counts['rejection_count'] ?? 0);
    $adminRejectionCount = (int)($counts['admin_rejection_count'] ?? 0);
    
    // Calculate net verification score (verifications - rejections)
    // Admin rejections immediately reject the report regardless of count
    $netScore = $verificationCount - $rejectionCount;
    $nowVerified = $netScore >= 3; // Need net 3+ verifications
    $nowRejected = $netScore <= -3 || $adminRejectionCount > 0; // Net -3 or admin rejection

    // Update report with new counts and status
    $newStatus = $report['status'];
    if ($nowRejected) {
        $newStatus = 'rejected';
    } elseif ($nowVerified) {
        $newStatus = 'verified';
    } else {
        $newStatus = 'pending';
    }

    $stmt = $pdo->prepare("
        UPDATE reports
        SET peer_verifications = ?,
            is_verified = ?,
            status = ?
        WHERE id = ?
    ");
    $stmt->execute([$netScore, $nowVerified ? 1 : 0, $newStatus, $reportId]);

    $pdo->commit();

    // Update trust scores after commit
    require_once 'trust_helper.php';
    
    // Update rejecter's trust score
    updateUserTrustScore($_SESSION['user_id'], 'Trust score recalculated (rejection)');
    
    // Penalize original reporter with -2 verification score
    if ($report['user_id']) {
        // Get current trust score of reporter
        $stmt = $pdo->prepare("SELECT trust_score FROM users WHERE id = ?");
        $stmt->execute([$report['user_id']]);
        $reporterRow = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($reporterRow) {
            $currentTrustScore = (float) $reporterRow['trust_score'];
            
            // Apply penalty: -2 for simple rejection, -10 for rejected report
            $penalty = $nowRejected ? 10 : 2;
            
            $newTrustScore = max(0, $currentTrustScore - $penalty); // Don't go below 0
            
            // Update reporter's trust score with penalty
            $stmt = $pdo->prepare("UPDATE users SET trust_score = ? WHERE id = ?");
            $stmt->execute([$newTrustScore, $report['user_id']]);
            
            // Log the trust score change
            $stmt = $pdo->prepare("
                INSERT INTO trust_score_logs (user_id, old_score, new_score, reason, adjusted_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $penaltyReason = $nowRejected ? 'Report rejected by peers (-10 penalty)' : 'Report rejection (-2 penalty)';
            $stmt->execute([
                $report['user_id'], 
                $currentTrustScore, 
                $newTrustScore, 
                $penaltyReason,
                $_SESSION['user_id']
            ]);
            
            error_log("Reporter {$report['user_id']} trust score reduced from {$currentTrustScore} to {$newTrustScore} due to: {$penaltyReason}");
        }
    }
    
    // Notify admin about the rejection
    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_notifications (type, message, report_id, user_id, created_at)
            VALUES ('report_rejection', ?, ?, ?, NOW())
        ");
        $notificationMessage = "Report #{$reportId} was rejected by a commuter. Current net score: {$netScore}";
        $stmt->execute([$notificationMessage, $reportId, $_SESSION['user_id']]);
        error_log("Admin notification created for report rejection: {$notificationMessage}");
    } catch (Exception $e) {
        error_log("Failed to create admin notification: " . $e->getMessage());
        // Continue even if notification fails
    }

    echo json_encode([
        'success' => true,
        'peer_verifications' => $netScore,
        'verification_count' => $verificationCount,
        'rejection_count' => $rejectionCount,
        'is_verified' => $nowVerified,
        'status' => $newStatus,
        'net_score' => $netScore
    ]);
} catch (PDOException $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Reject report error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error while rejecting report.']);
}
