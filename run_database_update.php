<?php
/**
 * Run Database Update Script
 */
require_once 'db.php';

echo "<h1>🗄️ Running Database Update</h1>";

try {
    $pdo = getDBConnection();
    
    // Read and execute the SQL update
    $sqlFile = __DIR__ . '/database_update_rejected_reports.sql';
    $sql = file_get_contents($sqlFile);
    
    if ($sql === false) {
        throw new Exception("Could not read SQL file: $sqlFile");
    }
    
    // Split SQL statements by semicolon
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    echo "<h2>Executing " . count($statements) . " SQL statements...</h2>";
    
    foreach ($statements as $i => $statement) {
        if (!empty($statement)) {
            echo "<p><strong>Statement " . ($i + 1) . ":</strong> " . htmlspecialchars($statement) . "</p>";
            
            try {
                $result = $pdo->exec($statement);
                echo "<p style='color: green;'>✅ Success</p>";
            } catch (PDOException $e) {
                echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }
    
    echo "<h2 style='color: green;'>✅ Database update complete!</h2>";
    echo "<p><a href='debug_trust_score.php'>🔍 Debug Trust Score</a></p>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>
