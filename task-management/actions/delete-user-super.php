<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Only Super Admin
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'super_admin') {
    setFlash('danger', 'Access denied.');
    redirect('../login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('danger', 'Invalid request method.');
    redirect('../superadmin/users.php');
}

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

$stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('danger', 'User not found.');
    redirect('../superadmin/users.php');
}

// Cannot delete another Super Admin
if ($user['role'] === 'super_admin') {
    setFlash('danger', 'You cannot delete a Super Admin account.');
    redirect('../superadmin/users.php');
}

// Check related tasks
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? OR created_by = ?");
$stmt->execute([$id, $id]);
$taskCount = (int)$stmt->fetchColumn();

// Set to true if you want users to be hard-deleted even when they have tasks
// (requires the tasks foreign keys to allow it, e.g. ON DELETE SET NULL / CASCADE)
$forceDelete = false;

try {
    if ($taskCount > 0 && !$forceDelete) {
        // Soft delete
        $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('warning', 'User has existing tasks. Account has been deactivated instead of deleted.');
    } else {
        // Hard delete
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'User "' . $user['name'] . '" deleted successfully.');
    }
} catch (PDOException $e) {
    error_log('Delete user failed (ID ' . $id . '): ' . $e->getMessage());
    setFlash('danger', 'Could not delete user. This user may have related records.');
}

redirect('../superadmin/users.php');