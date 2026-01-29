<?php
session_start();
require_once 'db.php';

try {
    $pdo = getDBConnection();

    $name = 'Administrator';
    $email = 'admin@gmail.com';
    $password = 'password';
    $role = 'Admin';

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo "<h2>Admin already exists</h2>";
        echo "<p>Email: " . htmlspecialchars($email) . "</p>";
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $email, $hashedPassword, $role]);

    echo "<h2>Admin account created successfully</h2>";
    echo "<p>Email: " . htmlspecialchars($email) . "</p>";
    echo "<p>Default password: <strong>password</strong></p>";
    echo "<p>You can now log in at <a href=\"login.php\">login.php</a>.</p>";
} catch (PDOException $e) {
    error_log('Create admin error: ' . $e->getMessage());
    echo "<h2>Failed to create admin account</h2>";
    echo "<p>Please check the server logs for more details.</p>";
}

