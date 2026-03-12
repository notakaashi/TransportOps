<?php
require_once 'auth_helper.php';
secureSessionStart();
require_once 'db.php';
require_once 'trust_helper.php';

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

    $stmt = $pdo->prepare("SELECT id, user_id, status FROM reports WHERE id = ?");
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

    if ($report['status'] === 'rejected') {
        echo json_encode(['error' => 'This report has already been rejected.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM report_rejections WHERE report_id = ? AND rejecter_user_id = ?");
    $stmt->execute([$reportId, $_SESSION['user_id']]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['error' => 'You have already rejected this report.']);
        exit;
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO report_rejections (report_id, rejecter_user_id) VALUES (?, ?)");
    $stmt->execute([$reportId, $_SESSION['user_id']]);

    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM report_rejections WHERE report_id = ?");
    $stmt->execute([$reportId]);
    $rejectionCount = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

    $newStatus = $report['status'];
    if ($rejectionCount >= 3) {
        $newStatus = 'rejected';
        updateUserTrustScore($report['user_id'], 'Report rejected by 3+ commuters');
    }

    $stmt = $pdo->prepare("UPDATE reports SET rejections = ?, status = ? WHERE id = ?");
    $stmt->execute([$rejectionCount, $newStatus, $reportId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'rejections' => $rejectionCount,
        'status' => $newStatus
    ]);
} catch (PDOException $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Reject report error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error while rejecting report.']);
}
