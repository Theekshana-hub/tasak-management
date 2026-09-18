<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    setFlash('danger', 'Access denied.');
    redirect('../login.php');
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0 || $id == $_SESSION['user_id']) {
    setFlash('danger', 'Cannot delete this user.');
    redirect('../admin/users.php');
}

$pdo = getDB();

// Check if user has tasks
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? OR created_by = ?");
$stmt->execute([$id, $id]);
$count = $stmt->fetchColumn();

if ($count > 0) {
    // Soft delete - deactivate instead
    $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
    $stmt->execute([$id]);
    setFlash('warning', 'User has existing tasks. Account has been deactivated instead of deleted.');
} else {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    setFlash('success', 'User deleted successfully.');
}

redirect('../admin/users.php');
?>
