<?php
/**
 * Authentication helper functions
 */

/**
 * Check if current user is active
 * Redirects to login with error message if user is not active
 */
function checkUserActive() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    
    try {
        require_once 'db.php';
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user || !$user['is_active']) {
            // Destroy session and redirect to login with error
            session_destroy();
            header('Location: login.php?error=deactivated');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Active user check error: " . $e->getMessage());
        // On error, allow access but log the issue
    }
}

/**
 * Check if user is admin and active
 */
function checkAdminActive() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
        header('Location: login.php');
        exit;
    }
    
    checkUserActive();
}
?>
