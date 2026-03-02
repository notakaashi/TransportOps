<?php
/**
 * Simple Dashboard Test - Check if session works
 */

require_once 'auth_helper.php';
secureSessionStart();
require_once 'db.php';

error_log("SIMPLE DASHBOARD - Session ID: " . session_id());
error_log("SIMPLE DASHBOARD - Session data: " . print_r($_SESSION, true));
error_log("SIMPLE DASHBOARD - Cookies: " . print_r($_COOKIE, true));

if (!isset($_SESSION['user_id'])) {
    error_log("SIMPLE DASHBOARD - No user_id found, redirecting to login");
    header('Location: login_simple.php');
    exit;
}

echo "<h1>Simple Dashboard Test</h1>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>User ID:</strong> " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";
echo "<p><strong>User Name:</strong> " . ($_SESSION['user_name'] ?? 'Not set') . "</p>";
echo "<p><strong>User Email:</strong> " . ($_SESSION['user_email'] ?? 'Not set') . "</p>";
echo "<p><strong>Role:</strong> " . ($_SESSION['role'] ?? 'Not set') . "</p>";

echo "<h2>Session Data:</h2>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

echo "<h2>Cookie Data:</h2>";
echo "<pre>" . print_r($_COOKIE, true) . "</pre>";

echo "<p><a href='login_simple.php'>Back to Simple Login</a></p>";
echo "<p><a href='logout.php'>Logout</a></p>";
?>
