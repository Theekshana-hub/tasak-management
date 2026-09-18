<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';


if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    setFlash('danger', 'Please login to continue.');
    redirect('../login.php');
}


$pdo = getDB();
$stmt = $pdo->prepare("SELECT id, name, email, role, section_id, status FROM users WHERE id = ? AND status = 'active'");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch();

if (!$current_user) {
    session_destroy();
    setFlash('danger', 'Your account is inactive or not found.');
    redirect('../login.php');
}

$CURRENT_USER = $current_user;
?>
