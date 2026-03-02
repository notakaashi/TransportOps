<?php
/**
 * Simple test for public profile functionality
 */

require_once 'auth_helper.php';
secureSessionStart();
require_once 'db.php';
require_once 'trust_helper.php';

// Get user ID from URL parameter
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 1;

echo "<h1>Profile Test for User ID: $userId</h1>";

// Test direct database query
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, name, trust_score FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "<p style='color: green;'>✅ User found: {$user['name']} (Trust Score: {$user['trust_score']})</p>";
    } else {
        echo "<p style='color: red;'>❌ User not found in database</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test getUserPublicProfile function
echo "<h2>Testing getUserPublicProfile function:</h2>";
$profile = getUserPublicProfile($userId);

if ($profile) {
    echo "<p style='color: green;'>✅ getUserPublicProfile returned data</p>";
    echo "<pre>" . print_r($profile, true) . "</pre>";
} else {
    echo "<p style='color: red;'>❌ getUserPublicProfile returned false</p>";
}

// Test trust badge
echo "<h2>Testing trust badge:</h2>";
if ($user) {
    $badge = getTrustBadge($user['trust_score']);
    echo "<p>Badge for trust score {$user['trust_score']}: {$badge['label']}</p>";
}

echo "<hr>";
echo "<p><a href='reports_map.php'>← Back to Reports Map</a></p>";
?>
