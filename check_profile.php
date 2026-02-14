<?php
require 'db.php';
$pdo = getDBConnection();
$stmt = $pdo->prepare('SELECT id, name, profile_image FROM users WHERE id = 1');
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);
echo "User ID: " . $user['id'] . "\n";
echo "Name: " . $user['name'] . "\n";
echo "Profile Image: " . ($user['profile_image'] ?: 'NULL') . "\n";
?>
