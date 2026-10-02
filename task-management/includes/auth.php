<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Not logged in
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

// Keep session role in sync with DB
$_SESSION['user_role'] = $current_user['role'];
$_SESSION['user_name'] = $current_user['name'];
$_SESSION['section_id'] = $current_user['section_id'];

$CURRENT_USER = $current_user;