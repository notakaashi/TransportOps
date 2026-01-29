<?php
session_start();
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

    $pdo->beginTransaction();

    // Insert verification record
    $stmt = $pdo->prepare("
        INSERT INTO report_verifications (report_id, verifier_user_id, latitude, longitude, distance_km)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$reportId, $_SESSION['user_id'], $lat, $lng, $distanceKm]);

    // Update report peer_verifications and is_verified when threshold reached
    $stmt = $pdo->prepare("
        UPDATE reports
        SET peer_verifications = peer_verifications + 1,
            is_verified = CASE WHEN peer_verifications + 1 >= 3 THEN 1 ELSE is_verified END
        WHERE id = ?
    ");
    $stmt->execute([$reportId]);

    // Get updated values
    $stmt = $pdo->prepare("
        SELECT peer_verifications, is_verified
        FROM reports
        WHERE id = ?
    ");
    $stmt->execute([$reportId]);
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'peer_verifications' => (int)$updated['peer_verifications'],
        'is_verified' => (int)$updated['is_verified'] === 1,
        'distance_km' => round($distanceKm, 2)
    ]);
} catch (PDOException $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Verify report error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error while verifying report.']);
}

