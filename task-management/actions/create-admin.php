<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Only Super Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'super_admin') {
    setFlash('danger', 'Access denied.');
    redirect('../login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../superadmin/admins.php');
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid request.');
    redirect('../superadmin/add-admin.php');
}

$name     = trim($_POST['name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$status   = $_POST['status'] ?? 'active';
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';

if (empty($name) || empty($email) || empty($password)) {
    setFlash('danger', 'Name, email and password are required.');
    redirect('../superadmin/add-admin.php');
}

if ($password !== $confirm) {
    setFlash('danger', 'Passwords do not match.');
    redirect('../superadmin/add-admin.php');
}

$pdo = getDB();

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    setFlash('danger', 'Email already exists.');
    redirect('../superadmin/add-admin.php');
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'admin', ?)");
$stmt->execute([$name, $email, $phone, $hash, $status]);

setFlash('success', 'Admin created successfully.');
redirect('../superadmin/admins.php');
?>