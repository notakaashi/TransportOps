<?php
/**
 * Database Connection Configuration
 * Establishes secure PDO connection to MySQL database
 */

// Database configuration constants
define('DB_HOST', 'localhost');
define('DB_NAME', 'transport_ops');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get database connection using PDO
 * Returns PDO instance or throws exception on failure
 */
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // Log error in production, show generic message to user
        error_log("Database connection failed: " . $e->getMessage());
        die("Database connection failed. Please contact the administrator.");
    }
}

