<?php
require_once 'auth_helper.php';
secureSessionStart();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Commuter') {
    http_response_code(403);
    echo json_encode(['error' => 'Only commuters can verify reports.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

$input = $_POST;
$reportId = isset($input['report_id']) ? (int)$input['report_id'] : 0;
$lat = isset($input['latitude']) ? (float)$input['latitude'] : null;
$lng = isset($input['longitude']) ? (float)$input['longitude'] : null;

if ($reportId <= 0 || $lat === null || $lng === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing report_id or location.']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Load report and original reporter
    $stmt = $pdo->prepare("
        SELECT id, user_id, latitude, longitude, peer_verifications, is_verified
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
        echo json_encode(['error' => 'You cannot verify your own report.']);
        exit;
    }

    // Prevent duplicate verification by same user
    $stmt = $pdo->prepare("
        SELECT id FROM report_verifications
        WHERE report_id = ? AND verifier_user_id = ?
    ");
    $stmt->execute([$reportId, $_SESSION['user_id']]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['error' => 'You have already verified this report.']);
        exit;
    }

    // Require original report to have coordinates
    if ($report['latitude'] === null || $report['longitude'] === null) {
        http_response_code(400);
        echo json_encode(['error' => 'This report has no location data to verify against.']);
        exit;
    }

    $repLat = (float)$report['latitude'];
    $repLng = (float)$report['longitude'];

    // Haversine formula to compute distance in km
    $earthRadius = 6371; // km
    $dLat = deg2rad($lat - $repLat);
    $dLng = deg2rad($lng - $repLng);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($repLat)) * cos(deg2rad($lat)) *
         sin($dLng / 2) * sin($dLng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $distanceKm = $earthRadius * $c;

    // Require user to be within 0.5 km of report location
    if ($distanceKm > 0.5) {
        http_response_code(400);
        echo json_encode([
            'error' => 'You are too far from the reported location to verify it.',
            'distance_km' => round($distanceKm, 2)
        ]);
        exit;
    }

    $wasVerified = (int)($report['is_verified'] ?? 0) === 1;

    $pdo->beginTransaction();

    // Insert verification record
    $stmt = $pdo->prepare("
        INSERT INTO report_verifications (report_id, verifier_user_id, latitude, longitude, distance_km)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$reportId, $_SESSION['user_id'], $lat, $lng, $distanceKm]);

    // Recompute verifications from source-of-truth table to avoid drift
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM report_verifications WHERE report_id = ?");
    $stmt->execute([$reportId]);
    $pvCount = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
    $nowVerified = $pvCount >= 3;

    // Persist computed count and verification state (keep status 'rejected' if already rejected)
    $stmt = $pdo->prepare("
        UPDATE reports
        SET peer_verifications = ?,
            is_verified = ?,
            status = CASE
                WHEN status = 'rejected' THEN status
                WHEN ? = 1 THEN 'verified'
                ELSE status
            END
        WHERE id = ?
    ");
    $stmt->execute([$pvCount, $nowVerified ? 1 : 0, $nowVerified ? 1 : 0, $reportId]);

    $pdo->commit();

    // Update trust scores after commit
    require_once 'trust_helper.php';
    
    // Update verifier's score (+1 point for verification)
    updateUserTrustScore($_SESSION['user_id'], 'Verified report: +1 point');
    $verifierPointsAwarded = 1;
    $verifierNewScore = null;
    try {
        $stmt = $pdo->prepare("SELECT trust_score FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $verifierNewScore = (float) $row['trust_score'];
        }
    } catch (Exception $e) {
        $verifierNewScore = null;
    }
    
    // Check if this verification brought the report to 3+ verifications
    $reporterBonusAwarded = false;
    if (!$wasVerified && $nowVerified) {
        // Get the original reporter's ID to give them the 10-point bonus
        $stmt = $pdo->prepare("SELECT user_id FROM reports WHERE id = ?");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch();
        
        if ($report && $report['user_id']) {
            updateUserTrustScore($report['user_id'], 'Report reached 3+ verifications: +10 bonus points');
            $reporterBonusAwarded = true;
        }
    }

    echo json_encode([
        'success' => true,
        'peer_verifications' => $pvCount,
        'is_verified' => $nowVerified,
        'distance_km' => round($distanceKm, 2),
        'verifier_points_awarded' => $verifierPointsAwarded,
        'verifier_new_trust_score' => $verifierNewScore,
        'reporter_bonus_awarded' => $reporterBonusAwarded
    ]);
} catch (PDOException $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Verify report error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error while verifying report.']);
}

