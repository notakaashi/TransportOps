<?php
echo "Starting database test...\n";

try {
    // Test basic MySQL connection first
    $mysql = mysqli_connect('localhost', 'root', '', 'transport_ops');
    
    if ($mysql) {
        echo "MySQL connection: SUCCESS\n";
        
        // Test the database exists
        $db_check = mysqli_select_db($mysql, 'transport_ops');
        if ($db_check) {
            echo "Database selection: SUCCESS\n";
            
            // Test the reports table
            $table_check = mysqli_query($mysql, "SHOW TABLES LIKE 'reports'");
            if ($table_check && mysqli_num_rows($table_check) > 0) {
                echo "Reports table: EXISTS\n";
                
                // Count records
                $count_result = mysqli_query($mysql, "SELECT COUNT(*) FROM reports");
                $count = mysqli_fetch_assoc($count_result);
                echo "Reports count: " . $count['COUNT(*)'] . "\n";
            } else {
                echo "Reports table: NOT FOUND\n";
            }
            
            // Test the users table
            $table_check = mysqli_query($mysql, "SHOW TABLES LIKE 'users'");
            if ($table_check && mysqli_num_rows($table_check) > 0) {
                echo "Users table: EXISTS\n";
                $count_result = mysqli_query($mysql, "SELECT COUNT(*) FROM users");
                $count = mysqli_fetch_assoc($count_result);
                echo "Users count: " . $count['COUNT(*)'] . "\n";
            } else {
                echo "Users table: NOT FOUND\n";
            }
            
        } else {
            echo "Database selection: FAILED\n";
        }
        
        mysqli_close($mysql);
        
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
?>
