<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Only Admin
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    setFlash('danger', 'Access denied.');
    redirect('../login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('danger', 'Invalid request method.');
    redirect('../admin/users.php');
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid security token. Please try again.');
    redirect('../admin/users.php');
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    setFlash('danger', 'Invalid user ID.');
    redirect('../admin/users.php');
}


if ($id === (int)$_SESSION['user_id']) {
    setFlash('danger', 'You cannot delete your own account.');
    redirect('../admin/users.php');
}

$pdo = getDB();

$stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('danger', 'User not found.');
    redirect('../admin/users.php');
}


if ($user['role'] === 'super_admin') {
    setFlash('danger', 'You cannot delete a Super Admin account.');
    redirect('../admin/users.php');
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? OR created_by = ?");
$stmt->execute([$id, $id]);
$taskCount = (int)$stmt->fetchColumn();

try {
    if ($taskCount > 0) {
       
        $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('warning', 'User has existing tasks. Account has been deactivated instead of deleted.');
    } else {
       
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'User "' . e($user['name']) . '" deleted successfully.');
    }
} catch (PDOException $e) {
    setFlash('danger', 'Could not delete user. This user may have related records.');
}

redirect('../admin/users.php');