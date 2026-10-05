<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$currentRole = $_SESSION['user_role'] ?? '';


if (!isset($_SESSION['user_id']) || !in_array($currentRole, ['super_admin', 'admin'])) {
    setFlash('danger', 'Access denied.');
    redirect('../login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $redirect = $currentRole === 'super_admin' ? '../superadmin/users.php' : '../admin/users.php';
    redirect($redirect);
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid request.');
    $redirect = $currentRole === 'super_admin' ? '../superadmin/users.php' : '../admin/users.php';
    redirect($redirect);
}

$id         = (int)($_POST['id'] ?? 0);
$name       = trim($_POST['name'] ?? '');
$email      = trim($_POST['email'] ?? '');
$phone      = trim($_POST['phone'] ?? '');
$section_id = $_POST['section_id'] !== '' ? (int)$_POST['section_id'] : null;
$role       = $_POST['role'] ?? 'user';
$status     = $_POST['status'] ?? 'active';
$password   = $_POST['password'] ?? '';
$confirm    = $_POST['confirm_password'] ?? '';

// Redirect helper
function goBack($id = 0) {
    global $currentRole;
    if ($currentRole === 'super_admin') {
        redirect($id > 0 ? '../superadmin/edit-user.php?id=' . $id : '../superadmin/users.php');
    } else {
        redirect($id > 0 ? '../admin/edit-user.php?id=' . $id : '../admin/users.php');
    }
}

// Basic validation
if (empty($name) || empty($email) || $id <= 0) {
    setFlash('danger', 'Name and email are required.');
    goBack($id);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    setFlash('danger', 'Invalid email address.');
    goBack($id);
}

if (!in_array($status, ['active', 'inactive'])) {
    $status = 'active';
}

$pdo = getDB();

// Check user exists
$stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('danger', 'User not found.');
    goBack();
}

// ========== SECURITY RULES ==========

if ($currentRole === 'super_admin') {
    // Super Admin rules
    if ($user['role'] === 'super_admin' && $id != $_SESSION['user_id']) {
        setFlash('danger', 'You cannot edit another Super Admin.');
        goBack();
    }

    // Keep super_admin role
    if ($user['role'] === 'super_admin') {
        $role = 'super_admin';
    } else {
        if (!in_array($role, ['admin', 'coordinator', 'user'])) {
            $role = 'user';
        }
    }

} else {
    // Admin rules
    if ($user['role'] === 'super_admin') {
        setFlash('danger', 'You cannot edit a Super Admin.');
        goBack();
    }

    if ($user['role'] === 'admin' && $id != $_SESSION['user_id']) {
        setFlash('danger', 'You cannot edit other Admins.');
        goBack();
    }

    // Admin can only set coordinator or user
    if (!in_array($role, ['coordinator', 'user'])) {
        $role = 'user';
    }

    // Keep admin role if editing own account
    if ($user['role'] === 'admin') {
        $role = 'admin';
    }
}

// Email unique check
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$stmt->execute([$email, $id]);
if ($stmt->fetch()) {
    setFlash('danger', 'Email already exists.');
    goBack($id);
}

// Password handling
if (!empty($password) || !empty($confirm)) {
    if (strlen($password) < 6) {
        setFlash('danger', 'Password must be at least 6 characters.');
        goBack($id);
    }
    if ($password !== $confirm) {
        setFlash('danger', 'Passwords do not match.');
        goBack($id);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, phone=?, section_id=?, role=?, status=?, password=? WHERE id=?");
    $stmt->execute([$name, $email, $phone, $section_id, $role, $status, $hash, $id]);
} else {
    $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, phone=?, section_id=?, role=?, status=? WHERE id=?");
    $stmt->execute([$name, $email, $phone, $section_id, $role, $status, $id]);
}

setFlash('success', 'User updated successfully.');
goBack(); 