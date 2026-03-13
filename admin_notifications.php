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

    // Check for new reports
    if ($since) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS new_count, MAX(timestamp) AS latest_timestamp
            FROM reports
            WHERE timestamp > ?
        ");
        $stmt->execute([$since]);
        $reportData = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['new_count' => 0, 'latest_timestamp' => null];
    } else {
        $stmt = $pdo->query("
            SELECT COUNT(*) AS new_count, MAX(timestamp) AS latest_timestamp
            FROM reports
        ");
        $reportData = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['new_count' => 0, 'latest_timestamp' => null];
    }

    // Check for unread rejection notifications
    $stmt = $pdo->query("
        SELECT COUNT(*) AS unread_count, 
               GROUP_CONCAT(message SEPARATOR ' | ') AS messages,
               MAX(created_at) AS latest_notification
        FROM admin_notifications 
        WHERE is_read = 0 
        AND (type = 'report_rejection' OR type = 'admin_rejection')
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $notificationData = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['unread_count' => 0, 'messages' => '', 'latest_notification' => null];

    echo json_encode([
        'new_count' => (int)($reportData['new_count'] ?? 0),
        'latest_timestamp' => $reportData['latest_timestamp'] ?? null,
        'unread_notifications' => (int)($notificationData['unread_count'] ?? 0),
        'notification_messages' => $notificationData['messages'] ?? '',
        'latest_notification' => $notificationData['latest_notification'] ?? null,
        'total_alerts' => (int)($reportData['new_count'] ?? 0) + (int)($notificationData['unread_count'] ?? 0)
    ]);
} catch (PDOException $e) {
    error_log('Admin notifications error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}