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
        error_log("Database connection failed: " . $e->getMessage());
        // On localhost, show the real error so you can fix it (MySQL not running, database missing, etc.)
        $isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true)
            || (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'localhost');
        if ($isLocal) {
            die("Database connection failed: " . htmlspecialchars($e->getMessage()) . ". Make sure MySQL is running in XAMPP and the database exists (run database.sql in phpMyAdmin).");
        }
        die("Database connection failed. Please contact the administrator.");
    }
}

