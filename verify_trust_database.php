<?php
/**
 * Verify Trust Scoring Database Structure
 */
require_once 'db.php';

echo "<h1>🔍 Trust Scoring Database Verification</h1>";

try {
    $pdo = getDBConnection();
    
    // Check users table structure
    echo "<h2>Users Table:</h2>";
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check reports table structure
    echo "<h2>Reports Table:</h2>";
    $stmt = $pdo->query("DESCRIBE reports");
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check report_verifications table structure
    echo "<h2>Report Verifications Table:</h2>";
    $stmt = $pdo->query("DESCRIBE report_verifications");
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test trust score calculation
    echo "<h2>Trust Score Test:</h2>";
    
    // Get a sample user
    $stmt = $pdo->query("SELECT id, name, trust_score FROM users LIMIT 5");
    $users = $stmt->fetchAll();
    
    foreach ($users as $user) {
        echo "<h3>User: " . htmlspecialchars($user['name']) . " (ID: " . $user['id'] . ")</h3>";
        echo "<p>Current trust score: " . $user['trust_score'] . "</p>";
        
        // Test the calculation
        require_once 'trust_helper.php';
        $calculated = calculateTrustScore($user['id']);
        echo "<p>Calculated trust score: " . $calculated . "</p>";
        
        if (abs($user['trust_score'] - $calculated) > 0.1) {
            echo "<p style='color: red;'>❌ Score mismatch!</p>";
        } else {
            echo "<p style='color: green;'>✅ Scores match</p>";
        }
        echo "<hr>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>
