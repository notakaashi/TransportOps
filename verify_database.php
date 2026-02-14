<?php
/**
 * Database Verification Script
 * Verifies that profile image database structure is correct
 */
require_once 'db.php';

echo "<h1>Database Profile Image Verification</h1>";

try {
    $pdo = getDBConnection();
    
    echo "<h2>✅ Database Connection: Successful</h2>";
    
    // Check table structure
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Users Table Structure:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    
    $hasProfileImage = false;
    $hasProfileIndex = false;
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "</tr>";
        
        if ($column['Field'] === 'profile_image') {
            $hasProfileImage = true;
        }
    }
    
    echo "</table>";
    
    // Check indexes
    $stmt = $pdo->query("SHOW INDEX FROM users");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Indexes on Users Table:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Key Name</th><th>Column</th><th>Unique</th></tr>";
    
    foreach ($indexes as $index) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($index['Key_name']) . "</td>";
        echo "<td>" . htmlspecialchars($index['Column_name']) . "</td>";
        echo "<td>" . ($index['Non_unique'] == 0 ? 'Yes' : 'No') . "</td>";
        echo "</tr>";
        
        if ($index['Key_name'] === 'idx_profile_image') {
            $hasProfileIndex = true;
        }
    }
    
    echo "</table>";
    
    // Verification results
    echo "<h2>🔍 Verification Results:</h2>";
    
    if ($hasProfileImage) {
        echo "<p>✅ <strong>profile_image column exists</strong></p>";
    } else {
        echo "<p>❌ <strong>profile_image column missing</strong></p>";
    }
    
    if ($hasProfileIndex) {
        echo "<p>✅ <strong>idx_profile_image index exists</strong></p>";
    } else {
        echo "<p>❌ <strong>idx_profile_image index missing</strong></p>";
    }
    
    // Test with sample data
    echo "<h3>📊 Sample User Data:</h3>";
    $stmt = $pdo->query("SELECT id, name, profile_image FROM users LIMIT 5");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th><th>Profile Image</th><th>Status</th></tr>";
    
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . htmlspecialchars($user['name']) . "</td>";
        echo "<td>" . ($user['profile_image'] ? htmlspecialchars($user['profile_image']) : 'NULL') . "</td>";
        echo "<td>" . ($user['profile_image'] ? '📸 Has Image' : '🔤 No Image') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<h2>🎉 Conclusion:</h2>";
    if ($hasProfileImage && $hasProfileIndex) {
        echo "<p style='color: green; font-weight: bold;'>✅ Database is properly configured for profile images!</p>";
        echo "<p><a href='profile.php'>Go to Profile Page →</a></p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ Database setup incomplete!</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; margin: 10px 0; }
th { background-color: #f2f2f2; padding: 8px; text-align: left; }
td { padding: 8px; border: 1px solid #ddd; }
h1 { color: #333; }
h2 { color: #666; margin-top: 20px; }
h3 { color: #888; margin-top: 15px; }
</style>
