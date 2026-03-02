<?php
require_once 'auth_helper.php';
secureSessionStart();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$since = $_GET['since'] ?? null;

try {
    $pdo = getDBConnection();

    if ($since) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS new_count, MAX(timestamp) AS latest_timestamp
            FROM reports
            WHERE timestamp > ?
        ");
        $stmt->execute([$since]);
    } else {
        $stmt = $pdo->query("
            SELECT COUNT(*) AS new_count, MAX(timestamp) AS latest_timestamp
            FROM reports
        ");
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['new_count' => 0, 'latest_timestamp' => null];

    echo json_encode([
        'new_count' => (int)($row['new_count'] ?? 0),
        'latest_timestamp' => $row['latest_timestamp'] ?? null,
    ]);
} catch (PDOException $e) {
    error_log('Admin notifications error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}

