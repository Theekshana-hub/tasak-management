<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Only Super Admin allowed
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'super_admin') {
    setFlash('danger', 'Access denied.');
    redirect('../login.php');
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('danger', 'Invalid request method.');
    redirect('../superadmin/users.php');
}

// CSRF Token check
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid security token. Please try again.');
    redirect('../superadmin/users.php');
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    setFlash('danger', 'Invalid user ID.');
    redirect('../superadmin/users.php');
}

// Cannot delete yourself
if ($id === (int)$_SESSION['user_id']) {
    setFlash('danger', 'You cannot delete your own account.');
    redirect('../superadmin/users.php');
}

$pdo = getDB();

try {
    // Get user details
    $stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if (!$user) {
        setFlash('danger', 'User not found.');
        redirect('../superadmin/users.php');
    }

    // Cannot delete other Super Admins
    if ($user['role'] === 'super_admin') {
        setFlash('danger', 'You cannot delete another Super Admin.');
        redirect('../superadmin/users.php');
    }

    // Delete the user
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);

    setFlash('success', 'User "' . htmlspecialchars($user['name']) . '" deleted successfully.');
    redirect('../superadmin/users.php');

} catch (Throwable $e) {
    error_log('Delete user (super) failed: ' . $e->getMessage());
    setFlash('danger', 'Could not delete user. Please try again.');
    redirect('../superadmin/users.php');
}