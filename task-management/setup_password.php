<?php

require_once 'config/database.php';

$pdo = getDB();
$hash = password_hash('Admin@123', PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = 'admin@sipway.com'");
$stmt->execute([$hash]);

echo "Admin password updated successfully to: Admin@123<br>";
echo "Hash: " . $hash . "<br>";
echo "<strong>Please delete this file (setup_password.php) now for security.</strong>";
?>
