<?php
require_once 'db.php';

echo "=== DATABASE DEBUG ===\n";

try {
    $pdo = getDBConnection();
    echo "Connection: " . ($pdo ? "SUCCESS" : "FAILED") . "\n";
    
    if ($pdo) {
        // Check if tables exist
        $tables = $pdo->query("SHOW TABLES");
        echo "Tables:\n";
        while ($table = $tables->fetch()) {
            echo "- " . $table[0] . "\n";
        }
        
        echo "\n=== REPORTS TABLE ===\n";
        $reportsCheck = $pdo->query("SELECT COUNT(*) as count FROM reports");
        if ($reportsCheck) {
            $count = $reportsCheck->fetchColumn();
            echo "Reports table exists, count: $count\n";
        } else {
            echo "Reports table query failed\n";
        }
        
        echo "\n=== USERS TABLE ===\n";
        $usersCheck = $pdo->query("SELECT COUNT(*) as count FROM users");
        if ($usersCheck) {
            $count = $usersCheck->fetchColumn();
            echo "Users table exists, count: $count\n";
        } else {
            echo "Users table query failed\n";
        }
        
        echo "\n=== ROUTES TABLE ===\n";
        $routesCheck = $pdo->query("SELECT COUNT(*) as count FROM route_definitions");
        if ($routesCheck) {
            $count = $routesCheck->fetchColumn();
            echo "Routes table exists, count: $count\n";
        } else {
            echo "Routes table query failed\n";
        }
        
    }
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
