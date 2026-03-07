<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== PHP/MySQL Test ===\n";

try {
    echo "Testing MySQL extension...\n";
    if (!extension_loaded('pdo_mysql')) {
        echo "ERROR: MySQL PDO extension not loaded\n";
        exit;
    }
    echo "MySQL PDO extension: LOADED\n";
    
    echo "Testing database connection...\n";
    require_once 'db.php';
    $pdo = getDBConnection();
    
    if ($pdo) {
        echo "Database connection: SUCCESS\n";
        
        echo "Testing basic query...\n";
        $result = $pdo->query("SELECT 1 as test");
        if ($result) {
            $row = $result->fetch();
            echo "Basic query: SUCCESS (result: " . $row['test'] . ")\n";
        } else {
            echo "Basic query: FAILED\n";
        }
        
        echo "Testing table existence...\n";
        $tables = $pdo->query("SHOW TABLES");
        if ($tables) {
            echo "Available tables:\n";
            while ($table = $tables->fetch()) {
                echo "- " . $table[0] . "\n";
            }
        } else {
            echo "Table query: FAILED\n";
        }
        
    } else {
        echo "Database connection: FAILED\n";
    }
    
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
