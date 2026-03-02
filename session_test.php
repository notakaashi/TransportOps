<?php
/**
 * Basic Session Test - No database, no auth, just session testing
 */

// Start session with minimal settings
session_start();

echo "<h1>Basic Session Test</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_value = $_POST['test_value'] ?? '';
    
    if (!empty($test_value)) {
        $_SESSION['test'] = $test_value;
        echo "<p style='color: green;'>✅ Session set: " . htmlspecialchars($test_value) . "</p>";
        echo "<p><a href='session_test.php'>Check if session persists</a></p>";
    } else {
        echo "<p style='color: red;'>❌ Please enter a value</p>";
    }
} else {
    if (isset($_SESSION['test'])) {
        echo "<p style='color: green;'>✅ Session found: " . htmlspecialchars($_SESSION['test']) . "</p>";
        echo "<p>Session ID: " . session_id() . "</p>";
        echo "<p><a href='session_test.php?action=clear'>Clear session</a></p>";
    } else {
        echo "<p style='color: orange;'>⚠️ No session data found</p>";
        echo "<p>Session ID: " . session_id() . "</p>";
    }
    
    echo "<hr>";
    echo "<h2>Set Session Test</h2>";
    echo "<form method='POST'>";
    echo "<input type='text' name='test_value' placeholder='Enter any value' required>";
    echo "<button type='submit'>Set Session</button>";
    echo "</form>";
}

if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    session_destroy();
    session_start();
    echo "<p style='color: blue;'>🔄 Session cleared</p>";
}

echo "<hr>";
echo "<h2>Debug Info</h2>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Not Active') . "</p>";
echo "<p><strong>Session Data:</strong> <pre>" . print_r($_SESSION, true) . "</pre></p>";
echo "<p><strong>Cookies:</strong> <pre>" . print_r($_COOKIE, true) . "</pre></p>";
echo "<p><strong>User Agent:</strong> " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "</p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1 { color: #333; }
h2 { color: #666; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
input { padding: 10px; margin: 5px 0; }
button { padding: 10px 20px; background: #007cba; color: white; border: none; cursor: pointer; }
pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
</style>
