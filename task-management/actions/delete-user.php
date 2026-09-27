<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Only Admin can access
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    setFlash('danger', 'Access denied.');
    redirect('../login.php');
}

// Must be POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('danger', 'Invalid request method.');
    redirect('../admin/users.php');
}

// CSRF Protection
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid request.');
    redirect('../admin/users.php');
}

$id = (int)($_POST['id'] ?? 0);

// Basic validation
if ($id <= 0) {
    setFlash('danger', 'Invalid user ID.');
    redirect('../admin/users.php');
}

// Cannot delete yourself
if ($id == $_SESSION['user_id']) {
    setFlash('danger', 'You cannot delete your own account.');
    redirect('../admin/users.php');
}

$pdo = getDB();

// Get user details
$stmt = $pdo->prepare("SELECT id, role, status FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('danger', 'User not found.');
    redirect('../admin/users.php');
}

// Security: Admin cannot delete Super Admin or other Admins
if (in_array($user['role'], ['super_admin', 'admin'])) {
    setFlash('danger', 'You cannot delete Admin or Super Admin accounts.');
    redirect('../admin/users.php');
}

// Check if user has related tasks
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? OR created_by = ?");
$stmt->execute([$id, $id]);
$taskCount = (int)$stmt->fetchColumn();

if ($taskCount > 0) {
    // Soft delete → just deactivate
    $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
    $stmt->execute([$id]);
    setFlash('warning', 'User has existing tasks. Account has been deactivated instead of deleted.');
} else {
    // Hard delete
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    setFlash('success', 'User deleted successfully.');
}

redirect('../admin/users.php');