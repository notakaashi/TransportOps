<?php
/**
 * Authentication helper functions
 */

/**
 * Configure secure session parameters and start session
 * Must be called before any output
 */
function secureSessionStart() {
    // Use basic session_start() for maximum compatibility
    // Mobile browsers can be picky about custom cookie parameters
    session_start();
}

/**
 * Regenerate session ID and destroy old session data
 * Used during login to prevent session fixation
 */
function regenerateSession() {
    // Regenerate session ID and delete old session
    session_regenerate_id(true);
}

/**
 * Complete session destruction for logout
 */
function destroySessionCompletely() {
    // Unset all session variables
    session_unset();
    
    // Get session cookie parameters
    $params = session_get_cookie_params();
    
    // Clear session cookie
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
    
    // Destroy session
    session_destroy();
}

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
 * Redirects non-admins to user login; unauthenticated users to admin login
 */
function checkAdminActive() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: admin_login.php');
        exit;
    }
    if ($_SESSION['role'] !== 'Admin') {
        header('Location: login.php');
        exit;
    }
    
    checkUserActive();
}
?>
