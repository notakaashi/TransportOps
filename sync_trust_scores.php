<?php
/**
 * Sync All Trust Scores
 * Updates all users' trust scores to match new calculation
 */
require_once 'trust_helper.php';
require_once 'db.php';

echo "<h1>🔄 Syncing Trust Scores</h1>";

try {
    $pdo = getDBConnection();
    
    // Get all users
    $stmt = $pdo->query("SELECT id, name, trust_score FROM users");
    $users = $stmt->fetchAll();
    
    echo "<h2>Updating " . count($users) . " users...</h2>";
    
    foreach ($users as $user) {
        $oldScore = (float)$user['trust_score'];
        $newScore = calculateTrustScore($user['id']);
        
        echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #ccc;'>";
        echo "<h4>" . htmlspecialchars($user['name']) . " (ID: " . $user['id'] . ")</h4>";
        echo "<p>Old score: " . $oldScore . "</p>";
        echo "<p>New score: " . $newScore . "</p>";
        
        if (abs($oldScore - $newScore) > 0.1) {
            // Update the score
            $updateStmt = $pdo->prepare("UPDATE users SET trust_score = ? WHERE id = ?");
            $updateStmt->execute([$newScore, $user['id']]);
            
            // Log the change
            $logStmt = $pdo->prepare("INSERT INTO trust_score_logs (user_id, old_score, new_score, reason, adjusted_by) VALUES (?, ?, ?, ?, NULL)");
            $logStmt->execute([$user['id'], $oldScore, $newScore, 'Trust scoring system sync']);
            
            echo "<p style='color: green;'>✅ Updated to " . $newScore . "</p>";
        } else {
            echo "<p style='color: blue;'>⏭️ Already up to date</p>";
        }
        echo "</div>";
    }
    
    echo "<h2 style='color: green;'>✅ Trust score sync complete!</h2>";
    echo "<p><a href='user_dashboard.php'>← Back to Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>
