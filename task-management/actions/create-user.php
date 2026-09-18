<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Only admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    setFlash('danger', 'Access denied.');
    redirect('../login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../admin/users.php');
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid request.');
    redirect('../admin/add-user.php');
}

$name     = trim($_POST['name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$section  = !empty($_POST['section_id']) ? (int)$_POST['section_id'] : null;
$role     = $_POST['role'] ?? 'user';
$status   = $_POST['status'] ?? 'active';
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';

if (empty($name) || empty($email) || empty($password)) {
    setFlash('danger', 'Name, email and password are required.');
    redirect('../admin/add-user.php');
}

if ($password !== $confirm) {
    setFlash('danger', 'Passwords do not match.');
    redirect('../admin/add-user.php');
}

if (!in_array($role, ['admin', 'coordinator', 'user'])) {
    $role = 'user';
}

$pdo = getDB();

// Check email unique
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    setFlash('danger', 'Email already exists.');
    redirect('../admin/add-user.php');
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, section_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$name, $email, $phone, $hash, $role, $section, $status]);

setFlash('success', 'User created successfully.');
redirect('../admin/users.php');
?>
