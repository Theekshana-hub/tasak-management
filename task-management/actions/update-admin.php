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
    redirect('../superadmin/admins.php');
}

$id       = (int)($_POST['id'] ?? 0);
$name     = trim($_POST['name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$status   = $_POST['status'] ?? 'active';
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';

if (empty($name) || empty($email) || $id <= 0) {
    setFlash('danger', 'Name and email are required.');
    redirect('../superadmin/edit-admin.php?id=' . $id);
}

if (!empty($password) && $password !== $confirm) {
    setFlash('danger', 'Passwords do not match.');
    redirect('../superadmin/edit-admin.php?id=' . $id);
}

$pdo = getDB();

$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'admin'");
$stmt->execute([$id]);
if (!$stmt->fetch()) {
    setFlash('danger', 'Admin not found.');
    redirect('../superadmin/admins.php');
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$stmt->execute([$email, $id]);
if ($stmt->fetch()) {
    setFlash('danger', 'Email already exists.');
    redirect('../superadmin/edit-admin.php?id=' . $id);
}

if (!empty($password)) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, password = ?, status = ? WHERE id = ?");
    $stmt->execute([$name, $email, $phone, $hash, $status, $id]);
} else {
    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, status = ? WHERE id = ?");
    $stmt->execute([$name, $email, $phone, $status, $id]);
}

setFlash('success', 'Admin updated successfully.');
redirect('../superadmin/admins.php');
?>