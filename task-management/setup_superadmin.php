<?php

require_once 'config/database.php';

$pdo = getDB();

$email = 'superadmin@sipway.com';
$password = 'Super@123';
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
$exists = $stmt->fetch();

if ($exists) {
    $stmt = $pdo->prepare("UPDATE users SET password = ?, role = 'super_admin', status = 'active' WHERE email = ?");
    $stmt->execute([$hash, $email]);
    echo "<h2 style='color:green'>Super Admin password updated!</h2>";
} else {
    $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'super_admin', 'active')");
    $stmt->execute(['Super Admin', $email, '9999999999', $hash]);
    echo "<h2 style='color:green'>Super Admin created successfully!</h2>";
}

echo "<p><strong>Email:</strong> superadmin@sipway.com</p>";
echo "<p><strong>Password:</strong> Super@123</p>";
echo "<p style='color:red'><strong>DELETE this file (setup_superadmin.php) now for security!</strong></p>";
echo "<p><a href='login.php'>Go to Login</a></p>";
?>