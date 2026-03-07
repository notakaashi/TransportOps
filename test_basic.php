<?php
echo "Admin Dashboard Test\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Current time: " . date('Y-m-d H:i:s') . "\n";

// Test if we can access the admin dashboard file
if (file_exists('admin_dashboard.php')) {
    echo "admin_dashboard.php: EXISTS\n";
} else {
    echo "admin_dashboard.php: NOT FOUND\n";
}

// Test database connection without requiring full admin authentication
try {
    require_once 'db.php';
    $pdo = getDBConnection();
    if ($pdo) {
        echo "Database connection: SUCCESS\n";
        
        // Test simple query
        $result = $pdo->query("SELECT COUNT(*) FROM reports");
        if ($result) {
            $count = $result->fetchColumn();
            echo "Reports count: $count\n";
        } else {
            echo "Reports query FAILED\n";
        }
    } else {
        echo "Database connection: FAILED\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
