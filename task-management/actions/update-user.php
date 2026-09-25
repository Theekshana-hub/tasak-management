<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    setFlash('danger', 'Access denied.');
    redirect('../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../admin/users.php');
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid request.');
    redirect('../admin/users.php');
    exit;
}

$id       = (int)($_POST['id'] ?? 0);
$name     = trim($_POST['name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$section  = !empty($_POST['section_id']) ? (int)$_POST['section_id'] : null;
$role     = $_POST['role'] ?? 'user';
$status   = $_POST['status'] ?? 'active';
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';

// ===== Basic validation =====
if (empty($name) || empty($email) || $id <= 0) {
    setFlash('danger', 'Invalid data.');
    redirect('../admin/users.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    setFlash('danger', 'Invalid email address.');
    redirect('../admin/edit-user.php?id=' . $id);
    exit;
}

if (!in_array($status, ['active', 'inactive'])) $status = 'active';
if (!in_array($role, ['admin', 'coordinator', 'user'])) $role = 'user';

$pdo = getDB();

// ===== User exists check =====
$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
$stmt->execute([$id]);
if (!$stmt->fetch()) {
    setFlash('danger', 'User not found.');
    redirect('../admin/users.php');
    exit;
}

// ===== Email unique check (exclude current user) =====
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$stmt->execute([$email, $id]);
if ($stmt->fetch()) {
    setFlash('danger', 'Email already used by another user.');
    redirect('../admin/edit-user.php?id=' . $id);
    exit;
}

// ===== Password validation (only if provided) =====
if (!empty($password) || !empty($confirm)) {
    if (strlen($password) < 6) {
        setFlash('danger', 'Password must be at least 6 characters.');
        redirect('../admin/edit-user.php?id=' . $id);
        exit;
    }
    if ($password !== $confirm) {
        setFlash('danger', 'Passwords do not match.');
        redirect('../admin/edit-user.php?id=' . $id);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, phone=?, role=?, section_id=?, status=?, password=? WHERE id=?");
    $stmt->execute([$name, $email, $phone, $role, $section, $status, $hash, $id]);
} else {
    $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, phone=?, role=?, section_id=?, status=? WHERE id=?");
    $stmt->execute([$name, $email, $phone, $role, $section, $status, $id]);
}

setFlash('success', 'User updated successfully.');
redirect('../admin/users.php');
exit;