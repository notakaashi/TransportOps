<?php
/**
 * Debug Login Script
 * Helps identify mobile login issues
 */

require_once 'auth_helper.php';
secureSessionStart();
require_once 'db.php';

echo "<h1>Login Debug Information</h1>";

// Show session info
echo "<h2>Session Information:</h2>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Not Active') . "\n";
echo "Session Data: " . print_r($_SESSION, true) . "\n";
echo "</pre>";

// Show cookie info
echo "<h2>Cookie Information:</h2>";
echo "<pre>";
echo "Cookies: " . print_r($_COOKIE, true) . "\n";
echo "</pre>";

// Show server info
echo "<h2>Server Information:</h2>";
echo "<pre>";
echo "HTTP Host: " . ($_SERVER['HTTP_HOST'] ?? 'Not set') . "\n";
echo "HTTPS: " . (isset($_SERVER['HTTPS']) ? 'On' : 'Off') . "\n";
echo "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Not set') . "\n";
echo "Request Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "</pre>";

// Show session cookie parameters
echo "<h2>Session Cookie Parameters:</h2>";
echo "<pre>";
$params = session_get_cookie_params();
echo "Lifetime: " . $params['lifetime'] . "\n";
echo "Path: " . $params['path'] . "\n";
echo "Domain: " . $params['domain'] . "\n";
echo "Secure: " . ($params['secure'] ? 'true' : 'false') . "\n";
echo "HttpOnly: " . ($params['httponly'] ? 'true' : 'false') . "\n";
echo "SameSite: " . ($params['samesite'] ?? 'Not set') . "\n";
echo "</pre>";

// Test form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>Form Submission Test:</h2>";
    echo "<pre>";
    echo "POST Data: " . print_r($_POST, true) . "\n";
    echo "</pre>";
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!empty($email) && !empty($password)) {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id, name, email, password, role, is_active FROM users WHERE LOWER(email) = LOWER(?)");
            $stmt->execute([trim($email)]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                echo "<p style='color: green;'>✅ Password verification successful!</p>";
                echo "<p>User found: " . htmlspecialchars($user['name']) . " (" . htmlspecialchars($user['role']) . ")</p>";
                echo "<p>Active: " . ($user['is_active'] ? 'Yes' : 'No') . "</p>";
                
                if ($user['role'] !== 'Admin' && $user['is_active']) {
                    echo "<p style='color: blue;'>🔄 Testing session regeneration...</p>";
                    
                    // Test session setting before regeneration
                    $_SESSION['test_before'] = 'Before regeneration';
                    echo "<p>Session before regeneration ID: " . session_id() . "</p>";
                    
                    regenerateSession();
                    
                    // Test session setting after regeneration
                    $_SESSION['test_after'] = 'After regeneration';
                    echo "<p>Session after regeneration ID: " . session_id() . "</p>";
                    echo "<p>Test data in session: " . print_r($_SESSION, true) . "</p>";
                    
                    // Set actual user session data
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    
                    echo "<p style='color: green;'>✅ Session data set successfully!</p>";
                    echo "<p><a href='user_dashboard.php'>Try going to dashboard</a></p>";
                }
            } else {
                echo "<p style='color: red;'>❌ Invalid email or password</p>";
            }
        } catch (PDOException $e) {
            echo "<p style='color: red;'>Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
        h2 { color: #333; border-bottom: 2px solid #007cba; padding-bottom: 5px; }
        form { margin-top: 20px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        input { display: block; margin: 10px 0; padding: 10px; width: 300px; }
        button { padding: 10px 20px; background: #007cba; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <form method="POST">
        <h3>Test Login</h3>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Test Login</button>
    </form>
    
    <p><a href="login.php">← Back to normal login</a></p>
</body>
</html>
