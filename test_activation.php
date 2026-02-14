<?php
/**
 * Test file for user activation functionality
 * Access this file at: http://192.168.100.4/system/test_activation.php
 */

require_once 'db.php';

echo "<h2>User Activation Functionality Test</h2>";

try {
    $pdo = getDBConnection();
    
    // Test 1: Check if is_active column exists
    echo "<h3>Test 1: Database Schema Check</h3>";
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('is_active', $columns)) {
        echo "<p style='color: green;'>✓ is_active column exists in users table</p>";
    } else {
        echo "<p style='color: red;'>✗ is_active column missing</p>";
    }
    
    // Test 2: Show current users and their status
    echo "<h3>Test 2: Current Users Status</h3>";
    $stmt = $pdo->query("SELECT id, name, email, role, is_active FROM users ORDER BY id");
    $users = $stmt->fetchAll();
    
    if (count($users) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr>";
        
        foreach ($users as $user) {
            $status = $user['is_active'] ? 'Active' : 'Inactive';
            $color = $user['is_active'] ? 'green' : 'red';
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['name']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>{$user['role']}</td>";
            echo "<td style='color: {$color}; font-weight: bold;'>{$status}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No users found in database.</p>";
    }
    
    // Test 3: Test toggle functionality
    echo "<h3>Test 3: Toggle Functionality Test</h3>";
    if (isset($_GET['toggle_user']) && is_numeric($_GET['toggle_user'])) {
        $user_id = (int)$_GET['toggle_user'];
        $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$user_id]);
        echo "<p style='color: blue;'>✓ User {$user_id} status toggled successfully!</p>";
        echo "<p><a href='test_activation.php'>Refresh to see changes</a></p>";
    } else {
        echo "<p>Add ?toggle_user=USER_ID to URL to test toggle functionality</p>";
    }
    
    echo "<h3>Access Links</h3>";
    echo "<ul>";
    echo "<li><a href='user_management.php'>User Management (Admin only)</a></li>";
    echo "<li><a href='login.php'>Login Page</a></li>";
    echo "<li><a href='admin_dashboard.php'>Admin Dashboard (Admin only)</a></li>";
    echo "<li><a href='user_dashboard.php'>User Dashboard</a></li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
h3 { margin-top: 20px; }
</style>
