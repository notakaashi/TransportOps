<?php
require_once 'db.php';

try {
    $pdo = getDBConnection();
    
    // Add status to reports table
    $pdo->exec("
        ALTER TABLE reports 
        ADD COLUMN rejections INT DEFAULT 0 NOT NULL;
    ");

    // Create report_rejections table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS report_rejections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            report_id INT NOT NULL,
            rejecter_user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
            FOREIGN KEY (rejecter_user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");

    echo "Migration successful!";
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage());
}
